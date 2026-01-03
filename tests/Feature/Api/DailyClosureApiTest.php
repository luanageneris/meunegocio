<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\User;
use App\Models\EntryType;
use App\Models\ExitType;
use App\Models\FinancialRecord;
use App\Models\DailyClosure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyClosureApiTest extends TestCase
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

    public function test_can_list_daily_closures(): void
    {
        DailyClosure::factory(5)->for($this->company)->create();

        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->getJson('/api/registros/daily-closures');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'date', 'total_entry', 'total_exit', 'profit', 'is_closed']
                ]
            ]);
    }

    public function test_can_close_a_day(): void
    {
        $date = today()->format('Y-m-d');
        
        EntryType::factory()->for($this->company)->create();
        ExitType::factory()->for($this->company)->create();

        FinancialRecord::factory(2)
            ->for($this->company)
            ->create([
                'date' => $date,
                'amount' => 100.00,
                'entry_type_id' => EntryType::first()->id,
                'exit_type_id' => null,
            ]);

        FinancialRecord::factory()
            ->for($this->company)
            ->create([
                'date' => $date,
                'amount' => 30.00,
                'entry_type_id' => null,
                'exit_type_id' => ExitType::first()->id,
            ]);

        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->postJson('/api/registros/daily-closures', [
                'date' => $date,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.total_entry', '200.00')
            ->assertJsonPath('data.total_exit', '30.00')
            ->assertJsonPath('data.profit', '170.00');

        $this->assertDatabaseHas('daily_closures', [
            'date' => $date,
            'company_id' => $this->company->id,
        ]);
    }

    public function test_cannot_close_same_day_twice(): void
    {
        $date = today()->format('Y-m-d');
        DailyClosure::factory()->for($this->company)->create(['date' => $date]);

        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->postJson('/api/registros/daily-closures', [
                'date' => $date,
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('message', 'Este dia já foi fechado');
    }

    public function test_cannot_close_future_day(): void
    {
        $futureDate = today()->addDay()->format('Y-m-d');

        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->postJson('/api/registros/daily-closures', [
                'date' => $futureDate,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('errors.date', ['Não é possível fechar um dia no futuro']);
    }

    public function test_can_get_closure_summary(): void
    {
        $startDate = today()->subDays(7)->format('Y-m-d');
        $endDate = today()->format('Y-m-d');

        DailyClosure::factory(3)
            ->for($this->company)
            ->create([
                'entries' => 200.00,
                'exits' => 50.00,
                'commissions' => 20.00,
                'profit' => 130.00,
            ]);

        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->postJson('/api/registros/daily-closures/summary', [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.summary.total_entries', 600.00)
            ->assertJsonPath('data.summary.total_exits', 150.00)
            ->assertJsonPath('data.summary.closed_days', 3);
    }

    public function test_can_check_if_day_is_closed(): void
    {
        $date = today()->format('Y-m-d');
        DailyClosure::factory()->for($this->company)->create(['date' => $date]);

        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->getJson("/api/registros/daily-closures/is-closed/{$date}");

        $response->assertStatus(200)
            ->assertJsonPath('data.closed', true);
    }

    public function test_can_reopen_closed_day(): void
    {
        $closure = DailyClosure::factory()
            ->for($this->company)
            ->create([
                'closed_at' => now(),
                'closed_by' => $this->rootUser->id,
            ]);

        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->postJson("/api/registros/daily-closures/reopen/{$closure->id}");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Dia reabertor com sucesso');

        $this->assertDatabaseHas('daily_closures', [
            'id' => $closure->id,
            'closed_at' => null,
        ]);
    }
}
