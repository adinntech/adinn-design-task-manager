<?php

namespace Tests\Feature\Api;

use App\Models\DesignTask;
use App\Models\FileUpload;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Designer mobile API: EOD, rework guard, decline/split/swap requests. Scratch DB only. */
class MobileDesignerProgressApiTest extends TestCase
{
    use DatabaseTransactions;

    private User $bd;

    private User $designer;

    private User $other;

    protected function setUp(): void
    {
        parent::setUp();

        $db = (string) config('database.connections.'.config('database.default').'.database');
        $this->assertTrue(str_contains($db, 'test') || str_contains($db, 'scratch'), "Refusing to run against '{$db}'.");

        Notification::fake();
        Storage::fake('spaces');

        $this->bd = $this->makeUser('bd');
        $this->designer = $this->makeUser('designer');
        $this->other = $this->makeUser('designer');
    }

    private function makeUser(string $role): User
    {
        static $n = 0;
        $n++;

        return User::create([
            'name' => ucfirst($role).' '.$n,
            'email' => "mdp{$role}{$n}@example.test",
            'password' => 'Password@123',
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function task(array $overrides = []): DesignTask
    {
        return DesignTask::create(array_merge([
            'task_id' => 'DT-TEST-'.uniqid(),
            'assigned_at' => now(),
            'assigned_by' => $this->bd->id,
            'task_name' => 'Progress task',
            'vertical' => 'roadshow',
            'task_nature' => 'new_creative_design',
            'party_type' => 'client',
            'party_name' => 'Acme',
            'contact_person' => 'Jo',
            'mobile_number' => '9898989898',
            'priority' => 'medium',
            'due_at' => now()->addDays(3),
            'designer_id' => $this->designer->id,
            'total_creatives' => 3,
            'status' => 'in_progress',
            'requirements' => [],
        ], $overrides));
    }

    private function upload(DesignTask $task, string $purpose, string $name = 'work.zip'): FileUpload
    {
        $key = '_uploads-staging/'.$purpose.'/'.uniqid().'.zip';
        Storage::disk('spaces')->put($key, 'x');

        return FileUpload::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->designer->id,
            'design_task_id' => $task->id,
            'purpose' => $purpose,
            'original_filename' => $name,
            'size_bytes' => 2048,
            'cloud_disk' => 'spaces',
            'cloud_key' => $key,
            'multipart_upload_id' => 'test',
            'part_size_bytes' => 64 * 1024 * 1024,
            'uploaded_bytes' => 2048,
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function test_eod_adds_progress_then_auto_moves_to_review_at_100(): void
    {
        $task = $this->task();
        $this->actingAs($this->designer, 'sanctum');

        $u1 = $this->upload($task, 'progress_update');
        $this->postJson("/api/v1/designer/tasks/{$task->id}/eod", ['completed_count' => 2, 'upload_ids' => (string) $u1->id])
            ->assertCreated()->assertJsonPath('auto_completed', false)->assertJsonPath('task.status', 'in_progress');

        $this->assertDatabaseHas('design_task_eod_records', ['design_task_id' => $task->id, 'completed_count' => 2, 'cumulative_completed' => 2, 'remaining_creatives' => 1]);
        $this->assertNotNull($u1->fresh()->consumed_at);

        // The consumed upload cannot be reused.
        $this->postJson("/api/v1/designer/tasks/{$task->id}/eod", ['completed_count' => 1, 'upload_ids' => (string) $u1->id])->assertStatus(422);

        $u2 = $this->upload($task, 'progress_update');
        $this->postJson("/api/v1/designer/tasks/{$task->id}/eod", ['completed_count' => 1, 'upload_ids' => (string) $u2->id])
            ->assertCreated()->assertJsonPath('auto_completed', true)->assertJsonPath('task.status', 'waiting_confirmation');
    }

    public function test_eod_rejects_more_than_remaining_and_wrong_status_and_foreign_uploads(): void
    {
        $task = $this->task();
        $this->actingAs($this->designer, 'sanctum');

        $u = $this->upload($task, 'progress_update');
        $this->postJson("/api/v1/designer/tasks/{$task->id}/eod", ['completed_count' => 9, 'upload_ids' => (string) $u->id])
            ->assertStatus(422)->assertJsonValidationErrors('completed_count');

        $this->postJson("/api/v1/designer/tasks/{$task->id}/eod", ['completed_count' => 1, 'upload_ids' => '999999'])
            ->assertStatus(422)->assertJsonValidationErrors('upload_ids');

        $idle = $this->task(['status' => 'yet_to_start']);
        $u2 = $this->upload($idle, 'progress_update');
        $this->postJson("/api/v1/designer/tasks/{$idle->id}/eod", ['completed_count' => 1, 'upload_ids' => (string) $u2->id])->assertStatus(422);

        $this->actingAs($this->other, 'sanctum')
            ->postJson("/api/v1/designer/tasks/{$task->id}/eod", ['completed_count' => 1, 'upload_ids' => (string) $u->id])->assertForbidden();
    }

    public function test_rework_only_allowed_in_rework_status(): void
    {
        $task = $this->task();
        $u = $this->upload($task, 'rework');

        $this->actingAs($this->designer, 'sanctum')
            ->postJson("/api/v1/designer/tasks/{$task->id}/rework", ['completed_count' => 1, 'upload_ids' => (string) $u->id])
            ->assertStatus(422)->assertJsonValidationErrors('completed_count');
    }

    public function test_request_options_follow_status(): void
    {
        $review = $this->task(['status' => 'review_analysis']);
        $progress = $this->task();
        $this->actingAs($this->designer, 'sanctum');

        $this->getJson("/api/v1/designer/tasks/{$review->id}/request-options")
            ->assertOk()->assertJsonPath('allowed_types', ['decline', 'split', 'swap']);
        $this->getJson("/api/v1/designer/tasks/{$progress->id}/request-options")
            ->assertOk()->assertJsonPath('allowed_types', ['split', 'swap']);
    }

    public function test_decline_split_and_swap_requests(): void
    {
        $this->actingAs($this->designer, 'sanctum');

        $decline = $this->task(['status' => 'review_analysis']);
        $this->postJson("/api/v1/designer/tasks/{$decline->id}/requests", ['type' => 'decline', 'reason' => 'Overloaded'])
            ->assertCreated()->assertJsonPath('request.overall_status', 'pending_approval');

        // Decline is not available once work is in progress.
        $busy = $this->task();
        $this->postJson("/api/v1/designer/tasks/{$busy->id}/requests", ['type' => 'decline', 'reason' => 'x'])->assertStatus(422);

        $this->postJson("/api/v1/designer/tasks/{$busy->id}/requests", ['type' => 'split', 'reason' => 'Too big'])->assertStatus(422)
            ->assertJsonValidationErrors(['creative_count', 'details']);

        $this->postJson("/api/v1/designer/tasks/{$busy->id}/requests", [
            'type' => 'split', 'reason' => 'Too big', 'creative_count' => 1, 'details' => 'Give me 1', 'target_designer_id' => $this->other->id,
        ])->assertCreated();

        $swap = $this->task(['status' => 'yet_to_start']);
        $this->postJson("/api/v1/designer/tasks/{$swap->id}/requests", ['type' => 'swap', 'reason' => 'Leave'])
            ->assertStatus(422)->assertJsonValidationErrors('target_designer_id');
        $this->postJson("/api/v1/designer/tasks/{$swap->id}/requests", ['type' => 'swap', 'reason' => 'Leave', 'target_designer_id' => $this->other->id])
            ->assertCreated();
        $this->assertSame('swap_tasks', $swap->fresh()->status);
    }

    public function test_attachments_are_stored_with_a_request(): void
    {
        $task = $this->task(['status' => 'review_analysis']);

        $this->actingAs($this->designer, 'sanctum')
            ->post("/api/v1/designer/tasks/{$task->id}/requests", [
                'type' => 'decline', 'reason' => 'See attached',
                'attachments' => [UploadedFile::fake()->create('proof.pdf', 10)],
            ], ['Accept' => 'application/json'])
            ->assertCreated();

        $paths = \App\Models\DesignTaskRequest::where('design_task_id', $task->id)->first()->attachments;
        $this->assertCount(1, $paths);
        Storage::disk('spaces')->assertExists($paths[0]);
    }

    public function test_other_designers_cannot_raise_requests(): void
    {
        $task = $this->task(['status' => 'review_analysis']);

        $this->actingAs($this->other, 'sanctum')
            ->postJson("/api/v1/designer/tasks/{$task->id}/requests", ['type' => 'decline', 'reason' => 'x'])->assertForbidden();
    }
}
