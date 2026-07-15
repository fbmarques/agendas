<?php

namespace App\Models;

use App\Models\Concerns\SerializesIdsAsStrings;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nome', 'responsavel_nome', 'responsavel_email', 'quantidade', 'status'])]
class Recurso extends Model
{
    use HasFactory, SerializesIdsAsStrings;

    protected $table = 'recursos';

    protected function casts(): array
    {
        return [
            'quantidade' => 'integer',
        ];
    }

    public function disponibilidades(): HasMany
    {
        return $this->hasMany(RecursoDisponibilidade::class);
    }

    public function reservas(): BelongsToMany
    {
        return $this->belongsToMany(Reserva::class, 'reserva_recurso')
            ->withPivot('quantidade')
            ->withTimestamps();
    }
}
