<?php

namespace App\Console\Commands;

use App\Mail\DailyRecordsReminderMail;
use App\Mail\PeriodicReportMail;
use App\Models\FinancialRecord;
use App\Models\User;
use App\Models\UserReminderSetting;
use App\Services\ReportPdfService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendReminderNotifications extends Command
{
    protected $signature = 'notifications:send-reminders
        {--force : Ignora horário e regras de calendário}
        {--ignore-history : Permite reenviar mesmo já tendo envio marcado no período}
        {--user_id=1 : Executa para um usuário específico}
        {--user_email= : Executa para um usuário específico via e-mail}
        {--channel=all : Canal: daily, monthly, yearly ou all}';

    protected $description = 'Envia lembretes diários e relatórios mensal/anual por e-mail.';

    public function __construct(private ReportPdfService $reportPdfService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $now = now();
        $force = (bool) $this->option('force');
        $ignoreHistory = (bool) $this->option('ignore-history');
        $userEmail = trim((string) ($this->option('user_email') ?? ''));
        $userId = (int) ($this->option('user_id') ?? 1);
        $channel = (string) ($this->option('channel') ?? 'all');

        if ($userEmail !== '') {
            $user = User::query()
                ->select(['id', 'email'])
                ->whereRaw('LOWER(email) = ?', [mb_strtolower($userEmail)])
                ->first();

            if (!$user) {
                $this->error(sprintf('Usuário não encontrado para o e-mail: %s', $userEmail));

                return self::FAILURE;
            }

            $userId = (int) $user->id;
            $this->info(sprintf('E-mail %s resolvido para user_id=%d.', $user->email, $userId));
        }

        if ($userId <= 0) {
            $userId = 1;
        }

        if (!in_array($channel, ['daily', 'monthly', 'yearly', 'all'], true)) {
            $this->error('Canal inválido. Use daily, monthly, yearly ou all.');

            return self::FAILURE;
        }

        $this->line(sprintf(
            'Iniciando envio de notificações | canal=%s | user_id=%d | force=%s | ignore_history=%s | horário=%s',
            $channel,
            $userId,
            $force ? 'true' : 'false',
            $ignoreHistory ? 'true' : 'false',
            $now->format('Y-m-d H:i:s')
        ));

        $settingsQuery = UserReminderSetting::query()
            ->where(function ($query) {
                $query->where('daily_enabled', true)
                    ->orWhere('monthly_enabled', true)
                    ->orWhere('yearly_enabled', true);
            })
            ->with(['user.company']);

        $settingsQuery->where('user_id', $userId);

        $settings = $settingsQuery->get();

        if ($settings->isEmpty()) {
            $this->warn(sprintf('Nenhuma configuração de lembrete ativa encontrada para user_id=%d.', $userId));

            return self::SUCCESS;
        }

        $sentCount = [
            'daily' => 0,
            'monthly' => 0,
            'yearly' => 0,
        ];

        $processedCount = 0;

        foreach ($settings as $setting) {
            $processedCount++;

            if (!$setting->user || !$setting->user->company) {
                $this->warn(sprintf('Configuração %d ignorada: usuário ou empresa não encontrada.', $setting->id));
                continue;
            }

            $this->line(sprintf(
                'Processando configuração %d | usuário=%s | email=%s | empresa=%s',
                $setting->id,
                $setting->user->name,
                $setting->user->email,
                $setting->user->company->name
            ));

            if (!$force && !$this->isTimeToSend($setting->send_time, $now)) {
                $this->line(sprintf('Ignorado por horário: configurado para %s.', $setting->send_time));
                continue;
            }

            if ($channel === 'daily' || $channel === 'all') {
                if ($this->handleDailyReminder($setting, $now, $force, $ignoreHistory)) {
                    $sentCount['daily']++;
                }
            }

            if ($channel === 'monthly' || $channel === 'all') {
                if ($this->handleMonthlyReport($setting, $now, $force, $ignoreHistory)) {
                    $sentCount['monthly']++;
                }
            }

            if ($channel === 'yearly' || $channel === 'all') {
                if ($this->handleYearlyReport($setting, $now, $force, $ignoreHistory)) {
                    $sentCount['yearly']++;
                }
            }
        }

        $this->info(sprintf(
            'Finalizado | configurações processadas=%d | daily enviados=%d | monthly enviados=%d | yearly enviados=%d',
            $processedCount,
            $sentCount['daily'],
            $sentCount['monthly'],
            $sentCount['yearly']
        ));

        return self::SUCCESS;
    }

    private function handleDailyReminder(UserReminderSetting $setting, Carbon $now, bool $force, bool $ignoreHistory): bool
    {
        if (!$setting->daily_enabled) {
            return false;
        }

        $weekday = $now->dayOfWeek;
        $days = collect($setting->daily_days_of_week ?? [])->map(fn ($day) => (int) $day)->all();
        if (!$force && !in_array($weekday, $days, true)) {
            return false;
        }

        if (!$ignoreHistory && $setting->last_daily_sent_for_date?->isSameDay($now)) {
            return false;
        }

        $entriesCount = FinancialRecord::query()
            ->where('company_id', $setting->user->company_id)
            ->whereDate('date', $now->toDateString())
            ->where('type', 'entry')
            ->count();

        $exitsCount = FinancialRecord::query()
            ->where('company_id', $setting->user->company_id)
            ->whereDate('date', $now->toDateString())
            ->where('type', 'exit')
            ->count();

        $shouldSend = match ($setting->daily_condition) {
            UserReminderSetting::CONDITION_MISSING_ENTRY => $entriesCount === 0,
            UserReminderSetting::CONDITION_MISSING_EXIT => $exitsCount === 0,
            UserReminderSetting::CONDITION_MISSING_BOTH => $entriesCount === 0 && $exitsCount === 0,
            default => $entriesCount === 0 || $exitsCount === 0,
        };

        if (!$shouldSend && !$force) {
            return false;
        }

        Mail::to($setting->user->email)->send(new DailyRecordsReminderMail(
            user: $setting->user,
            conditionLabel: $this->conditionLabel($setting->daily_condition)
        ));

        $this->info(sprintf(
            '[DAILY] E-mail enviado para user_id=%d | nome=%s | email=%s',
            $setting->user->id,
            $setting->user->name,
            $setting->user->email
        ));

        $setting->forceFill([
            'last_daily_sent_for_date' => $now->toDateString(),
        ])->save();

        return true;
    }

    private function handleMonthlyReport(UserReminderSetting $setting, Carbon $now, bool $force, bool $ignoreHistory): bool
    {
        if (!$setting->monthly_enabled) {
            return false;
        }

        if (!$force && (int) $now->day !== 1) {
            return false;
        }

        $reportMonth = $now->copy()->subMonthNoOverflow()->startOfMonth();
        $periodKey = $reportMonth->format('Y-m');

        if (!$ignoreHistory && $setting->last_monthly_sent_for_period === $periodKey) {
            return false;
        }

        $pdfPath = $this->reportPdfService->buildMonthlyStatementPdf(
            company: $setting->user->company,
            periodStart: $reportMonth,
            pin: (string) $setting->pdf_pin,
        );

        try {
            Mail::to($setting->user->email)->send(new PeriodicReportMail(
                user: $setting->user,
                reportType: 'monthly',
                reportPeriodLabel: $reportMonth->translatedFormat('m/Y'),
                pdfPath: $pdfPath,
            ));

            $this->info(sprintf(
                '[MONTHLY] E-mail enviado para user_id=%d | nome=%s | email=%s | período=%s',
                $setting->user->id,
                $setting->user->name,
                $setting->user->email,
                $periodKey
            ));
        } finally {
            @unlink($pdfPath);
        }

        $setting->forceFill([
            'last_monthly_sent_for_period' => $periodKey,
        ])->save();

        return true;
    }

    private function handleYearlyReport(UserReminderSetting $setting, Carbon $now, bool $force, bool $ignoreHistory): bool
    {
        if (!$setting->yearly_enabled) {
            return false;
        }

        if (!$force && ((int) $now->day !== 1 || (int) $now->month !== 1)) {
            return false;
        }

        $reportYear = (int) $now->copy()->subYear()->year;

        if (!$ignoreHistory && (int) $setting->last_yearly_sent_for_period === $reportYear) {
            return false;
        }

        $pdfPath = $this->reportPdfService->buildYearlySummaryPdf(
            company: $setting->user->company,
            year: $reportYear,
            pin: (string) $setting->pdf_pin,
        );

        try {
            Mail::to($setting->user->email)->send(new PeriodicReportMail(
                user: $setting->user,
                reportType: 'yearly',
                reportPeriodLabel: (string) $reportYear,
                pdfPath: $pdfPath,
            ));

            $this->info(sprintf(
                '[YEARLY] E-mail enviado para user_id=%d | nome=%s | email=%s | período=%d',
                $setting->user->id,
                $setting->user->name,
                $setting->user->email,
                $reportYear
            ));
        } finally {
            @unlink($pdfPath);
        }

        $setting->forceFill([
            'last_yearly_sent_for_period' => $reportYear,
        ])->save();

        return true;
    }

    private function isTimeToSend(string $settingTime, Carbon $now): bool
    {
        return substr($settingTime, 0, 5) === $now->format('H:i');
    }

    private function conditionLabel(string $condition): string
    {
        return match ($condition) {
            UserReminderSetting::CONDITION_MISSING_ENTRY => 'quando não houver entrada no dia',
            UserReminderSetting::CONDITION_MISSING_EXIT => 'quando não houver saída no dia',
            UserReminderSetting::CONDITION_MISSING_BOTH => 'quando não houver nem entrada nem saída no dia',
            default => 'quando faltar entrada ou saída no dia',
        };
    }
}
