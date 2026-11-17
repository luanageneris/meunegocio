<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DownloadYearlyReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'between:2000,2100'],
        ];
    }

    public function messages(): array
    {
        return [
            'year.required' => 'Selecione o ano do relatório.',
            'year.integer' => 'O ano do relatório deve ser um número inteiro.',
            'year.between' => 'Selecione um ano entre 2000 e 2100.',
        ];
    }
}
