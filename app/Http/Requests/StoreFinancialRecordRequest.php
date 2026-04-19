<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        $companyId = (int) $this->user()->company_id;

        return [
            'date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'quantity' => 'nullable|numeric|min:1',
            'discount' => 'nullable|numeric|min:0',
            'description' => 'nullable|string|max:500',
            'type' => 'required|in:entry,exit',
            'entry_type_id' => [
                'nullable',
                Rule::exists('entry_types', 'id')->where('company_id', $companyId),
            ],
            'exit_type_id' => [
                'nullable',
                Rule::exists('exit_types', 'id')->where('company_id', $companyId),
            ],
            'service_type_id' => [
                'nullable',
                Rule::exists('service_types', 'id')->where('company_id', $companyId),
            ],
            'collaborator_id' => [
                'nullable',
                Rule::exists('collaborators', 'id')->where('company_id', $companyId),
            ],
            'work_shift_id' => [
                'nullable',
                Rule::exists('work_shifts', 'id')->where('company_id', $companyId),
            ],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $amount = (float) $this->input('amount', 0);
            $quantity = (float) $this->input('quantity', 1);
            $discount = (float) $this->input('discount', 0);
            
            $total = ($amount * $quantity) - $discount;
            
            if ($total < 0) {
                $validator->errors()->add(
                    'discount', 
                    'O desconto não pode ser maior que o valor total. Total calculado: R$ ' . number_format($total, 2, ',', '.')
                );
            }
        });
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
            'type.required' => 'O tipo é obrigatório',
            'type.in' => 'O tipo deve ser "entry" ou "exit"',
            'entry_type_id.exists' => 'O tipo de entrada selecionado não existe',
            'exit_type_id.exists' => 'O tipo de saída selecionado não existe',
            'service_type_id.exists' => 'O tipo de serviço selecionado não existe',
            'collaborator_id.exists' => 'O colaborador selecionado não existe',
            'work_shift_id.exists' => 'O turno selecionado não existe',
        ];
    }
}
