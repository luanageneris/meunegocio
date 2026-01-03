<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCollaboratorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isRoot();
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'cpf' => 'required|regex:/^\d{3}\.\d{3}\.\d{3}-\d{2}$|unique:collaborators,cpf',
            'active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nome do colaborador é obrigatório',
            'cpf.required' => 'CPF é obrigatório',
            'cpf.regex' => 'CPF deve estar no formato XXX.XXX.XXX-XX',
            'cpf.unique' => 'Este CPF já está cadastrado',
        ];
    }
}
