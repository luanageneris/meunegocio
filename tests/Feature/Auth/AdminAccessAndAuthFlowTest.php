<?php

test('registration routes redirect to login and are not available', function () {
    $response = $this->get('/registre-se');
    $response->assertRedirect('/login');

    $registerResponse = $this->get('/register');
    $registerResponse->assertRedirect('/login');
});

test('master admin can see registered users and create a new user access', function () {
    $master = \App\Models\User::factory()->root()->create([
        'email' => 'admin@meunegocio.cloud',
        'name' => 'Administrador Master',
        'password' => bcrypt('secret123'),
    ]);

    $user = \App\Models\User::factory()->create([
        'email' => 'usuario@empresa.com',
        'name' => 'Usuário da Empresa',
        'password' => bcrypt('secret123'),
    ]);

    $this->actingAs($master, 'web');

    $list = $this->getJson('/api/admin/users');
    $list->assertOk();
    $list->assertJsonFragment(['email' => $user->email]);
    $list->assertJsonFragment(['email' => $master->email]);

    $created = $this->postJson('/api/admin/users', [
        'name' => 'Novo Usuário',
        'email' => 'novo-acesso@empresa.com',
        'password' => 'Senha@123',
        'role' => 'owner',
    ]);

    $created->assertStatus(201);
    $this->assertDatabaseHas('users', ['email' => 'novo-acesso@empresa.com']);
});

test('master admin can reset user password without current password', function () {
    $master = \App\Models\User::factory()->root()->create([
        'email' => 'admin@meunegocio.cloud',
        'name' => 'Administrador Master',
        'password' => bcrypt('secret123'),
    ]);

    $user = \App\Models\User::factory()->create([
        'email' => 'resetar@empresa.com',
        'name' => 'Usuário Reset',
        'password' => bcrypt('senha-antiga-123'),
    ]);

    $oldHash = $user->password;

    $this->actingAs($master, 'web');

    $response = $this->postJson("/api/admin/users/{$user->id}/reset-password");

    $response->assertOk();
    $response->assertJsonPath('data.user_id', $user->id);
    $response->assertJsonPath('data.email', $user->email);
    $response->assertJsonPath('message', 'Senha redefinida com sucesso.');

    $generatedPassword = $response->json('data.new_password');
    expect($generatedPassword)->toBeString()->not->toBe('');

    $user->refresh();
    expect($user->password)->not->toBe($oldHash);
    $this->assertTrue(\Illuminate\Support\Facades\Hash::check($generatedPassword, $user->password));
});

test('successful login updates the last login timestamp', function () {
    $user = \App\Models\User::factory()->create([
        'email' => 'teste-login@empresa.com',
        'name' => 'Usuário de Login',
        'password' => bcrypt('secret123'),
        'last_login_at' => null,
    ]);

    $response = $this->post('/login', [
        'email' => 'teste-login@empresa.com',
        'password' => 'secret123',
    ]);

    $response->assertRedirect('/dashboard');
    $user->refresh();
    expect($user->last_login_at)->not->toBeNull();
});

test('legacy google users without password can use the initial login password', function () {
    $user = \App\Models\User::factory()->create([
        'email' => 'google-legacy@empresa.com',
        'name' => 'Usuário Google Antigo',
        'google_id' => 'legacy-google-123',
        'password' => null,
    ]);

    \App\Models\User::applyInitialPasswordForGoogleUsers();

    $this->assertNotNull($user->fresh()->password);
    $this->assertTrue(\Illuminate\Support\Facades\Hash::check(\App\Models\User::INITIAL_PASSWORD, $user->fresh()->password));

    $response = $this->post('/login', [
        'email' => 'google-legacy@empresa.com',
        'password' => \App\Models\User::INITIAL_PASSWORD,
    ]);

    $response->assertRedirect('/dashboard');
    $this->assertAuthenticated();
});
