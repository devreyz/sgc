<?php

namespace App\Services;

use App\Models\PushDevice;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NotificationDeliveryStatusService
{
    /**
     * @param Collection<int, DatabaseNotification> $notifications
     * @return array<string, array<string, int|string>>
     */
    public function forUser(Collection $notifications, int $userId): array
    {
        $ids = $notifications->pluck('id')->all();
        $activeDevices = PushDevice::query()
            ->where('user_id', $userId)
            ->where('platform', 'android')
            ->where('notifications_enabled', true)
            ->whereNull('revoked_at')
            ->count();
        $receipts = collect();

        if ($ids !== [] && Schema::hasTable('push_delivery_receipts')) {
            $receipts = DB::table('push_delivery_receipts')
                ->join('push_devices', 'push_devices.id', '=', 'push_delivery_receipts.push_device_id')
                ->where('push_devices.user_id', $userId)
                ->whereIn('push_delivery_receipts.notification_id', $ids)
                ->get([
                    'push_delivery_receipts.notification_id',
                    'push_delivery_receipts.status',
                ])
                ->groupBy('notification_id');
        }

        return $notifications->mapWithKeys(function (DatabaseNotification $notification) use ($receipts, $activeDevices): array {
            $requested = (bool) data_get($notification->data, 'delivery_channels.android_push', false);
            $items = collect($receipts->get($notification->id, []));
            $sent = $items->where('status', 'sent')->count();
            $failed = $items->whereIn('status', ['failed', 'invalid_token'])->count();
            $pending = $items->where('status', 'pending')->count();

            $status = match (true) {
                ! $requested => 'not_applicable',
                $activeDevices === 0 => 'no_device',
                $failed > 0 => 'failed',
                $pending > 0 || $items->isEmpty() => 'pending',
                $sent > 0 => 'delivered',
                default => 'pending',
            };

            return [$notification->id => [
                'status' => $status,
                'sent' => $sent,
                'failed' => $failed,
                'pending' => $pending,
                'devices' => max($activeDevices, $items->count()),
            ]];
        })->all();
    }
}
