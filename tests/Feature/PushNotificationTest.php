<?php

namespace Tests\Feature;

use App\Models\Machine;
use App\Models\RankNotification;
use App\Models\User;
use App\Services\Fcm\FcmTokenProvider;
use App\Services\PushNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Pretend FCM is configured, and hand out a canned access token so the
        // send path runs without signing a real service-account JWT.
        config([
            'services.fcm.project_id' => 'demo-project',
            'services.fcm.credentials' => '/tmp/fake-credentials.json',
        ]);

        $this->app->instance(FcmTokenProvider::class, new class implements FcmTokenProvider
        {
            public function accessToken(): string
            {
                return 'test-access-token';
            }
        });
    }

    private function notificationFor(User $user): RankNotification
    {
        $machine = Machine::create(['name' => 'Row', 'brand' => 'Acme', 'category' => 'back']);

        return RankNotification::create([
            'user_id' => $user->id,
            'type' => 'rank',
            'machine_id' => $machine->id,
            'title' => 'Overtaken',
            'body' => 'Someone passed you.',
        ]);
    }

    public function test_it_posts_to_fcm_when_the_user_has_a_token(): void
    {
        Http::fake([
            'fcm.googleapis.com/*' => Http::response(['name' => 'projects/demo-project/messages/1'], 200),
        ]);

        $user = User::factory()->create(['fcm_token' => 'device-token-123']);

        app(PushNotifier::class)->notify($this->notificationFor($user));

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'projects/demo-project/messages:send')
                && $request->hasHeader('Authorization', 'Bearer test-access-token')
                && $request['message']['token'] === 'device-token-123'
                && $request['message']['notification']['title'] === 'Overtaken'
                && $request['message']['data']['type'] === 'rank';
        });
    }

    public function test_it_skips_users_without_a_token(): void
    {
        Http::fake();

        $user = User::factory()->create(['fcm_token' => null]);

        app(PushNotifier::class)->notify($this->notificationFor($user));

        Http::assertNothingSent();
    }

    public function test_it_clears_a_dead_token_on_unregistered_response(): void
    {
        Http::fake([
            'fcm.googleapis.com/*' => Http::response([
                'error' => ['status' => 'NOT_FOUND', 'message' => 'Requested entity was not found.'],
            ], 404),
        ]);

        $user = User::factory()->create(['fcm_token' => 'stale-token']);

        app(PushNotifier::class)->notify($this->notificationFor($user));

        $this->assertNull($user->fresh()->fcm_token);
    }

    public function test_it_is_a_no_op_when_fcm_is_not_configured(): void
    {
        config(['services.fcm.project_id' => null, 'services.fcm.credentials' => null]);
        Http::fake();

        $user = User::factory()->create(['fcm_token' => 'device-token-123']);

        app(PushNotifier::class)->notify($this->notificationFor($user));

        Http::assertNothingSent();
        // Token is left intact — nothing was attempted.
        $this->assertSame('device-token-123', $user->fresh()->fcm_token);
    }
}
