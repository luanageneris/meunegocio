<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCollaboratorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Qualquer usuário autenticado pode editar colaborador em sua empresa
    }

    public function rules(): array
    {
        $collaboratorRouteParam = $this->route('collaborator') ?? $this->route('id');
        $collaboratorId = $collaboratorRouteParam instanceof \App\Models\Collaborator
            ? $collaboratorRouteParam->id
            : $collaboratorRouteParam;
        
        return [
            'name' => 'required|string|max:255',
            'cpf' => ['required', 'regex:/^\d{3}\.\d{3}\.\d{3}-\d{2}$/', Rule::unique('collaborators', 'cpf')->ignore($collaboratorId)->where('company_id', $this->user()->company_id)],
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
