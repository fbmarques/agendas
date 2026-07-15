<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreLocalIndisponibilidadeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo' => ['required', 'in:data_especifica,periodo,recorrente_semanal'],
            'data_inicial' => ['nullable', 'date', 'required_if:tipo,data_especifica', 'required_if:tipo,periodo'],
            'data_final' => ['nullable', 'date', 'after_or_equal:data_inicial', 'required_if:tipo,periodo'],
            'dias_semana' => ['nullable', 'array', 'required_if:tipo,recorrente_semanal'],
            'dias_semana.*' => ['integer', 'between:0,6'],
            'horario_inicial' => ['nullable', 'date_format:H:i'],
            'horario_final' => ['nullable', 'date_format:H:i', 'after:horario_inicial'],
            'motivo' => ['nullable', 'string', 'max:255'],
        ];
    }
}
