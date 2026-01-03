<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBalanceRequest extends FormRequest
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
            'reference_date' => 'required|date',
            'opening_balance' => 'required|numeric',
            'closing_balance' => 'required|numeric',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'reference_date.required' => 'A data de referência é obrigatória',
            'reference_date.date' => 'A data de referência deve ser válida',
            'opening_balance.required' => 'O saldo inicial é obrigatório',
            'opening_balance.numeric' => 'O saldo inicial deve ser numérico',
            'closing_balance.required' => 'O saldo de fechamento é obrigatório',
            'closing_balance.numeric' => 'O saldo de fechamento deve ser numérico',
        ];
    }
}
