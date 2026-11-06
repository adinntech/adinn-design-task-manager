<?php

namespace Tests\Feature\Api;

use App\Models\DesignTask;
use App\Models\DesignTaskBdReview;
use App\Models\DesignTaskRequest;
use App\Models\DesignTaskStatusHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Designer dashboard API: same numbers as the web page (shared service), scoped to the designer. Scratch DB only. */
class MobileDesignerDashboardApiTest extends TestCase
{
    use DatabaseTransactions;

    private User $bd;

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
        $this->head = $this->makeUser('designer_head');
        $this->designer = $this->makeUser('designer');
        $this->other = $this->makeUser('designer');
    }

    private function makeUser(string $role): User
    {
        static $n = 0;
        $n++;

        return User::create([
            'name' => ucfirst($role).' '.$n, 'email' => "mdd{$role}{$n}@example.test", 'password' => 'Password@123',
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

    private function completedWithReview(float $rating, ?string $comment): DesignTask
    {
        $task = $this->task('completed', ['due_at' => now()->subDays(3)]);

        DesignTaskStatusHistory::create([
            'design_task_id' => $task->id, 'from_status' => 'waiting_confirmation', 'to_status' => 'completed',
            'changed_by' => $this->bd->id, 'change_source' => 'bd_completion_rating',
        ]);
        DesignTaskBdReview::create([
            'design_task_id' => $task->id, 'submitted_by' => $this->bd->id, 'action' => 'completed', 'number_of_creatives' => 4,
            'comment' => $comment, 'designer_attitude' => $rating, 'design_satisfaction' => $rating, 'rework_iteration' => $rating,
            'meeting_deadline' => $rating, 'client_satisfaction' => $rating, 'overall_rating' => $rating,
        ]);

        return $task;
    }

    private function request(DesignTask $task, string $type, string $status, array $o = []): DesignTaskRequest
    {
        return DesignTaskRequest::create(array_merge([
            'design_task_id' => $task->id, 'request_type' => $type, 'requested_by' => $this->designer->id, 'overall_status' => $status,
            'designer_head_status' => $status === 'approved' ? 'approved' : 'pending', 'admin_status' => 'pending', 'reason' => 'Because',
        ], $o));
    }

    private function dashboard(): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->designer, 'sanctum')->getJson('/api/v1/designer/dashboard');
    }

    public function test_role_and_auth_boundaries(): void
    {
        $this->getJson('/api/v1/designer/dashboard')->assertUnauthorized();
        $this->actingAs($this->bd, 'sanctum')->getJson('/api/v1/designer/dashboard')->assertForbidden();
        $this->actingAs($this->head, 'sanctum')->getJson('/api/v1/designer/dashboard/tasks')->assertForbidden();
    }

    public function test_an_empty_dashboard_is_all_zeros_and_does_not_error(): void
    {
        $d = $this->dashboard()->assertOk()->json();

        $this->assertSame(0, $d['stats']['total']);
        $this->assertSame(0, $d['completion_rate']);
        $this->assertNull($d['overall_rating']['average']);
        $this->assertSame([], $d['recent_tasks']);
        $this->assertSame([], $d['reviews']);
        $this->assertSame([], $d['my_requests']);
        $this->assertCount(6, $d['trend']['line']);
        $this->assertCount(5, $d['trend']['cards']);
    }

    public function test_stats_match_the_tasks_and_ignore_other_designers(): void
    {
        $this->task('assigned_tasks');
        $this->task('yet_to_start');
        $this->task('in_progress', ['due_at' => now()->subDay()]);
        $this->task('rework');
        $this->task('waiting_confirmation');
        $this->task('pending_bd_approval');
        $this->task('prepare_printing_file');
        $this->completedWithReview(4.5, 'Great work');
        $this->task('assigned_tasks', ['designer_id' => $this->other->id]);

        $s = $this->dashboard()->assertOk()->json('stats');

        $this->assertSame(8, $s['total']);
        $this->assertSame(1, $s['assigned']);
        $this->assertSame(1, $s['ready_to_start']);
        $this->assertSame(1, $s['in_progress']);
        $this->assertSame(1, $s['rework']);
        $this->assertSame(1, $s['waiting_bd_review']);
        $this->assertSame(1, $s['waiting_bd_confirmation']);
        $this->assertSame(1, $s['prepare_printing_file']);
        $this->assertSame(1, $s['completed']);
        $this->assertSame(1, $s['overdue']); // only the in-progress one; the completed late task is not "overdue"

        $this->assertSame(13, $this->dashboard()->json('completion_rate')); // 1 of 8
    }

    public function test_rating_reviews_and_overall_rework(): void
    {
        $withComment = $this->completedWithReview(4.5, 'Great work');
        $this->completedWithReview(3.5, null); // rated, but no comment → not in the carousel
        $rework = $this->task('rework');
        DesignTaskBdReview::create([
            'design_task_id' => $rework->id, 'submitted_by' => $this->bd->id, 'action' => 'rework', 'number_of_creatives' => 2, 'comment' => 'Fix logo',
        ]);

        $d = $this->dashboard()->json();

        $this->assertSame(4.0, (float) $d['overall_rating']['average']); // avg(4.5, 3.5), the rework row never counts
        $this->assertSame(2, $d['overall_rating']['rated']);
        $this->assertSame(2, $d['overall_rating']['total']);

        $this->assertCount(1, $d['reviews']);
        $this->assertSame('Great work', $d['reviews'][0]['comment']);
        $this->assertSame($withComment->task_id, $d['reviews'][0]['task_id']);
        $this->assertSame($this->bd->name, $d['reviews'][0]['reviewer']);
        $this->assertNotNull($d['reviews'][0]['completed_at']);

        $this->assertSame(2, $d['overall_rework']['creatives']);
    }

    public function test_task_rows_carry_progress_timeliness_and_rating(): void
    {
        $late = $this->completedWithReview(5, 'Perfect');
        $overdue = $this->task('in_progress', ['due_at' => now()->subDays(2), 'party_name' => 'Zebra Co']);

        $rows = collect($this->dashboard()->json('recent_tasks'))->keyBy('id');

        $this->assertSame('late', $rows[$late->id]['completion']['status']);
        $this->assertEquals(5.0, $rows[$late->id]['rating']); // JSON may carry 5 or 5.0
        $this->assertNotNull($rows[$late->id]['completed_at']);

        $this->assertSame('overdue', $rows[$overdue->id]['completion']['status']);
        $this->assertTrue($rows[$overdue->id]['overdue']);
        $this->assertNull($rows[$overdue->id]['rating']);
        $this->assertSame(4, $rows[$overdue->id]['total_creatives']);
        $this->assertSame(0, $rows[$overdue->id]['percentage']);
        $this->assertSame($this->bd->name, $rows[$overdue->id]['assigned_by']);
    }

    public function test_recent_tasks_are_capped_at_twelve_but_search_covers_all(): void
    {
        for ($i = 0; $i < 14; $i++) {
            $this->task('assigned_tasks', ['assigned_at' => now()->subMinutes($i)]);
        }
        $needle = $this->task('assigned_tasks', ['assigned_at' => now()->subYear(), 'party_name' => 'Needle Industries']);

        $this->assertCount(12, $this->dashboard()->json('recent_tasks'));

        $hits = $this->actingAs($this->designer, 'sanctum')->getJson('/api/v1/designer/dashboard/tasks?search=needle')->assertOk()->json('data');
        $this->assertSame([$needle->id], array_column($hits, 'id'));

        $this->assertCount(15, $this->actingAs($this->designer, 'sanctum')->getJson('/api/v1/designer/dashboard/tasks')->json('data'));
    }

    public function test_my_requests_show_status_responder_and_current_handler(): void
    {
        $swapTask = $this->task('swap_tasks');
        $pending = $this->request($this->task('review_analysis'), 'decline', 'pending_designer_head');
        $approvedSwap = $this->request($swapTask, 'swap', 'approved', [
            'approved_designer_id' => $this->other->id, 'designer_head_action_by' => $this->head->id, 'designer_head_action_at' => now(),
        ]);
        $this->request($this->task('in_progress'), 'split', 'rejected', ['designer_head_action_by' => $this->head->id, 'designer_head_action_at' => now()->subDay()]);
        $this->request($this->task('assigned_tasks', ['designer_id' => $this->other->id]), 'decline', 'pending_approval', ['requested_by' => $this->other->id]);

        $d = $this->dashboard()->assertOk()->json();
        $byId = collect($d['my_requests'])->keyBy('id');

        $this->assertCount(3, $d['my_requests']); // only my own
        $this->assertSame('pending', $byId[$pending->id]['status']);
        $this->assertNull($byId[$pending->id]['responded_by']);
        $this->assertSame($this->designer->name, $byId[$pending->id]['current_handler']);

        $this->assertSame('approved', $byId[$approvedSwap->id]['status']);
        $this->assertSame($this->head->name, $byId[$approvedSwap->id]['responded_by']);
        $this->assertSame($this->other->name, $byId[$approvedSwap->id]['current_handler']); // an approved swap is with the new designer
        $this->assertSame($this->designer->name, $byId[$approvedSwap->id]['requested_by']);

        $this->assertSame(['swap' => 1, 'decline' => 1, 'split' => 1, 'approved' => 1], $d['request_counts']);
    }

    public function test_trend_has_six_months_ending_now_and_counts_this_months_work(): void
    {
        $this->task('assigned_tasks');
        $this->task('in_progress');
        $this->completedWithReview(4, 'ok');

        $d = $this->dashboard()->json();
        $line = $d['trend']['line'];

        $this->assertCount(6, $line);
        $this->assertSame(now()->format('M y'), $line[5]['label']);
        $this->assertSame(now()->subMonths(5)->startOfMonth()->format('M y'), $line[0]['label']);
        $this->assertSame(3, $line[5]['assigned']);
        $this->assertSame(1, $line[5]['in_progress']);
        $this->assertSame(1, $line[5]['completed']);
        $this->assertSame(1, $line[5]['overdue_completed']);
        $this->assertSame(0, $line[0]['assigned']);

        $cards = collect($d['trend']['cards'])->pluck('value', 'label');
        $this->assertSame(3, $cards['Assigned Tasks']);
        $this->assertSame(1, $cards['Completed']);
        $this->assertStringContainsString(now()->format('M Y'), $d['trend']['context']);
    }

    public function test_the_web_dashboard_still_renders_from_the_shared_service(): void
    {
        $this->task('assigned_tasks');
        $this->completedWithReview(4, 'Nice');

        $this->actingAs($this->designer)->get('/designer/dashboard')->assertOk()->assertSee('Welcome back')->assertSee('Designer Reviews');
        $this->actingAs($this->designer)->get('/designer/dashboard/task-rows?search=Assigned')->assertOk();
    }
}
