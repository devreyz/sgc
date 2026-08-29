<?php

namespace App\Notifications\Channels;

use App\Jobs\SendFcmNotification;
use App\Models\User;
use App\Notifications\TenantEventNotification;

class FcmChannel
{
    public function send(object $notifiable, TenantEventNotification $notification): void
    {
        if (! $notifiable instanceof User) {
            return;
        }

        SendFcmNotification::dispatch(
            $notifiable->id,
            $notification->tenantId(),
            (string) $notification->id,
        )->afterCommit();
    }
}
