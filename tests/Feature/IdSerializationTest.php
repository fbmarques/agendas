<?php

namespace Tests\Feature;

use App\Models\Campi;
use App\Models\Grupo;
use App\Models\Local;
use App\Models\Reserva;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Frontend (herdado do Base44) compara IDs como string em várias telas.
 * O trait SerializesIdsAsStrings garante o contrato.
 */
class IdSerializationTest extends TestCase
{
    use RefreshDatabase;

    public function test_campi_response_uses_string_ids(): void
    {
        Campi::factory()->create();

        $response = $this->getJson('/api/campi');
        $response->assertOk();

        $id = $response->json('data.0.id');
        $this->assertIsString($id);
    }

    public function test_grupo_response_stringifies_id_and_campi_id(): void
    {
        $g = Grupo::factory()->create();

        $response = $this->getJson("/api/grupos/{$g->id}");
        $response->assertOk();

        $this->assertIsString($response->json('data.id'));
        $this->assertIsString($response->json('data.campi_id'));
    }

    public function test_local_response_stringifies_grupo_id(): void
    {
        $l = Local::factory()->create();

        $response = $this->getJson("/api/locais/{$l->id}");
        $response->assertOk();

        $this->assertIsString($response->json('data.id'));
        $this->assertIsString($response->json('data.campi_id'));
        $this->assertIsString($response->json('data.grupo_id'));
    }

    public function test_reserva_response_stringifies_all_fks(): void
    {
        $r = Reserva::factory()->create();

        $response = $this->getJson("/api/reservas/{$r->id}");
        $response->assertOk();

        $this->assertIsString($response->json('data.id'));
        $this->assertIsString($response->json('data.campi_id'));
        $this->assertIsString($response->json('data.grupo_id'));
        $this->assertIsString($response->json('data.local_id'));
        $this->assertIsString($response->json('data.user_id'));
    }

    public function test_auth_me_returns_string_id(): void
    {
        Sanctum::actingAs(User::factory()->create(['email_verified_at' => now()]));

        $response = $this->getJson('/api/auth/me');
        $response->assertOk();

        $this->assertIsString($response->json('id'));
    }
}
