<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkShiftRequest extends FormRequest
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
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'collaborator_id' => 'required|exists:collaborators,id',
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
            'start_time.required' => 'A hora de início é obrigatória',
            'start_time.date_format' => 'A hora de início deve estar no formato HH:mm',
            'end_time.required' => 'A hora de término é obrigatória',
            'end_time.date_format' => 'A hora de término deve estar no formato HH:mm',
            'end_time.after' => 'A hora de término deve ser após a hora de início',
            'collaborator_id.required' => 'O colaborador é obrigatório',
            'collaborator_id.exists' => 'O colaborador selecionado não existe',
        ];
    }
}
