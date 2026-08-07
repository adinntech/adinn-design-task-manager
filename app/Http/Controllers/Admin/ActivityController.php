<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DesignTaskComment;
use App\Models\DesignTaskStatusHistory;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityController extends Controller
{
    public function index(Request $request): View
    {
        $history = DesignTaskStatusHistory::query()
            ->with(['task:id,task_id,task_name', 'changedBy:id,name,role'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.trim((string) $request->input('search')).'%';
                $query->whereHas('task', fn ($q) => $q->where('task_id', 'like', $term)->orWhere('task_name', 'like', $term));
            })
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $commentCount = DesignTaskComment::count();

        return view('admin.activity', compact('history', 'commentCount'));
    }
}
