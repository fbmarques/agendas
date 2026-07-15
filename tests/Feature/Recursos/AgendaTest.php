<?php

namespace Tests\Feature\Recursos;

use App\Models\Recurso;
use App\Models\Reserva;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AgendaTest extends TestCase
{
    use RefreshDatabase;

    public function test_responsavel_por_email_ve_agenda(): void
    {
        $email = 'ana@ex.test';
        $ana = User::factory()->create(['email' => $email, 'role' => 'user', 'email_verified_at' => now(), 'full_name' => 'Ana']);
        $recurso = Recurso::create([
            'nome' => 'Som', 'responsavel_nome' => 'Ana', 'responsavel_email' => $email, 'quantidade' => 1,
        ]);
        $r = Reserva::factory()->create();
        $r->recursos()->attach($recurso->id, ['quantidade' => 1]);

        Sanctum::actingAs($ana);
        $this->getJson("/api/recursos/{$recurso->id}/agenda")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_outro_usuario_recebe_403(): void
    {
        $recurso = Recurso::create([
            'nome' => 'Som', 'responsavel_nome' => 'Ana', 'responsavel_email' => 'ana@ex.test', 'quantidade' => 1,
        ]);
        $outro = User::factory()->create(['role' => 'user', 'email_verified_at' => now(), 'full_name' => 'Outro']);
        Sanctum::actingAs($outro);
        $this->getJson("/api/recursos/{$recurso->id}/agenda")->assertStatus(403);
    }

    public function test_admin_ve_agenda(): void
    {
        $recurso = Recurso::create([
            'nome' => 'Som', 'responsavel_nome' => 'Ana', 'responsavel_email' => 'ana@ex.test', 'quantidade' => 1,
        ]);
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now(), 'full_name' => 'Admin']);
        Sanctum::actingAs($admin);
        $this->getJson("/api/recursos/{$recurso->id}/agenda")->assertOk();
    }
}
