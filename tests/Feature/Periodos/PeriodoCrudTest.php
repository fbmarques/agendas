<?php

namespace Tests\Feature\Periodos;

use App\Models\Periodo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PeriodoCrudTest extends TestCase
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

    public function test_listagem_publica(): void
    {
        Periodo::create(['nome' => '2026/1', 'data_inicio' => '2026-02-01', 'data_fim' => '2026-06-30']);

        $this->getJson('/api/periodos')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_cria_periodo(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/periodos', [
            'nome' => '2026/2',
            'data_inicio' => '2026-08-01',
            'data_fim' => '2026-12-15',
        ])->assertStatus(201)->assertJsonPath('nome', '2026/2');
    }

    public function test_usuario_nao_pode_criar(): void
    {
        Sanctum::actingAs($this->user());
        $this->postJson('/api/periodos', [
            'nome' => 'x', 'data_inicio' => '2026-01-01', 'data_fim' => '2026-02-01',
        ])->assertStatus(403);
    }

    public function test_data_fim_deve_ser_maior_ou_igual_a_inicio(): void
    {
        Sanctum::actingAs($this->admin());
        $this->postJson('/api/periodos', [
            'nome' => 'x', 'data_inicio' => '2026-05-01', 'data_fim' => '2026-04-01',
        ])->assertStatus(422)->assertJsonValidationErrors(['data_fim']);
    }

    public function test_admin_pode_editar_e_apagar(): void
    {
        $p = Periodo::create(['nome' => 'x', 'data_inicio' => '2026-01-01', 'data_fim' => '2026-02-01']);
        Sanctum::actingAs($this->admin());

        $this->putJson("/api/periodos/{$p->id}", ['nome' => 'renomeado'])
            ->assertOk()->assertJsonPath('data.nome', 'renomeado');

        $this->deleteJson("/api/periodos/{$p->id}")->assertStatus(204);
    }
}
