<?php

namespace Tests\Feature\Api;

use App\Models\Collaborator;
use App\Models\User;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollaboratorApiTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $rootUser;
    protected User $ownerUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        usleep(10000);
        $ts = time() . intval((microtime(true) - floor(microtime(true))) * 10000);
        
        $this->company = Company::create([
            'name' => 'Collab Company ' . $ts,
            'slug' => 'collab-company-' . $ts,
            'email' => 'collab+' . $ts . '@test.com',
            'phone' => '123456789',
            'document' => $ts . '.345.678/0001-90',
            'address' => 'Test Address'
        ]);

        $this->rootUser = User::create([
            'name' => 'Root User Collab',
            'email' => 'root+collab+' . $ts . '@example.com',
            'password' => bcrypt('password'),
            'company_id' => $this->company->id,
            'google_id' => 'google-root-collab-' . $ts,
            'role' => 'root'
        ]);

        $this->ownerUser = User::create([
            'name' => 'Owner User Collab',
            'email' => 'owner+collab+' . $ts . '@example.com',
            'password' => bcrypt('password'),
            'company_id' => $this->company->id,
            'google_id' => 'google-owner-collab-' . $ts,
            'role' => 'owner'
        ]);
    }

    public function test_can_list_collaborators()
    {
        Collaborator::create([
            'name' => 'João Silva',
            'cpf' => '123.456.789-00',
            'active' => true,
            'company_id' => $this->company->id
        ]);
        Collaborator::create([
            'name' => 'Maria Santos',
            'cpf' => '123.456.789-01',
            'active' => true,
            'company_id' => $this->company->id
        ]);

        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->getJson('/api/personalizar/collaborators');

        $response->assertStatus(200)
            ->assertJsonCount(2);
    }

    public function test_root_can_create_collaborator()
    {
        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->postJson('/api/personalizar/collaborators', [
                'name' => 'João Silva',
                'cpf' => '123.456.789-00',
                'active' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'name', 'cpf', 'company_id', 'active']);

        $this->assertDatabaseHas('collaborators', [
            'name' => 'João Silva',
            'company_id' => $this->company->id,
        ]);
    }

    public function test_root_can_update_collaborator()
    {
        $collaborator = Collaborator::create([
            'name' => 'João Silva',
            'cpf' => '123.456.789-00',
            'active' => true,
            'company_id' => $this->company->id
        ]);

        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->putJson("/api/personalizar/collaborators/{$collaborator->id}", [
                'name' => 'João Silva Atualizado',
                'cpf' => $collaborator->cpf,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('collaborators', [
            'id' => $collaborator->id,
            'name' => 'João Silva Atualizado',
        ]);
    }

    public function test_root_can_delete_collaborator()
    {
        $collaborator = Collaborator::create([
            'name' => 'João Silva',
            'cpf' => '123.456.789-00',
            'active' => true,
            'company_id' => $this->company->id
        ]);

        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->deleteJson("/api/personalizar/collaborators/{$collaborator->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('collaborators', ['id' => $collaborator->id]);
    }

    public function test_owner_cannot_delete_collaborator()
    {
        $collaborator = Collaborator::create([
            'name' => 'João Silva',
            'cpf' => '123.456.789-00',
            'active' => true,
            'company_id' => $this->company->id
        ]);

        $response = $this->actingAs($this->ownerUser, 'sanctum')
            ->deleteJson("/api/personalizar/collaborators/{$collaborator->id}");

        $response->assertStatus(403);
    }

    public function test_cannot_access_collaborator_from_other_company()
    {
        usleep(10000);
        $ts = time() . intval((microtime(true) - floor(microtime(true))) * 10000);
        
        $otherCompany = Company::create([
            'name' => 'Other Access Company ' . $ts,
            'slug' => 'other-access-' . $ts,
            'email' => 'other-access+' . $ts . '@test.com',
            'phone' => '987654321',
            'document' => $ts . '.765.432/0001-11',
            'address' => 'Other Address'
        ]);
        $collaborator = Collaborator::create([
            'name' => 'Pedro',
            'cpf' => '123.456.789-03',
            'active' => true,
            'company_id' => $otherCompany->id
        ]);

        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->getJson("/api/personalizar/collaborators/{$collaborator->id}");

        $response->assertStatus(403);
    }

    public function test_cpf_must_be_unique()
    {
        Collaborator::create([
            'name' => 'João Silva',
            'cpf' => '123.456.789-00',
            'active' => true,
            'company_id' => $this->company->id
        ]);

        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->postJson('/api/personalizar/collaborators', [
                'name' => 'Outro',
                'cpf' => '123.456.789-00',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('cpf');
    }
}
