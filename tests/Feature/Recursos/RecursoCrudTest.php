<?php

namespace Tests\Feature\Recursos;

use App\Models\Recurso;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecursoCrudTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'email_verified_at' => now(), 'full_name' => 'Admin']);
    }

    private function user(): User
    {
        return User::factory()->create(['role' => 'user', 'email_verified_at' => now(), 'full_name' => 'User']);
    }

    public function test_precisa_estar_autenticado(): void
    {
        $this->getJson('/api/recursos')->assertStatus(401);
    }

    public function test_admin_cria_recurso_com_disponibilidades(): void
    {
        Sanctum::actingAs($this->admin());

        $res = $this->postJson('/api/recursos', [
            'nome' => 'Som',
            'responsavel_nome' => 'Ana',
            'responsavel_email' => 'ana@exemplo.test',
            'quantidade' => 3,
            'disponibilidades' => [
                ['dias_semana' => [1, 2, 3, 4, 5], 'horario_inicial' => '08:00', 'horario_final' => '12:00'],
                ['dias_semana' => [1, 2, 3, 4, 5], 'horario_inicial' => '14:00', 'horario_final' => '18:00'],
            ],
        ]);

        $res->assertStatus(201);
        $r = Recurso::first();
        $this->assertNotNull($r);
        $this->assertSame(2, $r->disponibilidades()->count());
    }

    public function test_usuario_comum_nao_cria(): void
    {
        Sanctum::actingAs($this->user());
        $this->postJson('/api/recursos', [
            'nome' => 'x', 'responsavel_nome' => 'x', 'responsavel_email' => 'x@x.com', 'quantidade' => 1,
        ])->assertStatus(403);
    }

    public function test_cria_recurso_com_unidades_e_quantidade_deriva(): void
    {
        Sanctum::actingAs($this->admin());

        $res = $this->postJson('/api/recursos', [
            'nome' => 'Projetor',
            'responsavel_nome' => 'Carla',
            'responsavel_email' => 'carla@ex.test',
            'unidades' => [
                ['patrimonio' => 'PROJ-001'],
                ['patrimonio' => 'PROJ-002', 'observacoes' => 'nova aquisição'],
            ],
        ]);

        $res->assertStatus(201);
        $r = Recurso::first();
        $this->assertSame(2, $r->unidades()->count());
        $this->assertSame(2, $r->quantidade); // accessor derivado
    }

    public function test_inativar_unidade_reduz_quantidade(): void
    {
        Sanctum::actingAs($this->admin());
        $create = $this->postJson('/api/recursos', [
            'nome' => 'Som', 'responsavel_nome' => 'A', 'responsavel_email' => 'a@ex.test',
            'unidades' => [
                ['patrimonio' => 'SOM-1'],
                ['patrimonio' => 'SOM-2'],
                ['patrimonio' => 'SOM-3'],
            ],
        ]);
        $rid = $create->json('id');
        $uid = $create->json('unidades.0.id');
        $this->assertSame(3, Recurso::find($rid)->quantidade);

        $this->patchJson("/api/recursos/{$rid}/unidades/{$uid}", ['status' => 'inativo'])
            ->assertOk();

        $this->assertSame(2, Recurso::find($rid)->quantidade);
    }

    public function test_patrimonio_repetido_no_mesmo_recurso_falha(): void
    {
        Sanctum::actingAs($this->admin());
        $create = $this->postJson('/api/recursos', [
            'nome' => 'Som', 'responsavel_nome' => 'A', 'responsavel_email' => 'a@ex.test',
            'unidades' => [['patrimonio' => 'SOM-1']],
        ]);
        $rid = $create->json('id');

        $this->postJson("/api/recursos/{$rid}/unidades", ['patrimonio' => 'SOM-1'])
            ->assertStatus(422);
    }

    public function test_backfill_migration_cria_unidades_para_recurso_pre_existente(): void
    {
        // Insere um recurso "legado" direto na tabela (só coluna quantidade)
        \DB::table('recursos')->insert([
            'nome' => 'Legado',
            'responsavel_nome' => 'X',
            'responsavel_email' => 'x@ex.test',
            'quantidade' => 4,
            'status' => 'ativo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $rid = \DB::table('recursos')->max('id');

        $migration = require database_path('migrations/2026_07_17_100001_backfill_recurso_unidades.php');
        $migration->up();

        $this->assertSame(4, Recurso::find($rid)->unidades()->count());
        $this->assertSame(4, Recurso::find($rid)->quantidade);
    }
}
