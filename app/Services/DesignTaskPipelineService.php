<?php

namespace App\Services;

use App\Models\DesignTask;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DesignTaskPipelineService
{
    public function build(
        DesignTask $task,
        Collection $history,
        Collection $comments,
        Collection $editHistory
    ): Collection {
        $events = collect();

        foreach ($history as $event) {
            $title = $event->note ?: $this->statusTitle($event->from_status, $event->to_status);

            if ($event->change_source === 'task_updation') {
                $title = 'Comment Added';
            }

            $events->push([
                'type' => 'history',
                'title' => $this->shortTitle($title, $event->change_source),
                'description' => 'By '.($event->changedBy?->name ?? 'System'),
                'role' => $event->changedBy?->role ?? 'default',
                'created_at' => $event->created_at,
                'order' => $this->eventOrder($event->change_source),
            ]);
        }

        foreach ($comments as $comment) {
            $events->push([
                'type' => 'comment',
                'title' => 'Comment Added',
                'description' => 'By '.($comment->user?->name ?? 'User'),
                'role' => $comment->user?->role ?? 'default',
                'created_at' => $comment->created_at,
                'order' => 20,
            ]);
        }

        // Every task edit is reflected in Pipeline History. The detailed old/new
        // values remain available in Task History, keeping the pipeline compact.
        foreach ($editHistory as $batch) {
            foreach ($batch as $change) {
                $events->push([
                    'type' => 'edit',
                    'title' => 'Task Updated · '.$change->field_name,
                    'description' => 'By '.($change->editor?->name ?? 'User'),
                    'role' => $change->editor?->role ?? 'default',
                    'created_at' => $change->created_at,
                    'order' => 30,
                ]);
            }
        }

        $hasCreated = $history->contains(fn ($event) => $event->change_source === 'task_created');
        $hasAssigned = $history->contains(fn ($event) => $event->change_source === 'task_assigned');
        $assignerRole = $task->assigner?->role ?? 'bd';

        if (! $hasCreated) {
            $events->push([
                'type' => 'system',
                'title' => 'Task Created',
                'description' => 'Created by '.($task->assigner?->name ?? 'BD'),
                'role' => $assignerRole,
                'created_at' => $task->created_at,
                'order' => 0,
            ]);
        }

        if (! $hasAssigned && $task->designer_id) {
            $events->push([
                'type' => 'system',
                'title' => 'Task Assigned',
                'description' => 'Assigned by '.($task->assigner?->name ?? 'BD'),
                'role' => $assignerRole,
                'created_at' => $task->assigned_at ?: $task->created_at,
                'order' => 1,
            ]);
        }

        // Newest first. When multiple events share the same timestamp, use
        // the event order as a stable tie-breaker while keeping creation/assignment
        // events in their natural sequence relative to one another.
        return $events
            ->filter(fn (array $event) => $event['created_at'] !== null)
            ->sort(function (array $a, array $b): int {
                $aTime = $a['created_at']?->getTimestamp() ?? 0;
                $bTime = $b['created_at']?->getTimestamp() ?? 0;

                if ($aTime !== $bTime) {
                    return $bTime <=> $aTime;
                }

                $aOrder = $a['order'] ?? 50;
                $bOrder = $b['order'] ?? 50;

                return $bOrder <=> $aOrder;
            })
            ->values();
    }

    private function eventOrder(?string $source): int
    {
        return match ($source) {
            'task_created' => 0,
            'task_assigned' => 1,
            default => 10,
        };
    }

    private function statusTitle(?string $from, ?string $to): string
    {
        if (! $from) {
            return DesignTaskStatusService::STATUSES[$to] ?? Str::headline((string) $to);
        }

        return (DesignTaskStatusService::STATUSES[$from] ?? Str::headline($from))
            .' → '.(DesignTaskStatusService::STATUSES[$to] ?? Str::headline((string) $to));
    }

    private function shortTitle(string $title, ?string $source): string
    {
        $title = trim($title);
        $lower = strtolower($title);

        if ($source === 'task_updation') {
            return 'Comment Added';
        }
        if ($source === 'task_created' || str_starts_with($lower, 'task created')) {
            return 'Task Created';
        }
        if ($source === 'task_assigned' || str_starts_with($lower, 'task assigned')) {
            return 'Task Assigned';
        }
        if (str_contains($lower, 'swap request')) {
            return str_contains($lower, 'approv') ? 'Swap Request Approved'
                : (str_contains($lower, 'reject') || str_contains($lower, 'declin') ? 'Swap Request Declined' : 'Swap Request Created');
        }
        if (str_contains($lower, 'split request')) {
            return str_contains($lower, 'approv') ? 'Split Request Approved'
                : (str_contains($lower, 'reject') || str_contains($lower, 'declin') ? 'Split Request Declined' : 'Split Request Created');
        }
        if (str_contains($lower, 'decline request')) {
            return str_contains($lower, 'approv') ? 'Decline Request Approved'
                : (str_contains($lower, 'reject') || str_contains($lower, 'declin') ? 'Decline Request Declined' : 'Decline Request Created');
        }
        if (preg_match('/^(.+?)\s*(?:→|->)\s*(.+)$/u', $title, $matches)) {
            return 'Moved to '.trim($matches[2]);
        }

        $words = preg_split('/\s+/', $title) ?: [];
        return count($words) <= 10 ? $title : implode(' ', array_slice($words, 0, 10)).'…';
    }
}
