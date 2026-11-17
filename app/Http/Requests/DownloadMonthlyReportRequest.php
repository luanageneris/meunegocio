<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DownloadMonthlyReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
        ];
    }

    public function messages(): array
    {
        return [
            'month.required' => 'Selecione o mês do relatório.',
            'month.integer' => 'O mês do relatório deve ser um número inteiro.',
            'month.between' => 'Selecione um mês entre 1 e 12.',
            'year.required' => 'Selecione o ano do relatório.',
            'year.integer' => 'O ano do relatório deve ser um número inteiro.',
            'year.between' => 'Selecione um ano entre 2000 e 2100.',
        ];
    }
}
