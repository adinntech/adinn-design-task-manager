<?php

namespace App\Http\Controllers\Designer;

use App\Http\Controllers\Controller;
use App\Models\DesignTask;
use Illuminate\Http\Request;

class TaskPageController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->role === 'designer', 403);

        return view('designer.tasks.index');
    }

    public function show(Request $request, DesignTask $task)
    {
        abort_unless(
            $request->user()?->role === 'designer'
            && (int) $task->designer_id === (int) $request->user()->id,
            403
        );

        return view('designer.tasks.show', compact('task'));
    }
}
