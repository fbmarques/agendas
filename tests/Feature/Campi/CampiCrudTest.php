<?php

namespace Tests\Feature\Campi;

use App\Models\Campi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CampiCrudTest extends TestCase
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

    public function test_index_is_public(): void
    {
        Campi::factory()->count(2)->create();

        $this->getJson('/api/campi')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_show_is_public(): void
    {
        $c = Campi::factory()->create(['nome' => 'X']);

        $this->getJson("/api/campi/{$c->id}")
            ->assertOk()
            ->assertJsonPath('data.nome', 'X');
    }

    public function test_store_requires_authentication(): void
    {
        $this->postJson('/api/campi', ['nome' => 'A', 'sigla' => 'A'])
            ->assertStatus(401);
    }

    public function test_store_rejects_non_admin(): void
    {
        Sanctum::actingAs($this->user());

        $this->postJson('/api/campi', ['nome' => 'A', 'sigla' => 'A'])
            ->assertStatus(403);
    }

    public function test_admin_can_create(): void
    {
        Sanctum::actingAs($this->admin());

        $response = $this->postJson('/api/campi', [
            'nome' => 'Campus Central',
            'sigla' => 'CC',
            'cidade' => 'São Paulo',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('nome', 'Campus Central');
        $this->assertDatabaseHas('campi', ['sigla' => 'CC']);
    }

    public function test_store_validates_required_fields(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/campi', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nome', 'sigla']);
    }

    public function test_admin_can_update(): void
    {
        $c = Campi::factory()->create(['nome' => 'Antigo']);
        Sanctum::actingAs($this->admin());

        $this->putJson("/api/campi/{$c->id}", ['nome' => 'Novo'])
            ->assertOk()
            ->assertJsonPath('data.nome', 'Novo');
    }

    public function test_update_rejects_non_admin(): void
    {
        $c = Campi::factory()->create();
        Sanctum::actingAs($this->user());

        $this->putJson("/api/campi/{$c->id}", ['nome' => 'Y'])
            ->assertStatus(403);
    }

    public function test_admin_can_delete(): void
    {
        $c = Campi::factory()->create();
        Sanctum::actingAs($this->admin());

        $this->deleteJson("/api/campi/{$c->id}")
            ->assertStatus(204);
        $this->assertDatabaseMissing('campi', ['id' => $c->id]);
    }

    public function test_delete_rejects_non_admin(): void
    {
        $c = Campi::factory()->create();
        Sanctum::actingAs($this->user());

        $this->deleteJson("/api/campi/{$c->id}")
            ->assertStatus(403);
    }

    public function test_status_validation(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/campi', ['nome' => 'X', 'sigla' => 'X', 'status' => 'invalido'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }
}
