<?php

namespace App\Models;

use App\Models\Concerns\SerializesIdsAsStrings;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['nome', 'campi_id', 'grupo_id', 'tipo', 'capacidade', 'descricao', 'recursos', 'status'])]
class Local extends Model
{
    use HasFactory, SerializesIdsAsStrings;

    protected $table = 'locais';

    protected function casts(): array
    {
        return [
            'capacidade' => 'integer',
        ];
    }

    public function campi(): BelongsTo
    {
        return $this->belongsTo(Campi::class, 'campi_id');
    }

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class, 'grupo_id');
    }

    public static function tiposPermitidos(): array
    {
        return array_map(fn ($t) => $t['nome'], config('tipos_local'));
    }
}
