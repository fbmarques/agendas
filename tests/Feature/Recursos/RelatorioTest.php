<?php

namespace Tests\Feature\Recursos;

use App\Models\Local;
use App\Models\Recurso;
use App\Models\Reserva;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RelatorioTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'email_verified_at' => now(), 'full_name' => 'Admin']);
    }

    private function recursoComReserva(): array
    {
        $r = Recurso::create([
            'nome' => 'Projetor', 'responsavel_nome' => 'Carla', 'responsavel_email' => 'carla@ex.test',
        ]);
        $r->unidades()->create(['patrimonio' => 'PROJ-1', 'status' => 'ativo']);
        $r->unidades()->create(['patrimonio' => 'PROJ-2', 'status' => 'ativo']);
        $r->disponibilidades()->create(['dias_semana' => [1, 2, 3, 4, 5], 'horario_inicial' => '08:00', 'horario_final' => '18:00']);

        $local = Local::factory()->create();
        $user = User::factory()->create(['role' => 'user', 'email_verified_at' => now(), 'full_name' => 'Cliente']);

        $reserva = Reserva::factory()->create([
            'user_id' => $user->id,
            'local_id' => $local->id, 'campi_id' => $local->campi_id, 'grupo_id' => $local->grupo_id,
            'titulo' => 'Aula demo',
            'data_inicial' => '2026-08-10', 'data_final' => '2026-08-10',
            'horario_inicial' => '09:00', 'horario_final' => '11:00',
            'status' => 'confirmada',
        ]);
        $reserva->recursos()->attach($r->id, ['quantidade' => 1]);

        return [$r, $reserva, $user];
    }

    public function test_reservas_json(): void
    {
        [$r, $reserva, $user] = $this->recursoComReserva();
        Sanctum::actingAs($this->admin());

        $res = $this->getJson("/api/recursos/{$r->id}/relatorio/reservas?data_inicial=2026-08-01&data_final=2026-08-31");
        $res->assertOk();
        $this->assertCount(1, $res->json('linhas'));
        $this->assertSame('Aula demo', $res->json('linhas.0.titulo'));
        $this->assertSame($user->full_name, $res->json('linhas.0.usuario'));
    }

    public function test_reservas_csv(): void
    {
        [$r] = $this->recursoComReserva();
        Sanctum::actingAs($this->admin());

        $res = $this->get("/api/recursos/{$r->id}/relatorio/reservas?data_inicial=2026-08-01&data_final=2026-08-31&format=csv");
        $res->assertOk();
        $res->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $csv = $res->streamedContent();
        $this->assertStringContainsString('Reserva', $csv);
        $this->assertStringContainsString('Aula demo', $csv);
    }

    public function test_ocupacao_calcula_percentual(): void
    {
        [$r] = $this->recursoComReserva();
        Sanctum::actingAs($this->admin());

        // Janela: seg-sex 08-18 = 10h/dia, 2 unidades. Agosto/2026 tem 21 dias úteis.
        // Horas disponíveis mês = 21 * 10 * 2 = 420. Reservada = 2h * 1qtd = 2h.
        $res = $this->getJson("/api/recursos/{$r->id}/relatorio/ocupacao?data_inicial=2026-08-01&data_final=2026-08-31");
        $res->assertOk();
        $linha = collect($res->json('linhas'))->firstWhere('mes', '2026-08');
        $this->assertNotNull($linha);
        $this->assertGreaterThan(0, $linha['horas_disponiveis']);
        $this->assertEquals(2.0, $linha['horas_reservadas']);
    }

    public function test_unidades_distribui_horas(): void
    {
        [$r] = $this->recursoComReserva();
        Sanctum::actingAs($this->admin());

        // 2 unidades ativas, 2h reservadas -> 1h por unidade
        $res = $this->getJson("/api/recursos/{$r->id}/relatorio/unidades?data_inicial=2026-08-01&data_final=2026-08-31");
        $res->assertOk();
        $this->assertCount(2, $res->json('linhas'));
        $this->assertEquals(1.0, $res->json('linhas.0.horas_alocadas_estimadas'));
    }
}
