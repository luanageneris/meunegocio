<?php

namespace Tests\Feature;

use App\Mail\PeriodicReportMail;
use App\Models\FinancialRecord;
use App\Models\Holiday;
use App\Models\HolidaySync;
use App\Models\User;
use App\Models\UserReminderSetting;
use App\Services\HolidayService;
use App\Services\ReportPdfService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class ReportHolidayTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_holidays_follow_each_recipients_consent_location_and_period(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create(['company_id' => $user->company_id]);
        $withoutConsent = User::factory()->create(['company_id' => $user->company_id]);
        $this->createLocation($user, 'PT-LI');
        $this->createLocation($other, 'PT-PO');

        Http::fake(['openholidaysapi.org/PublicHolidays*' => Http::response([
            $this->event('Nacional', true),
            $this->event('Lisboa', false, ['PT-LI']),
            $this->event('Porto', false, ['PT-PO']),
            array_merge($this->event('Intervalo', true), ['startDate' => '2026-01-31', 'endDate' => '2026-02-02']),
        ])]);

        $service = app(ReportPdfService::class);
        $load = new ReflectionMethod($service, 'loadHolidayData');
        $january = [Carbon::parse('2026-01-01'), Carbon::parse('2026-01-31')];
        $lisbon = $load->invoke($service, $user->company, $user, ...$january);
        $porto = $load->invoke($service, $user->company, $other, ...$january);
        $this->assertSame(['Intervalo', 'Lisboa', 'Nacional'], $lisbon['holidays']->pluck('name')->sort()->values()->all());
        $this->assertSame(['Intervalo', 'Nacional', 'Porto'], $porto['holidays']->pluck('name')->sort()->values()->all());
        $this->assertNull($load->invoke($service, $user->company, $withoutConsent, ...$january));

        $february = $load->invoke($service, $user->company, $user, Carbon::parse('2026-02-01'), Carbon::parse('2026-02-28'));
        $this->assertSame(['Intervalo'], $february['holidays']->pluck('name')->all());
        $isHoliday = new ReflectionMethod($service, 'isHoliday');
        $this->assertTrue($isHoliday->invoke($service, $february['holidays'], Carbon::parse('2026-02-02')));
        $this->assertFalse($isHoliday->invoke($service, $february['holidays'], Carbon::parse('2026-02-03')));

        $user->holidayLocation()->delete();
        $this->assertNull($load->invoke($service, $user->company, $user, ...$january));
        Http::assertSentCount(2);
    }

    public function test_report_holiday_section_displays_types_ranges_and_provider_failures(): void
    {
        $service = app(ReportPdfService::class);
        $render = new ReflectionMethod($service, 'buildHolidaySectionHtml');
        $holidays = new Collection([
            new Holiday(['start_date' => '2026-01-01', 'end_date' => '2026-01-01', 'name' => 'Ano <Novo>', 'type' => 'unknown']),
            new Holiday(['start_date' => '2026-02-01', 'end_date' => '2026-02-02', 'name' => 'Carnaval', 'type' => 'optional']),
        ]);
        $html = $render->invoke($service, ['holidays' => $holidays, 'status' => 'partial'], true);
        $this->assertStringContainsString('Cobertura parcial', $html);
        $this->assertStringContainsString('01/01/2026 — Ano &lt;Novo&gt;', $html);
        $this->assertStringContainsString('01/02/2026 a 02/02/2026 — Carnaval (Ponto facultativo)', $html);
        $this->assertStringNotContainsString('Classificação não informada', $html);
        $this->assertStringContainsString('marcadas com *', $html);
        $this->assertStringNotContainsString('marcadas com *', $render->invoke($service, ['holidays' => $holidays, 'status' => 'complete'], false));
        $this->assertStringContainsString('Não foi possível consultar', $render->invoke($service, ['holidays' => new Collection, 'status' => 'failed'], false));
        $this->assertSame('', $render->invoke($service, null, false));
    }

    public function test_report_excludes_holidays_if_consent_is_revoked_during_synchronization(): void
    {
        $user = User::factory()->create();
        $this->createLocation($user, 'PT-LI');
        $sync = HolidaySync::create([
            'location_key' => $user->holidayLocation()->first()->cacheKey(),
            'year' => 2026,
            'status' => 'complete',
        ]);
        $holidays = Mockery::mock(HolidayService::class);
        $holidays->shouldReceive('synchronize')->once()->andReturnUsing(function () use ($user, $sync) {
            $user->holidayLocation()->delete();

            return $sync;
        });
        $service = new ReportPdfService($holidays);
        $load = new ReflectionMethod($service, 'loadHolidayData');

        $this->assertNull($load->invoke($service, $user->company, $user, Carbon::parse('2026-01-01'), Carbon::parse('2026-01-31')));
    }

    public function test_report_rejects_a_recipient_from_another_company(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $service = app(ReportPdfService::class);
        $load = new ReflectionMethod($service, 'loadHolidayData');

        $this->expectException(\InvalidArgumentException::class);
        $load->invoke($service, $user->company, $other, Carbon::parse('2026-01-01'), Carbon::parse('2026-01-31'));
    }

    public function test_monthly_and_yearly_pdfs_remain_pin_protected(): void
    {
        $user = User::factory()->create();
        $this->createLocation($user, 'PT-LI');
        FinancialRecord::factory()->create([
            'company_id' => $user->company_id,
            'type' => 'entry',
            'date' => '2026-01-01',
            'amount' => 100,
        ]);
        Http::fake(['openholidaysapi.org/PublicHolidays*' => Http::response([$this->event('Nacional', true)])]);
        $service = app(ReportPdfService::class);
        $paths = [];
        try {
            $paths[] = $service->buildMonthlyStatementPdf($user->company, Carbon::parse('2026-01-01'), '1234', $user);
            $paths[] = $service->buildYearlySummaryPdf($user->company, 2026, '1234', $user);
            foreach ($paths as $path) {
                $pdf = file_get_contents($path);
                $this->assertStringStartsWith('%PDF-', $pdf);
                $this->assertStringContainsString('/Encrypt', $pdf);
            }
        } finally {
            foreach ($paths as $path) {
                unlink($path);
                $temporaryPath = substr($path, 0, -4);
                if (is_file($temporaryPath)) {
                    unlink($temporaryPath);
                }
            }
        }
    }

    public function test_yearly_pdf_does_not_consult_holidays_even_with_location_consent(): void
    {
        $user = User::factory()->create();
        $this->createLocation($user, 'PT-LI');
        $holidays = Mockery::mock(HolidayService::class);
        $holidays->shouldNotReceive('synchronize');
        $service = new ReportPdfService($holidays);

        $path = $service->buildYearlySummaryPdf($user->company, 2026, '1234', $user);
        try {
            $this->assertStringStartsWith('%PDF-', file_get_contents($path));
        } finally {
            unlink($path);
            $temporaryPath = substr($path, 0, -4);
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }

    public function test_yearly_pdf_rejects_a_recipient_from_another_company(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        app(ReportPdfService::class)->buildYearlySummaryPdf($user->company, 2026, '1234', $other);
    }

    public function test_both_emailed_reports_use_the_company_and_recipient(): void
    {
        $user = User::factory()->create();
        UserReminderSetting::create([
            'user_id' => $user->id,
            'monthly_enabled' => true,
            'yearly_enabled' => true,
            'daily_enabled' => false,
            'send_time' => '08:00',
            'pdf_pin' => '1234',
        ]);
        Mail::fake();
        $monthlyPath = tempnam(sys_get_temp_dir(), 'monthly-test-');
        $yearlyPath = tempnam(sys_get_temp_dir(), 'yearly-test-');
        $reports = Mockery::mock(ReportPdfService::class);
        $reports->shouldReceive('buildMonthlyStatementPdf')->once()
            ->withArgs(fn ($company, $period, $pin, $recipient) => $company->is($user->company) && $recipient->is($user))
            ->andReturn($monthlyPath);
        $reports->shouldReceive('buildYearlySummaryPdf')->once()
            ->withArgs(fn ($company, $year, $pin, $recipient) => $company->is($user->company) && $recipient->is($user))
            ->andReturn($yearlyPath);
        $this->app->instance(ReportPdfService::class, $reports);

        try {
            $this->artisan('notifications:send-reminders', ['--user_id' => $user->id, '--force' => true])
                ->assertExitCode(0);
            Mail::assertSent(PeriodicReportMail::class, 2);
        } finally {
            if (is_file($monthlyPath)) {
                unlink($monthlyPath);
            }
            if (is_file($yearlyPath)) {
                unlink($yearlyPath);
            }
        }
    }

    private function createLocation(User $user, string $region): void
    {
        $user->holidayLocation()->create([
            'country_code' => 'PT', 'country_name' => 'Portugal',
            'region_code' => $region, 'region_name' => $region,
            'city_code' => '', 'city_name' => '', 'consented_at' => now(),
        ]);
    }

    private function event(string $name, bool $national, array $codes = []): array
    {
        return [
            'id' => $name, 'startDate' => '2026-01-01', 'endDate' => '2026-01-01',
            'name' => [['language' => 'PT', 'text' => $name]], 'type' => 'Public',
            'nationwide' => $national, 'subdivisions' => array_map(fn ($code) => ['code' => $code], $codes),
        ];
    }
}
