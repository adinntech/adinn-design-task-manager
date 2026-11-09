<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Bd\TaskController as BdTaskController;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\DesignerProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * "My Profile" for the mobile app. It mirrors the web page's self-service rules (App\Http\Controllers\ProfileController)
 * without touching it: the same validation, always the authenticated user, e-mail never editable here.
 */
class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json($this->payload($request->user()));
    }

    public function updateBasicInfo(Request $request): JsonResponse
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

        $user->update($data);

        return response()->json($this->payload($user->fresh()) + ['message' => 'Profile details updated successfully.']);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ], [
            'password.different' => 'The new password must be different from the current password.',
        ]);

        $user = $request->user();

        // The change is only granted when the current password is right.
        if (! Hash::check($data['current_password'], $user->password)) {
            return response()->json([
                'message' => 'The current password is incorrect.',
                'errors' => ['current_password' => ['The current password is incorrect.']],
            ], 422);
        }

        $user->update(['password' => Hash::make($data['password'])]);

        return response()->json(['message' => 'Password updated successfully.']);
    }

    /** The push switch. (The app's push registers per device; this is the same account-wide preference as the web.) */
    public function updateNotifications(Request $request): JsonResponse
    {
        $data = $request->validate(['web_push_enabled' => ['required', 'boolean']]);

        $request->user()->update(['web_push_enabled' => (bool) $data['web_push_enabled']]);

        return response()->json(['web_push_enabled' => (bool) $request->user()->web_push_enabled]);
    }

    /** Designer: experienced verticals + skills. BD: working verticals. Nobody else has this section. */
    public function updateVerticals(Request $request, DesignerProfileService $normalizer): JsonResponse
    {
        $user = $request->user();
        abort_unless(in_array($user->role, ['designer', 'bd'], true), 403);

        $data = $request->validate([
            'experienced_verticals' => ['nullable', 'array'],
            'experienced_verticals.*' => [Rule::in(array_keys(BdTaskController::VERTICALS))],
            'skills' => ['nullable', 'array'],
            'skills.*' => ['string', 'max:100'],
        ]);

        $update = ['experienced_verticals' => $normalizer->normalizeVerticals($data['experienced_verticals'] ?? [])];
        if ($user->role === 'designer') {
            $update['skills'] = $normalizer->normalizeSkills($data['skills'] ?? []);
        }
        $user->update($update);

        return response()->json($this->payload($user->fresh()) + ['message' => 'Profile details updated successfully.']);
    }

    private function payload(User $user): array
    {
        return [
            'profile' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'employee_code' => $user->employee_code,
                'role' => $user->role,
                'role_name' => $user->role_name,
                'email' => $user->email,
                'phone_number' => $user->phone_number,
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'web_push_enabled' => (bool) $user->web_push_enabled,
                // Designers and BD keep a list of verticals; only designers also keep skills.
                'has_verticals' => in_array($user->role, ['designer', 'bd'], true),
                'has_skills' => $user->role === 'designer',
                'experienced_verticals' => array_values($user->experienced_verticals ?? []),
                'skills' => array_values($user->skills ?? []),
            ],
            'vertical_options' => BdTaskController::VERTICALS,
        ];
    }
}
