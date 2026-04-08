<?php
/**
 * Reescreve a view project-producers.blade.php com design consistente usando CSS vars
 */
$file = __DIR__ . '/../resources/views/delivery/project-producers.blade.php';

$content = <<<'BLADE'
@extends('layouts.bento')

@section('title', 'Produtores do Projeto')
@section('page-title', 'Produtores do Projeto')
@section('user-role', 'Registrador')

@section('navigation')
<nav class="nav-tabs">
    <a href="{{ route('delivery.dashboard', ['tenant' => $tenant->slug]) }}" class="nav-tab">
        <i data-lucide="layout-dashboard" style="width:14px;height:14px"></i> Dashboard
    </a>
    <a href="{{ route('delivery.all-deliveries', ['tenant' => $tenant->slug]) }}" class="nav-tab">
        <i data-lucide="list" style="width:14px;height:14px"></i> Entregas
    </a>
    <a href="{{ route('delivery.register', ['tenant' => $tenant->slug]) }}" class="nav-tab">
        <i data-lucide="plus-circle" style="width:14px;height:14px"></i> Registrar
    </a>
    <form action="{{ route('logout') }}" method="POST" style="display:inline">
        @csrf
        <button type="submit" class="nav-tab" style="background:none;cursor:pointer;color:var(--color-danger)">
            <i data-lucide="log-out" style="width:14px;height:14px"></i> Sair
        </button>
    </form>
</nav>
@endsection

@section('content')
<style>
/* ── Page header ── */
.pp-header {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    padding: 1.2rem 1.5rem;
    margin-bottom: 1.25rem;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}
.pp-title { font-size:1.2rem; font-weight:700; margin:0 0 .3rem; display:flex; align-items:center; gap:.45rem; }
.pp-meta  { font-size:.82rem; color:var(--color-text-secondary); }

/* ── Actions ── */
.pp-actions { display:flex; gap:.5rem; flex-wrap:wrap; align-items:flex-start; }
.btn { display:inline-flex; align-items:center; gap:.3rem; padding:.42rem .85rem; border-radius:var(--radius-md); border:none; cursor:pointer; font-size:.78rem; font-weight:600; text-decoration:none; transition:.15s; white-space:nowrap; }
.btn:hover { transform:translateY(-1px); }
.btn-primary { background:var(--color-primary); color:#fff; }
.btn-primary:hover { opacity:.88; }
.btn-ghost   { background:transparent; color:var(--color-text-secondary); border:1px solid var(--color-border); }
.btn-ghost:hover { background:var(--color-bg); color:var(--color-text); }
.btn-sm { padding:.3rem .65rem; font-size:.73rem; }

/* ── Table card ── */
.pp-card { background:var(--color-surface); border:1px solid var(--color-border); border-radius:var(--radius-lg); overflow:hidden; }
.pp-table { width:100%; border-collapse:collapse; font-size:.88rem; }
.pp-table th {
    background:var(--color-primary);
    color:#fff;
    padding:.65rem .85rem;
    text-align:left;
    font-size:.73rem;
    text-transform:uppercase;
    letter-spacing:.04em;
    font-weight:600;
    white-space:nowrap;
}
.pp-table th.r { text-align:right; }
.pp-table td {
    padding:.62rem .85rem;
    border-bottom:1px solid var(--color-border);
    color:var(--color-text);
}
.pp-table td.r { text-align:right; }
.pp-table tr:nth-child(even) td { background:rgba(0,0,0,.015); }
.pp-table tr:hover td { background:rgba(0,0,0,.03); }
.pp-table tfoot td {
    padding:.65rem .85rem;
    font-weight:700;
    background:var(--color-bg);
    border-top:2px solid var(--color-primary);
    color:var(--color-primary);
}
.pp-table tfoot td.r { text-align:right; }

/* Receipt button */
.receipt-btn {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    background: var(--color-success);
    color: #fff;
    padding: .28rem .62rem;
    font-size: .73rem;
    font-weight: 600;
    border-radius: var(--radius-md);
    text-decoration: none;
    transition: .15s;
    white-space: nowrap;
}
.receipt-btn:hover { opacity:.88; transform:translateY(-1px); }

/* Empty */
.pp-empty { padding:2.5rem; text-align:center; color:var(--color-text-secondary); }

/* Print */
@media print {
    .nav-tabs, nav, .no-print { display:none !important; }
    body { background:#fff !important; }
    .pp-header { border:none; padding:.4rem 0; background:none; }
    .pp-table th { background:#1a3a5c !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    .pp-card { border:1px solid #ccc; }
}
</style>

{{-- ── HEADER ── --}}
<div class="pp-header">
    <div>
        <h1 class="pp-title">
            <i data-lucide="users" style="width:20px;height:20px;color:var(--color-primary)"></i>
            {{ $project->title }}
        </h1>
        <div class="pp-meta">
            @if($project->contract_number)
                Contrato: {{ $project->contract_number }} &nbsp;·&nbsp;
            @endif
            {{ $producers->count() }} produtor(es) &nbsp;·&nbsp;
            Gerado em {{ now()->format('d/m/Y H:i') }}
        </div>
    </div>
    <div class="pp-actions no-print">
        <a href="{{ route('delivery.projects.deliveries', ['tenant' => $tenant->slug, 'project' => $project->id]) }}" class="btn btn-ghost btn-sm">
            <i data-lucide="arrow-left" style="width:13px;height:13px"></i> Entregas
        </a>
        <button class="btn btn-primary btn-sm" onclick="window.print()">
            <i data-lucide="printer" style="width:13px;height:13px"></i> Imprimir
        </button>
    </div>
</div>

{{-- ── PRODUCERS TABLE ── --}}
<div class="pp-card">
    @if($producers->isEmpty())
        <div class="pp-empty">
            <i data-lucide="inbox" style="width:40px;height:40px;opacity:.35;margin-bottom:.75rem;"></i>
            <p>Nenhum produtor com entregas aprovadas neste projeto.</p>
        </div>
    @else
        <div style="overflow-x:auto;">
            <table class="pp-table">
                <thead>
                    <tr>
                        <th style="width:3%">#</th>
                        <th>Produtor</th>
                        <th style="width:16%">CPF/CNPJ</th>
                        <th style="width:12%">Matrícula</th>
                        <th class="r" style="width:9%">Entregas</th>
                        <th class="r" style="width:13%">Qtd. Total</th>
                        <th class="r" style="width:13%">Val. Bruto</th>
                        <th class="r" style="width:13%">Val. Líquido</th>
                        <th class="no-print" style="width:12%;text-align:center;">Comprovante</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($producers as $i => $p)
                    <tr>
                        <td style="color:var(--color-text-secondary);font-size:.8rem;">{{ $i + 1 }}</td>
                        <td><strong>{{ $p['name'] }}</strong></td>
                        <td style="font-family:monospace;">{{ $p['cpf'] }}</td>
                        <td>{{ $p['registration'] }}</td>
                        <td class="r">{{ $p['deliveries'] }}</td>
                        <td class="r">{{ number_format($p['quantity'], 3, ',', '.') }}</td>
                        <td class="r">R$ {{ number_format($p['gross_value'], 2, ',', '.') }}</td>
                        <td class="r" style="color:var(--color-success);font-weight:600;">
                            R$ {{ number_format($p['net_value'], 2, ',', '.') }}
                        </td>
                        <td class="no-print" style="text-align:center;">
                            <a href="{{ route('delivery.projects.associate-receipt', ['tenant' => $tenant->slug, 'project' => $project->id, 'associate' => $p['associate']->id]) }}"
                               class="receipt-btn"
                               title="Gerar Comprovante PDF"
                               target="_blank">
                                <i data-lucide="file-down" style="width:12px;height:12px"></i>
                                PDF
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4"><strong>TOTAL</strong></td>
                        <td class="r">{{ $producers->sum('deliveries') }}</td>
                        <td class="r">{{ number_format($producers->sum('quantity'), 3, ',', '.') }}</td>
                        <td class="r">R$ {{ number_format($producers->sum('gross_value'), 2, ',', '.') }}</td>
                        <td class="r">R$ {{ number_format($producers->sum('net_value'), 2, ',', '.') }}</td>
                        <td class="no-print"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>
@endsection
BLADE;

file_put_contents($file, $content);
echo "project-producers.blade.php written (" . strlen($content) . " bytes)\n";
