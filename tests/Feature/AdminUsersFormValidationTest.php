<?php

test('admin create user form requires all fields to be filled', function () {
    $master = \App\Models\User::factory()->root()->create([
        'email' => 'admin@meunegocio.cloud',
        'name' => 'Administrador Master',
        'password' => bcrypt('secret123'),
    ]);

    $this->actingAs($master, 'web');

    // Test with missing name
    $response = $this->postJson('/api/admin/users', [
        'name' => '',
        'email' => 'test@empresa.com',
        'password' => 'Senha@123',
        'role' => 'owner',
    ]);

    $response->assertStatus(422); // Validation error

    // Test with missing email
    $response = $this->postJson('/api/admin/users', [
        'name' => 'Test User',
        'email' => '',
        'password' => 'Senha@123',
        'role' => 'owner',
    ]);

    $response->assertStatus(422); // Validation error

    // Test with missing password
    $response = $this->postJson('/api/admin/users', [
        'name' => 'Test User',
        'email' => 'test@empresa.com',
        'password' => '',
        'role' => 'owner',
    ]);

    $response->assertStatus(422); // Validation error

    // Test with all fields filled - should succeed
    $response = $this->postJson('/api/admin/users', [
        'name' => 'Test User',
        'email' => 'test-complete@empresa.com',
        'password' => 'Senha@123',
        'role' => 'owner',
    ]);

    $response->assertStatus(201);
    $this->assertDatabaseHas('users', ['email' => 'test-complete@empresa.com']);
});
