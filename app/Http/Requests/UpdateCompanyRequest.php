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
