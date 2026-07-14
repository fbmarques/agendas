<?php

namespace App\Http\Requests\Api;

use App\Models\Grupo;
use App\Models\Local;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLocalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => ['sometimes', 'required', 'string', 'max:255'],
            'campi_id' => ['sometimes', 'required', 'integer', 'exists:campi,id'],
            'grupo_id' => [
                'sometimes',
                'required',
                'integer',
                'exists:grupos,id',
                function (string $attribute, mixed $value, Closure $fail) {
                    $campiId = $this->input('campi_id') ?? $this->route('local')?->campi_id;
                    $grupo = Grupo::find($value);
                    if ($grupo && $campiId && $grupo->campi_id !== (int) $campiId) {
                        $fail('O grupo não pertence ao campi informado.');
                    }
                },
            ],
            'tipo' => ['sometimes', 'required', 'string', Rule::in(Local::tiposPermitidos())],
            'capacidade' => ['nullable', 'integer', 'min:0'],
            'descricao' => ['nullable', 'string'],
            'recursos' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:ativo,inativo'],
        ];
    }
}
