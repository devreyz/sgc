@php
    $quantityTotals = [];
    foreach ($quantityRows as $quantityRow) {
        $quantityUnit = trim((string) ($quantityRow['unit'] ?? '')) ?: 'un';
        $quantityValue = isset($quantityCustomerId)
            ? ($quantityRow['by_customer'][$quantityCustomerId] ?? 0)
            : ($quantityRow[$quantityKey ?? 'quantity'] ?? 0);
        $quantityTotals[$quantityUnit] = bcadd($quantityTotals[$quantityUnit] ?? '0', (string) $quantityValue, 8);
    }
    ksort($quantityTotals);
@endphp
@forelse($quantityTotals as $quantityUnit => $quantityValue)
    <span style="display:block;white-space:nowrap">{{ rtrim(rtrim(number_format((float) $quantityValue, 4, ',', '.'), '0'), ',') }}&nbsp;{{ $quantityUnit }}</span>
@empty
    <span>—</span>
@endforelse
