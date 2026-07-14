<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreGrupoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'campi_id' => ['required', 'integer', 'exists:campi,id'],
            'descricao' => ['nullable', 'string'],
            'status' => ['nullable', 'in:ativo,inativo'],
        ];
    }
}
