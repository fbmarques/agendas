<?php

namespace Tests\Feature\Locais;

use App\Models\Local;
use App\Models\LocalIndisponibilidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IndisponibilidadeTest extends TestCase
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

    private function payloadReserva(Local $local, array $overrides = []): array
    {
        return array_merge([
            'titulo' => 'Aula',
            'motivo' => 'Aula da disciplina de teste para exemplificar o funcionamento da regra',
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

    public function test_admin_cria_indisponibilidade_de_data_especifica(): void
    {
        $admin = $this->admin();
        $local = Local::factory()->create();
        Sanctum::actingAs($admin);

        $this->postJson("/api/locais/{$local->id}/indisponibilidades", [
            'tipo' => 'data_especifica',
            'data_inicial' => '2026-08-10',
            'motivo' => 'Feriado municipal',
        ])->assertStatus(201);

        $this->assertDatabaseCount('local_indisponibilidades', 1);
    }

    public function test_feriado_bloqueia_reserva_no_mesmo_dia(): void
    {
        $local = Local::factory()->create();
        LocalIndisponibilidade::create([
            'local_id' => $local->id,
            'tipo' => 'data_especifica',
            'data_inicial' => '2026-08-10',
            'motivo' => 'Feriado municipal',
        ]);

        Sanctum::actingAs($this->user());

        $this->postJson('/api/reservas', $this->payloadReserva($local))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['local_id']);
    }

    public function test_recorrente_domingo_bloqueia_reserva_de_domingo(): void
    {
        $local = Local::factory()->create();
        LocalIndisponibilidade::create([
            'local_id' => $local->id,
            'tipo' => 'recorrente_semanal',
            'dias_semana' => [0], // domingo
            'motivo' => 'Fechado aos domingos',
        ]);

        Sanctum::actingAs($this->user());

        // 2026-08-09 é domingo
        $this->postJson('/api/reservas', $this->payloadReserva($local, [
            'data_inicial' => '2026-08-09', 'data_final' => '2026-08-09',
        ]))->assertStatus(422)->assertJsonValidationErrors(['local_id']);

        // 2026-08-10 é segunda-feira — deve passar
        $this->postJson('/api/reservas', $this->payloadReserva($local, [
            'data_inicial' => '2026-08-10', 'data_final' => '2026-08-10',
        ]))->assertStatus(201);
    }

    public function test_faixa_horaria_bloqueia_apenas_janela_sobreposta(): void
    {
        $local = Local::factory()->create();
        LocalIndisponibilidade::create([
            'local_id' => $local->id,
            'tipo' => 'recorrente_semanal',
            'dias_semana' => [0, 1, 2, 3, 4, 5, 6],
            'horario_inicial' => '22:00',
            'horario_final' => '23:59',
            'motivo' => 'Sem uso noturno',
        ]);

        Sanctum::actingAs($this->user());

        // 23:00-23:30 deve falhar
        $this->postJson('/api/reservas', $this->payloadReserva($local, [
            'horario_inicial' => '23:00', 'horario_final' => '23:30',
        ]))->assertStatus(422)->assertJsonValidationErrors(['local_id']);

        // 14:00-16:00 passa
        $this->postJson('/api/reservas', $this->payloadReserva($local, [
            'horario_inicial' => '14:00', 'horario_final' => '16:00',
        ]))->assertStatus(201);
    }

    public function test_listagem_de_indisponibilidades_publica(): void
    {
        $local = Local::factory()->create();
        LocalIndisponibilidade::create([
            'local_id' => $local->id,
            'tipo' => 'data_especifica',
            'data_inicial' => '2026-08-10',
            'motivo' => 'Feriado',
        ]);

        $this->getJson("/api/locais/{$local->id}/indisponibilidades")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
