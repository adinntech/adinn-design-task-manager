<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DesignTaskRequest;
use App\Services\DesignTaskRequestService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RequestActionController extends Controller
{
    public function approve(Request $request, DesignTaskRequest $taskRequest, DesignTaskRequestService $service): RedirectResponse
    {
        $rules = [];
        if (in_array($taskRequest->request_type, ['split', 'swap'], true)) {
            $rules['approved_designer_id'] = [
                'required',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'designer')->where('is_active', true)),
            ];
        }
        if ($taskRequest->request_type === 'split') {
            $rules['approved_creative_count'] = ['required', 'integer', 'min:1'];
        }
        $validated = $rules ? $request->validate($rules) : [];

        try {
            $service->approve(
                $taskRequest,
                auth()->user(),
                isset($validated['approved_designer_id']) ? (int) $validated['approved_designer_id'] : null,
                isset($validated['approved_creative_count']) ? (int) $validated['approved_creative_count'] : null,
            );
        } catch (ValidationException|AuthorizationException $e) {
            $message = $e instanceof ValidationException ? $e->validator->errors()->first() : $e->getMessage();
            return back()->with('error', $message)->withInput();
        }

        return back()->with('success', 'Request approved successfully.');
    }

    public function reject(Request $request, DesignTaskRequest $taskRequest, DesignTaskRequestService $service): RedirectResponse
    {
        $validated = $request->validate([
            'decision_reason' => ['required', 'string', 'max:5000'],
        ]);

        try {
            $service->reject($taskRequest, auth()->user(), $validated['decision_reason']);
        } catch (ValidationException|AuthorizationException $e) {
            $message = $e instanceof ValidationException ? $e->validator->errors()->first() : $e->getMessage();
            return back()->with('error', $message)->withInput();
        }

        return back()->with('success', 'Request declined successfully.');
    }
}
