<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\User;
use App\Models\EntryType;
use App\Models\ExitType;
use App\Models\FinancialRecord;
use App\Models\Balance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BalanceApiTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $rootUser;

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
    }

    public function test_can_get_current_balance(): void
    {
        Balance::factory()->for($this->company)->create([
            'opening_balance' => 1000.00,
            'closing_balance' => 1200.00,
            'reference_date' => today()->subDays(5),
        ]);

        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->getJson('/api/registros/balance');

        $response->assertStatus(200);
    }

    public function test_returns_404_when_no_balance_recorded(): void
    {
        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->getJson('/api/registros/balance');

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Nenhum saldo registrado. Configure o saldo inicial.');
    }

    public function test_can_store_balance(): void
    {
        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->postJson('/api/registros/balance', [
                'reference_date' => today()->format('Y-m-d'),
                'opening_balance' => 5000.00,
                'closing_balance' => 5500.00,
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('balances', [
            'opening_balance' => 5000.00,
            'company_id' => $this->company->id,
        ]);
    }

    public function test_can_get_balance_history(): void
    {
        Balance::factory(5)->for($this->company)->create();

        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->getJson('/api/registros/balance');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'company_id', 'reference_date', 'opening_balance', 'closing_balance']
                ]
            ]);
    }

    public function test_can_get_daily_progression(): void
    {
        $startDate = today()->subDays(3)->format('Y-m-d');
        $endDate = today()->format('Y-m-d');

        EntryType::factory()->for($this->company)->create();
        ExitType::factory()->for($this->company)->create();

        FinancialRecord::factory()
            ->for($this->company)
            ->create([
                'date' => $startDate,
                'amount' => 100.00,
                'entry_type_id' => EntryType::first()->id,
                'exit_type_id' => null,
            ]);

        FinancialRecord::factory()
            ->for($this->company)
            ->create([
                'date' => $endDate,
                'amount' => 50.00,
                'entry_type_id' => null,
                'exit_type_id' => ExitType::first()->id,
            ]);

        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->postJson('/api/registros/balance/daily-progression', [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.base_balance', 0)
            ->assertJsonPath('data.final_balance', 50.00);
    }

    public function test_can_get_monthly_summary(): void
    {
        $month = today()->month;
        $year = today()->year;

        EntryType::factory()->for($this->company)->create();
        ExitType::factory()->for($this->company)->create();

        FinancialRecord::factory(3)
            ->for($this->company)
            ->create([
                'date' => today(),
                'amount' => 100.00,
                'entry_type_id' => EntryType::first()->id,
                'exit_type_id' => null,
            ]);

        FinancialRecord::factory(2)
            ->for($this->company)
            ->create([
                'date' => today(),
                'amount' => 30.00,
                'entry_type_id' => null,
                'exit_type_id' => ExitType::first()->id,
            ]);

        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->postJson('/api/registros/balance/monthly-summary', [
                'year' => $year,
                'month' => $month,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.entries', 300.00)
            ->assertJsonPath('data.exits', 60.00)
            ->assertJsonPath('data.profit', 240.00);
    }

    public function test_can_get_yearly_summary(): void
    {
        $year = today()->year;

        EntryType::factory()->for($this->company)->create();
        ExitType::factory()->for($this->company)->create();

        FinancialRecord::factory(5)
            ->for($this->company)
            ->create([
                'date' => today(),
                'amount' => 100.00,
                'entry_type_id' => EntryType::first()->id,
                'exit_type_id' => null,
            ]);

        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->postJson('/api/registros/balance/yearly-summary', [
                'year' => $year,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.entries', 500.00)
            ->assertJsonPath('data.profit', 500.00);
    }
}
