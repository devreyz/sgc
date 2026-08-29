<?php

namespace App\Http\Controllers;

use App\Jobs\SendFcmNotification;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Services\NotificationDeliveryStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Illuminate\View\View;

class NotificationCenterController extends Controller
{
    public function index(Request $request, Tenant $tenant, NotificationDeliveryStatusService $deliveries): View
    {
        $this->assertTenant($request, $tenant);

        $notifications = $this->tenantNotifications($request, $tenant)
            ->latest()
            ->paginate(20);
        $deliveryStatuses = $deliveries->forUser($notifications->getCollection(), (int) $request->user()->id);
        $canManageDeliveries = TenantUser::query()
            ->forTenant($tenant->id)
            ->active()
            ->where('user_id', $request->user()->id)
            ->where(fn ($query) => $query->where('is_admin', true)->orWhereJsonContains('roles', 'admin'))
            ->exists();

        return view('notifications.index', compact('tenant', 'notifications', 'deliveryStatuses', 'canManageDeliveries'));
    }

    public function unreadCount(Request $request, Tenant $tenant): JsonResponse
    {
        $this->assertTenant($request, $tenant);

        return response()->json([
            'count' => $this->tenantNotifications($request, $tenant)->whereNull('read_at')->count(),
        ]);
    }

    public function markRead(Request $request, Tenant $tenant, string $notification): JsonResponse
    {
        $record = $this->findOwned($request, $tenant, $notification);
        $record->markAsRead();

        return response()->json(['ok' => true]);
    }

    public function markAllRead(Request $request, Tenant $tenant): JsonResponse
    {
        $this->assertTenant($request, $tenant);
        $this->tenantNotifications($request, $tenant)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['ok' => true, 'count' => 0]);
    }

    public function open(Request $request, Tenant $tenant, string $notification): RedirectResponse
    {
        $record = $this->findOwned($request, $tenant, $notification);
        $record->markAsRead();

        return redirect()->to($this->safePath((string) data_get($record->data, 'url', '/')));
    }

    public function retryDelivery(Request $request, Tenant $tenant, string $notification): JsonResponse
    {
        $record = $this->findOwned($request, $tenant, $notification);
        abort_unless(TenantUser::query()
            ->forTenant($tenant->id)
            ->active()
            ->where('user_id', $request->user()->id)
            ->where(fn ($query) => $query->where('is_admin', true)->orWhereJsonContains('roles', 'admin'))
            ->exists(), 403);
        abort_unless((bool) data_get($record->data, 'delivery_channels.android_push', false), 422);

        $hasDevice = \App\Models\PushDevice::query()
            ->where('user_id', $request->user()->id)
            ->where('platform', 'android')
            ->where('notifications_enabled', true)
            ->whereNull('revoked_at')
            ->exists();

        if (! $hasDevice) {
            return response()->json(['ok' => false, 'message' => 'Nenhum dispositivo Android ativo.'], 422);
        }

        SendFcmNotification::dispatch(
            (int) $request->user()->id,
            (int) $tenant->id,
            (string) $record->id,
            (array) $record->data,
        )->afterCommit();

        return response()->json(['ok' => true, 'message' => 'Nova tentativa agendada.']);
    }

    private function tenantNotifications(Request $request, Tenant $tenant)
    {
        return $request->user()->notifications()
            ->where('data->tenant_id', $tenant->id);
    }

    private function findOwned(Request $request, Tenant $tenant, string $id): DatabaseNotification
    {
        $this->assertTenant($request, $tenant);

        return $this->tenantNotifications($request, $tenant)->findOrFail($id);
    }

    private function assertTenant(Request $request, Tenant $tenant): void
    {
        abort_unless((int) session('tenant_id') === (int) $tenant->id, 403);
        abort_unless($request->user()->tenants()
            ->where('tenants.id', $tenant->id)
            ->wherePivot('status', true)
            ->exists(), 403);
    }

    private function safePath(string $path): string
    {
        return Str::startsWith($path, '/') && ! Str::startsWith($path, '//') ? $path : '/';
    }
}
