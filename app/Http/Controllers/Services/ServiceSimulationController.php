<?php

namespace App\Http\Controllers\Services;

use App\Http\Controllers\Controller;
use App\Models\ServiceProvider;
use App\Models\ServiceSimulation;
use App\Models\ServiceVersion;
use App\Models\Tenant;
use App\Services\Services\ServiceSimulationEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class ServiceSimulationController extends Controller
{
    public function index(Request $request, Tenant $tenant): View
    {
        $this->allow($request);
        ServiceSimulation::query()->where('tenant_id', $tenant->id)->where('expires_at', '<', now())->delete();
        $versions = ServiceVersion::query()->where('tenant_id', $tenant->id)
            ->with(['service', 'fields', 'providerRates'])->orderByDesc('id')->get();
        $providers = ServiceProvider::query()->where('tenant_id', $tenant->id)->where('status', true)->orderBy('name')->get();
        $simulations = ServiceSimulation::query()->where('tenant_id', $tenant->id)
            ->with(['version.service', 'provider', 'creator'])->latest()->paginate(20);

        return view('services.simulations-index', compact('versions', 'providers', 'simulations'));
    }

    public function store(Request $request, Tenant $tenant, ServiceSimulationEngine $engine): RedirectResponse
    {
        $this->allow($request);
        $data = $request->validate([
            'service_version_id' => 'required|integer',
            'service_provider_id' => 'nullable|integer',
            'name' => 'nullable|string|max:191',
            'auto_fill' => 'nullable|boolean',
            'values' => 'nullable|array',
            'values.*' => ['nullable', function (string $attribute, mixed $value, $fail): void {
                if (! is_scalar($value)) $fail('Os dados da simulação devem ser valores simples.');
            }],
        ]);
        $version = ServiceVersion::query()->where('tenant_id', $tenant->id)->whereKey($data['service_version_id'])
            ->with(['service', 'fields', 'providerRates'])->firstOrFail();
        $provider = filled($data['service_provider_id'] ?? null)
            ? ServiceProvider::query()->where('tenant_id', $tenant->id)->where('status', true)->findOrFail($data['service_provider_id'])
            : null;
        if (! $provider && $version->payable_enabled) {
            $provider = ServiceProvider::query()->where('tenant_id', $tenant->id)->where('status', true)
                ->whereHas('services', fn ($query) => $query->whereKey($version->service_id))->first()
                ?? ServiceProvider::query()->where('tenant_id', $tenant->id)->where('status', true)->first();
        }

        $status = 'success';
        $result = null;
        $values = (array) ($data['values'] ?? []);
        $diagnostics = [];
        try {
            $simulation = $engine->run($version, $provider, $values, $request->boolean('auto_fill'));
            $values = $simulation['values'];
            $result = $simulation['result'];
            $diagnostics = $simulation['diagnostics'];
        } catch (ValidationException $exception) {
            $status = 'error';
            $diagnostics = ['errors' => $exception->errors(), 'temporary_order_rolled_back' => true];
        } catch (Throwable $exception) {
            report($exception);
            $status = 'error';
            $diagnostics = ['errors' => ['simulation' => [$exception->getMessage()]], 'temporary_order_rolled_back' => true];
        }

        $record = new ServiceSimulation([
            'service_version_id' => $version->id,
            'service_provider_id' => $provider?->id,
            'created_by' => $request->user()->id,
            'name' => trim((string) ($data['name'] ?? '')) ?: $version->service->name.' · v'.$version->version.' · '.now()->format('d/m H:i'),
            'status' => $status,
            'auto_filled' => $request->boolean('auto_fill'),
            'values' => $values,
            'result' => $result,
            'diagnostics' => $diagnostics,
            'expires_at' => now()->addDays(30),
        ]);
        $record->tenant_id = $tenant->id;
        $record->save();

        return redirect()->route('services.simulations.show', [$tenant, $record]);
    }

    public function show(Request $request, Tenant $tenant, int $simulation): View
    {
        $this->allow($request);
        $simulation = ServiceSimulation::query()->where('tenant_id', $tenant->id)->whereKey($simulation)
            ->with(['version.service', 'version.fields', 'provider', 'creator'])->firstOrFail();

        return view('services.simulations-show', compact('simulation'));
    }

    public function destroy(Request $request, Tenant $tenant, int $simulation): RedirectResponse
    {
        $this->allow($request);
        ServiceSimulation::query()->where('tenant_id', $tenant->id)->whereKey($simulation)->firstOrFail()->delete();

        return redirect()->route('services.simulations.index', $tenant)->with('success', 'Simulação excluída. Nenhuma OS ou obrigação real foi afetada.');
    }

    private function allow(Request $request): void
    {
        abort_unless($request->user()->checkPermissionTo('simulate_services'), 403);
    }
}
