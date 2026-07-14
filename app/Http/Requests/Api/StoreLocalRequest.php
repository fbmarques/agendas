<?php

namespace App\Http\Requests\Api;

use App\Models\Grupo;
use App\Models\Local;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLocalRequest extends FormRequest
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
            'grupo_id' => [
                'required',
                'integer',
                'exists:grupos,id',
                function (string $attribute, mixed $value, Closure $fail) {
                    $grupo = Grupo::find($value);
                    if ($grupo && $grupo->campi_id !== (int) $this->input('campi_id')) {
                        $fail('O grupo não pertence ao campi informado.');
                    }
                },
            ],
            'tipo' => ['required', 'string', Rule::in(Local::tiposPermitidos())],
            'capacidade' => ['nullable', 'integer', 'min:0'],
            'descricao' => ['nullable', 'string'],
            'recursos' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:ativo,inativo'],
        ];
    }
}
