<?php

namespace Tests\Feature\Reservas;

use App\Models\Local;
use App\Models\Reserva;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConflitoTest extends TestCase
{
    use RefreshDatabase;

    private function auth(): User
    {
        $u = User::factory()->create(['role' => 'user', 'email_verified_at' => now(), 'full_name' => 'X']);
        Sanctum::actingAs($u);
        return $u;
    }

    private function payload(Local $local, array $overrides = []): array
    {
        return array_merge([
            'titulo' => 'Sessão',
            'motivo' => 'Uso do espaço para atividade prevista durante todo o semestre letivo pelo curso',
            'campi_id' => $local->campi_id,
            'grupo_id' => $local->grupo_id,
            'local_id' => $local->id,
            'tipo_local' => $local->tipo,
            'data_inicial' => '2026-09-01',
            'data_final' => '2026-09-01',
            'horario_inicial' => '08:00',
            'horario_final' => '10:00',
            'responsavel_nome' => 'Fulano',
        ], $overrides);
    }

    public function test_reserva_no_mesmo_local_e_horario_sobrepondo_bloqueia(): void
    {
        $this->auth();
        $local = Local::factory()->create();

        $this->postJson('/api/reservas', $this->payload($local))->assertStatus(201);

        $this->postJson('/api/reservas', $this->payload($local, [
            'horario_inicial' => '09:00',
            'horario_final' => '11:00',
        ]))->assertStatus(422)->assertJsonValidationErrors(['local_id']);
    }

    public function test_reserva_cancelada_nao_bloqueia_nova(): void
    {
        $user = $this->auth();
        $local = Local::factory()->create();
        Reserva::factory()->create(array_merge($this->payload($local), [
            'user_id' => $user->id,
            'status' => 'cancelada',
        ]));

        $this->postJson('/api/reservas', $this->payload($local))->assertStatus(201);
    }

    public function test_reservas_em_locais_diferentes_nao_conflitam(): void
    {
        $this->auth();
        $l1 = Local::factory()->create();
        $l2 = Local::factory()->create();

        $this->postJson('/api/reservas', $this->payload($l1))->assertStatus(201);
        $this->postJson('/api/reservas', $this->payload($l2))->assertStatus(201);
    }

    public function test_horarios_adjacentes_nao_conflitam(): void
    {
        $this->auth();
        $local = Local::factory()->create();

        $this->postJson('/api/reservas', $this->payload($local, [
            'horario_inicial' => '08:00', 'horario_final' => '10:00',
        ]))->assertStatus(201);

        $this->postJson('/api/reservas', $this->payload($local, [
            'horario_inicial' => '10:00', 'horario_final' => '12:00',
        ]))->assertStatus(201);
    }

    public function test_update_para_horario_conflitante_bloqueia(): void
    {
        $user = $this->auth();
        $local = Local::factory()->create();

        $r1 = Reserva::factory()->create(array_merge($this->payload($local), ['user_id' => $user->id]));
        $r2 = Reserva::factory()->create(array_merge($this->payload($local, [
            'horario_inicial' => '10:00', 'horario_final' => '12:00',
        ]), ['user_id' => $user->id]));

        // Tentar mover r2 para colidir com r1
        $this->putJson("/api/reservas/{$r2->id}", [
            'horario_inicial' => '09:00',
            'horario_final' => '11:00',
        ])->assertStatus(422)->assertJsonValidationErrors(['local_id']);
    }
}
