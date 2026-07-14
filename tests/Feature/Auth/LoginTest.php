<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_with_valid_credentials_returns_token(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('senha1234'),
            'email_verified_at' => now(),
            'role' => 'user',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'user@example.com',
            'password' => 'senha1234',
        ]);

        $response->assertOk();
        $this->assertNotEmpty($response->json('access_token'));
        $this->assertSame('user', $response->json('user.role'));
    }

    public function test_login_with_invalid_password_returns_401(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('senha1234'),
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'user@example.com',
            'password' => 'errado',
        ]);

        $response->assertStatus(401);
    }

    public function test_login_with_unknown_email_returns_401(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'inexistente@example.com',
            'password' => 'senha1234',
        ]);

        $response->assertStatus(401);
    }

    public function test_login_when_email_not_verified_returns_403(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('senha1234'),
            'email_verified_at' => null,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'user@example.com',
            'password' => 'senha1234',
        ]);

        $response->assertStatus(403);
        $response->assertJson(['requires_otp' => true]);
    }
}
