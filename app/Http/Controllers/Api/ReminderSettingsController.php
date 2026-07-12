<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateReminderSettingsRequest;
use App\Models\UserReminderSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class ReminderSettingsController extends Controller
{
    public function show(): JsonResponse
    {
        $setting = $this->getOrCreateSetting();

        return response()->json([
            'data' => $this->present($setting),
        ]);
    }

    public function update(UpdateReminderSettingsRequest $request): JsonResponse
    {
        $setting = $this->getOrCreateSetting();

        $payload = [
            'daily_enabled' => (bool) $request->boolean('daily_enabled'),
            'daily_condition' => $request->string('daily_condition')->toString() ?: UserReminderSetting::CONDITION_MISSING_ENTRY_OR_EXIT,
            'daily_days_of_week' => collect($request->input('daily_days_of_week', []))
                ->map(fn ($day) => (int) $day)
                ->filter(fn (int $day) => $day >= 0 && $day <= 6)
                ->unique()
                ->sort()
                ->values()
                ->all(),
            'send_time' => $request->string('send_time')->toString(),
            'monthly_enabled' => (bool) $request->boolean('monthly_enabled'),
            'yearly_enabled' => (bool) $request->boolean('yearly_enabled'),
        ];

        $pin = $request->string('pdf_pin')->toString();
        if ($pin !== '') {
            $payload['pdf_pin'] = strtoupper($pin);
        }

        $setting->update($payload);

        return response()->json([
            'data' => $this->present($setting->fresh()),
            'message' => 'Configurações de lembretes atualizadas com sucesso.',
        ]);
    }

    private function getOrCreateSetting(): UserReminderSetting
    {
        $user = auth('sanctum')->user();

        return UserReminderSetting::firstOrCreate(
            ['user_id' => $user->id],
            [
                'daily_enabled' => false,
                'daily_condition' => UserReminderSetting::CONDITION_MISSING_ENTRY_OR_EXIT,
                'daily_days_of_week' => [1, 2, 3, 4, 5],
                'send_time' => '18:00',
                'monthly_enabled' => false,
                'yearly_enabled' => false,
                'pdf_pin' => strtoupper(Str::random(4)),
            ]
        );
    }

    private function present(UserReminderSetting $setting): array
    {
        return [
            'daily_enabled' => (bool) $setting->daily_enabled,
            'daily_condition' => (string) $setting->daily_condition,
            'daily_days_of_week' => array_values($setting->daily_days_of_week ?? []),
            'send_time' => substr((string) $setting->send_time, 0, 5),
            'monthly_enabled' => (bool) $setting->monthly_enabled,
            'yearly_enabled' => (bool) $setting->yearly_enabled,
            'pdf_pin_hint' => 'PIN configurado. Informe um novo PIN para alterar.',
        ];
    }
}
