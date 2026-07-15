<?php

namespace App\Notifications;

use App\Models\Reserva;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReservaAprovada extends Notification implements ShouldQueue
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
        $local = $r->local?->nome ?? '—';
        $aprovador = $r->aprovadaPor?->full_name ?? 'Administração';

        return (new MailMessage)
            ->subject("Reserva aprovada: {$r->titulo}")
            ->greeting('Boa notícia!')
            ->line("Sua reserva foi aprovada por {$aprovador}.")
            ->line("**Título:** {$r->titulo}")
            ->line("**Local:** {$local}")
            ->line("**Data:** {$r->data_inicial->format('d/m/Y')} — {$r->data_final->format('d/m/Y')}")
            ->line("**Horário:** ".substr($r->horario_inicial, 0, 5).' às '.substr($r->horario_final, 0, 5));
    }
}
