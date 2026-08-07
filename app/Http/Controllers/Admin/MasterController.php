<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DesignTaskStatusService;
use Illuminate\View\View;

class MasterController extends Controller
{
    public function index(): View
    {
        $verticals = [
            'outdoor' => ['Outdoor', ['Mock-up Requirements', 'Creative Adaptation', 'New Creative Design', '3D Cut-out Size Calculation']],
            'roadshow' => ['RoadShow', ['Creative Adaptation Requirements', 'New Creative Design']],
            'fixtures' => ['Fixtures', ['Design with Creative Given', 'Design without Creative']],
            'signage' => ['Signage', ['Mock Up', 'Creative Adaptation', 'New Creative', 'Technical Drawing Design', '3D Design', 'Technical Drawing and 3D Design']],
            'pop_offsets' => ['POP and Offsets', ['Mockup Design', 'Design Adaptation', 'Creative Design']],
            'digital_marketing' => ['Digital Marketing', ['Proposal', 'Logo Design', 'Poster Design', 'Video Design']],
            'events_activations' => ['Events and Activations', ['Proposal Designs', 'Element Design with Creative Given', 'Element Design Creative Not Given', '3D Layout']],
        ];

        $statuses = DesignTaskStatusService::STATUSES;

        return view('admin.master', compact('verticals', 'statuses'));
    }
}
