<?php

namespace App\Notifications;

use App\Models\Recurso;
use App\Models\Reserva;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReservaRecursoRemovido extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Reserva $reserva,
        public Recurso $recurso,
        public ?string $motivo = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $r = $this->reserva;
        $local = $r->local?->nome ?? '—';
        $motivo = $this->motivo ?: 'Unidade do recurso foi inativada pela administração.';

        return (new MailMessage)
            ->subject("Recurso {$this->recurso->nome} removido da sua reserva")
            ->greeting('Aviso sobre sua reserva')
            ->line("O recurso **{$this->recurso->nome}** foi desvinculado da sua reserva '{$r->titulo}'.")
            ->line("**Local:** {$local}")
            ->line("**Data:** {$r->data_inicial->format('d/m/Y')} — {$r->data_final->format('d/m/Y')}")
            ->line("**Horário:** ".substr($r->horario_inicial, 0, 5).' às '.substr($r->horario_final, 0, 5))
            ->line("**Motivo:** {$motivo}")
            ->line('A reserva do local continua ativa.');
    }
}
