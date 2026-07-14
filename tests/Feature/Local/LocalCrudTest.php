<?php

namespace Tests\Feature\Local;

use App\Models\Campi;
use App\Models\Grupo;
use App\Models\Local;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LocalCrudTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
    }

    private function user(): User
    {
        return User::factory()->create(['role' => 'user', 'email_verified_at' => now()]);
    }

    private function makeGrupo(): Grupo
    {
        $c = Campi::factory()->create();
        return Grupo::factory()->create(['campi_id' => $c->id]);
    }

    public function test_index_is_public_and_supports_filters(): void
    {
        $g1 = $this->makeGrupo();
        $g2 = $this->makeGrupo();
        Local::factory()->count(2)->create(['campi_id' => $g1->campi_id, 'grupo_id' => $g1->id]);
        Local::factory()->create(['campi_id' => $g2->campi_id, 'grupo_id' => $g2->id, 'tipo' => 'Laboratório']);

        $this->getJson('/api/locais')->assertOk()->assertJsonCount(3, 'data');
        $this->getJson("/api/locais?campi_id={$g1->campi_id}")->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/locais?tipo=Laboratório')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_admin_can_create(): void
    {
        $g = $this->makeGrupo();
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/locais', [
            'nome' => 'Sala 101',
            'campi_id' => $g->campi_id,
            'grupo_id' => $g->id,
            'tipo' => 'Sala de aula',
            'capacidade' => 40,
        ])
            ->assertStatus(201)
            ->assertJsonPath('nome', 'Sala 101');
    }

    public function test_store_rejects_non_admin(): void
    {
        $g = $this->makeGrupo();
        Sanctum::actingAs($this->user());

        $this->postJson('/api/locais', [
            'nome' => 'X', 'campi_id' => $g->campi_id, 'grupo_id' => $g->id, 'tipo' => 'Sala de aula',
        ])->assertStatus(403);
    }

    public function test_store_validates_tipo(): void
    {
        $g = $this->makeGrupo();
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/locais', [
            'nome' => 'X', 'campi_id' => $g->campi_id, 'grupo_id' => $g->id, 'tipo' => 'Inexistente',
        ])->assertStatus(422)->assertJsonValidationErrors(['tipo']);
    }

    public function test_store_rejects_grupo_from_other_campi(): void
    {
        $g = $this->makeGrupo();
        $outroCampi = Campi::factory()->create();
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/locais', [
            'nome' => 'X', 'campi_id' => $outroCampi->id, 'grupo_id' => $g->id, 'tipo' => 'Sala de aula',
        ])->assertStatus(422)->assertJsonValidationErrors(['grupo_id']);
    }

    public function test_store_validates_required_fields(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/locais', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nome', 'campi_id', 'grupo_id', 'tipo']);
    }

    public function test_admin_can_update(): void
    {
        $l = Local::factory()->create(['nome' => 'A']);
        Sanctum::actingAs($this->admin());

        $this->putJson("/api/locais/{$l->id}", ['nome' => 'B'])
            ->assertOk()
            ->assertJsonPath('data.nome', 'B');
    }

    public function test_admin_can_delete(): void
    {
        $l = Local::factory()->create();
        Sanctum::actingAs($this->admin());

        $this->deleteJson("/api/locais/{$l->id}")->assertStatus(204);
        $this->assertDatabaseMissing('locais', ['id' => $l->id]);
    }

    public function test_deleting_grupo_cascades_locais(): void
    {
        $l = Local::factory()->create();
        $l->grupo->delete();

        $this->assertDatabaseMissing('locais', ['id' => $l->id]);
    }
}
