<?php

namespace App\Policies;

use App\Models\Reserva;
use App\Models\User;

class ReservaPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Reserva $reserva): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Reserva $reserva): bool
    {
        return $user->isAdmin() || $user->id === $reserva->user_id;
    }

    public function delete(User $user, Reserva $reserva): bool
    {
        return $user->isAdmin() || $user->id === $reserva->user_id;
    }

    public function aprovar(User $user, Reserva $reserva): bool
    {
        return $reserva->status === 'pendente' && $user->podeAprovarReserva($reserva);
    }

    public function cancelar(User $user, Reserva $reserva): bool
    {
        if ($reserva->status === 'cancelada') return false;
        return $user->podeAprovarReserva($reserva) || $user->id === $reserva->user_id;
    }
}
