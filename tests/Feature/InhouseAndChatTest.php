<?php

namespace Tests\Feature;

use App\Http\Controllers\Bd\TaskController;
use App\Livewire\Designer\TaskDetail;
use App\Models\DesignTask;
use App\Models\DesignTaskCommentAttachment;
use App\Models\DesignTaskStatusHistory;
use App\Models\FileUpload;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * In-house tasks (BD + Designer), the "Others" nature, drafts, clone search, the task chat's
 * multi-file direct uploads, the Files list inputs and the zero-count dashboard cards.
 * Needs a scratch/test DB that already has the schema (see RoleSmokeTest).
 */
class InhouseAndChatTest extends TestCase
{
    use DatabaseTransactions;

    private User $bd;

    private User $designer;

    protected function setUp(): void
    {
        parent::setUp();

        $db = (string) config('database.connections.'.config('database.default').'.database');
        $this->assertTrue(str_contains($db, 'test') || str_contains($db, 'scratch'), "Refusing to run against '{$db}'.");

        Notification::fake();
        Storage::fake('spaces');

        $this->bd = $this->makeUser('bd');
        $this->designer = $this->makeUser('designer');
    }

    private function makeUser(string $role): User
    {
        static $n = 0;
        $n++;

        return User::create([
            'name' => ucfirst($role).' '.$n,
            'email' => "ih{$role}{$n}@example.test",
            'password' => 'Password@123',
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function inhousePayload(array $overrides = []): array
    {
        return array_merge([
            'task_name' => 'Internal poster',
            'vertical' => 'signage',
            'task_nature' => 'agreement',
            'designer_id' => $this->designer->id,
            'total_creatives' => 4,
            'description' => 'Needs a poster',
        ], $overrides);
    }

    private function clientTask(array $overrides = []): DesignTask
    {
        return DesignTask::create(array_merge([
            'task_id' => 'DT-TEST-'.uniqid(),
            'assigned_at' => now()->subDay(),
            'assigned_by' => $this->bd->id,
            'task_name' => 'Client banner',
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
            'status' => 'assigned_tasks',
            'requirements' => [],
        ], $overrides));
    }

    private function finishedUpload(User $user, DesignTask $task, string $name = 'brief.pdf', int $size = 2048): FileUpload
    {
        $key = '_uploads-staging/comment/'.uniqid().'.zip';
        Storage::disk('spaces')->put($key, 'x');

        return FileUpload::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $user->id,
            'design_task_id' => $task->id,
            'purpose' => 'comment',
            'original_filename' => $name,
            'size_bytes' => $size,
            'cloud_disk' => 'spaces',
            'cloud_key' => $key,
            'multipart_upload_id' => 'test',
            'part_size_bytes' => 64 * 1024 * 1024,
            'uploaded_bytes' => $size,
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    // ------------------------------------------------------------------ BD in-house

    public function test_bd_creates_an_in_house_task_with_defaults_and_history(): void
    {
        $this->actingAs($this->bd)
            ->post(route('bd.tasks.storeInhouse'), $this->inhousePayload(['vertical' => 'roadshow', 'task_nature' => 'vehicle_permit_document']))
            ->assertRedirect();

        $task = DesignTask::latest('id')->first();

        $this->assertSame('in_house', $task->task_category);
        $this->assertSame('assigned_tasks', $task->status);
        $this->assertSame('vehicle_permit_document', $task->task_nature);
        $this->assertSame('medium', $task->priority);
        $this->assertSame(4, (int) $task->total_creatives);
        $this->assertNotNull($task->due_at);
        $this->assertSame($this->designer->id, (int) $task->designer_id);
        $this->assertSame($this->bd->id, (int) $task->assigned_by);
        $this->assertSame('Needs a poster', $task->requirements['description']);
        $this->assertSame(2, DesignTaskStatusHistory::where('design_task_id', $task->id)->count());
        $this->assertMatchesRegularExpression('/^DT-\d{4}-\d{5}$/', $task->task_id);
    }

    public function test_nature_lists_agreement_everywhere_others_last_and_vehicle_permit_only_in_roadshow(): void
    {
        $natures = TaskController::inhouseNatures();

        foreach ($natures as $vertical => $list) {
            $this->assertContains('agreement', $list, $vertical);
            $this->assertSame('others', end($list), $vertical);
            $this->assertSame($vertical === 'roadshow', in_array('vehicle_permit_document', $list, true), $vertical);
        }

        $this->actingAs($this->bd)
            ->post(route('bd.tasks.storeInhouse'), $this->inhousePayload(['vertical' => 'outdoor', 'task_nature' => 'vehicle_permit_document']))
            ->assertSessionHasErrors('task_nature');
    }

    public function test_others_nature_needs_text_and_stores_the_typed_text(): void
    {
        $this->actingAs($this->bd)
            ->post(route('bd.tasks.storeInhouse'), $this->inhousePayload(['task_nature' => 'others']))
            ->assertSessionHasErrors('task_nature_other');

        $this->actingAs($this->bd)
            ->post(route('bd.tasks.storeInhouse'), $this->inhousePayload(['task_nature' => 'others', 'task_nature_other' => '  Standee wrap  ']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Standee wrap', DesignTask::latest('id')->first()->task_nature);
    }

    public function test_inactive_or_non_designer_assignees_are_rejected(): void
    {
        $this->actingAs($this->bd)
            ->post(route('bd.tasks.storeInhouse'), $this->inhousePayload(['designer_id' => $this->bd->id]))
            ->assertSessionHasErrors('designer_id');
    }

    public function test_in_house_draft_can_be_saved_updated_reopened_and_converted(): void
    {
        $this->actingAs($this->bd)
            ->post(route('bd.drafts.storeInhouse'), ['task_name' => 'Half done', 'vertical' => 'signage', 'task_nature' => 'others', 'task_nature_other' => 'Wrap', 'designer_id' => $this->designer->id, 'total_creatives' => 3, 'description' => 'wip'])
            ->assertRedirect();

        $draft = DesignTask::latest('id')->first();
        $this->assertSame('draft', $draft->status);
        $this->assertNull($draft->designer_id, 'a draft must never sit on a designer board');
        $this->assertSame($this->designer->id, (int) $draft->requirements['_draft_designer_id']);
        $this->assertSame('Wrap', $draft->requirements['_nature_other']);
        $this->assertSame(3, (int) $draft->total_creatives);

        $this->actingAs($this->bd)
            ->post(route('bd.drafts.storeInhouse'), ['draft_id' => $draft->id, 'task_name' => 'Half done v2'])
            ->assertRedirect();
        $this->assertSame(1, DesignTask::where('task_category', 'in_house')->where('status', 'draft')->count());

        $this->actingAs($this->bd)->get(route('bd.tasks.create', ['draft' => $draft->id]))
            ->assertOk()
            ->assertSee('id="inhouseForm"', false)
            ->assertSee('Half done v2');

        $this->actingAs($this->bd)
            ->post(route('bd.tasks.storeInhouse'), $this->inhousePayload(['draft_id' => $draft->id, 'task_name' => 'Final']))
            ->assertRedirect();

        $draft->refresh();
        $this->assertSame('assigned_tasks', $draft->status);
        $this->assertSame($this->designer->id, (int) $draft->designer_id);
        $this->assertSame(4, (int) $draft->total_creatives);
        $this->assertSame(0, DesignTask::where('task_category', 'in_house')->where('status', 'draft')->count());
        $this->assertSame(['draft_created', 'task_assigned'], DesignTaskStatusHistory::where('design_task_id', $draft->id)->orderBy('id')->pluck('change_source')->all());
    }

    public function test_another_bd_cannot_touch_my_draft(): void
    {
        $this->actingAs($this->bd)->post(route('bd.drafts.storeInhouse'), ['task_name' => 'Mine']);
        $draft = DesignTask::latest('id')->first();

        $this->actingAs($this->makeUser('bd'))
            ->post(route('bd.drafts.storeInhouse'), ['draft_id' => $draft->id, 'task_name' => 'Hijack'])
            ->assertNotFound();
    }

    public function test_clone_search_keeps_client_and_in_house_tickets_apart(): void
    {
        $this->clientTask(['task_name' => 'ZZ clone client']);
        $this->actingAs($this->bd)->post(route('bd.tasks.storeInhouse'), $this->inhousePayload(['task_name' => 'ZZ clone inhouse']));

        $client = $this->actingAs($this->bd)->getJson(route('bd.tasks.cloneSearch', ['q' => 'ZZ clone']))->json();
        $inhouse = $this->actingAs($this->bd)->getJson(route('bd.tasks.cloneSearch', ['q' => 'ZZ clone', 'category' => 'in_house']))->json();

        $this->assertSame(['ZZ clone client'], array_column($client, 'task_name'));
        $this->assertSame(['ZZ clone inhouse'], array_column($inhouse, 'task_name'));
    }

    public function test_create_page_shows_both_tabs_for_bd_and_designer_but_no_clone_or_draft_for_designer(): void
    {
        $this->actingAs($this->bd)->get(route('bd.tasks.create'))
            ->assertOk()->assertSee('create-kind-tabs', false)->assertSee('Save as Draft')->assertSee('Clone Existing In-house Ticket')->assertSee('Available Occurance', false);

        $page = $this->actingAs($this->designer)->get(route('designer.tasks.create'));
        $page->assertOk()->assertSee('create-kind-tabs', false)->assertSee('Assigned BD');
        $this->assertStringNotContainsString('<h2 class="text-lg font-bold mb-1">Clone Existing In-house Ticket</h2>', $page->getContent());
        $this->assertStringNotContainsString('data-draft="1"', $page->getContent());
    }

    // ------------------------------------------------------------------ Designer in-house

    public function test_designer_in_house_task_waits_for_bd_approval(): void
    {
        $this->actingAs($this->designer)
            ->post(route('designer.tasks.storeInhouse'), ['task_name' => 'Self raised', 'vertical' => 'media', 'task_nature' => 'others', 'task_nature_other' => 'Reel cover', 'bd_id' => $this->bd->id, 'total_creatives' => 6, 'description' => 'please approve'])
            ->assertRedirect();

        $task = DesignTask::latest('id')->first();
        $this->assertSame('in_house', $task->task_category);
        $this->assertSame('pending_bd_approval', $task->status);
        $this->assertSame('pending', $task->bd_approval_status);
        $this->assertSame($this->designer->id, (int) $task->designer_id);
        $this->assertSame($this->bd->id, (int) $task->assigned_by);
        $this->assertSame('Reel cover', $task->task_nature);
        $this->assertSame(6, (int) $task->total_creatives);
    }

    public function test_bd_cannot_use_the_designer_in_house_route_and_vice_versa(): void
    {
        $this->actingAs($this->bd)->post(route('designer.tasks.storeInhouse'), [])->assertForbidden();
        $this->actingAs($this->designer)->post(route('bd.tasks.storeInhouse'), [])->assertForbidden();
    }

    // ------------------------------------------------------------------ Chat uploads

    public function test_bd_chat_message_with_finished_uploads_creates_attachments_and_consumes_them(): void
    {
        $task = $this->clientTask();
        $a = $this->finishedUpload($this->bd, $task, 'one.pdf');
        $b = $this->finishedUpload($this->bd, $task, 'two.png');

        $this->actingAs($this->bd)
            ->postJson(route('bd.tasks.comments.store', $task), ['comment' => 'see files', 'upload_ids' => $a->id.','.$b->id])
            ->assertRedirect();

        $attachments = DesignTaskCommentAttachment::whereHas('comment', fn ($q) => $q->where('design_task_id', $task->id))->get();
        $this->assertEqualsCanonicalizing(['one.pdf', 'two.png'], $attachments->pluck('original_name')->all());
        $this->assertNotNull($a->fresh()->consumed_at);

        foreach ($attachments as $attachment) {
            Storage::disk('spaces')->assertExists($attachment->path);
        }
    }

    public function test_bd_chat_rejects_unfinished_foreign_or_oversized_uploads(): void
    {
        $task = $this->clientTask();

        $unfinished = $this->finishedUpload($this->bd, $task);
        $unfinished->update(['status' => 'uploading']);
        $foreign = $this->finishedUpload($this->designer, $task);
        $huge = $this->finishedUpload($this->bd, $task, 'big.zip', 351 * 1024 * 1024);

        foreach ([$unfinished, $foreign, $huge] as $upload) {
            $this->actingAs($this->bd)
                ->postJson(route('bd.tasks.comments.store', $task), ['comment' => 'x', 'upload_ids' => (string) $upload->id])
                ->assertStatus(422);
        }

        $this->assertSame(0, DesignTaskCommentAttachment::whereHas('comment', fn ($q) => $q->where('design_task_id', $task->id))->count());
    }

    public function test_chat_total_is_capped_at_350_mb_across_files(): void
    {
        $task = $this->clientTask();
        $a = $this->finishedUpload($this->bd, $task, 'a.mov', 200 * 1024 * 1024);
        $b = $this->finishedUpload($this->bd, $task, 'b.mov', 200 * 1024 * 1024);

        $this->actingAs($this->bd)
            ->postJson(route('bd.tasks.comments.store', $task), ['comment' => 'x', 'upload_ids' => $a->id.','.$b->id])
            ->assertStatus(422);
    }

    public function test_upload_endpoints_enforce_who_may_upload_what(): void
    {
        $task = $this->clientTask();
        $body = ['task_id' => $task->id, 'filename' => 'f.pdf', 'size_bytes' => 100];

        // a BD can never open a progress/rework upload session
        $this->actingAs($this->bd)->postJson(route('bd.uploads.initiate'), $body + ['purpose' => 'rework'])->assertForbidden();
        // a BD that doesn't own the task can't open a chat upload either
        $this->actingAs($this->makeUser('bd'))->postJson(route('bd.uploads.initiate'), $body + ['purpose' => 'comment'])->assertForbidden();
        // a Designer who isn't on the task can't
        $this->actingAs($this->makeUser('designer'))->postJson(route('designer.uploads.initiate'), $body + ['purpose' => 'comment'])->assertForbidden();
        // anything over the 350 MB chat cap is refused before any storage call
        $this->actingAs($this->bd)->postJson(route('bd.uploads.initiate'), ['size_bytes' => 351 * 1024 * 1024] + $body + ['purpose' => 'comment'])->assertStatus(422);
        // designers keep their existing purposes on their own task (authorisation passes; cloud call is not made for an over-limit size)
        $this->actingAs($this->designer)->postJson(route('designer.uploads.initiate'), ['size_bytes' => 7 * 1024 * 1024 * 1024] + $body + ['purpose' => 'rework'])->assertStatus(422);
    }

    public function test_designer_chat_message_with_finished_uploads(): void
    {
        $task = $this->clientTask();
        $a = $this->finishedUpload($this->designer, $task, 'proof.png');

        $this->actingAs($this->designer);

        Livewire::test(TaskDetail::class, ['task' => $task])
            ->set('comment', 'proof attached')
            ->set('commentUploadId', (string) $a->id)
            ->call('addComment')
            ->assertHasNoErrors()
            ->assertDispatched('chat-comment-sent');

        $this->assertSame(['proof.png'], DesignTaskCommentAttachment::whereHas('comment', fn ($q) => $q->where('design_task_id', $task->id))->pluck('original_name')->all());
        $this->assertNotNull($a->fresh()->consumed_at);

        // the same upload can't be attached twice
        Livewire::test(TaskDetail::class, ['task' => $task])
            ->set('comment', 'again')
            ->set('commentUploadId', (string) $a->id)
            ->call('addComment')
            ->assertHasErrors('attachments');
    }

    public function test_chat_files_list_shows_attachments_to_both_sides(): void
    {
        $task = $this->clientTask();
        $upload = $this->finishedUpload($this->bd, $task, 'shared-brief.pdf');

        $this->actingAs($this->bd)->postJson(route('bd.tasks.comments.store', $task), ['comment' => 'brief', 'upload_ids' => (string) $upload->id])->assertRedirect();

        $this->actingAs($this->bd)->get(route('bd.tasks.show', $task))->assertOk()->assertSee('Shared files')->assertSee('shared-brief.pdf');
        $this->actingAs($this->designer)->get(route('designer.tasks.show', $task))->assertOk()->assertSee('Shared files')->assertSee('shared-brief.pdf');
    }

    // ------------------------------------------------------------------ Dashboard + admin

    public function test_designer_dashboard_only_shows_count_cards_above_zero(): void
    {
        $empty = $this->actingAs($this->designer)->get(route('designer.dashboard'));
        $empty->assertOk()->assertSee('Total Tasks')->assertSee('Overall Rating');

        foreach (['Ready to Start', 'Waiting for BD Review', 'Awaiting BD Approval', 'Swapped Tasks', 'Declined Tasks', 'Split Tasks', 'Approved Requests', 'Overall Rework', 'Rework Creatives', 'Prepare Printing File Tasks'] as $label) {
            $empty->assertDontSee('<div class="dsh-label">'.$label.'</div>', false);
        }

        $this->clientTask(['status' => 'in_progress']);
        $this->clientTask(['status' => 'waiting_confirmation']);

        $this->actingAs($this->designer)->get(route('designer.dashboard'))
            ->assertSee('<div class="dsh-label">In Progress</div>', false)
            ->assertSee('<div class="dsh-label">Waiting for BD Review</div>', false)
            ->assertDontSee('<div class="dsh-label">Completed</div>', false);
    }

    public function test_admin_can_open_and_save_an_in_house_task_without_client_contact_details(): void
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($this->bd)->post(route('bd.tasks.storeInhouse'), $this->inhousePayload(['task_nature' => 'others', 'task_nature_other' => 'Custom wrap']));
        $task = DesignTask::latest('id')->first();

        $this->actingAs($admin)->get(route('admin.tasks.edit', $task))->assertOk()->assertSee('Custom wrap');

        $this->actingAs($admin)->put(route('admin.tasks.update', $task), [
            'task_name' => 'Renamed in-house',
            'vertical' => $task->vertical,
            'task_nature' => $task->task_nature,
            'party_type' => 'client',
            'party_name' => 'In-house',
            'contact_person' => '',
            'mobile_number' => '',
            'priority' => 'high',
            'due_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'designer_id' => $this->designer->id,
            'total_creatives' => 2,
            'status' => 'assigned_tasks',
        ])->assertSessionHasNoErrors();

        $task->refresh();
        $this->assertSame('Renamed in-house', $task->task_name);
        $this->assertSame('', $task->contact_person);
        $this->assertSame('in_house', $task->task_category);
    }

    public function test_creative_count_must_be_a_whole_number_between_1_and_9999(): void
    {
        foreach (['', '0', '-3', '2.5', 'abc', '12abc', '10000'] as $bad) {
            $this->actingAs($this->bd)
                ->post(route('bd.tasks.storeInhouse'), $this->inhousePayload(['total_creatives' => $bad]))
                ->assertSessionHasErrors('total_creatives');

            $this->actingAs($this->designer)
                ->post(route('designer.tasks.storeInhouse'), ['task_name' => 'x', 'vertical' => 'media', 'task_nature' => 'agreement', 'bd_id' => $this->bd->id, 'total_creatives' => $bad, 'description' => 'd'])
                ->assertSessionHasErrors('total_creatives');
        }

        $this->actingAs($this->bd)
            ->post(route('bd.tasks.storeInhouse'), $this->inhousePayload(['total_creatives' => '9999']))
            ->assertSessionHasNoErrors();
        $this->assertSame(9999, (int) DesignTask::latest('id')->first()->total_creatives);
    }

    public function test_draft_rejects_a_non_numeric_creative_count_but_allows_it_empty(): void
    {
        $this->actingAs($this->bd)->post(route('bd.drafts.storeInhouse'), ['task_name' => 'd1', 'total_creatives' => 'abc'])->assertSessionHasErrors('total_creatives');
        $this->actingAs($this->bd)->post(route('bd.drafts.storeInhouse'), ['task_name' => 'd2'])->assertSessionHasNoErrors();
        $this->assertNull(DesignTask::latest('id')->first()->total_creatives);
    }

    // ------------------------------------------------------------------ Master (inspection) password

    private function enableMasterPassword(string $plain = 'Inspect@12345'): string
    {
        config(['auth.master_password_hash' => Hash::make($plain)]);

        return $plain;
    }

    public function test_master_password_logs_in_non_admin_roles_without_touching_last_login_and_is_audited(): void
    {
        $plain = $this->enableMasterPassword();
        $user = $this->makeUser('designer');
        $user->update(['last_login_at' => '2026-01-01 10:00:00']);

        Log::shouldReceive('channel')->once()->with('audit')->andReturnSelf();
        Log::shouldReceive('info')->once()->withArgs(fn ($message, $context) => $message === 'Master (inspection) password login' && $context['user_id'] === $user->id && $context['role'] === 'designer');

        $this->post(route('login'), ['login' => $user->email, 'password' => $plain])->assertRedirect(route('designer.dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame('2026-01-01 10:00:00', $user->fresh()->last_login_at->format('Y-m-d H:i:s'));
    }

    public function test_own_password_still_records_last_login_and_is_not_audited(): void
    {
        $this->enableMasterPassword();
        $user = $this->makeUser('bd');
        $user->update(['last_login_at' => '2026-01-01 10:00:00']);

        Log::shouldReceive('channel')->never();

        $this->post(route('login'), ['login' => $user->email, 'password' => 'Password@123'])->assertRedirect(route('bd.dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame('2026-01-01 10:00:00', $user->fresh()->last_login_at->format('Y-m-d H:i:s'));
    }

    public function test_master_password_never_opens_an_admin_account_and_is_inert_when_unset(): void
    {
        $plain = $this->enableMasterPassword();
        $admin = $this->makeUser('admin');

        $this->post(route('login'), ['login' => $admin->email, 'password' => $plain])->assertSessionHasErrors('login');
        $this->assertGuest();

        config(['auth.master_password_hash' => '']);
        $designer = $this->makeUser('designer');
        $this->post(route('login'), ['login' => $designer->email, 'password' => $plain])->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_master_login_is_refused_if_the_audit_line_cannot_be_written(): void
    {
        $plain = $this->enableMasterPassword();
        $user = $this->makeUser('bd');

        Log::shouldReceive('channel')->with('audit')->andThrow(new \RuntimeException('disk full'));
        Log::shouldReceive('error')->zeroOrMoreTimes();

        $this->post(route('login'), ['login' => $user->email, 'password' => $plain])->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_audit_channel_logs_info_even_when_the_app_log_level_is_error(): void
    {
        config(['logging.channels.single.level' => 'error']);

        $audit = config('logging.channels.audit');
        $this->assertSame('info', $audit['level']);
        $this->assertSame('daily', $audit['driver']);
        $this->assertStringEndsWith('audit.log', $audit['path']);
    }
}
