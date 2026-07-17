<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRecursoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => ['sometimes', 'required', 'string', 'max:255'],
            'responsavel_nome' => ['sometimes', 'nullable', 'string', 'max:255'],
            'responsavel_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'status' => ['sometimes', 'in:ativo,inativo'],
            'gerentes_ids' => ['sometimes', 'array'],
            'gerentes_ids.*' => ['integer', 'exists:users,id'],
            'disponibilidades' => ['sometimes', 'array'],
            'disponibilidades.*.dias_semana' => ['required', 'array', 'min:1'],
            'disponibilidades.*.dias_semana.*' => ['integer', 'between:0,6'],
            'disponibilidades.*.horario_inicial' => ['required', 'date_format:H:i'],
            'disponibilidades.*.horario_final' => ['required', 'date_format:H:i', 'after:disponibilidades.*.horario_inicial'],
        ];
    }
}
