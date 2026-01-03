<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        usleep(10000); // 10ms para garantir timestamp único
        $ts = time() . intval((microtime(true) - floor(microtime(true))) * 10000);
        
        $this->company = Company::create([
            'name' => 'Test Company Auth ' . $ts,
            'slug' => 'test-company-auth-' . $ts,
            'email' => 'test+' . $ts . '@auth.com',
            'phone' => '123456789',
            'document' => $ts . '.345.678/0001-90',
            'address' => 'Test Address'
        ]);
        
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'testuser+' . $ts . '@example.com',
            'password' => bcrypt('password'),
            'company_id' => $this->company->id,
            'google_id' => 'google-123-' . $ts,
            'role' => 'owner',
        ]);
    }

    public function test_user_can_get_current_user_info()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/auth/user');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'company_id', 'role', 'company']
            ]);
    }

    public function test_user_can_logout()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/auth/logout');

        $response->assertStatus(200)
            ->assertJson(['message' => 'Desconectado com sucesso']);
    }

    public function test_root_can_impersonate_user()
    {
        $ts = time();
        $root = User::create([
            'name' => 'Root User',
            'email' => 'root+' . $ts . '@example.com',
            'password' => bcrypt('password'),
            'company_id' => $this->company->id,
            'google_id' => 'google-root-' . $ts,
            'role' => 'root',
        ]);

        $response = $this->actingAs($root, 'sanctum')
            ->postJson("/api/auth/impersonate/{$this->user->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email'],
                'token',
                'message'
            ]);
    }

    public function test_non_root_cannot_impersonate()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/auth/impersonate/{$this->user->id}");

        $response->assertStatus(403);
    }

    public function test_cannot_impersonate_user_from_different_company()
    {
        $ts = time();
        $otherCompany = Company::create([
            'name' => 'Other Company ' . $ts,
            'slug' => 'other-company-' . $ts,
            'email' => 'other+' . $ts . '@company.com',
            'phone' => '987654321',
            'document' => $ts . '.765.432/0001-10',
            'address' => 'Other Address'
        ]);

        $root = User::create([
            'name' => 'Root User 2',
            'email' => 'root2+' . $ts . '@example.com',
            'password' => bcrypt('password'),
            'company_id' => $this->company->id,
            'google_id' => 'google-root-2-' . $ts,
            'role' => 'root',
        ]);

        $otherUser = User::create([
            'name' => 'Other User',
            'email' => 'otheruser+' . $ts . '@example.com',
            'password' => bcrypt('password'),
            'company_id' => $otherCompany->id,
            'google_id' => 'google-other-' . $ts,
            'role' => 'owner',
        ]);

        $response = $this->actingAs($root, 'sanctum')
            ->postJson("/api/auth/impersonate/{$otherUser->id}");

        $response->assertStatus(403);
    }
}
