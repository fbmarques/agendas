<?php

namespace Tests\Feature\Locais;

use App\Models\Local;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GerentesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'email_verified_at' => now(), 'full_name' => 'Admin']);
    }

    private function user(): User
    {
        return User::factory()->create(['role' => 'user', 'email_verified_at' => now(), 'full_name' => 'Usuario']);
    }

    public function test_admin_can_attach_gerentes_on_create(): void
    {
        $admin = $this->admin();
        $gerente = $this->user();
        Sanctum::actingAs($admin);

        $local = Local::factory()->make();

        $res = $this->postJson('/api/locais', [
            'nome' => 'Auditório A',
            'campi_id' => $local->campi_id,
            'grupo_id' => $local->grupo_id,
            'tipo' => 'Sala de aula',
            'gerentes' => [$gerente->id],
            'requer_aprovacao' => true,
        ]);

        $res->assertStatus(201);
        $novoLocal = Local::latest('id')->first();
        $this->assertTrue($novoLocal->requer_aprovacao);
        $this->assertTrue($novoLocal->temGerente($gerente));
    }

    public function test_admin_can_sync_gerentes_via_dedicated_endpoint(): void
    {
        $admin = $this->admin();
        $g1 = $this->user();
        $g2 = $this->user();
        $local = Local::factory()->create();

        Sanctum::actingAs($admin);

        $this->putJson("/api/locais/{$local->id}/gerentes", [
            'user_ids' => [$g1->id, $g2->id],
        ])->assertOk()->assertJsonCount(2, 'data');

        $this->assertTrue($local->fresh()->temGerente($g1));
        $this->assertTrue($local->fresh()->temGerente($g2));
    }

    public function test_common_user_cannot_change_gerentes(): void
    {
        $user = $this->user();
        $local = Local::factory()->create();

        Sanctum::actingAs($user);

        $this->putJson("/api/locais/{$local->id}/gerentes", [
            'user_ids' => [$user->id],
        ])->assertStatus(403);
    }

    public function test_gerentes_are_listed_publicly(): void
    {
        $g = $this->user();
        $local = Local::factory()->create();
        $local->gerentes()->attach($g->id);

        $this->getJson("/api/locais/{$local->id}/gerentes")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
