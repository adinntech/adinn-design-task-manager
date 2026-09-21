<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Bd\TaskController as BdTaskController;
use App\Services\DesignerProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Shared "My Profile" page for Admin, Designer, Designer Head and BD —
 * self-service editing of Name/Username/Employee Code/Role Name/Phone Number
 * plus a password change. Email stays read-only for everyone (admin-managed
 * via Admin\UserController); Phone Number reuses Admin\UserController's
 * regex/uniqueness rule. Designer additionally gets self-service editing of
 * experienced verticals + skills via updateDesignerProfile() below.
 */
class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        return view('profile.show', ['user' => $request->user()]);
    }

    /**
     * Name/Username/Employee Code/Role Name/Phone Number — self-service for
     * every role. Email is intentionally excluded (admin-managed only).
     * Phone Number reuses the same regex/uniqueness rule as Admin User
     * Management.
     */
    public function updateBasicInfo(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($request->filled('phone_number')) {
            $request->merge(['phone_number' => trim($request->input('phone_number'))]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($user->id)],
            'employee_code' => ['required', 'string', 'max:100', Rule::unique('users', 'employee_code')->ignore($user->id)],
            'role_name' => ['nullable', 'string', 'max:255'],
            'phone_number' => [
                'required', 'regex:'.AdminUserController::PHONE_REGEX,
                Rule::unique('users', 'phone_number')->ignore($user->id),
            ],
        ], [
            'username.unique' => 'Username already exists.',
            'employee_code.unique' => 'Employee Code already exists.',
            'phone_number.unique' => 'Phone Number already exists.',
            'phone_number.regex' => 'Enter a valid 10-digit phone number.',
        ]);

        // Always the authenticated user — never a submitted id, so one account
        // can never edit another's profile (or phone number) through this form.
        $user->update($data);

        return back()->with('success', 'Profile details updated successfully.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // Always the authenticated user — never a submitted id, so one account
        // can never change another's password through this form.
        Auth::user()->update(['password' => Hash::make($data['password'])]);

        return back()->with('success', 'Password updated successfully.');
    }

    /**
     * Designer-only self-service edit of experienced verticals + skills.
     * Route middleware already restricts this to role=designer; abort_unless
     * here is a defensive second check, not the primary guard.
     */
    public function updateDesignerProfile(Request $request, DesignerProfileService $normalizer): RedirectResponse
    {
        abort_unless($request->user()->role === 'designer', 403);

        $data = $request->validate([
            'experienced_verticals' => ['nullable', 'array'],
            'experienced_verticals.*' => [Rule::in(array_keys(BdTaskController::VERTICALS))],
            'skills' => ['nullable', 'array'],
            'skills.*' => ['string', 'max:100'],
        ]);

        $request->user()->update([
            'experienced_verticals' => $normalizer->normalizeVerticals($data['experienced_verticals'] ?? []),
            'skills' => $normalizer->normalizeSkills($data['skills'] ?? []),
        ]);

        return back()->with('success', 'Profile details updated successfully.');
    }

    /**
     * BD-only self-service edit of Working Verticals — reuses the same
     * experienced_verticals column/normalizer as Designer's profile above,
     * just without the skills field (BD has no skills concept).
     */
    public function updateBdProfile(Request $request, DesignerProfileService $normalizer): RedirectResponse
    {
        abort_unless($request->user()->role === 'bd', 403);

        $data = $request->validate([
            'experienced_verticals' => ['nullable', 'array'],
            'experienced_verticals.*' => [Rule::in(array_keys(BdTaskController::VERTICALS))],
        ]);

        $request->user()->update([
            'experienced_verticals' => $normalizer->normalizeVerticals($data['experienced_verticals'] ?? []),
        ]);

        return back()->with('success', 'Working verticals updated successfully.');
    }
}
