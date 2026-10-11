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
