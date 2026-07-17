<?php

namespace Tests\Feature\Recursos;

use App\Models\Local;
use App\Models\Recurso;
use App\Models\Reserva;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DisponibilidadeTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['role' => 'user', 'email_verified_at' => now(), 'full_name' => 'User']);
    }

    private function recursoComJanela(int $quantidade = 2, array $dias = [1, 2, 3, 4, 5], string $hi = '08:00', string $hf = '18:00'): Recurso
    {
        $r = Recurso::create([
            'nome' => 'Som', 'responsavel_nome' => 'Ana', 'responsavel_email' => 'ana@ex.test',
        ]);
        for ($n = 1; $n <= $quantidade; $n++) {
            $r->unidades()->create(['patrimonio' => "SOM-{$n}", 'status' => 'ativo']);
        }
        $r->disponibilidades()->create(['dias_semana' => $dias, 'horario_inicial' => $hi, 'horario_final' => $hf]);
        return $r;
    }

    private function payload(Local $local, array $overrides = []): array
    {
        return array_merge([
            'titulo' => 'Aula',
            'motivo' => 'Aula da disciplina de teste para verificar o comportamento dos recursos',
            'campi_id' => $local->campi_id,
            'grupo_id' => $local->grupo_id,
            'local_id' => $local->id,
            'tipo_local' => $local->tipo,
            'data_inicial' => '2026-08-10', // segunda
            'data_final' => '2026-08-10',
            'horario_inicial' => '09:00',
            'horario_final' => '11:00',
            'responsavel_nome' => 'Prof. Silva',
        ], $overrides);
    }

    public function test_reserva_com_recurso_em_janela_disponivel_grava_pivot(): void
    {
        $local = Local::factory()->create();
        $recurso = $this->recursoComJanela(2);

        Sanctum::actingAs($this->user());
        $this->postJson('/api/reservas', $this->payload($local, [
            'recursos' => [['id' => $recurso->id, 'quantidade' => 1]],
        ]))->assertStatus(201);

        $this->assertDatabaseCount('reserva_recurso', 1);
    }

    public function test_recurso_fora_da_janela_falha(): void
    {
        $local = Local::factory()->create();
        // Só segunda a sexta 08-12
        $recurso = $this->recursoComJanela(1, [1, 2, 3, 4, 5], '08:00', '12:00');

        Sanctum::actingAs($this->user());
        $this->postJson('/api/reservas', $this->payload($local, [
            'horario_inicial' => '13:00', 'horario_final' => '14:00',
            'recursos' => [['id' => $recurso->id, 'quantidade' => 1]],
        ]))->assertStatus(422);
    }

    public function test_quantidade_esgotada_falha(): void
    {
        $local = Local::factory()->create();
        $l2 = Local::factory()->create();
        $recurso = $this->recursoComJanela(1);

        // Primeira reserva consome a única unidade
        $r1 = Reserva::factory()->create([
            'local_id' => $local->id, 'campi_id' => $local->campi_id, 'grupo_id' => $local->grupo_id,
            'data_inicial' => '2026-08-10', 'data_final' => '2026-08-10',
            'horario_inicial' => '09:00', 'horario_final' => '11:00',
            'status' => 'confirmada',
        ]);
        $r1->recursos()->attach($recurso->id, ['quantidade' => 1]);

        Sanctum::actingAs($this->user());
        $this->postJson('/api/reservas', $this->payload($l2, [
            'local_id' => $l2->id, 'campi_id' => $l2->campi_id, 'grupo_id' => $l2->grupo_id,
            'recursos' => [['id' => $recurso->id, 'quantidade' => 1]],
        ]))->assertStatus(422);
    }

    public function test_cancelada_libera_a_quantidade(): void
    {
        $local = Local::factory()->create();
        $l2 = Local::factory()->create();
        $recurso = $this->recursoComJanela(1);

        $r1 = Reserva::factory()->create([
            'local_id' => $local->id, 'campi_id' => $local->campi_id, 'grupo_id' => $local->grupo_id,
            'data_inicial' => '2026-08-10', 'data_final' => '2026-08-10',
            'horario_inicial' => '09:00', 'horario_final' => '11:00',
            'status' => 'cancelada',
        ]);
        $r1->recursos()->attach($recurso->id, ['quantidade' => 1]);

        Sanctum::actingAs($this->user());
        $this->postJson('/api/reservas', $this->payload($l2, [
            'local_id' => $l2->id, 'campi_id' => $l2->campi_id, 'grupo_id' => $l2->grupo_id,
            'recursos' => [['id' => $recurso->id, 'quantidade' => 1]],
        ]))->assertStatus(201);
    }
}
