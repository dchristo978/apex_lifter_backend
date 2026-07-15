<?php

namespace App\Services\Fcm;

use RuntimeException;

/**
 * Bound when FCM isn't configured. PushNotifier guards on config before ever
 * asking for a token, so this should never actually be called — it throws to
 * surface a misconfiguration loudly rather than send a broken request.
 */
class NullFcmTokenProvider implements FcmTokenProvider
{
    public function accessToken(): string
    {
        throw new RuntimeException('FCM is not configured (set FCM_PROJECT_ID and FCM_CREDENTIALS).');
    }
}
