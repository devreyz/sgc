<?php

namespace App\Services\PreDistributions;

use App\Models\PreDistributionEvent;
use App\Models\PreDistributionItem;
use App\Models\PreDistributionOperation;
use App\Models\PreDistributionSheet;
use App\Models\ProductionDelivery;
use App\Models\SalesProject;
use App\Models\User;
use App\Services\ProjectDistributionCustomerService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Links operational quantities. Official creation remains exclusively in the existing distribution action. */
final class PreDistributionReconciliationService
{
    public function __construct(private PreDistributionAccess $access, private PreDistributionSheetService $sheets) {}

    public function authorize(int $tenantId, User $actor): void
    {
        $this->access->assertContext($actor, $tenantId);
        abort_unless($this->access->manager($actor, $tenantId) && $actor->hasRoleInTenant(['registrador_entregas', 'admin'], $tenantId), 403);
    }

    public function replay(int $tenantId, User $actor, string $key, int $receptionId, array $rows): ?array
    {
        $this->authorize($tenantId, $actor);
        Validator::make(['key' => $key], ['key' => 'required|uuid'])->validate();
        $operation = PreDistributionOperation::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('operation_key', $key)->first();
        if (! $operation) {
            return null;
        }
        abort_unless((int) $operation->actor_id === (int) $actor->id && $operation->kind === 'snippets'
            && hash_equals($operation->request_hash, $this->hash($receptionId, $rows)), 409, 'Chave já utilizada com outra operação.');

        return $operation->result;
    }

    /** Called before reception lock and within the SAME official transaction. */
    public function begin(int $tenantId, User $actor, string $key, int $receptionId, array $rows): array
    {
        $this->authorize($tenantId, $actor);
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('Conciliação requer a transação oficial.');
        }
        DB::table('tenants')->where('id', $tenantId)->lockForUpdate()->firstOrFail();
        if (($replay = $this->replay($tenantId, $actor, $key, $receptionId, $rows)) !== null) {
            return ['replay' => $replay];
        }
        $operation = PreDistributionOperation::create(['tenant_id' => $tenantId, 'actor_id' => $actor->id,
            'operation_key' => $key, 'kind' => 'snippets', 'request_hash' => $this->hash($receptionId, $rows), 'result' => []]);
        $ids = collect($rows)->pluck('pre_distribution_item_id')->filter()->map(fn ($id) => (int) $id)->unique()->sort()->values();
        $items = PreDistributionItem::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereIn('id', $ids)->orderBy('id')->get();
        if ($items->count() !== $ids->count()) {
            $this->invalid('Um item preliminar não existe neste tenant.');
        }
        $sheets = PreDistributionSheet::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereIn('id', $items->pluck('sheet_id')->unique())
            ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $items = PreDistributionItem::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        foreach ($rows as $row) {
            $this->quantity($row['quantity']);
            if (empty($row['pre_distribution_item_id'])) {
                continue;
            }
            $item = $items[$row['pre_distribution_item_id']];
            $sheet = $sheets[$item->sheet_id];
            abort_unless((int) ($row['pre_distribution_version'] ?? 0) === (int) $sheet->version, 409, 'A planilha mudou. Consulte novamente os snippets.');
            if ($sheet->status !== 'completed' || $item->cancelled_at || $item->reported_quantity === null || bccomp($item->reported_quantity, '0', 4) <= 0) {
                $this->invalid('Utilize uma linha positiva de planilha concluída.');
            }
        }

        return ['operation' => $operation, 'items' => $items, 'sheets' => $sheets];
    }

    public function attach(array $context, array $row, ProductionDelivery $official, User $actor): void
    {
        if (empty($row['pre_distribution_item_id'])) {
            return;
        }
        $item = $context['items'][$row['pre_distribution_item_id']];
        if ((int) $official->tenant_id !== (int) $item->tenant_id || ! $official->parent_delivery_id
            || (int) $official->sales_project_id !== (int) $item->sales_project_id || (int) $official->customer_id !== (int) $item->customer_id
            || (int) $official->product_id !== (int) $item->product_id || trim((string) $official->product?->unit) !== $item->unit
            || $official->getRawOriginal('status') !== 'approved') {
            $this->invalid('Projeto, produto, unidade, cliente ou situação oficial incompatível.');
        }
        $quantity = $this->quantity($row['quantity']);
        $reserved = $this->reserved((int) $item->tenant_id, (int) $item->id);
        if (bccomp(bcadd($reserved, $quantity, 4), $item->reported_quantity, 4) > 0) {
            $this->invalid('A quantidade excede o saldo preliminar. Outra operação pode ter utilizado esta linha.');
        }
        $used = (string) DB::table('pre_distribution_links')->where('tenant_id', $item->tenant_id)->where('distribution_id', $official->id)->whereNull('reversed_at')->sum('quantity');
        if (bccomp(bcadd($used, $quantity, 4), $official->quantity, 4) > 0) {
            $this->invalid('A quantidade excede o saldo da distribuição oficial.');
        }
        DB::table('pre_distribution_links')->insert(['tenant_id' => $item->tenant_id, 'item_id' => $item->id, 'distribution_id' => $official->id,
            'quantity' => $quantity, 'unit' => $item->unit, 'actor_id' => $actor->id, 'operation_id' => $context['operation']->id,
            'origin' => $context['origin'] ?? 'snippet', 'official_snapshot' => json_encode($official->only(['id', 'parent_delivery_id', 'associate_id', 'sales_project_id', 'customer_id', 'product_id', 'quantity', 'unit_price', 'status']), JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }

    public function linkExisting(int $tenantId, User $actor, string $key, array $data): array
    {
        $this->authorize($tenantId, $actor);
        $data = Validator::make($data, ['rows' => 'required|array|min:1|max:50', 'rows.*.pre_distribution_item_id' => 'required|integer|min:1',
            'rows.*.pre_distribution_version' => 'required|integer|min:1', 'rows.*.distribution_id' => 'required|integer|min:1', 'rows.*.quantity' => 'required'])->validate();
        $pairs = array_map(fn ($row) => (int) $row['pre_distribution_item_id'].':'.(int) $row['distribution_id'], $data['rows']);
        if (count($pairs) !== count(array_unique($pairs))) {
            $this->invalid('A mesma combinação de item e distribuição foi enviada duas vezes. Some as quantidades em uma única linha.');
        }

        // No official records are created or modified here. Existing official quantities may be shared by several preliminary items.
        return DB::transaction(function () use ($tenantId, $actor, $key, $data) {
            $context = $this->begin($tenantId, $actor, $key, 0, $data['rows']);
            if (isset($context['replay'])) {
                return $context['replay'];
            }
            $ids = collect($data['rows'])->pluck('distribution_id')->unique()->sort()->values();
            $officials = ProductionDelivery::query()->where('tenant_id', $tenantId)->whereIn('id', $ids)->whereNotNull('parent_delivery_id')->get();
            if ($officials->count() !== $ids->count()) {
                $this->invalid('Uma distribuição não existe neste tenant.');
            }
            $parents = ProductionDelivery::query()->where('tenant_id', $tenantId)->whereIn('id', $officials->pluck('parent_delivery_id')->unique())
                ->whereNull('parent_delivery_id')->where('status', 'approved')->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $totals = $this->receptionTotals($tenantId, $parents->keys()->all());
            foreach ($parents as $parent) {
                if (bccomp((string) ($totals[$parent->id] ?? '0'), $parent->quantity, 4) > 0) {
                    $this->invalid('A entrega oficial possui distribuições acima da quantidade recebida. Corrija sua origem antes de conciliar.');
                }
            }
            $officials = ProductionDelivery::query()->where('tenant_id', $tenantId)->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $context['origin'] = 'manual_link';
            foreach ($data['rows'] as $row) {
                $official = $officials[$row['distribution_id']];
                $parent = $parents[$official->parent_delivery_id] ?? null;
                if (! $parent || (int) $parent->product_id !== (int) $official->product_id || (int) $parent->sales_project_id !== (int) $official->sales_project_id
                    || (int) $parent->associate_id !== (int) $official->associate_id) {
                    $this->invalid('A distribuição não possui origem oficial aprovada e compatível.');
                }
                app(ProjectDistributionCustomerService::class)->assertAllowed($official->salesProject, [$official->customer_id]);
                $this->attach($context, $row, $official, $actor);
            }
            $result = ['linked_ids' => $ids->all()];
            $this->finish($context, $actor, $result);

            return $result;
        }, 3);
    }

    public function finish(array $context, User $actor, array $result): void
    {
        foreach ($context['sheets'] as $sheet) {
            $sheet->version++;
            $sheet->save();
            PreDistributionEvent::create(['tenant_id' => $sheet->tenant_id, 'sheet_id' => $sheet->id, 'actor_id' => $actor->id,
                'operation_id' => $context['operation']->id, 'event' => 'reconciled', 'version' => $sheet->version,
                'after' => ['link_ids' => DB::table('pre_distribution_links')->where('tenant_id', $sheet->tenant_id)->where('operation_id', $context['operation']->id)->whereIn('item_id', $context['items']->where('sheet_id', $sheet->id)->keys())->pluck('id')->all()], 'created_at' => now()]);
        }
        $context['operation']->update(['result' => $result]);
    }

    public function reserved(int $tenantId, int $itemId): string
    {
        return bcadd((string) DB::table('pre_distribution_links')->where('tenant_id', $tenantId)->where('item_id', $itemId)->whereNull('reversed_at')->sum('quantity'), '0', 4);
    }

    public function progress(int $tenantId, int $itemId): array
    {
        return $this->progressMany($tenantId, [$itemId])[$itemId];
    }

    public function progressMany(int $tenantId, array $ids): array
    {
        $items = PreDistributionItem::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereIn('id', $ids)->get();
        abort_unless($items->count() === count(array_unique($ids)), 404);
        $links = DB::table('pre_distribution_links')->where('tenant_id', $tenantId)->whereIn('item_id', $ids)->orderBy('id')->get();
        $officials = DB::table('production_deliveries')->where('tenant_id', $tenantId)->whereIn('id', $links->pluck('distribution_id'))->get()->keyBy('id');
        $parents = DB::table('production_deliveries')->where('tenant_id', $tenantId)->whereIn('id', $officials->pluck('parent_delivery_id')->filter()->unique())->get()->keyBy('id');
        $totals = $this->receptionTotals($tenantId, $parents->keys()->all());
        foreach ($parents as $parent) {
            $parent->total_distributed = (string) ($totals[$parent->id] ?? '0');
        }
        $products = DB::table('products')->where('tenant_id', $tenantId)->whereIn('id', $items->pluck('product_id')->unique())->whereNull('deleted_at')->get()->keyBy('id');
        $projects = SalesProject::query()->where('tenant_id', $tenantId)->whereIn('id', $items->pluck('sales_project_id')->unique())
            ->with(['customer:id,tenant_id,status', 'customers:id', 'organizations:id'])->get();
        $allowed = [];
        foreach ($projects as $project) {
            $allowed[$project->id] = app(ProjectDistributionCustomerService::class)->ids($project)->all();
        }
        $byItem = $links->groupBy('item_id');
        $result = [];
        foreach ($items as $item) {
            $result[$item->id] = $this->itemProgress($item, $byItem->get($item->id, collect()), $officials, $parents,
                $products[$item->product_id] ?? null, in_array((int) $item->customer_id, $allowed[$item->sales_project_id] ?? [], true));
        }

        return $result;
    }

    private function itemProgress(PreDistributionItem $item, $links, $officials, $parents, ?object $product, bool $customerAllowed): array
    {
        $reserved = '0.0000';
        $valid = '0.0000';
        $warnings = [];
        if (! $product || trim((string) $product->unit) !== $item->unit) {
            $warnings[] = ['code' => 'context_changed', 'message' => 'O produto foi removido ou sua unidade mudou. Revise o cadastro sem converter quantidades automaticamente.'];
        }
        if (! $customerAllowed) {
            $warnings[] = ['code' => 'context_changed', 'message' => 'O projeto ou cliente não está mais autorizado. Revise a planilha e os vínculos oficiais.'];
        }
        foreach ($links as $link) {
            if ($link->reversed_at) {
                continue;
            }
            $reserved = bcadd($reserved, (string) $link->quantity, 4);
            $official = $officials[$link->distribution_id] ?? null;
            $snapshot = json_decode($link->official_snapshot, true, 512, JSON_THROW_ON_ERROR);
            $problem = ! $official || $official->deleted_at || $official->status !== 'approved' || ! $official->parent_delivery_id;
            if ($official && $official->parent_delivery_id) {
                $parent = $parents[$official->parent_delivery_id] ?? null;
                if (! $parent || $parent->deleted_at || $parent->status !== 'approved' || $parent->parent_delivery_id
                    || (int) $parent->product_id !== (int) $official->product_id || (int) $parent->sales_project_id !== (int) $official->sales_project_id
                    || (int) $parent->associate_id !== (int) $official->associate_id || bccomp($parent->total_distributed, (string) $parent->quantity, 4) > 0) {
                    $problem = true;
                }
            }
            foreach (['sales_project_id', 'customer_id', 'product_id', 'associate_id', 'parent_delivery_id'] as $field) {
                if ($official && (int) $official->$field !== (int) $snapshot[$field]) {
                    $problem = true;
                }
            }
            if ($official && bccomp((string) $official->quantity, (string) $snapshot['quantity'], 4) !== 0) {
                $problem = true;
            }
            if ($problem) {
                $warnings[] = ['code' => 'official_changed', 'link_id' => $link->id, 'message' => 'Distribuição #'.$link->distribution_id.' foi alterada, cancelada ou removida. Confira no fluxo oficial e reverta o vínculo quando permitido.'];
            } else {
                $valid = bcadd($valid, (string) $link->quantity, 4);
            }
            if ($official && $item->reference_price !== null && bccomp((string) $official->unit_price, $item->reference_price, 4) !== 0) {
                $warnings[] = ['code' => 'price_difference', 'link_id' => $link->id, 'message' => 'Preço oficial diferente da referência; a quantidade é conferida separadamente.'];
            }
        }
        $remaining = bcsub($item->reported_quantity ?? '0', $reserved, 4);
        $state = $item->cancelled_at ? 'cancelled' : (collect($warnings)->contains(fn ($warning) => in_array($warning['code'], ['official_changed', 'context_changed'], true)) || bccomp($remaining, '0', 4) < 0 ? 'divergent' : (bccomp($reserved, '0', 4) === 0 ? 'unreconciled' : (bccomp($remaining, '0', 4) === 0 ? 'reconciled' : 'partial')));

        return ['state' => $state, 'reported' => $item->reported_quantity, 'reconciled' => $valid, 'reserved' => $reserved,
            'remaining' => bccomp($remaining, '0', 4) < 0 ? '0.0000' : $remaining, 'warnings' => $warnings, 'links' => $links];
    }

    public function reverse(int $tenantId, User $actor, int $linkId, string $key, array $data): array
    {
        $this->authorize($tenantId, $actor);
        $data = Validator::make($data, ['version' => 'required|integer|min:1', 'reason' => 'required|string|min:5|max:2000'])->validate();

        return $this->sheets->operate($tenantId, $actor, 'reverse_link', $key, ['link_id' => $linkId] + $data, function ($operation) use ($tenantId, $actor, $linkId, $data) {
            $link = DB::table('pre_distribution_links')->where('tenant_id', $tenantId)->find($linkId);
            abort_unless($link, 404);
            $item = PreDistributionItem::withoutGlobalScopes()->where('tenant_id', $tenantId)->findOrFail($link->item_id);
            $sheet = PreDistributionSheet::withoutGlobalScopes()->where('tenant_id', $tenantId)->lockForUpdate()->findOrFail($item->sheet_id);
            abort_unless($sheet->version === (int) $data['version'], 409, 'A planilha mudou. Atualize antes de reverter.');
            if ($link->reversed_at) {
                $this->invalid('Este vínculo já foi revertido.');
            }
            $official = DB::table('production_deliveries')->where('tenant_id', $tenantId)->where('id', $link->distribution_id)->lockForUpdate()->first();
            if ($official && ! $official->deleted_at && ! in_array($official->status, ['cancelled', 'rejected'], true)) {
                $this->invalid('Corrija ou remova a distribuição pelo fluxo oficial autorizado antes de liberar a quantidade preliminar.');
            }
            DB::table('pre_distribution_links')->where('tenant_id', $tenantId)->where('id', $linkId)->update(['reversed_at' => now(), 'reversed_by' => $actor->id, 'reversal_reason' => $data['reason'], 'reversal_operation_id' => $operation->id]);
            $sheet->version++;
            $sheet->save();
            PreDistributionEvent::create(['tenant_id' => $tenantId, 'sheet_id' => $sheet->id, 'actor_id' => $actor->id, 'operation_id' => $operation->id,
                'event' => 'reconciliation_reversed', 'version' => $sheet->version, 'reason' => $data['reason'], 'before' => (array) $link, 'after' => ['link_id' => $linkId, 'reversed' => true], 'created_at' => now()]);

            return ['sheet_id' => $sheet->id, 'version' => $sheet->version, 'link_id' => $linkId];
        });
    }

    private function hash(int $receptionId, array $rows): string
    {
        // JSON validation retains only accepted attributes; recursively sort object keys for stable retries.
        $sort = function (array $value) use (&$sort): array {
            if (! array_is_list($value)) {
                ksort($value);
            } foreach ($value as &$entry) {
                if (is_array($entry)) {
                    $entry = $sort($entry);
                }
            }

            return $value;
        };

        return hash('sha256', json_encode($sort(['reception_id' => $receptionId, 'rows' => $rows]), JSON_THROW_ON_ERROR));
    }

    private function receptionTotals(int $tenantId, array $ids): Collection
    {
        return DB::table('production_deliveries')->where('tenant_id', $tenantId)->whereIn('parent_delivery_id', $ids)->whereNull('deleted_at')
            ->whereNotIn('status', ['cancelled', 'rejected'])->groupBy('parent_delivery_id')->selectRaw('parent_delivery_id, SUM(quantity) as total')->pluck('total', 'parent_delivery_id');
    }

    private function quantity(mixed $value): string
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            $this->invalid('Informe uma quantidade numérica válida.');
        }
        if (! preg_match('/^\d{1,14}(?:\.\d{1,4})?$/D', (string) $value) || bccomp((string) $value, '0', 4) <= 0) {
            $this->invalid('Informe uma quantidade positiva com até quatro casas decimais.');
        }

        return bcadd((string) $value, '0', 4);
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['pre_distributions' => $message]);
    }
}
