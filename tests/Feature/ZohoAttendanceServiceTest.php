<?php

namespace Tests\Feature;

use App\Services\ZohoAttendanceService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Check-in lookup + automatic OAuth token refresh. No real Zoho calls (Http::fake). */
class ZohoAttendanceServiceTest extends TestCase
{
    private const ATTENDANCE = 'https://people.test/api/attendance/getUserAvailability*';

    private const TOKEN = 'https://accounts.test/oauth/v2/token*';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config([
            'zoho.access_token' => 'expired-token',
            'zoho.refresh_token' => 'refresh', 'zoho.client_id' => 'cid', 'zoho.client_secret' => 'secret',
            'zoho.accounts_url' => 'https://accounts.test',
            'zoho.attendance_url' => 'https://people.test/api/attendance/getUserAvailability',
        ]);
    }

    public function test_valid_token_returns_status_without_refreshing(): void
    {
        Http::fake([self::ATTENDANCE => Http::response(['isUserAvailable' => true])]);

        $this->assertTrue(app(ZohoAttendanceService::class)->isCheckedIn('a@x.test'));
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'oauth/v2/token'));
    }

    public function test_expired_token_is_refreshed_once_and_the_lookup_retried(): void
    {
        Http::fake([
            self::TOKEN => Http::response(['access_token' => 'fresh-token']),
            self::ATTENDANCE => Http::sequence()
                ->push(['response' => ['errors' => ['code' => 7213]]], 401)
                ->push(['isUserAvailable' => false])
                ->push(['isUserAvailable' => true]),
        ]);

        $service = app(ZohoAttendanceService::class);
        $this->assertFalse($service->isCheckedIn('a@x.test'));
        $this->assertSame('fresh-token', Cache::get('zoho.people.access_token'));

        // Next lookup uses the cached token directly — no second refresh.
        $this->assertTrue($service->isCheckedIn('b@x.test'));

        $refreshes = Http::recorded(fn ($request) => str_contains($request->url(), 'oauth/v2/token'));
        $this->assertCount(1, $refreshes);
    }

    public function test_failed_refresh_returns_null_and_backs_off(): void
    {
        Http::fake([
            self::TOKEN => Http::response(['error' => 'invalid_code'], 400),
            self::ATTENDANCE => Http::response(['response' => ['errors' => ['code' => 7213]]], 401),
        ]);

        $service = app(ZohoAttendanceService::class);
        $this->assertNull($service->isCheckedIn('a@x.test'));
        $this->assertNull($service->isCheckedIn('b@x.test'));

        // Only one refresh attempt despite two lookups (2-minute back-off).
        Http::assertSentCount(3); // 1 lookup + 1 refresh + 1 lookup (no 2nd refresh)
    }

    public function test_unknown_user_or_missing_settings_is_unable_to_check_not_false(): void
    {
        Http::fake([self::ATTENDANCE => Http::response(['error' => 'Invalid User'])]);
        $this->assertNull(app(ZohoAttendanceService::class)->isCheckedIn('nobody@x.test'));

        config(['zoho.access_token' => null]);
        Cache::flush();
        $this->assertNull(app(ZohoAttendanceService::class)->isCheckedIn('a@x.test'));
        $this->assertNull(app(ZohoAttendanceService::class)->isCheckedIn(null));
    }
}
