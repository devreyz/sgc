<?php

namespace App\Services;

use App\Models\NotificationEventPreference;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Notifications\TenantEventNotification;
use App\Support\NotificationEventCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
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

        $preference = $this->preference($eventKey, $tenantId);
        $targetRoles = $preference?->recipient_roles ?? $definition['roles'];
        if (is_string($targetRoles)) {
            $targetRoles = json_decode($targetRoles, true) ?: [];
        }
        $targetRoles = is_array($targetRoles) ? $targetRoles : [];

        // A central do SGC é a fonte de verdade: push é somente transporte e
        // nunca pode eliminar o histórico do destinatário.
        $databaseEnabled = true;
        $pushEnabled = ($preference?->push_enabled ?? $definition['pushDefault'])
            && $definition['pushAllowed'];

        $payload = $this->normalizePayload($eventKey, $tenantId, $message, $preference?->priority ?? $definition['priority']);
        $tenantSlug = $pushEnabled ? Tenant::query()->whereKey($tenantId)->value('slug') : null;
        $sent = 0;

        foreach (collect($recipients)->filter()->unique('id') as $recipient) {
            if (! $recipient instanceof User || ! $recipient->status) {
                continue;
            }

            $notificationId = (string) Str::uuid();
            $recipientPayload = $payload;
            $destination = $this->recipientDestination(
                $message,
                $recipient,
                $tenantId,
                $payload['url'],
                $targetRoles,
            );
            $recipientPayload['url'] = $destination['path'];
            $recipientPayload['recipient_role'] = $destination['role'];
            // Todo push tambem fica registrado na central para manter historico e leitura.
            if ($databaseEnabled) {
                $centralPayload = $recipientPayload + [
                    'delivery_channels' => [
                        'in_app' => true,
                        'android_push' => $pushEnabled,
                    ],
                ];
                $pushPayload = $centralPayload;
                if ($tenantSlug) {
                    $pushPayload['url'] = route('notifications.open', [
                        'tenant' => $tenantSlug,
                        'notification' => $notificationId,
                    ], false);
                }
                $notification = new TenantEventNotification($centralPayload, $pushEnabled, $pushPayload);
                $notification->id = $notificationId;
                $recipient->notify($notification);
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

        $roles = $this->preference($eventKey, $tenantId)?->recipient_roles ?? $definition['roles'];

        if (is_string($roles)) {
            $roles = json_decode($roles, true) ?: [];
        }

        $message['role_priority'] ??= $roles;

        return $this->dispatch($eventKey, $tenantId, $this->usersForRoles($tenantId, $roles), $message);
    }

    public function configuredRoles(string $eventKey, int $tenantId): array
    {
        $definition = NotificationEventCatalog::get($eventKey);
        if (! $definition) {
            return [];
        }

        $roles = $this->preference($eventKey, $tenantId)?->recipient_roles;

        if (is_string($roles)) {
            $roles = json_decode($roles, true);
        }

        return is_array($roles) ? $roles : $definition['roles'];
    }

    private function normalizePayload(string $eventKey, int $tenantId, array $message, string $priority): array
    {
        $path = (string) ($message['url'] ?? '/');
        if (! Str::startsWith($path, '/') || Str::startsWith($path, '//')) {
            $path = '/';
        }

        $roleUrls = collect(is_array($message['role_urls'] ?? null) ? $message['role_urls'] : [])
            ->only(array_keys(NotificationEventCatalog::roles()))
            ->map(fn ($url): string => $this->safePath((string) $url, ''))
            ->filter()
            ->all();

        return [
            'format' => 'filament',
            'tenant_id' => $tenantId,
            'event_key' => $eventKey,
            'priority' => in_array($priority, NotificationEventCatalog::PRIORITIES, true) ? $priority : 'normal',
            'title' => Str::limit(strip_tags((string) ($message['title'] ?? 'Nova notificacao')), 120, ''),
            'body' => Str::limit(strip_tags((string) ($message['body'] ?? '')), 320, ''),
            'url' => $path,
            'role_urls' => $roleUrls,
            'icon' => 'heroicon-o-bell',
            'display_icon' => (string) ($message['icon'] ?? 'bell'),
            'action_label' => Str::limit(strip_tags((string) ($message['action_label'] ?? '')), 40, ''),
            'action_icon' => Str::limit(strip_tags((string) ($message['action_icon'] ?? '')), 40, ''),
            'iconColor' => in_array($priority, ['high', 'critical'], true) ? 'danger' : ($priority === 'info' ? 'info' : 'primary'),
            'duration' => null,
            'actions' => [],
            'links' => collect($message['actions'] ?? [])->take(2)->map(fn ($action) => [
                'label' => Str::limit(strip_tags((string) ($action['label'] ?? 'Abrir')), 30, ''),
                'url' => $this->safePath((string) ($action['url'] ?? ''), $path),
            ])->values()->all(),
        ];
    }

    /**
     * A mesma ocorrência pode levar cada papel a uma tela diferente. O destino
     * é resolvido no momento da criação da notificação, ficando seguro e
     * estável mesmo se o usuário trocar de papel posteriormente.
     */
    private function recipientDestination(
        array $message,
        User $recipient,
        int $tenantId,
        string $fallback,
        array $targetRoles,
    ): array {
        $roleUrls = is_array($message['role_urls'] ?? null) ? $message['role_urls'] : [];
        $membership = TenantUser::query()
            ->forTenant($tenantId)
            ->where('user_id', $recipient->id)
            ->active()
            ->first(['roles', 'is_admin']);
        $roles = is_array($membership?->roles) ? $membership->roles : [];
        if ($membership?->is_admin) {
            $roles[] = 'admin';
        }

        $explicitRole = (string) ($message['role_context'] ?? '');
        $messagePriority = is_array($message['role_priority'] ?? null) ? $message['role_priority'] : [];
        if ($explicitRole !== ''
            && array_key_exists($explicitRole, NotificationEventCatalog::roles())
            && in_array($explicitRole, $roles, true)) {
            return [
                'path' => $this->safePath((string) ($roleUrls[$explicitRole] ?? ''), $fallback),
                'role' => $explicitRole,
            ];
        }

        $roleOrder = collect([$explicitRole])
            ->merge($messagePriority)
            ->merge($targetRoles)
            ->merge(['registrador_entregas', 'visualizador_entregas', 'comprador', 'financeiro', 'tesoureiro', 'contador', 'admin', 'associado'])
            ->filter(fn ($role): bool => is_string($role) && array_key_exists($role, NotificationEventCatalog::roles()))
            ->unique()
            ->values();

        foreach ($roleOrder as $role) {
            $path = $this->safePath((string) ($roleUrls[$role] ?? ''), '');
            if ($path !== '' && in_array($role, $roles, true)) {
                return ['path' => $path, 'role' => $role];
            }
        }

        return ['path' => $fallback, 'role' => null];
    }

    private function safePath(string $path, string $fallback = '/'): string
    {
        return Str::startsWith($path, '/') && ! Str::startsWith($path, '//')
            ? $path
            : $fallback;
    }

    private function preference(string $eventKey, int $tenantId): ?NotificationEventPreference
    {
        if (! Schema::hasTable('notification_event_preferences')) {
            return null;
        }

        return NotificationEventPreference::query()
            ->where('tenant_id', $tenantId)
            ->where('event_key', $eventKey)
            ->first();
    }
}
