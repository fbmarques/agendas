<?php

namespace Tests\Feature\Recursos;

use App\Models\Local;
use App\Models\Recurso;
use App\Models\Reserva;
use App\Models\User;
use App\Notifications\ReservaRecursoRemovido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RemocaoUnidadeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'email_verified_at' => now(), 'full_name' => 'Admin']);
    }

    private function recursoCom($qtdUnidades): Recurso
    {
        $r = Recurso::create([
            'nome' => 'Projetor', 'responsavel_nome' => 'Carla', 'responsavel_email' => 'carla@ex.test',
        ]);
        for ($n = 1; $n <= $qtdUnidades; $n++) {
            $r->unidades()->create(['patrimonio' => "PROJ-{$n}", 'status' => 'ativo']);
        }
        return $r;
    }

    public function test_preview_sem_reservas_futuras_retorna_vazio(): void
    {
        $r = $this->recursoCom(3);
        Sanctum::actingAs($this->admin());
        $u = $r->unidades()->first();

        $res = $this->postJson("/api/recursos/{$r->id}/unidades/{$u->id}/preview-remocao");
        $res->assertOk();
        $this->assertSame(0, count($res->json('afetadas')));
        $this->assertSame(3, $res->json('resumo.quantidade_antes'));
        $this->assertSame(2, $res->json('resumo.quantidade_depois'));
    }

    public function test_preview_marca_reserva_quando_slot_ficaria_sobrealocado(): void
    {
        $r = $this->recursoCom(2);
        $local = Local::factory()->create();
        $user = User::factory()->create(['role' => 'user', 'email_verified_at' => now(), 'full_name' => 'User A']);

        // Duas reservas concorrentes usando 1 cada, saldo esgotado. Ao remover 1 unidade
        // (2 → 1), precisa desvincular 1 reserva.
        foreach ([1, 2] as $i) {
            $reserva = Reserva::factory()->create([
                'user_id' => $user->id,
                'local_id' => $local->id, 'campi_id' => $local->campi_id, 'grupo_id' => $local->grupo_id,
                'data_inicial' => '2026-09-10', 'data_final' => '2026-09-10',
                'horario_inicial' => '09:00', 'horario_final' => '11:00',
                'status' => 'confirmada',
            ]);
            $reserva->recursos()->attach($r->id, ['quantidade' => 1]);
        }

        Sanctum::actingAs($this->admin());
        $u = $r->unidades()->first();
        $res = $this->postJson("/api/recursos/{$r->id}/unidades/{$u->id}/preview-remocao");
        $res->assertOk();
        $this->assertSame(1, count($res->json('afetadas')));
    }

    public function test_confirmar_remove_pivot_marca_unidade_inativa_e_notifica(): void
    {
        Notification::fake();

        $r = $this->recursoCom(2);
        $local = Local::factory()->create();
        $user = User::factory()->create(['role' => 'user', 'email_verified_at' => now(), 'full_name' => 'User B']);

        $reserva = Reserva::factory()->create([
            'user_id' => $user->id,
            'local_id' => $local->id, 'campi_id' => $local->campi_id, 'grupo_id' => $local->grupo_id,
            'data_inicial' => '2026-09-10', 'data_final' => '2026-09-10',
            'horario_inicial' => '09:00', 'horario_final' => '11:00',
            'status' => 'confirmada',
        ]);
        $reserva->recursos()->attach($r->id, ['quantidade' => 1]);

        $u = $r->unidades()->first();
        Sanctum::actingAs($this->admin());
        $res = $this->postJson("/api/recursos/{$r->id}/unidades/{$u->id}/confirmar-remocao", [
            'reserva_ids_desvincular' => [$reserva->id],
            'motivo' => 'Projetor queimou',
        ]);

        $res->assertOk();
        $this->assertSame(1, $res->json('desvinculadas'));
        $this->assertSame('inativo', $u->fresh()->status);
        $this->assertSame(0, $reserva->recursos()->count());
        // A reserva do LOCAL continua ativa
        $this->assertSame('confirmada', $reserva->fresh()->status);

        Notification::assertSentTo($user, ReservaRecursoRemovido::class);
    }

    public function test_preview_deterministico_entre_chamadas(): void
    {
        $r = $this->recursoCom(2);
        $local = Local::factory()->create();
        $user = User::factory()->create(['role' => 'user', 'email_verified_at' => now(), 'full_name' => 'Determ']);

        foreach ([1, 2, 3] as $i) {
            $reserva = Reserva::factory()->create([
                'user_id' => $user->id,
                'local_id' => $local->id, 'campi_id' => $local->campi_id, 'grupo_id' => $local->grupo_id,
                'data_inicial' => '2026-09-10', 'data_final' => '2026-09-10',
                'horario_inicial' => '09:00', 'horario_final' => '11:00',
                'status' => 'confirmada',
            ]);
            $reserva->recursos()->attach($r->id, ['quantidade' => 1]);
        }

        $u = $r->unidades()->first();
        Sanctum::actingAs($this->admin());
        $a = $this->postJson("/api/recursos/{$r->id}/unidades/{$u->id}/preview-remocao")->json('afetadas');
        $b = $this->postJson("/api/recursos/{$r->id}/unidades/{$u->id}/preview-remocao")->json('afetadas');

        $this->assertSame($a, $b, 'Preview deve ser determinístico para permitir revisão do admin.');
    }
}
