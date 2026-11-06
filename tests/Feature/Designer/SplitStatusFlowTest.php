<?php

namespace Tests\Feature\Designer;

use App\Models\DesignTask;
use App\Models\DesignTaskEodRecord;
use App\Models\DesignTaskRequest;
use App\Models\User;
use App\Services\DesignerHeadTaskBoardService;
use App\Services\DesignTaskRequestService;
use App\Services\DesignTaskStatusService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SplitStatusFlowTest extends TestCase
{
    use DatabaseTransactions;

    private User $head;

    private User $designer;

    private User $other;

    private User $bd;

    protected function setUp(): void
    {
        parent::setUp();

        $db = (string) config('database.connections.'.config('database.default').'.database');
        $this->assertTrue(str_contains($db, 'test') || str_contains($db, 'scratch'), "Refusing to run against '{$db}'.");

        Notification::fake();

        $this->head = $this->makeUser('designer_head');
        $this->designer = $this->makeUser('designer', $this->head->id);
        $this->other = $this->makeUser('designer', $this->head->id);
        $this->bd = $this->makeUser('bd');
    }

    private function makeUser(string $role, ?int $headId = null): User
    {
        static $n = 0;
        $n++;

        return User::create([
            'name' => ucfirst($role).' '.$n,
            'email' => "split{$role}{$n}@example.test",
            'password' => 'Password@123',
            'role' => $role,
            'is_active' => true,
            'designer_head_id' => $headId,
        ]);
    }

    private function makeTask(int $total = 5): DesignTask
    {
        return DesignTask::create([
            'task_id' => 'DT-TEST-'.uniqid(),
            'assigned_at' => now()->subDay(),
            'assigned_by' => $this->bd->id,
            'task_name' => 'Split flow task',
            'vertical' => 'roadshow',
            'task_nature' => 'creative_adaptation_requirements',
            'party_type' => 'client',
            'party_name' => 'Acme',
            'contact_person' => 'Jo',
            'mobile_number' => '9898989898',
            'priority' => 'medium',
            'due_at' => now()->addDays(3)->startOfMinute(),
            'designer_id' => $this->designer->id,
            'total_creatives' => $total,
            'status' => 'in_progress',
            'requirements' => [],
        ]);
    }

    private function requestSplit(DesignTask $task, int $count): DesignTaskRequest
    {
        return app(DesignTaskRequestService::class)->create($task, $this->designer, 'split', 'Need help', [
            'split_details' => ['creative_count' => $count],
        ]);
    }

    private function progress(DesignTask $task, int $count): void
    {
        DesignTaskEodRecord::create([
            'design_task_id' => $task->id,
            'designer_id' => $this->designer->id,
            'update_type' => 'progress',
            'completed_count' => $count,
            'total_creatives_snapshot' => $task->total_creatives,
            'cumulative_completed' => $count,
            'remaining_creatives' => $task->total_creatives - $count,
            'attachment_disk' => 'spaces',
            'attachment_path' => 'x/y.zip',
            'attachment_original_name' => 'y.zip',
            'submitted_at' => now(),
        ]);
    }

    private function boardRows(User $viewer): \Illuminate\Support\Collection
    {
        $this->actingAs($viewer);

        $filters = [
            'search' => '', 'vertical' => '', 'priority' => '', 'bdId' => '', 'period' => 'month',
            'dateFrom' => '', 'dateTo' => '',
            'designerId' => $viewer->role === 'designer' ? (string) $viewer->id : '',
        ];
        if ($viewer->role === 'designer_head') {
            $filters['headDesignerIds'] = [$this->designer->id, $this->other->id];
        }

        return app(DesignerHeadTaskBoardService::class)->build($filters)['splitBoardRows'];
    }

    public function test_dashboard_split_card_links_to_kanban_split_column(): void
    {
        $this->requestSplit($this->makeTask(), 2);

        $this->actingAs($this->designer)->get(route('designer.dashboard'))
            ->assertOk()
            ->assertSee(route('designer.tasks.index', ['focus' => 'split_log']), false);
    }

    public function test_pending_split_shows_pending_status_for_designer_and_head(): void
    {
        $this->requestSplit($this->makeTask(), 2);

        foreach ([$this->designer, $this->head] as $viewer) {
            $row = $this->boardRows($viewer)->first();
            $this->assertNotNull($row, 'pending split missing for '.$viewer->role);
            $this->assertNull($row['childTask']);
            $this->assertSame('Pending approval', $row['request']->splitStatus($viewer)['label']);
        }
    }

    public function test_approved_split_limits_original_and_child_to_approved_counts(): void
    {
        $task = $this->makeTask(5);
        $request = $this->requestSplit($task, 2);

        app(DesignTaskRequestService::class)->approve($request, $this->head, $this->other->id, 2);

        $this->assertSame(3, $task->fresh()->total_creatives);
        $child = DesignTask::find(data_get($request->fresh()->split_details, 'created_task_id'));
        $this->assertSame(2, $child->total_creatives);

        $row = $this->boardRows($this->head)->first();
        $this->assertSame('Split approved', $row['request']->splitStatus($this->head)['label']);
        $this->assertNotNull($row['childTask']);
    }

    public function test_designer_completing_everything_before_approval_closes_split_with_role_based_status(): void
    {
        $task = $this->makeTask(5);
        $request = $this->requestSplit($task, 2);
        $this->progress($task, 5);

        app(DesignTaskStatusService::class)->moveAsDesigner($task, $this->designer, 'waiting_confirmation');

        $this->assertSame('waiting_confirmation', $task->fresh()->status);
        $this->assertSame('rejected', $request->fresh()->overall_status);
        $this->assertTrue($request->fresh()->splitCompletedBeforeApproval());

        $designerRow = $this->boardRows($this->designer)->first();
        $this->assertSame('You completed all', $designerRow['request']->splitStatus($this->designer)['label']);

        $headRow = $this->boardRows($this->head)->first();
        $this->assertSame('Self-completed', $headRow['request']->splitStatus($this->head)['label']);

        // Task keeps all its creatives and no child task is created.
        $this->assertSame(5, $task->fresh()->total_creatives);
        $this->assertNull($designerRow['childTask']);
    }

    public function test_head_cannot_approve_split_after_designer_completed_everything(): void
    {
        $task = $this->makeTask(5);
        $request = $this->requestSplit($task, 2);
        $this->progress($task, 5);
        app(DesignTaskStatusService::class)->moveAsDesigner($task, $this->designer, 'waiting_confirmation');

        try {
            app(DesignTaskRequestService::class)->approve($request, $this->head, $this->other->id, 2);
            $this->fail('Approval should have been refused.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('already completed all creatives', $e->errors()['status'][0]);
        }

        $this->assertSame(5, $task->fresh()->total_creatives);
    }
}
