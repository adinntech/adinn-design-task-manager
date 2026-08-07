<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DesignTask;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $stats = [
            'total_users' => User::count(),
            'bd_users' => User::where('role', 'bd')->where('is_active', true)->count(),
            'designer_users' => User::where('role', 'designer')->where('is_active', true)->count(),
            'total_tasks' => DesignTask::count(),
            'active_tasks' => DesignTask::where('status', '!=', 'completed')->count(),
            'completed_tasks' => DesignTask::where('status', 'completed')->count(),
        ];

        return view('admin.dashboard', compact('stats'));
    }
}
