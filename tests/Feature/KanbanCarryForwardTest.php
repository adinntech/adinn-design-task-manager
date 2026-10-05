<?php

namespace Tests\Feature;

use App\Livewire\Bd\TaskKanban as BdTaskKanban;
use App\Livewire\Designer\TaskKanban as DesignerTaskKanban;
use App\Models\DesignTask;
use App\Models\DesignTaskStatusHistory;
use App\Models\User;
use App\Services\DesignerHeadTaskBoardService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Kanban month-rollover: every still-open task from an earlier month stays on
 * the current-month board; only completed tasks drop off once their month ends.
 */
class KanbanCarryForwardTest extends TestCase
{
    use DatabaseTransactions;

    private User $bd;

    private User $designer;

    protected function setUp(): void
    {
        parent::setUp();

        // Never touch a real database from this suite.
        $db = (string) config('database.connections.'.config('database.default').'.database');
        $this->assertTrue(str_contains($db, 'test') || str_contains($db, 'scratch'), "Refusing to run against '{$db}'.");

        Notification::fake();

        $this->bd = $this->makeUser('bd');
        $this->designer = $this->makeUser('designer');
    }

    private function makeUser(string $role): User
    {
        static $n = 0;
        $n++;

        return User::create([
            'name' => ucfirst($role).' CF '.$n,
            'email' => "cf{$role}{$n}@example.test",
            'password' => 'Password@123',
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function makeTask(string $status, $createdAt): DesignTask
    {
        $task = DesignTask::create([
            'task_id' => 'DT-CF-'.uniqid(),
            'assigned_at' => $createdAt,
            'assigned_by' => $this->bd->id,
            'task_name' => 'Carry forward task',
            'vertical' => 'roadshow',
            'task_nature' => 'creative_adaptation_requirements',
            'party_type' => 'client',
            'party_name' => 'Acme',
            'contact_person' => 'Jo',
            'mobile_number' => '9898989898',
            'priority' => 'medium',
            'due_at' => now()->addDays(3)->startOfMinute(),
            'designer_id' => $this->designer->id,
            'total_creatives' => 5,
            'status' => $status,
            'requirements' => [],
        ]);

        $task->forceFill(['created_at' => $createdAt])->saveQuietly();

        return $task->refresh();
    }

    private function markCompletedAt(DesignTask $task, $at): void
    {
        $history = DesignTaskStatusHistory::create([
            'design_task_id' => $task->id,
            'from_status' => 'waiting_confirmation',
            'to_status' => 'completed',
            'changed_by' => $this->bd->id,
            'change_source' => 'test',
        ]);
        $history->forceFill(['created_at' => $at])->saveQuietly();
    }

    private function boardIds(array $overrides = []): array
    {
        $board = app(DesignerHeadTaskBoardService::class)->build(array_merge([
            'search' => '', 'vertical' => '', 'priority' => '', 'designerId' => '', 'bdId' => '',
            'projectNumber' => '', 'period' => 'current_month', 'dateFrom' => '', 'dateTo' => '',
        ], $overrides));

        return $board['visibleTasks']->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public static function openStatuses(): array
    {
        return collect([
            'assigned_tasks', 'review_analysis', 'need_clarification', 'yet_to_start',
            'in_progress', 'waiting_confirmation', 'rework', 'prepare_printing_file',
        ])->mapWithKeys(fn ($s) => [$s => [$s]])->all();
    }

    #[DataProvider('openStatuses')]
    public function test_open_task_from_last_month_stays_on_current_board(string $status): void
    {
        $task = $this->makeTask($status, now()->subMonthNoOverflow()->endOfMonth()->subHour());

        $this->assertContains($task->id, $this->boardIds(['designerId' => (string) $this->designer->id]), 'Designer board');
        $this->assertContains($task->id, $this->boardIds(['bdId' => (string) $this->bd->id]), 'BD board');
        $this->assertContains($task->id, $this->boardIds(['headDesignerIds' => [$this->designer->id]]), 'Designer Head board');
    }

    public function test_task_from_two_months_ago_still_open_stays_visible(): void
    {
        $task = $this->makeTask('prepare_printing_file', now()->subMonthsNoOverflow(2)->startOfMonth()->addDay());

        $this->assertContains($task->id, $this->boardIds(['designerId' => (string) $this->designer->id]));
    }

    public function test_task_completed_last_month_drops_off_current_board(): void
    {
        $lastMonth = now()->subMonthNoOverflow()->startOfMonth()->addDays(2);
        $task = $this->makeTask('completed', $lastMonth);
        $this->markCompletedAt($task, $lastMonth->copy()->addDay());

        $this->assertNotContains($task->id, $this->boardIds(['designerId' => (string) $this->designer->id]));
        $this->assertNotContains($task->id, $this->boardIds(['bdId' => (string) $this->bd->id]));
        // ...but it is still in last month's board.
        $this->assertContains($task->id, $this->boardIds(['designerId' => (string) $this->designer->id, 'period' => 'last_month']));
    }

    public function test_task_from_last_month_completed_this_month_still_shows_this_month(): void
    {
        $task = $this->makeTask('completed', now()->subMonthNoOverflow()->endOfMonth()->subHour());
        $this->markCompletedAt($task, now());

        $this->assertContains($task->id, $this->boardIds(['designerId' => (string) $this->designer->id]));
    }

    public function test_last_month_view_is_unchanged_by_carry_forward(): void
    {
        $thisMonth = $this->makeTask('in_progress', now());
        $older = $this->makeTask('in_progress', now()->subMonthsNoOverflow(2)->startOfMonth()->addDay());

        $ids = $this->boardIds(['designerId' => (string) $this->designer->id, 'period' => 'last_month']);

        $this->assertNotContains($thisMonth->id, $ids);
        $this->assertNotContains($older->id, $ids);
    }

    public function test_designer_and_bd_kanbans_render_previous_month_printing_task(): void
    {
        $task = $this->makeTask('prepare_printing_file', now()->subMonthNoOverflow()->endOfMonth()->subHour());

        Livewire::actingAs($this->designer)->test(DesignerTaskKanban::class)
            ->assertSee($task->task_id)
            ->assertSee('Previous Month Task');

        Livewire::actingAs($this->bd)->test(BdTaskKanban::class)
            ->assertSee($task->task_id)
            ->assertSee('Previous Month Task');
    }
}
