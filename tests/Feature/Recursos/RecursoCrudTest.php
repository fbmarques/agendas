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
}
