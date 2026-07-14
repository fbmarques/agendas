<?php

namespace App\Http\Requests\Api;

use App\Rules\PalavrasMinimas;
use Illuminate\Foundation\Http\FormRequest;

class BulkStoreReservaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reservas' => ['required', 'array', 'min:1'],
            'reservas.*.titulo' => ['required', 'string', 'max:255'],
            'reservas.*.motivo' => ['required', 'string', new PalavrasMinimas(10)],
            'reservas.*.campi_id' => ['required', 'integer', 'exists:campi,id'],
            'reservas.*.grupo_id' => ['required', 'integer', 'exists:grupos,id'],
            'reservas.*.local_id' => ['required', 'integer', 'exists:locais,id'],
            'reservas.*.tipo_local' => ['nullable', 'string'],
            'reservas.*.data_inicial' => ['required', 'date'],
            'reservas.*.data_final' => ['required', 'date', 'after_or_equal:reservas.*.data_inicial'],
            'reservas.*.horario_inicial' => ['required', 'date_format:H:i'],
            'reservas.*.horario_final' => ['required', 'date_format:H:i', 'after:reservas.*.horario_inicial'],
            'reservas.*.responsavel_nome' => ['required', 'string', 'max:255'],
            'reservas.*.observacoes' => ['nullable', 'string'],
            'reservas.*.status' => ['nullable', 'in:confirmada,pendente,cancelada'],
            'reservas.*.recorrente' => ['nullable', 'boolean'],
        ];
    }
}
