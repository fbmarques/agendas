<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmailVerification;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $existing = User::where('email', $data['email'])->first();

        if ($existing && $existing->email_verified_at !== null) {
            return response()->json([
                'message' => 'E-mail já cadastrado.',
            ], 422);
        }

        $user = $existing ?? new User();
        $user->email = $data['email'];
        $user->password = $data['password'];
        $user->role = $user->role ?? 'user';
        $user->save();

        $code = $this->generateAndStoreCode($data['email']);
        Log::info("[OTP register] {$data['email']} -> {$code}");

        return response()->json([
            'message' => 'Código de verificação enviado para o e-mail.',
            'debug_code' => app()->environment('local', 'testing') ? $code : null,
        ], 200);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'otpCode' => ['required', 'string', 'size:6'],
        ]);

        $verification = EmailVerification::where('email', $data['email'])
            ->where('code', $data['otpCode'])
            ->latest('id')
            ->first();

        if (! $verification) {
            return response()->json(['message' => 'Código inválido.'], 422);
        }

        if ($verification->isExpired()) {
            return response()->json(['message' => 'Código expirado.'], 422);
        }

        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            return response()->json(['message' => 'Usuário não encontrado.'], 404);
        }

        $user->email_verified_at = now();
        $user->save();

        EmailVerification::where('email', $data['email'])->delete();

        $token = $user->createToken('spa')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'user' => $this->userPayload($user),
        ], 200);
    }

    public function resendOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || $user->email_verified_at !== null) {
            return response()->json([
                'message' => 'Se houver conta pendente, novo código foi enviado.',
            ], 200);
        }

        $code = $this->generateAndStoreCode($data['email']);
        Log::info("[OTP resend] {$data['email']} -> {$code}");

        return response()->json([
            'message' => 'Novo código enviado.',
            'debug_code' => app()->environment('local', 'testing') ? $code : null,
        ], 200);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Credenciais inválidas.'], 401);
        }

        if ($user->email_verified_at === null) {
            return response()->json([
                'message' => 'E-mail não verificado.',
                'requires_otp' => true,
            ], 403);
        }

        $token = $user->createToken('spa')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'user' => $this->userPayload($user),
        ], 200);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logout efetuado.'], 200);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->userPayload($request->user()), 200);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        Password::sendResetLink(['email' => $data['email']]);

        return response()->json([
            'message' => 'Se o e-mail existir, você receberá as instruções.',
        ], 200);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'resetToken' => ['required', 'string'],
            'email' => ['required', 'email'],
            'newPassword' => ['required', 'string', 'min:8'],
        ]);

        $status = Password::reset(
            [
                'email' => $data['email'],
                'password' => $data['newPassword'],
                'password_confirmation' => $data['newPassword'],
                'token' => $data['resetToken'],
            ],
            function (User $user, string $password) {
                $user->password = $password;
                $user->setRememberToken(Str::random(60));
                $user->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PasswordReset) {
            return response()->json([
                'message' => 'Não foi possível redefinir a senha.',
                'status' => $status,
            ], 422);
        }

        return response()->json(['message' => 'Senha redefinida com sucesso.'], 200);
    }

    public function loginWithProvider(Request $request, string $provider): JsonResponse
    {
        return response()->json([
            'message' => "Login com {$provider} ainda não implementado.",
        ], 501);
    }

    private function generateAndStoreCode(string $email): string
    {
        EmailVerification::where('email', $email)->delete();

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        EmailVerification::create([
            'email' => $email,
            'code' => $code,
            'expires_at' => now()->addMinutes(15),
        ]);

        return $code;
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'email' => $user->email,
            'full_name' => $user->full_name,
            'role' => $user->role,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
        ];
    }
}
