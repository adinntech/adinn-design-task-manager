<?php

namespace Tests\Feature\Api;

use App\Models\DesignTask;
use App\Models\DesignTaskBdReview;
use App\Models\DesignTaskEodRecord;
use App\Models\DesignTaskRequest;
use App\Models\DesignTaskStatusHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** "My Tasks" board API: same board as the web (shared service), enriched for the mobile kanban. Scratch DB only. */
class MobileDesignerBoardApiTest extends TestCase
{
    use DatabaseTransactions;

    private User $bd;

    private User $otherBd;

    private User $head;

    private User $designer;

    private User $other;

    protected function setUp(): void
    {
        parent::setUp();

        $db = (string) config('database.connections.'.config('database.default').'.database');
        $this->assertTrue(str_contains($db, 'test') || str_contains($db, 'scratch'), "Refusing to run against '{$db}'.");

        Notification::fake();

        $this->bd = $this->makeUser('bd');
        $this->otherBd = $this->makeUser('bd');
        $this->head = $this->makeUser('designer_head');
        $this->designer = $this->makeUser('designer');
        $this->other = $this->makeUser('designer');
    }

    private function makeUser(string $role): User
    {
        static $n = 0;
        $n++;

        return User::create([
            'name' => ucfirst($role).' '.$n, 'email' => "mdb{$role}{$n}@example.test", 'password' => 'Password@123',
            'role' => $role, 'is_active' => true,
        ]);
    }

    private function task(string $status, array $o = []): DesignTask
    {
        return DesignTask::create(array_merge([
            'task_id' => 'DT-TEST-'.uniqid(), 'assigned_at' => now(), 'assigned_by' => $this->bd->id, 'task_name' => ucfirst(str_replace('_', ' ', $status)).' task',
            'vertical' => 'roadshow', 'task_nature' => 'new_creative_design', 'party_type' => 'client', 'party_name' => 'Acme',
            'contact_person' => 'Jo', 'mobile_number' => '9898989898', 'priority' => 'medium', 'due_at' => now()->addDays(5),
            'designer_id' => $this->designer->id, 'total_creatives' => 4, 'status' => $status, 'requirements' => [],
        ], $o));
    }

    private function board(string $query = ''): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->designer, 'sanctum')->getJson('/api/v1/designer/tasks'.$query);
    }

    private function card(array $json, DesignTask $task): array
    {
        return collect($json['tasks'])->firstWhere('id', $task->id) ?? $this->fail('Task not on the board: '.$task->task_id);
    }

    private function ids(array $json): array
    {
        return collect($json['tasks'])->pluck('id')->all();
    }

    private function request(DesignTask $task, string $type, string $status, array $o = []): DesignTaskRequest
    {
        return DesignTaskRequest::create(array_merge([
            'design_task_id' => $task->id, 'request_type' => $type, 'requested_by' => $this->designer->id, 'overall_status' => $status,
            'designer_head_status' => $status === 'approved' ? 'approved' : 'pending', 'admin_status' => 'pending', 'reason' => 'Because',
        ], $o));
    }

    public function test_columns_follow_the_web_order_with_the_split_log_after_completed(): void
    {
        $columns = $this->board()->assertOk()->json('columns');
        $keys = array_column($columns, 'key');

        $this->assertSame(
            ['pending_bd_approval', 'bd_rejected', 'assigned_tasks', 'review_analysis', 'need_clarification', 'yet_to_start', 'in_progress', 'waiting_confirmation', 'rework', 'prepare_printing_file', 'completed', 'split_log', 'swap_tasks', 'self_declined'],
            $keys
        );

        $byKey = collect($columns)->keyBy('key');
        $this->assertSame('Awaiting BD Approval', $byKey['pending_bd_approval']['label']);
        $this->assertSame('Self Declined', $byKey['self_declined']['label']);
        $this->assertSame('split_log', $byKey['split_log']['type']);
        $this->assertStringContainsString(now()->format('M Y'), $byKey['split_log']['label']);
        $this->assertSame('status', $byKey['completed']['type']);
    }

    public function test_cards_carry_what_the_web_card_shows(): void
    {
        $inProgress = $this->task('in_progress', ['task_name' => 'Hoarding job', 'party_name' => 'Zebra Co', 'party_type' => 'agency']);
        DesignTaskEodRecord::create([
            'design_task_id' => $inProgress->id, 'designer_id' => $this->designer->id, 'update_type' => 'progress', 'completed_count' => 1,
            'total_creatives_snapshot' => 4, 'cumulative_completed' => 1, 'remaining_creatives' => 3, 'attachment_disk' => 'spaces',
            'attachment_path' => 'x/y.zip', 'attachment_original_name' => 'y.zip', 'submitted_at' => now(),
        ]);
        $plain = $this->task('assigned_tasks');

        $json = $this->board()->json();
        $c = $this->card($json, $inProgress);

        $this->assertSame('Hoarding job', $c['display_task_name']);
        $this->assertSame('agency', $c['party_type']);
        $this->assertSame('Zebra Co', $c['party_name']);
        $this->assertSame($this->bd->name, $c['assigner']);
        $this->assertNotNull($c['created_at']);
        $this->assertSame(['completed' => 1, 'remaining' => 3, 'percentage' => 25], $c['progress']);
        $this->assertNull($c['rating']);
        $this->assertNull($c['completed_at']);

        // Progress is only shown for the statuses the web card shows it for.
        $this->assertNull($this->card($json, $plain)['progress']);
    }

    public function test_waiting_cards_say_since_when_and_completed_cards_carry_the_rating_and_date(): void
    {
        $waiting = $this->task('waiting_confirmation');
        DesignTaskStatusHistory::create([
            'design_task_id' => $waiting->id, 'from_status' => 'in_progress', 'to_status' => 'waiting_confirmation',
            'changed_by' => $this->designer->id, 'change_source' => 'progress_completed',
        ]);

        $done = $this->task('completed');
        DesignTaskBdReview::create([
            'design_task_id' => $done->id, 'submitted_by' => $this->bd->id, 'action' => 'completed', 'number_of_creatives' => 4, 'comment' => 'Nice',
            'designer_attitude' => 4.5, 'design_satisfaction' => 4.5, 'rework_iteration' => 4.5, 'meeting_deadline' => 4.5, 'client_satisfaction' => 4.5, 'overall_rating' => 4.5,
        ]);

        $json = $this->board()->json();

        $this->assertNotNull($this->card($json, $waiting)['waiting_since']);
        $this->assertNull($this->card($json, $done)['waiting_since']);
        $this->assertEquals(4.5, $this->card($json, $done)['rating']);
        $this->assertNotNull($this->card($json, $done)['completed_at']);
    }

    public function test_each_card_lists_where_it_may_move_from_the_state_machine(): void
    {
        $working = $this->task('in_progress');
        $new = $this->task('assigned_tasks');
        $review = $this->task('waiting_confirmation');
        $done = $this->task('completed');
        $awaiting = $this->task('pending_bd_approval');

        $json = $this->board()->json();

        $forward = $this->card($json, $working)['forward_to'];
        $this->assertSame(['waiting_confirmation'], $forward);
        $this->assertContains('assigned_tasks', $this->card($json, $working)['backward_to']);
        $this->assertNotContains('in_progress', $this->card($json, $working)['backward_to']);

        // The designer can go forward to any later working column, never into rework/completed.
        $this->assertSame(['review_analysis', 'need_clarification', 'yet_to_start', 'in_progress', 'waiting_confirmation'], $this->card($json, $new)['forward_to']);
        $this->assertSame([], $this->card($json, $new)['backward_to']);

        // BD owns review/completion; nothing moves a task that is out of the designer's hands.
        $this->assertSame([], $this->card($json, $review)['forward_to']);
        $this->assertSame([], $this->card($json, $done)['forward_to']);
        $this->assertSame([], $this->card($json, $awaiting)['forward_to']);
        $this->assertSame([], $this->card($json, $awaiting)['backward_to']);
    }

    public function test_designer_created_tasks_waiting_on_bd_show_in_their_own_columns(): void
    {
        $pending = $this->task('pending_bd_approval');
        $rejected = $this->task('bd_rejected');

        $json = $this->board()->json();

        $this->assertSame('pending_bd_approval', $this->card($json, $pending)['status']);
        $this->assertSame('bd_rejected', $this->card($json, $rejected)['status']);
        $this->assertSame(2, $json['stats']['total']);
    }

    public function test_request_tags_follow_the_latest_request(): void
    {
        $declining = $this->task('review_analysis');
        $this->request($declining, 'decline', 'pending_designer_head');

        $back = $this->task('in_progress');
        $this->request($back, 'status_change', 'pending_designer_head', ['from_status' => 'in_progress', 'to_status' => 'yet_to_start']);

        $splitDone = $this->task('in_progress');
        $this->request($splitDone, 'split', 'approved', ['split_details' => ['creative_count' => 1, 'details' => 'x']]);

        $rejectedSwap = $this->task('yet_to_start');
        $this->request($rejectedSwap, 'swap', 'rejected');

        $untouched = $this->task('assigned_tasks');

        $json = $this->board()->json();

        $this->assertSame(['label' => 'Decline · Pending', 'title' => 'Decline · Pending', 'tone' => 'pending'], $this->card($json, $declining)['tag']);
        $this->assertSame('Waiting for Status Change Approval', $this->card($json, $back)['tag']['title']);
        $this->assertSame('pending', $this->card($json, $back)['tag']['tone']);
        $this->assertSame(['label' => 'Split Task', 'title' => 'Split Task', 'tone' => 'approved'], $this->card($json, $splitDone)['tag']);
        $this->assertSame('Swap · Declined', $this->card($json, $rejectedSwap)['tag']['label']);
        $this->assertSame('declined', $this->card($json, $rejectedSwap)['tag']['tone']);
        $this->assertNull($this->card($json, $untouched)['tag']);
    }

    public function test_self_declined_tasks_appear_read_only_with_who_has_them_now(): void
    {
        $handedOver = $this->task('in_progress', ['designer_id' => $this->other->id]);
        $this->request($handedOver, 'decline', 'approved', ['approved_designer_id' => $this->other->id, 'designer_head_action_by' => $this->head->id, 'designer_head_action_at' => now()]);

        $json = $this->board()->json();
        $c = $this->card($json, $handedOver);

        $this->assertSame('self_declined', $c['status']);
        $this->assertSame($this->other->name, $c['now_with']);
        $this->assertSame([], $c['forward_to']);
        $this->assertSame([], $c['backward_to']);

        // The other designer is working on it and sees it as theirs (in progress), not as a self-declined record.
        $theirs = $this->actingAs($this->other, 'sanctum')->getJson('/api/v1/designer/tasks')->json();
        $this->assertNotContains('self_declined', array_column($theirs['tasks'], 'status'));
        $this->assertNotContains('decline_tasks', array_column($theirs['tasks'], 'status'));
    }

    public function test_split_tasks_column_lists_approved_splits_with_their_status(): void
    {
        $origin = $this->task('in_progress', ['task_name' => 'Big job']);
        $child = $this->task('assigned_tasks', ['designer_id' => $this->other->id, 'task_name' => 'Big job (split)', 'total_creatives' => 2]);
        $request = $this->request($origin, 'split', 'approved', [
            'approved_designer_id' => $this->other->id, 'designer_head_action_by' => $this->head->id, 'designer_head_action_at' => now(),
            'split_details' => ['creative_count' => 2, 'details' => 'x', 'created_task_id' => $child->id],
        ]);

        $log = $this->board()->json('split_log');

        $this->assertCount(1, $log);
        $this->assertSame($child->id, $log[0]['id']); // opens the child task
        $this->assertSame($request->id, $log[0]['request_id']);
        $this->assertTrue($log[0]['has_child']);
        $this->assertSame(['key' => 'approved', 'label' => 'Split approved'], $log[0]['status']);
        $this->assertSame($origin->task_id, $log[0]['split_from']);
        $this->assertSame($this->other->name, $log[0]['designer']);
        $this->assertSame(2, $log[0]['creatives']);
        $this->assertNotNull($log[0]['approved_at']);
    }

    public function test_pending_and_rejected_splits_show_on_the_board_and_open_the_original_task(): void
    {
        $pendingTask = $this->task('in_progress', ['task_name' => 'Waiting split']);
        $this->request($pendingTask, 'split', 'pending_designer_head', ['split_details' => ['creative_count' => 3, 'details' => 'x']]);

        $rejectedTask = $this->task('yet_to_start', ['task_name' => 'Refused split']);
        $this->request($rejectedTask, 'split', 'rejected', ['split_details' => ['creative_count' => 1, 'details' => 'x']]);

        $byTask = collect($this->board()->json('split_log'))->keyBy('id');

        $pending = $byTask[$pendingTask->id];
        $this->assertFalse($pending['has_child']);
        $this->assertSame(['key' => 'pending', 'label' => 'Pending approval'], $pending['status']);
        $this->assertSame(3, $pending['requested_split']);
        $this->assertNull($pending['designer']);
        $this->assertNull($pending['approved_at']);
        $this->assertNotNull($pending['requested_at']);

        $this->assertSame(['key' => 'rejected', 'label' => 'Split rejected'], $byTask[$rejectedTask->id]['status']);
    }

    public function test_a_split_closed_because_the_designer_finished_everything_reads_you_completed_all(): void
    {
        $task = $this->task('waiting_confirmation');
        $this->request($task, 'split', 'rejected', ['split_details' => ['creative_count' => 2, 'details' => 'x', 'completed_before_approval' => true]]);

        $row = collect($this->board()->json('split_log'))->firstWhere('id', $task->id);

        $this->assertSame(['key' => 'completed_all', 'label' => 'You completed all'], $row['status']);
    }

    public function test_splits_follow_the_search_filters_like_the_cards(): void
    {
        $match = $this->task('in_progress', ['task_name' => 'Zebra split job']);
        $other = $this->task('in_progress', ['task_name' => 'Plain job']);
        $this->request($match, 'split', 'pending_designer_head', ['split_details' => ['creative_count' => 1, 'details' => 'x']]);
        $this->request($other, 'split', 'pending_designer_head', ['split_details' => ['creative_count' => 1, 'details' => 'x']]);

        $this->assertSame([$match->id], array_column($this->board('?search=zebra')->json('split_log'), 'id'));
        $this->assertCount(2, $this->board()->json('split_log'));
    }

    public function test_filters_bd_project_number_search_vertical_and_priority(): void
    {
        $mine = $this->task('assigned_tasks', ['zoho_project_number' => 'ZP-100', 'priority' => 'urgent', 'vertical' => 'signage', 'task_name' => 'Zebra banner']);
        $viaOtherBd = $this->task('assigned_tasks', ['assigned_by' => $this->otherBd->id, 'zoho_project_number' => 'ZP-200', 'priority' => 'low']);

        $this->assertEqualsCanonicalizing([$mine->id, $viaOtherBd->id], $this->ids($this->board()->json()));

        $this->assertSame([$viaOtherBd->id], $this->ids($this->board("?bd_id={$this->otherBd->id}")->json()));
        $this->assertSame([$mine->id], $this->ids($this->board('?project_number=ZP-100')->json()));
        $this->assertSame([$mine->id], $this->ids($this->board('?search=zebra')->json()));
        $this->assertSame([$mine->id], $this->ids($this->board('?vertical=signage')->json()));
        $this->assertSame([$mine->id], $this->ids($this->board('?priority=urgent')->json()));
        $this->assertSame([], $this->ids($this->board('?search=nothing-like-this')->json()));
    }

    public function test_overdue_filter_and_period_scoping(): void
    {
        $overdue = $this->task('in_progress', ['due_at' => now()->subDays(2), 'assigned_at' => now()->subMonths(3)]);
        $fresh = $this->task('assigned_tasks');
        $when = now()->subMonthNoOverflow()->startOfMonth()->addDays(3);
        $lastMonth = $this->task('assigned_tasks', ['assigned_at' => $when]);
        $lastMonth->forceFill(['created_at' => $when])->saveQuietly(); // the carry-forward badge keys off created_at

        // Overdue ignores the period, exactly like the web's Priority → Overdue.
        $this->assertSame([$overdue->id], $this->ids($this->board('?priority=overdue')->json()));

        // An unfinished task from an earlier month is carried forward into this one, flagged as such.
        $currentJson = $this->board()->json();
        $this->assertContains($fresh->id, $this->ids($currentJson));
        $this->assertContains($lastMonth->id, $this->ids($currentJson));
        $this->assertStringContainsString('Previous Month Task', $this->card($currentJson, $lastMonth)['previous_month_label']);
        $this->assertNull($this->card($currentJson, $fresh)['previous_month_label']);

        $last = $this->board('?period=last_month')->json();
        $this->assertContains($lastMonth->id, $this->ids($last));
        $this->assertNotContains($fresh->id, $this->ids($last));
        $this->assertSame(now()->subMonthNoOverflow()->format('M Y'), $last['period_label']);

        $custom = $this->board('?period=custom&date_from='.now()->subMonthNoOverflow()->startOfMonth()->format('Y-m-d').'&date_to='.now()->endOfMonth()->format('Y-m-d'))->json();
        $this->assertContains($fresh->id, $this->ids($custom));
        $this->assertContains($lastMonth->id, $this->ids($custom));
    }

    public function test_stats_active_breakdown_and_related_bds(): void
    {
        $this->task('assigned_tasks');
        $this->task('in_progress');
        $this->task('in_progress');
        $this->task('waiting_confirmation');
        $this->task('completed');
        $this->task('assigned_tasks', ['designer_id' => $this->other->id, 'assigned_by' => $this->otherBd->id]);

        $json = $this->board()->json();

        $this->assertSame(['total' => 5, 'active' => 4, 'waiting' => 1, 'completed' => 1], $json['stats']);

        $breakdown = collect($json['active_breakdown'])->pluck('count', 'label');
        $this->assertSame(2, $breakdown['In Progress']);
        $this->assertArrayNotHasKey('Completed', $breakdown->all());

        // Only BDs who actually assigned work to this designer are offered as a filter.
        $this->assertSame([$this->bd->id], array_column($json['bds'], 'id'));
    }

    public function test_the_web_board_still_renders_from_the_shared_service(): void
    {
        $this->task('assigned_tasks', ['task_name' => 'Visible on web']);

        $this->actingAs($this->designer)->get('/designer/tasks')->assertOk();
    }

    public function test_boundaries(): void
    {
        $this->getJson('/api/v1/designer/tasks')->assertUnauthorized();
        $this->actingAs($this->bd, 'sanctum')->getJson('/api/v1/designer/tasks')->assertForbidden();
        $this->actingAs($this->designer, 'sanctum')->getJson('/api/v1/designer/tasks?period=forever')->assertStatus(422);
    }
}
