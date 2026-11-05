<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\DesignerAvailabilityService;
use Illuminate\Http\JsonResponse;

/**
 * Availability meter feed for Designers and Designer Heads (choosing a
 * Preferred Designer for a split/swap, or the Final Designer when deciding
 * one). Routed inside the role-guarded designer / designer-head groups; the
 * BD version lives on Bd\TaskController::availability().
 */
class DesignerAvailabilityController extends Controller
{
    public function __invoke(User $designer, DesignerAvailabilityService $availability): JsonResponse
    {
        abort_unless($designer->role === 'designer' && $designer->is_active, 404);

        return response()->json($availability->snapshot($designer));
    }
}
