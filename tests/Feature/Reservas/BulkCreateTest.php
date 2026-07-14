<?php

namespace Tests\Feature\Reservas;

use App\Models\Local;
use App\Models\Reserva;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BulkCreateTest extends TestCase
{
    use RefreshDatabase;

    private function auth(): User
    {
        $u = User::factory()->create(['role' => 'user', 'email_verified_at' => now(), 'full_name' => 'X']);
        Sanctum::actingAs($u);
        return $u;
    }

    private function item(Local $local, string $data, array $overrides = []): array
    {
        return array_merge([
            'titulo' => 'Aula recorrente',
            'motivo' => 'Aula recorrente da disciplina de exemplo para primeiro semestre com carga horária completa',
            'campi_id' => $local->campi_id,
            'grupo_id' => $local->grupo_id,
            'local_id' => $local->id,
            'tipo_local' => $local->tipo,
            'data_inicial' => $data,
            'data_final' => $data,
            'horario_inicial' => '08:00',
            'horario_final' => '10:00',
            'responsavel_nome' => 'Prof',
            'recorrente' => true,
        ], $overrides);
    }

    public function test_bulk_success(): void
    {
        $this->auth();
        $local = Local::factory()->create();

        $payload = ['reservas' => [
            $this->item($local, '2026-09-07'),
            $this->item($local, '2026-09-14'),
            $this->item($local, '2026-09-21'),
        ]];

        $r = $this->postJson('/api/reservas/bulk', $payload);
        $r->assertStatus(201)->assertJsonPath('created_count', 3);
        $this->assertSame(3, Reserva::count());
    }

    public function test_bulk_rollback_on_conflict(): void
    {
        $this->auth();
        $local = Local::factory()->create();

        // Pre-existing conflict on 2nd date
        Reserva::factory()->create([
            'local_id' => $local->id,
            'campi_id' => $local->campi_id,
            'grupo_id' => $local->grupo_id,
            'data_inicial' => '2026-09-14',
            'data_final' => '2026-09-14',
            'horario_inicial' => '09:00',
            'horario_final' => '11:00',
            'status' => 'confirmada',
        ]);

        $payload = ['reservas' => [
            $this->item($local, '2026-09-07'),
            $this->item($local, '2026-09-14'), // conflita
            $this->item($local, '2026-09-21'),
        ]];

        $r = $this->postJson('/api/reservas/bulk', $payload);
        $r->assertStatus(422);
        $r->assertJsonPath('conflict_index', 1);

        // Deve haver apenas a pré-existente
        $this->assertSame(1, Reserva::count());
    }

    public function test_bulk_requires_auth(): void
    {
        $local = Local::factory()->create();
        $this->postJson('/api/reservas/bulk', ['reservas' => [$this->item($local, '2026-09-07')]])
            ->assertStatus(401);
    }
}
