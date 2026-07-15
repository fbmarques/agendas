<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePeriodoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => ['sometimes', 'required', 'string', 'max:255'],
            'data_inicio' => ['sometimes', 'required', 'date'],
            'data_fim' => ['sometimes', 'required', 'date', 'after_or_equal:data_inicio'],
            'status' => ['sometimes', 'in:ativo,inativo'],
        ];
    }
}
