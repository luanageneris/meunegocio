<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExitTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isRoot();
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('exit_types', 'name')->where('company_id', $this->user()->company_id),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nome do tipo de saída é obrigatório',
            'name.unique' => 'Este tipo de saída já existe',
        ];
    }
}
