<?php

namespace App\Http\Requests\Api;

use App\Models\Local;
use App\Models\Reserva;
use App\Rules\PalavrasMinimas;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StoreReservaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'titulo' => ['required', 'string', 'max:255'],
            'motivo' => ['required', 'string', new PalavrasMinimas(10)],
            'campi_id' => ['required', 'integer', 'exists:campi,id'],
            'grupo_id' => ['required', 'integer', 'exists:grupos,id'],
            'local_id' => ['required', 'integer', 'exists:locais,id'],
            'tipo_local' => ['nullable', 'string'],
            'data_inicial' => ['required', 'date'],
            'data_final' => ['required', 'date', 'after_or_equal:data_inicial'],
            'horario_inicial' => ['required', 'date_format:H:i'],
            'horario_final' => ['required', 'date_format:H:i', 'after:horario_inicial'],
            'responsavel_nome' => ['required', 'string', 'max:255'],
            'observacoes' => ['nullable', 'string'],
            'status' => ['nullable', 'in:confirmada,pendente,cancelada'],
            'recorrente' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if ($v->errors()->isNotEmpty()) return;

            $localId = (int) $this->input('local_id');
            $local = Local::find($localId);
            if (! $local) return;

            $conflito = Reserva::conflitos(
                $localId,
                $this->input('data_inicial'),
                $this->input('data_final'),
                $this->input('horario_inicial'),
                $this->input('horario_final'),
            )->first();

            if ($conflito) {
                $v->errors()->add(
                    'local_id',
                    "Já existe reserva ativa para este local nesse período: {$conflito->titulo}."
                );
            }
        });
    }
}
