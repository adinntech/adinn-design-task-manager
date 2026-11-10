<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Placeholder tasks for previewing the BD UI at every pipeline stage.
 *
 * Everything is tagged: task_id "DEMO-xxx" and task_name "[DEMO] ...", so it is
 * idempotent (re-running replaces the old set) and removable:
 *   php artisan db:seed --class=DemoBdTasksSeeder            (create / refresh)
 *   DEMO_CLEAN=1 php artisan db:seed --class=DemoBdTasksSeeder  (remove only)
 */
class DemoBdTasksSeeder extends Seeder
{
    public function run(): void
    {
        $bd = User::where('email', 'bd@adinn.com')->where('role', 'bd')->first();
        $designers = User::where('role', 'designer')->where('is_active', true)->orderBy('id')->get();

        if (! $bd || $designers->isEmpty()) {
            $this->command?->error('Needs bd@adinn.com and at least one active designer.');

            return;
        }

        $this->clean();

        if (env('DEMO_CLEAN')) {
            $this->command?->info('Demo tasks removed.');

            return;
        }

        $now = now();
        $d = fn (int $i) => $designers[$i % $designers->count()];

        // [status, vertical, nature, name, party, priority, creatives, done, dueDays, createdDaysAgo, extra]
        $rows = [
            ['draft', 'outdoor', 'new_creative_design', 'Hoarding – Festive Sale', 'Lulu Retail', 'medium', 3, 0, 9, 0, []],
            ['pending_bd_approval', 'signage', 'new_creative', 'Shop Signage – Phase 2', 'Pothys', 'high', 2, 0, 6, 0, []],
            ['assigned_tasks', 'outdoor', 'creative_adaptation', 'Unipole Adaptation – Chennai', 'Joyalukkas', 'urgent', 6, 0, 2, 0, []],
            ['review_analysis', 'fixtures', 'design_with_creative', 'Gondola Fixture Wrap', 'Reliance Trends', 'medium', 4, 0, 7, 1, []],
            ['need_clarification', 'roadshow', 'new_creative_design', 'Mobile Van Branding', 'TVS Motors', 'high', 5, 0, 5, 1, ['clarify' => true]],
            ['yet_to_start', 'pop_offsets', 'creative_design', 'Counter Standee Set', 'Aachi Masala', 'low', 8, 0, 10, 1, []],
            ['in_progress', 'outdoor', 'new_creative_design', 'Metro Pillar Wraps', 'Kalyan Jewellers', 'high', 10, 4, 4, 4, []],
            ['in_progress', 'events_activations', 'proposal_designs', 'Product Launch Stage Design', 'Hyundai', 'medium', 6, 2, -2, 6, []],
            ['in_progress', 'signage', 'mockup', 'Glow Sign Mockups', 'Saravana Stores', 'urgent', 5, 4, -1, 5, []],
            ['waiting_confirmation', 'pop_offsets', 'design_adaptation', 'Danglers & Wobblers', 'Nestle India', 'medium', 4, 4, 1, 3, []],
            ['rework', 'outdoor', 'mockup_requirements', 'Bus Shelter Mockups', 'Bata', 'high', 3, 3, 2, 6, ['rework' => true]],
            ['prepare_printing_file', 'fixtures', 'design_without_creative', 'Wall Bay Fixtures', 'Max Fashion', 'medium', 4, 4, 3, 6, ['completed_review' => 4.5]],
            ['completed', 'roadshow', 'creative_adaptation_requirements', 'Kiosk Panel Adaptation', 'Airtel', 'low', 3, 3, -5, 6, ['completed_review' => 4.0]],
            ['completed', 'outdoor', 'creative_adaptation', 'Billboard Resize Pack', 'Dr Agarwals', 'medium', 5, 5, -3, 5, ['completed_review' => 5.0]],
        ];

        foreach ($rows as $i => [$status, $vertical, $nature, $name, $party, $priority, $total, $done, $dueDays, $ago, $extra]) {
            $designer = $d($i);
            $created = $now->copy()->subDays($ago)->setTime(10, 0);

            $id = DB::table('design_tasks')->insertGetId([
                'task_id' => 'DEMO-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'assigned_at' => $created,
                'assigned_by' => $bd->id,
                'task_name' => '[DEMO] '.$name,
                'zoho_project_number' => 'ZP-DEMO-'.(1000 + $i),
                'vertical' => $vertical,
                'task_nature' => $nature,
                'task_category' => 'client',
                'party_type' => $i % 3 === 0 ? 'agency' : 'client',
                'party_name' => $party,
                'contact_person' => 'Demo Contact '.($i + 1),
                'mobile_number' => '98400000'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'priority' => $priority,
                'due_at' => $now->copy()->addDays($dueDays)->setTime(18, 0),
                'designer_id' => $status === 'draft' ? null : $designer->id,
                'total_creatives' => $total,
                'status' => $status,
                'requirements' => json_encode([
                    'brief' => 'Placeholder brief for '.$name.'. Not real client data.',
                    'size' => ['width' => 20, 'height' => 10, 'unit' => 'ft'],
                    'notes' => 'Demo record – safe to delete.',
                ]),
                'bd_approval_status' => $status === 'pending_bd_approval' ? 'pending' : null,
                'created_at' => $created,
                'updated_at' => $created,
            ]);

            $this->history($id, $status, $bd->id, $designer->id, $created, $extra);

            if ($done > 0 && $designer) {
                $this->eod($id, $designer->id, $total, $done, $created, $status);
            }

            if (! empty($extra['clarify'])) {
                $this->comment($id, $designer->id, $status, 'clarification', 'Which file format and bleed should we follow for this one?', $this->cap($created->copy()->addHours(5)));
                $this->comment($id, $bd->id, $status, 'clarification', 'Use PDF with 5 mm bleed. Brand guideline attached on the Zoho ticket.', $this->cap($created->copy()->addHours(8)));
            }
            if (in_array($status, ['in_progress', 'waiting_confirmation', 'rework'], true)) {
                $this->comment($id, $designer->id, $status, 'general', 'First round shared, please check the layout.', $this->cap($created->copy()->addDays(1)));
            }

            if (! empty($extra['rework'])) {
                DB::table('design_task_bd_reviews')->insert([
                    'design_task_id' => $id, 'submitted_by' => $bd->id, 'action' => 'rework',
                    'number_of_creatives' => 2, 'comment' => 'Logo placement and colour tone need changes.',
                    'created_at' => $this->cap($created->copy()->addDays(4)), 'updated_at' => $this->cap($created->copy()->addDays(4)),
                ]);
            }

            if (isset($extra['completed_review'])) {
                $r = $extra['completed_review'];
                DB::table('design_task_bd_reviews')->insert([
                    'design_task_id' => $id, 'submitted_by' => $bd->id, 'action' => 'completed',
                    'number_of_creatives' => $total, 'comment' => 'Delivered as expected. (demo)',
                    'designer_attitude' => $r, 'design_satisfaction' => $r, 'rework_iteration' => 4.0,
                    'meeting_deadline' => 4.5, 'client_satisfaction' => $r, 'overall_rating' => $r,
                    'created_at' => $this->cap($created->copy()->addDays(5)), 'updated_at' => $this->cap($created->copy()->addDays(5)),
                ]);
            }
        }

        // One pending decline request so the BD "requests" panel has content.
        $inProgress = DB::table('design_tasks')->where('task_name', 'like', '[DEMO]%')->where('status', 'in_progress')->first();
        if ($inProgress) {
            DB::table('design_task_requests')->insert([
                'design_task_id' => $inProgress->id, 'request_type' => 'decline',
                'requested_by' => $inProgress->designer_id, 'overall_status' => 'pending_designer_head',
                'designer_head_status' => 'pending', 'admin_status' => 'pending',
                'reason' => 'Overloaded this week – demo request.',
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $this->command?->info(count($rows).' demo tasks created for bd@adinn.com.');
    }

    private function clean(): void
    {
        $ids = DB::table('design_tasks')->where('task_id', 'like', 'DEMO-%')->pluck('id');
        if ($ids->isEmpty()) {
            return;
        }
        foreach (['design_task_requests', 'design_task_bd_reviews', 'design_task_eod_records', 'design_task_comments', 'design_task_status_histories'] as $t) {
            DB::table($t)->whereIn('design_task_id', $ids)->delete();
        }
        DB::table('design_tasks')->whereIn('id', $ids)->delete();
    }

    private function history(int $id, string $status, int $bdId, int $designerId, $created, array $extra): void
    {
        $chain = ['assigned_tasks', 'review_analysis', 'yet_to_start', 'in_progress', 'waiting_confirmation', 'rework', 'prepare_printing_file', 'completed'];
        $path = match ($status) {
            'draft', 'pending_bd_approval' => [],
            'need_clarification' => ['assigned_tasks', 'review_analysis', 'need_clarification'],
            'rework' => ['assigned_tasks', 'review_analysis', 'yet_to_start', 'in_progress', 'waiting_confirmation', 'rework'],
            'prepare_printing_file', 'completed' => ['assigned_tasks', 'review_analysis', 'yet_to_start', 'in_progress', 'waiting_confirmation', 'completed'],
            default => array_slice($chain, 0, array_search($status, $chain, true) + 1),
        };
        if ($status === 'prepare_printing_file') {
            $path[count($path) - 1] = 'prepare_printing_file';
        }

        $from = null;
        foreach ($path as $step => $to) {
            $at = $this->cap($created->copy()->addHours($step * 6));
            DB::table('design_task_status_histories')->insert([
                'design_task_id' => $id, 'from_status' => $from, 'to_status' => $to,
                'changed_by' => in_array($to, ['assigned_tasks', 'rework', 'completed', 'prepare_printing_file'], true) ? $bdId : $designerId,
                'change_source' => $from === null ? 'bd_create' : (in_array($to, ['rework', 'completed'], true) ? 'bd_action' : 'designer_move'),
                'note' => 'Demo history entry.', 'created_at' => $at, 'updated_at' => $at,
            ]);
            $from = $to;
        }
    }

    private function eod(int $id, int $designerId, int $total, int $done, $created, string $status): void
    {
        $first = (int) ceil($done / 2);
        foreach ([[$first, $first], [$done - $first, $done]] as $n => [$count, $cumulative]) {
            if ($count <= 0) {
                continue;
            }
            $at = $this->cap($created->copy()->addDays($n + 1)->setTime(18, 0));
            DB::table('design_task_eod_records')->insert([
                'design_task_id' => $id, 'designer_id' => $designerId, 'update_type' => 'progress',
                'completed_count' => $count, 'total_creatives_snapshot' => $total,
                'cumulative_completed' => $cumulative, 'remaining_creatives' => max(0, $total - $cumulative),
                'rework_count_snapshot' => 0, 'submitted_at' => $at, 'created_at' => $at, 'updated_at' => $at,
            ]);
        }
    }

    private function comment(int $id, int $userId, string $status, string $context, string $text, $at): void
    {
        DB::table('design_task_comments')->insert([
            'design_task_id' => $id, 'user_id' => $userId, 'status_at_comment' => $status,
            'context' => $context, 'comment' => $text, 'created_at' => $at, 'updated_at' => $at,
        ]);
    }

    /** Keep demo timestamps from landing in the future. */
    private function cap($at)
    {
        return $at->gt(now()) ? now()->subMinutes(5) : $at;
    }
}
