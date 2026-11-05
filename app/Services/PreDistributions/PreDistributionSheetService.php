<?php

namespace App\Services\PreDistributions;

use App\Models\Customer;
use App\Models\PriceTable;
use App\Models\PreDistributionEvent;
use App\Models\PreDistributionItem;
use App\Models\PreDistributionOperation;
use App\Models\PreDistributionSheet;
use App\Models\Product;
use App\Models\SalesProject;
use App\Models\User;
use App\Services\PricingService;
use App\Services\ProjectDistributionCustomerService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Operational writes only. No reference to stock, ledger or financial creation services. */
final class PreDistributionSheetService
{
    private array $allowedCustomers = [];

    private array $products = [];

    public function __construct(private PreDistributionAccess $access, private ProjectDistributionCustomerService $customers) {}

    public function visibleQuery(int $tenantId, User $actor)
    {
        $this->access->assertContext($actor, $tenantId);
        abort_unless($this->access->manager($actor, $tenantId) || $actor->checkPermissionTo('view_pre_distributions'), 403);

        return PreDistributionSheet::withoutGlobalScopes()->where('tenant_id', $tenantId)
            ->when(! $this->access->manager($actor, $tenantId), fn ($query) => $query->where('assigned_to', $actor->id));
    }

    public function create(int $tenantId, User $actor, string $key, array $data): array
    {
        $this->access->assertContext($actor, $tenantId);
        abort_unless($this->access->manager($actor, $tenantId), 403);
        $data = Validator::make($data, [
            'title' => 'required|string|max:180', 'operational_date' => 'required|date_format:Y-m-d',
            'assigned_to' => 'required|integer|min:1', 'project_ids' => 'required|array|min:1|max:30',
            'project_ids.*' => 'required|integer|distinct|min:1', 'notes' => 'nullable|string|max:5000',
            'items' => 'present|array|max:500', 'items.*' => 'required|array',
        ])->validate();

        return $this->operate($tenantId, $actor, 'create', $key, $data, function ($operation) use ($tenantId, $actor, $data): array {
            $this->assertOperator($tenantId, (int) $data['assigned_to']);
            $projects = SalesProject::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereIn('id', $data['project_ids'])
                ->whereNull('deleted_at')->whereIn('status', ['draft', 'active', 'deliveries_closed'])->get();
            if ($projects->count() !== count($data['project_ids'])) {
                $this->invalid('project_ids', 'Um projeto é inválido, inativo ou pertence a outro tenant.');
            }
            $sheet = PreDistributionSheet::create([
                'tenant_id' => $tenantId, 'public_id' => (string) Str::uuid(), 'title' => $data['title'],
                'operational_date' => $data['operational_date'], 'assigned_to' => $data['assigned_to'],
                'created_by' => $actor->id, 'notes' => $data['notes'] ?? null, 'status' => 'draft', 'version' => 1,
            ]);
            foreach ($projects as $project) {
                DB::table('pre_distribution_sheet_projects')->insert([
                    'tenant_id' => $tenantId, 'sheet_id' => $sheet->id, 'sales_project_id' => $project->id,
                ]);
            }
            foreach ($data['items'] as $row) {
                $attributes = $this->itemAttributes($sheet, $row);
                PreDistributionItem::create($attributes + ['tenant_id' => $tenantId, 'sheet_id' => $sheet->id, 'origin' => 'manager']);
            }
            $this->event($sheet, $actor, $operation, 'created', null);

            return $this->result($sheet);
        });
    }

    public function save(int $tenantId, User $actor, int $sheetId, string $key, array $data): array
    {
        $this->access->assertContext($actor, $tenantId);
        abort_unless($this->access->record($actor, $this->visibleQuery($tenantId, $actor)->findOrFail($sheetId)), 403);
        $data = Validator::make($data, ['version' => 'required|integer|min:1', 'items' => 'required|array|min:1|max:100', 'items.*' => 'required|array'])->validate();

        return $this->operate($tenantId, $actor, 'save', $key, ['sheet_id' => $sheetId] + $data, function ($operation) use ($tenantId, $actor, $sheetId, $data): array {
            $sheet = $this->lockedSheet($tenantId, $actor, $sheetId, $data['version']);
            abort_unless($this->access->record($actor, $sheet), 403);
            $this->assertDraft($sheet);
            $before = $this->snapshot($sheet);
            $manager = $this->access->manager($actor, $tenantId);
            $seen = [];
            foreach ($data['items'] as $row) {
                if (! $manager && array_diff(array_keys($row), ['id', 'reported_quantity', 'notes', 'delivered_at'])) {
                    abort(403, 'O entregador só pode informar quantidade, horário e observações.');
                }
                Validator::make($row, ['id' => 'sometimes|required|integer|min:1', 'sales_project_id' => 'sometimes|required|integer|min:1',
                    'customer_id' => 'sometimes|required|integer|min:1', 'product_id' => 'sometimes|required|integer|min:1'])->validate();
                if (isset($row['id'])) {
                    $id = (int) $row['id'];
                    if (in_array($id, $seen, true)) {
                        $this->invalid('items', 'A mesma linha foi enviada mais de uma vez.');
                    }
                    $seen[] = $id;
                    $item = PreDistributionItem::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('sheet_id', $sheet->id)->lockForUpdate()->findOrFail($id);
                    if ($item->cancelled_at) {
                        $this->invalid('items', 'Uma linha cancelada não pode ser modificada.');
                    }
                    $reserved = DB::table('pre_distribution_links')->where('tenant_id', $tenantId)->where('item_id', $item->id)->whereNull('reversed_at')->exists();
                    if ($reserved) {
                        foreach (['sales_project_id', 'customer_id', 'product_id'] as $field) {
                            if (! array_key_exists($field, $row)) {
                                continue;
                            }
                            if ((int) $row[$field] !== (int) $item->$field) {
                                $this->invalid('items', 'Esta linha possui conciliação. Corrija primeiro a distribuição pelo fluxo oficial e reverta o vínculo antes de alterar seus dados.');
                            }
                        }
                        if (array_key_exists('reported_quantity', $row)) {
                            $quantity = $this->decimal($row['reported_quantity'], 'reported_quantity');
                            $minimum = (string) DB::table('pre_distribution_links')->where('tenant_id', $tenantId)->where('item_id', $item->id)->whereNull('reversed_at')->sum('quantity');
                            if ($quantity === null || bccomp($quantity, $minimum, 4) < 0) {
                                $this->invalid('reported_quantity', 'A quantidade não pode ficar abaixo da conciliação existente. Corrija primeiro as distribuições pelo fluxo oficial.');
                            }
                        }
                    }
                    if ($manager) {
                        foreach (['sales_project_id', 'customer_id', 'product_id'] as $field) {
                            if (isset($row[$field]) && (int) $row[$field] !== (int) $item->$field
                                && ! array_key_exists('reference_price', $row) && ! array_key_exists('price_table_id', $row)) {
                                $row['price_table_id'] = null;
                                break;
                            }
                        }
                        $attributes = $this->itemAttributes($sheet, array_merge($item->getAttributes(), $row), $item);
                        if ((int) $item->product_id === (int) $attributes['product_id'] && $item->unit !== $attributes['unit']) {
                            $this->invalid('product_id', 'A unidade do produto mudou. Cancele a linha e crie outra, sem converter quantidades automaticamente.');
                        }
                        $item->fill($attributes);
                    } else {
                        if (array_diff(array_keys($row), ['id', 'reported_quantity', 'notes', 'delivered_at'])) {
                            abort(403, 'O entregador só pode informar quantidade, horário e observações.');
                        }
                        $patch = Validator::make($row, ['reported_quantity' => 'sometimes|nullable', 'notes' => 'sometimes|nullable|string|max:5000', 'delivered_at' => 'sometimes|nullable|date'])->validate();
                        if (array_key_exists('reported_quantity', $patch)) {
                            $patch['reported_quantity'] = $this->decimal($patch['reported_quantity'], 'reported_quantity');
                        }
                        $item->fill($patch);
                    }
                    $item->save();
                } else {
                    abort_unless($manager, 403, 'Somente o gestor pode adicionar destinos e produtos.');
                    if ($sheet->items()->count() >= 500) {
                        $this->invalid('items', 'Limite de 500 linhas por planilha.');
                    }
                    PreDistributionItem::create($this->itemAttributes($sheet, $row) + ['tenant_id' => $tenantId, 'sheet_id' => $sheet->id, 'origin' => 'manager']);
                }
            }
            $sheet->increment('version');
            $this->event($sheet->refresh(), $actor, $operation, 'items_updated', $before);

            return $this->result($sheet);
        });
    }

    public function duplicate(int $tenantId, User $actor, int $sheetId, string $key, array $data): array
    {
        $this->access->assertContext($actor, $tenantId);
        abort_unless($this->access->manager($actor, $tenantId), 403);
        $data = Validator::make($data, ['version' => 'required|integer|min:1', 'title' => 'required|string|max:180',
            'operational_date' => 'required|date_format:Y-m-d', 'assigned_to' => 'required|integer|min:1'])->validate();

        return $this->operate($tenantId, $actor, 'duplicate', $key, ['sheet_id' => $sheetId] + $data, function ($operation) use ($tenantId, $actor, $sheetId, $data) {
            $source = $this->lockedSheet($tenantId, $actor, $sheetId, $data['version']);
            $this->assertOperator($tenantId, $data['assigned_to']);
            $sheet = PreDistributionSheet::create(['tenant_id' => $tenantId, 'public_id' => (string) Str::uuid(),
                'title' => $data['title'], 'operational_date' => $data['operational_date'], 'assigned_to' => $data['assigned_to'],
                'created_by' => $actor->id, 'status' => 'draft', 'version' => 1, 'notes' => $source->notes]);
            foreach ($source->projects()->where('sales_projects.tenant_id', $tenantId)->get() as $project) {
                if (! in_array($project->getRawOriginal('status'), ['draft', 'active', 'deliveries_closed'], true)) {
                    $this->invalid('project_ids', 'Um projeto da planilha original já foi encerrado.');
                }
                DB::table('pre_distribution_sheet_projects')->insert(['tenant_id' => $tenantId, 'sheet_id' => $sheet->id, 'sales_project_id' => $project->id]);
            }
            foreach ($source->items()->whereNull('cancelled_at')->orderBy('id')->get() as $item) {
                $row = Arr::only($item->attributesToArray(), ['sales_project_id', 'customer_id', 'product_id', 'planned_quantity', 'reference_price', 'logistics_mode']);
                $attributes = $this->itemAttributes($sheet, $row, $item);
                if ($attributes['unit'] !== $item->unit) {
                    $this->invalid('items', 'A unidade de um produto mudou; crie uma nova planilha para revisar as quantidades.');
                }
                PreDistributionItem::create($attributes + ['tenant_id' => $tenantId, 'sheet_id' => $sheet->id, 'origin' => 'duplicate']);
            }
            $this->event($sheet, $actor, $operation, 'duplicated', null, 'Modelo: '.$source->code.' · versão '.$source->version);

            return $this->result($sheet);
        });
    }

    public function updateDetails(int $tenantId, User $actor, int $sheetId, string $key, array $data): array
    {
        $this->access->assertContext($actor, $tenantId);
        abort_unless($this->access->manager($actor, $tenantId), 403);
        $data = Validator::make($data, ['version' => 'required|integer|min:1', 'title' => 'required|string|max:180',
            'operational_date' => 'required|date_format:Y-m-d', 'assigned_to' => 'required|integer|min:1', 'notes' => 'nullable|string|max:5000'])->validate();

        return $this->operate($tenantId, $actor, 'details', $key, ['sheet_id' => $sheetId] + $data, function ($operation) use ($tenantId, $actor, $sheetId, $data) {
            $sheet = $this->lockedSheet($tenantId, $actor, $sheetId, $data['version']);
            $this->assertDraft($sheet);
            $this->assertOperator($tenantId, $data['assigned_to']);
            $before = $this->snapshot($sheet);
            $sheet->fill(Arr::except($data, ['version']));
            $sheet->version++;
            $sheet->save();
            $this->event($sheet, $actor, $operation, 'details_updated', $before);

            return $this->result($sheet);
        });
    }

    public function transition(int $tenantId, User $actor, int $sheetId, string $key, array $data): array
    {
        $this->access->assertContext($actor, $tenantId);
        abort_unless($this->access->record($actor, $this->visibleQuery($tenantId, $actor)->findOrFail($sheetId)), 403);
        $data = Validator::make($data, ['version' => 'required|integer|min:1',
            'action' => 'required|in:complete,reopen,cancel,request_correction', 'reason' => 'nullable|string|min:5|max:2000'])->validate();

        return $this->operate($tenantId, $actor, 'transition', $key, ['sheet_id' => $sheetId] + $data, function ($operation) use ($tenantId, $actor, $sheetId, $data): array {
            $sheet = $this->lockedSheet($tenantId, $actor, $sheetId, $data['version']);
            abort_unless($this->access->record($actor, $sheet), 403);
            $before = $this->snapshot($sheet);
            $action = $data['action'];
            if ($sheet->status === 'cancelled') {
                $this->invalid('action', 'Uma planilha cancelada não pode ser restaurada.');
            }
            if ($action === 'complete') {
                $this->assertDraft($sheet);
                $active = $sheet->items()->whereNull('cancelled_at');
                if (! (clone $active)->exists() || (clone $active)->whereNull('reported_quantity')->exists()) {
                    $this->invalid('items', 'Responda todas as linhas: vazio significa pendente; informe zero quando não houve entrega.');
                }
                $sheet->status = 'completed';
                $sheet->completed_at = now();
                $sheet->completed_by = $actor->id;
            } else {
                if (! trim((string) ($data['reason'] ?? ''))) {
                    $this->invalid('reason', 'Informe a justificativa.');
                }
                if ($action === 'request_correction') {
                    if ($sheet->status !== 'completed') {
                        $this->invalid('action', 'Só é possível solicitar correção de uma planilha concluída.');
                    }
                } else {
                    abort_unless($this->access->manager($actor, $tenantId), 403);
                    if ($action === 'reopen') {
                        if ($sheet->status !== 'completed') {
                            $this->invalid('action', 'Somente uma planilha concluída pode ser reaberta.');
                        }
                        $sheet->status = 'draft';
                        $sheet->completed_at = null;
                        $sheet->completed_by = null;
                    } else {
                        if (DB::table('pre_distribution_links')->where('tenant_id', $tenantId)->whereIn('item_id', $sheet->items()->select('id'))->whereNull('reversed_at')->exists()) {
                            $this->invalid('action', 'Existem conciliações nesta planilha. Resolva os vínculos pelo fluxo oficial antes de cancelar.');
                        }
                        $sheet->status = 'cancelled';
                        $sheet->cancelled_at = now();
                        $sheet->cancellation_reason = $data['reason'];
                    }
                }
            }
            $sheet->version++;
            $sheet->save();
            $this->event($sheet, $actor, $operation, $action, $before, $data['reason'] ?? null);

            return $this->result($sheet);
        });
    }

    public function cancelItem(int $tenantId, User $actor, int $sheetId, int $itemId, string $key, array $data): array
    {
        $this->access->assertContext($actor, $tenantId);
        abort_unless($this->access->manager($actor, $tenantId), 403);
        $data = Validator::make($data, ['version' => 'required|integer|min:1', 'reason' => 'required|string|min:5|max:2000'])->validate();

        return $this->operate($tenantId, $actor, 'cancel_item', $key, ['sheet_id' => $sheetId, 'item_id' => $itemId] + $data,
            function ($operation) use ($tenantId, $actor, $sheetId, $itemId, $data): array {
                $sheet = $this->lockedSheet($tenantId, $actor, $sheetId, $data['version']);
                $this->assertDraft($sheet);
                $before = $this->snapshot($sheet);
                $item = PreDistributionItem::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('sheet_id', $sheetId)->lockForUpdate()->findOrFail($itemId);
                if ($item->cancelled_at) {
                    $this->invalid('item', 'A linha já está cancelada.');
                }
                if (DB::table('pre_distribution_links')->where('tenant_id', $tenantId)->where('item_id', $item->id)->whereNull('reversed_at')->exists()) {
                    $this->invalid('item', 'A linha possui conciliação. Corrija a distribuição oficial antes de cancelar.');
                }
                $item->update(['cancelled_at' => now(), 'cancellation_reason' => $data['reason']]);
                $sheet->increment('version');
                $sheet->refresh();
                $this->event($sheet, $actor, $operation, 'item_cancelled', $before, $data['reason']);

                return $this->result($sheet);
            });
    }

    public function referenceQuote(SalesProject $project, int $customerId, Product $product, ?int $tableId): array
    {
        $customer = Customer::query()->where('tenant_id', $project->tenant_id)->findOrFail($customerId);
        if ($tableId !== null && $tableId !== (int) $customer->price_table_id) {
            $this->invalid('price_table_id', 'Use a tabela vinculada ao cliente que recebeu a entrega.');
        }
        $table = PriceTable::query()->where('tenant_id', $project->tenant_id)->where('active', true)
            ->find($tableId ?? $customer->price_table_id);
        if ($tableId !== null && ! $table) {
            $this->invalid('price_table_id', 'Selecione uma tabela de preços ativa desta organização.');
        }
        if (! $table) {
            return ['table_id' => null, 'table_name' => null, 'price' => null];
        }

        $customer->price_table_id = $table->id;
        $customer->setRelation('priceTable', $table);
        $price = app(PricingService::class)->resolvePrice($product, $customer, $project);

        return [
            'table_id' => $table->id,
            'table_name' => $table->name,
            'price' => bccomp($price['sale_price'], '0', 4) > 0 ? $price['sale_price'] : null,
        ];
    }

    private function itemAttributes(PreDistributionSheet $sheet, array $row, ?PreDistributionItem $original = null): array
    {
        $row = Validator::make($row, ['sales_project_id' => 'required|integer|min:1', 'customer_id' => 'required|integer|min:1',
            'product_id' => 'required|integer|min:1', 'planned_quantity' => 'nullable', 'reported_quantity' => 'nullable',
            'reference_price' => 'nullable', 'price_table_id' => 'sometimes|nullable|integer|min:1', 'organization_id' => 'nullable|integer|min:1', 'notes' => 'nullable|string|max:5000', 'delivered_at' => 'nullable|date',
            'logistics_mode' => 'sometimes|in:association,direct,unspecified'])->validate();
        $sheet->loadMissing(['projects' => fn ($q) => $q->where('sales_projects.tenant_id', $sheet->tenant_id)]);
        $project = $sheet->projects->firstWhere('id', (int) $row['sales_project_id']);
        if (! $project) {
            $this->invalid('sales_project_id', 'O projeto não está autorizado nesta planilha.');
        }
        $context = $sheet->tenant_id.':'.$project->id;
        $this->allowedCustomers[$context] ??= $this->customers->ids($project)->all();
        if (! in_array((int) $row['customer_id'], $this->allowedCustomers[$context], true)) {
            $this->invalid('customer_id', 'O cliente não está habilitado para este projeto.');
        }
        if (isset($row['organization_id']) && ! Customer::query()->where('tenant_id', $sheet->tenant_id)
            ->where('organization_id', $row['organization_id'])->whereKey($row['customer_id'])->exists()) {
            $this->invalid('customer_id', 'Escolha um cliente da organização selecionada.');
        }
        $productKey = $sheet->tenant_id.':'.$row['product_id'];
        if (! array_key_exists($productKey, $this->products)) {
            $this->products[$productKey] = Product::withoutGlobalScopes()->where('tenant_id', $sheet->tenant_id)->whereNull('deleted_at')->find($row['product_id']);
        }
        $product = $this->products[$productKey];
        if (! $product) {
            $this->invalid('product_id', 'Produto inválido ou pertencente a outro tenant.');
        }
        $unit = trim((string) $product->unit);
        if ($unit === '' || mb_strlen($unit) > 32) {
            $this->invalid('product_id', 'Cadastre a unidade de medida do produto antes de continuar.');
        }

        $reference = $this->decimal($row['reference_price'] ?? null, 'reference_price');
        $tableId = null;
        if (array_key_exists('price_table_id', $row) || (! $original && ! array_key_exists('reference_price', $row))) {
            $quote = $this->referenceQuote($project, (int) $row['customer_id'], $product, $row['price_table_id'] ?? null);
            if (isset($row['price_table_id']) && $quote['price'] === null) {
                $this->invalid('price_table_id', 'O produto está sem preço de venda nesta tabela. Escolha outra tabela ou informe uma referência manual.');
            }
            $reference = $quote['price'];
            $tableId = $reference !== null ? $quote['table_id'] : null;
        } elseif ($original && $reference === $original->reference_price
            && (int) $original->customer_id === (int) $row['customer_id']
            && (int) $original->sales_project_id === (int) $row['sales_project_id']
            && (int) $original->product_id === (int) $row['product_id']) {
            $tableId = $original->reference_price_table_id;
        }

        $reported = $this->decimal($row['reported_quantity'] ?? null, 'reported_quantity');
        if (! $original && ($reported !== null || array_key_exists('price_table_id', $row) || array_key_exists('reference_price', $row))
            && ($reference === null || bccomp($reference, '0', 4) <= 0)) {
            $this->invalid('reference_price', 'O produto precisa de um preço maior que zero para ser registrado. Confira a tabela do cliente ou informe uma referência manual.');
        }

        return Arr::only($row, ['sales_project_id', 'customer_id', 'product_id', 'notes', 'delivered_at']) + [
            'unit' => $unit, 'planned_quantity' => $this->decimal($row['planned_quantity'] ?? null, 'planned_quantity'),
            'reported_quantity' => $reported,
            'reference_price' => $reference, 'reference_price_table_id' => $tableId, 'logistics_mode' => $row['logistics_mode'] ?? 'unspecified',
        ];
    }

    public function operate(int $tenantId, User $actor, string $kind, string $key, array $payload, callable $callback, int $attempts = 3): array
    {
        $this->access->assertContext($actor, $tenantId);
        Validator::make(['operation_key' => $key], ['operation_key' => 'required|uuid'])->validate();
        $hash = hash('sha256', json_encode($this->canonical($payload), JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($tenantId, $actor, $kind, $key, $hash, $callback): array {
            // Short tenant-wide serialization gate: deterministic first lock, also covers absent idempotency keys.
            abort_unless(DB::table('tenants')->where('id', $tenantId)->lockForUpdate()->exists(), 404);
            $operation = PreDistributionOperation::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('operation_key', $key)->lockForUpdate()->first();
            if ($operation) {
                abort_unless((int) $operation->actor_id === (int) $actor->id && $operation->kind === $kind && hash_equals($operation->request_hash, $hash), 409, 'A chave da operação já foi utilizada com outros dados ou por outro usuário.');

                return $operation->result;
            }
            $operation = PreDistributionOperation::create(['tenant_id' => $tenantId, 'actor_id' => $actor->id,
                'operation_key' => $key, 'kind' => $kind, 'request_hash' => $hash, 'result' => []]);
            $this->allowedCustomers = [];
            $this->products = []; // Per-operation only; never retain authorization across writes/retries.
            $result = $callback($operation);
            $operation->update(['result' => $result]);

            return $result;
        }, $attempts);
    }

    private function lockedSheet(int $tenantId, User $actor, int $id, int $version): PreDistributionSheet
    {
        $sheet = $this->visibleQuery($tenantId, $actor)->lockForUpdate()->findOrFail($id);
        abort_unless($sheet->version === $version, 409, 'A planilha foi alterada em outra sessão. Atualize os dados antes de salvar; nada foi sobrescrito.');

        return $sheet;
    }

    private function assertDraft(PreDistributionSheet $sheet): void
    {
        if ($sheet->status !== 'draft') {
            $this->invalid('status', 'A planilha está finalizada. Solicite a reabertura autorizada para corrigir.');
        }
    }

    private function assertOperator(int $tenantId, int $userId): void
    {
        $operator = User::find($userId);
        if (! $operator || ! $operator->status || ! DB::table('tenant_user')->where('tenant_id', $tenantId)->where('user_id', $userId)->where('status', true)->exists()
            || ! $operator->hasRoleInTenant(['entregador', 'registrador_entregas', 'admin'], $tenantId)) {
            $this->invalid('assigned_to', 'O responsável precisa ter um vínculo ativo e papel operacional neste tenant.');
        }
    }

    private function decimal(mixed $value, string $field): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            $this->invalid($field, 'Informe um número válido.');
        }
        if (! preg_match('/^\d{1,14}(?:\.\d{1,4})?$/D', (string) $value)) {
            $this->invalid($field, 'Informe uma quantidade ou preço não negativo, com até quatro casas decimais e ponto como separador.');
        }

        return bcadd((string) $value, '0', 4);
    }

    private function event(PreDistributionSheet $sheet, User $actor, PreDistributionOperation $operation, string $event, ?array $before, ?string $reason = null): void
    {
        PreDistributionEvent::create(['tenant_id' => $sheet->tenant_id, 'sheet_id' => $sheet->id, 'actor_id' => $actor->id,
            'operation_id' => $operation->id, 'event' => $event, 'version' => $sheet->version, 'reason' => $reason,
            'before' => $before, 'after' => $this->snapshot($sheet), 'created_at' => now()]);
    }

    private function snapshot(PreDistributionSheet $sheet): array
    {
        return ['sheet' => Arr::only($sheet->getAttributes(), ['id', 'title', 'assigned_to', 'operational_date', 'status', 'version', 'notes', 'completed_by', 'completed_at', 'cancelled_at', 'cancellation_reason']),
            'items' => $sheet->items()->withoutGlobalScopes()->where('tenant_id', $sheet->tenant_id)->orderBy('id')->get()->map(fn ($item) => $item->attributesToArray())->all()];
    }

    private function result(PreDistributionSheet $sheet): array
    {
        return ['sheet_id' => (int) $sheet->id, 'code' => $sheet->code, 'version' => (int) $sheet->version, 'status' => $sheet->status];
    }

    private function canonical(array $data): array
    {
        if (! array_is_list($data)) {
            ksort($data);
        }
        foreach ($data as &$value) {
            if (is_array($value)) {
                $value = $this->canonical($value);
            }
        }

        return $data;
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
