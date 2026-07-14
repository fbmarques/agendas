<?php

namespace Tests\Feature\Auth;

use App\Models\EmailVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterOtpTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_user_pending_and_stores_otp_code(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'email' => 'novo@example.com',
            'password' => 'senha1234',
        ]);

        $response->assertOk();
        $this->assertNotNull($response->json('debug_code'));

        $this->assertDatabaseHas('users', [
            'email' => 'novo@example.com',
            'role' => 'user',
            'email_verified_at' => null,
        ]);
        $this->assertDatabaseHas('email_verifications', [
            'email' => 'novo@example.com',
        ]);
    }

    public function test_register_rejects_short_password(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'email' => 'x@example.com',
            'password' => '123',
        ]);

        $response->assertStatus(422);
    }

    public function test_register_rejects_already_verified_email(): void
    {
        User::factory()->create([
            'email' => 'verificado@example.com',
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/register', [
            'email' => 'verificado@example.com',
            'password' => 'senha1234',
        ]);

        $response->assertStatus(422);
    }

    public function test_verify_otp_with_valid_code_returns_token_and_marks_verified(): void
    {
        $this->postJson('/api/auth/register', [
            'email' => 'novo@example.com',
            'password' => 'senha1234',
        ])->assertOk();

        $code = EmailVerification::where('email', 'novo@example.com')->first()->code;

        $response = $this->postJson('/api/auth/verify-otp', [
            'email' => 'novo@example.com',
            'otpCode' => $code,
        ]);

        $response->assertOk();
        $this->assertNotEmpty($response->json('access_token'));
        $this->assertNotNull(User::where('email', 'novo@example.com')->first()->email_verified_at);
        $this->assertDatabaseMissing('email_verifications', ['email' => 'novo@example.com']);
    }

    public function test_verify_otp_with_wrong_code_returns_422(): void
    {
        $this->postJson('/api/auth/register', [
            'email' => 'novo@example.com',
            'password' => 'senha1234',
        ])->assertOk();

        $response = $this->postJson('/api/auth/verify-otp', [
            'email' => 'novo@example.com',
            'otpCode' => '000000',
        ]);

        $response->assertStatus(422);
    }

    public function test_verify_otp_expired_returns_422(): void
    {
        $this->postJson('/api/auth/register', [
            'email' => 'novo@example.com',
            'password' => 'senha1234',
        ])->assertOk();

        EmailVerification::where('email', 'novo@example.com')
            ->update(['expires_at' => now()->subMinutes(1)]);

        $code = EmailVerification::where('email', 'novo@example.com')->first()->code;

        $response = $this->postJson('/api/auth/verify-otp', [
            'email' => 'novo@example.com',
            'otpCode' => $code,
        ]);

        $response->assertStatus(422);
    }

    public function test_resend_otp_generates_new_code(): void
    {
        $this->postJson('/api/auth/register', [
            'email' => 'novo@example.com',
            'password' => 'senha1234',
        ])->assertOk();

        $original = EmailVerification::where('email', 'novo@example.com')->first()->code;

        $response = $this->postJson('/api/auth/resend-otp', [
            'email' => 'novo@example.com',
        ]);

        $response->assertOk();
        $novo = EmailVerification::where('email', 'novo@example.com')->latest('id')->first()->code;
        $this->assertNotEquals($original, $novo);
    }

    public function test_resend_otp_returns_200_for_unknown_email(): void
    {
        $response = $this->postJson('/api/auth/resend-otp', [
            'email' => 'inexistente@example.com',
        ]);

        $response->assertOk();
    }
}
