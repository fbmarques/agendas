<?php

namespace App\Models;

use App\Models\Concerns\SerializesIdsAsStrings;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'full_name', 'email', 'role', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SerializesIdsAsStrings;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function locaisGerenciados(): BelongsToMany
    {
        return $this->belongsToMany(Local::class, 'local_gerentes')->withTimestamps();
    }

    public function recursosGerenciados(): BelongsToMany
    {
        return $this->belongsToMany(Recurso::class, 'recurso_gerentes')->withTimestamps();
    }

    public function podeAprovarReserva(Reserva $reserva): bool
    {
        if ($this->isAdmin()) return true;
        $local = $reserva->local;
        return $local ? $local->temGerente($this) : false;
    }
}
