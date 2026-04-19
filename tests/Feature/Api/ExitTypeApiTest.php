<?php

namespace Tests\Feature\Api;

use App\Models\ExitType;
use App\Models\User;
use App\Models\Company;
use App\Services\CompanyDefaultsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExitTypeApiTest extends TestCase
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
            'name' => 'Exit Company ' . $ts,
            'slug' => 'exit-company-' . $ts,
            'email' => 'exit+' . $ts . '@test.com',
            'phone' => '123456789',
            'document' => $ts . '.345.678/0001-92',
            'address' => 'Test Address'
        ]);

        $this->rootUser = User::create([
            'name' => 'Root User Exit',
            'email' => 'root+exit+' . $ts . '@example.com',
            'password' => bcrypt('password'),
            'company_id' => $this->company->id,
            'google_id' => 'google-root-exit-' . $ts,
            'role' => 'root'
        ]);
    }

    public function test_can_list_exit_types()
    {
        ExitType::create(['name' => 'Comissão', 'company_id' => $this->company->id]);
        ExitType::create(['name' => 'Material', 'company_id' => $this->company->id]);
        ExitType::create(['name' => 'Energia', 'company_id' => $this->company->id]);

        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->getJson('/api/personalizar/exit-types');

        $response->assertStatus(200)
            ->assertJsonCount(3);
    }

    public function test_default_exit_type_is_hidden_from_list(): void
    {
        ExitType::create([
            'name' => CompanyDefaultsService::DEFAULT_LABEL,
            'company_id' => $this->company->id,
        ]);
        ExitType::create(['name' => 'Comissão comum', 'company_id' => $this->company->id]);

        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->getJson('/api/personalizar/exit-types');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonFragment(['name' => 'Comissão comum'])
            ->assertJsonMissing(['name' => CompanyDefaultsService::DEFAULT_LABEL]);
    }

    public function test_can_create_exit_type()
    {
        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->postJson('/api/personalizar/exit-types', [
                'name' => 'Comissão',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'name', 'company_id']);

        $this->assertDatabaseHas('exit_types', [
            'name' => 'Comissão',
            'company_id' => $this->company->id,
        ]);
    }

    public function test_can_update_exit_type()
    {
        $exitType = ExitType::create([
            'name' => 'Comissão',
            'company_id' => $this->company->id
        ]);

        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->putJson("/api/personalizar/exit-types/{$exitType->id}", [
                'name' => 'Comissão Atualizado',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('exit_types', [
            'id' => $exitType->id,
            'name' => 'Comissão Atualizado',
        ]);
    }

    public function test_can_delete_exit_type()
    {
        $exitType = ExitType::create([
            'name' => 'Comissão',
            'company_id' => $this->company->id
        ]);

        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->deleteJson("/api/personalizar/exit-types/{$exitType->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('exit_types', ['id' => $exitType->id]);
    }

    public function test_exit_type_name_must_be_unique()
    {
        ExitType::create([
            'name' => 'Comissão',
            'company_id' => $this->company->id
        ]);

        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->postJson('/api/personalizar/exit-types', [
                'name' => 'Comissão',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_cannot_update_or_delete_default_exit_type(): void
    {
        $default = ExitType::create([
            'name' => CompanyDefaultsService::DEFAULT_LABEL,
            'company_id' => $this->company->id,
        ]);

        $updateResponse = $this->actingAs($this->rootUser, 'sanctum')
            ->putJson("/api/personalizar/exit-types/{$default->id}", [
                'name' => 'Outro nome',
            ]);

        $updateResponse->assertStatus(422);

        $deleteResponse = $this->actingAs($this->rootUser, 'sanctum')
            ->deleteJson("/api/personalizar/exit-types/{$default->id}");

        $deleteResponse->assertStatus(422);
        $this->assertDatabaseHas('exit_types', ['id' => $default->id, 'deleted_at' => null]);
    }
}
