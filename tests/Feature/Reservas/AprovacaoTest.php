<?php

namespace Tests\Feature\Reservas;

use App\Models\Local;
use App\Models\Reserva;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AprovacaoTest extends TestCase
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

    private function payload(Local $local, array $overrides = []): array
    {
        return array_merge([
            'titulo' => 'Aula de Cálculo I',
            'motivo' => 'Aula da disciplina de Cálculo Diferencial e Integral I para primeiro semestre',
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

    public function test_local_com_requer_aprovacao_cria_reserva_pendente(): void
    {
        $local = Local::factory()->create(['requer_aprovacao' => true]);
        Sanctum::actingAs($this->user());

        $res = $this->postJson('/api/reservas', $this->payload($local));
        $res->assertStatus(201)->assertJsonPath('status', 'pendente');
    }

    public function test_local_sem_requer_aprovacao_cria_reserva_confirmada(): void
    {
        $local = Local::factory()->create(['requer_aprovacao' => false]);
        Sanctum::actingAs($this->user());

        $res = $this->postJson('/api/reservas', $this->payload($local));
        $res->assertStatus(201)->assertJsonPath('status', 'confirmada');
    }

    public function test_gerente_aprova_reserva_pendente(): void
    {
        $local = Local::factory()->create(['requer_aprovacao' => true]);
        $gerente = $this->user();
        $local->gerentes()->attach($gerente->id);

        $reserva = Reserva::factory()->create([
            'local_id' => $local->id,
            'campi_id' => $local->campi_id,
            'grupo_id' => $local->grupo_id,
            'status' => 'pendente',
        ]);

        Sanctum::actingAs($gerente);
        $res = $this->patchJson("/api/reservas/{$reserva->id}/aprovar");

        $res->assertOk()->assertJsonPath('data.status', 'confirmada');
        $this->assertNotNull($reserva->fresh()->aprovada_em);
        $this->assertSame($gerente->id, $reserva->fresh()->aprovada_por_id);
    }

    public function test_admin_pode_aprovar(): void
    {
        $local = Local::factory()->create(['requer_aprovacao' => true]);
        $reserva = Reserva::factory()->create([
            'local_id' => $local->id,
            'campi_id' => $local->campi_id,
            'grupo_id' => $local->grupo_id,
            'status' => 'pendente',
        ]);

        Sanctum::actingAs($this->admin());
        $this->patchJson("/api/reservas/{$reserva->id}/aprovar")
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmada');
    }

    public function test_nao_gerente_recebe_403_ao_aprovar(): void
    {
        $local = Local::factory()->create(['requer_aprovacao' => true]);
        $reserva = Reserva::factory()->create([
            'local_id' => $local->id,
            'campi_id' => $local->campi_id,
            'grupo_id' => $local->grupo_id,
            'status' => 'pendente',
        ]);

        Sanctum::actingAs($this->user());
        $this->patchJson("/api/reservas/{$reserva->id}/aprovar")->assertStatus(403);
    }

    public function test_aprovar_reserva_ja_confirmada_falha(): void
    {
        $local = Local::factory()->create(['requer_aprovacao' => true]);
        $gerente = $this->user();
        $local->gerentes()->attach($gerente->id);

        $reserva = Reserva::factory()->create([
            'local_id' => $local->id,
            'campi_id' => $local->campi_id,
            'grupo_id' => $local->grupo_id,
            'status' => 'confirmada',
        ]);

        Sanctum::actingAs($gerente);
        $this->patchJson("/api/reservas/{$reserva->id}/aprovar")->assertStatus(403);
    }

    public function test_pendentes_endpoint_lista_apenas_das_locais_gerenciados(): void
    {
        $l1 = Local::factory()->create(['requer_aprovacao' => true]);
        $l2 = Local::factory()->create(['requer_aprovacao' => true]);

        Reserva::factory()->create(['local_id' => $l1->id, 'campi_id' => $l1->campi_id, 'grupo_id' => $l1->grupo_id, 'status' => 'pendente']);
        Reserva::factory()->create(['local_id' => $l2->id, 'campi_id' => $l2->campi_id, 'grupo_id' => $l2->grupo_id, 'status' => 'pendente']);
        Reserva::factory()->create(['local_id' => $l1->id, 'campi_id' => $l1->campi_id, 'grupo_id' => $l1->grupo_id, 'status' => 'confirmada']);

        $gerente = $this->user();
        $l1->gerentes()->attach($gerente->id);

        Sanctum::actingAs($gerente);
        $this->getJson('/api/reservas/pendentes')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_pendentes_endpoint_admin_ve_todas(): void
    {
        $l1 = Local::factory()->create(['requer_aprovacao' => true]);
        $l2 = Local::factory()->create(['requer_aprovacao' => true]);
        Reserva::factory()->create(['local_id' => $l1->id, 'campi_id' => $l1->campi_id, 'grupo_id' => $l1->grupo_id, 'status' => 'pendente']);
        Reserva::factory()->create(['local_id' => $l2->id, 'campi_id' => $l2->campi_id, 'grupo_id' => $l2->grupo_id, 'status' => 'pendente']);

        Sanctum::actingAs($this->admin());
        $this->getJson('/api/reservas/pendentes')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }
}
