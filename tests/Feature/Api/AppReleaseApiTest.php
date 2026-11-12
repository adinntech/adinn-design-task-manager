<?php

namespace Tests\Feature\Api;

use App\Jobs\AnnounceAppUpdateJob;
use App\Models\AppSetting;
use App\Support\AppRelease;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

/** The release pipeline endpoint. Runs on the scratch database (see CLAUDE.md), like the other DB tests. */
class AppReleaseApiTest extends TestCase
{
    use DatabaseTransactions;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $db = (string) config('database.connections.'.config('database.default').'.database');
        if (! str_contains($db, 'test') && ! str_contains($db, 'scratch')) {
            $this->markTestSkipped('Refusing to run against a non-test database.');
        }

        config(['app_update.release_token' => 'secret-token', 'app_update.keep_apks' => 2]);
        AppSetting::query()->where('key', AppRelease::KEY)->delete();
        Cache::forget(AppRelease::KEY);

        $this->dir = public_path('downloads');
        File::delete(File::glob($this->dir.'/adinn-design-tasks-9.*-build*.apk'));
    }

    protected function tearDown(): void
    {
        if (isset($this->dir)) {
            File::delete(File::glob($this->dir.'/adinn-design-tasks-9.*-build*.apk'));
        }
        Cache::forget(AppRelease::KEY);

        parent::tearDown();
    }

    private function apk(string $name = 'app.apk', bool $valid = true): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'apk');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString($valid ? 'AndroidManifest.xml' : 'readme.txt', 'x'.random_int(1, 999999));
        $zip->close();

        return new UploadedFile($path, $name, 'application/vnd.android.package-archive', null, true);
    }

    private function release(int $build, string $version = '9.0.1', array $extra = [], ?UploadedFile $apk = null, string $token = 'secret-token')
    {
        $apk ??= $this->apk();

        return $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/v1/internal/app-release', array_merge([
            'apk' => $apk,
            'build' => $build,
            'version' => $version,
            'notes' => 'New things',
            'sha256' => hash_file('sha256', $apk->getRealPath()),
        ], $extra));
    }

    public function test_endpoint_is_off_without_a_configured_token(): void
    {
        config(['app_update.release_token' => null]);

        $this->release(901)->assertNotFound();
    }

    public function test_wrong_or_missing_token_is_refused(): void
    {
        $this->release(901, token: 'nope')->assertUnauthorized();
        $this->postJson('/api/v1/internal/app-release', [])->assertUnauthorized();
    }

    public function test_publishes_a_forced_release_and_announces_it(): void
    {
        Bus::fake();

        $this->release(901)->assertOk()->assertJsonPath('release.build', 901)->assertJsonPath('release.min_build', 901);

        $this->assertFileExists($this->dir.'/adinn-design-tasks-9.0.1-build901.apk');
        Bus::assertDispatchedAfterResponse(AnnounceAppUpdateJob::class);

        $this->getJson('/api/v1/app-update')->assertOk()->assertJson([
            'min_build' => 901,
            'latest_build' => 901,
            'latest_version' => '9.0.1',
            'notes' => 'New things',
        ])->assertJsonPath('url', url('downloads/adinn-design-tasks-9.0.1-build901.apk'));
    }

    public function test_rejects_non_apk_damaged_and_not_newer_uploads(): void
    {
        Bus::fake();

        $this->release(901, apk: $this->apk('x.apk', valid: false))->assertStatus(422)->assertJsonValidationErrors('apk');
        $this->release(901, extra: ['sha256' => str_repeat('a', 64)])->assertStatus(422)->assertJsonValidationErrors('sha256');

        $this->release(905)->assertOk();
        $this->release(905, '9.0.2')->assertStatus(422)->assertJsonValidationErrors('build');
        $this->release(904, '9.0.2')->assertStatus(422)->assertJsonValidationErrors('build');
        Bus::assertDispatchedAfterResponseTimes(AnnounceAppUpdateJob::class, 1);
    }

    public function test_keeps_only_the_newest_apks(): void
    {
        Bus::fake();

        foreach ([901, 902, 903] as $b) {
            $this->release($b, "9.0.{$b}")->assertOk();
        }

        $this->assertFileDoesNotExist($this->dir.'/adinn-design-tasks-9.0.901-build901.apk');
        $this->assertFileExists($this->dir.'/adinn-design-tasks-9.0.902-build902.apk');
        $this->assertFileExists($this->dir.'/adinn-design-tasks-9.0.903-build903.apk');
    }

    public function test_kill_switch_unforces_everyone(): void
    {
        Bus::fake();
        $this->release(901)->assertOk();

        $this->artisan('app:release', ['--min-build' => 0])->assertSuccessful();

        $this->getJson('/api/v1/app-update')->assertJson(['min_build' => 0, 'latest_build' => 901]);
    }
}
