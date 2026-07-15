<?php

namespace App\Observers;

use App\Models\Reserva;
use App\Notifications\ReservaAprovada;
use App\Notifications\ReservaCancelada;
use App\Notifications\ReservaCriada;
use Illuminate\Support\Facades\Notification;

class ReservaObserver
{
    public function created(Reserva $reserva): void
    {
        $destinatarios = $this->destinatariosBase($reserva);
        if ($destinatarios->isEmpty()) return;

        Notification::send($destinatarios, new ReservaCriada($reserva));
    }

    public function updated(Reserva $reserva): void
    {
        if (! $reserva->wasChanged('status')) return;

        $novo = $reserva->status;
        $anterior = $reserva->getOriginal('status');

        if ($anterior === 'pendente' && $novo === 'confirmada') {
            $destinatarios = collect([$reserva->user])->filter();
            if ($destinatarios->isNotEmpty()) {
                Notification::send($destinatarios, new ReservaAprovada($reserva));
            }
            return;
        }

        if ($novo === 'cancelada' && $anterior !== 'cancelada') {
            $destinatarios = $this->destinatariosBase($reserva);
            if ($destinatarios->isNotEmpty()) {
                Notification::send($destinatarios, new ReservaCancelada($reserva));
            }
        }
    }

    /**
     * Dono da reserva + gerentes do Local, sem duplicatas por email.
     */
    private function destinatariosBase(Reserva $reserva)
    {
        $lista = collect();
        if ($reserva->user) $lista->push($reserva->user);
        if ($reserva->local) {
            foreach ($reserva->local->gerentes as $g) $lista->push($g);
        }
        return $lista->unique(fn ($u) => $u->email)->values();
    }
}
