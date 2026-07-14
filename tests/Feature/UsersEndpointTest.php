<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UsersEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_lists_users(): void
    {
        User::factory()->count(2)->create();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]));

        $this->getJson('/api/users')->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_non_admin_forbidden(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'user', 'email_verified_at' => now()]));

        $this->getJson('/api/users')->assertStatus(403);
    }

    public function test_unauthenticated_401(): void
    {
        $this->getJson('/api/users')->assertStatus(401);
    }
}
