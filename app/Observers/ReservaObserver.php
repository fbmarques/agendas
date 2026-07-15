<?php

namespace App\Observers;

use App\Models\Reserva;
use App\Notifications\ReservaAprovada;
use App\Notifications\ReservaCancelada;
use App\Notifications\ReservaCriada;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

class ReservaObserver
{
    public function created(Reserva $reserva): void
    {
        $usuarios = $this->destinatariosUsuarios($reserva);
        if ($usuarios->isNotEmpty()) {
            Notification::send($usuarios, new ReservaCriada($reserva));
        }
        $this->notificarResponsaveisRecursos($reserva, new ReservaCriada($reserva));
    }

    public function updated(Reserva $reserva): void
    {
        if (! $reserva->wasChanged('status')) return;

        $novo = $reserva->status;
        $anterior = $reserva->getOriginal('status');

        if ($anterior === 'pendente' && $novo === 'confirmada') {
            $usuarios = collect([$reserva->user])->filter();
            if ($usuarios->isNotEmpty()) {
                Notification::send($usuarios, new ReservaAprovada($reserva));
            }
            $this->notificarResponsaveisRecursos($reserva, new ReservaAprovada($reserva));
            return;
        }

        if ($novo === 'cancelada' && $anterior !== 'cancelada') {
            $usuarios = $this->destinatariosUsuarios($reserva);
            if ($usuarios->isNotEmpty()) {
                Notification::send($usuarios, new ReservaCancelada($reserva));
            }
            $this->notificarResponsaveisRecursos($reserva, new ReservaCancelada($reserva));
        }
    }

    /**
     * Dono da reserva + gerentes do Local (users com email), sem duplicatas por email.
     */
    private function destinatariosUsuarios(Reserva $reserva)
    {
        $lista = collect();
        if ($reserva->user) $lista->push($reserva->user);
        if ($reserva->local) {
            foreach ($reserva->local->gerentes as $g) $lista->push($g);
        }
        return $lista->unique(fn ($u) => $u->email)->values();
    }

    /**
     * Responsáveis dos recursos (por email, mesmo que não sejam usuários do sistema).
     */
    private function notificarResponsaveisRecursos(Reserva $reserva, $notification): void
    {
        $emails = $reserva->recursos->pluck('responsavel_email')->filter()->unique()->values();
        foreach ($emails as $email) {
            Notification::route('mail', $email)->notify($notification);
        }
    }
}
