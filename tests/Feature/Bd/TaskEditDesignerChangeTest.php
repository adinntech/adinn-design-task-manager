<?php

namespace Tests\Feature\Bd;

use App\Models\DesignTask;
use App\Models\DesignTaskEditHistory;
use App\Models\DesignTaskRequest;
use App\Models\DesignTaskStatusHistory;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TaskEditDesignerChangeTest extends TestCase
{
    // Runs inside a transaction that is rolled back after every test, against a
    // schema that already exists (a scratch/test DB — never the real one).
    use DatabaseTransactions;

    private User $bd;

    private User $designerA;

    private User $designerB;

    protected function setUp(): void
    {
        parent::setUp();

        // Never touch a real database from this suite.
        $db = (string) config('database.connections.'.config('database.default').'.database');
        $this->assertTrue(str_contains($db, 'test') || str_contains($db, 'scratch'), "Refusing to run against '{$db}'.");

        Notification::fake();

        $this->bd = $this->makeUser('bd');
        $this->designerA = $this->makeUser('designer');
        $this->designerB = $this->makeUser('designer');
    }

    private function makeUser(string $role, bool $active = true): User
    {
        static $n = 0;
        $n++;

        return User::create([
            'name' => ucfirst($role).' '.$n,
            'email' => "{$role}{$n}@example.test",
            'password' => 'Password@123',
            'role' => $role,
            'is_active' => $active,
        ]);
    }

    private function makeTask(string $status = 'assigned_tasks', ?User $designer = null, ?User $bd = null): DesignTask
    {
        return DesignTask::create([
            'task_id' => 'DT-TEST-'.uniqid(),
            'assigned_at' => now()->subDay(),
            'assigned_by' => ($bd ?? $this->bd)->id,
            'task_name' => 'Smoke task',
            'vertical' => 'roadshow',
            'task_nature' => 'creative_adaptation_requirements',
            'party_type' => 'client',
            'party_name' => 'Acme',
            'contact_person' => 'Jo',
            'mobile_number' => '9898989898',
            'priority' => 'medium',
            'due_at' => now()->addDays(3)->startOfMinute(),
            'designer_id' => ($designer ?? $this->designerA)->id,
            'total_creatives' => 5,
            'status' => $status,
            'requirements' => [],
        ]);
    }

    private function payload(DesignTask $task, array $overrides = []): array
    {
        return array_merge([
            'priority' => $task->priority,
            'due_at' => $task->due_at->format('Y-m-d\TH:i'),
            'total_creatives' => $task->total_creatives,
        ], $overrides);
    }

    public function test_edit_page_shows_designer_dropdown_when_change_allowed(): void
    {
        $task = $this->makeTask('assigned_tasks');

        $this->actingAs($this->bd)->get(route('bd.tasks.edit', $task))
            ->assertOk()
            ->assertSee('name="designer_id"', false)
            ->assertSee($this->designerB->name);
    }

    public function test_edit_page_keeps_designer_read_only_once_work_started(): void
    {
        $task = $this->makeTask('in_progress');

        $this->actingAs($this->bd)->get(route('bd.tasks.edit', $task))
            ->assertOk()
            ->assertDontSee('name="designer_id"', false)
            ->assertSee($this->designerA->name);
    }

    public function test_bd_can_change_designer_on_new_assignment(): void
    {
        $task = $this->makeTask('assigned_tasks');

        $this->actingAs($this->bd)
            ->put(route('bd.tasks.update', $task), $this->payload($task, ['designer_id' => $this->designerB->id]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $task->refresh();
        $this->assertSame($this->designerB->id, (int) $task->designer_id);
        $this->assertSame('assigned_tasks', $task->status);

        $history = DesignTaskEditHistory::where('design_task_id', $task->id)->where('field_name', 'Assigned Designer')->first();
        $this->assertNotNull($history);
        $this->assertSame($this->designerA->name, $history->old_value);
        $this->assertSame($this->designerB->name, $history->new_value);

        Notification::assertSentTo($this->designerB, TaskAssignedNotification::class);
        Notification::assertNotSentTo($this->designerA, TaskAssignedNotification::class);
        // No status change happened, so no status-history row is written.
        $this->assertSame(0, DesignTaskStatusHistory::where('design_task_id', $task->id)->count());
    }

    public function test_changing_designer_after_review_returns_task_to_assigned_tasks(): void
    {
        $task = $this->makeTask('yet_to_start');

        $this->actingAs($this->bd)
            ->put(route('bd.tasks.update', $task), $this->payload($task, ['designer_id' => $this->designerB->id]))
            ->assertSessionHasNoErrors();

        $task->refresh();
        $this->assertSame($this->designerB->id, (int) $task->designer_id);
        $this->assertSame('assigned_tasks', $task->status);
        $this->assertDatabaseHas('design_task_status_histories', [
            'design_task_id' => $task->id,
            'from_status' => 'yet_to_start',
            'to_status' => 'assigned_tasks',
            'change_source' => 'bd_edit_designer_changed',
        ]);
    }

    public function test_designer_cannot_be_changed_once_in_progress(): void
    {
        $task = $this->makeTask('in_progress');

        $this->actingAs($this->bd)
            ->put(route('bd.tasks.update', $task), $this->payload($task, ['designer_id' => $this->designerB->id]))
            ->assertSessionHasErrors('designer_id');

        $task->refresh();
        $this->assertSame($this->designerA->id, (int) $task->designer_id);
        $this->assertSame('in_progress', $task->status);
        Notification::assertNothingSent();
    }

    public function test_designer_cannot_be_changed_while_a_request_is_pending(): void
    {
        $task = $this->makeTask('assigned_tasks');
        DesignTaskRequest::query()->insert([
            'design_task_id' => $task->id,
            'requested_by' => $this->designerA->id,
            'request_type' => 'decline',
            'overall_status' => 'pending_designer_head',
            'designer_head_status' => 'pending',
            'admin_status' => 'pending',
            'reason' => 'test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->bd)
            ->put(route('bd.tasks.update', $task), $this->payload($task, ['designer_id' => $this->designerB->id]))
            ->assertSessionHasErrors('designer_id');

        $this->assertSame($this->designerA->id, (int) $task->fresh()->designer_id);
    }

    public function test_inactive_or_non_designer_user_is_rejected(): void
    {
        $task = $this->makeTask('assigned_tasks');
        $inactive = $this->makeUser('designer', false);
        $otherBd = $this->makeUser('bd');

        foreach ([$inactive, $otherBd] as $bad) {
            $this->actingAs($this->bd)
                ->put(route('bd.tasks.update', $task), $this->payload($task, ['designer_id' => $bad->id]))
                ->assertSessionHasErrors('designer_id');
        }

        $this->assertSame($this->designerA->id, (int) $task->fresh()->designer_id);
    }

    // ---- Regression: existing edit flow must be unchanged -------------------

    public function test_update_without_designer_field_still_works_and_keeps_designer(): void
    {
        $task = $this->makeTask('in_progress');

        $this->actingAs($this->bd)
            ->put(route('bd.tasks.update', $task), $this->payload($task, ['priority' => 'urgent', 'total_creatives' => 8]))
            ->assertSessionHasNoErrors();

        $task->refresh();
        $this->assertSame('urgent', $task->priority);
        $this->assertSame(8, (int) $task->total_creatives);
        $this->assertSame($this->designerA->id, (int) $task->designer_id);
        $this->assertSame('in_progress', $task->status);
        $this->assertSame(2, DesignTaskEditHistory::where('design_task_id', $task->id)->count());
        Notification::assertNothingSent();
    }

    public function test_resubmitting_same_designer_is_a_no_op(): void
    {
        $task = $this->makeTask('assigned_tasks');

        $this->actingAs($this->bd)
            ->put(route('bd.tasks.update', $task), $this->payload($task, ['designer_id' => $this->designerA->id]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'No changes were detected.');

        $this->assertSame(0, DesignTaskEditHistory::where('design_task_id', $task->id)->count());
        Notification::assertNothingSent();
    }

    public function test_priority_and_designer_can_change_together(): void
    {
        $task = $this->makeTask('review_analysis');

        $this->actingAs($this->bd)
            ->put(route('bd.tasks.update', $task), $this->payload($task, ['priority' => 'high', 'designer_id' => $this->designerB->id]))
            ->assertSessionHasNoErrors();

        $task->refresh();
        $this->assertSame('high', $task->priority);
        $this->assertSame($this->designerB->id, (int) $task->designer_id);
        $this->assertSame(2, DesignTaskEditHistory::where('design_task_id', $task->id)->count());
    }

    public function test_other_bd_cannot_edit_or_reassign(): void
    {
        $task = $this->makeTask('assigned_tasks');
        $stranger = $this->makeUser('bd');

        $this->actingAs($stranger)->get(route('bd.tasks.edit', $task))->assertForbidden();
        $this->actingAs($stranger)
            ->put(route('bd.tasks.update', $task), $this->payload($task, ['designer_id' => $this->designerB->id]))
            ->assertForbidden();

        $this->assertSame($this->designerA->id, (int) $task->fresh()->designer_id);
    }

    public function test_completed_task_stays_locked(): void
    {
        $task = $this->makeTask('completed');

        $this->actingAs($this->bd)->get(route('bd.tasks.edit', $task))->assertForbidden();
        $this->actingAs($this->bd)
            ->put(route('bd.tasks.update', $task), $this->payload($task, ['designer_id' => $this->designerB->id]))
            ->assertForbidden();
    }

    public function test_designer_role_cannot_use_bd_edit(): void
    {
        $task = $this->makeTask('assigned_tasks');

        $this->actingAs($this->designerA)->get(route('bd.tasks.edit', $task))->assertForbidden();
    }
}
