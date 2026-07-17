<?php

namespace App\Models;

use App\Models\Concerns\SerializesIdsAsStrings;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['recurso_id', 'patrimonio', 'status', 'observacoes'])]
class RecursoUnidade extends Model
{
    use HasFactory, SerializesIdsAsStrings;

    protected $table = 'recurso_unidades';

    public function recurso(): BelongsTo
    {
        return $this->belongsTo(Recurso::class);
    }
}
