<?php

namespace Tests\Feature\Recursos;

use App\Models\Local;
use App\Models\Recurso;
use App\Models\Reserva;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DisponiveisTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['role' => 'user', 'email_verified_at' => now(), 'full_name' => 'User']);
    }

    private function recurso(int $unidadesAtivas, array $dias = [1, 2, 3, 4, 5], string $hi = '08:00', string $hf = '18:00'): Recurso
    {
        $r = Recurso::create([
            'nome' => 'Som', 'responsavel_nome' => 'Ana', 'responsavel_email' => 'ana@ex.test',
        ]);
        for ($n = 1; $n <= $unidadesAtivas; $n++) {
            $r->unidades()->create(['patrimonio' => "SOM-{$n}", 'status' => 'ativo']);
        }
        $r->disponibilidades()->create(['dias_semana' => $dias, 'horario_inicial' => $hi, 'horario_final' => $hf]);
        return $r;
    }

    public function test_recurso_dentro_da_janela_aparece_com_saldo_total(): void
    {
        $r = $this->recurso(3);
        Sanctum::actingAs($this->user());

        $res = $this->postJson('/api/recursos/disponiveis', [
            'ocorrencias' => [
                ['data_inicial' => '2026-08-10', 'data_final' => '2026-08-10', 'horario_inicial' => '09:00', 'horario_final' => '11:00'],
            ],
        ]);

        $res->assertOk()->assertJsonCount(1);
        $this->assertSame(3, $res->json('0.saldo_minimo'));
    }

    public function test_recurso_fora_da_janela_nao_aparece(): void
    {
        $this->recurso(3, [1, 2, 3, 4, 5], '08:00', '12:00');
        Sanctum::actingAs($this->user());

        $res = $this->postJson('/api/recursos/disponiveis', [
            'ocorrencias' => [
                ['data_inicial' => '2026-08-10', 'data_final' => '2026-08-10', 'horario_inicial' => '13:00', 'horario_final' => '14:00'],
            ],
        ]);

        $res->assertOk()->assertJsonCount(0);
    }

    public function test_recurso_saldo_deriva_de_reservas_ativas(): void
    {
        $local = Local::factory()->create();
        $r = $this->recurso(2);

        // Uma reserva já consome 1 unidade no mesmo slot
        $reserva = Reserva::factory()->create([
            'local_id' => $local->id, 'campi_id' => $local->campi_id, 'grupo_id' => $local->grupo_id,
            'data_inicial' => '2026-08-10', 'data_final' => '2026-08-10',
            'horario_inicial' => '09:00', 'horario_final' => '11:00',
            'status' => 'confirmada',
        ]);
        $reserva->recursos()->attach($r->id, ['quantidade' => 1]);

        Sanctum::actingAs($this->user());
        $res = $this->postJson('/api/recursos/disponiveis', [
            'ocorrencias' => [
                ['data_inicial' => '2026-08-10', 'data_final' => '2026-08-10', 'horario_inicial' => '09:00', 'horario_final' => '11:00'],
            ],
        ]);

        $res->assertOk();
        $this->assertSame(1, $res->json('0.saldo_minimo'));
    }

    public function test_recorrente_saldo_e_minimo_entre_ocorrencias(): void
    {
        $local = Local::factory()->create();
        $r = $this->recurso(3);

        // Duas reservas em datas distintas: uma consome 2, outra consome 0
        $reserva = Reserva::factory()->create([
            'local_id' => $local->id, 'campi_id' => $local->campi_id, 'grupo_id' => $local->grupo_id,
            'data_inicial' => '2026-08-10', 'data_final' => '2026-08-10',
            'horario_inicial' => '09:00', 'horario_final' => '11:00',
            'status' => 'confirmada',
        ]);
        $reserva->recursos()->attach($r->id, ['quantidade' => 2]);

        Sanctum::actingAs($this->user());
        $res = $this->postJson('/api/recursos/disponiveis', [
            'ocorrencias' => [
                ['data_inicial' => '2026-08-10', 'data_final' => '2026-08-10', 'horario_inicial' => '09:00', 'horario_final' => '11:00'],
                ['data_inicial' => '2026-08-17', 'data_final' => '2026-08-17', 'horario_inicial' => '09:00', 'horario_final' => '11:00'],
            ],
        ]);

        // Saldo em 10/08: 3-2 = 1; em 17/08: 3-0 = 3. Mínimo = 1.
        $res->assertOk();
        $this->assertSame(1, $res->json('0.saldo_minimo'));
    }
}
