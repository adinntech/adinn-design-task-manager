<?php

namespace App\Http\Controllers\Bd;

use App\Http\Controllers\Controller;
use App\Models\DesignTask;
use Illuminate\Http\Request;

class AssignedTaskController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->role === 'bd', 403);

        $tasks = DesignTask::query()
            ->with(['designer:id,name'])
            ->where('assigned_by', $request->user()->id)
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.trim($request->string('search')).'%';
                $query->where(function ($query) use ($term) {
                    $query->where('task_id', 'like', $term)
                        ->orWhere('task_name', 'like', $term)
                        ->orWhere('party_name', 'like', $term);
                });
            })
            ->latest('assigned_at')
            ->paginate(15)
            ->withQueryString();

        return view('bd.tasks.index', compact('tasks'));
    }
}
