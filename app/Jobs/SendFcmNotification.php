<?php

namespace App\Jobs;

use App\Models\PushDevice;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\FcmHttpV1Client;
use App\Support\NotificationEventCatalog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SendFcmNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 45;

    public function __construct(
        public readonly int $userId,
        public readonly int $tenantId,
        public readonly string $notificationId,
        public readonly array $payload,
    ) {
        $this->onQueue('notifications');
    }

    public function backoff(): array
    {
        return [30, 180, 600];
    }

    public function handle(FcmHttpV1Client $fcm): void
    {
        if (! $fcm->configured()) {
            if (app()->environment('testing')) {
                return;
            }

            throw new RuntimeException('O Firebase HTTP v1 não está configurado no servidor.');
        }

        if (! User::query()->whereKey($this->userId)->where('status', true)->exists()
            || ! TenantUser::query()->forTenant($this->tenantId)->active()->where('user_id', $this->userId)->exists()) {
            Log::notice('FCM delivery skipped after authorization recheck.', [
                'user_id' => $this->userId,
                'tenant_id' => $this->tenantId,
                'notification_id' => $this->notificationId,
            ]);

            return;
        }

        PushDevice::query()
            ->where('user_id', $this->userId)
            ->where('platform', 'android')
            ->where('notifications_enabled', true)
            ->whereNull('revoked_at')
            ->eachById(function (PushDevice $device) use ($fcm): void {
                if (DB::table('push_delivery_receipts')
                    ->where('push_device_id', $device->id)
                    ->where('notification_id', $this->notificationId)
                    ->where('status', 'sent')
                    ->exists()) {
                    return;
                }

                $response = $fcm->send($device->token, $this->message());
                if ($response->successful()) {
                    $device->forceFill([
                        'failure_count' => 0,
                        'last_used_at' => now(),
                        'last_failure_at' => null,
                    ])->save();
                    DB::table('push_delivery_receipts')->updateOrInsert(
                        ['push_device_id' => $device->id, 'notification_id' => $this->notificationId],
                        [
                            'status' => 'sent', 'response_code' => $response->status(), 'delivered_at' => now(),
                            'created_at' => now(), 'updated_at' => now(),
                        ],
                    );

                    return;
                }

                $errorCode = $this->errorCode($response->json());
                $status = $response->status();

                if ($response->tooManyRequests() || $status >= 500) {
                    throw new RuntimeException("Temporary FCM failure ({$status}).");
                }

                $failures = $device->failure_count + 1;
                $invalidToken = in_array($errorCode, ['UNREGISTERED', 'SENDER_ID_MISMATCH'], true);
                $device->forceFill([
                    'failure_count' => $failures,
                    'last_failure_at' => now(),
                    'notifications_enabled' => ! $invalidToken,
                    'revoked_at' => $invalidToken || $failures >= config('notifications.fcm.failures_before_revoke', 3)
                        ? now()
                        : null,
                ])->save();
                DB::table('push_delivery_receipts')->updateOrInsert(
                    ['push_device_id' => $device->id, 'notification_id' => $this->notificationId],
                    [
                        'status' => $invalidToken ? 'invalid_token' : 'failed',
                        'response_code' => $status,
                        'delivered_at' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );

                Log::warning('FCM delivery rejected.', [
                    'push_device_id' => $device->id,
                    'notification_id' => $this->notificationId,
                    'http_status' => $status,
                    'error_code' => $errorCode,
                ]);
            });
    }

    private function message(): array
    {
        $eventKey = (string) ($this->payload['event_key'] ?? 'manual.message');
        $priority = (string) ($this->payload['priority'] ?? 'normal');

        return [
            'notification' => [
                'title' => NotificationEventCatalog::safePushTitle($eventKey),
                'body' => NotificationEventCatalog::safePushBody($eventKey),
            ],
            'data' => [
                'notification_id' => $this->notificationId,
                'tenant_id' => (string) $this->tenantId,
                'event_key' => $eventKey,
                'route' => (string) ($this->payload['url'] ?? '/'),
            ],
            'android' => [
                'priority' => in_array($priority, ['high', 'critical'], true) ? 'HIGH' : 'NORMAL',
                'ttl' => $priority === 'critical' ? '86400s' : '14400s',
                'restricted_package_name' => (string) config('notifications.fcm.android_package', 'br.rzin.sgc'),
                'notification' => [
                    'channel_id' => NotificationEventCatalog::androidChannel($eventKey),
                    'tag' => 'sgc-'.$this->notificationId,
                    'visibility' => 'PRIVATE',
                    'notification_priority' => in_array($priority, ['high', 'critical'], true)
                        ? 'PRIORITY_HIGH'
                        : 'PRIORITY_DEFAULT',
                ],
            ],
        ];
    }

    private function errorCode(mixed $body): ?string
    {
        foreach ((array) data_get($body, 'error.details', []) as $detail) {
            if (is_array($detail) && filled($detail['errorCode'] ?? null)) {
                return (string) $detail['errorCode'];
            }
        }

        return data_get($body, 'error.status');
    }
}
