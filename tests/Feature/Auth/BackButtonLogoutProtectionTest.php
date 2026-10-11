<?php

use App\Models\User;

test('authenticated pages include no-store headers', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));
    $cacheControl = (string) $response->headers->get('Cache-Control');

    $response->assertOk();
    expect($cacheControl)->toContain('no-store');
    expect($cacheControl)->toContain('no-cache');
    expect($cacheControl)->toContain('must-revalidate');
    expect($cacheControl)->toContain('max-age=0');
    expect($cacheControl)->toContain('private');
    $response->assertHeader('Pragma', 'no-cache');
    $response->assertHeader('Expires', 'Fri, 01 Jan 1990 00:00:00 GMT');
});

test('user cannot access protected pages after logout', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('logout'))->assertRedirect(route('home'));

    $this->get(route('dashboard'))->assertRedirect(route('login'));
});
