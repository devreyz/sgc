<?php

namespace App\Services;

use App\Models\PushSubscription;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Schema;

class PushSubscriptionSessionService
{
    private const SESSION_KEY = 'security.push_device_session';

    public function hash(Session $session): string
    {
        $binding = $session->get(self::SESSION_KEY);
        if (! is_string($binding) || strlen($binding) < 43) {
            $binding = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
            $session->put(self::SESSION_KEY, $binding);
        }

        return hash_hmac('sha256', $binding, (string) config('app.key'));
    }

    public function revokeCurrentSession(int $userId, Session $session): int
    {
        if (! Schema::hasTable('push_subscriptions')
            || ! Schema::hasColumn('push_subscriptions', 'session_hash')) {
            return 0;
        }

        return PushSubscription::query()
            ->where('user_id', $userId)
            ->where('session_hash', $this->hash($session))
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }
}
