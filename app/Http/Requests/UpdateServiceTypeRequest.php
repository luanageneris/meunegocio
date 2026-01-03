<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateServiceTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isRoot();
    }

    public function rules(): array
    {
        $serviceTypeId = $this->route('service_type');
        
        return [
            'name' => "required|string|max:255|unique:service_types,name,{$serviceTypeId}",
            'commission_percentage' => 'required|numeric|min:0|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nome do serviço é obrigatório',
            'name.unique' => 'Este tipo de serviço já existe',
            'commission_percentage.required' => 'Percentual de comissão é obrigatório',
            'commission_percentage.numeric' => 'Percentual deve ser um número',
            'commission_percentage.min' => 'Percentual não pode ser negativo',
            'commission_percentage.max' => 'Percentual não pode ser maior que 100%',
        ];
    }
}
