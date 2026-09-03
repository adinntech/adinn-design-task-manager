<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
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
        $user->update(['last_login_at' => now()]);

        return $this->redirectForRole(Auth::user()->role);
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
