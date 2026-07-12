<?php

namespace App\Http\Requests;

use App\Models\UserReminderSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReminderSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'daily_enabled' => ['required', 'boolean'],
            'daily_condition' => [
                'required_if:daily_enabled,true',
                Rule::in(UserReminderSetting::DAILY_CONDITIONS),
            ],
            'daily_days_of_week' => ['nullable', 'array'],
            'daily_days_of_week.*' => ['integer', 'between:0,6'],
            'send_time' => ['required', 'date_format:H:i'],
            'monthly_enabled' => ['required', 'boolean'],
            'yearly_enabled' => ['required', 'boolean'],
            'pdf_pin' => ['nullable', 'regex:/^[A-Za-z0-9]{4}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'daily_condition.required_if' => 'Selecione a condição da notificação diária.',
            'daily_condition.in' => 'Condição diária inválida.',
            'daily_days_of_week.array' => 'Selecione os dias da semana válidos.',
            'daily_days_of_week.*.between' => 'Dia da semana inválido.',
            'send_time.required' => 'Informe o horário de envio.',
            'send_time.date_format' => 'Horário de envio inválido.',
            'pdf_pin.regex' => 'O PIN deve ter exatamente 4 caracteres (letras ou números).',
        ];
    }
}
