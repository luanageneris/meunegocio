<?php

namespace Tests\Feature\Api;

use App\Models\EntryType;
use App\Models\User;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntryTypeApiTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $rootUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        usleep(10000); // 10ms para garantir timestamp único
        $ts = time() . intval((microtime(true) - floor(microtime(true))) * 10000);
        
        $this->company = Company::create([
            'name' => 'Entry Company ' . $ts,
            'slug' => 'entry-company-' . $ts,
            'email' => 'entry+' . $ts . '@test.com',
            'phone' => '123456789',
            'document' => $ts . '.345.678/0001-90',
            'address' => 'Test Address'
        ]);

        $this->rootUser = User::create([
            'name' => 'Root User Entry',
            'email' => 'root+entry+' . $ts . '@example.com',
            'password' => bcrypt('password'),
            'company_id' => $this->company->id,
            'google_id' => 'google-root-entry-' . $ts,
            'role' => 'root'
        ]);
    }

    public function test_can_list_entry_types()
    {
        EntryType::create(['name' => 'Serviço 1', 'requires_collaborator' => false, 'company_id' => $this->company->id]);
        EntryType::create(['name' => 'Serviço 2', 'requires_collaborator' => true, 'company_id' => $this->company->id]);
        EntryType::create(['name' => 'Serviço 3', 'requires_collaborator' => false, 'company_id' => $this->company->id]);

        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->getJson('/api/personalizar/entry-types');

        $response->assertStatus(200)
            ->assertJsonCount(3);
    }

    public function test_can_create_entry_type()
    {
        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->postJson('/api/personalizar/entry-types', [
                'name' => 'Serviço',
                'requires_collaborator' => false,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'name', 'requires_collaborator', 'company_id']);

        $this->assertDatabaseHas('entry_types', [
            'name' => 'Serviço',
            'company_id' => $this->company->id,
        ]);
    }

    public function test_can_update_entry_type()
    {
        $entryType = EntryType::create([
            'name' => 'Serviço',
            'requires_collaborator' => false,
            'company_id' => $this->company->id
        ]);

        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->putJson("/api/personalizar/entry-types/{$entryType->id}", [
                'name' => 'Serviço Atualizado',
                'requires_collaborator' => true,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('entry_types', [
            'id' => $entryType->id,
            'name' => 'Serviço Atualizado',
            'requires_collaborator' => true,
        ]);
    }

    public function test_can_delete_entry_type()
    {
        $entryType = EntryType::create([
            'name' => 'Serviço',
            'requires_collaborator' => false,
            'company_id' => $this->company->id
        ]);

        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->deleteJson("/api/personalizar/entry-types/{$entryType->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('entry_types', ['id' => $entryType->id]);
    }

    public function test_entry_type_name_must_be_unique()
    {
        EntryType::create([
            'name' => 'Serviço',
            'requires_collaborator' => false,
            'company_id' => $this->company->id
        ]);

        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->postJson('/api/personalizar/entry-types', [
                'name' => 'Serviço',
                'requires_collaborator' => false,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }
}
