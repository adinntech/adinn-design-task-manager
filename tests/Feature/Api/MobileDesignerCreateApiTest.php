<?php

namespace Tests\Feature\Api;

use App\Models\DesignTask;
use App\Models\DesignTaskStatusHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** A Designer creating a task through the API: the web Designer controller's rules, the task waits for the chosen BD. Scratch DB only. */
class MobileDesignerCreateApiTest extends TestCase
{
    use DatabaseTransactions;

    private User $designer;

    private User $bd;

    protected function setUp(): void
    {
        parent::setUp();

        $db = (string) config('database.connections.'.config('database.default').'.database');
        $this->assertTrue(str_contains($db, 'test') || str_contains($db, 'scratch'), "Refusing to run against '{$db}'.");

        Notification::fake();
        Storage::fake('spaces');

        $this->designer = $this->makeUser('designer');
        $this->bd = $this->makeUser('bd');
    }

    private function makeUser(string $role): User
    {
        static $n = 0;
        $n++;

        return User::create(['name' => ucfirst($role).' '.$n, 'email' => "mdc{$role}{$n}@example.test", 'password' => 'Password@123', 'role' => $role, 'is_active' => true]);
    }

    private function payload(array $o = []): array
    {
        return array_merge([
            'task_name' => 'Station hoarding',
            'vertical' => 'outdoor',
            'task_nature' => 'creative_adaptation',
            'party_type' => 'client',
            'party_name' => 'Acme Foods',
            'priority' => 'high',
            'due_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'bd_id' => $this->bd->id,
            'total_creatives' => 2,
            'description' => 'Adapt the festive creative',
            'outdoor_type' => 'Unipole',
            'board_type' => 'Static',
            'dimension_rows' => [['name' => 'Board A', 'width' => 40, 'height' => 20]],
        ], $o);
    }

    public function test_the_form_lists_the_bds_to_pick_from_not_designers_and_is_designer_only(): void
    {
        $this->actingAs($this->bd, 'sanctum')->getJson('/api/v1/designer/task-form')->assertForbidden();

        $form = $this->actingAs($this->designer, 'sanctum')->getJson('/api/v1/designer/task-form')->assertOk()->json();

        $this->assertContains($this->bd->id, array_column($form['bds'], 'id'));
        $this->assertArrayNotHasKey('designers', $form);
        $this->assertContains('outdoor', array_column($form['verticals'], 'key'));
        $this->assertNotEmpty($form['web_forms']['outdoor']['natures']);
        $this->assertNotEmpty($form['inhouse_natures']);
    }

    public function test_creates_a_client_task_that_waits_for_the_chosen_bd(): void
    {
        $res = $this->actingAs($this->designer, 'sanctum')->postJson('/api/v1/designer/tasks', $this->payload())->assertCreated();

        $task = DesignTask::findOrFail($res->json('id'));
        $this->assertSame('pending_bd_approval', $task->status);
        $this->assertSame($this->designer->id, (int) $task->designer_id);
        $this->assertSame($this->bd->id, (int) $task->assigned_by);
        $this->assertSame('Adapt the festive creative', $task->requirements['description']);
        $this->assertStringContainsString('Waiting for BD', (string) $res->json('message'));

        $history = DesignTaskStatusHistory::where('design_task_id', $task->id)->first();
        $this->assertSame('designer_task_created', $history->change_source);
    }

    public function test_the_same_validation_as_the_web_form_applies(): void
    {
        $api = $this->actingAs($this->designer, 'sanctum');

        $api->postJson('/api/v1/designer/tasks', $this->payload(['bd_id' => null]))->assertUnprocessable()->assertJsonValidationErrors('bd_id');
        // The "BD" must really be an active BD, not another designer.
        $api->postJson('/api/v1/designer/tasks', $this->payload(['bd_id' => $this->makeUser('designer')->id]))->assertUnprocessable()->assertJsonValidationErrors('bd_id');
        $api->postJson('/api/v1/designer/tasks', $this->payload(['due_at' => now()->subDays(2)->format('Y-m-d H:i:s')]))->assertUnprocessable()->assertJsonValidationErrors('due_at');
        $api->postJson('/api/v1/designer/tasks', $this->payload(['mobile_number' => '123']))->assertUnprocessable()->assertJsonValidationErrors('mobile_number');
    }

    public function test_creates_an_in_house_task_for_the_designer_that_waits_for_the_bd(): void
    {
        $natures = \App\Http\Controllers\Bd\TaskController::inhouseNatures();
        $vertical = array_key_first($natures);

        $res = $this->actingAs($this->designer, 'sanctum')->postJson('/api/v1/designer/tasks/in-house', [
            'task_name' => 'Office poster',
            'vertical' => $vertical,
            'task_nature' => $natures[$vertical][0],
            'bd_id' => $this->bd->id,
            'total_creatives' => 1,
            'description' => 'For reception',
        ])->assertCreated();

        $task = DesignTask::findOrFail($res->json('id'));
        $this->assertSame('in_house', $task->task_category);
        $this->assertSame('pending_bd_approval', $task->status);
        $this->assertSame($this->designer->id, (int) $task->designer_id);
        $this->assertSame($this->bd->id, (int) $task->assigned_by);
    }

    public function test_only_designers_can_use_the_designer_creation_routes(): void
    {
        $this->actingAs($this->bd, 'sanctum')->postJson('/api/v1/designer/tasks', $this->payload())->assertForbidden();
        $this->actingAs($this->bd, 'sanctum')->postJson('/api/v1/designer/tasks/in-house', [])->assertForbidden();
    }

    public function test_signed_out_callers_are_refused(): void
    {
        $this->postJson('/api/v1/designer/tasks', $this->payload())->assertUnauthorized();
        $this->getJson('/api/v1/designer/task-form')->assertUnauthorized();
    }
}
