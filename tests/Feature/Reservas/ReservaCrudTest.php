<?php

namespace Tests\Feature\Reservas;

use App\Models\Local;
use App\Models\Reserva;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReservaCrudTest extends TestCase
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

    private function payload(Local $local, array $overrides = []): array
    {
        return array_merge([
            'titulo' => 'Aula de Cálculo I',
            'motivo' => 'Aula da disciplina de Cálculo Diferencial e Integral I para primeiro semestre',
            'campi_id' => $local->campi_id,
            'grupo_id' => $local->grupo_id,
            'local_id' => $local->id,
            'tipo_local' => $local->tipo,
            'data_inicial' => '2026-08-10',
            'data_final' => '2026-08-10',
            'horario_inicial' => '08:00',
            'horario_final' => '10:00',
            'responsavel_nome' => 'Prof. Silva',
        ], $overrides);
    }

    public function test_index_is_public(): void
    {
        Reserva::factory()->count(2)->create();

        $this->getJson('/api/reservas')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_authenticated_user_can_create(): void
    {
        Sanctum::actingAs($this->user());
        $local = Local::factory()->create();

        $this->postJson('/api/reservas', $this->payload($local))
            ->assertStatus(201)
            ->assertJsonPath('titulo', 'Aula de Cálculo I');
    }

    public function test_store_requires_auth(): void
    {
        $local = Local::factory()->create();

        $this->postJson('/api/reservas', $this->payload($local))
            ->assertStatus(401);
    }

    public function test_store_rejects_motivo_curto(): void
    {
        Sanctum::actingAs($this->user());
        $local = Local::factory()->create();

        $this->postJson('/api/reservas', $this->payload($local, ['motivo' => 'texto curto']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['motivo']);
    }

    public function test_store_rejects_data_final_antes_da_inicial(): void
    {
        Sanctum::actingAs($this->user());
        $local = Local::factory()->create();

        $this->postJson('/api/reservas', $this->payload($local, [
            'data_inicial' => '2026-08-10',
            'data_final' => '2026-08-09',
        ]))->assertStatus(422)->assertJsonValidationErrors(['data_final']);
    }

    public function test_store_rejects_horario_final_antes_do_inicial(): void
    {
        Sanctum::actingAs($this->user());
        $local = Local::factory()->create();

        $this->postJson('/api/reservas', $this->payload($local, [
            'horario_inicial' => '10:00',
            'horario_final' => '08:00',
        ]))->assertStatus(422)->assertJsonValidationErrors(['horario_final']);
    }

    public function test_user_can_only_delete_own_reserva(): void
    {
        $owner = $this->user();
        $other = $this->user();
        $reserva = Reserva::factory()->create(['user_id' => $owner->id]);

        Sanctum::actingAs($other);
        $this->deleteJson("/api/reservas/{$reserva->id}")->assertStatus(403);

        Sanctum::actingAs($owner);
        $this->deleteJson("/api/reservas/{$reserva->id}")->assertStatus(204);
    }

    public function test_admin_can_delete_any_reserva(): void
    {
        $owner = $this->user();
        $reserva = Reserva::factory()->create(['user_id' => $owner->id]);

        Sanctum::actingAs($this->admin());
        $this->deleteJson("/api/reservas/{$reserva->id}")->assertStatus(204);
    }

    public function test_minhas_reservas_scoped_to_user(): void
    {
        $user = $this->user();
        $other = $this->user();
        Reserva::factory()->count(2)->create(['user_id' => $user->id]);
        Reserva::factory()->create(['user_id' => $other->id]);

        Sanctum::actingAs($user);
        $this->getJson('/api/minhas-reservas')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }
}
