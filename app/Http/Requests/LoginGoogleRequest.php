<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginGoogleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Endpoint público
    }

    public function rules(): array
    {
        return [
            'token' => 'required|string',
            'company_id' => 'nullable|integer|exists:companies,id',
        ];
    }

    public function messages(): array
    {
        return [
            'token.required' => 'Token do Google é obrigatório',
            'company_id.exists' => 'Empresa não encontrada',
        ];
    }
}
