<?php

namespace Tests\Feature\Notifications;

use App\Models\Local;
use App\Models\Reserva;
use App\Models\User;
use App\Notifications\ReservaAprovada;
use App\Notifications\ReservaCancelada;
use App\Notifications\ReservaCriada;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReservaNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['role' => 'user', 'email_verified_at' => now(), 'full_name' => 'Usuario']);
    }

    private function payload(Local $local, array $overrides = []): array
    {
        return array_merge([
            'titulo' => 'Aula de Cálculo I',
            'motivo' => 'Aula da disciplina de Cálculo Diferencial e Integral I para o primeiro semestre',
            'campi_id' => $local->campi_id,
            'grupo_id' => $local->grupo_id,
            'local_id' => $local->id,
            'tipo_local' => $local->tipo,
            'data_inicial' => '2026-08-10',
            'data_final' => '2026-08-10',
            'horario_inicial' => '08:00',
            'horario_final' => '10:00',
            'responsavel_nome' => 'Prof. Silva',
        ], $overrides);
    }

    public function test_criacao_de_reserva_notifica_dono_e_gerentes(): void
    {
        Notification::fake();

        $local = Local::factory()->create(['requer_aprovacao' => false]);
        $g1 = $this->user();
        $g2 = $this->user();
        $local->gerentes()->attach([$g1->id, $g2->id]);

        $dono = $this->user();
        Sanctum::actingAs($dono);

        $this->postJson('/api/reservas', $this->payload($local))->assertStatus(201);

        Notification::assertSentTo($dono, ReservaCriada::class);
        Notification::assertSentTo($g1, ReservaCriada::class);
        Notification::assertSentTo($g2, ReservaCriada::class);
    }

    public function test_aprovar_notifica_apenas_dono(): void
    {
        Notification::fake();

        $local = Local::factory()->create(['requer_aprovacao' => true]);
        $gerente = $this->user();
        $local->gerentes()->attach($gerente->id);

        $dono = $this->user();
        $reserva = Reserva::factory()->create([
            'user_id' => $dono->id,
            'local_id' => $local->id,
            'campi_id' => $local->campi_id,
            'grupo_id' => $local->grupo_id,
            'status' => 'pendente',
        ]);

        Sanctum::actingAs($gerente);
        $this->patchJson("/api/reservas/{$reserva->id}/aprovar")->assertOk();

        Notification::assertSentTo($dono, ReservaAprovada::class);
        Notification::assertNotSentTo($gerente, ReservaAprovada::class);
    }

    public function test_cancelar_notifica_dono_e_gerentes_com_motivo(): void
    {
        Notification::fake();

        $local = Local::factory()->create();
        $gerente = $this->user();
        $local->gerentes()->attach($gerente->id);

        $dono = $this->user();
        $reserva = Reserva::factory()->create([
            'user_id' => $dono->id,
            'local_id' => $local->id,
            'campi_id' => $local->campi_id,
            'grupo_id' => $local->grupo_id,
            'status' => 'confirmada',
        ]);

        Sanctum::actingAs($dono);
        $motivo = 'Não vou mais precisar do espaço hoje';
        $this->patchJson("/api/reservas/{$reserva->id}/cancelar", [
            'motivo_cancelamento' => $motivo,
        ])->assertOk();

        Notification::assertSentTo($dono, ReservaCancelada::class, function ($n) use ($motivo) {
            return $n->reserva->motivo_cancelamento === $motivo;
        });
        Notification::assertSentTo($gerente, ReservaCancelada::class);
    }
}
