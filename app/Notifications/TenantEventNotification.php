<?php

namespace App\Notifications;

use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TenantEventNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly array $payload,
        private readonly bool $pushEnabled = false,
        private readonly ?array $pushPayload = null,
    )
    {
    }

    public function via(object $notifiable): array
    {
        return $this->pushEnabled ? ['database', FcmChannel::class] : ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload;
    }

    public function payload(): array
    {
        return $this->pushPayload ?? $this->payload;
    }

    public function tenantId(): int
    {
        return (int) ($this->payload['tenant_id'] ?? 0);
    }
}
