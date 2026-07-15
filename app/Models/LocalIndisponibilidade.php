<?php

namespace App\Models;

use App\Models\Concerns\SerializesIdsAsStrings;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'local_id', 'tipo',
    'data_inicial', 'data_final',
    'dias_semana', 'horario_inicial', 'horario_final',
    'motivo',
])]
class LocalIndisponibilidade extends Model
{
    use HasFactory, SerializesIdsAsStrings;

    protected $table = 'local_indisponibilidades';

    protected function casts(): array
    {
        return [
            'data_inicial' => 'date:Y-m-d',
            'data_final' => 'date:Y-m-d',
            'dias_semana' => 'array',
        ];
    }

    public function local(): BelongsTo
    {
        return $this->belongsTo(Local::class);
    }

    /**
     * Verifica se este registro bloqueia a janela informada.
     * Considera sobreposição de datas e de horários; se horário nulo, é dia todo.
     */
    public function conflitaCom(string $dataInicial, string $dataFinal, string $horarioInicial, string $horarioFinal): bool
    {
        $sobrepoeHorario = function () use ($horarioInicial, $horarioFinal): bool {
            if ($this->horario_inicial === null && $this->horario_final === null) return true;
            $hi = $this->horario_inicial ? substr($this->horario_inicial, 0, 5) : '00:00';
            $hf = $this->horario_final ? substr($this->horario_final, 0, 5) : '23:59';
            return $hi < $horarioFinal && $horarioInicial < $hf;
        };

        if ($this->tipo === 'data_especifica') {
            $data = optional($this->data_inicial)->format('Y-m-d');
            if (! $data) return false;
            if ($data < $dataInicial || $data > $dataFinal) return false;
            return $sobrepoeHorario();
        }

        if ($this->tipo === 'periodo') {
            $di = optional($this->data_inicial)->format('Y-m-d');
            $df = optional($this->data_final)->format('Y-m-d') ?? $di;
            if (! $di) return false;
            if ($di > $dataFinal || $df < $dataInicial) return false;
            return $sobrepoeHorario();
        }

        if ($this->tipo === 'recorrente_semanal') {
            $dias = is_array($this->dias_semana) ? $this->dias_semana : [];
            if (empty($dias)) return false;

            $start = strtotime($dataInicial);
            $end = strtotime($dataFinal);
            for ($t = $start; $t <= $end; $t += 86400) {
                if (in_array((int) date('w', $t), $dias, true)) {
                    return $sobrepoeHorario();
                }
            }
            return false;
        }

        return false;
    }

    public static function conflitosNoLocal(int $localId, string $dataInicial, string $dataFinal, string $horarioInicial, string $horarioFinal): Builder
    {
        return static::query()->where('local_id', $localId);
    }
}
