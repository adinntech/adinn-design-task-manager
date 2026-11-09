<?php

namespace Database\Seeders;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Rich mock data for redesigning the dashboards: six months of tasks for
 * bd@adinn.com across every status, with progress (EOD) records, BD reviews and
 * ratings, rework cycles, overdue/late work, comments, edit history and
 * decline/split/swap requests (pending + decided).
 *
 * Everything is tagged task_id "MOCK-YYYY-nnnnn" so it is idempotent and removable:
 *   php artisan db:seed --class=MockDashboardDataSeeder              (create / refresh)
 *   MOCK_CLEAN=1 php artisan db:seed --class=MockDashboardDataSeeder (remove only)
 */
class MockDashboardDataSeeder extends Seeder
{
    private Carbon $now;

    private int $seq = 0;

    public function run(): void
    {
        $bd = User::where('email', 'bd@adinn.com')->where('role', 'bd')->first();
        $designers = User::where('role', 'designer')->where('is_active', true)->orderBy('id')->get()->values();
        $head = User::where('role', 'designer_head')->orderBy('id')->first();
        $admin = User::where('role', 'admin')->first();

        if (! $bd || $designers->count() < 2 || ! $head || ! $admin) {
            $this->command?->error('Needs bd@adinn.com, an admin, a designer head and 2+ active designers.');

            return;
        }

        $this->clean();
        if (env('MOCK_CLEAN')) {
            $this->command?->info('Mock tasks removed.');

            return;
        }

        mt_srand(2026);
        $this->now = now();
        $this->seedClients();

        $d = fn (int $i) => $designers[$i % $designers->count()];

        // [status, vertical, nature, name, client, priority, creatives, daysAgo(assigned), dueDays, designerIdx, opts]
        // opts: reworks (n), late (days), done (creatives done for in-flight), clarify, edited, rating
        $rows = [
            // ---------------- November 2026 (current month) ----------------
            ['draft', 'outdoor', 'new_creative_design', 'Hoarding – Diwali Offers', 'Lulu Hypermarket', 'medium', 3, 0, 7, 0, []],
            ['draft', 'signage', 'new_creative', 'Showroom Fascia Sign', 'Pothys', 'low', 2, 0, 9, 1, []],
            ['pending_bd_approval', 'pop_offsets', 'creative_design', 'Counter Top Standees', 'Aachi Masala', 'medium', 6, 1, 6, 1, []],
            ['assigned_tasks', 'outdoor', 'creative_adaptation', 'Unipole Adaptation – Chennai', 'Joyalukkas', 'urgent', 6, 0, 2, 0, []],
            ['assigned_tasks', 'events_activations', 'proposal_designs', 'Dealer Meet Stage Proposal', 'Hyundai Motor India', 'high', 4, 1, 5, 1, []],
            ['review_analysis', 'fixtures', 'design_with_creative', 'Gondola Fixture Wrap', 'Reliance Trends', 'medium', 4, 2, 6, 2, []],
            ['review_analysis', 'roadshow', 'new_creative_design', 'Roadshow Van Wrap – Phase 1', 'TVS Motor Company', 'high', 5, 2, 4, 0, []],
            ['need_clarification', 'signage', 'technical_drawing', 'Glow Sign Technical Drawing', 'Saravana Stores', 'high', 3, 3, 5, 1, ['clarify' => true]],
            ['need_clarification', 'outdoor', 'cutout_size_calculation', 'Mall Entrance Cutouts', 'Phoenix Marketcity', 'medium', 5, 3, 6, 2, ['clarify' => true]],
            ['yet_to_start', 'pop_offsets', 'design_adaptation', 'Danglers & Wobblers', 'Nestlé India', 'low', 8, 4, 8, 0, []],
            ['yet_to_start', 'fixtures', 'design_without_creative', 'Wall Bay Fixtures', 'Max Fashion', 'medium', 4, 5, 3, 1, ['edited' => true]],
            ['yet_to_start', 'outdoor', 'mockup_requirements', 'Bus Shelter Mockups', 'Bata India', 'urgent', 3, 8, 1, 2, []],
            ['in_progress', 'outdoor', 'new_creative_design', 'Metro Pillar Wraps', 'Kalyan Jewellers', 'high', 10, 6, 9, 0, ['done' => 4, 'edited' => true]],
            ['in_progress', 'events_activations', 'three_d_layout', 'Product Launch 3D Layout', 'Hyundai Motor India', 'medium', 6, 7, 8, 1, ['done' => 2]],
            ['in_progress', 'signage', 'mockup', 'LED Sign Mockups', 'Saravana Stores', 'urgent', 5, 8, 4, 2, ['done' => 4]],
            ['in_progress', 'roadshow', 'creative_adaptation_requirements', 'Kiosk Panel Adaptation', 'Airtel Business', 'high', 8, 8, -1, 0, ['done' => 3]],
            ['in_progress', 'outdoor', 'creative_adaptation', 'Airport Dioramas', 'Tanishq', 'urgent', 6, 8, -2, 1, ['done' => 1]],
            ['waiting_confirmation', 'pop_offsets', 'mockup_design', 'Shelf Strips & Headers', 'Britannia', 'medium', 4, 5, 3, 0, ['done' => 4]],
            ['waiting_confirmation', 'signage', 'creative_adaptation', 'Pylon Sign Resize', 'Hotel Sree Annapoorna', 'low', 3, 6, 2, 1, ['done' => 3]],
            ['waiting_confirmation', 'outdoor', 'new_creative_design', 'Bridge Banner Series', 'IndusInd Bank', 'high', 5, 7, 1, 2, ['done' => 5, 'reworks' => 1]],
            ['rework', 'outdoor', 'mockup_requirements', 'Bus Back Panels', 'Bata India', 'high', 4, 8, 1, 0, ['done' => 4, 'reworks' => 1]],
            ['rework', 'events_activations', 'element_design_with_creative', 'Expo Booth Elements', 'Dr Agarwal\'s Eye Hospital', 'medium', 7, 8, 2, 1, ['done' => 7, 'reworks' => 2]],
            ['prepare_printing_file', 'fixtures', 'design_with_creative', 'Endcap Display Pack', 'Reliance Smart', 'medium', 4, 6, 3, 2, ['done' => 4, 'rating' => 4.5]],
            ['prepare_printing_file', 'pop_offsets', 'creative_design', 'Floor Graphics Set', 'Cadbury Mondelez', 'low', 5, 7, 4, 0, ['done' => 5, 'rating' => 4.0]],
            ['completed', 'signage', 'new_creative', 'Wayfinding Sign Suite', 'Apollo Hospitals', 'high', 3, 8, 6, 1, ['done' => 3, 'rating' => 5.0]],
            ['completed', 'outdoor', 'creative_adaptation', 'Billboard Resize Pack', 'Dr Agarwal\'s Eye Hospital', 'medium', 5, 8, 3, 2, ['done' => 5, 'rating' => 4.5, 'reworks' => 1]],
            ['completed', 'roadshow', 'new_creative_design', 'Mobile Van Branding', 'Sundaram Finance', 'high', 4, 8, 2, 0, ['done' => 4, 'rating' => 3.5, 'late' => 2]],
            ['completed', 'pop_offsets', 'design_adaptation', 'Dispenser Toppers', 'Aavin Milk', 'low', 6, 7, 5, 1, ['done' => 6, 'rating' => 4.0]],
            ['completed', 'fixtures', 'design_without_creative', 'Jewellery Counter Fixtures', 'Malabar Gold', 'medium', 4, 8, 4, 2, ['done' => 4, 'rating' => 5.0, 'reworks' => 2]],
            ['completed', 'events_activations', 'proposal_designs', 'Anniversary Gala Proposal', 'Pothys', 'urgent', 5, 8, 2, 0, ['done' => 5, 'rating' => 4.5]],

            // ---------------- October 2026 ----------------
            ['completed', 'outdoor', 'new_creative_design', 'Diwali Unipole Series', 'Kalyan Jewellers', 'high', 8, 30, 5, 0, ['rating' => 4.5, 'reworks' => 1]],
            ['completed', 'signage', 'mockup', 'Store Fascia Mockups', 'Pothys', 'medium', 4, 28, 4, 1, ['rating' => 4.0]],
            ['completed', 'pop_offsets', 'creative_design', 'Festive Dangler Set', 'Aachi Masala', 'low', 6, 26, 6, 2, ['rating' => 3.5, 'late' => 3]],
            ['completed', 'fixtures', 'design_with_creative', 'Gift Counter Wraps', 'Reliance Trends', 'medium', 5, 24, 5, 0, ['rating' => 5.0]],
            ['completed', 'roadshow', 'creative_adaptation_requirements', 'Festive Van Wraps', 'TVS Motor Company', 'high', 3, 22, 4, 1, ['rating' => 4.0, 'late' => 1]],
            ['completed', 'events_activations', 'three_d_layout', 'Dealer Conclave Layout', 'Hyundai Motor India', 'medium', 4, 20, 7, 2, ['rating' => 4.5]],
            ['in_progress', 'outdoor', 'creative_adaptation', 'Highway Hoardings – Salem', 'Joyalukkas', 'high', 6, 18, 5, 0, ['done' => 3]],
            ['completed', 'signage', 'creative_adaptation', 'Pylon Resize', 'Saravana Stores', 'low', 3, 15, 3, 1, ['rating' => 4.0]],

            // ---------------- September 2026 ----------------
            ['completed', 'outdoor', 'mockup_requirements', 'Bus Shelter Mockups', 'Bata India', 'medium', 4, 58, 5, 0, ['rating' => 4.0]],
            ['completed', 'pop_offsets', 'mockup_design', 'Onam Shelf Strips', 'Aavin Milk', 'low', 7, 56, 6, 1, ['rating' => 3.5, 'reworks' => 1]],
            ['completed', 'signage', 'technical_drawing', 'Mall Directory Drawings', 'Phoenix Marketcity', 'medium', 3, 52, 6, 2, ['rating' => 4.5]],
            ['completed', 'fixtures', 'design_without_creative', 'Apparel Wall Bays', 'Max Fashion', 'high', 5, 50, 4, 0, ['rating' => 5.0, 'late' => 2]],
            ['completed', 'events_activations', 'proposal_designs', 'Product Launch Proposal', 'Hyundai Motor India', 'urgent', 4, 47, 5, 1, ['rating' => 4.0]],
            ['rework', 'outdoor', 'new_creative_design', 'Airport Backlit Series', 'Tanishq', 'high', 5, 44, 5, 2, ['done' => 5, 'reworks' => 2]],

            // ---------------- August 2026 ----------------
            ['completed', 'signage', 'new_creative', 'Clinic Signage Pack', 'Apollo Hospitals', 'medium', 4, 88, 6, 0, ['rating' => 4.5]],
            ['completed', 'outdoor', 'creative_adaptation', 'Monsoon Sale Boards', 'Lulu Hypermarket', 'low', 6, 84, 4, 1, ['rating' => 4.0, 'late' => 1]],
            ['completed', 'pop_offsets', 'creative_design', 'Freedom Sale Standees', 'Reliance Smart', 'medium', 8, 80, 5, 2, ['rating' => 3.5]],
            ['completed', 'roadshow', 'new_creative_design', 'Service Camp Vans', 'Sundaram Finance', 'high', 3, 76, 5, 0, ['rating' => 5.0, 'reworks' => 1]],
            ['in_progress', 'fixtures', 'design_with_creative', 'Kiosk Fixture Concepts', 'Malabar Gold', 'medium', 5, 72, 6, 1, ['done' => 2]],

            // ---------------- July 2026 ----------------
            ['completed', 'outdoor', 'new_creative_design', 'Aadi Offer Hoardings', 'Pothys', 'high', 5, 118, 5, 0, ['rating' => 4.0]],
            ['completed', 'signage', 'mockup', 'Branch Sign Mockups', 'IndusInd Bank', 'medium', 6, 114, 5, 1, ['rating' => 4.5, 'late' => 2]],
            ['completed', 'events_activations', 'element_design_without_creative', 'Roadshow Arch Elements', 'Britannia', 'low', 4, 110, 6, 2, ['rating' => 3.5]],
            ['completed', 'pop_offsets', 'design_adaptation', 'Counter Mats', 'Nestlé India', 'medium', 5, 106, 4, 0, ['rating' => 4.0, 'reworks' => 1]],

            // ---------------- June 2026 ----------------
            ['completed', 'outdoor', 'creative_adaptation', 'Summer Campaign Boards', 'Cadbury Mondelez', 'medium', 4, 148, 5, 1, ['rating' => 4.5]],
            ['completed', 'fixtures', 'design_with_creative', 'Pharmacy Gondolas', 'Apollo Hospitals', 'high', 6, 144, 6, 2, ['rating' => 4.0, 'late' => 2]],
            ['completed', 'signage', 'new_creative', 'Cafe Menu Boards', 'Hotel Sree Annapoorna', 'low', 3, 140, 4, 0, ['rating' => 5.0]],
            ['completed', 'roadshow', 'creative_adaptation_requirements', 'Mango Season Vans', 'Aavin Milk', 'medium', 5, 136, 5, 1, ['rating' => 3.5, 'reworks' => 1]],
        ];

        $created = [];
        foreach ($rows as $i => $row) {
            $created[] = $this->makeTask($i, $row, $bd, $d($row[9]), $head, $admin);
        }

        $this->seedRequests($created, $designers, $head, $admin);

        $this->command?->info(count($rows).' mock tasks created for '.$bd->email.'.');
    }

    /* ------------------------------------------------------------------ */

    private function makeTask(int $i, array $row, User $bd, User $designer, User $head, User $admin): array
    {
        [$status, $vertical, $nature, $name, $client, $priority, $total, $daysAgo, $dueDays, , $opts] = $row;
        $reworks = (int) ($opts['reworks'] ?? 0);
        $late = (int) ($opts['late'] ?? 0);
        $done = $opts['done'] ?? ($status === 'completed' ? $total : 0);
        $hasDesigner = $status !== 'draft';

        $assigned = $this->now->copy()->subDays($daysAgo)->setTime(10, mt_rand(0, 40));
        if ($assigned->gt($this->now)) {
            $assigned = $this->now->copy()->subHours(2);
        }
        $due = $assigned->copy()->addDays($dueDays)->setTime(18, 0);
        $seq = ++$this->seq;
        $taskId = 'MOCK-'.$assigned->format('Y').'-'.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);

        $id = DB::table('design_tasks')->insertGetId([
            'task_id' => $taskId,
            'assigned_at' => $assigned,
            'assigned_by' => $bd->id,
            'task_name' => $name,
            'zoho_project_number' => 'ZP-'.$assigned->format('y').str_pad((string) (400 + $seq), 4, '0', STR_PAD_LEFT),
            'vertical' => $vertical,
            'task_nature' => $nature,
            'task_category' => 'client',
            'party_type' => $i % 3 === 0 ? 'agency' : 'client',
            'party_name' => $client,
            'contact_person' => $this->contact($i),
            'mobile_number' => '9'.str_pad((string) mt_rand(100000000, 999999999), 9, '0', STR_PAD_LEFT),
            'priority' => $priority,
            'due_at' => $due,
            'designer_id' => $hasDesigner ? $designer->id : null,
            'total_creatives' => $total,
            'status' => $status,
            'requirements' => json_encode($this->requirements($vertical, $name, $client, $i)),
            'bd_approval_status' => $status === 'pending_bd_approval' ? 'pending' : null,
            'created_at' => $assigned,
            'updated_at' => $assigned,
        ]);

        $t = ['id' => $id, 'designer_id' => $hasDesigner ? $designer->id : null, 'status' => $status, 'assigned' => $assigned, 'due' => $due];

        if (in_array($status, ['draft', 'pending_bd_approval'], true)) {
            $this->history($id, null, 'draft', $bd->id, 'task_created', $assigned, 'Task created.');

            return $t;
        }

        /* ---- timeline: build a chronological list of [to_status, by, source, at] ---- */
        $steps = [];
        $at = $assigned->copy();
        $push = function (string $to, int $by, string $source, Carbon $when, ?string $note = null) use (&$steps) {
            $steps[] = [$to, $by, $source, $when->copy(), $note];
        };

        $push('assigned_tasks', $bd->id, 'task_assigned', $at, 'Assigned to '.$designer->name.'.');
        $path = ['assigned_tasks' => 0, 'review_analysis' => 1, 'need_clarification' => 1, 'yet_to_start' => 2, 'in_progress' => 3];
        $depth = $path[$status] ?? 4; // anything later has passed the early stages

        if ($depth >= 1) {
            $push('review_analysis', $designer->id, 'kanban_drag', $at->copy()->addHours(1)->addMinutes(20));
        }
        if ($status === 'need_clarification') {
            $push('need_clarification', $designer->id, 'kanban_drag', $at->copy()->addHours(3));
        } elseif ($depth >= 2) {
            $push('yet_to_start', $designer->id, 'kanban_drag', $at->copy()->addHours(4));
        }
        if ($depth >= 3) {
            $push('in_progress', $designer->id, 'kanban_drag', $at->copy()->addDay()->setTime(9, 45));
        }

        $submitAt = null;
        $eod = [];       // [type, count, at]
        $reworkRows = []; // [decisionAt, creatives]
        $finalAt = null;

        if ($depth >= 4) {
            $window = max(1, $dueDays);
            $submitAt = $late > 0
                ? $due->copy()->addDays($late)->subHours(3)
                : $assigned->copy()->addHours(max(30, (int) ($window * 24 * 0.7)));
            if ($submitAt->gt($this->now)) {
                $submitAt = $this->now->copy()->subHours(5 + $i % 7);
            }

            $half = (int) ceil($total / 2);
            $eod[] = ['progress', $half, $submitAt->copy()->subHours(20)];
            $eod[] = ['progress', $total - $half, $submitAt->copy()->subHours(1)];
            $push('waiting_confirmation', $designer->id, 'progress_completed', $submitAt);

            $cursor = $submitAt->copy();
            for ($r = 1; $r <= $reworks; $r++) {
                $decision = $cursor->copy()->addHours(6);
                $sent = max(1, (int) ceil($total / ($r + 1)));
                if ($decision->gt($this->now)) {
                    $decision = $this->now->copy()->subHours(3);
                }
                $push('rework', $bd->id, 'bd_rework', $decision, 'Sent back for rework #'.$r.'.');
                $reworkRows[] = [$decision, $sent, $r];

                $stillOpen = $status === 'rework' && $r === $reworks;
                if (! $stillOpen) {
                    $back = $decision->copy()->addDay()->setTime(10, 5);
                    if ($back->gt($this->now)) {
                        $back = $this->now->copy()->subHours(1);
                    }
                    $push('yet_to_start', $designer->id, 'kanban_drag', $back->copy()->subHours(2));
                    $push('in_progress', $designer->id, 'kanban_drag', $back->copy()->subHour());
                    $eod[] = ['rework', $sent, $back->copy()->subMinutes(10), $r];
                    $push('waiting_confirmation', $designer->id, 'progress_completed', $back);
                    $cursor = $back;
                } elseif ($sent > 1) {
                    // rework in flight: a partial rework submission already logged
                    $eod[] = ['rework', (int) floor($sent / 2), $decision->copy()->addHours(8), $r];
                }
            }

            if ($status === 'prepare_printing_file') {
                $finalAt = $cursor->copy()->addHours(5);
                $push('prepare_printing_file', $bd->id, 'bd_prepare_printing_file', $finalAt, 'Approved; preparing print-ready files.');
            } elseif ($status === 'completed') {
                $finalAt = $cursor->copy()->addHours(4 + $i % 9);
                $push('completed', $bd->id, 'bd_completion_rating', $finalAt, 'Approved and rated.');
            }
        } elseif ($depth === 3 && ($opts['done'] ?? 0) > 0) {
            // in_progress: partial progress logged day by day
            $left = (int) $opts['done'];
            $day = 1;
            while ($left > 0) {
                $n = min($left, max(1, (int) ceil($left / 2)));
                $when = $assigned->copy()->addDays($day)->setTime(18, 10);
                if ($when->lte($this->now)) {
                    $eod[] = ['progress', $n, $when];
                }
                $left -= $n;
                $day++;
            }
        }

        foreach ($steps as $k => [$to, $by, $source, $when, $note]) {
            $from = $k === 0 ? null : $steps[$k - 1][0];
            $this->history($id, $from, $to, $by, $source, $this->cap($when), $note);
        }

        $cumulative = 0;
        foreach ($eod as $rec) {
            [$type, $count, $when] = $rec;
            $cumulative = $type === 'progress' ? $cumulative + $count : $cumulative;
            DB::table('design_task_eod_records')->insert([
                'design_task_id' => $id, 'designer_id' => $designer->id, 'update_type' => $type,
                'completed_count' => $count, 'total_creatives_snapshot' => $total,
                'cumulative_completed' => min($total, $cumulative),
                'remaining_creatives' => max(0, $total - $cumulative),
                'rework_count_snapshot' => $type === 'rework' ? ($rec[3] ?? 1) : 0,
                'submitted_at' => $this->cap($when), 'created_at' => $this->cap($when), 'updated_at' => $this->cap($when),
            ]);
        }

        foreach ($reworkRows as [$when, $sent, $ordinal]) {
            DB::table('design_task_bd_reviews')->insert([
                'design_task_id' => $id, 'submitted_by' => $bd->id, 'action' => 'rework',
                'number_of_creatives' => $sent,
                'comment' => ['Logo placement and colour tone need changes.', 'Please fix the typography and increase contrast on the offer text.', 'Client wants the headline reworded; resize for the new bleed.'][($ordinal - 1) % 3],
                'created_at' => $this->cap($when), 'updated_at' => $this->cap($when),
            ]);
        }

        if (in_array($status, ['completed', 'prepare_printing_file'], true) && $finalAt) {
            $rating = (float) ($opts['rating'] ?? 4.0);
            $jitter = fn (float $v) => max(3.0, min(5.0, $v + [-0.5, 0.0, 0.5][mt_rand(0, 2)]));
            $scores = [$jitter($rating), $jitter($rating), $jitter($rating), $late > 0 ? 3.0 : $jitter($rating), $jitter($rating)];
            DB::table('design_task_bd_reviews')->insert([
                'design_task_id' => $id, 'submitted_by' => $bd->id, 'action' => 'completed',
                'number_of_creatives' => $total,
                'comment' => ['Delivered as expected. Great turnaround.', 'Good work, minor delay but quality was strong.', 'Client approved in the first round after edits.', 'Clean files, easy to hand over to printing.'][$i % 4],
                'designer_attitude' => $scores[0], 'design_satisfaction' => $scores[1], 'rework_iteration' => $scores[2],
                'meeting_deadline' => $scores[3], 'client_satisfaction' => $scores[4],
                'overall_rating' => round(array_sum($scores) / 5, 2),
                'created_at' => $this->cap($finalAt), 'updated_at' => $this->cap($finalAt),
            ]);
        }

        /* ---- comments ---- */
        if (! empty($opts['clarify'])) {
            $this->comment($id, $designer->id, $status, 'comment', 'Which file format and bleed should we follow for this one?', $assigned->copy()->addHours(5));
            $this->comment($id, $bd->id, $status, 'comment', 'Use PDF with 5 mm bleed. Brand guidelines are on the Zoho ticket.', $assigned->copy()->addHours(8));
            $this->comment($id, $designer->id, $status, 'comment', 'Thanks, also need the final site dimensions please.', $assigned->copy()->addHours(10));
        } elseif ($depth >= 3) {
            $this->comment($id, $designer->id, $status, 'comment', 'First round shared, please check the layout.', $assigned->copy()->addDays(1)->setTime(17, 30));
            if ($depth >= 4 || $i % 2 === 0) {
                $this->comment($id, $bd->id, $status, 'comment', 'Looks good. Client asked for slightly bolder colours.', $assigned->copy()->addDays(2)->setTime(11, 0));
            }
        }

        if (! empty($opts['edited'])) {
            $batch = (string) Str::uuid();
            foreach ([['Priority', 'medium', $priority], ['Due Date', $due->copy()->subDays(2)->format('d M Y'), $due->format('d M Y')], ['Total Creatives', (string) max(1, $total - 2), (string) $total]] as [$field, $old, $new]) {
                DB::table('design_task_edit_histories')->insert([
                    'design_task_id' => $id, 'edited_by' => $bd->id, 'edit_batch_id' => $batch,
                    'field_name' => $field, 'old_value' => $old, 'new_value' => $new,
                    'created_at' => $this->cap($assigned->copy()->addHours(30)), 'updated_at' => $this->cap($assigned->copy()->addHours(30)),
                ]);
            }
        }

        return $t;
    }

    /**
     * Pending + decided requests so every request panel/card has content.
     */
    private function seedRequests(array $tasks, $designers, User $head, User $admin): void
    {
        $by = fn (callable $f) => collect($tasks)->first($f);
        $now = $this->now;

        $pick = fn (string $status, int $nth = 0) => collect($tasks)->where('status', $status)->where('assigned', '>=', $now->copy()->startOfMonth())->values()->get($nth);
        $other = fn (int $exclude) => $designers->firstWhere('id', '!=', $exclude);

        $make = function (?array $task, array $attrs) use ($head, $admin) {
            if (! $task || ! $task['designer_id']) {
                return;
            }
            DB::table('design_task_requests')->insert(array_merge([
                'design_task_id' => $task['id'], 'requested_by' => $task['designer_id'],
                'designer_head_status' => 'pending', 'admin_status' => 'pending',
                'overall_status' => 'pending_designer_head', 'created_at' => now(), 'updated_at' => now(),
            ], $attrs));
        };
        $decided = function (string $type, ?array $task, Carbon $at, string $overall, string $reason, string $decision, array $extra = []) use ($make, $head, $admin) {
            $approved = $overall === 'approved';
            $make($task, array_merge([
                'request_type' => $type, 'reason' => $reason, 'decision_reason' => $decision,
                'designer_head_status' => $approved ? 'approved' : 'rejected',
                'designer_head_action_by' => $head->id, 'designer_head_action_at' => $at->copy()->subHours(3),
                'admin_status' => $approved ? 'approved' : 'pending',
                'admin_action_by' => $approved ? $admin->id : null, 'admin_action_at' => $approved ? $at : null,
                'overall_status' => $overall, 'created_at' => $at->copy()->subDay(), 'updated_at' => $at,
            ], $extra));
        };

        // Pending (BD "Pending Approval Requests" panel)
        $p1 = $pick('in_progress', 0);
        $p2 = $pick('in_progress', 1);
        $p3 = $pick('in_progress', 2);
        $make($p1, ['request_type' => 'decline', 'reason' => 'Overloaded with 3 other urgent briefs this week; cannot meet this deadline.', 'created_at' => $now->copy()->subHours(6)]);
        $make($p2, ['request_type' => 'split', 'reason' => 'Large volume of creatives; need help from a second designer.', 'split_details' => json_encode(['creative_count' => 3, 'preferred_designer_id' => $other((int) ($p2['designer_id'] ?? 0))?->id]), 'created_at' => $now->copy()->subHours(20)]);
        $make($p3, ['request_type' => 'swap', 'reason' => 'Task needs a 3D specialist.', 'target_designer_id' => $other((int) ($p3['designer_id'] ?? 0))?->id, 'designer_head_status' => 'approved', 'designer_head_action_by' => $head->id, 'designer_head_action_at' => $now->copy()->subHours(4), 'overall_status' => 'pending_admin', 'created_at' => $now->copy()->subDay()]);

        // Decided in the current month (feeds Declined / Split / Swapped cards + Recent Decisions)
        $c = fn (int $n) => $pick('completed', $n);
        $decided('decline', $pick('yet_to_start', 0), $now->copy()->subDays(2), 'approved', 'Leave planned for the deadline week.', 'Approved, reassigning the task.');
        $decided('split', $c(0), $now->copy()->subDays(3), 'approved', 'Volume too high for one designer.', 'Approved; 2 creatives moved to another designer.', ['split_details' => json_encode(['creative_count' => 2])]);
        $s = $pick('rework', 0);
        $decided('swap', $s, $now->copy()->subDays(4), 'approved', 'Specialist skill needed.', 'Transfer approved.', ['target_designer_id' => $other((int) ($s['designer_id'] ?? 0))?->id, 'approved_designer_id' => $other((int) ($s['designer_id'] ?? 0))?->id]);
        $decided('decline', $pick('assigned_tasks', 0), $now->copy()->subDays(1), 'rejected', 'Not comfortable with the format.', 'Rejected, please take it up; guidance shared.');
        $decided('split', $pick('waiting_confirmation', 1), $now->copy()->subHours(30), 'rejected', 'Want to hand off half the creatives.', 'Rejected; deadline allows one designer.', ['split_details' => json_encode(['creative_count' => 1])]);

        // Approved declines in past months (feeds the 6-month trend "Declined" series)
        $past = collect($tasks)->filter(fn ($t) => $t['assigned']->lt($now->copy()->startOfMonth()))->values();
        foreach ([[1, 0], [2, 1], [2, 3], [3, 2], [4, 4], [5, 5]] as [$monthsBack, $idx]) {
            $task = $past->filter(fn ($t) => $t['assigned']->diffInMonths($now) >= $monthsBack - 1)->values()->get($idx);
            $at = $now->copy()->subMonths($monthsBack)->startOfMonth()->addDays(9 + $idx);
            $decided('decline', $task, $at, 'approved', 'Capacity constraints during campaign peak.', 'Approved.');
        }
        $decided('split', $past->get(2), $now->copy()->subMonths(1)->addDays(3), 'approved', 'Volume split.', 'Approved.', ['split_details' => json_encode(['creative_count' => 2])]);
        $decided('swap', $past->get(4), $now->copy()->subMonths(2)->addDays(5), 'approved', 'Reassign to specialist.', 'Approved.');
    }

    /* ------------------------------------------------------------------ */

    private function requirements(string $vertical, string $name, string $client, int $i): array
    {
        $base = [
            'description' => $name.' for '.$client.'. Follow the latest brand guidelines and keep the offer copy prominent.',
            'reference_notes' => 'Refer to last quarter\'s campaign for look and feel.',
            'company_details' => $client.' – regional marketing team',
            'website_link' => 'https://www.'.Str::slug($client).'.example.com',
        ];

        return match ($vertical) {
            'outdoor' => $base + [
                'outdoor_type' => ['Unipole', 'Bus Shelter', 'Hoarding', 'Gantry'][$i % 4], 'board_type' => ['Static', 'Backlit', 'Digital'][$i % 3],
                'location' => ['Chennai', 'Coimbatore', 'Madurai', 'Salem'][$i % 4],
                'board_details' => [['name' => 'Main face', 'width' => 20, 'height' => 10, 'unit' => 'feet', 'area' => 200]],
                'board_size' => ['width' => 20, 'height' => 10, 'square_feet' => 200],
            ],
            'roadshow' => $base + ['location' => ['Coimbatore', 'Trichy', 'Tirupur'][$i % 3], 'vehicle_quantity' => (string) (2 + $i % 6), 'roadshow_subtype' => 'Creative Adaptation', 'vehicle_type' => ['3 Side LED 14 feet', 'Mini Truck 10 feet'][$i % 2]],
            'fixtures' => $base + ['fixture_type' => ['Gondola', 'Wall Bay', 'Endcap'][$i % 3], 'store_format' => ['Hypermarket', 'Supermarket', 'Exclusive Store'][$i % 3], 'material' => ['Acrylic', 'ACP', 'Sunboard'][$i % 3]],
            'signage' => $base + ['sign_type' => ['Glow Sign', 'Pylon', 'Fascia', 'Wayfinding'][$i % 4], 'illumination' => ['LED', 'Non-lit'][$i % 2], 'board_details' => [['name' => 'Sign A', 'width' => 12, 'height' => 4, 'unit' => 'feet', 'area' => 48]]],
            'pop_offsets' => $base + ['pop_type' => ['Dangler', 'Standee', 'Shelf Strip', 'Floor Graphic'][$i % 4], 'quantity' => (string) (50 + $i * 10), 'size' => ['A4', 'A3', '2x3 ft'][$i % 3]],
            default => $base + ['event_name' => $client.' Event', 'event_date' => $this->now->copy()->addDays(20 + $i)->format('Y-m-d'), 'venue' => ['Chennai Trade Centre', 'CODISSIA Coimbatore'][$i % 2], 'expected_footfall' => (string) (300 + $i * 25)],
        };
    }

    private function contact(int $i): string
    {
        return ['Ramesh Kumar', 'Priya Nair', 'Arun Prakash', 'Divya Lakshmi', 'Karthik Raja', 'Meena Sundaram', 'Vignesh S', 'Anitha R'][$i % 8];
    }

    private function seedClients(): void
    {
        $clients = ['Lulu Hypermarket', 'Pothys', 'Joyalukkas', 'Reliance Trends', 'TVS Motor Company', 'Saravana Stores', 'Nestlé India', 'Max Fashion', 'Bata India', 'Kalyan Jewellers', 'Hyundai Motor India', 'Airtel Business', 'Tanishq', 'Britannia', 'IndusInd Bank', 'Apollo Hospitals', 'Malabar Gold', 'Aavin Milk', 'Aachi Masala', 'Sundaram Finance'];
        foreach ($clients as $n => $client) {
            DB::table('client_details')->updateOrInsert(
                ['client_name_normalized' => Str::lower($client)],
                ['client_name' => $client, 'contact_person_name' => $this->contact($n), 'mobile_number' => '98'.str_pad((string) (40000000 + $n * 137), 8, '0', STR_PAD_LEFT), 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    private function history(int $id, ?string $from, string $to, int $by, string $source, Carbon $at, ?string $note = null): void
    {
        DB::table('design_task_status_histories')->insert([
            'design_task_id' => $id, 'from_status' => $from, 'to_status' => $to, 'changed_by' => $by,
            'change_source' => $source, 'note' => $note, 'created_at' => $at, 'updated_at' => $at,
        ]);
    }

    private function comment(int $id, int $userId, string $status, string $context, string $text, Carbon $at): void
    {
        $at = $this->cap($at);
        DB::table('design_task_comments')->insert([
            'design_task_id' => $id, 'user_id' => $userId, 'status_at_comment' => $status,
            'context' => $context, 'comment' => $text, 'created_at' => $at, 'updated_at' => $at,
        ]);
    }

    /** Keep timestamps from landing in the future. */
    private function cap(Carbon $at): Carbon
    {
        return $at->gt($this->now) ? $this->now->copy()->subMinutes(5) : $at;
    }

    private function clean(): void
    {
        $ids = DB::table('design_tasks')->where('task_id', 'like', 'MOCK-%')->pluck('id');
        if ($ids->isEmpty()) {
            return;
        }
        foreach (['design_task_requests', 'design_task_bd_reviews', 'design_task_eod_records', 'design_task_comments', 'design_task_status_histories', 'design_task_edit_histories'] as $table) {
            DB::table($table)->whereIn('design_task_id', $ids)->delete();
        }
        DB::table('design_tasks')->whereIn('id', $ids)->delete();
    }
}
