<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFinancialRecordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->isRoot();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'date' => 'required|date',
            'value' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:500',
            'entry_type_id' => 'nullable|exists:entry_types,id',
            'exit_type_id' => 'nullable|exists:exit_types,id',
            'service_type_id' => 'nullable|exists:service_types,id',
            'collaborator_id' => 'nullable|exists:collaborators,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'date.required' => 'A data é obrigatória',
            'date.date' => 'A data deve ser válida',
            'value.required' => 'O valor é obrigatório',
            'value.numeric' => 'O valor deve ser numérico',
            'value.min' => 'O valor deve ser maior que 0',
            'entry_type_id.exists' => 'O tipo de entrada selecionado não existe',
            'exit_type_id.exists' => 'O tipo de saída selecionado não existe',
            'service_type_id.exists' => 'O tipo de serviço selecionado não existe',
            'collaborator_id.exists' => 'O colaborador selecionado não existe',
        ];
    }
}
