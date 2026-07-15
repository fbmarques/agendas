<?php

namespace App\Notifications;

use App\Models\Reserva;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReservaCriada extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Reserva $reserva)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $r = $this->reserva;
        $status = $r->status === 'pendente' ? 'Aguardando aprovação' : 'Confirmada';
        $local = $r->local?->nome ?? '—';

        return (new MailMessage)
            ->subject("Nova reserva: {$r->titulo}")
            ->greeting('Olá!')
            ->line("Uma nova reserva foi criada no sistema Agendas.")
            ->line("**Título:** {$r->titulo}")
            ->line("**Local:** {$local}")
            ->line("**Data:** {$r->data_inicial->format('d/m/Y')} — {$r->data_final->format('d/m/Y')}")
            ->line("**Horário:** ".substr($r->horario_inicial, 0, 5).' às '.substr($r->horario_final, 0, 5))
            ->line("**Responsável:** {$r->responsavel_nome}")
            ->line("**Status:** {$status}");
    }
}
