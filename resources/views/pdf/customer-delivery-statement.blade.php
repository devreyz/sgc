@php
/**
     * Relatório de Entregas por Cliente — tabela única, design B&W limpo.
     */
    $logoPath = null; $hasLogo = false;
    if ($tenant && !empty($tenant->logo)) {
        $raw = trim($tenant->logo);
        if (preg_match('/^https?:\/\//i', $raw) || str_starts_with($raw, '//')) {
            $logoPath = $raw; $hasLogo = true;
        } else {
            $c1 = public_path('storage/' . $raw);
            if (file_exists($c1)) { $logoPath = $c1; $hasLogo = true; }
            else { $c2 = public_path($raw); if (file_exists($c2)) { $logoPath = $c2; $hasLogo = true; }
            else { $logoPath = asset('storage/' . ltrim($raw, '/')); $hasLogo = true; } }
        }
    }
    $showUnitPrice = $show_unit_price ?? true;
    $showTotal     = $show_total ?? true;
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Extrato de Entregas - {{ $customer->name ?? $customer->trade_name ?? 'Cliente' }}</title>
<style>
@page { size: A4 portrait; margin: 1.2cm 1.5cm; }
body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 9pt; color: #111; line-height: 1.4; }
.header { text-align: center; margin-bottom: 20px; border-bottom: 1px solid #ccc; padding-bottom: 8px; }
.header .title { font-size: 16pt; font-weight: bold; }
.header .subtitle { font-size: 10pt; color: #555; margin-top: 4px; }
.org-info { margin-bottom: 20px; border: 1px solid #ddd; padding: 10px; background: #f9f9f9; border-radius: 4px; }
.org-info p { margin: 4px 0; }
.customer-info { margin-bottom: 20px; }
.customer-info h3 { margin: 0 0 6px 0; font-size: 12pt; }
table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
table th { background: #eaeaea; border-bottom: 2px solid #333; padding: 6px 4px; text-align: left; font-size: 8.5pt; }
table td { border-bottom: 1px solid #ddd; padding: 5px 4px; vertical-align: top; }
table th.right, table td.right { text-align: right; }
.product-group { margin-bottom: 20px; }
.product-title { background: #f0f0f0; padding: 4px 8px; font-weight: bold; margin: 10px 0 5px 0; border-left: 4px solid #666; }
.total-row { font-weight: bold; background: #f5f5f5; }
.grand-total { margin-top: 15px; padding: 6px 10px; background: #222; color: white; text-align: right; font-weight: bold; }
.footer { margin-top: 30px; font-size: 7pt; text-align: center; color: #777; border-top: 1px solid #ccc; padding-top: 8px; }
</style>
</head>
<body>

<div class="header">
    @if($hasLogo)<img src="{{ $logoPath }}" style="max-height: 50px; margin-bottom: 8px;">@endif
    <div class="title">{{ $tenant->name ?? '' }}</div>
    <div class="subtitle">Extrato de Entregas - Período: {{ $period_label }}</div>
</div>

<div class="org-info">
    <strong>{{ $organization->name ?? '' }}</strong><br>
    @if($organization?->cnpj)CNPJ: {{ $organization->cnpj }}<br>@endif
    @if($organization?->address){{ $organization->address }}@endif
</div>

<div class="customer-info">
    <h3>Cliente: {{ $customer->name ?? $customer->trade_name ?? '' }}</h3>
    @if($customer->cpf_cnpj)<p>CPF/CNPJ: {{ $customer->cpf_cnpj }}</p>@endif
    @if($project_label)<p>Projeto: {{ $project_label }}</p>@endif
</div>

@if($ungrouped)
    {{-- Modo não agrupado: lista simples por data --}}
    <table>
        <thead>
            <tr>
                <th>Data</th>
                <th>Produto</th>
                <th class="right">Quantidade</th>
                @if($showUnitPrice)<th class="right">Preço Unitário (R$)</th>@endif
                @if($showTotal)<th class="right">Total (R$)</th>@endif
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
            <tr>
                <td>{{ $row['date'] }}</td>
                <td>{{ $row['product_name'] }}</td>
                <td class="right">{{ number_format($row['quantity'], 3, ',', '.') }} {{ $row['unit'] }}</td>
                @if($showUnitPrice)<td class="right">{{ number_format($row['unit_price'], 2, ',', '.') }}</td>@endif
                @if($showTotal)<td class="right">{{ number_format($row['total'], 2, ',', '.') }}</td>@endif
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="2"><strong>Totais</strong></td>
                <td class="right"><strong>{{ number_format($totals['total_qty'], 3, ',', '.') }}</strong></td>
                @if($showUnitPrice)<td></td>@endif
                @if($showTotal)<td class="right"><strong>R$ {{ number_format($totals['total_gross'], 2, ',', '.') }}</strong></td>@endif
            </tr>
        </tfoot>
    </table>
@else
    {{-- Modo agrupado por produto (com detalhamento por data) --}}
    @foreach($product_groups as $group)
    <div class="product-group">
        <div class="product-title">{{ $group['product_name'] }} ({{ $group['unit'] }})</div>
        <table>
            <thead>
                <tr>
                    <th>Data da Entrega</th>
                    <th class="right">Quantidade</th>
                    @if($showUnitPrice)<th class="right">Preço Unitário (R$)</th>@endif
                    @if($showTotal)<th class="right">Valor Total (R$)</th>@endif
                </tr>
            </thead>
            <tbody>
                @foreach($group['rows'] as $row)
                <tr>
                    <td>{{ $row['delivery_date'] }}</td>
                    <td class="right">{{ number_format($row['quantity'], 3, ',', '.') }} {{ $group['unit'] }}</td>
                    @if($showUnitPrice)<td class="right">{{ number_format($row['unit_price'], 2, ',', '.') }}</td>@endif
                    @if($showTotal)<td class="right">{{ number_format($row['gross'], 2, ',', '.') }}</td>@endif
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td><strong>Total {{ $group['product_name'] }}</strong></td>
                    <td class="right"><strong>{{ number_format($group['total_qty'], 3, ',', '.') }} {{ $group['unit'] }}</strong></td>
                    @if($showUnitPrice)<td></td>@endif
                    @if($showTotal)<td class="right"><strong>R$ {{ number_format($group['total_gross'], 2, ',', '.') }}</strong></td>@endif
                </tr>
            </tfoot>
        </table>
    </div>
    @endforeach
@endif

<div class="grand-total">
    TOTAL GERAL: R$ {{ number_format($totals['total_gross'], 2, ',', '.') }} ({{ number_format($totals['total_qty'], 3, ',', '.') }} unidades)
</div>

<div class="footer">
    Documento gerado eletronicamente em {{ $generated_at }}.<br>
    @if($tenant?->cnpj) {{ $tenant->name }} - CNPJ: {{ $tenant->cnpj }} @endif
</div>

</body>
</html>