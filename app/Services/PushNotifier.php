<?php

namespace App\Services;

use App\Models\RankNotification;
use App\Models\User;
use App\Services\Fcm\FcmTokenProvider;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Delivers a saved in-app notification to the recipient's device via FCM
 * HTTP v1. Notifications always live in the in-app feed; this is best-effort
 * push on top. When FCM isn't configured (e.g. local dev) every call is a
 * silent no-op, so callers never have to check.
 */
class PushNotifier
{
    public function __construct(private readonly FcmTokenProvider $tokens) {}

    public function notify(RankNotification $notification): void
    {
        if (! $this->enabled()) {
            return;
        }

        $user = User::find($notification->user_id);
        $token = $user?->fcm_token;

        if ($user === null || $token === null) {
            return;
        }

        try {
            $response = $this->send($token, $notification);
        } catch (Throwable $e) {
            // Never let a push failure break the request that triggered it.
            Log::warning('FCM send errored', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        if ($this->tokenIsDead($response)) {
            // App uninstalled or token rotated — stop pushing to it.
            $user->forceFill(['fcm_token' => null])->save();

            return;
        }

        if ($response->failed()) {
            Log::warning('FCM send failed', [
                'notification_id' => $notification->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }
    }

    private function send(string $token, RankNotification $notification): Response
    {
        $projectId = config('services.fcm.project_id');

        return Http::withToken($this->tokens->accessToken())
            ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => $notification->title,
                        'body' => $notification->body,
                    ],
                    // Data payload the app reads to deep-link to the right screen.
                    // Every value must be a string per the FCM v1 spec.
                    'data' => [
                        'type' => (string) ($notification->type ?? 'rank'),
                        'notification_id' => (string) $notification->id,
                        'challenge_id' => (string) ($notification->challenge_id ?? ''),
                        'machine_id' => (string) ($notification->machine_id ?? ''),
                        // The related user (e.g. the new follower) for profile deep-links.
                        'actor_id' => (string) ($notification->overtaken_by_user_id ?? ''),
                    ],
                    'android' => ['priority' => 'high'],
                    'apns' => [
                        'headers' => ['apns-priority' => '10'],
                        'payload' => ['aps' => ['sound' => 'default']],
                    ],
                ],
            ]);
    }

    /**
     * FCM reports a stale/invalid token as HTTP 404 (UNREGISTERED) or as a 400
     * INVALID_ARGUMENT on the token field. Either way the token is unusable.
     */
    private function tokenIsDead(Response $response): bool
    {
        if ($response->status() === 404) {
            return true;
        }

        $status = $response->json('error.status');

        return in_array($status, ['UNREGISTERED', 'NOT_FOUND', 'INVALID_ARGUMENT'], true);
    }

    private function enabled(): bool
    {
        return filled(config('services.fcm.project_id'))
            && filled(config('services.fcm.credentials'));
    }
}
