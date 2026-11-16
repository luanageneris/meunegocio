<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHolidayLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'consent' => ['accepted'],
            'country_code' => ['required', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'region_code' => ['nullable', 'string', 'max:100'],
            'region_name' => ['nullable', 'string', 'max:100'],
            'city_code' => ['nullable', 'string', 'max:100'],
            'city_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'consent.accepted' => 'É necessário confirmar o consentimento para usar a localização.',
            'country_code.required' => 'Selecione um país.',
            'country_code.size' => 'Selecione um país válido.',
        ];
    }
}
