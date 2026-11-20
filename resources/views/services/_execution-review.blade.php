@php
    $derived = (array) $execution->derived_values;
    $values = (array) $execution->values;
    $config = (array) data_get($execution->catalog_snapshot, 'execution_config', []);
    $fields = collect(data_get($execution->catalog_snapshot, 'fields', []))->keyBy('key');
    $number = static fn ($value) => rtrim(rtrim(number_format((float) $value, 4, ',', '.'), '0'), ',');
    $quantity = static fn ($value, $unit) => $value === null
        ? 'Não informado'
        : trim($number($value).' '.(in_array($unit, ['hora', 'horas'], true) ? 'h' : ($unit ?? '')));
    $primary = (array) ($derived['calculation'] ?? []);
    [$configuredStart, $configuredEnd] = \App\Services\Services\ServiceCalculationRules::meterPair((array) $execution->catalog_snapshot);
    $startField = $primary['meter_start_field'] ?? $configuredStart;
    $endField = $primary['meter_end_field'] ?? $configuredEnd;
    $measurement = $derived['primary_quantity'] ?? $execution->quantity;
    $visibleFields = $fields->filter(fn ($field) => !in_array($field['type'] ?? '', ['image', 'file', 'signature'], true)
        && (($operator ?? false) ? ($field['visible_to_management'] ?? true) : ($field['visible_to_provider'] ?? false)));
    $readMeters = static function ($startKey, $endKey, $fallbackUnit) use ($values, $fields, $visibleFields): ?array {
        if (!$startKey || !$endKey
            || ($fields->has($startKey) && !$visibleFields->has($startKey))
            || ($fields->has($endKey) && !$visibleFields->has($endKey))) {
            return null;
        }
        $start = is_numeric($values[$startKey] ?? null) ? $values[$startKey] : null;
        $end = is_numeric($values[$endKey] ?? null) ? $values[$endKey] : null;
        $encoded = $values['meter_pauses_'.$startKey] ?? '[]';
        $pauses = is_string($encoded) ? json_decode($encoded, true) : [];
        $pauses = collect(is_array($pauses) ? $pauses : [])
            ->filter(fn ($pause) => is_array($pause) && is_numeric($pause['stop'] ?? null) && is_numeric($pause['resume'] ?? null))
            ->values();

        $unit = $fields->get($startKey)['unit'] ?? $fallbackUnit;
        if (in_array($unit, ['hora', 'horas'], true)) {
            $unit = 'h';
        }

        return ['start' => $start, 'end' => $end, 'pauses' => $pauses, 'unit' => $unit,
            'start_label' => $visibleFields->get($startKey)['label'] ?? 'Leitura inicial',
            'end_label' => $visibleFields->get($endKey)['label'] ?? 'Leitura final',
            'paused' => $pauses->sum(fn ($pause) => (float) $pause['resume'] - (float) $pause['stop'])];
    };
    $meter = $readMeters($startField, $endField, $execution->unit);
    $quantityField = $primary['quantity_field'] ?? $config['quantity_field'] ?? null;
    $otherFields = $visibleFields->except($meter ? [$startField, $endField] : []);
    $directions = [
        'customer' => ['label' => 'Quantidade da cobrança', 'lines' => 'receivable', 'enabled' => 'receivable_enabled'],
        'provider' => ['label' => 'Quantidade da remuneração', 'lines' => 'payable', 'enabled' => 'payable_enabled'],
    ];
@endphp

<div class="svc-review-measurements">
    <div class="svc-review-quantity">
        <span>{{ $meter ? ($meter['unit'] === 'h' ? 'Horas trabalhadas' : 'Quantidade líquida') : ($visibleFields->get($quantityField)['label'] ?? 'Quantidade registrada') }}</span>
        <strong>{{ $quantity($measurement, $execution->unit) }}</strong>
        @if($meter && $meter['start'] !== null && $meter['end'] !== null && $meter['pauses']->isNotEmpty())
            <small>{{ $quantity($meter['end'] - $meter['start'], $meter['unit']) }} no medidor − {{ $quantity($meter['paused'], $meter['unit']) }} de pausa</small>
            @if($measurement !== null && abs($measurement - ($meter['end'] - $meter['start'] - $meter['paused'])) > .0001)
                <small role="alert">A quantidade registrada difere da leitura líquida. Confira as medições antes de concluir.</small>
            @endif
        @endif
    </div>
    @if($meter)
        @include('services._execution-review-meter', ['reading' => $meter])
    @endif

    @foreach(($showFinancialQuantities ?? true) ? $directions : [] as $direction => $options)
        @php
            $base = collect($financialPreview[$options['lines']] ?? [])->firstWhere('type', 'base');
            $calculation = $primary[$direction] ?? [];
            $result = $base['quantity'] ?? $calculation['result'] ?? $derived[$direction.'_quantity'] ?? null;
            $unit = $base['unit'] ?? $config[$direction.'_unit'] ?? $execution->unit;
            $independentMeter = ($calculation['mode'] ?? null) === 'meter_difference'
                && [($calculation['meter_start_field'] ?? null), ($calculation['meter_end_field'] ?? null)] !== [$startField, $endField];
        @endphp
        @continue($result === null || !data_get($execution->catalog_snapshot, $options['enabled'], true))
        <div class="svc-review-direction">
            <span>{{ $options['label'] }}</span>
            <strong>{{ $quantity($result, $unit) }}</strong>
            @if(data_get($base, 'rule_snapshot.method') === 'quantity_x_rate' && isset($base['unit_price']))
                <small>Tarifa aplicada: R$ {{ number_format((float) $base['unit_price'], 2, ',', '.') }}</small>
            @endif
            @if(isset($visibleFields[$calculation['field'] ?? '']))
                <small>{{ $visibleFields[$calculation['field']]['label'] }}</small>
            @endif
        </div>
        @if($independentMeter && ($reading = $readMeters($calculation['meter_start_field'] ?? null, $calculation['meter_end_field'] ?? null, $unit)))
            @include('services._execution-review-meter', compact('reading'))
        @endif
    @endforeach

    @if($otherFields->isNotEmpty())
        <div class="svc-review-table-wrap">
            <table class="svc-review-table" aria-label="Outros dados da execução">
                <tbody>
                    @foreach($otherFields as $key => $field)
                        @php
                            $value = $values[$key] ?? null;
                            $display = is_bool($value) ? ($value ? 'Sim' : 'Não') : (is_array($value) ? implode(', ', array_filter($value, 'is_scalar')) : $value);
                        @endphp
                        <tr>
                            <th scope="row">{{ $field['label'] ?? 'Dado da execução' }}</th>
                            <td>@if(filled($display)){{ $display }} {{ $field['unit'] ?? '' }}@else<small>Não informado</small>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
