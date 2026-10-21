<?php

namespace App\Services;

use App\Enums\CustomerReceiptStatus;
use App\Models\CustomerBillingReceipt;
use Illuminate\Support\Collection;

/** Consulta documental: não cria nem corrige vínculos financeiros. */
class DistributionBillingReferenceService
{
    /** @return array<int, list<array{id:int,number:string,status:string,source:string}>> */
    public function forDistributions(int $tenantId, Collection $distributions): array
    {
        $rows = $distributions->filter(fn ($row) => (int) $row->tenant_id === $tenantId)->keyBy('id');
        if ($rows->isEmpty()) {
            return [];
        }
        $references = [];
        $linkedRows = $rows->filter(fn ($row) => $row->billing_receipt_id)->groupBy('billing_receipt_id');
        $billings = CustomerBillingReceipt::withoutGlobalScopes()->where('tenant_id', $tenantId)
            ->with('project')->get();
        foreach ($billings as $billing) {
            $reference = [
                'id' => (int) $billing->id,
                'number' => $billing->formatted_number,
                'status' => $billing->status?->getLabel() ?? 'Rascunho',
            ];
            foreach ($linkedRows->get($billing->id, collect()) as $row) {
                $references[(int) $row->id][] = $reference + ['source' => 'linked'];
            }
            foreach ((array) $billing->delivery_ids as $id) {
                $row = $rows->get((int) $id);
                if (! $row || (int) $row->billing_receipt_id === (int) $billing->id) {
                    continue;
                }
                $references[(int) $row->id][] = $reference + [
                    'source' => $billing->status === CustomerReceiptStatus::DRAFT ? 'draft' : 'snapshot',
                ];
            }
        }
        foreach ($references as &$items) {
            $items = collect($items)->unique('id')->sortBy(fn ($item) => match ($item['source']) {
                'linked' => 0, 'draft' => 1, default => 2,
            })->values()->all();
        }

        return $references;
    }
}
