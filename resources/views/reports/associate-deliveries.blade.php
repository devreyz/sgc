@extends('reports.layout')

@section('content')

{{-- ── Título do Relatório ── --}}
<div class="report-title">
    <h2>Relatório de Entregas por Associado</h2>
    @if(isset($period))
        <div class="subtitle">Período: {{ $period }}</div>
    @endif
</div>

{{-- ── Dados do Associado ── --}}
<div class="bento-grid cols-3" style="margin-bottom: 18px;">
    <div class="bento-card">
        <div class="label">Associado</div>
        <div class="value">{{ optional($associate->user)->name ?? $associate->property_name ?? 'N/D' }}</div>
    </div>
    <div class="bento-card">
        <div class="label">CPF / CNPJ</div>
        <div class="value">{{ $associate->cpf_cnpj ?? 'N/D' }}</div>
    </div>
    <div class="bento-card">
        <div class="label">Código do Membro</div>
        <div class="value">{{ $associate->member_code ?? $associate->registration_number ?? 'N/D' }}</div>
    </div>
    <div class="bento-card">
        <div class="label">Propriedade</div>
        <div class="value">{{ $associate->property_name ?? 'N/D' }}</div>
    </div>
    <div class="bento-card">
        <div class="label">Cidade / Estado</div>
        <div class="value">{{ $associate->city ?? 'N/D' }}{{ $associate->state ? ' / ' . $associate->state : '' }}</div>
    </div>
    <div class="bento-card">
        <div class="label">Admitido em</div>
        <div class="value">
            {{ $associate->admission_date ? $associate->admission_date->format('d/m/Y') : 'N/D' }}
        </div>
    </div>
</div>

{{-- ── Resumo Financeiro ── --}}
<div class="bento-grid cols-4" style="margin-bottom: 18px;">
    <div class="bento-card highlight">
        <div class="label">Total de Entregas</div>
        <div class="value">{{ $deliveries->count() }}</div>
    </div>
    <div class="bento-card highlight">
        <div class="label">Quantidade Total</div>
        <div class="value">{{ number_format($deliveries->sum('quantity'), 3, ',', '.') }}</div>
    </div>
    <div class="bento-card highlight">
        <div class="label">Valor Bruto Total</div>
        <div class="value">R$ {{ number_format($deliveries->sum('gross_value'), 2, ',', '.') }}</div>
    </div>
    <div class="bento-card highlight">
        <div class="label">Valor Líquido Total</div>
        <div class="value">R$ {{ number_format($deliveries->sum('net_value'), 2, ',', '.') }}</div>
    </div>
</div>

{{-- ── Tabela de Entregas ── --}}
<table class="report-table">
    <thead>
        <tr>
            <th>#</th>
            <th>Data</th>
            <th>Produto</th>
            <th>Projeto</th>
            <th class="text-right">Quantidade</th>
            <th class="text-right">Preço Unit.</th>
            <th class="text-right">Valor Bruto</th>
            <th class="text-right">Taxa Adm.</th>
            <th class="text-right">Valor Líquido</th>
            <th class="text-center">Status</th>
            <th class="text-center">Pago</th>
        </tr>
    </thead>
    <tbody>
        @forelse($deliveries as $i => $delivery)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ $delivery->delivery_date?->format('d/m/Y') ?? '—' }}</td>
            <td>{{ optional($delivery->product)->name ?? '—' }}</td>
            <td>{{ optional($delivery->salesProject)->name ?? '—' }}</td>
            <td class="text-right">{{ number_format($delivery->quantity, 3, ',', '.') }}</td>
            <td class="text-right">R$ {{ number_format($delivery->unit_price, 2, ',', '.') }}</td>
            <td class="text-right">R$ {{ number_format($delivery->gross_value, 2, ',', '.') }}</td>
            <td class="text-right">R$ {{ number_format($delivery->admin_fee_amount, 2, ',', '.') }}</td>
            <td class="text-right"><strong>R$ {{ number_format($delivery->net_value, 2, ',', '.') }}</strong></td>
            <td class="text-center">
                @php
                    $statusColors = [
                        'pending'  => 'badge-warning',
                        'approved' => 'badge-success',
                        'rejected' => 'badge-danger',
                    ];
                    $statusLabels = [
                        'pending'  => 'Pendente',
                        'approved' => 'Aprovada',
                        'rejected' => 'Rejeitada',
                    ];
                    $statusVal = $delivery->status instanceof \BackedEnum ? $delivery->status->value : $delivery->status;
                @endphp
                <span class="badge {{ $statusColors[$statusVal] ?? 'badge-gray' }}">
                    {{ $statusLabels[$statusVal] ?? ucfirst($statusVal) }}
                </span>
            </td>
            <td class="text-center">
                @if($delivery->paid)
                    <span class="badge badge-success">✓ Sim</span>
                @else
                    <span class="badge badge-warning">Não</span>
                @endif
            </td>
        </tr>
        @empty
        <tr>
            <td colspan="11" style="text-align:center; padding: 20px; color:#888;">
                Nenhuma entrega encontrada no período selecionado.
            </td>
        </tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr>
            <td colspan="4"><strong>TOTAL</strong></td>
            <td class="text-right"><strong>{{ number_format($deliveries->sum('quantity'), 3, ',', '.') }}</strong></td>
            <td></td>
            <td class="text-right"><strong>R$ {{ number_format($deliveries->sum('gross_value'), 2, ',', '.') }}</strong></td>
            <td class="text-right"><strong>R$ {{ number_format($deliveries->sum('admin_fee_amount'), 2, ',', '.') }}</strong></td>
            <td class="text-right"><strong>R$ {{ number_format($deliveries->sum('net_value'), 2, ',', '.') }}</strong></td>
            <td colspan="2"></td>
        </tr>
    </tfoot>
</table>

{{-- ── Observações ── --}}
@if(! empty($notes))
<div style="background:#f8fafc; border:1px solid #dde3ea; border-radius:6px; padding:12px 16px; margin-bottom:20px;">
    <div style="font-size:8.5pt; text-transform:uppercase; letter-spacing:0.5px; color:#888; font-weight:600; margin-bottom:6px;">Observações</div>
    <p style="font-size:10pt;">{{ $notes }}</p>
</div>
@endif

{{-- ── Declaração / Assinaturas ── --}}
<div style="margin-top: 30px; padding: 14px 16px; background:#f8fafc; border:1px solid #dde3ea; border-radius:6px; font-size:9pt; color:#555;">
    Declaro que as informações acima estão corretas e que os valores correspondem às entregas realizadas conforme registros do sistema.
</div>

<div class="signature-area">
    <div class="signature-block">
        <div class="signature-line"></div>
        <div class="signature-label">Assinatura do Associado</div>
        <div class="signature-name">{{ optional($associate->user)->name ?? $associate->property_name ?? '' }}</div>
    </div>
    <div class="signature-block">
        <div class="signature-line"></div>
        <div class="signature-label">Assinatura do Presidente</div>
        <div class="signature-name">{{ $tenant->legal_representative_name ?? '___________________________' }}</div>
    </div>
</div>

@endsection
