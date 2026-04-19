<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCommissionRequest extends FormRequest
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
        $companyId = (int) $this->user()->company_id;

        return [
            'date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'collaborator_id' => [
                'required',
                Rule::exists('collaborators', 'id')->where('company_id', $companyId),
            ],
            'financial_record_id' => [
                'nullable',
                Rule::exists('financial_records', 'id')->where('company_id', $companyId),
            ],
            'paid_at' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
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
            'amount.required' => 'O valor é obrigatório',
            'amount.numeric' => 'O valor deve ser numérico',
            'amount.min' => 'O valor deve ser maior que 0',
            'collaborator_id.required' => 'O colaborador é obrigatório',
            'collaborator_id.exists' => 'O colaborador selecionado não existe',
            'financial_record_id.exists' => 'O registro financeiro selecionado não existe',
        ];
    }
}
