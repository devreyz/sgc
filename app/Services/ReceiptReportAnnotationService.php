<?php

namespace App\Services;

use App\Models\ProductionDelivery;
use Illuminate\Database\Eloquent\Model;

class ReceiptReportAnnotationService
{
    public function targetOptions(array $deliveryIds, int $tenantId): array
    {
        $ids = collect($deliveryIds)->map(fn ($id): int => (int) $id)->filter()->unique()->all();
        if ($ids === [] || $tenantId <= 0) {
            return [];
        }

        $rows = ProductionDelivery::withoutGlobalScopes()->where('tenant_id', $tenantId)
            ->whereIn('id', $ids)->whereNull('deleted_at')
            ->with(['product:id,name', 'parentDelivery:id,notes'])
            ->get(['id', 'tenant_id', 'product_id', 'parent_delivery_id', 'delivery_date', 'notes']);
        $products = $rows->filter(fn ($row): bool => (int) $row->product_id > 0)
            ->unique('product_id')->mapWithKeys(fn ($row): array => [
                'product:'.$row->product_id => 'Produto: '.($row->product?->name ?: '#'.$row->product_id),
            ])->all();
        $distributions = $rows->mapWithKeys(fn ($row): array => [
            'distribution:'.$row->id => 'Distribuição #'.$row->id.' · '.($row->product?->name ?: 'Produto').' · '.($row->delivery_date?->format('d/m/Y') ?: 'sem data'),
        ])->all();
        $deliveryNotes = $rows->filter(fn ($row): bool => $this->deliveryNote($row) !== '')
            ->mapWithKeys(fn ($row): array => [
                'delivery-note:'.$row->id => 'Observação da entrega · '.($row->product?->name ?: 'Produto').' · '.($row->delivery_date?->format('d/m/Y') ?: 'sem data'),
            ])->all();

        return ['global' => 'Observação geral (sem marcador)', ...$products, ...$distributions, ...$deliveryNotes];
    }

    /** @return array<string, string> */
    public function deliveryNoteTexts(array $deliveryIds, int $tenantId): array
    {
        $ids = collect($deliveryIds)->map(fn ($id): int => (int) $id)->filter()->unique();
        if ($ids->isEmpty() || $tenantId <= 0) {
            return [];
        }

        return ProductionDelivery::withoutGlobalScopes()->where('tenant_id', $tenantId)
            ->whereIn('id', $ids)->whereNull('deleted_at')->with('parentDelivery:id,notes')
            ->get(['id', 'parent_delivery_id', 'notes'])
            ->mapWithKeys(fn (ProductionDelivery $row): array => ['delivery-note:'.$row->id => $this->deliveryNote($row)])
            ->filter()->all();
    }

    public function notes(Model $receipt): array
    {
        $entries = collect($receipt->report_annotations ?? []);
        $number = 0;

        return $entries->filter(fn ($item): bool => is_array($item) && trim((string) ($item['text'] ?? '')) !== '')
            ->take(30)->map(function (array $item) use (&$number): array {
                $target = (string) ($item['target'] ?? 'global');
                if (! preg_match('/^(product|distribution|delivery-note):[1-9][0-9]*$/', $target)) {
                    $target = 'global';
                }
                if ($target !== 'global') {
                    $number++;
                }

                return [
                    'target' => $target,
                    'marker' => $target === 'global' ? null : $this->superscript($number),
                    'text' => mb_substr(trim((string) $item['text']), 0, 1000),
                ];
            })->values()->all();
    }

    public function validateTargets(array $items, array $deliveryIds, int $tenantId): array
    {
        $allowed = $this->targetOptions($deliveryIds, $tenantId);
        $deliveryNotes = $this->deliveryNoteTexts($deliveryIds, $tenantId);
        $selectedDeliveryNoteTargets = collect($deliveryIds)
            ->map(fn ($id): string => 'delivery-note:'.(int) $id)
            ->flip();

        return collect($items)->map(function (array $item) use ($allowed, $deliveryNotes, $selectedDeliveryNoteTargets): array {
            $target = (string) ($item['target'] ?? 'global');
            $text = trim((string) ($item['text'] ?? ''));
            if (str_starts_with($target, 'delivery-note:') && isset($deliveryNotes[$target])) {
                $text = $deliveryNotes[$target];
            }
            $targetIsAllowed = array_key_exists($target, $allowed)
                || $selectedDeliveryNoteTargets->has($target);
            if (! $targetIsAllowed || $text === '' || mb_strlen($text) > 1000) {
                throw new \RuntimeException('Uma observação aponta para um produto ou distribuição fora deste comprovante.');
            }

            return ['target' => $target, 'text' => $text];
        })->values()->all();
    }

    public function markers(array $notes, int $productId, array $distributionIds = []): string
    {
        $targets = array_merge(['product:'.$productId], array_map(
            fn ($id): string => 'distribution:'.(int) $id, $distributionIds,
        ), array_map(fn ($id): string => 'delivery-note:'.(int) $id, $distributionIds));

        return collect($notes)->filter(fn (array $note): bool => in_array($note['target'], $targets, true))
            ->pluck('marker')->implode('');
    }

    private function superscript(int $number): string
    {
        return str_replace(
            str_split('0123456789'),
            ['⁰', '¹', '²', '³', '⁴', '⁵', '⁶', '⁷', '⁸', '⁹'],
            (string) $number,
        );
    }

    private function deliveryNote(ProductionDelivery $distribution): string
    {
        return trim((string) ($distribution->parentDelivery?->notes ?: $distribution->notes ?: ''));
    }
}
