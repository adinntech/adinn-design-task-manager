<?php

namespace Tests\Feature\Designer;

use App\Livewire\Designer\TaskRequestModal;
use App\Models\DesignTask;
use App\Models\DesignTaskEodRecord;
use App\Models\DesignTaskRequest;
use App\Models\User;
use App\Services\DesignTaskRequestService;
use App\Services\DesignTaskStatusService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Rendering-level checks for today's UI changes: split tickets + Status on the
 * Designer / Designer Head boards, Self-completed wording on the split details
 * card, the admin permission checkbox, and the availability meter in the
 * split/swap request modal.
 */
class BoardsAndMeterRenderTest extends TestCase
{
    use DatabaseTransactions;

    private User $head;

    private User $designer;

    private User $other;

    private User $bd;

    private User $admin;

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
        $this->admin = $this->makeUser('admin');
    }

    private function makeUser(string $role, ?int $headId = null): User
    {
        static $n = 0;
        $n++;

        return User::create([
            'name' => ucfirst($role).' '.$n, 'email' => "render{$role}{$n}@example.test", 'password' => 'Password@123',
            'role' => $role, 'is_active' => true, 'designer_head_id' => $headId,
        ]);
    }

    private function makeTask(int $total = 6): DesignTask
    {
        return DesignTask::create([
            'task_id' => 'DT-TEST-'.uniqid(), 'assigned_at' => now()->subDay(), 'assigned_by' => $this->bd->id,
            'task_name' => 'Render task', 'vertical' => 'roadshow', 'task_nature' => 'creative_adaptation_requirements',
            'party_type' => 'client', 'party_name' => 'Acme', 'contact_person' => 'Jo', 'mobile_number' => '9898989898',
            'priority' => 'medium', 'due_at' => now()->addDays(3)->startOfMinute(), 'designer_id' => $this->designer->id,
            'total_creatives' => $total, 'status' => 'in_progress', 'requirements' => [],
        ]);
    }

    private function split(DesignTask $task): DesignTaskRequest
    {
        return app(DesignTaskRequestService::class)->create($task, $this->designer, 'split', 'Need help', [
            'split_details' => ['creative_count' => 2],
        ]);
    }

    public function test_boards_show_pending_split_with_status_for_designer_and_head(): void
    {
        $this->split($this->makeTask());

        $this->actingAs($this->designer)->get(route('designer.tasks.index'))
            ->assertOk()->assertSee('Status: Pending approval', false)->assertSee('data-status="split_log"', false);

        $this->actingAs($this->head)->get(route('designer-head.assigned-tasks'))
            ->assertOk()->assertSee('Status: Pending approval', false);
    }

    public function test_self_completed_wording_differs_by_viewer_on_boards_and_card(): void
    {
        $task = $this->makeTask(5);
        $request = $this->split($task);
        DesignTaskEodRecord::create([
            'design_task_id' => $task->id, 'designer_id' => $this->designer->id, 'update_type' => 'progress',
            'completed_count' => 5, 'total_creatives_snapshot' => 5, 'cumulative_completed' => 5, 'remaining_creatives' => 0,
            'attachment_disk' => 'spaces', 'attachment_path' => 'x/y.zip', 'attachment_original_name' => 'y.zip', 'submitted_at' => now(),
        ]);
        app(DesignTaskStatusService::class)->moveAsDesigner($task, $this->designer, 'waiting_confirmation');

        $this->actingAs($this->designer)->get(route('designer.tasks.index'))
            ->assertOk()->assertSee('Status: You completed all', false)->assertDontSee('Status: Self-completed', false);

        $this->actingAs($this->head)->get(route('designer-head.assigned-tasks'))
            ->assertOk()->assertSee('Status: Self-completed', false);

        // Split details page shows Self-completed, not Rejected.
        $this->actingAs($this->head)->get(route('designer-head.tasks.show', ['task' => $task, 'tab' => 'split-details']))
            ->assertOk()->assertSee('Self-completed')->assertSee('System (auto-closed)');
    }

    public function test_admin_form_has_permission_checkbox_for_designers(): void
    {
        $this->actingAs($this->admin)->get(route('admin.users.edit', $this->designer))
            ->assertOk()->assertSee('Permission to approve (Swap / Split)')->assertSee('name="can_approve_requests"', false);
        $this->actingAs($this->admin)->get(route('admin.users.create'))
            ->assertOk()->assertSee('name="can_approve_requests"', false);
    }

    public function test_request_modal_renders_availability_meter_for_split_and_swap(): void
    {
        $task = $this->makeTask();
        $this->actingAs($this->designer);

        foreach (['split', 'swap'] as $type) {
            Livewire::test(TaskRequestModal::class, ['task' => $task])
                ->call('open', $type)
                ->assertSee('data-availability-box', false)
                ->assertSee('data-availability-url', false)
                ->assertSee('/designer/designers/__ID__/availability', false);
        }
    }
}
