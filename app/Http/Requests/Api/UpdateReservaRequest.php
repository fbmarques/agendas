<?php

namespace App\Http\Requests\Api;

use App\Models\Reserva;
use App\Rules\PalavrasMinimas;
use Illuminate\Foundation\Http\FormRequest;

class UpdateReservaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'titulo' => ['sometimes', 'required', 'string', 'max:255'],
            'motivo' => ['sometimes', 'required', 'string', new PalavrasMinimas(10)],
            'campi_id' => ['sometimes', 'required', 'integer', 'exists:campi,id'],
            'grupo_id' => ['sometimes', 'required', 'integer', 'exists:grupos,id'],
            'local_id' => ['sometimes', 'required', 'integer', 'exists:locais,id'],
            'tipo_local' => ['sometimes', 'nullable', 'string'],
            'data_inicial' => ['sometimes', 'required', 'date'],
            'data_final' => ['sometimes', 'required', 'date', 'after_or_equal:data_inicial'],
            'horario_inicial' => ['sometimes', 'required', 'date_format:H:i'],
            'horario_final' => ['sometimes', 'required', 'date_format:H:i', 'after:horario_inicial'],
            'responsavel_nome' => ['sometimes', 'required', 'string', 'max:255'],
            'observacoes' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'in:confirmada,pendente,cancelada'],
            'recorrente' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if ($v->errors()->isNotEmpty()) return;

            /** @var Reserva $reserva */
            $reserva = $this->route('reserva');
            if (! $reserva) return;

            $localId = (int) ($this->input('local_id') ?? $reserva->local_id);
            $dataInicial = $this->input('data_inicial') ?? $reserva->data_inicial->format('Y-m-d');
            $dataFinal = $this->input('data_final') ?? $reserva->data_final->format('Y-m-d');
            $hInicial = $this->input('horario_inicial') ?? substr($reserva->horario_inicial, 0, 5);
            $hFinal = $this->input('horario_final') ?? substr($reserva->horario_final, 0, 5);

            $conflito = Reserva::conflitos($localId, $dataInicial, $dataFinal, $hInicial, $hFinal, $reserva->id)->first();

            if ($conflito) {
                $v->errors()->add('local_id', "Já existe reserva ativa nesse período: {$conflito->titulo}.");
            }
        });
    }
}
