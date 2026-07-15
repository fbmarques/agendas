<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StorePeriodoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'data_inicio' => ['required', 'date'],
            'data_fim' => ['required', 'date', 'after_or_equal:data_inicio'],
            'status' => ['nullable', 'in:ativo,inativo'],
        ];
    }
}
