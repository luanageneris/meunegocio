<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('sanctum') && ($this->user('sanctum')->role === 'root' || $this->user('sanctum')->role === 'owner');
    }

    /**
     * Normalize empty strings to null to avoid unique constraints with blank values.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => $this->filled('email') ? $this->input('email') : null,
            'phone' => $this->filled('phone') ? $this->input('phone') : null,
            'document' => $this->filled('document') ? $this->input('document') : null,
            'address' => $this->filled('address') ? $this->input('address') : null,
            'city' => $this->filled('city') ? $this->input('city') : null,
            'state' => $this->filled('state') ? $this->input('state') : null,
            'postal_code' => $this->filled('postal_code') ? $this->input('postal_code') : null,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $companyId = $this->user('sanctum')?->company_id;

        return [
            'name' => 'required|string|max:255|unique:companies,name,' . $companyId,
            'email' => 'nullable|email|max:255|unique:companies,email,' . $companyId,
            'phone' => 'nullable|string|max:20',
            'document' => 'nullable|string|max:20|unique:companies,document,' . $companyId,
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:2',
            'postal_code' => 'nullable|string|max:20',
            'use_service_types' => 'sometimes|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'O nome da empresa é obrigatório.',
            'name.unique' => 'Este nome de empresa já está registrado.',
            'email.email' => 'O email deve ser válido.',
            'email.unique' => 'Este email já está registrado.',
        ];
    }
}
