<?php

namespace App\Models;

use App\Models\Concerns\SerializesIdsAsStrings;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'campi_id', 'grupo_id', 'local_id',
    'titulo', 'motivo', 'tipo_local',
    'data_inicial', 'data_final', 'horario_inicial', 'horario_final',
    'responsavel_nome', 'observacoes', 'status', 'recorrente',
    'motivo_cancelamento', 'aprovada_por_id', 'aprovada_em',
    'cancelada_por_id', 'cancelada_em',
])]
class Reserva extends Model
{
    use HasFactory, SerializesIdsAsStrings;

    protected $table = 'reservas';

    protected function casts(): array
    {
        return [
            'data_inicial' => 'date:Y-m-d',
            'data_final' => 'date:Y-m-d',
            'recorrente' => 'boolean',
            'aprovada_em' => 'datetime',
            'cancelada_em' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function aprovadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprovada_por_id');
    }

    public function canceladaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelada_por_id');
    }

    public function local(): BelongsTo
    {
        return $this->belongsTo(Local::class);
    }

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class);
    }

    public function campi(): BelongsTo
    {
        return $this->belongsTo(Campi::class);
    }

    public function scopeAtivas(Builder $q): Builder
    {
        return $q->where('status', '!=', 'cancelada');
    }

    public function scopePendentes(Builder $q): Builder
    {
        return $q->where('status', 'pendente');
    }

    /**
     * Reservas ativas que sobrepõem a janela informada no mesmo local.
     */
    public static function conflitos(
        int $localId,
        string $dataInicial,
        string $dataFinal,
        string $horarioInicial,
        string $horarioFinal,
        ?int $ignorarId = null,
    ): Builder {
        return static::query()
            ->ativas()
            ->where('local_id', $localId)
            ->where('data_inicial', '<=', $dataFinal)
            ->where('data_final', '>=', $dataInicial)
            ->where('horario_inicial', '<', $horarioFinal)
            ->where('horario_final', '>', $horarioInicial)
            ->when($ignorarId, fn ($q) => $q->where('id', '!=', $ignorarId));
    }

    /**
     * Retorna a primeira indisponibilidade cadastrada que bloqueia a janela,
     * ou null se o local está livre neste intervalo.
     */
    public static function indisponibilidadeQueBloqueia(
        int $localId,
        string $dataInicial,
        string $dataFinal,
        string $horarioInicial,
        string $horarioFinal,
    ): ?LocalIndisponibilidade {
        $lis = LocalIndisponibilidade::where('local_id', $localId)->get();
        foreach ($lis as $li) {
            if ($li->conflitaCom($dataInicial, $dataFinal, $horarioInicial, $horarioFinal)) {
                return $li;
            }
        }
        return null;
    }
}
