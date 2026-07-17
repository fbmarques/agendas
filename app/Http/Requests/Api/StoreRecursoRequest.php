<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreRecursoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'responsavel_nome' => ['nullable', 'string', 'max:255'],
            'responsavel_email' => ['nullable', 'email', 'max:255'],
            'status' => ['nullable', 'in:ativo,inativo'],
            'gerentes_ids' => ['nullable', 'array'],
            'gerentes_ids.*' => ['integer', 'exists:users,id'],
            'disponibilidades' => ['nullable', 'array'],
            'disponibilidades.*.dias_semana' => ['required', 'array', 'min:1'],
            'disponibilidades.*.dias_semana.*' => ['integer', 'between:0,6'],
            'disponibilidades.*.horario_inicial' => ['required', 'date_format:H:i'],
            'disponibilidades.*.horario_final' => ['required', 'date_format:H:i', 'after:disponibilidades.*.horario_inicial'],
            'unidades' => ['nullable', 'array'],
            'unidades.*.patrimonio' => ['required', 'string', 'max:60', 'distinct'],
            'unidades.*.observacoes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
