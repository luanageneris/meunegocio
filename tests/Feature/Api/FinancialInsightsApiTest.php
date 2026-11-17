<?php

namespace Tests\Feature\Api;

use App\Models\Collaborator;
use App\Models\Commission;
use App\Models\Company;
use App\Models\EntryType;
use App\Models\ExitType;
use App\Models\FinancialRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FinancialInsightsApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    private EntryType $entryType;

    private ExitType $exitType;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-16 12:00:00');

        $this->company = Company::factory()->create();
        $this->user = User::factory()->root()->create(['company_id' => $this->company->id]);
        $this->entryType = EntryType::factory()->for($this->company)->create(['name' => 'Vendas']);
        $this->exitType = ExitType::factory()->for($this->company)->create(['name' => 'Despesas']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_opportunities_form_and_api_use_the_same_today_at_a_timezone_boundary(): void
    {
        $originalTimezone = date_default_timezone_get();
        config(['app.timezone' => 'America/Sao_Paulo']);
        date_default_timezone_set('America/Sao_Paulo');
        Carbon::setTestNow(Carbon::parse('2026-11-16 21:15:00', 'America/Sao_Paulo'));

        try {
            $this->assertSame('2026-11-17', now('UTC')->toDateString());
            $this->actingAs($this->user, 'sanctum')
                ->get('/oportunidades')
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Oportunidades')
                    ->where('today', '2026-11-16'));

            $this->getJson('/api/opportunities?start_date=2026-11-01&end_date=2026-11-16')
                ->assertOk()
                ->assertJsonPath('data.period.end_date', '2026-11-16');

            $this->getJson('/api/opportunities?start_date=2026-11-01&end_date=2026-11-17')
                ->assertUnprocessable()
                ->assertJsonPath('errors.end_date.0', 'A data final não pode ser posterior a hoje.');
        } finally {
            date_default_timezone_set($originalTimezone);
        }
    }

    public function test_opportunities_require_thirty_active_days_in_the_last_ninety_days(): void
    {
        for ($day = 1; $day <= 29; $day++) {
            $this->createRecord('entry', Carbon::parse('2026-09-01')->addDays($day)->toDateString(), 10);
        }

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/opportunities?start_date=2026-11-01&end_date=2026-11-16');

        $response->assertOk()
            ->assertJsonPath('data.eligible', false)
            ->assertJsonPath('data.eligibility.active_days', 29)
            ->assertJsonPath('data.eligibility.required_active_days', 30)
            ->assertJsonPath('data.insights', []);
    }

    public function test_opportunities_compare_periods_and_apply_company_scoped_category_filters(): void
    {
        $this->addRecentActivityDays();

        foreach ([
            ['2026-11-01', 100],
            ['2026-11-02', 200],
            ['2026-11-03', 300],
            ['2026-10-29', 50],
            ['2026-10-30', 50],
            ['2026-10-31', 50],
        ] as [$date, $amount]) {
            $this->createRecord('entry', $date, $amount);
        }

        $otherCompany = Company::factory()->create();
        $otherEntryType = EntryType::factory()->for($otherCompany)->create();
        FinancialRecord::factory()->for($otherCompany)->create([
            'type' => 'entry',
            'entry_type_id' => $otherEntryType->id,
            'date' => '2026-11-01',
            'amount' => 9999,
            'quantity' => 1,
            'discount' => 0,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/opportunities?start_date=2026-11-01&end_date=2026-11-03&category=entry:'.$this->entryType->id);

        $response->assertOk()
            ->assertJsonPath('data.eligible', true)
            ->assertJsonPath('data.insights.revenue_trend.current_entries', 600)
            ->assertJsonPath('data.insights.revenue_trend.previous_entries', 150)
            ->assertJsonPath('data.insights.cash_flow.entries', 600)
            ->assertJsonPath('data.selected_category.type', 'entry')
            ->assertJsonPath('data.selected_category.id', $this->entryType->id);
    }

    public function test_opportunities_do_not_generate_insights_without_activity_in_selected_period(): void
    {
        $this->addRecentActivityDays();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/opportunities?start_date=2026-11-01&end_date=2026-11-03');

        $response->assertOk()
            ->assertJsonPath('data.eligible', true)
            ->assertJsonPath('data.period_has_activity', false)
            ->assertJsonPath('data.insights', []);
    }

    public function test_opportunities_weekday_graph_includes_only_company_open_days(): void
    {
        $this->addRecentActivityDays();
        $this->company->update(['opening_days' => [1, 3, 5]]);

        foreach ([
            ['2026-11-01', 100],
            ['2026-11-02', 200],
            ['2026-11-03', 300],
            ['2026-11-04', 400],
            ['2026-11-05', 500],
            ['2026-11-06', 600],
            ['2026-11-07', 700],
        ] as [$date, $amount]) {
            $this->createRecord('entry', $date, $amount);
        }

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/opportunities?start_date=2026-11-01&end_date=2026-11-07');

        $response->assertOk()
            ->assertJsonPath('data.insights.weekday_opportunity.weakest_weekday.day', 1)
            ->assertJsonPath('data.insights.weekday_opportunity.strongest_weekday.day', 5);

        $this->assertSame(
            [1, 3, 5],
            array_column($response->json('data.insights.weekday_opportunity.weekday_averages'), 'day'),
        );
    }

    public function test_opportunities_allow_up_to_two_years_between_dates(): void
    {
        $this->addRecentActivityDays();

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/opportunities?start_date=2024-11-16&end_date=2026-11-16')
            ->assertOk();

        $this->getJson('/api/opportunities?start_date=2024-11-15&end_date=2026-11-16')
            ->assertUnprocessable()
            ->assertJsonPath('errors.start_date.0', 'O intervalo entre as datas não pode ser superior a 2 anos.');
    }

    public function test_category_from_another_company_is_rejected(): void
    {
        $this->addRecentActivityDays();
        $otherCompany = Company::factory()->create();
        $otherEntryType = EntryType::factory()->for($otherCompany)->create();

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/opportunities?start_date=2026-11-01&end_date=2026-11-03&category=entry:'.$otherEntryType->id)
            ->assertUnprocessable()
            ->assertJsonPath('errors.category.0', 'A categoria selecionada não pertence à sua empresa ou está inativa.');
    }

    public function test_monthly_curiosities_use_only_same_months_from_previous_years(): void
    {
        $this->addRecentActivityDays();
        $this->createRecord('entry', '2024-01-01', 100);
        $this->createRecord('entry', '2025-01-01', 300);
        $this->createRecord('entry', '2026-01-01', 900);

        $ranking = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/dashboard/curiosities/monthly-ranking?month=1&year=2026');

        $ranking->assertOk()
            ->assertJsonPath('data.available', true)
            ->assertJsonPath('data.years_count', 2)
            ->assertJsonPath('data.latest_comparable.year', 2025)
            ->assertJsonPath('data.latest_comparable.entries', 300)
            ->assertJsonPath('data.latest_comparable.rank', 1);

        $average = $this->getJson('/api/dashboard/curiosities/monthly-average?month=1&year=2026');
        $average->assertOk()
            ->assertJsonPath('data.average_entries', 200)
            ->assertJsonPath('data.years', [2024, 2025]);
    }

    public function test_current_month_curiosities_compare_only_days_that_have_elapsed(): void
    {
        $this->addRecentActivityDays();
        $this->createRecord('entry', '2024-11-03', 100);
        $this->createRecord('entry', '2024-11-17', 10000);
        $this->createRecord('entry', '2025-11-02', 200);
        $this->createRecord('entry', '2025-11-17', 20000);

        $average = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/dashboard/curiosities/monthly-average?month=11&year=2026');

        $average->assertOk()
            ->assertJsonPath('data.comparison_through_day', 16)
            ->assertJsonPath('data.average_entries', 150)
            ->assertJsonPath('data.years', [2024, 2025]);

        $ranking = $this->getJson('/api/dashboard/curiosities/monthly-ranking?month=11&year=2026');
        $ranking->assertOk()
            ->assertJsonPath('data.latest_comparable.year', 2025)
            ->assertJsonPath('data.latest_comparable.entries', 200);

        $weekdays = $this->getJson('/api/dashboard/curiosities/weekday-average?month=11&year=2026');
        $weekdays->assertOk()
            ->assertJsonPath('data.comparison_through_day', 16)
            ->assertJsonPath('data.weekdays.0.average_entries', 60);

        $pastMonth = $this->getJson('/api/dashboard/curiosities/monthly-average?month=11&year=2025');
        $pastMonth->assertOk()
            ->assertJsonPath('data.comparison_through_day', null)
            ->assertJsonPath('data.average_entries', 10100);
    }

    public function test_current_month_without_historical_data_includes_year_to_date_fallbacks(): void
    {
        $this->addRecentActivityDays();
        $this->createRecord('entry', '2026-06-05', 700);
        $this->createRecord('entry', '2026-06-20', 9000);
        $this->createRecord('entry', '2026-11-02', 100);
        $this->createRecord('entry', '2026-11-10', 300);
        $this->createRecord('entry', '2026-11-16', 50);

        $ranking = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/dashboard/curiosities/monthly-ranking?month=11&year=2026');

        $ranking->assertOk()
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.message', 'Ainda não há registos deste mês em anos anteriores para fazer esta comparação.')
            ->assertJsonPath('data.current_month_progress.through_day', 16)
            ->assertJsonPath('data.current_month_progress.rank', 2)
            ->assertJsonPath('data.current_month_progress.months_compared', 11);

        $average = $this->getJson('/api/dashboard/curiosities/monthly-average?month=11&year=2026');
        $average->assertOk()
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.current_month_progress.entries', 450);

        $weekdays = $this->getJson('/api/dashboard/curiosities/weekday-average?month=11&year=2026');
        $weekdays->assertOk()
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.current_month_progress.weekday_averages.1.average_entries', 50)
            ->assertJsonPath('data.current_month_progress.weekday_averages.2.average_entries', 150);
    }

    public function test_cash_flow_does_not_add_a_commission_twice(): void
    {
        $this->addRecentActivityDays();
        $commissionRecord = $this->createRecord('exit', '2026-11-10', 110);
        $collaborator = Collaborator::factory()->for($this->company)->create();
        Commission::factory()->for($this->company)->create([
            'collaborator_id' => $collaborator->id,
            'financial_record_id' => $commissionRecord->id,
            'date' => '2026-11-10',
            'amount' => 110,
            'paid_at' => '2026-11-10',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/opportunities?start_date=2026-11-01&end_date=2026-11-16');

        $response->assertOk()
            ->assertJsonPath('data.insights.cash_flow.exits', 110);
    }

    public function test_weekday_curiosity_uses_historical_same_months_and_best_day_stops_at_selected_month(): void
    {
        $this->addRecentActivityDays();
        $this->createRecord('entry', '2024-01-01', 100);
        $this->createRecord('entry', '2025-01-01', 300);
        $this->createRecord('entry', '2026-02-14', 500);
        $this->createRecord('entry', '2026-04-04', 1000);

        $weekdays = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/dashboard/curiosities/weekday-average?month=1&year=2026');

        $weekdays->assertOk()
            ->assertJsonPath('data.available', true)
            ->assertJsonPath('data.weekdays.1.label', 'Segunda-feira')
            ->assertJsonPath('data.weekdays.1.average_entries', 11.11)
            ->assertJsonPath('data.weekdays.3.average_entries', 30);

        $bestDay = $this->getJson('/api/dashboard/curiosities/best-day?month=3&year=2026');
        $bestDay->assertOk()
            ->assertJsonPath('data.available', true)
            ->assertJsonPath('data.best_day.date', '2026-02-14')
            ->assertJsonPath('data.best_day.entries', 500)
            ->assertJsonPath('data.previous_year_month_best_day', null);
    }

    public function test_best_day_includes_the_same_full_month_last_year_with_daily_totals_and_tenant_isolation(): void
    {
        $this->addRecentActivityDays();
        $this->createRecord('entry', '2026-11-01', 500);
        $this->createRecord('entry', '2025-11-25', 100)->update(['quantity' => 3, 'discount' => 20]);
        $this->createRecord('entry', '2025-11-25', 20);
        $this->createRecord('entry', '2025-11-26', 300);
        $this->createRecord('entry', '2025-10-25', 9999);
        $this->createRecord('exit', '2025-11-24', 9999);
        $this->createRecord('entry', '2025-11-24', 8888)->delete();
        FinancialRecord::factory()->for(Company::factory()->create())->create([
            'type' => 'entry', 'date' => '2025-11-24', 'amount' => 99999,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/dashboard/curiosities/best-day?month=11&year=2026')
            ->assertOk()
            ->assertJsonPath('data.best_day.date', '2026-11-01')
            ->assertJsonPath('data.best_day.entries', 500)
            ->assertJsonPath('data.previous_year_month_best_day.date', '2025-11-25')
            ->assertJsonPath('data.previous_year_month_best_day.entries', 300);
    }

    public function test_previous_year_month_best_day_can_exist_without_entries_in_the_selected_year(): void
    {
        $this->addRecentActivityDays();
        $this->createRecord('entry', '2024-02-29', 250);
        $this->createRecord('entry', '2024-03-01', 9999);
        $this->createRecord('entry', '2023-02-28', 9999);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/dashboard/curiosities/best-day?month=2&year=2025')
            ->assertOk()
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.previous_year_month_best_day.date', '2024-02-29')
            ->assertJsonPath('data.previous_year_month_best_day.entries', 250);
    }

    public function test_previous_year_month_best_day_does_not_fall_back_to_older_years_or_exits(): void
    {
        $this->addRecentActivityDays();
        $this->createRecord('entry', '2024-03-01', 500);
        $this->createRecord('exit', '2025-03-01', 800);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/dashboard/curiosities/best-day?month=3&year=2026')
            ->assertOk()
            ->assertJsonPath('data.previous_year_month_best_day', null);
    }

    public function test_curiosities_average_exits_over_the_same_historical_years_and_elapsed_days(): void
    {
        $this->addRecentActivityDays();
        $this->createRecord('entry', '2023-11-05', 300);
        $this->createRecord('entry', '2024-11-03', 100);
        $this->createRecord('entry', '2025-11-02', 200);
        $this->createRecord('exit', '2024-11-03', 40)->update(['quantity' => 3, 'discount' => 20]);
        $commissionRecord = $this->createRecord('exit', '2025-11-02', 200);
        Commission::factory()->for($this->company)->create([
            'collaborator_id' => Collaborator::factory()->for($this->company)->create()->id,
            'financial_record_id' => $commissionRecord->id,
            'date' => '2025-11-02',
            'amount' => 200,
            'paid_at' => '2025-11-02',
        ]);
        $this->createRecord('exit', '2024-11-17', 900);
        $this->createRecord('exit', '2025-12-02', 9999);
        $this->createRecord('exit', '2026-11-02', 9999);
        $this->createRecord('exit', '2025-11-02', 8888)->delete();
        FinancialRecord::factory()->for(Company::factory()->create())->create([
            'type' => 'exit', 'date' => '2025-11-02', 'amount' => 99999,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/dashboard/curiosities/monthly-average?month=11&year=2026')
            ->assertOk()
            ->assertJsonPath('data.average_entries', 200)
            ->assertJsonPath('data.average_exits', 100)
            ->assertJsonPath('data.years', [2023, 2024, 2025]);

        $this->getJson('/api/dashboard/curiosities/weekday-average?month=11&year=2026')
            ->assertOk()
            ->assertJsonPath('data.weekdays.0.average_entries', 85.71)
            ->assertJsonPath('data.weekdays.0.average_exits', 42.86)
            ->assertJsonPath('data.weekdays.1.average_exits', 0);

        $this->getJson('/api/dashboard/curiosities/monthly-average?month=11&year=2025')
            ->assertOk()
            ->assertJsonPath('data.comparison_through_day', null)
            ->assertJsonPath('data.average_exits', 500);
    }

    public function test_current_month_fallback_includes_expenses_without_needing_entries(): void
    {
        $this->addRecentActivityDays();
        $this->createRecord('exit', '2026-11-02', 90);
        $this->createRecord('exit', '2026-11-17', 9999);
        $this->createRecord('exit', '2026-10-02', 8888);
        $this->createRecord('exit', '2026-11-02', 7777)->delete();

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/dashboard/curiosities/monthly-average?month=11&year=2026')
            ->assertOk()
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.current_month_progress.entries', 0)
            ->assertJsonPath('data.current_month_progress.exits', 90);

        $this->getJson('/api/dashboard/curiosities/weekday-average?month=11&year=2026')
            ->assertOk()
            ->assertJsonPath('data.current_month_progress.weekday_averages.1.average_entries', 0)
            ->assertJsonPath('data.current_month_progress.weekday_averages.1.average_exits', 30)
            ->assertJsonPath('data.current_month_progress.weekday_averages.2.average_exits', 0);
    }

    private function addRecentActivityDays(): void
    {
        for ($day = 0; $day < 30; $day++) {
            $this->createRecord('entry', Carbon::parse('2026-08-20')->addDays($day)->toDateString(), 10);
        }
    }

    private function createRecord(string $type, string $date, float $amount): FinancialRecord
    {
        return FinancialRecord::factory()->for($this->company)->create([
            'type' => $type,
            'entry_type_id' => $type === 'entry' ? $this->entryType->id : null,
            'exit_type_id' => $type === 'exit' ? $this->exitType->id : null,
            'date' => $date,
            'amount' => $amount,
            'quantity' => 1,
            'discount' => 0,
        ]);
    }
}
