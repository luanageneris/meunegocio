<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterGoogleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Registro é público
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'company_name' => 'nullable|string|min:3|max:255|unique:companies,name',
            'google_user' => 'nullable|array',
            'google_user.name' => 'nullable|string|max:255',
            'google_user.email' => 'required_with:google_user|email|max:255',
            'google_user.google_id' => 'required_with:google_user|string|max:255',
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'company_name.unique' => 'Este nome de empresa já está registrado.',
            'company_name.min' => 'O nome da empresa deve ter pelo menos 3 caracteres.',
            'company_name.max' => 'O nome da empresa deve ter no máximo 255 caracteres.',
        ];
    }
}
