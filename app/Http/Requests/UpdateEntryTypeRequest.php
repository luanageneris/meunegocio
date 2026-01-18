<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEntryTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isRoot();
    }

    public function rules(): array
    {
        $entryTypeId = $this->route('entry_type');
        
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('entry_types', 'name')->ignore($entryTypeId)->where('company_id', $this->user()->company_id)],
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
