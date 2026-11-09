<?php

namespace App\Http\Requests;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFinancialRecordFieldRequest extends FormRequest
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
        $table = match ($this->input('field')) {
            'entry_type_id' => 'entry_types',
            'exit_type_id' => 'exit_types',
            'collaborator_id' => 'collaborators',
            default => null,
        };
        $isDate = $this->input('field') === 'date';
        $isDescription = $this->input('field') === 'description';
        $valueRules = match (true) {
            $isDate => ['required', 'date_format:Y-m-d'],
            $isDescription => ['present', 'nullable', 'string', 'max:500'],
            default => ['present', 'nullable', 'integer'],
        };
        if ($table !== null) {
            $valueRules[] = Rule::exists($table, 'id')
                ->where('company_id', $this->user()->company_id)
                ->whereNull('deleted_at');
        }

        return [
            'field' => ['required', Rule::in(['entry_type_id', 'exit_type_id', 'collaborator_id', 'date', 'description'])],
            'value' => $valueRules,
            'expected_value' => $isDescription
                ? ['present', 'nullable', 'string', 'max:500']
                : ($isDate ? ['required', 'date_format:Y-m-d'] : ['present', 'nullable', 'integer']),
            'expected_type' => ['required', Rule::in(['entry', 'exit'])],
        ];
    }

    public function messages(): array
    {
        return [
            'field.*' => 'O campo selecionado é inválido.',
            'value.*' => match ($this->input('field')) {
                'date' => 'Informe uma data válida.',
                'description' => 'A descrição deve ter no máximo 500 caracteres.',
                default => 'A opção selecionada não está disponível nesta empresa.',
            },
            'expected_value.*' => 'O valor original é inválido. Atualize a página e tente novamente.',
            'expected_type.*' => 'O tipo original é inválido. Atualize a página e tente novamente.',
        ];
    }
}
