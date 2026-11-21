<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Models\Associate;
use App\Models\ChartAccount;
use App\Models\Document;
use App\Models\Expense;
use App\Models\ServiceExecutionEvidence;
use App\Models\ServiceObligation;
use App\Models\ServiceOrder;
use App\Models\ServiceProvider;
use App\Models\ServiceVersion;
use App\Models\Tenant;
use App\Services\Services\CreateServiceOrder;
use App\Services\Services\ServiceCompositionCalculator;
use App\Services\Services\ServiceEvidenceService;
use App\Services\Services\ServiceExecutionWorkflow;
use App\Services\Services\ServiceExpenseAttachmentService;
use App\Services\Services\ServiceExpenseService;
use App\Services\Services\ServicePayoutRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ServiceProviderPortalController extends Controller
{
    private const EXECUTION_EDIT_PHASES = ['start', 'execution', 'finish'];

    public function index(Request $request, Tenant $tenant): View|\Illuminate\Http\Response
    {
        [$provider, $operator] = $this->access($request, $tenant);
        $base = ServiceOrder::query()->where('tenant_id', $tenant->id)->when(! $operator, fn ($query) => $query->where('service_provider_id', $provider->id))->with(['service', 'execution.obligations']);
        $date = DB::raw('COALESCE(scheduled_at, scheduled_date)');
        $today = today();
        $requestedDate = $request->query('agenda_date');
        $selectedDay = $today->copy();
        if (is_string($requestedDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $requestedDate)
            && checkdate((int) substr($requestedDate, 5, 2), (int) substr($requestedDate, 8, 2), (int) substr($requestedDate, 0, 4))) {
            $selectedDay = $today->copy()->setDate((int) substr($requestedDate, 0, 4), (int) substr($requestedDate, 5, 2), (int) substr($requestedDate, 8, 2));
        }
        $start = $selectedDay->copy()->startOfMonth();
        $end = $start->copy()->addMonth();
        $agenda = (clone $base)
            ->where($date, '>=', $start->toDateString())->where($date, '<', $end->toDateString())
            ->orderBy($date)->orderBy('id')->get();
        $agendaDays = $agenda->groupBy(fn (ServiceOrder $order) => ($order->scheduled_at ?? $order->scheduled_date)->toDateString());
        $agendaWindow = [
            'previous' => $start->copy()->subMonth()->toDateString(),
            'next' => $end->toDateString(),
        ];
        $tenantSlug = $tenant->slug;
        $statuses = app(\App\Services\Services\ServiceHumanStatusResolver::class);
        $calendarData = compact('agendaDays', 'start', 'end', 'today', 'selectedDay', 'agendaWindow', 'tenantSlug', 'statuses');

        if ($request->query('agenda_fragment') === '1') {
            return response()->view('provider._agenda-month', $calendarData)->header('Cache-Control', 'private, no-store');
        }
        $pending = (clone $base)->whereIn('operational_status', ['in_progress', 'submitted', 'rejected'])->orderBy('scheduled_at')->get();
        $payables = ServiceObligation::query()->where('tenant_id', $tenant->id)->when(! $operator, fn ($query) => $query->where('service_provider_id', $provider->id))->where('direction', 'payable')->where('status', '!=', 'cancelled')->get();

        return view('provider.services-dashboard', $calendarData + compact('provider', 'operator', 'pending') + ['due' => $payables->sum('total_amount'), 'paid' => $payables->sum('paid_amount'), 'balance' => $payables->sum('balance')]);
    }

    public function orders(Request $request, Tenant $tenant): View
    {
        [$provider, $operator] = $this->access($request, $tenant);
        $orders = ServiceOrder::query()->where('tenant_id', $tenant->id)->when(! $operator, fn ($query) => $query->where('service_provider_id', $provider->id))->with(['service', 'serviceProvider', 'execution.obligations'])->latest('scheduled_at')->paginate(20);

        return view('provider.services-orders', compact('provider', 'operator', 'orders'));
    }

    public function create(Request $request, Tenant $tenant): View
    {
        [$provider, $operator] = $this->access($request, $tenant);
        $versions = $this->availableVersions($tenant, $provider, $operator)->get();
        $providers = $operator ? ServiceProvider::query()->where('tenant_id', $tenant->id)->where('status', true)->orderBy('name')->get() : collect([$provider]);

        $associates = Associate::query()->where('tenant_id', $tenant->id)->active()->orderBy('id')->get();

        return view('provider.services-create', compact('provider', 'operator', 'providers', 'versions', 'associates'));
    }

    public function offlineData(Request $request, Tenant $tenant): JsonResponse
    {
        [$provider, $operator] = $this->access($request, $tenant);
        $issued = now();
        $providers = $operator ? ServiceProvider::query()->where('tenant_id', $tenant->id)->where('status', true)
            ->with('services')->orderBy('name')->get() : collect([$provider]);
        $catalog = $this->availableVersions($tenant, $provider, $operator)->with('providerRates')->get()
            ->filter(fn (ServiceVersion $version) => ! $version->service->current_version_id || (int) $version->service->current_version_id === (int) $version->id)
            ->map(function (ServiceVersion $version) use ($provider, $operator, $providers): array {
                $snapshot = $version->snapshot();
                $eligible = $operator
                    ? $providers->filter(fn (ServiceProvider $candidate) => $candidate->services->contains($version->service_id))
                    : $providers;
                $eligibleIds = $eligible->pluck('id')->values()->all();
                $rates = $version->providerRates->where('active', true)->whereIn('service_provider_id', $eligibleIds)
                    ->mapWithKeys(fn ($rate) => [$rate->service_provider_id => [
                        'method' => $version->provider_pricing_method,
                        'rate' => $version->provider_pricing_method === 'fixed'
                            ? ($rate->fixed_amount ?? $rate->rate ?? $version->default_provider_rate)
                            : ($rate->rate ?? $rate->fixed_amount ?? $version->default_provider_rate),
                        'percentage' => $rate->percentage ?? $version->provider_percentage,
                    ]]);
                if (! $operator && $rates->has($provider->id)) {
                    $snapshot['provider_compensation'] = $rates->get($provider->id);
                }

                return [
                    'id' => $version->id, 'name' => $version->service->name, 'unit' => $version->unit,
                    'membersOnly' => (bool) $version->members_only, 'catalog' => $this->offlineCatalog($snapshot, $operator),
                    'providerIds' => $eligibleIds, 'providerRates' => $operator ? $rates : [],
                ];
            })->values();
        $orders = ServiceOrder::query()->where('tenant_id', $tenant->id)
            ->when(! $operator, fn ($query) => $query->where('service_provider_id', $provider->id))
            ->forOfflineFieldwork($issued)
            ->with(['service', 'serviceProvider', 'execution.evidences', 'execution.obligations', 'execution.resources'])
            ->orderByRaw(ServiceOrder::FIELDWORK_DATE)->orderBy('scheduled_at')->orderBy('id')->get()
            ->map(fn (ServiceOrder $order) => $this->offlineOrder($order, $operator))->values();
        $members = $catalog->contains('membersOnly', true)
            ? Associate::query()->where('tenant_id', $tenant->id)->active()->get()
                ->map(fn (Associate $member) => ['id' => $member->id, 'name' => $member->nickname ?: $member->display_name])->values()
            : [];
        return response()->json([
            'scope' => $this->offlineScope($request, $tenant, $provider),
            'user' => (string) $request->user()->id, 'tenant' => $tenant->slug, 'organization' => $tenant->name,
            'logo' => $this->offlineLogo($tenant),
            'provider' => $provider?->name, 'providerId' => $provider?->id, 'operator' => $operator,
            'providers' => $providers->map(fn (ServiceProvider $candidate) => ['id' => $candidate->id, 'name' => $candidate->name])->values(),
            'catalog' => $catalog, 'orders' => $orders, 'members' => $members,
            'issuedAt' => $issued->toIso8601String(),
            'timezone' => config('app.timezone'),
            'expiresAt' => $issued->copy()->addMinutes(min(720, max(1, (int) config('session.lifetime'))))->toIso8601String(),
            'syncUrl' => route('provider.offline.sync', $tenant->slug), 'portalUrl' => route('provider.dashboard', $tenant->slug),
            'csrf' => csrf_token(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function offlineSync(Request $request, Tenant $tenant, CreateServiceOrder $creator, ServiceExecutionWorkflow $workflow, ServiceEvidenceService $evidence): JsonResponse
    {
        [$provider, $operator] = $this->access($request, $tenant);
        $input = $request->validate([
            'scope' => 'required|string|max:100', 'operation_key' => 'required|uuid', 'order_id' => 'nullable|integer|min:1',
            'lock_version' => 'required_with:order_id|nullable|integer|min:0', 'action' => 'required|in:schedule,draft,start,finish',
            'creation' => 'nullable|required_without:order_id|array:service_version_id,service_provider_id,beneficiary_name,beneficiary_whatsapp,associate_id,scheduled_at,location,order_data',
            'creation.service_version_id' => 'required_without:order_id|integer|min:1',
            'creation.service_provider_id' => 'nullable|integer|min:1',
            'creation.beneficiary_name' => 'required_without:order_id|string|max:191',
            'creation.beneficiary_whatsapp' => ['nullable', 'regex:/\A\+?[0-9 ()-]{10,25}\z/'],
            'creation.associate_id' => 'nullable|integer|min:1',
            'creation.scheduled_at' => 'required_without:order_id|date',
            'creation.location' => 'nullable|string|max:191',
            'creation.order_data' => 'array|max:120',
            'creation.order_data.*' => 'nullable|string|max:10000',
            'values' => 'array|max:120', 'values.*' => 'nullable|string|max:10000',
            'recorded_at' => 'nullable|date|before_or_equal:now',
            'started_at' => 'nullable|date|before_or_equal:recorded_at',
            'evidences' => 'array|max:12', 'evidences.*' => 'file|max:12288',
        ]);
        abort_unless(hash_equals($this->offlineScope($request, $tenant, $provider), $input['scope']), 403);
        $identity = ['tenant_id' => $tenant->id, 'provider_id' => $provider?->id ?? 0,
            'user_id' => $request->user()->id, 'operation_key' => $input['operation_key']];
        $localFiles = [];
        try {
            $order = DB::transaction(function () use ($request, $tenant, $provider, $operator, $input, $identity, $creator, $workflow, $evidence, &$localFiles) {
                if ($operator) {
                    $request->user()->newQuery()->lockForUpdate()->findOrFail($request->user()->id);
                } else {
                    ServiceProvider::query()->where('tenant_id', $tenant->id)->where('status', true)
                        ->where('user_id', $request->user()->id)->lockForUpdate()->findOrFail($provider->id);
                }
                $previous = DB::table('service_offline_operations')->where($identity)->value('result');
                if ($previous) {
                    return $this->order($tenant, $provider, $operator, json_decode($previous, true, flags: JSON_THROW_ON_ERROR)['order_id']);
                }
                if (! empty($input['order_id'])) {
                    $order = $this->order($tenant, $provider, $operator, $input['order_id']);
                    $execution = $order->execution()->lockForUpdate()->firstOrFail();
                    abort_if((! $operator && (int) $execution->service_provider_id !== (int) $provider->id)
                        || (int) $execution->lock_version !== (int) $input['lock_version']
                        || ! in_array($execution->status, ['draft', 'in_progress', 'rejected'], true), 409,
                        'A ordem foi alterada no servidor. Confira os dados antes de reenviar.');
                    abort_if($input['action'] === 'schedule', 422);
                    $order->setRelation('execution', $execution);
                } else {
                    $creation = $input['creation'];
                    $version = $this->availableVersions($tenant, $provider, $operator)->findOrFail($creation['service_version_id']);
                    $providerId = $provider?->id;
                    if ($operator) {
                        $selected = $creation['service_provider_id'] ?? $providerId;
                        if (! $selected) {
                            throw ValidationException::withMessages(['creation.service_provider_id' => 'Selecione o prestador da ordem.']);
                        }
                        $providerId = ServiceProvider::query()->where('tenant_id', $tenant->id)
                            ->where('status', true)->findOrFail($selected)->id;
                    }
                    $allowedKeys = $version->fields->filter(fn ($field) => $field->phase === 'order'
                        && ($operator ? $field->visible_to_management : ($field->visible_to_provider && $field->editable_by_provider)))->pluck('key')->all();
                    $creation['order_data'] = array_intersect_key($creation['order_data'] ?? [], array_flip($allowedKeys));
                    $creation['service_provider_id'] = $providerId;
                    $order = $creator->handle($tenant->id, $version, $creation, $request->user());
                    $execution = $order->execution;
                }
                if ($input['action'] !== 'schedule') {
                    $values = $this->portalValues($order, $input['values'] ?? [], self::EXECUTION_EDIT_PHASES, $operator);
                    $this->uploadSubmittedEvidences($request, $order, $operator, self::EXECUTION_EDIT_PHASES, $evidence,
                        function (string $path) use (&$localFiles): void { $localFiles[] = $path; });
                    $recordedAt = isset($input['recorded_at']) ? \Illuminate\Support\Carbon::parse($input['recorded_at'])->timezone(config('app.timezone')) : null;
                    if (in_array($input['action'], ['start', 'finish'], true) && $execution->status !== 'in_progress') {
                        $startedAt = isset($input['started_at']) ? \Illuminate\Support\Carbon::parse($input['started_at'])->timezone(config('app.timezone')) : $recordedAt;
                        $execution = $workflow->start($execution, $this->portalValues($order, $values, ['start'], $operator),
                            $input['operation_key'], $request->user(), $startedAt);
                    }
                    if ($input['action'] === 'finish') {
                        $workflow->submit($execution, $values, $input['operation_key'], $request->user(), $recordedAt);
                    } else {
                        $workflow->saveDraft($execution, $values, $request->user());
                    }
                }
                DB::table('service_offline_operations')->insert($identity + [
                    'result' => json_encode(['order_id' => $order->id], JSON_THROW_ON_ERROR), 'created_at' => now(),
                ]);

                return $order->fresh(['service', 'serviceProvider', 'execution.evidences', 'execution.obligations', 'execution.resources']);
            });
        } catch (\Throwable $error) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($localFiles);
            throw $error;
        }

        $available = ServiceOrder::query()->where('tenant_id', $tenant->id)->whereKey($order->id)->forOfflineFieldwork(now())->exists();

        return response()->json(['order' => $available ? $this->offlineOrder($order, $operator) : ['id' => $order->id], 'available' => $available])
            ->header('Cache-Control', 'private, no-store');
    }

    private function offlineScope(Request $request, Tenant $tenant, ?ServiceProvider $provider): string
    {
        return implode(':', [$request->user()->id, $tenant->id, $provider?->id ?? 0]);
    }

    private function offlineCatalog(array $snapshot, bool $operator): array
    {
        $snapshot['meter_pair'] = \App\Services\Services\ServiceCalculationRules::meterPair($snapshot);
        $snapshot['fields'] = collect($snapshot['fields'] ?? [])
            ->filter(fn (array $field) => $operator ? ($field['visible_to_management'] ?? true) : ($field['visible_to_provider'] ?? false))
            ->map(fn (array $field) => $operator ? array_replace($field, ['editable_by_provider' => true]) : $field)
            ->values()->all();

        return $snapshot;
    }

    private function offlineLogo(Tenant $tenant): ?string
    {
        $root = realpath(public_path('storage'));
        $name = ltrim((string) $tenant->logo, '/');
        if (! $root || $name === '' || ! function_exists('imagejpeg')) {
            return null;
        }
        $file = realpath($root.DIRECTORY_SEPARATOR.$name);
        if (! $file || ! str_starts_with($file, $root.DIRECTORY_SEPARATOR) || ! is_file($file) || filesize($file) > 2_000_000) {
            return null;
        }
        $size = @getimagesize($file);
        if (! $size || $size[0] * $size[1] > 4_000_000) {
            return null;
        }
        $image = @imagecreatefromstring((string) file_get_contents($file));
        if (! $image) {
            return null;
        }
        $side = 72;
        $ratio = $side / max($size[0], $size[1]);
        $width = max(1, (int) round($size[0] * $ratio));
        $height = max(1, (int) round($size[1] * $ratio));
        $canvas = imagecreatetruecolor($side, $side);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $image, intdiv($side - $width, 2), intdiv($side - $height, 2), 0, 0,
            $width, $height, $size[0], $size[1]);
        ob_start();
        imagejpeg($canvas, null, 85);

        return base64_encode((string) ob_get_clean());
    }

    private function offlineOrder(ServiceOrder $order, bool $operator): array
    {
        $execution = $order->execution;
        $catalog = $this->offlineCatalog((array) $execution->catalog_snapshot, $operator);
        $keys = array_column($catalog['fields'], 'key');
        foreach (array_filter($catalog['meter_pair']) as $field) {
            $keys[] = 'meter_pauses_'.$field;
        }

        return [
            'id' => $order->id, 'number' => $order->number, 'service' => $order->service?->name,
            'provider' => $order->serviceProvider?->name ?? data_get($order->provider_snapshot, 'name'),
            'beneficiary' => data_get($order->beneficiary_snapshot, 'name'), 'whatsapp' => data_get($order->beneficiary_snapshot, 'whatsapp'),
            'location' => $order->location, 'scheduled' => ($order->scheduled_at ?? $order->scheduled_date)?->format('Y-m-d\TH:i'),
            'executionDate' => $order->execution_date?->toDateString(),
            'status' => app(\App\Services\Services\ServiceHumanStatusResolver::class)->resolve($order),
            'state' => $order->operational_status ?? $order->status?->value, 'execution' => $execution->status, 'lockVersion' => $execution->lock_version,
            'unit' => $execution->unit, 'quantity' => $execution->quantity, 'catalog' => $catalog,
            'totals' => $execution->isFrozen() ? [
                'quantity' => $execution->quantity, 'receivable' => $order->final_price, 'payable' => $order->provider_payment,
            ] : null,
            'resources' => $execution->resources->map(fn ($resource) => ['effect' => $resource->effect, 'amount' => $resource->amount])->values(),
            'values' => array_intersect_key($execution->values ?? [], array_flip($keys)),
            'evidences' => $execution->evidences->whereIn('field_key', $keys)->pluck('field_key')->unique()->values(),
        ];
    }


    public function store(Request $request, Tenant $tenant, CreateServiceOrder $creator): RedirectResponse
    {
        [$provider, $operator] = $this->access($request, $tenant);
        $data = $request->validate(['service_version_id' => 'required|integer', 'service_provider_id' => $operator ? 'required|integer' : 'nullable', 'associate_id' => 'nullable|integer', 'beneficiary_name' => 'nullable|string|max:191', 'beneficiary_whatsapp' => ['nullable', 'regex:/\A\+?[0-9 ()-]{10,25}\z/'], 'asset_id' => 'nullable|integer', 'scheduled_at' => 'required|date', 'location' => 'nullable|string|max:191', 'order_data' => 'array']);
        if ($operator) {
            $provider = ServiceProvider::query()->where('tenant_id', $tenant->id)->where('status', true)->whereKey($data['service_provider_id'])->firstOrFail();
        }
        $version = $this->availableVersions($tenant, $provider, $operator)->whereKey($data['service_version_id'])->firstOrFail();
        $associate = filled($data['associate_id'] ?? null)
            ? Associate::query()->where('tenant_id', $tenant->id)->active()->find($data['associate_id'])
            : null;
        $beneficiaryName = trim((string) ($data['beneficiary_name'] ?? ''));
        if ($beneficiaryName === '' && $associate) {
            $beneficiaryName = trim((string) ($associate->nickname ?: $associate->display_name));
        }
        if ($beneficiaryName === '') {
            throw ValidationException::withMessages(['beneficiary_name' => 'Informe o nome/apelido ou selecione um membro.']);
        }
        $order = $creator->handle($tenant->id, $version, $data + ['service_provider_id' => $provider->id, 'beneficiary_name' => $beneficiaryName], $request->user());

        return redirect()->route('provider.orders.show', [$tenant, $order]);
    }

    public function show(Request $request, Tenant $tenant, int $order): View
    {
        [$provider, $operator] = $this->access($request, $tenant);
        $order = $this->order($tenant, $provider, $operator, $order);
        $financialPreview = null;
        try {
            if ($order->execution && $order->operational_status !== 'cancelled') {
                $financialPreview = in_array($order->execution->status, ['draft', 'in_progress', 'rejected'], true)
                    ? app(ServiceExecutionWorkflow::class)->preview($order->execution, [])
                    : app(ServiceCompositionCalculator::class)->calculate($order->execution, false);
            }
        } catch (ValidationException) {
            // Campos ainda incompletos: a tela mostra as tarifas e aguarda os dados.
        }

        return view('provider.services-show', compact('provider', 'operator', 'order', 'financialPreview'));
    }

    public function previewExecution(Request $request, Tenant $tenant, int $order, ServiceExecutionWorkflow $workflow): JsonResponse
    {
        [$provider, $operator] = $this->access($request, $tenant);
        $order = $this->order($tenant, $provider, $operator, $order);
        abort_unless($order->execution?->status === 'in_progress', 409);
        $data = $request->validate(['values' => 'array|max:200', 'values.*' => 'nullable|string|max:10000']);
        $values = $this->portalValues($order, $data['values'] ?? [], self::EXECUTION_EDIT_PHASES, $operator);
        $totals = $workflow->preview($order->execution, $values);

        return response()->json([
            'quantity' => $totals['primary_quantity'],
            'receivable' => $totals['receivable_total'],
            'payable' => $totals['payable_total'],
        ]);
    }

    public function start(Request $request, Tenant $tenant, int $order, ServiceExecutionWorkflow $workflow, ServiceEvidenceService $evidence): RedirectResponse|JsonResponse
    {
        [$provider, $operator] = $this->access($request, $tenant);
        $order = $this->order($tenant, $provider, $operator, $order);
        $data = $request->validate(['operation_key' => 'required|uuid', 'values' => 'array', 'evidences' => 'array', 'evidences.*' => 'nullable|file|max:12288']);
        DB::transaction(function () use ($request, $order, $operator, $data, $workflow, $evidence): void {
            $this->uploadSubmittedEvidences($request, $order, $operator, ['start'], $evidence);
            $workflow->start($order->execution, $this->portalValues($order, $data['values'] ?? [], ['start'], $operator), $data['operation_key'], $request->user());
        }, 3);

        return $this->mutationResponse($request, $tenant, $order->fresh(['execution.evidences.document']), 'Serviço iniciado.', true);
    }

    public function draft(Request $request, Tenant $tenant, int $order, ServiceExecutionWorkflow $workflow, ServiceEvidenceService $evidence): RedirectResponse|JsonResponse
    {
        [$provider, $operator] = $this->access($request, $tenant);
        $order = $this->order($tenant, $provider, $operator, $order);
        $data = $request->validate(['values' => 'array', 'evidences' => 'array', 'evidences.*' => 'nullable|file|max:12288']);
        DB::transaction(function () use ($request, $order, $operator, $data, $workflow, $evidence): void {
            $workflow->saveDraft($order->execution, $this->portalValues($order, $data['values'] ?? [], self::EXECUTION_EDIT_PHASES, $operator), $request->user());
            $this->uploadSubmittedEvidences($request, $order, $operator, self::EXECUTION_EDIT_PHASES, $evidence);
        }, 3);

        return $this->mutationResponse($request, $tenant, $order->fresh(['execution.evidences.document']), 'Rascunho salvo.', false);
    }

    public function submit(Request $request, Tenant $tenant, int $order, ServiceExecutionWorkflow $workflow, ServiceEvidenceService $evidence): RedirectResponse|JsonResponse
    {
        [$provider, $operator] = $this->access($request, $tenant);
        $order = $this->order($tenant, $provider, $operator, $order);
        $data = $request->validate(['operation_key' => 'required|uuid', 'values' => 'array', 'evidences' => 'array', 'evidences.*' => 'nullable|file|max:12288']);
        $execution = DB::transaction(function () use ($request, $order, $operator, $data, $workflow, $evidence) {
            $this->uploadSubmittedEvidences($request, $order, $operator, self::EXECUTION_EDIT_PHASES, $evidence);

            return $workflow->submit($order->execution, $this->portalValues($order, $data['values'] ?? [], self::EXECUTION_EDIT_PHASES, $operator), $data['operation_key'], $request->user());
        }, 3);

        $message = $execution->status === 'validated' ? 'Serviço validado e obrigações geradas.' : 'Serviço enviado para conferência.';

        return $this->mutationResponse($request, $tenant, $order->fresh(['execution.evidences.document']), $message, true);
    }

    public function upload(Request $request, Tenant $tenant, int $order, ServiceEvidenceService $evidence): RedirectResponse
    {
        [$provider, $operator] = $this->access($request, $tenant);
        $order = $this->order($tenant, $provider, $operator, $order);
        $data = $request->validate(['field_key' => 'required|string|max:80', 'file' => 'required|file|max:12288']);
        $allowed = collect(data_get($order->execution->catalog_snapshot, 'fields', []))->contains(fn (array $field) => $field['key'] === $data['field_key']
            && in_array($field['type'] ?? null, ['image', 'file', 'signature'], true)
            && ($operator ? ($field['visible_to_management'] ?? true) : (($field['visible_to_provider'] ?? false) && ($field['editable_by_provider'] ?? false))));
        abort_unless($allowed, 422, 'Esta evidência não pode ser enviada pelo prestador.');
        $evidence->upload($order->execution, $data['field_key'], $data['file'], $request->user());

        return back()->with('success', 'Evidência anexada.');
    }

    public function approve(Request $request, Tenant $tenant, int $order, ServiceExecutionWorkflow $workflow): RedirectResponse|JsonResponse
    {
        [$provider, $operator] = $this->access($request, $tenant);
        abort_unless($operator && $request->user()->checkPermissionTo('approve_service_execution'), 403);
        $order = $this->order($tenant, $provider, true, $order);
        $data = $request->validate(['operation_key' => 'required|uuid']);
        $expectedVersion = $request->validate(['lock_version' => 'required|integer|min:0'])['lock_version'];
        $workflow->approve($order->execution, $data['operation_key'], $request->user(), (int) $expectedVersion);

        return $this->mutationResponse($request, $tenant, $order->fresh(['execution.evidences.document']), 'Execução conferida e concluída.', true);
    }

    public function financial(Request $request, Tenant $tenant): View
    {
        [$provider, $operator] = $this->access($request, $tenant);
        $query = ServiceObligation::query()->where('tenant_id', $tenant->id)->when(! $operator, fn ($query) => $query->where('service_provider_id', $provider->id))->where('direction', 'payable');
        $due = (float) (clone $query)->where('status', '!=', 'cancelled')->selectRaw('COALESCE(SUM(principal_amount + adjustment_amount), 0) AS aggregate')->value('aggregate');
        $paid = (float) DB::table('service_payment_allocations as allocation')
            ->join('service_obligations as obligation', 'obligation.id', '=', 'allocation.service_obligation_id')
            ->join('service_payment_events as payment', 'payment.id', '=', 'allocation.service_payment_event_id')
            ->where('obligation.tenant_id', $tenant->id)
            ->where('obligation.direction', 'payable')
            ->when(! $operator, fn ($builder) => $builder->where('obligation.service_provider_id', $provider->id))
            ->whereIn('payment.status', ['confirmed', 'reversed'])
            ->sum('allocation.amount');
        $summary = ['due' => round($due, 2), 'paid' => round($paid, 2), 'balance' => max(0, round($due - $paid, 2))];
        $obligations = $query->with(['execution.order.service', 'provider'])->latest()->paginate(20);

        return view('provider.services-financial', compact('provider', 'operator', 'obligations', 'summary'));
    }

    public function payout(Request $request, Tenant $tenant, ServicePayoutRequestService $service): RedirectResponse
    {
        $provider = $this->provider($request, $tenant);
        $request->merge(['amounts' => array_filter((array) $request->input('amounts', []), fn ($value) => (float) $value > 0)]);
        $data = $request->validate(['operation_key' => 'required|uuid', 'amounts' => 'required|array|min:1', 'amounts.*' => 'numeric|min:0.01']);
        $service->create($provider, $data['amounts'], $data['operation_key'], $request->user());

        return back()->with('success', 'Solicitação enviada para análise.');
    }

    public function evidence(Request $request, Tenant $tenant, int $evidence, ServiceEvidenceService $files)
    {
        [$provider, $operator] = $this->access($request, $tenant);
        $record = ServiceExecutionEvidence::query()->where('tenant_id', $tenant->id)->whereKey($evidence)->when(! $operator, fn ($query) => $query->whereHas('execution', fn ($q) => $q->where('service_provider_id', $provider->id)))->with('document')->firstOrFail();

        return response($files->contents($record->document), 200, ['Content-Type' => $record->document->mime_type, 'Content-Disposition' => 'inline; filename="'.str_replace('"', '', $record->document->original_name).'"']);
    }

    public function expenses(Request $request, Tenant $tenant): View
    {
        [$management, $reportingProvider] = $this->expenseAccess($request, $tenant);
        $selectedOrder = $request->integer('order') ?: null;
        $expenses = Expense::query()->where('tenant_id', $tenant->id)->where('origin_module', 'services')
            ->when($reportingProvider, fn ($query) => $query->where('service_provider_id', $reportingProvider->id))
            ->when($selectedOrder, fn ($query) => $query->where(fn ($linked) => $linked
                ->whereMorphedTo('expenseable', ServiceOrder::class)->where('expenseable_id', $selectedOrder)
                ->orWhereHasMorph('expenseable', [\App\Models\ServiceExecution::class], fn ($execution) => $execution->where('tenant_id', $tenant->id)->where('service_order_id', $selectedOrder))))
            ->with(['expenseable', 'documents', 'serviceProvider:id,name'])->latest('date')->paginate(25)->withQueryString();
        $orders = ServiceOrder::query()->where('tenant_id', $tenant->id)->whereNotNull('service_version_id')
            ->when($reportingProvider, fn ($query) => $query->where('service_provider_id', $reportingProvider->id))
            ->where(fn ($query) => $query->whereNull('operational_status')->orWhere('operational_status', '!=', 'cancelled'))
            ->when($selectedOrder, fn ($query) => $query->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$selectedOrder]))
            ->latest()->limit(200)->get(['id', 'number', 'beneficiary_snapshot', 'scheduled_at']);
        $accounts = ChartAccount::query()->where('tenant_id', $tenant->id)->where('type', 'despesa')->orderBy('name')->get(['id', 'name']);
        $expenseProviders = $reportingProvider ? collect() : ServiceProvider::query()->where('tenant_id', $tenant->id)
            ->where('status', true)->orderBy('name')->get(['id', 'name', 'user_id']);
        $defaultProviderId = $expenseProviders->firstWhere('user_id', $request->user()->id)?->id;

        $selectedOrder = $orders->contains('id', $selectedOrder) ? $selectedOrder : null;
        $unlinkedExpenses = $management && $selectedOrder
            ? Expense::query()->where('tenant_id', $tenant->id)->where('origin_module', 'services')->pending()
                ->whereNull('expenseable_type')->whereNull('expenseable_id')->latest('date')->limit(200)
                ->get(['id', 'description', 'amount', 'date'])
            : collect();

        return view('provider.services-expenses', compact(
            'management', 'expenses', 'orders', 'accounts', 'selectedOrder', 'unlinkedExpenses',
            'expenseProviders', 'reportingProvider', 'defaultProviderId'
        ));
    }

    public function linkExpense(Request $request, Tenant $tenant, ServiceExpenseService $service): RedirectResponse
    {
        $this->expenseAccess($request, $tenant);
        $data = $request->validate(['service_order_id' => 'required|integer', 'expense_id' => 'required|integer']);
        $service->link($tenant, (int) $data['service_order_id'], (int) $data['expense_id'], $request->user());

        return back()->with('success', 'Despesa vinculada à ordem.');
    }

    public function showExpense(Request $request, Tenant $tenant, int $expense): View
    {
        [$management, $provider] = $this->expenseAccess($request, $tenant);
        $record = Expense::query()->where('tenant_id', $tenant->id)->where('origin_module', 'services')
            ->when($provider, fn ($query) => $query->where('service_provider_id', $provider->id))
            ->with(['documents', 'expenseable', 'serviceProvider'])->findOrFail($expense);
        $resource = \App\Models\ServiceExecutionResource::query()->where('tenant_id', $tenant->id)->where('expense_id', $record->id)->first();
        $editable = ! $resource && (float) $record->paid_amount <= 0 && in_array($record->status->value, ['pending', 'overdue'], true);
        $canConvert = ! $provider && ! $resource && $record->status->value !== 'cancelled'
            && $request->user()->checkPermissionTo('manage_service_resources')
            && $request->user()->checkPermissionTo('manage_service_receivables');
        $order = $record->linkedServiceOrder();
        $linkedOrder = $order && $order->operational_status !== 'cancelled' ? $order->id : null;
        $canChangeLink = ! $resource && $record->status->value !== 'cancelled';
        $canLink = $canChangeLink && ! $linkedOrder;
        $orderSearch = trim(mb_substr($request->string('ordem')->toString(), 0, 80));
        $orders = ($canConvert || $canLink) ? ServiceOrder::query()->where('tenant_id', $tenant->id)->whereNotNull('service_version_id')
            ->when($provider, fn ($query) => $query->where('service_provider_id', $provider->id))
            ->when(! $canLink, fn ($query) => $query->whereHas('execution'))
            ->where(fn ($query) => $query->whereNull('operational_status')->orWhere('operational_status', '!=', 'cancelled'))
            ->when($linkedOrder, fn ($query) => $query->whereKey($linkedOrder))
            ->when($canLink && $orderSearch !== '', fn ($query) => $query->where(fn ($matches) => $matches
                ->whereLike('number', '%'.$orderSearch.'%')->orWhereLike('beneficiary_snapshot->name', '%'.$orderSearch.'%')))
            ->latest()->limit(100)
            ->select(['id', 'number', 'beneficiary_snapshot'])->withExists('execution')->get() : collect();

        return view('provider.service-expense-detail', compact('tenant', 'management', 'provider', 'record', 'order', 'resource', 'editable', 'orders', 'canLink', 'canConvert', 'canChangeLink', 'orderSearch'));
    }

    public function manageExpense(Request $request, Tenant $tenant, int $expense, ServiceExpenseService $service, \App\Services\Services\ServiceResourceService $resources, ServiceExpenseAttachmentService $attachments): RedirectResponse
    {
        [$management, $provider] = $this->expenseAccess($request, $tenant);
        $data = $request->validate([
            'action' => 'required|in:edit,link,unlink,delete,convert,attach,remove_image',
            'description' => 'exclude_unless:action,edit|required|string|max:191',
            'amount' => 'exclude_unless:action,edit|required|numeric|min:0.01',
            'date' => 'exclude_unless:action,edit|required|date',
            'due_date' => 'exclude_unless:action,edit|required|date|after_or_equal:date',
            'document_number' => 'exclude_unless:action,edit|nullable|string|max:80',
            'notes' => 'exclude_unless:action,edit|nullable|string|max:2000',
            'service_order_id' => 'exclude_unless:action,convert,link|required|integer|min:1',
            'attachments' => 'exclude_unless:action,attach|required|array|min:1|max:5',
            'attachments.*' => 'file|mimes:jpg,jpeg,png,webp,pdf|max:12288',
            'document_id' => 'exclude_unless:action,remove_image|required|integer|min:1',
        ]);
        if (in_array($data['action'], ['attach', 'remove_image'], true)) {
            DB::transaction(function () use ($tenant, $expense, $provider, $data, $request, $attachments): void {
                $record = Expense::query()->where('tenant_id', $tenant->id)->where('origin_module', 'services')
                    ->when($provider, fn ($query) => $query->where('service_provider_id', $provider->id))
                    ->lockForUpdate()->findOrFail($expense);
                if ($data['action'] === 'remove_image') {
                    $attachments->removeImage($record, (int) $data['document_id']);

                    return;
                }
                if ($record->status->value === 'cancelled' || $record->documents()->count() + count($data['attachments']) > 5) {
                    throw ValidationException::withMessages(['attachments' => 'Despesas ativas aceitam até cinco comprovantes ao todo.']);
                }
                foreach ($data['attachments'] as $file) {
                    $attachments->upload($tenant, $record, $file, $request->user());
                }
            });
        } else {
            $service->manage($tenant, $expense, $data, $request->user(), $provider, $resources);
        }
        $base = $management ? 'services.management.expenses' : 'provider.expenses';

        return redirect()->route($data['action'] === 'delete' ? $base : $base.'.show', $data['action'] === 'delete'
            ? ['tenant' => $tenant->slug] : ['tenant' => $tenant->slug, 'expense' => $expense])
            ->with('success', match ($data['action']) {
                'delete' => 'Despesa excluída.',
                'unlink' => 'Vínculo com a ordem cancelado.',
                'link' => 'Despesa vinculada à ordem.',
                'convert' => 'Despesa registrada como abatimento na ordem.',
                'attach' => 'Comprovantes adicionados.',
                'remove_image' => 'Imagem removida da despesa.',
                default => 'Despesa atualizada.',
            });
    }

    public function storeExpense(Request $request, Tenant $tenant, ServiceExpenseService $service, ServiceExpenseAttachmentService $attachments): RedirectResponse|JsonResponse
    {
        [$management, $reportingProvider] = $this->expenseAccess($request, $tenant);
        $data = $request->validate([
            'description' => 'required|string|max:191', 'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date', 'due_date' => 'required|date|after_or_equal:date', 'service_order_id' => 'nullable|integer',
            'chart_account_id' => 'nullable|integer', 'document_number' => 'nullable|string|max:80', 'notes' => 'nullable|string|max:2000',
            'service_provider_id' => 'nullable|integer|min:1',
            'attachments' => 'nullable|array|max:5', 'attachments.*' => 'file|mimes:jpg,jpeg,png,webp,pdf|max:12288',
        ]);
        DB::transaction(function () use ($tenant, $data, $request, $service, $attachments, $reportingProvider): void {
            $expense = $service->create($tenant, $data, $request->user(), $reportingProvider);
            foreach ($request->file('attachments', []) as $file) {
                $attachments->upload($tenant, $expense, $file, $request->user());
            }
        }, 3);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Despesa de serviço registrada. O pagamento continua sendo controlado pelo motor financeiro.',
                'url' => route($management ? 'services.management.expenses' : 'provider.expenses',
                    ['tenant' => $tenant->slug, 'order' => $management ? ($data['service_order_id'] ?? null) : null]),
            ]);
        }

        return back()->with('success', 'Despesa de serviço registrada. O pagamento continua sendo controlado pelo motor financeiro.');
    }

    public function expenseDocument(Request $request, Tenant $tenant, int $document, ServiceExpenseAttachmentService $attachments)
    {
        [, $reportingProvider] = $this->expenseAccess($request, $tenant);
        $record = Document::query()->where('tenant_id', $tenant->id)
            ->where('documentable_type', Expense::class)->whereKey($document)->with('documentable')->firstOrFail();
        $expense = $record->documentable;
        abort_unless($expense?->origin_module === 'services' && (int) $expense->tenant_id === (int) $tenant->id
            && (! $reportingProvider || (int) $expense->service_provider_id === (int) $reportingProvider->id), 404);

        return response($attachments->contents($record), 200, [
            'Content-Type' => $record->mime_type,
            'Content-Disposition' => 'inline; filename="'.str_replace('"', '', $record->original_name).'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @return array{bool, ?ServiceProvider} */
    private function expenseAccess(Request $request, Tenant $tenant): array
    {
        $management = $request->routeIs('services.management.expenses*');
        $permission = $management ? 'view_service_management' : 'operate_all_service_orders_portal';
        if ($request->user()->checkPermissionTo($permission)
            && $request->user()->checkPermissionTo('manage_service_expenses')) {
            return [$management, null];
        }

        abort_if($management, 403);

        return [false, $this->provider($request, $tenant)];
    }

    /** @return array{0:?ServiceProvider,1:bool} */
    private function access(Request $request, Tenant $tenant): array
    {
        $operator = $request->user()->checkPermissionTo('operate_all_service_orders_portal');
        $provider = ServiceProvider::query()->where('tenant_id', $tenant->id)->where('user_id', $request->user()->id)->where('status', true)->first();
        abort_unless($operator || $provider, 403, 'Seu acesso não permite operar serviços nesta organização.');

        return [$provider, $operator];
    }

    private function provider(Request $request, Tenant $tenant): ServiceProvider
    {
        $provider = ServiceProvider::query()->where('tenant_id', $tenant->id)->where('user_id', $request->user()->id)->where('status', true)->first();
        abort_unless($provider, 403, 'Seu acesso não está vinculado a um prestador ativo.');

        return $provider;
    }

    private function order(Tenant $tenant, ?ServiceProvider $provider, bool $operator, int $id): ServiceOrder
    {
        return ServiceOrder::query()->where('tenant_id', $tenant->id)->when(! $operator, fn ($query) => $query->where('service_provider_id', $provider->id))->whereKey($id)->with(['service', 'serviceVersion.providerRates', 'serviceProvider', 'execution.evidences.document', 'execution.resources', 'execution.compositionLines', 'execution.obligations'])->firstOrFail();
    }

    private function availableVersions(Tenant $tenant, ?ServiceProvider $provider, bool $operator)
    {
        return ServiceVersion::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'published')
            ->when(! $operator, fn ($query) => $query->where('allow_provider_create_order', true))
            ->whereHas('service', fn ($query) => $query->where('status', true))
            ->when(! $operator, fn ($query) => $query->whereHas('service.serviceProviders', fn ($eligible) => $eligible->whereKey($provider->id)))
            ->with(['service', 'fields'])
            ->orderBy('service_id');
    }

    private function portalValues(ServiceOrder $order, array $values, array $phases, bool $operator): array
    {
        $keys = collect(data_get($order->execution->catalog_snapshot, 'fields', []))
            ->filter(fn (array $field) => in_array($field['phase'] ?? null, $phases, true)
                && ($operator ? ($field['visible_to_management'] ?? true) : (($field['visible_to_provider'] ?? false) && ($field['editable_by_provider'] ?? false))))
            ->pluck('key')->all();

        [$meterStart, $meterEnd] = \App\Services\Services\ServiceCalculationRules::meterPair((array) $order->execution->catalog_snapshot);
        if ($meterStart && in_array($meterEnd, $keys, true)) {
            $keys[] = 'meter_pauses_'.$meterStart;
        }

        return array_intersect_key($values, array_flip($keys));
    }

    private function uploadSubmittedEvidences(Request $request, ServiceOrder $order, bool $operator, array $phases, ServiceEvidenceService $service, ?callable $trackLocalFile = null): void
    {
        $fields = collect(data_get($order->execution->catalog_snapshot, 'fields', []))->keyBy('key');
        foreach ((array) $request->file('evidences', []) as $key => $file) {
            if (! $file) {
                continue;
            }
            $field = $fields->get($key);
            $allowed = $field
                && in_array($field['type'] ?? null, ['image', 'file', 'signature'], true)
                && in_array($field['phase'] ?? null, $phases, true)
                && ($operator ? ($field['visible_to_management'] ?? true) : (($field['visible_to_provider'] ?? false) && ($field['editable_by_provider'] ?? false)));
            abort_unless($allowed, 422, 'Esta evidência não pode ser enviada neste momento.');
            $service->upload($order->execution, (string) $key, $file, $request->user(), $trackLocalFile);
        }
    }

    private function mutationResponse(Request $request, Tenant $tenant, ServiceOrder $order, string $message, bool $reload): RedirectResponse|JsonResponse
    {
        if (! $request->expectsJson()) {
            return back()->with('success', $message);
        }

        $order->loadMissing('execution.evidences.document');

        return response()->json([
            'ok' => true,
            'message' => $message,
            'reload' => $reload,
            'url' => route(str_starts_with((string) $request->route()?->getName(), 'services.management.execute.') ? 'services.management.execute' : 'provider.orders.show', [$tenant, $order]),
            'status' => $order->execution?->status,
            'values' => $order->execution?->values ?? [],
            'evidences' => $order->execution?->evidences->map(fn (ServiceExecutionEvidence $item): array => [
                'field_key' => $item->field_key,
                'name' => $item->document?->original_name,
                'mime_type' => $item->document?->mime_type,
                'url' => route('provider.evidences.download', [$tenant, $item]),
            ])->values()->all() ?? [],
        ]);
    }
}
