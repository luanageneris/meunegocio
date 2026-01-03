<?php

namespace Tests\Feature\Api;

use App\Models\ExitType;
use App\Models\User;
use App\Models\Company;
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
}
