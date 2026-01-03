<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFinancialRecordRequest extends FormRequest
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
            'amount' => 'required|numeric|min:0.01',
            'quantity' => 'nullable|numeric|min:1',
            'discount' => 'nullable|numeric|min:0',
            'description' => 'nullable|string|max:500',
            'type' => 'nullable|in:income,expense',
            'entry_type_id' => 'nullable|exists:entry_types,id',
            'exit_type_id' => 'nullable|exists:exit_types,id',
            'service_type_id' => 'nullable|exists:service_types,id',
            'collaborator_id' => 'nullable|exists:collaborators,id',
            'work_shift_id' => 'nullable|exists:work_shifts,id',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'company_id' => $this->user()->company_id,
        ]);
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'date.required' => 'A data é obrigatória',
            'date.date' => 'A data deve ser válida',
            'amount.required' => 'O valor é obrigatório',
            'amount.numeric' => 'O valor deve ser numérico',
            'amount.min' => 'O valor deve ser maior que 0',
            'entry_type_id.exists' => 'O tipo de entrada selecionado não existe',
            'exit_type_id.exists' => 'O tipo de saída selecionado não existe',
            'service_type_id.exists' => 'O tipo de serviço selecionado não existe',
            'collaborator_id.exists' => 'O colaborador selecionado não existe',
            'work_shift_id.exists' => 'O turno selecionado não existe',
        ];
    }
}
