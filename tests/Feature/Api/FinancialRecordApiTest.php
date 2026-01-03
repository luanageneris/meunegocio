<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\User;
use App\Models\Collaborator;
use App\Models\EntryType;
use App\Models\ExitType;
use App\Models\ServiceType;
use App\Models\FinancialRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialRecordApiTest extends TestCase
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
            'name' => 'Test Company ' . $ts,
            'slug' => 'test-company-' . $ts,
            'email' => 'test+' . $ts . '@example.com',
            'document' => $ts . '.345.678/0001-90',
            'website' => 'https://example.com',
            'address' => 'Rua Teste, 123',
            'city' => 'São Paulo',
            'state' => 'SP',
            'zip_code' => '01234-567',
        ]);

        $this->rootUser = User::create([
            'name' => 'Root User',
            'email' => 'root+' . $ts . '@example.com',
            'password' => bcrypt('password'),
            'company_id' => $this->company->id,
            'role' => 'root',
        ]);

        $this->ownerUser = User::create([
            'name' => 'Owner User',
            'email' => 'owner+' . $ts . '@example.com',
            'password' => bcrypt('password'),
            'company_id' => $this->company->id,
            'role' => 'owner',
        ]);
    }

    public function test_can_list_financial_records(): void
    {
        FinancialRecord::factory(5)->for($this->company)->create();

        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->getJson('/api/registros/financial-records');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'date', 'amount', 'description']
                ]
            ]);
    }

    public function test_root_can_create_financial_record(): void
    {
        $entryType = EntryType::factory()->for($this->company)->create();
        $collaborator = Collaborator::factory()->for($this->company)->create();

        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->postJson('/api/registros/financial-records', [
                'date' => '2025-01-15',
                'amount' => 150.50,
                'description' => 'Test entry',
                'entry_type_id' => $entryType->id,
                'collaborator_id' => $collaborator->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.description', 'Test entry')
            ->assertJsonPath('data.amount', '150.50');

        $this->assertDatabaseHas('financial_records', [
            'description' => 'Test entry',
            'company_id' => $this->company->id,
        ]);
    }

    public function test_root_can_update_financial_record(): void
    {
        $record = FinancialRecord::factory()->for($this->company)->create([
            'amount' => 100.00,
            'description' => 'Original',
        ]);

        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->putJson("/api/registros/financial-records/{$record->id}", [
                'date' => $record->date,
                'amount' => 200.00,
                'description' => 'Updated',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.amount', '200.00')
            ->assertJsonPath('data.description', 'Updated');

        $this->assertDatabaseHas('financial_records', [
            'id' => $record->id,
            'amount' => 200.00,
        ]);
    }

    public function test_root_can_delete_financial_record(): void
    {
        $record = FinancialRecord::factory()->for($this->company)->create();

        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->deleteJson("/api/registros/financial-records/{$record->id}");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Registro financeiro removido com sucesso');

        $this->assertSoftDeleted('financial_records', ['id' => $record->id]);
    }

    public function test_owner_cannot_delete_financial_record(): void
    {
        $record = FinancialRecord::factory()->for($this->company)->create();

        $response = $this->actingAs($this->ownerUser, 'sanctum')
            ->deleteJson("/api/registros/financial-records/{$record->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('financial_records', ['id' => $record->id]);
    }

    public function test_cannot_access_financial_record_from_other_company(): void
    {
        $otherCompany = Company::factory()->create();
        $otherRecord = FinancialRecord::factory()->for($otherCompany)->create();

        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->getJson("/api/registros/financial-records/{$otherRecord->id}");

        $response->assertStatus(403);
    }

    public function test_can_get_financial_records_by_date(): void
    {
        $date = '2025-01-15';
        FinancialRecord::factory(3)->for($this->company)->create(['date' => $date]);
        FinancialRecord::factory(2)->for($this->company)->create(['date' => '2025-01-16']);

        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->getJson("/api/registros/financial-records/by-date/{$date}");

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_can_get_daily_totals(): void
    {
        $date = '2025-01-15';
        
        EntryType::factory()->for($this->company)->create();
        ExitType::factory()->for($this->company)->create();

        FinancialRecord::factory()
            ->for($this->company)
            ->create([
                'date' => $date,
                'amount' => '100.00',
                'entry_type_id' => EntryType::first()->id,
                'exit_type_id' => null,
            ]);

        FinancialRecord::factory()
            ->for($this->company)
            ->create([
                'date' => $date,
                'amount' => '30.00',
                'entry_type_id' => null,
                'exit_type_id' => ExitType::first()->id,
            ]);

        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->postJson('/api/registros/financial-records/daily-totals', [
                'date' => $date,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.entries', '100.00')
            ->assertJsonPath('data.exits', '30.00')
            ->assertJsonPath('data.profit', '70.00');
    }
}
