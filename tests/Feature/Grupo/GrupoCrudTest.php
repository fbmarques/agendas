<?php

namespace Tests\Feature\Grupo;

use App\Models\Campi;
use App\Models\Grupo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GrupoCrudTest extends TestCase
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

    public function test_index_is_public_and_filters_by_campi(): void
    {
        $c1 = Campi::factory()->create();
        $c2 = Campi::factory()->create();
        Grupo::factory()->count(2)->create(['campi_id' => $c1->id]);
        Grupo::factory()->create(['campi_id' => $c2->id]);

        $this->getJson('/api/grupos')->assertOk()->assertJsonCount(3, 'data');
        $this->getJson("/api/grupos?campi_id={$c1->id}")->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_store_requires_authentication(): void
    {
        $c = Campi::factory()->create();

        $this->postJson('/api/grupos', ['nome' => 'X', 'campi_id' => $c->id])
            ->assertStatus(401);
    }

    public function test_store_rejects_non_admin(): void
    {
        $c = Campi::factory()->create();
        Sanctum::actingAs($this->user());

        $this->postJson('/api/grupos', ['nome' => 'X', 'campi_id' => $c->id])
            ->assertStatus(403);
    }

    public function test_admin_can_create(): void
    {
        $c = Campi::factory()->create();
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/grupos', ['nome' => 'Engenharias', 'campi_id' => $c->id])
            ->assertStatus(201)
            ->assertJsonPath('nome', 'Engenharias');
    }

    public function test_store_validates_campi_exists(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/grupos', ['nome' => 'X', 'campi_id' => 99999])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['campi_id']);
    }

    public function test_store_validates_required_fields(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/grupos', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nome', 'campi_id']);
    }

    public function test_admin_can_update(): void
    {
        $g = Grupo::factory()->create(['nome' => 'A']);
        Sanctum::actingAs($this->admin());

        $this->putJson("/api/grupos/{$g->id}", ['nome' => 'B'])
            ->assertOk()
            ->assertJsonPath('data.nome', 'B');
    }

    public function test_admin_can_delete(): void
    {
        $g = Grupo::factory()->create();
        Sanctum::actingAs($this->admin());

        $this->deleteJson("/api/grupos/{$g->id}")->assertStatus(204);
        $this->assertDatabaseMissing('grupos', ['id' => $g->id]);
    }

    public function test_deleting_campi_cascades_grupos(): void
    {
        $c = Campi::factory()->create();
        $g = Grupo::factory()->create(['campi_id' => $c->id]);

        $c->delete();

        $this->assertDatabaseMissing('grupos', ['id' => $g->id]);
    }
}
