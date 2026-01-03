<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEntryTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isRoot();
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:entry_types,name',
            'requires_collaborator' => 'boolean',
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
