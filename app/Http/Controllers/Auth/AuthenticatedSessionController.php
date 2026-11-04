<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Jobs\CleanupCompletedTaskNotifications;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (Auth::check()) {
            return $this->redirectForRole(Auth::user()->role);
        }

        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // Accepts username, email address, or employee code — whichever the
        // account was set up with — so there is no separate login screen per
        // identifier type.
        $user = User::query()
            ->where('email', $credentials['login'])
            ->orWhere('username', $credentials['login'])
            ->orWhere('employee_code', $credentials['login'])
            ->first();

        // The user's own password always wins. The shared inspection password is only
        // tried when it doesn't match, and only for bd / designer / designer_head.
        $ownPassword = $user && Hash::check($credentials['password'], $user->password);
        $viaMasterPassword = ! $ownPassword && $user && $this->matchesMasterPassword($user, $credentials['password']);

        if (! $user || (! $ownPassword && ! $viaMasterPassword)) {
            throw ValidationException::withMessages([
                'login' => 'The provided credentials are incorrect.',
            ]);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        if (! Auth::user()->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'login' => 'This account is currently inactive.',
            ]);
        }

        // Only a genuinely successful, active-account login bumps this — never
        // a refresh, an existing session, or a rejected/inactive attempt above.
        // Skipped for the shared inspection password: only the account's own password counts
        // as the user's login. The use is noted in the app log instead.
        if ($viaMasterPassword) {
            Log::info('Master (inspection) password login', ['user_id' => $user->id, 'role' => $user->role, 'ip' => $request->ip()]);
        } else {
            $user->update(['last_login_at' => now()]);
        }

        // Queued so login never waits on it; Cache::add throttles it to once
        // per hour across all logins so the notification table isn't rescanned
        // on every sign-in.
        if (Cache::add('cleanup-completed-task-notifications:dispatched', true, now()->addHour())) {
            CleanupCompletedTaskNotifications::dispatch();
        }

        return $this->redirectForRole(Auth::user()->role);
    }

    /** Shared inspection password: bd / designer / designer_head only — never admin. */
    private function matchesMasterPassword(User $user, string $password): bool
    {
        $hash = (string) config('auth.master_password_hash');

        return $hash !== ''
            && in_array($user->role, ['bd', 'designer', 'designer_head'], true)
            && Hash::check($password, $hash);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function redirectAuthenticatedUser(): RedirectResponse
    {
        return $this->redirectForRole(Auth::user()->role);
    }

    private function redirectForRole(string $role): RedirectResponse
    {
        return match ($role) {
            'admin' => redirect()->route('admin.dashboard'),
            'bd' => redirect()->route('bd.dashboard'),
            'designer' => redirect()->route('designer.dashboard'),
            'designer_head' => redirect()->route('designer-head.dashboard'),
            default => abort(403, 'No workspace is configured for this role.'),
        };
    }
}
