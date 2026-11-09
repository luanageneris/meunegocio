<?php

namespace App\Http\Requests;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFinancialRecordTotalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->canManageFinancialRecords();
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Apenas o proprietário da empresa pode alterar registros financeiros.');
    }

    public function rules(): array
    {
        return [
            'total_cents' => 'required|integer|min:0|max:9007199254740991',
            'expected_total_cents' => 'required|integer|min:0|max:9007199254740991',
        ];
    }

    public function messages(): array
    {
        return [
            'total_cents.*' => 'Informe um valor total válido.',
            'expected_total_cents.*' => 'O valor original é inválido. Atualize a página e tente novamente.',
        ];
    }
}
