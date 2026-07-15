<?php

namespace App\Services\Fcm;

/**
 * Mints a short-lived OAuth2 access token for the FCM HTTP v1 API. Split out
 * from the sender so the send/cleanup logic can be tested without signing a
 * real service-account JWT or reaching Google.
 */
interface FcmTokenProvider
{
    public function accessToken(): string;
}
