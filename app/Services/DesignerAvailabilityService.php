<?php

namespace App\Services;

use App\Models\DesignTask;
use App\Models\User;

/**
 * Informational-only workload snapshot behind the "Designer Availability"
 * meter (BD task creation, Designer split/swap requests, Designer Head and
 * delegate approvals). Deterministic count-based formula (no AI) reusing the
 * same "active = not completed" definition as the Kanban boards — it never
 * affects assignment.
 */
class DesignerAvailabilityService
{
    public function snapshot(User $designer): array
    {
        $stats = DesignTask::where('designer_id', $designer->id)
            ->whereNotIn('status', ['completed'])
            ->selectRaw('count(*) as active_tasks, coalesce(sum(total_creatives), 0) as active_creatives')
            ->first();

        $activeTasks = (int) $stats->active_tasks;
        $activeCreatives = (int) $stats->active_creatives;

        $taskCapacity = max(1, (int) config('workload.designer_capacity'));
        $creativeCapacity = max(1, (int) config('workload.designer_creative_capacity'));

        $taskWorkloadPercent = (int) min(100, round($activeTasks / $taskCapacity * 100));
        $creativeWorkloadPercent = (int) min(100, round($activeCreatives / $creativeCapacity * 100));
        $workloadPercent = max($taskWorkloadPercent, $creativeWorkloadPercent);
        $availabilityPercent = 100 - $workloadPercent;

        $thresholds = config('workload.availability_thresholds');
        $level = match (true) {
            $availabilityPercent >= $thresholds['available'] => 'available',
            $availabilityPercent >= $thresholds['moderate'] => 'moderate',
            $availabilityPercent >= $thresholds['busy'] => 'busy',
            default => 'critical',
        };

        $label = match ($level) {
            'available' => 'Available',
            'moderate' => 'Moderate',
            'busy' => 'Busy',
            default => 'Critical',
        };

        return [
            'designer_id' => $designer->id,
            'designer_name' => $designer->name,
            'active_tasks' => $activeTasks,
            'active_creatives' => $activeCreatives,
            'availability_percent' => $availabilityPercent,
            'level' => $level,
            'label' => $label,
            'checked_in' => app(ZohoAttendanceService::class)->isCheckedIn($designer->email),
        ];
    }
}
