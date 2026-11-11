<?php

namespace App\Http\Controllers\Api\Designer;

use App\Http\Controllers\Api\Bd\CreateTaskController as BdCreateTaskController;
use App\Http\Controllers\Designer\TaskController as WebDesignerTaskController;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Designer-created tasks for the mobile app: the same form as the BD one (the form definition is shared), but the Designer is
 * the assignee and picks the BD who must approve it, so it waits in "pending BD approval" exactly like on the web. As for the
 * BD, nothing is re-implemented: the web Designer controller's own validation and storage run, and its redirect becomes JSON.
 * No drafts and no clone-from-ticket (the web form hides both for a Designer too).
 */
class CreateTaskController extends BdCreateTaskController
{
    private const SUBMITTED = 'Task submitted. Waiting for BD confirmation.';

    /** The BD form definition, with the BDs to pick from instead of the designers. */
    public function form(Request $request): JsonResponse
    {
        $form = parent::form($request)->getData(true);
        unset($form['designers']);

        $form['bds'] = User::query()
            ->where('role', 'bd')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'experienced_verticals']);

        return response()->json($form);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->delegate($request, fn () => (new WebDesignerTaskController)->store($request), 'task', self::SUBMITTED);
    }

    public function storeInhouse(Request $request): JsonResponse
    {
        return $this->delegate($request, fn () => (new WebDesignerTaskController)->storeInhouseAsDesigner($request), 'task', self::SUBMITTED);
    }
}
