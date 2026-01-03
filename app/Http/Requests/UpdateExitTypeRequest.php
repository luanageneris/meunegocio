<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExitTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isRoot();
    }

    public function rules(): array
    {
        $exitTypeId = $this->route('exit_type');
        
        return [
            'name' => "required|string|max:255|unique:exit_types,name,{$exitTypeId}",
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
