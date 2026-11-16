<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends ApiController
{
    /**
     * Authenticate user and issue Sanctum personal access token.
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'login' => 'required|string',
            'password' => 'required|string',
            'device_name' => 'nullable|string|max:100',
        ], [
            'login.required' => 'Username atau email wajib diisi.',
            'password.required' => 'Password akun wajib diisi.',
        ]);

        if ($validator->fails()) {
            return $this->jsonError('Validasi formulir gagal', 422, $validator->errors());
        }

        $login = trim($request->input('login'));
        $password = (string) $request->input('password');
        $deviceName = $request->input('device_name') ?: 'SPKTAD Mobile / Client API';

        // Search by username or email (grouped to keep the OR clause scoped).
        $user = User::with('division')
            ->where(function ($query) use ($login) {
                $query->where('username', $login)
                    ->orWhere('email', $login);
            })
            ->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            ActivityLog::log(
                'api_auth_failed',
                "Percobaan login API gagal menggunakan kredensial: {$login}",
                null,
                $request->ip(),
                $request->userAgent()
            );

            return $this->jsonError('Kredensial username atau password tidak cocok.', 401);
        }

        if (! $user->is_active) {
            ActivityLog::log(
                'api_auth_blocked',
                "Percobaan login API dari akun nonaktif: {$user->username}",
                $user->id,
                $request->ip(),
                $request->userAgent()
            );

            return $this->jsonError('Akun Anda sedang dinonaktifkan oleh Administrator SDM. Silakan hubungi bagian SDM Cabang.', 403);
        }

        // Determine token abilities based on role
        $abilities = $user->role === 'admin'
            ? ['*']
            : ['evaluations:read', 'evaluations:write', 'division:'.$user->division_id];

        // Create Sanctum PlainText Token
        $token = $user->createToken($deviceName, $abilities)->plainTextToken;

        ActivityLog::log(
            'api_auth_success',
            "Pengguna {$user->name} ({$user->username}) berhasil mengotentikasi sesi API via {$deviceName}",
            $user->id,
            $request->ip(),
            $request->userAgent()
        );

        return $this->jsonSuccess([
            'token_type' => 'Bearer',
            'access_token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'position' => $user->position,
                'division_id' => $user->division_id,
                'division_name' => $user->division?->name,
                'is_active' => (bool) $user->is_active,
            ],
            'abilities' => $abilities,
        ], 'Otentikasi berhasil, token akses diterbitkan.');
    }

    /**
     * Get profile of authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('division');

        return $this->jsonSuccess([
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role,
            'position' => $user->position,
            'division_id' => $user->division_id,
            'division_name' => $user->division?->name,
            'is_active' => (bool) $user->is_active,
            'current_token' => [
                'name' => $request->user()->currentAccessToken()?->name,
                'abilities' => $request->user()->currentAccessToken()?->abilities ?? [],
                'last_used_at' => $request->user()->currentAccessToken()?->last_used_at,
            ],
        ], 'Data profil pengguna berhasil dimuat.');
    }

    /**
     * Revoke current access token.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $user->currentAccessToken();

        if ($token) {
            $token->delete();
        }

        if (auth()->guard('sanctum')->check()) {
            auth()->guard('sanctum')->forgetUser();
        }

        ActivityLog::log(
            'api_logout',
            "Pengguna {$user->name} mencabut token akses API",
            $user->id,
            $request->ip(),
            $request->userAgent()
        );

        return $this->jsonSuccess(null, 'Token akses berhasil dicabut (Logout berhasil).');
    }
}
