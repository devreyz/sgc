<?php

namespace App\Services;

use App\Jobs\SendWebPushNotification;
use App\Models\NotificationEventPreference;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Notifications\TenantEventNotification;
use App\Support\NotificationEventCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TenantNotificationDispatcher
{
    public function usersForRoles(int $tenantId, array $roles): Collection
    {
        $roles = array_values(array_unique($roles));

        return TenantUser::query()
            ->forTenant($tenantId)
            ->active()
            ->with(['user:id,status'])
            ->get(['id', 'tenant_id', 'user_id', 'is_admin', 'roles'])
            ->filter(function (TenantUser $membership) use ($roles) {
                if (! $membership->user?->status) {
                    return false;
                }

                if ($membership->is_admin && in_array('admin', $roles, true)) {
                    return true;
                }

                return count(array_intersect($membership->roles ?? [], $roles)) > 0;
            })
            ->pluck('user')
            ->unique('id')
            ->values();
    }

    public function dispatch(string $eventKey, int $tenantId, iterable $recipients, array $message): int
    {
        $definition = NotificationEventCatalog::get($eventKey);
        if (! $definition) {
            throw new \InvalidArgumentException("Evento de notificacao desconhecido: {$eventKey}");
        }

        $preference = NotificationEventPreference::query()
            ->where('tenant_id', $tenantId)
            ->where('event_key', $eventKey)
            ->first();

        $databaseEnabled = $preference?->database_enabled ?? $definition['databaseDefault'];
        $pushEnabled = ($preference?->push_enabled ?? $definition['pushDefault'])
            && $definition['pushAllowed'];

        if (! $databaseEnabled && ! $pushEnabled) {
            return 0;
        }

        $payload = $this->normalizePayload($eventKey, $tenantId, $message, $preference?->priority ?? $definition['priority']);
        $tenantSlug = $pushEnabled ? Tenant::query()->whereKey($tenantId)->value('slug') : null;
        $sent = 0;

        foreach (collect($recipients)->filter()->unique('id') as $recipient) {
            if (! $recipient instanceof User || ! $recipient->status) {
                continue;
            }

            $notificationId = (string) Str::uuid();
            // Todo push tambem fica registrado na central para manter historico e leitura.
            if ($databaseEnabled || $pushEnabled) {
                $notification = new TenantEventNotification($payload);
                $notification->id = $notificationId;
                $recipient->notify($notification);
            }

            if ($pushEnabled && $this->pushConfigured()) {
                $pushPayload = $payload;
                if ($tenantSlug) {
                    $pushPayload['url'] = route('notifications.open', [
                        'tenant' => $tenantSlug,
                        'notification' => $notificationId,
                    ], false);
                }
                SendWebPushNotification::dispatch($recipient->id, $tenantId, $notificationId, $pushPayload)->afterCommit();
            }

            $sent++;
        }

        return $sent;
    }

    public function dispatchToConfiguredRoles(string $eventKey, int $tenantId, array $message): int
    {
        $definition = NotificationEventCatalog::get($eventKey);
        if (! $definition) {
            return 0;
        }

        $roles = NotificationEventPreference::query()
            ->where('tenant_id', $tenantId)
            ->where('event_key', $eventKey)
            ->value('recipient_roles') ?? $definition['roles'];

        if (is_string($roles)) {
            $roles = json_decode($roles, true) ?: [];
        }

        return $this->dispatch($eventKey, $tenantId, $this->usersForRoles($tenantId, $roles), $message);
    }

    public function configuredRoles(string $eventKey, int $tenantId): array
    {
        $definition = NotificationEventCatalog::get($eventKey);
        if (! $definition) return [];

        $roles = NotificationEventPreference::query()
            ->where('tenant_id', $tenantId)
            ->where('event_key', $eventKey)
            ->value('recipient_roles');

        if (is_string($roles)) $roles = json_decode($roles, true);

        return is_array($roles) ? $roles : $definition['roles'];
    }

    private function normalizePayload(string $eventKey, int $tenantId, array $message, string $priority): array
    {
        $path = (string) ($message['url'] ?? '/');
        if (! Str::startsWith($path, '/') || Str::startsWith($path, '//')) {
            $path = '/';
        }

        return [
            'format' => 'filament',
            'tenant_id' => $tenantId,
            'event_key' => $eventKey,
            'priority' => in_array($priority, NotificationEventCatalog::PRIORITIES, true) ? $priority : 'normal',
            'title' => Str::limit(strip_tags((string) ($message['title'] ?? 'Nova notificacao')), 120, ''),
            'body' => Str::limit(strip_tags((string) ($message['body'] ?? '')), 320, ''),
            'url' => $path,
            'icon' => 'heroicon-o-bell',
            'display_icon' => (string) ($message['icon'] ?? 'bell'),
            'iconColor' => in_array($priority, ['high', 'critical'], true) ? 'danger' : ($priority === 'info' ? 'info' : 'primary'),
            'duration' => null,
            'actions' => [],
            'links' => collect($message['actions'] ?? [])->take(2)->map(fn ($action) => [
                'label' => Str::limit(strip_tags((string) ($action['label'] ?? 'Abrir')), 30, ''),
                'url' => Str::startsWith((string) ($action['url'] ?? ''), '/') ? $action['url'] : $path,
            ])->values()->all(),
        ];
    }

    private function pushConfigured(): bool
    {
        return filled(config('notifications.vapid.subject'))
            && filled(config('notifications.vapid.public_key'))
            && filled(config('notifications.vapid.private_key'));
    }
}
