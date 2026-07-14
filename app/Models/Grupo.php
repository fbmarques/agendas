<?php

namespace App\Models;

use App\Models\Concerns\SerializesIdsAsStrings;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['nome', 'campi_id', 'descricao', 'status'])]
class Grupo extends Model
{
    use HasFactory, SerializesIdsAsStrings;

    protected $table = 'grupos';

    public function campi(): BelongsTo
    {
        return $this->belongsTo(Campi::class, 'campi_id');
    }
}
