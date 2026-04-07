@extends('layouts.bento')

@section('title', 'Produtores do Projeto')
@section('page-title', 'Produtores do Projeto')
@section('user-role', 'Registrador')

@section('navigation')
<nav class="nav-tabs">
    <a href="{{ route('delivery.dashboard', ['tenant' => $tenant->slug]) }}" class="nav-tab">Dashboard</a>
    <a href="{{ route('delivery.all-deliveries', ['tenant' => $tenant->slug]) }}" class="nav-tab">Entregas</a>
    <form action="{{ route('logout') }}" method="POST" style="display: inline;">
        @csrf
        <button type="submit" class="nav-tab" style="background: none; cursor: pointer;">Sair</button>
    </form>
</nav>
@endsection

@section('content')
<style>
.page-header {
    background: #fff;
    border: 1px solid #e2e8f0;
    padding: 1.25rem 1.5rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}
.page-header h2 { font-size:1.2rem; font-weight:700; color:#1a3a5c; margin:0; }
.page-header .meta { font-size:.85rem; color:#64748b; margin-top:.2rem; }
.print-btn {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    background: #1a3a5c;
    color: #fff;
    padding: .5rem 1.1rem;
    border: none;
    cursor: pointer;
    font-size: .9rem;
    font-weight: 600;
    text-decoration: none;
}
.print-btn:hover { background: #163158; }
.producers-table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
    font-size: .9rem;
}
.producers-table thead th {
    background: #1a3a5c;
    color: #fff;
    padding: .65rem .85rem;
    text-align: left;
    font-size: .8rem;
    text-transform: uppercase;
    letter-spacing: .3px;
}
.producers-table thead th.r { text-align: right; }
.producers-table tbody td {
    padding: .6rem .85rem;
    border-bottom: 1px solid #e8ecf0;
    color: #374151;
}
.producers-table tbody td.r { text-align: right; }
.producers-table tbody tr:nth-child(even) { background: #f7f9fb; }
.producers-table tfoot td {
    padding: .65rem .85rem;
    font-weight: 700;
    background: #eef1f5;
    border-top: 2px solid #1a3a5c;
    color: #1a3a5c;
}
.producers-table tfoot td.r { text-align: right; }
.empty-state { padding: 2rem; text-align: center; color: #94a3b8; font-size: .95rem; background: #fff; border: 1px solid #e2e8f0; }

@media print {
    .nav-tabs, .print-btn, nav { display: none !important; }
    body { background: #fff !important; }
    .page-header { border: none; padding: .5rem 0; }
}
</style>

<div class="page-header">
    <div>
        <h2>{{ $project->title }}</h2>
        <div class="meta">
            @if($project->contract_number)Contrato: {{ $project->contract_number }} &nbsp;|&nbsp; @endif
            {{ $producers->count() }} produtor(es) &nbsp;|&nbsp; Gerado em {{ now()->format('d/m/Y H:i') }}
        </div>
    </div>
    <button class="print-btn" onclick="window.print()">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.098m.72-.098a42.415 42.415 0 0110.56 0m-10.56 0L6.75 19.5m10.56-5.671L17.25 19.5M4.5 7.5a3 3 0 013-3h9a3 3 0 013 3v6.375c0 .621-.504 1.125-1.125 1.125H18a2.25 2.25 0 01-2.25-2.25v-1.5A.75.75 0 0015 10.5H9a.75.75 0 00-.75.75v1.5A2.25 2.25 0 016 15H5.625A1.125 1.125 0 014.5 13.875V7.5z"/></svg>
        Imprimir
    </button>
</div>

@if($producers->isEmpty())
<div class="empty-state">Nenhum produtor com entregas aprovadas neste projeto.</div>
@else
<table class="producers-table">
    <thead>
        <tr>
            <th style="width:3%;">#</th>
            <th>Produtor</th>
            <th style="width:16%;">CPF</th>
            <th style="width:12%;">Matrícula</th>
            <th class="r" style="width:10%;">Entregas</th>
            <th class="r" style="width:14%;">Qtd. Total</th>
            <th class="r" style="width:14%;">Val. Bruto</th>
            <th class="r" style="width:14%;">Val. Líquido</th>
        </tr>
    </thead>
    <tbody>
        @foreach($producers as $i => $p)
        <tr>
            <td style="color:#94a3b8;">{{ $i + 1 }}</td>
            <td><strong>{{ $p['name'] }}</strong></td>
            <td>{{ $p['cpf'] }}</td>
            <td>{{ $p['registration'] }}</td>
            <td class="r">{{ $p['deliveries'] }}</td>
            <td class="r">{{ number_format($p['quantity'], 3, ',', '.') }}</td>
            <td class="r">R$ {{ number_format($p['gross_value'], 2, ',', '.') }}</td>
            <td class="r" style="color:#1a5c3a;font-weight:600;">R$ {{ number_format($p['net_value'], 2, ',', '.') }}</td>
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
        </tr>
    </tfoot>
</table>
@endif
@endsection
