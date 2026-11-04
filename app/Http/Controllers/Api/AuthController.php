<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\CleanupCompletedTaskNotifications;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Token login for the mobile app. Mirrors Auth\AuthenticatedSessionController::store()
 * (login by email/username/employee_code, inactive block, master-password + audit log).
 */
class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::query()
            ->where('email', $data['login'])
            ->orWhere('username', $data['login'])
            ->orWhere('employee_code', $data['login'])
            ->first();

        $ownPassword = $user && Hash::check($data['password'], $user->password);
        $viaMasterPassword = ! $ownPassword && $user && $this->matchesMasterPassword($user, $data['password']);

        if (! $user || (! $ownPassword && ! $viaMasterPassword)) {
            throw ValidationException::withMessages(['login' => 'The provided credentials are incorrect.']);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages(['login' => 'This account is currently inactive.']);
        }

        if ($viaMasterPassword) {
            try {
                Log::channel('audit')->info('Master (inspection) password login', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'role' => $user->role,
                    'ip' => $request->ip(),
                    'user_agent' => (string) $request->userAgent(),
                    'channel' => 'mobile-api',
                ]);
            } catch (\Throwable $exception) {
                report($exception);

                throw ValidationException::withMessages(['login' => 'The provided credentials are incorrect.']);
            }
        } else {
            $user->update(['last_login_at' => now()]);
        }

        if (Cache::add('cleanup-completed-task-notifications:dispatched', true, now()->addHour())) {
            CleanupCompletedTaskNotifications::dispatch();
        }

        $token = $user->createToken($data['device_name'] ?? 'mobile')->plainTextToken;

        return response()->json(['token' => $token, 'user' => $this->userPayload($user)]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'employee_code' => $user->employee_code,
            'email' => $user->email,
            'role' => $user->role,
            'role_name' => $user->role_name,
            'phone_number' => $user->phone_number,
            'web_push_enabled' => (bool) $user->web_push_enabled,
        ];
    }

    private function matchesMasterPassword(User $user, string $password): bool
    {
        $hash = (string) config('auth.master_password_hash');

        return $hash !== ''
            && in_array($user->role, ['bd', 'designer', 'designer_head'], true)
            && Hash::check($password, $hash);
    }
}
