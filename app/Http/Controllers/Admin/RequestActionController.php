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
        $approvedDesignerId = null;

        if (in_array($taskRequest->request_type, ['split', 'swap'], true)) {
            $validated = $request->validate([
                'approved_designer_id' => [
                    'required',
                    Rule::exists('users', 'id')->where(fn ($query) => $query
                        ->where('role', 'designer')
                        ->where('is_active', true)),
                ],
            ]);

            $approvedDesignerId = (int) $validated['approved_designer_id'];
        }

        try {
            $service->approve($taskRequest, auth()->user(), $approvedDesignerId);
        } catch (ValidationException|AuthorizationException $e) {
            $message = $e instanceof ValidationException
                ? $e->validator->errors()->first()
                : $e->getMessage();

            return back()->with('error', $message);
        }

        return back()->with('success', 'Request approved successfully.');
    }

    public function reject(DesignTaskRequest $taskRequest, DesignTaskRequestService $service): RedirectResponse
    {
        try {
            $service->reject($taskRequest, auth()->user());
        } catch (ValidationException|AuthorizationException $e) {
            $message = $e instanceof ValidationException
                ? $e->validator->errors()->first()
                : $e->getMessage();

            return back()->with('error', $message);
        }

        return back()->with('success', 'Request rejected successfully.');
    }
}
