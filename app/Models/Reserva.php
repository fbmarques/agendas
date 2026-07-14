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
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
}
