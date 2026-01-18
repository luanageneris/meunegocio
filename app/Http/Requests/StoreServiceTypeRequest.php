<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreServiceTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isRoot();
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:service_types,name',
            'unit' => 'required|string|max:50',
            'service_value' => 'required|numeric|min:0',
            'commission_percentage' => 'required|numeric|min:0|max:100',
            'commission_value' => 'nullable|numeric|min:0',
            'resource_value' => 'nullable|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nome do serviço é obrigatório',
            'name.unique' => 'Este tipo de serviço já existe',
            'unit.required' => 'Unidade é obrigatória',
            'service_value.required' => 'Valor do serviço é obrigatório',
            'service_value.numeric' => 'Valor deve ser um número',
            'commission_percentage.required' => 'Percentual de comissão é obrigatório',
            'commission_percentage.numeric' => 'Percentual deve ser um número',
            'commission_percentage.min' => 'Percentual não pode ser negativo',
            'commission_percentage.max' => 'Percentual não pode ser maior que 100%',
        ];
    }
}
