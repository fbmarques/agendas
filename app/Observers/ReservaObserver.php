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
        // Recursos são anexados via pivot DEPOIS de create(), então
        // notificarRecursos() é chamada explicitamente pelo controller.
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
     * Envia a notificação para: (1) gerentes de cada recurso (User-typed) +
     * (2) responsavel_email de cada recurso (anônimo, para responsável que
     * não é usuário do sistema). Evita duplicata quando o gerente também
     * for o responsavel_email.
     *
     * Público porque é chamado do controller após o attach do pivot (o
     * observer created() dispara antes do attach).
     */
    public function notificarRecursos(Reserva $reserva, $notification): void
    {
        $this->notificarResponsaveisRecursos($reserva, $notification);
    }

    private function notificarResponsaveisRecursos(Reserva $reserva, $notification): void
    {
        $emailsJaNotificados = [];

        foreach ($reserva->recursos as $recurso) {
            foreach ($recurso->gerentes as $gerente) {
                if (! $gerente->email) continue;
                $key = strtolower(trim($gerente->email));
                if (isset($emailsJaNotificados[$key])) continue;
                $emailsJaNotificados[$key] = true;
                Notification::send([$gerente], $notification);
            }

            $respEmail = $recurso->responsavel_email;
            if ($respEmail) {
                $key = strtolower(trim($respEmail));
                if (isset($emailsJaNotificados[$key])) continue;
                $emailsJaNotificados[$key] = true;
                Notification::route('mail', $respEmail)->notify($notification);
            }
        }
    }
}
