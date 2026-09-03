<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.trim((string) $request->input('search')).'%';

                $query->where(
                    fn ($q) => $q
                        ->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                );
            })
            ->when($request->filled('role'), fn ($query) => $query->where('role', $request->input('role')))
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('is_active', $request->input('status') === 'active');
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    public function create(): View
    {
        return view('admin.users.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'employee_code' => ['required', 'string', 'max:100', 'unique:users,employee_code'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(['admin', 'bd', 'designer', 'designer_head'])],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'is_active' => ['nullable', 'boolean'],
        ], $this->duplicateMessages());

        try {
            User::create([
                'name' => $data['name'],
                'username' => $data['username'],
                'employee_code' => $data['employee_code'],
                'email' => $data['email'],
                'role' => $data['role'],
                'password' => Hash::make($data['password']),
                'is_active' => $request->boolean('is_active'),
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            return back()->withInput()->withErrors([
                'username' => 'That username, email or employee code is already taken.',
            ]);
        }

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User created successfully.');
    }

    /**
     * Custom uniqueness wording matching the exact phrasing requested for the
     * User Management form ("Username already exists", etc.), reused by both
     * store() and update().
     */
    private function duplicateMessages(): array
    {
        return [
            'username.unique' => 'Username already exists.',
            'email.unique' => 'Email already exists.',
            'employee_code.unique' => 'Employee Code already exists.',
        ];
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required', 'string', 'max:255',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
            'employee_code' => [
                'required', 'string', 'max:100',
                Rule::unique('users', 'employee_code')->ignore($user->id),
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'role' => ['required', Rule::in(['admin', 'bd', 'designer', 'designer_head'])],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'is_active' => ['nullable', 'boolean'],
        ], $this->duplicateMessages());

        if ($user->is(auth()->user()) && ! $request->boolean('is_active')) {
            return back()
                ->withErrors(['is_active' => 'You cannot deactivate your own Admin account.'])
                ->withInput();
        }

        $update = [
            'name' => $data['name'],
            'username' => $data['username'],
            'employee_code' => $data['employee_code'],
            'email' => $data['email'],
            'role' => $data['role'],
            'is_active' => $request->boolean('is_active'),
        ];

        if (! empty($data['password'])) {
            $update['password'] = Hash::make($data['password']);
        }

        try {
            $user->update($update);
        } catch (\Illuminate\Database\QueryException $e) {
            return back()->withInput()->withErrors([
                'username' => 'That username, email or employee code is already taken.',
            ]);
        }

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User updated successfully.');
    }

    public function toggle(User $user): RedirectResponse
    {
        if ($user->is(auth()->user())) {
            return back()->with('error', 'You cannot deactivate your own Admin account.');
        }

        $user->update(['is_active' => ! $user->is_active]);

        return back()
            ->with('success', $user->is_active ? 'User activated.' : 'User deactivated.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->is(auth()->user())) {
            return back()->with('error', 'You cannot delete your own Admin account.');
        }

        $dependencies = $this->dependencyCount($user->id);

        if ($dependencies > 0) {
            return back()->with(
                'error',
                'This user already has task/history activity and cannot be permanently deleted. Deactivate the account instead so existing records remain intact.'
            );
        }

        $name = $user->name;
        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('success', "{$name} deleted successfully.");
    }

    private function dependencyCount(int $userId): int
    {
        $count = 0;

        $references = [
            ['design_tasks', 'assigned_by'],
            ['design_tasks', 'designer_id'],
            ['design_task_comments', 'user_id'],
            ['design_task_status_histories', 'changed_by'],
            ['design_task_requests', 'requested_by'],
            ['design_task_requests', 'designer_head_action_by'],
            ['design_task_requests', 'admin_action_by'],
            ['design_task_requests', 'target_designer_id'],
            ['design_task_requests', 'approved_designer_id'],
            ['design_task_eod_records', 'designer_id'],
            ['design_task_edit_histories', 'edited_by'],
        ];

        foreach ($references as [$table, $column]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            if (DB::table($table)->where($column, $userId)->exists()) {
                $count++;
            }
        }

        return $count;
    }
}
