<?php

namespace Tests\Integration;

use App\Models\PushDelivery;
use App\Models\User;
use App\Services\Push\PushService;
use Illuminate\Support\Facades\Http;
use Tests\Feature\MobilePushApiTest;

/** Real HTTP with generated test credentials; does not use a live device or Google service. */
class MobileFcmSimulationTest extends MobilePushApiTest
{
    public function test_real_mobile_fcm_http_against_local_simulation(): void
    {
        $base = getenv('TENANTISM_FCM_SIMULATION_BASE') ?: '';
        $this->assertMatchesRegularExpression('#^http://127\.0\.0\.1:[0-9]+/firebase$#', $base);
        config(['push.test_base_url' => $base, 'push.test_oauth_url' => $base.'/token']);
        Http::allowStrayRequests([$base.'/*']);
        $user = User::factory()->renter()->create();
        foreach (['valid', 'unregistered', 'temporary', 'quota'] as $index => $scenario) {
            $this->registerDevice($user, ['platform' => $index % 2 === 0 ? 'android' : 'ios',
                'token' => $scenario.'-'.str_repeat('x', 30)]);
        }
        $notification = $this->notification($user);
        $service = app(PushService::class);
        $this->assertTrue($service->sendToUser($notification));
        $this->assertTrue($service->sendToUser($notification));
        $this->assertFalse($service->sendToUser($notification));
        $this->assertSame(1, PushDelivery::where('status', 'sent')->count());
        $this->assertSame(1, PushDelivery::where('status', 'revoked')->count());
        $this->assertSame(2, PushDelivery::where('status', 'failed')->where('attempts', 3)->count());
        $this->assertNull($notification->fresh()->read_at);
    }
}
