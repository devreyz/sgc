<?php

namespace App\Jobs;

use App\Models\PushDevice;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\FcmHttpV1Client;
use App\Support\NotificationEventCatalog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SendFcmNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 45;

    public function __construct(
        public readonly int $userId,
        public readonly int $tenantId,
        public readonly string $notificationId,
        /** @deprecated Mantido apenas para desserializar tarefas criadas por versões anteriores. */
        public readonly array $payload = [],
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

        $notification = DatabaseNotification::query()
            ->whereKey($this->notificationId)
            ->where('notifiable_type', (new User)->getMorphClass())
            ->where('notifiable_id', $this->userId)
            ->first();

        if (! $notification || (int) data_get($notification->data, 'tenant_id') !== $this->tenantId) {
            Log::warning('FCM delivery skipped because its central notification was not found.', [
                'user_id' => $this->userId,
                'tenant_id' => $this->tenantId,
                'notification_id' => $this->notificationId,
            ]);

            return;
        }

        $centralPayload = (array) $notification->data;
        $tenantSlug = Tenant::query()->whereKey($this->tenantId)->value('slug');
        $openRoute = $tenantSlug
            ? route('notifications.open', ['tenant' => $tenantSlug, 'notification' => $notification->id], false)
            : '/';

        PushDevice::query()
            ->where('user_id', $this->userId)
            ->where('platform', 'android')
            ->where('notifications_enabled', true)
            ->whereNull('revoked_at')
            ->eachById(function (PushDevice $device) use ($fcm, $centralPayload, $openRoute): void {
                if (DB::table('push_delivery_receipts')
                    ->where('push_device_id', $device->id)
                    ->where('notification_id', $this->notificationId)
                    ->where('status', 'sent')
                    ->exists()) {
                    return;
                }

                DB::table('push_delivery_receipts')->updateOrInsert(
                    ['push_device_id' => $device->id, 'notification_id' => $this->notificationId],
                    [
                        'status' => 'pending',
                        'response_code' => null,
                        'delivered_at' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );

                try {
                    $response = $fcm->send($device->token, $this->message($centralPayload, $openRoute));
                } catch (Throwable $exception) {
                    Log::warning('FCM delivery attempt could not contact Firebase.', [
                        'push_device_id' => $device->id,
                        'notification_id' => $this->notificationId,
                        'tenant_id' => $this->tenantId,
                        'attempt' => $this->attempts(),
                        'exception_class' => $exception::class,
                        'error' => mb_substr($exception->getMessage(), 0, 300),
                    ]);

                    throw $exception;
                }
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
                    Log::warning('FCM delivery will be retried after a temporary response.', [
                        'push_device_id' => $device->id,
                        'notification_id' => $this->notificationId,
                        'tenant_id' => $this->tenantId,
                        'http_status' => $status,
                        'attempt' => $this->attempts(),
                    ]);

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

    public function failed(?Throwable $exception): void
    {
        Log::error('FCM notification job failed permanently.', [
            'user_id' => $this->userId,
            'tenant_id' => $this->tenantId,
            'notification_id' => $this->notificationId,
            'exception_class' => $exception ? $exception::class : null,
            'error' => $exception ? mb_substr($exception->getMessage(), 0, 300) : null,
        ]);
    }

    private function message(array $payload, string $openRoute): array
    {
        $eventKey = (string) ($payload['event_key'] ?? 'manual.message');
        $priority = (string) ($payload['priority'] ?? 'normal');

        return [
            'notification' => [
                'title' => Str::limit(strip_tags((string) ($payload['title'] ?? 'Nova notificação')), 120, ''),
                'body' => Str::limit(strip_tags((string) ($payload['body'] ?? '')), 320, ''),
            ],
            'data' => [
                'notification_id' => $this->notificationId,
                'tenant_id' => (string) $this->tenantId,
                'event_key' => $eventKey,
                'route' => $openRoute,
            ],
            'android' => [
                // Todos os avisos Android são entregues como alta prioridade.
                // A prioridade de negócio continua separada no payload da central.
                'priority' => 'HIGH',
                'ttl' => $priority === 'critical' ? '86400s' : '14400s',
                'restricted_package_name' => (string) config('notifications.fcm.android_package', 'br.rzin.sgc'),
                'notification' => [
                    'channel_id' => NotificationEventCatalog::androidChannel($eventKey),
                    'tag' => 'sgc-'.$this->notificationId,
                    'visibility' => 'PRIVATE',
                    'notification_priority' => 'PRIORITY_HIGH',
                    'default_sound' => true,
                    'default_vibrate_timings' => true,
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
