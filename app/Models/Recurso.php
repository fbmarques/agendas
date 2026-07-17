<?php

namespace App\Models;

use App\Models\Concerns\SerializesIdsAsStrings;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nome', 'responsavel_nome', 'responsavel_email', 'status'])]
class Recurso extends Model
{
    use HasFactory, SerializesIdsAsStrings;

    protected $table = 'recursos';

    protected $appends = ['quantidade'];

    public function disponibilidades(): HasMany
    {
        return $this->hasMany(RecursoDisponibilidade::class);
    }

    public function unidades(): HasMany
    {
        return $this->hasMany(RecursoUnidade::class);
    }

    public function unidadesAtivas(): HasMany
    {
        return $this->hasMany(RecursoUnidade::class)->where('status', 'ativo');
    }

    public function reservas(): BelongsToMany
    {
        return $this->belongsToMany(Reserva::class, 'reserva_recurso')
            ->withPivot('quantidade')
            ->withTimestamps();
    }

    public function gerentes(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'recurso_gerentes')->withTimestamps();
    }

    public function temGerente(?User $user): bool
    {
        if (! $user) return false;
        return $this->gerentes()->where('users.id', $user->id)->exists();
    }

    // Quantidade agora é derivada do número de unidades ativas (patrimônios).
    // Se o registro foi carregado com withCount('unidadesAtivas'), reaproveita
    // a contagem para evitar N+1 nas listagens.
    public function getQuantidadeAttribute(): int
    {
        if (array_key_exists('unidades_ativas_count', $this->attributes)) {
            return (int) $this->attributes['unidades_ativas_count'];
        }
        return $this->unidadesAtivas()->count();
    }
}
