<?php

namespace App\Http\Controllers;

use App\Models\NotificationEventPreference;
use App\Models\Tenant;
use App\Support\NotificationEventCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NotificationPreferenceController extends Controller
{
    public function index(Request $request, Tenant $tenant): View
    {
        $this->authorizeAdmin($request, $tenant);

        $preferences = NotificationEventPreference::query()
            ->where('tenant_id', $tenant->id)
            ->get()
            ->keyBy('event_key');

        return view('notifications.settings', [
            'tenant' => $tenant,
            'catalog' => NotificationEventCatalog::all(),
            'roleOptions' => NotificationEventCatalog::roles(),
            'preferences' => $preferences,
        ]);
    }

    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        $this->authorizeAdmin($request, $tenant);

        $data = $request->validate([
            'events' => ['required', 'array'],
            'events.*.database_enabled' => ['nullable', 'boolean'],
            'events.*.push_enabled' => ['nullable', 'boolean'],
            'events.*.priority' => ['required', Rule::in(NotificationEventCatalog::PRIORITIES)],
            'events.*.roles' => ['nullable', 'array'],
            'events.*.roles.*' => ['string', Rule::in(array_keys(NotificationEventCatalog::roles()))],
        ]);

        DB::transaction(function () use ($data, $request, $tenant) {
            foreach ($data['events'] as $key => $values) {
                $definition = NotificationEventCatalog::get($key);
                if (! $definition) {
                    continue;
                }

                NotificationEventPreference::query()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'event_key' => $key],
                    [
                        'database_enabled' => (bool) ($values['database_enabled'] ?? false),
                        'push_enabled' => $definition['pushAllowed'] && (bool) ($values['push_enabled'] ?? false),
                        'priority' => $values['priority'],
                        'recipient_roles' => array_values(array_unique($values['roles'] ?? $definition['roles'])),
                        'updated_by' => $request->user()->id,
                    ]
                );
            }

            activity('notification_settings')->causedBy($request->user())->withProperties([
                'tenant_id' => $tenant->id,
                'events_updated' => array_keys($data['events']),
            ])->log('Preferencias de notificacao atualizadas');
        });

        return back()->with('success', 'Preferencias de notificacao atualizadas.');
    }

    private function authorizeAdmin(Request $request, Tenant $tenant): void
    {
        abort_unless((int) session('tenant_id') === (int) $tenant->id, 403);
        abort_unless($request->user()->hasRoleInTenant(['admin', 'super_admin'], $tenant->id), 403);
    }
}
