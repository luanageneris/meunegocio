<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class InvertFinancialRecordRequest extends UpdateFinancialRecordRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $companyId = (int) $this->user()->company_id;

        $rules['entry_type_id'] = [
            'required_if:type,entry',
            'nullable',
            Rule::exists('entry_types', 'id')->where('company_id', $companyId)->whereNull('deleted_at'),
        ];
        $rules['exit_type_id'] = [
            'required_if:type,exit',
            'nullable',
            Rule::exists('exit_types', 'id')->where('company_id', $companyId)->whereNull('deleted_at'),
        ];

        return $rules;
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'entry_type_id.required_if' => 'Selecione um tipo de entrada.',
            'exit_type_id.required_if' => 'Selecione um tipo de saída.',
        ]);
    }
}
