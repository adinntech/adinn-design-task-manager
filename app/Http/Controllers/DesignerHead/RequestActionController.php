<?php

namespace App\Http\Controllers\DesignerHead;

use App\Http\Controllers\Controller;
use App\Models\DesignTaskRequest;
use App\Services\DesignTaskRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class RequestActionController extends Controller
{
    public function approve(DesignTaskRequest $taskRequest, DesignTaskRequestService $service): RedirectResponse
    {
        try {
            $service->approve($taskRequest, auth()->user());
        } catch (ValidationException $e) {
            return back()->with('error', $e->validator->errors()->first());
        }

        return back()->with('success', 'Request approved.');
    }

    public function reject(DesignTaskRequest $taskRequest, DesignTaskRequestService $service): RedirectResponse
    {
        try {
            $service->reject($taskRequest, auth()->user());
        } catch (ValidationException $e) {
            return back()->with('error', $e->validator->errors()->first());
        }

        return back()->with('success', 'Request rejected.');
    }
}
