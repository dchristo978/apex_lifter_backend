<?php

namespace App\Services\Fcm;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Mints FCM access tokens from a Firebase service-account key via google/auth.
 * Tokens are valid for ~1h; we cache slightly under that to avoid re-signing on
 * every push.
 */
class GoogleFcmTokenProvider implements FcmTokenProvider
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    private const CACHE_KEY = 'fcm.access_token';

    public function __construct(private readonly string $credentialsPath) {}

    public function accessToken(): string
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(50), function (): string {
            $credentials = new ServiceAccountCredentials(self::SCOPE, $this->credentialsPath);
            $token = $credentials->fetchAuthToken();

            if (! isset($token['access_token'])) {
                throw new RuntimeException('FCM: service account did not return an access token.');
            }

            return $token['access_token'];
        });
    }
}
