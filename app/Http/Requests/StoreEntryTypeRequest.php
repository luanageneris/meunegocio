<?php

namespace App\Http\Requests;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEntryTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->canManageFinancialRecords();
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Apenas o proprietário da empresa pode criar tipos de entrada.');
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('entry_types', 'name')->where('company_id', $this->user()->company_id),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nome do tipo de entrada é obrigatório',
            'name.unique' => 'Este tipo de entrada já existe',
        ];
    }
}
