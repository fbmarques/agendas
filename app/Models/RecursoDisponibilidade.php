<?php

namespace App\Models;

use App\Models\Concerns\SerializesIdsAsStrings;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['recurso_id', 'dias_semana', 'horario_inicial', 'horario_final'])]
class RecursoDisponibilidade extends Model
{
    use HasFactory, SerializesIdsAsStrings;

    protected $table = 'recurso_disponibilidades';

    protected function casts(): array
    {
        return [
            'dias_semana' => 'array',
        ];
    }

    public function recurso(): BelongsTo
    {
        return $this->belongsTo(Recurso::class);
    }
}
