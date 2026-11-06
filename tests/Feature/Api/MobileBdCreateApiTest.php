<?php

namespace Tests\Feature\Api;

use App\Models\ClientDetail;
use App\Models\DesignTask;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** BD task creation through the API reuses the web controller's rules. Scratch DB only. */
class MobileBdCreateApiTest extends TestCase
{
    use DatabaseTransactions;

    private User $bd;

    private User $otherBd;

    private User $designer;

    protected function setUp(): void
    {
        parent::setUp();

        $db = (string) config('database.connections.'.config('database.default').'.database');
        $this->assertTrue(str_contains($db, 'test') || str_contains($db, 'scratch'), "Refusing to run against '{$db}'.");

        Notification::fake();
        Storage::fake('spaces');

        $this->bd = $this->makeUser('bd');
        $this->otherBd = $this->makeUser('bd');
        $this->designer = $this->makeUser('designer');
    }

    private function makeUser(string $role): User
    {
        static $n = 0;
        $n++;

        return User::create([
            'name' => ucfirst($role).' '.$n, 'email' => "mbc{$role}{$n}@example.test", 'password' => 'Password@123',
            'role' => $role, 'is_active' => true,
        ]);
    }

    private function payload(array $o = []): array
    {
        return array_merge([
            'task_name' => 'Highway hoarding',
            'vertical' => 'outdoor',
            'task_nature' => 'creative_adaptation',
            'party_type' => 'client',
            'party_name' => 'Acme Foods',
            'contact_person' => 'Jo Mehta',
            'mobile_number' => '9876543210',
            'priority' => 'high',
            'due_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'designer_id' => $this->designer->id,
            'total_creatives' => 3,
            'description' => 'Adapt the festive creative',
            'outdoor_type' => 'Unipole',
            'board_type' => 'Static',
            'dimension_rows' => [['name' => 'Board A', 'width' => 40, 'height' => 20]],
        ], $o);
    }

    private function fieldsOf(array $form, string $vertical, string $nature): array
    {
        return collect($form['fields'][$vertical][$nature])->keyBy('key')->all();
    }

    // ---------------------------------------------------------------- form definition

    public function test_form_is_bd_only_and_lists_verticals_natures_designers(): void
    {
        $this->actingAs($this->designer, 'sanctum')->getJson('/api/v1/bd/task-form')->assertForbidden();

        $form = $this->actingAs($this->bd, 'sanctum')->getJson('/api/v1/bd/task-form')->assertOk()->json();

        $this->assertContains('outdoor', array_column($form['verticals'], 'key'));
        $this->assertContains('creative_adaptation', array_column($form['natures']['outdoor'], 'key'));
        $this->assertContains($this->designer->id, array_column($form['designers'], 'id'));
        $this->assertNotEmpty($form['inhouse_natures']);
        $this->assertSame(['low', 'medium', 'high', 'urgent'], $form['priorities']);
        $this->assertNotNull($form['max_due_at']);
    }

    public function test_form_fields_come_from_the_same_rules_the_server_enforces(): void
    {
        $form = $this->actingAs($this->bd, 'sanctum')->getJson('/api/v1/bd/task-form')->json();

        $cutout = $this->fieldsOf($form, 'outdoor', 'cutout_size_calculation');
        $this->assertTrue($cutout['description']['required']);
        $this->assertSame('file', $cutout['hoarding_artwork']['type']);
        $this->assertTrue($cutout['hoarding_artwork']['required']);
        $this->assertSame('choice', $cutout['outdoor_type']['type']);
        $this->assertContains('Unipole', $cutout['outdoor_type']['options']);
        $this->assertSame('rows', $cutout['dimension_rows']['type']);
        $this->assertSame(['name', 'width', 'height'], $cutout['dimension_rows']['columns']);
        $this->assertSame('dimension_upload', $cutout['dimension_rows']['or_upload']);

        // board_type is only required for the three "creative" outdoor natures.
        $this->assertArrayNotHasKey('board_type', $cutout);
        $this->assertTrue($this->fieldsOf($form, 'outdoor', 'creative_adaptation')['board_type']['required']);

        // Road Show: a written OR uploaded description.
        $road = $this->fieldsOf($form, 'roadshow', 'new_creative_design');
        $this->assertFalse($road['description']['required']);
        $this->assertSame('description_upload', $road['description']['either']);
        $this->assertSame('choice', $road['vehicle_type']['type']);

        // Row editors only where the rules ask for them.
        $this->assertSame('rows', $this->fieldsOf($form, 'media', 'own_creative')['media_size_rows']['type']);
        $this->assertSame('rows', $this->fieldsOf($form, 'pop_offsets', 'creative_design')['size_rows']['type']);
        $this->assertArrayNotHasKey('size_rows', $this->fieldsOf($form, 'pop_offsets', 'mockup_design'));
        $this->assertArrayNotHasKey('dimension_rows', $this->fieldsOf($form, 'signage', 'mockup'));

        // Optional free text is offered everywhere without being required.
        $this->assertFalse($this->fieldsOf($form, 'signage', 'mockup')['other_details']['required']);
    }

    public function test_every_vertical_and_nature_has_a_field_list(): void
    {
        $form = $this->actingAs($this->bd, 'sanctum')->getJson('/api/v1/bd/task-form')->json();

        foreach ($form['natures'] as $vertical => $natures) {
            foreach ($natures as $nature) {
                $this->assertNotEmpty($form['fields'][$vertical][$nature['key']], "{$vertical}.{$nature['key']} has no fields");
            }
        }
    }

    // ---------------------------------------------------------------- create

    public function test_creates_a_client_task_from_json(): void
    {
        $res = $this->actingAs($this->bd, 'sanctum')->postJson('/api/v1/bd/tasks', $this->payload())->assertCreated();

        $task = DesignTask::findOrFail($res->json('id'));
        $this->assertSame('assigned_tasks', $task->status);
        $this->assertSame($this->bd->id, (int) $task->assigned_by);
        $this->assertSame($this->designer->id, (int) $task->designer_id);
        $this->assertSame(sprintf('DT-%s-%05d', now()->format('Y'), $task->id), $task->task_id);
        $this->assertSame('Adapt the festive creative', $task->requirements['description']);
        $this->assertSame('Board A', $task->requirements['board_details'][0]['name']);

        $this->assertDatabaseHas('design_task_status_histories', ['design_task_id' => $task->id, 'change_source' => 'task_created']);
        $this->assertDatabaseHas('design_task_status_histories', ['design_task_id' => $task->id, 'change_source' => 'task_assigned']);
        Notification::assertSentTo($this->designer, \App\Notifications\TaskAssignedNotification::class);
        $this->assertNotNull(ClientDetail::where('client_name', 'Acme Foods')->first());
    }

    public function test_server_rules_are_enforced_with_json_errors(): void
    {
        $this->actingAs($this->bd, 'sanctum');

        $this->postJson('/api/v1/bd/tasks', [])->assertStatus(422)->assertJsonValidationErrors(['task_name', 'vertical', 'designer_id']);

        $this->postJson('/api/v1/bd/tasks', $this->payload(['description' => '']))->assertStatus(422)->assertJsonValidationErrors('description');
        $this->postJson('/api/v1/bd/tasks', $this->payload(['mobile_number' => '123']))->assertStatus(422)->assertJsonValidationErrors('mobile_number');
        $this->postJson('/api/v1/bd/tasks', $this->payload(['due_at' => now()->subDays(2)->format('Y-m-d H:i:s')]))->assertStatus(422)->assertJsonValidationErrors('due_at');
        $this->postJson('/api/v1/bd/tasks', $this->payload(['due_at' => now()->addDays(40)->format('Y-m-d H:i:s')]))->assertStatus(422)->assertJsonValidationErrors('due_at');
        $this->postJson('/api/v1/bd/tasks', $this->payload(['designer_id' => 999999]))->assertStatus(422)->assertJsonValidationErrors('designer_id');
        $this->postJson('/api/v1/bd/tasks', $this->payload(['task_nature' => 'mockup']))->assertStatus(422)->assertJsonValidationErrors('task_nature');
        $this->postJson('/api/v1/bd/tasks', $this->payload(['dimension_rows' => [['name' => 'Half', 'width' => 10]]]))->assertStatus(422)->assertJsonValidationErrors('dimension_rows');

        $this->assertSame(0, DesignTask::where('assigned_by', $this->bd->id)->count());
    }

    public function test_a_required_file_is_accepted_as_multipart_and_stored(): void
    {
        $this->actingAs($this->bd, 'sanctum');

        $this->postJson('/api/v1/bd/tasks', $this->payload(['task_nature' => 'cutout_size_calculation', 'board_type' => null]))
            ->assertStatus(422)->assertJsonValidationErrors('hoarding_artwork');

        $res = $this->post('/api/v1/bd/tasks', $this->payload([
            'task_nature' => 'cutout_size_calculation',
            'dimension_rows' => [['name' => 'Board A', 'width' => 40, 'height' => 20]],
            'hoarding_artwork' => [UploadedFile::fake()->create('artwork.pdf', 20, 'application/pdf')],
        ]), ['Accept' => 'application/json'])->assertCreated();

        $paths = DesignTask::findOrFail($res->json('id'))->requirements['hoarding_artwork'];
        $this->assertCount(1, $paths);
        Storage::disk('spaces')->assertExists($paths[0]);
    }

    public function test_creates_an_in_house_task(): void
    {
        $res = $this->actingAs($this->bd, 'sanctum')->postJson('/api/v1/bd/tasks/in-house', [
            'task_name' => 'Office poster', 'vertical' => 'signage', 'task_nature' => 'agreement',
            'designer_id' => $this->designer->id, 'total_creatives' => 2, 'description' => 'Internal use',
        ])->assertCreated();

        $task = DesignTask::findOrFail($res->json('id'));
        $this->assertSame('in_house', $task->task_category);
        $this->assertSame('assigned_tasks', $task->status);

        $this->actingAs($this->bd, 'sanctum')->postJson('/api/v1/bd/tasks/in-house', ['task_name' => 'x'])
            ->assertStatus(422)->assertJsonValidationErrors(['vertical', 'designer_id', 'description']);
    }

    public function test_only_bd_can_create(): void
    {
        $this->actingAs($this->designer, 'sanctum')->postJson('/api/v1/bd/tasks', $this->payload())->assertForbidden();
        $this->actingAs($this->designer, 'sanctum')->postJson('/api/v1/bd/tasks/in-house', [])->assertForbidden();
        $this->actingAs($this->designer, 'sanctum')->postJson('/api/v1/bd/drafts', [])->assertForbidden();
        $this->assertSame(0, DesignTask::where('task_name', 'Highway hoarding')->count());
    }

    // ---------------------------------------------------------------- drafts

    public function test_drafts_need_only_a_name_and_can_be_updated_then_converted(): void
    {
        $this->actingAs($this->bd, 'sanctum');

        $id = $this->postJson('/api/v1/bd/drafts', ['task_name' => 'Wip', 'vertical' => 'outdoor', 'task_nature' => 'creative_adaptation'])
            ->assertCreated()->json('id');
        $this->assertSame('draft', DesignTask::findOrFail($id)->status);

        $this->assertSame($id, $this->postJson('/api/v1/bd/drafts', ['draft_id' => $id, 'task_name' => 'Wip 2', 'vertical' => 'outdoor', 'task_nature' => 'creative_adaptation'])
            ->assertCreated()->json('id'));
        $this->assertSame('Wip 2', DesignTask::findOrFail($id)->task_name);

        $this->postJson('/api/v1/bd/drafts', [])->assertStatus(422);

        // Converting the draft keeps its id.
        $created = $this->postJson('/api/v1/bd/tasks', $this->payload(['draft_id' => $id]))->assertCreated()->json('id');
        $this->assertSame($id, $created);
        $this->assertSame('assigned_tasks', DesignTask::findOrFail($id)->status);
    }

    public function test_in_house_draft(): void
    {
        $id = $this->actingAs($this->bd, 'sanctum')->postJson('/api/v1/bd/drafts/in-house', ['task_name' => 'Maybe later'])
            ->assertCreated()->json('id');

        $draft = DesignTask::findOrFail($id);
        $this->assertSame('draft', $draft->status);
        $this->assertSame('in_house', $draft->task_category);
    }

    public function test_cannot_overwrite_another_bds_draft(): void
    {
        $draft = DesignTask::create([
            'task_id' => 'PENDING-'.uniqid(), 'assigned_at' => now(), 'assigned_by' => $this->otherBd->id, 'task_name' => 'Theirs',
            'vertical' => 'outdoor', 'task_nature' => 'creative_adaptation', 'party_type' => 'client', 'party_name' => '', 'contact_person' => '',
            'mobile_number' => '', 'priority' => 'low', 'status' => 'draft', 'requirements' => [],
        ]);

        $this->actingAs($this->bd, 'sanctum')
            ->postJson('/api/v1/bd/drafts', ['draft_id' => $draft->id, 'task_name' => 'Hijacked', 'vertical' => 'outdoor', 'task_nature' => 'creative_adaptation'])
            ->assertForbidden();
        $this->assertSame('Theirs', $draft->fresh()->task_name);
    }

    // ---------------------------------------------------------------- helpers used by the form

    public function test_designer_availability_and_client_search(): void
    {
        ClientDetail::rememberFromTask('Zenith Motors', 'Ravi', '9123456780');
        $this->actingAs($this->bd, 'sanctum');

        $this->getJson("/api/v1/bd/designers/{$this->designer->id}/availability")
            ->assertOk()->assertJsonStructure(['designer_id', 'availability_percent', 'level', 'label']);

        $this->assertSame('Zenith Motors', $this->getJson('/api/v1/bd/clients/search?q=Zenith')->assertOk()->json('0.client_name'));

        $this->actingAs($this->designer, 'sanctum')->getJson('/api/v1/bd/clients/search?q=Zenith')->assertForbidden();
    }
}
