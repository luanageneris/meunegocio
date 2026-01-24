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
            'company_name' => 'required|string|min:3|max:255|unique:companies,name',
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
