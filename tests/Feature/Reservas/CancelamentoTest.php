<?php

namespace Tests\Feature\Reservas;

use App\Models\Local;
use App\Models\Reserva;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CancelamentoTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'email_verified_at' => now(), 'full_name' => 'Admin']);
    }

    private function user(): User
    {
        return User::factory()->create(['role' => 'user', 'email_verified_at' => now(), 'full_name' => 'Usuario']);
    }

    public function test_cancelamento_requer_motivo(): void
    {
        $local = Local::factory()->create();
        $owner = $this->user();
        $reserva = Reserva::factory()->create([
            'user_id' => $owner->id,
            'local_id' => $local->id,
            'campi_id' => $local->campi_id,
            'grupo_id' => $local->grupo_id,
            'status' => 'confirmada',
        ]);

        Sanctum::actingAs($owner);
        $this->patchJson("/api/reservas/{$reserva->id}/cancelar", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['motivo_cancelamento']);
    }

    public function test_cancelamento_com_motivo_curto_falha(): void
    {
        $local = Local::factory()->create();
        $owner = $this->user();
        $reserva = Reserva::factory()->create([
            'user_id' => $owner->id,
            'local_id' => $local->id,
            'campi_id' => $local->campi_id,
            'grupo_id' => $local->grupo_id,
            'status' => 'confirmada',
        ]);

        Sanctum::actingAs($owner);
        $this->patchJson("/api/reservas/{$reserva->id}/cancelar", [
            'motivo_cancelamento' => 'motivo curto',
        ])->assertStatus(422)->assertJsonValidationErrors(['motivo_cancelamento']);
    }

    public function test_dono_pode_cancelar_propria_reserva(): void
    {
        $local = Local::factory()->create();
        $owner = $this->user();
        $reserva = Reserva::factory()->create([
            'user_id' => $owner->id,
            'local_id' => $local->id,
            'campi_id' => $local->campi_id,
            'grupo_id' => $local->grupo_id,
            'status' => 'confirmada',
        ]);

        Sanctum::actingAs($owner);
        $this->patchJson("/api/reservas/{$reserva->id}/cancelar", [
            'motivo_cancelamento' => 'Não preciso mais deste espaço porque o evento foi remarcado',
        ])->assertOk()->assertJsonPath('data.status', 'cancelada');

        $fresh = $reserva->fresh();
        $this->assertSame($owner->id, $fresh->cancelada_por_id);
        $this->assertNotNull($fresh->cancelada_em);
    }

    public function test_outro_usuario_sem_ser_gerente_recebe_403(): void
    {
        $local = Local::factory()->create();
        $owner = $this->user();
        $other = $this->user();
        $reserva = Reserva::factory()->create([
            'user_id' => $owner->id,
            'local_id' => $local->id,
            'campi_id' => $local->campi_id,
            'grupo_id' => $local->grupo_id,
            'status' => 'confirmada',
        ]);

        Sanctum::actingAs($other);
        $this->patchJson("/api/reservas/{$reserva->id}/cancelar", [
            'motivo_cancelamento' => 'Preciso cancelar mesmo sem ser dono nem gerente',
        ])->assertStatus(403);
    }

    public function test_gerente_pode_cancelar(): void
    {
        $local = Local::factory()->create();
        $gerente = $this->user();
        $local->gerentes()->attach($gerente->id);

        $reserva = Reserva::factory()->create([
            'local_id' => $local->id,
            'campi_id' => $local->campi_id,
            'grupo_id' => $local->grupo_id,
            'status' => 'confirmada',
        ]);

        Sanctum::actingAs($gerente);
        $this->patchJson("/api/reservas/{$reserva->id}/cancelar", [
            'motivo_cancelamento' => 'Cancelado pelo gerente por manutenção no espaço',
        ])->assertOk()->assertJsonPath('data.status', 'cancelada');
    }

    public function test_reserva_cancelada_libera_o_slot(): void
    {
        $local = Local::factory()->create();
        $owner = $this->user();
        $reserva = Reserva::factory()->create([
            'user_id' => $owner->id,
            'local_id' => $local->id,
            'campi_id' => $local->campi_id,
            'grupo_id' => $local->grupo_id,
            'status' => 'confirmada',
        ]);

        Sanctum::actingAs($owner);
        $this->patchJson("/api/reservas/{$reserva->id}/cancelar", [
            'motivo_cancelamento' => 'Liberando espaço para outra atividade urgente do curso',
        ])->assertOk();

        $conflito = Reserva::conflitos(
            $reserva->local_id,
            '2026-08-01',
            '2026-08-01',
            '08:00',
            '10:00',
        )->exists();

        $this->assertFalse($conflito, 'Reserva cancelada não deve mais bloquear o slot');
    }
}
