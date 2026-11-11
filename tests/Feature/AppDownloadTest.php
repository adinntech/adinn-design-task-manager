<?php

namespace Tests\Feature;

use App\Http\Controllers\AppDownloadController;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** "Get the app": BD and Designers download the published APK; nobody else, and nobody signed out. */
class AppDownloadTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $db = (string) config('database.connections.'.config('database.default').'.database');
        $this->assertTrue(str_contains($db, 'test') || str_contains($db, 'scratch'), "Refusing to run against '{$db}'.");

        Storage::fake('local');
        config(['app_update.android.url' => null, 'app_update.android.latest_version' => '1.2.3', 'app_update.android.download_enabled' => true]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::where('role', $role)->where('is_active', true)->first();
        $this->assertNotNull($user, "No active {$role} user in the database.");

        return $user;
    }

    public function test_bd_and_designer_download_the_published_apk_as_a_file(): void
    {
        Storage::disk('local')->put(AppDownloadController::DISK_PATH, 'PK-fake-apk-bytes');

        foreach (['bd', 'designer'] as $role) {
            $response = $this->actingAs($this->userWithRole($role))->get('/app/download');

            $response->assertOk();
            $response->assertHeader('Content-Type', 'application/vnd.android.package-archive');
            $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
            $this->assertStringContainsString('adinn-design-tasks-1.2.3.apk', (string) $response->headers->get('Content-Disposition'));
        }
    }

    public function test_without_a_published_file_the_configured_link_is_used_and_without_either_it_is_404(): void
    {
        $bd = $this->userWithRole('bd');

        $this->actingAs($bd)->get('/app/download')->assertNotFound();

        config(['app_update.android.url' => 'https://files.example.test/adinn.apk']);
        $this->actingAs($bd)->get('/app/download')->assertRedirect('https://files.example.test/adinn.apk');
    }

    public function test_other_roles_and_guests_cannot_download(): void
    {
        Storage::disk('local')->put(AppDownloadController::DISK_PATH, 'PK-fake-apk-bytes');

        foreach (['admin', 'designer_head'] as $role) {
            $user = User::where('role', $role)->where('is_active', true)->first();
            if ($user) {
                $this->actingAs($user)->get('/app/download')->assertForbidden();
            }
        }
    }

    public function test_it_is_off_by_default_so_deploying_does_not_show_the_app(): void
    {
        config(['app_update.android.download_enabled' => false, 'app_update.android.url' => 'https://files.example.test/adinn.apk']);
        Storage::disk('local')->put(AppDownloadController::DISK_PATH, 'PK-fake-apk-bytes');

        $bd = $this->userWithRole('bd');
        $this->actingAs($bd)->get('/app/download')->assertNotFound();
        $this->actingAs($bd)->get('/bd/dashboard')->assertDontSee('data-app-download', false);
    }

    public function test_guests_are_sent_to_sign_in(): void
    {
        $this->get('/app/download')->assertRedirect();
    }

    public function test_the_top_bar_button_appears_for_bd_and_designer_only_when_there_is_something_to_download(): void
    {
        $bd = $this->userWithRole('bd');
        $designer = $this->userWithRole('designer');

        $this->actingAs($bd)->get('/bd/dashboard')->assertDontSee('data-app-download', false);

        Storage::disk('local')->put(AppDownloadController::DISK_PATH, 'PK-fake-apk-bytes');

        $this->actingAs($bd)->get('/bd/dashboard')->assertSee('data-app-download', false)->assertSee(route('app.download'), false);
        $this->actingAs($designer)->get('/designer/dashboard')->assertSee('data-app-download', false);
    }
}
