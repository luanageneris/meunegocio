<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Company;
use App\Models\Collaborator;
use App\Models\EntryType;
use App\Models\ExitType;
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

    public function test_google_registration_creates_company_with_slug_and_default_records()
    {
        $payload = [
            'name' => 'Nova Empresa',
            'email' => 'registro@example.com',
            'google_id' => 'google-register-123',
            'expires_at' => now()->addMinutes(10)->timestamp,
        ];

        $response = $this->withCookie('pending_registration', json_encode($payload))
            ->postJson('/api/auth/register', [
                'company_name' => 'Eletronica',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.email', 'registro@example.com')
            ->assertJsonPath('data.user.role', 'root')
            ->assertJsonPath('data.user.company.slug', 'eletronica');

        $company = Company::firstWhere('slug', 'eletronica');

        $this->assertNotNull($company);
        $this->assertNotNull($company->owner_id);
        $this->assertDatabaseHas('entry_types', [
            'company_id' => $company->id,
            'name' => 'Não definido',
        ]);
        $this->assertDatabaseHas('exit_types', [
            'company_id' => $company->id,
            'name' => 'Não definido',
        ]);
        $this->assertDatabaseHas('collaborators', [
            'company_id' => $company->id,
            'name' => 'Não definido',
        ]);
    }

    public function test_google_registration_generates_unique_slug_for_duplicate_company_name()
    {
        Company::create([
            'name' => 'Empresa Base',
            'slug' => 'eletronica',
            'email' => 'empresa-base@example.com',
        ]);

        $payload = [
            'name' => 'Outra Empresa',
            'email' => 'registro2@example.com',
            'google_id' => 'google-register-456',
            'expires_at' => now()->addMinutes(10)->timestamp,
        ];

        $response = $this->withCookie('pending_registration', json_encode($payload))
            ->postJson('/api/auth/register', [
                'company_name' => 'Eletronica 2',
            ]);

        $response->assertCreated();

        $this->assertDatabaseHas('companies', [
            'name' => 'Eletronica 2',
            'slug' => 'eletronica-2',
        ]);
    }
}
