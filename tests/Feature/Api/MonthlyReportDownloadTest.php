<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\User;
use App\Models\UserReminderSetting;
use App\Services\ReportPdfService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthlyReportDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_download_uses_selected_period_and_only_the_authenticated_company_and_pin(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->root()->create(['company_id' => $company->id]);
        $setting = UserReminderSetting::create([
            'user_id' => $user->id,
            'pdf_pin' => 'A1B2',
            'monthly_enabled' => false,
        ]);
        $otherUser = User::factory()->root()->create(['company_id' => Company::factory()->create()->id]);
        UserReminderSetting::create(['user_id' => $otherUser->id, 'pdf_pin' => 'C3D4']);

        $path = tempnam(sys_get_temp_dir(), 'report-download-test-');
        file_put_contents($path, '%PDF-1.7 test');

        $this->mock(ReportPdfService::class, function ($mock) use ($company, $user, $path) {
            $mock->shouldReceive('buildMonthlyStatementPdf')->once()->withArgs(
                fn (Company $reportCompany, Carbon $period, string $pin, User $recipient) => $reportCompany->is($company)
                    && $period->toDateString() === '2024-02-01'
                    && $pin === 'A1B2'
                    && $recipient->is($user)
            )->andReturn($path);
        });

        try {
            $response = $this->actingAs($user, 'sanctum')->getJson(
                '/api/dashboard/monthly-report?month=2&year=2024&company_id='.$otherUser->company_id
            );

            $response->assertOk()
                ->assertDownload('relatorio-mensal-2024-02.pdf')
                ->assertHeader('Content-Type', 'application/pdf');
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
            $this->assertSame('%PDF-1.7 test', file_get_contents($response->baseResponse->getFile()->getPathname()));
            $this->assertNull($setting->fresh()->last_monthly_sent_for_period);
            $this->assertFalse($setting->fresh()->monthly_enabled);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_download_requires_a_configured_pin(): void
    {
        $user = User::factory()->root()->create(['company_id' => Company::factory()->create()->id]);
        $this->mock(ReportPdfService::class, function ($mock) {
            $mock->shouldNotReceive('buildMonthlyStatementPdf');
            $mock->shouldNotReceive('buildYearlySummaryPdf');
        });

        foreach (['monthly-report?month=11&year=2026', 'yearly-report?year=2026'] as $report) {
            $this->actingAs($user, 'sanctum')
                ->getJson('/api/dashboard/'.$report)
                ->assertUnprocessable()
                ->assertJsonPath('message', 'Configure o PIN do PDF em Minha Conta, na seção de lembretes, antes de gerar o relatório.');
        }
    }

    public function test_download_validates_period_before_generating_a_pdf(): void
    {
        $user = User::factory()->root()->create(['company_id' => Company::factory()->create()->id]);
        $this->mock(ReportPdfService::class)->shouldNotReceive('buildMonthlyStatementPdf');
        $this->actingAs($user, 'sanctum');

        foreach ([
            '',
            '?month=13&year=2101',
            '?month=0&year=1999',
            '?month=texto&year=2026.5',
        ] as $query) {
            $this->getJson('/api/dashboard/monthly-report'.$query)
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['month', 'year']);
        }
    }

    public function test_download_requires_authentication_and_a_company(): void
    {
        $this->getJson('/api/dashboard/monthly-report?month=11&year=2026')->assertUnauthorized();
        $this->getJson('/api/dashboard/yearly-report?year=2026')->assertUnauthorized();

        $user = User::factory()->create(['company_id' => null]);
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/dashboard/monthly-report?month=11&year=2026')
            ->assertForbidden();
        $this->getJson('/api/dashboard/yearly-report?year=2026')->assertForbidden();
    }

    public function test_yearly_download_uses_selected_year_and_only_the_authenticated_company_and_pin(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->root()->create(['company_id' => $company->id]);
        $setting = UserReminderSetting::create(['user_id' => $user->id, 'pdf_pin' => 'A1B2', 'yearly_enabled' => false]);
        $otherUser = User::factory()->root()->create(['company_id' => Company::factory()->create()->id]);
        UserReminderSetting::create(['user_id' => $otherUser->id, 'pdf_pin' => 'C3D4']);
        $path = tempnam(sys_get_temp_dir(), 'yearly-download-test-');
        file_put_contents($path, '%PDF-1.7 annual test');

        $this->mock(ReportPdfService::class, function ($mock) use ($company, $user, $path) {
            $mock->shouldNotReceive('buildMonthlyStatementPdf');
            $mock->shouldReceive('buildYearlySummaryPdf')->once()->withArgs(
                fn (Company $reportCompany, int $year, string $pin, User $recipient) => $reportCompany->is($company)
                    && $year === 2024
                    && $pin === 'A1B2'
                    && $recipient->is($user)
            )->andReturn($path);
        });

        try {
            $response = $this->actingAs($user, 'sanctum')->getJson(
                '/api/dashboard/yearly-report?year=2024&company_id='.$otherUser->company_id
            );
            $response->assertOk()
                ->assertDownload('relatorio-anual-2024.pdf')
                ->assertHeader('Content-Type', 'application/pdf');
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
            $this->assertSame('%PDF-1.7 annual test', file_get_contents($response->baseResponse->getFile()->getPathname()));
            $this->assertNull($setting->fresh()->last_yearly_sent_for_period);
            $this->assertFalse($setting->fresh()->yearly_enabled);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_yearly_download_validates_year_without_requiring_month(): void
    {
        $user = User::factory()->root()->create(['company_id' => Company::factory()->create()->id]);
        $this->mock(ReportPdfService::class)->shouldNotReceive('buildYearlySummaryPdf');
        $this->actingAs($user, 'sanctum');

        foreach (['', '?year=1999', '?year=2101', '?year=texto', '?year=2026.5'] as $query) {
            $this->getJson('/api/dashboard/yearly-report'.$query)
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['year'])
                ->assertJsonMissingValidationErrors(['month']);
        }
    }

    public function test_download_generates_real_password_protected_monthly_and_yearly_pdfs(): void
    {
        $user = User::factory()->root()->create(['company_id' => Company::factory()->create()->id]);
        UserReminderSetting::create(['user_id' => $user->id, 'pdf_pin' => 'A1B2']);

        $this->actingAs($user, 'sanctum');
        foreach ([
            'monthly-report?month=11&year=2026' => 'relatorio-mensal-2026-11.pdf',
            'yearly-report?year=2026' => 'relatorio-anual-2026.pdf',
        ] as $report => $filename) {
            $response = $this->getJson('/api/dashboard/'.$report);

            $response->assertOk()->assertDownload($filename);
            $path = $response->baseResponse->getFile()->getPathname();

            try {
                $pdf = file_get_contents($path);
                $this->assertStringStartsWith('%PDF-', $pdf);
                $this->assertStringContainsString('/Encrypt', $pdf);
            } finally {
                unlink($path);
                $temporaryBase = substr($path, 0, -4);
                if (is_file($temporaryBase)) {
                    unlink($temporaryBase);
                }
            }
        }
    }
}
