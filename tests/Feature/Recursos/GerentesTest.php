<?php

namespace Tests\Feature\Recursos;

use App\Models\Local;
use App\Models\Recurso;
use App\Models\Reserva;
use App\Models\User;
use App\Notifications\ReservaCriada;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GerentesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'email_verified_at' => now(), 'full_name' => 'Admin']);
    }

    private function user(string $email = 'gerente@ex.test'): User
    {
        return User::factory()->create(['role' => 'user', 'email' => $email, 'email_verified_at' => now(), 'full_name' => 'Gerente']);
    }

    public function test_admin_cria_recurso_com_gerentes(): void
    {
        $gerente1 = $this->user('g1@ex.test');
        $gerente2 = $this->user('g2@ex.test');

        Sanctum::actingAs($this->admin());
        $res = $this->postJson('/api/recursos', [
            'nome' => 'Projetor',
            'gerentes_ids' => [$gerente1->id, $gerente2->id],
            'unidades' => [['patrimonio' => 'PROJ-1']],
        ]);

        $res->assertStatus(201);
        $r = Recurso::first();
        $this->assertSame(2, $r->gerentes()->count());
    }

    public function test_gerente_pode_editar_recurso(): void
    {
        $gerente = $this->user();
        $r = Recurso::create(['nome' => 'X']);
        $r->gerentes()->attach($gerente->id);

        Sanctum::actingAs($gerente);
        $this->patchJson("/api/recursos/{$r->id}", ['nome' => 'X editado'])->assertOk();
        $this->assertSame('X editado', $r->fresh()->nome);
    }

    public function test_gerente_nao_pode_alterar_lista_de_gerentes(): void
    {
        $gerente = $this->user('g1@ex.test');
        $outro = $this->user('outro@ex.test');
        $r = Recurso::create(['nome' => 'X']);
        $r->gerentes()->attach($gerente->id);

        Sanctum::actingAs($gerente);
        // Tenta remover a si mesmo via update do recurso
        $this->patchJson("/api/recursos/{$r->id}", ['gerentes_ids' => [$outro->id]])->assertOk();
        // A pivot NÃO deve ter mudado — só admin edita gerentes
        $this->assertTrue($r->fresh()->gerentes()->where('users.id', $gerente->id)->exists());
        $this->assertFalse($r->fresh()->gerentes()->where('users.id', $outro->id)->exists());
    }

    public function test_gerente_pode_criar_unidade(): void
    {
        $gerente = $this->user();
        $r = Recurso::create(['nome' => 'X']);
        $r->gerentes()->attach($gerente->id);

        Sanctum::actingAs($gerente);
        $this->postJson("/api/recursos/{$r->id}/unidades", ['patrimonio' => 'X-01'])->assertStatus(201);
        $this->assertSame(1, $r->fresh()->unidades()->count());
    }

    public function test_usuario_sem_papel_nao_edita(): void
    {
        $r = Recurso::create(['nome' => 'X']);
        Sanctum::actingAs($this->user());
        $this->patchJson("/api/recursos/{$r->id}", ['nome' => 'Y'])->assertStatus(403);
    }

    public function test_definir_gerentes_endpoint_so_admin(): void
    {
        $gerente = $this->user('g1@ex.test');
        $outro = $this->user('outro@ex.test');
        $r = Recurso::create(['nome' => 'X']);
        $r->gerentes()->attach($gerente->id);

        Sanctum::actingAs($gerente);
        $this->putJson("/api/recursos/{$r->id}/gerentes", ['user_ids' => [$outro->id]])->assertStatus(403);

        Sanctum::actingAs($this->admin());
        $this->putJson("/api/recursos/{$r->id}/gerentes", ['user_ids' => [$outro->id]])->assertOk();
        $this->assertSame(1, $r->fresh()->gerentes()->count());
        $this->assertTrue($r->fresh()->gerentes()->where('users.id', $outro->id)->exists());
    }

    public function test_gerentes_recebem_notificacao_ao_criar_reserva(): void
    {
        Notification::fake();

        $gerente = $this->user('gerente@ex.test');
        $r = Recurso::create(['nome' => 'Som', 'responsavel_email' => 'legado@ex.test']);
        $r->unidades()->create(['patrimonio' => 'SOM-1', 'status' => 'ativo']);
        $r->disponibilidades()->create(['dias_semana' => [1,2,3,4,5], 'horario_inicial' => '08:00', 'horario_final' => '18:00']);
        $r->gerentes()->attach($gerente->id);

        $local = Local::factory()->create();
        $dono = User::factory()->create(['email_verified_at' => now(), 'full_name' => 'Dono']);

        Sanctum::actingAs($dono);
        $this->postJson('/api/reservas', [
            'titulo' => 'Aula',
            'motivo' => 'Aula da disciplina de programação para a turma do primeiro semestre',
            'campi_id' => $local->campi_id, 'grupo_id' => $local->grupo_id, 'local_id' => $local->id,
            'tipo_local' => $local->tipo,
            'data_inicial' => '2026-08-10', 'data_final' => '2026-08-10',
            'horario_inicial' => '09:00', 'horario_final' => '11:00',
            'responsavel_nome' => 'Dono',
            'recursos' => [['id' => $r->id, 'quantidade' => 1]],
        ])->assertStatus(201);

        Notification::assertSentTo($gerente, ReservaCriada::class);
        Notification::assertSentTo(new \Illuminate\Notifications\AnonymousNotifiable(), ReservaCriada::class);
    }
}
