<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_returns_200_for_existing_email(): void
    {
        Notification::fake();

        User::factory()->create([
            'email' => 'user@example.com',
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'user@example.com',
        ]);

        $response->assertOk();
        Notification::assertSentTo(
            User::where('email', 'user@example.com')->first(),
            ResetPassword::class,
        );
    }

    public function test_forgot_password_returns_200_for_unknown_email(): void
    {
        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'nao-existe@example.com',
        ]);

        $response->assertOk();
    }

    public function test_reset_password_updates_hash_with_valid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('senha-antiga'),
            'email_verified_at' => now(),
        ]);

        $token = Password::createToken($user);

        $response = $this->postJson('/api/auth/reset-password', [
            'resetToken' => $token,
            'email' => 'user@example.com',
            'newPassword' => 'nova-senha-123',
        ]);

        $response->assertOk();
        $this->assertTrue(Hash::check('nova-senha-123', $user->fresh()->password));
    }

    public function test_reset_password_rejects_invalid_token(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('senha-antiga'),
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'resetToken' => 'invalido',
            'email' => 'user@example.com',
            'newPassword' => 'nova-senha-123',
        ]);

        $response->assertStatus(422);
    }
}
