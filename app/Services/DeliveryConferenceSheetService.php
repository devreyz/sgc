<?php

namespace App\Services;

use App\Enums\CustomerReceiptStatus;
use App\Enums\DeliveryConferenceGroupingMode;
use App\Enums\DeliveryConferenceStatus;
use App\Enums\DeliveryStatus;
use App\Models\CustomerBillingReceipt;
use App\Models\DeliveryConferenceSheet;
use App\Models\ProductionDelivery;
use App\Models\SalesProject;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryConferenceSheetService
{
    public const SNAPSHOT_VERSION = 1;

    public function eligibleQuery(
        SalesProject $project,
        ?int $customerId,
        ?int $organizationId,
        string $periodStart,
        string $periodEnd,
    ): Builder {
        $query = ProductionDelivery::withoutGlobalScopes()
            ->where('tenant_id', $project->tenant_id)
            ->where('sales_project_id', $project->id)
            ->whereNotNull('parent_delivery_id')
            ->where('status', DeliveryStatus::APPROVED->value)
            ->where('quantity', '>', 0)
            ->whereBetween('delivery_date', [$periodStart, $periodEnd]);

        if ($customerId) {
            $query->where('customer_id', $customerId);
        } else {
            $query->whereHas('customer', fn (Builder $builder) => $builder
                ->withoutGlobalScopes()->where('tenant_id', $project->tenant_id)
                ->where('organization_id', $organizationId));
        }

        return $query;
    }

    public function createDraft(SalesProject $project, array $data, User $actor, ?DeliveryConferenceSheet $supersedes = null): DeliveryConferenceSheet
    {
        $this->assertRecipient($project, $data['customer_id'] ?? null, $data['organization_id'] ?? null);
        $mode = $this->validatedMode($data['grouping_mode'], ! empty($data['organization_id']));
        $distributions = $supersedes
            ? $supersedes->distributions()->get()
            : $this->eligibleQuery($project, $data['customer_id'] ?? null, $data['organization_id'] ?? null, $data['period_start'], $data['period_end'])
                ->orderBy('id')->get();

        if ($distributions->isEmpty()) {
            throw ValidationException::withMessages(['period_start' => 'Nenhuma distribuição aprovada foi encontrada para este destinatário e período.']);
        }

        return DB::transaction(function () use ($project, $data, $actor, $supersedes, $mode, $distributions): DeliveryConferenceSheet {
            $sheet = DeliveryConferenceSheet::create([
                'tenant_id' => $project->tenant_id,
                'sales_project_id' => $project->id,
                'customer_id' => $data['customer_id'] ?? null,
                'organization_id' => $data['organization_id'] ?? null,
                'period_start' => $data['period_start'],
                'period_end' => $data['period_end'],
                'grouping_mode' => $mode,
                'status' => DeliveryConferenceStatus::DRAFT,
                'revision' => $supersedes ? $supersedes->revision + 1 : 1,
                'supersedes_id' => $supersedes?->id,
                'created_by' => $actor->id,
            ]);
            $sheet->distributions()->sync($distributions->pluck('id')->all());
            activity()->performedOn($sheet)->causedBy($actor)->event('created')
                ->withProperties(['tenant_id' => $project->tenant_id, 'distribution_count' => $distributions->count()])
                ->log('Folha de conferência criada');

            return $sheet->load(['project', 'customer', 'organization', 'distributions.product', 'distributions.customer']);
        }, 5);
    }

    public function issue(DeliveryConferenceSheet $sheet, User $actor): DeliveryConferenceSheet
    {
        return DB::transaction(function () use ($sheet, $actor): DeliveryConferenceSheet {
            $lockedSheet = DeliveryConferenceSheet::withoutGlobalScopes()->where('tenant_id', $sheet->tenant_id)
                ->lockForUpdate()->findOrFail($sheet->id);
            if ($lockedSheet->status !== DeliveryConferenceStatus::DRAFT) {
                throw ValidationException::withMessages(['sheet' => 'Somente um rascunho pode ser emitido.']);
            }

            $ids = $lockedSheet->distributions()->orderBy('production_deliveries.id')->pluck('production_deliveries.id')->all();
            $distributions = ProductionDelivery::withoutGlobalScopes()->where('tenant_id', $lockedSheet->tenant_id)
                ->whereIn('id', $ids)->orderBy('id')->lockForUpdate()
                ->with(['product:id,name,unit', 'customer:id,tenant_id,name,organization_id'])->get();
            $this->assertLockedDistributions($lockedSheet, $distributions, $ids);
            $this->assertNoActiveDuplicate($lockedSheet, $ids);

            $lockedSheet->load(['project', 'customer', 'organization', 'tenant']);
            $year = now()->year;
            $number = app(ProjectReceiptNumberingService::class)->nextNumber(
                DeliveryConferenceSheet::class, (int) $lockedSheet->tenant_id, $year, $lockedSheet->project
            );
            $formatted = app(ProjectReceiptNumberingService::class)->format($lockedSheet->project, $number, $year, 'FC-');
            $lockedSheet->forceFill(['receipt_year' => $year, 'receipt_number' => $number, 'number' => $formatted]);
            $snapshot = $this->snapshot($lockedSheet, $distributions);

            $lockedSheet->forceFill([
                'snapshot_version' => self::SNAPSHOT_VERSION,
                'snapshot' => $snapshot,
                'snapshot_hash' => $this->hash($snapshot),
                'status' => DeliveryConferenceStatus::ISSUED,
                'issued_at' => now(),
                'issued_by' => $actor->id,
            ])->save();

            if ($lockedSheet->supersedes_id) {
                DeliveryConferenceSheet::withoutGlobalScopes()->where('tenant_id', $lockedSheet->tenant_id)
                    ->whereKey($lockedSheet->supersedes_id)->update(['status' => DeliveryConferenceStatus::SUPERSEDED->value]);
            }
            activity()->performedOn($lockedSheet)->causedBy($actor)->event('issued')
                ->withProperties(['tenant_id' => $lockedSheet->tenant_id, 'number' => $formatted, 'snapshot_hash' => $lockedSheet->snapshot_hash])
                ->log('Folha de conferência emitida');

            return $lockedSheet->fresh(['project', 'customer', 'organization']);
        }, 5);
    }

    public function updateDraft(DeliveryConferenceSheet $sheet, array $data, User $actor): DeliveryConferenceSheet
    {
        $mode = $this->validatedMode($data['grouping_mode'], (bool) $sheet->organization_id);
        $rows = $this->eligibleQuery(
            $sheet->project,
            $sheet->customer_id ? (int) $sheet->customer_id : null,
            $sheet->organization_id ? (int) $sheet->organization_id : null,
            $data['period_start'],
            $data['period_end'],
        )->orderBy('id')->get();
        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['period_start' => 'Nenhuma distribuição aprovada foi encontrada no novo período.']);
        }

        return DB::transaction(function () use ($sheet, $data, $mode, $rows, $actor): DeliveryConferenceSheet {
            $locked = DeliveryConferenceSheet::withoutGlobalScopes()->where('tenant_id', $sheet->tenant_id)->lockForUpdate()->findOrFail($sheet->id);
            if (! $locked->isDraft()) {
                throw ValidationException::withMessages(['sheet' => 'Uma folha emitida não pode ser alterada silenciosamente.']);
            }
            $locked->update([
                'period_start' => $data['period_start'],
                'period_end' => $data['period_end'],
                'grouping_mode' => $mode,
            ]);
            $locked->distributions()->sync($rows->pluck('id')->all());
            activity()->performedOn($locked)->causedBy($actor)->event('updated')
                ->withProperties(['tenant_id' => $locked->tenant_id, 'distribution_count' => $rows->count()])
                ->log('Rascunho da folha de conferência atualizado');

            return $locked->fresh();
        }, 5);
    }

    public function review(DeliveryConferenceSheet $sheet, string $decision, ?string $note, User $actor): DeliveryConferenceSheet
    {
        $status = DeliveryConferenceStatus::tryFrom($decision);
        if (! in_array($status, [DeliveryConferenceStatus::APPROVED, DeliveryConferenceStatus::CORRECTION_REQUESTED, DeliveryConferenceStatus::REJECTED], true)) {
            throw ValidationException::withMessages(['decision' => 'Resultado de conferência inválido.']);
        }
        if ($status !== DeliveryConferenceStatus::APPROVED && mb_strlen(trim((string) $note)) < 5) {
            throw ValidationException::withMessages(['review_note' => 'Informe o motivo da correção ou rejeição.']);
        }

        return DB::transaction(function () use ($sheet, $status, $note, $actor): DeliveryConferenceSheet {
            $locked = DeliveryConferenceSheet::withoutGlobalScopes()->where('tenant_id', $sheet->tenant_id)->lockForUpdate()->findOrFail($sheet->id);
            if ($locked->status !== DeliveryConferenceStatus::ISSUED) {
                throw ValidationException::withMessages(['sheet' => 'Apenas uma folha emitida e sem retorno pode ser conferida.']);
            }
            $locked->update(['status' => $status, 'reviewed_at' => now(), 'reviewed_by' => $actor->id, 'review_note' => trim((string) $note) ?: null]);
            activity()->performedOn($locked)->causedBy($actor)->event('reviewed')
                ->withProperties(['tenant_id' => $locked->tenant_id, 'decision' => $status->value])
                ->log('Retorno da folha de conferência registrado');

            return $locked->fresh();
        }, 5);
    }

    public function cancel(DeliveryConferenceSheet $sheet, string $reason, User $actor): void
    {
        if (! in_array($sheet->status, [DeliveryConferenceStatus::DRAFT, DeliveryConferenceStatus::ISSUED], true)) {
            throw ValidationException::withMessages(['sheet' => 'Esta folha não pode mais ser cancelada.']);
        }
        if (mb_strlen(trim($reason)) < 5) {
            throw ValidationException::withMessages(['reason' => 'Informe um motivo claro para o cancelamento.']);
        }
        $sheet->update(['status' => DeliveryConferenceStatus::CANCELLED, 'invalidated_at' => now(), 'invalidation_reason' => trim($reason)]);
        activity()->performedOn($sheet)->causedBy($actor)->event('cancelled')->withProperties(['tenant_id' => $sheet->tenant_id, 'reason' => trim($reason)])->log('Folha de conferência cancelada');
    }

    public function currentHash(DeliveryConferenceSheet $sheet): string
    {
        $sheet->loadMissing(['project', 'customer', 'organization']);
        $ids = collect(data_get($sheet->snapshot, 'distributions', []))->pluck('id')->map(fn ($id) => (int) $id)->all();
        $rows = ProductionDelivery::withoutGlobalScopes()->where('tenant_id', $sheet->tenant_id)->whereIn('id', $ids)
            ->with(['product:id,name,unit', 'customer:id,tenant_id,name,organization_id'])->orderBy('id')->get();
        if ($rows->count() !== count($ids)) {
            return hash('sha256', 'missing-distribution');
        }

        return $this->hash($this->snapshot($sheet, $rows));
    }

    public function previewSnapshot(DeliveryConferenceSheet $sheet): array
    {
        $sheet->loadMissing(['tenant', 'project', 'customer', 'organization']);
        $rows = $sheet->distributions()->with(['product:id,name,unit', 'customer:id,tenant_id,name,organization_id'])
            ->orderBy('production_deliveries.id')->get();

        return $this->snapshot($sheet, $rows);
    }

    public function isCurrentlyValid(DeliveryConferenceSheet $sheet): bool
    {
        return filled($sheet->snapshot_hash) && hash_equals($sheet->snapshot_hash, $this->currentHash($sheet));
    }

    public function prepareBilling(Collection $sheets, User $actor): CustomerBillingReceipt
    {
        if ($sheets->isEmpty()) {
            throw ValidationException::withMessages(['sheets' => 'Selecione ao menos uma folha aprovada.']);
        }
        $first = $sheets->first();
        foreach ($sheets as $sheet) {
            if ((int) $sheet->tenant_id !== (int) $first->tenant_id || (int) $sheet->sales_project_id !== (int) $first->sales_project_id
                || (int) $sheet->customer_id !== (int) $first->customer_id || (int) $sheet->organization_id !== (int) $first->organization_id
                || ! $sheet->isApproved() || ! $this->isCurrentlyValid($sheet)) {
                throw ValidationException::withMessages(['sheets' => 'As folhas devem estar aprovadas, válidas e possuir o mesmo projeto e destinatário.']);
            }
        }
        $ids = $sheets->flatMap(fn (DeliveryConferenceSheet $sheet) => data_get($sheet->snapshot, 'distributions', []))
            ->pluck('id')->map(fn ($id) => (int) $id)->unique()->sort()->values();
        $project = SalesProject::withoutGlobalScopes()->where('tenant_id', $first->tenant_id)->findOrFail($first->sales_project_id);
        $distributions = ProductionDelivery::withoutGlobalScopes()->where('tenant_id', $first->tenant_id)->whereIn('id', $ids)
            ->orderBy('id')->get();
        $billed = $distributions->whereNotNull('billing_receipt_id');
        if ($billed->isNotEmpty()) {
            throw ValidationException::withMessages(['sheets' => $billed->count().' distribuições já pertencem a outra cobrança: '.$billed->pluck('billing_receipt_id')->unique()->implode(', ').'.']);
        }

        $receipt = DB::transaction(function () use ($project, $first, $sheets, $ids, $actor): CustomerBillingReceipt {
            $receipt = CustomerBillingReceipt::create(array_merge(
                CustomerBillingReceipt::numberingFor($project),
                [
                    'tenant_id' => $first->tenant_id, 'sales_project_id' => $first->sales_project_id,
                    'customer_id' => $first->customer_id, 'organization_id' => $first->organization_id,
                    'issued_at' => now(), 'from_date' => $sheets->min('period_start'), 'to_date' => $sheets->max('period_end'),
                    'notes' => 'Preparada a partir das folhas: '.$sheets->pluck('number')->implode(', '),
                    'delivery_ids' => $ids->all(), 'status' => CustomerReceiptStatus::DRAFT, 'created_by' => $actor->id,
                ]
            ));

            return $receipt;
        }, 5);
        activity()->performedOn($first)->causedBy($actor)->event('billing_prepared')
            ->withProperties(['tenant_id' => $first->tenant_id, 'sheet_ids' => $sheets->pluck('id'), 'receipt_id' => $receipt->id])
            ->log('Rascunho de cobrança preparado a partir de folhas de conferência');

        return $receipt->fresh();
    }

    private function snapshot(DeliveryConferenceSheet $sheet, Collection $distributions): array
    {
        $rows = $distributions->sortBy('id')->map(fn (ProductionDelivery $distribution) => [
            'id' => (int) $distribution->id,
            'date' => $distribution->delivery_date?->format('Y-m-d'),
            'customer' => ['id' => (int) $distribution->customer_id, 'name' => (string) $distribution->customer?->name],
            'product' => ['id' => (int) $distribution->product_id, 'name' => (string) $distribution->product?->name],
            'unit' => (string) $distribution->product?->unit,
            'quantity' => number_format((float) $distribution->quantity, 4, '.', ''),
        ])->values()->all();

        $grouped = collect($rows)->groupBy(function (array $row) use ($sheet): string {
            $prefix = $sheet->grouping_mode === DeliveryConferenceGroupingMode::ORGANIZATION_DETAILED
                ? $row['customer']['id'].'|'.$row['customer']['name'].'|' : '';

            return $prefix.$row['product']['id'].'|'.$row['product']['name'].'|'.$row['unit'];
        })->map(function (Collection $items): array {
            $first = $items->first();

            return [
                'customer' => $first['customer'], 'product' => $first['product'], 'unit' => $first['unit'],
                'quantity' => number_format($items->sum(fn (array $item) => (float) $item['quantity']), 4, '.', ''),
            ];
        })->sortBy(fn (array $row) => ($row['customer']['name'] ?? '').'|'.$row['product']['name'].'|'.$row['unit'])->values()->all();

        return [
            'version' => self::SNAPSHOT_VERSION,
            'sheet' => ['number' => $sheet->number, 'revision' => (int) $sheet->revision],
            'tenant' => ['id' => (int) $sheet->tenant_id, 'name' => (string) $sheet->tenant?->name],
            'project' => ['id' => (int) $sheet->sales_project_id, 'title' => (string) $sheet->project?->title],
            'recipient' => $sheet->organization_id
                ? ['type' => 'organization', 'id' => (int) $sheet->organization_id, 'name' => (string) $sheet->organization?->name]
                : ['type' => 'customer', 'id' => (int) $sheet->customer_id, 'name' => (string) $sheet->customer?->name],
            'period' => ['start' => $sheet->period_start->format('Y-m-d'), 'end' => $sheet->period_end->format('Y-m-d')],
            'grouping_mode' => $sheet->grouping_mode->value,
            'distributions' => $rows,
            'rows' => $grouped,
        ];
    }

    private function hash(array $snapshot): string
    {
        $normalize = function (mixed $value) use (&$normalize): mixed {
            if (! is_array($value)) {
                return $value;
            }
            if (! array_is_list($value)) {
                ksort($value);
            }

            return array_map($normalize, $value);
        };

        return hash('sha256', json_encode($normalize($snapshot), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));
    }

    private function assertRecipient(SalesProject $project, ?int $customerId, ?int $organizationId): void
    {
        if (($customerId ? 1 : 0) + ($organizationId ? 1 : 0) !== 1) {
            throw ValidationException::withMessages(['recipient_type' => 'Escolha exatamente um cliente ou uma organização.']);
        }
        $valid = $customerId
            ? $project->all_customers->contains(fn ($customer) => (int) $customer->id === $customerId && (int) $customer->tenant_id === (int) $project->tenant_id)
            : $project->organizations()->where('organizations.tenant_id', $project->tenant_id)->whereKey($organizationId)->exists();
        if (! $valid) {
            throw ValidationException::withMessages(['recipient_id' => 'O destinatário não pertence a este projeto e tenant.']);
        }
    }

    private function validatedMode(string $mode, bool $organization): DeliveryConferenceGroupingMode
    {
        $enum = DeliveryConferenceGroupingMode::tryFrom($mode);
        $allowed = $organization
            ? [DeliveryConferenceGroupingMode::ORGANIZATION_DETAILED, DeliveryConferenceGroupingMode::ORGANIZATION_CONSOLIDATED]
            : [DeliveryConferenceGroupingMode::CUSTOMER];
        if (! $enum || ! in_array($enum, $allowed, true)) {
            throw ValidationException::withMessages(['grouping_mode' => 'Modo de apresentação incompatível com o destinatário.']);
        }

        return $enum;
    }

    private function assertLockedDistributions(DeliveryConferenceSheet $sheet, Collection $rows, array $ids): void
    {
        if ($rows->count() !== count($ids) || $rows->contains(fn (ProductionDelivery $row) => ! $row->parent_delivery_id || (int) $row->tenant_id !== (int) $sheet->tenant_id
            || (int) $row->sales_project_id !== (int) $sheet->sales_project_id
            || $row->status !== DeliveryStatus::APPROVED || (float) $row->quantity <= 0
            || ($sheet->customer_id && (int) $row->customer_id !== (int) $sheet->customer_id)
            || ($sheet->organization_id && (int) $row->customer?->organization_id !== (int) $sheet->organization_id)
        )) {
            throw ValidationException::withMessages(['distributions' => 'Uma ou mais distribuições deixaram de ser elegíveis. Atualize o rascunho.']);
        }
    }

    private function assertNoActiveDuplicate(DeliveryConferenceSheet $sheet, array $ids): void
    {
        $duplicate = DB::table('delivery_conference_sheet_items as item')
            ->join('delivery_conference_sheets as existing', 'existing.id', '=', 'item.delivery_conference_sheet_id')
            ->where('existing.tenant_id', $sheet->tenant_id)->where('existing.sales_project_id', $sheet->sales_project_id)
            ->where('existing.id', '<>', $sheet->id)->whereIn('item.distribution_id', $ids)
            ->whereIn('existing.status', ['issued', 'approved', 'correction_requested'])
            ->when($sheet->supersedes_id, fn ($query) => $query->where('existing.id', '<>', $sheet->supersedes_id))
            ->value('existing.number');
        if ($duplicate) {
            throw ValidationException::withMessages(['distributions' => 'Há distribuições já incluídas na folha ativa '.$duplicate.'.']);
        }
    }
}
