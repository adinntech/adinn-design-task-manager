<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

/** Public feed behind the mobile update gate. Needs no database. */
class AppUpdateApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']); // the throttle middleware must not touch the database
    }

    public function test_it_is_public_and_returns_the_configured_release(): void
    {
        config(['app_update.android' => [
            'min_build' => 5, 'latest_build' => 7, 'latest_version' => '1.2.0',
            'url' => 'https://example.test/adinn-tasks.apk', 'notes' => 'Bug fixes',
        ]]);

        $this->getJson('/api/v1/app-update?platform=android')
            ->assertOk()
            ->assertExactJson([
                'platform' => 'android', 'min_build' => 5, 'latest_build' => 7, 'latest_version' => '1.2.0',
                'url' => 'https://example.test/adinn-tasks.apk', 'notes' => 'Bug fixes',
            ]);
    }

    public function test_unset_values_never_force_an_update(): void
    {
        config(['app_update.android' => ['min_build' => 0, 'latest_build' => 0, 'latest_version' => null, 'url' => null, 'notes' => null]]);

        $this->getJson('/api/v1/app-update')->assertOk()->assertJsonPath('min_build', 0)->assertJsonPath('url', null);
    }

    public function test_latest_is_never_below_the_minimum(): void
    {
        config(['app_update.android' => ['min_build' => 9, 'latest_build' => 3, 'latest_version' => null, 'url' => null, 'notes' => null]]);

        $this->getJson('/api/v1/app-update')->assertOk()->assertJsonPath('latest_build', 9);
    }

    public function test_an_unknown_platform_gets_a_harmless_empty_answer(): void
    {
        $this->getJson('/api/v1/app-update?platform=ios')->assertOk()->assertJsonPath('min_build', 0);
    }
}
