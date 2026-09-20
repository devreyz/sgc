<?php

namespace App\Http\Controllers\Services;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceProvider;
use App\Models\ServiceProviderService;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ServiceProviderManagementController extends Controller
{
    public function index(Request $request, Tenant $tenant): View
    {
        $this->allow($request);

        $search = trim((string) $request->string('search'));
        $status = $request->string('status')->toString();
        $providers = ServiceProvider::query()
            ->where('tenant_id', $tenant->id)
            ->with(['user:id,name,email', 'allServices' => fn ($query) => $query->wherePivot('status', true)->orderBy('name')])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('cpf', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            }))
            ->when(in_array($status, ['active', 'inactive'], true), fn ($query) => $query->where('status', $status === 'active'))
            ->orderByDesc('status')
            ->orderBy('name')
            ->paginate(18)
            ->withQueryString();

        $summary = [
            'total' => ServiceProvider::query()->where('tenant_id', $tenant->id)->count(),
            'active' => ServiceProvider::query()->where('tenant_id', $tenant->id)->active()->count(),
            'without_services' => ServiceProvider::query()->where('tenant_id', $tenant->id)
                ->active()->whereDoesntHave('services')->count(),
        ];

        return view('services.providers-index', compact('providers', 'summary', 'search', 'status'));
    }

    public function create(Request $request, Tenant $tenant): View
    {
        $this->allow($request);

        return $this->form($tenant, new ServiceProvider);
    }

    public function store(Request $request, Tenant $tenant): JsonResponse|RedirectResponse
    {
        $this->allow($request);
        $data = $this->validated($request, $tenant);

        $provider = DB::transaction(function () use ($data, $tenant, $request): ServiceProvider {
            $services = $data['services'] ?? [];
            unset($data['services']);
            $data['provider_roles'] = $data['provider_roles'] ?? [];
            $provider = new ServiceProvider($data);
            $provider->tenant_id = $tenant->id;
            $provider->status = $request->boolean('status');
            $provider->save();
            $provider->syncRolesToUser();
            $this->syncServices($provider, $services, $tenant->id, $request->user()->id);

            return $provider;
        }, 3);

        return $this->saved($request, $tenant, $provider, 'Prestador criado e habilitações salvas.');
    }

    public function edit(Request $request, Tenant $tenant, int $provider): View
    {
        $this->allow($request);
        $provider = $this->provider($tenant, $provider)->load('allServices:id');

        return $this->form($tenant, $provider);
    }

    public function update(Request $request, Tenant $tenant, int $provider): JsonResponse|RedirectResponse
    {
        $this->allow($request);
        $provider = $this->provider($tenant, $provider);
        $data = $this->validated($request, $tenant, $provider);

        DB::transaction(function () use ($data, $provider, $tenant, $request): void {
            $provider = ServiceProvider::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)->lockForUpdate()->findOrFail($provider->id);
            $services = $data['services'] ?? [];
            unset($data['services']);
            $data['provider_roles'] = $data['provider_roles'] ?? [];
            if ($provider->user_id && isset($data['user_id']) && (int) $data['user_id'] !== (int) $provider->user_id) {
                abort(422, 'O usuário já vinculado não pode ser trocado. Crie outro prestador ou remova o vínculo pelo painel administrativo.');
            }
            $provider->fill($data);
            $provider->status = $request->boolean('status');
            $provider->save();
            $provider->syncRolesToUser();
            $this->syncServices($provider, $services, $tenant->id, $request->user()->id);
        }, 3);

        return $this->saved($request, $tenant, $provider, 'Cadastro e habilitações atualizados.');
    }

    private function form(Tenant $tenant, ServiceProvider $provider): View
    {
        $users = $tenant->users()->wherePivot('status', true)->orderBy('name')->get(['users.id', 'users.name', 'users.email']);
        $services = Service::query()->where('tenant_id', $tenant->id)->where('status', true)->orderBy('name')->get(['id', 'name', 'unit']);
        $selectedServices = $provider->exists
            ? $provider->allServices()->wherePivot('status', true)->pluck('services.id')->map(fn ($id) => (int) $id)->all()
            : [];

        return view('services.providers-form', compact('provider', 'users', 'services', 'selectedServices'));
    }

    private function validated(Request $request, Tenant $tenant, ?ServiceProvider $provider = null): array
    {
        $availableRoles = array_keys(ServiceProvider::getAvailableRoles());

        return $request->validate([
            'user_id' => [
                'nullable', 'integer',
                Rule::exists('tenant_user', 'user_id')->where(fn ($query) => $query->where('tenant_id', $tenant->id)->where('status', true)),
                Rule::unique('service_providers', 'user_id')->where(fn ($query) => $query->where('tenant_id', $tenant->id))->ignore($provider?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'cpf' => ['nullable', 'string', 'max:14', Rule::unique('service_providers', 'cpf')->where(fn ($query) => $query->where('tenant_id', $tenant->id))->ignore($provider?->id)],
            'rg' => ['nullable', 'string', 'max:20'],
            'type' => ['required', Rule::in(['tratorista', 'motorista', 'diarista', 'tecnico', 'consultor', 'outro'])],
            'provider_roles' => ['nullable', 'array'],
            'provider_roles.*' => [Rule::in($availableRoles)],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:191'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'size:2'],
            'zip_code' => ['nullable', 'string', 'max:10'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_agency' => ['nullable', 'string', 'max:10'],
            'bank_account' => ['nullable', 'string', 'max:20'],
            'pix_key' => ['nullable', 'string', 'max:191'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', 'boolean'],
            'services' => ['nullable', 'array'],
            'services.*' => [
                'integer',
                Rule::exists('services', 'id')->where(fn ($query) => $query->where('tenant_id', $tenant->id)->where('status', true)),
            ],
        ]);
    }

    private function syncServices(ServiceProvider $provider, array $serviceIds, int $tenantId, int $actorId): void
    {
        $serviceIds = collect($serviceIds)->map(fn ($id) => (int) $id)->unique()->values();
        ServiceProviderService::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->where('service_provider_id', $provider->id)
            ->whereNotIn('service_id', $serviceIds)->update(['status' => false, 'updated_at' => now()]);

        foreach ($serviceIds as $serviceId) {
            $eligibility = ServiceProviderService::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->firstOrNew([
                    'service_provider_id' => $provider->id,
                    'service_id' => $serviceId,
                ]);
            $eligibility->tenant_id = $tenantId;
            $eligibility->status = true;
            $eligibility->save();
        }

        activity('service_provider')
            ->performedOn($provider)
            ->causedBy($actorId)
            ->withProperties(['tenant_id' => $tenantId, 'enabled_service_ids' => $serviceIds->all()])
            ->log('Habilitações de serviços do prestador atualizadas');
    }

    private function provider(Tenant $tenant, int $provider): ServiceProvider
    {
        return ServiceProvider::query()->where('tenant_id', $tenant->id)->findOrFail($provider);
    }

    private function allow(Request $request): void
    {
        abort_unless($request->user()->checkPermissionTo('manage_service_providers'), 403);
    }

    private function saved(Request $request, Tenant $tenant, ServiceProvider $provider, string $message): JsonResponse|RedirectResponse
    {
        $url = route('services.providers.edit', [$tenant, $provider]);
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => $message, 'url' => $url]);
        }

        return redirect($url)->with('success', $message);
    }
}
