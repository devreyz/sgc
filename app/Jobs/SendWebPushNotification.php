<?php

namespace App\Jobs;

use App\Models\PushSubscription;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class SendWebPushNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;
    public int $timeout = 30;

    public function __construct(
        public readonly int $userId,
        public readonly int $tenantId,
        public readonly string $notificationId,
        public readonly array $payload,
    ) {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        if (! User::query()->whereKey($this->userId)->where('status', true)->exists()
            || ! TenantUser::query()->forTenant($this->tenantId)->active()->where('user_id', $this->userId)->exists()) {
            return;
        }

        $vapid = config('notifications.vapid');
        if (blank($vapid['subject']) || blank($vapid['public_key']) || blank($vapid['private_key'])) {
            return;
        }

        $webPush = new WebPush(['VAPID' => $vapid]);
        $body = json_encode([
            'title' => $this->payload['title'],
            'body' => $this->payload['body'],
            'icon' => '/icons/icon-192.svg',
            'badge' => '/icons/icon-192.svg',
            'tag' => 'sgc-'.$this->notificationId,
            'priority' => $this->payload['priority'],
            'url' => $this->payload['url'],
            'notification_id' => $this->notificationId,
            'tenant_id' => $this->tenantId,
            'actions' => $this->payload['links'] ?? [],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        PushSubscription::query()->active()->where('user_id', $this->userId)->each(function (PushSubscription $stored) use ($webPush, $body) {
            try {
                $subscription = Subscription::create([
                    'endpoint' => $stored->endpoint,
                    'publicKey' => $stored->public_key,
                    'authToken' => $stored->auth_token,
                    'contentEncoding' => $stored->content_encoding,
                ]);

                $report = $webPush->sendOneNotification($subscription, $body, [
                    'TTL' => $this->payload['priority'] === 'critical' ? 86400 : 14400,
                    'urgency' => in_array($this->payload['priority'], ['high', 'critical'], true) ? 'high' : 'normal',
                ]);

                if ($report->isSuccess()) {
                    $stored->forceFill(['failure_count' => 0, 'last_used_at' => now(), 'last_failure_at' => null])->save();
                    return;
                }

                $failures = $stored->failure_count + 1;
                $stored->forceFill([
                    'failure_count' => $failures,
                    'last_failure_at' => now(),
                    'revoked_at' => $report->isSubscriptionExpired() || $failures >= config('notifications.subscription_failures_before_revoke', 3) ? now() : null,
                ])->save();
            } catch (\Throwable $exception) {
                $failures = $stored->failure_count + 1;
                $stored->forceFill([
                    'failure_count' => $failures,
                    'last_failure_at' => now(),
                    'revoked_at' => $failures >= config('notifications.subscription_failures_before_revoke', 3) ? now() : null,
                ])->save();
                Log::warning('Falha ao enviar Web Push.', [
                    'subscription_id' => $stored->id,
                    'notification_id' => $this->notificationId,
                    'error' => $exception->getMessage(),
                ]);
            }
        });
    }
}
