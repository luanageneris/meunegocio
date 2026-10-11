<?php

use App\Models\AuditLog;
use App\Models\User;
use Laravel\Fortify\Features;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertStatus(200);
});

test('authenticated users are redirected away from login screen', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    $response = $this->actingAs($user)->get(route('login'));

    $response->assertRedirect(route('dashboard', absolute: false));
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two-factor authentication is not enabled.');
    }

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->create();

    $user->forceFill([
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $response->assertSessionHas('login.id', $user->id);
    $this->assertGuest();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();

    $auditLog = AuditLog::query()
        ->where('user_id', $user->id)
        ->where('action', 'login_failed')
        ->latest('id')
        ->first();

    expect($auditLog)->not->toBeNull();
    expect($auditLog?->new_values['event'] ?? null)->toBe('login_failed');
    expect($auditLog?->new_values['ip_address'] ?? null)->toBe('127.0.0.1');
    expect($auditLog?->new_values['location'] ?? null)->toBe('Localizacao indisponivel');
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $this->assertGuest();
    $response->assertRedirect(route('home'));
});

test('users are rate limited', function () {
    $user = User::factory()->create();

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);
    }

    $response = $this->postJson(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertTooManyRequests();
    $response->assertJsonPath('message', 'Muitas tentativas de login. Tente novamente em 15 minuto(s).');
});

test('successful login is stored in audit history with ip and location', function () {
    $user = User::factory()->create([
        'last_login_at' => null,
    ]);

    $this->withServerVariables([
        'REMOTE_ADDR' => '177.1.1.1',
    ])->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $auditLog = AuditLog::query()
        ->where('user_id', $user->id)
        ->where('action', 'login')
        ->latest('id')
        ->first();

    expect($auditLog)->not->toBeNull();
    expect($auditLog?->ip_address)->toBe('177.1.1.1');
    expect($auditLog?->new_values['location'] ?? null)->toBe('Localizacao indisponivel');
    expect($auditLog?->new_values['login_at'] ?? null)->not->toBeNull();
});

test('root user can unlock login attempts before decay window', function () {
    $company = \App\Models\Company::factory()->create();

    $root = User::factory()->root()->create([
        'company_id' => $company->id,
    ]);

    $lockedUser = User::factory()->create([
        'company_id' => $company->id,
    ]);

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $this->withServerVariables([
            'REMOTE_ADDR' => '177.1.1.10',
        ])->post(route('login.store'), [
            'email' => $lockedUser->email,
            'password' => 'wrong-password',
        ]);
    }

    $this->withServerVariables([
        'REMOTE_ADDR' => '177.1.1.10',
    ])->postJson(route('login.store'), [
        'email' => $lockedUser->email,
        'password' => 'wrong-password',
    ])->assertTooManyRequests();

    $lockoutAudit = AuditLog::query()
        ->where('user_id', $lockedUser->id)
        ->where('action', 'login_failed')
        ->latest('id')
        ->get()
        ->first(fn (AuditLog $log) => ($log->new_values['event'] ?? null) === 'login_locked');

    expect($lockoutAudit)->not->toBeNull();
    expect($lockoutAudit?->new_values['ip_address'] ?? null)->toBe('177.1.1.10');
    expect($lockoutAudit?->new_values['location'] ?? null)->toBe('Localizacao indisponivel');

    $unlockResponse = $this->actingAs($root, 'web')->postJson('/api/auth/login-attempts/unlock', [
        'email' => $lockedUser->email,
        'ip_address' => '177.1.1.10',
    ]);

    $unlockResponse->assertOk();
    $unlockResponse->assertJsonPath('data.email', strtolower($lockedUser->email));

    $this->withServerVariables([
        'REMOTE_ADDR' => '177.1.1.10',
    ])->post(route('login.store'), [
        'email' => $lockedUser->email,
        'password' => 'wrong-password',
    ])->assertStatus(302);
});