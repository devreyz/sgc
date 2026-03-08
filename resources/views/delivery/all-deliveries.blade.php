@extends('layouts.bento')

@section('title', 'Todas as Entregas')
@section('page-title', 'Gestão de Entregas e Estoque')
@section('user-role', 'Registrador')

@section('navigation')
<nav class="nav-tabs">
    <a href="{{ route('delivery.dashboard', ['tenant' => $currentTenant->slug]) }}" class="nav-tab">Dashboard</a>
    <a href="{{ route('delivery.all-deliveries', ['tenant' => $currentTenant->slug]) }}" class="nav-tab active">Entregas</a>
    <a href="{{ route('delivery.register', ['tenant' => $currentTenant->slug]) }}" class="nav-tab">Registrar Entrega</a>
    <form action="{{ route('logout') }}" method="POST" style="display: inline;">
        @csrf
        <button type="submit" class="nav-tab" style="background: none; cursor: pointer;">Sair</button>
    </form>
</nav>
@endsection

@section('content')
<style>
    .stats-row { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:.75rem; margin-bottom:1.5rem; }
    .mini-stat { background:var(--color-surface); border-radius:var(--radius-md); padding:1rem; border:1px solid var(--color-border); text-align:center; }
    .mini-stat .label { font-size:.7rem; text-transform:uppercase; letter-spacing:.04em; color:var(--color-text-secondary); margin-bottom:.25rem; }
    .mini-stat .value { font-size:1.6rem; font-weight:700; }
    .filters-bar { background:var(--color-surface); border-radius:var(--radius-lg); padding:1rem 1.25rem; border:1px solid var(--color-border); margin-bottom:1.25rem; }
    .filters-form { display:flex; flex-wrap:wrap; gap:.75rem; align-items:flex-end; }
    .filter-group { display:flex; flex-direction:column; gap:.25rem; }
    .filter-group label { font-size:.7rem; font-weight:600; text-transform:uppercase; color:var(--color-text-secondary); }
    .filter-group input, .filter-group select { font-size:.85rem; padding:.4rem .6rem; border:1px solid var(--color-border); border-radius:var(--radius-md); background:var(--color-bg); color:var(--color-text); }
    .section-card { background:var(--color-surface); border-radius:var(--radius-lg); border:1px solid var(--color-border); overflow:hidden; margin-bottom:1.5rem; }
    .section-card-header { padding:1rem 1.25rem; border-bottom:1px solid var(--color-border); display:flex; justify-content:space-between; align-items:center; }
    .section-card-header h3 { font-size:1rem; font-weight:700; display:flex; align-items:center; gap:.4rem; }
    .table-scroll { overflow-x:auto; }
    .data-table { width:100%; border-collapse:collapse; font-size:.85rem; }
    .data-table th { background:var(--color-bg); padding:.65rem .75rem; text-align:left; font-size:.7rem; text-transform:uppercase; letter-spacing:.05em; color:var(--color-text-secondary); font-weight:600; border-bottom:2px solid var(--color-border); white-space:nowrap; }
    .data-table td { padding:.6rem .75rem; border-bottom:1px solid var(--color-border); }
    .data-table tr:hover td { background:rgba(0,0,0,.02); }
    .badge-status { display:inline-flex; align-items:center; gap:.2rem; padding:.15rem .5rem; border-radius:99px; font-size:.7rem; font-weight:600; text-transform:uppercase; }
    .badge-status.pending { background:rgba(245,158,11,.15); color:#d97706; }
    .badge-status.approved { background:rgba(16,185,129,.15); color:#059669; }
    .badge-status.rejected { background:rgba(239,68,68,.15); color:#dc2626; }
    .badge-status.cancelled { background:rgba(107,114,128,.15); color:#6b7280; }
    .stock-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:.75rem; padding:1rem 1.25rem; }
    .stock-item { display:flex; justify-content:space-between; align-items:center; padding:.75rem; background:var(--color-bg); border-radius:var(--radius-md); border:1px solid var(--color-border); }
    .stock-item .product-name { font-weight:600; font-size:.85rem; }
    .stock-item .product-qty { font-size:1.1rem; font-weight:700; color:var(--color-primary); }
    .stock-item .product-count { font-size:.7rem; color:var(--color-text-secondary); }
    .pagination-wrap { padding:1rem 1.25rem; display:flex; justify-content:center; }
    .btn { display:inline-flex; align-items:center; gap:.3rem; padding:.4rem .8rem; border-radius:var(--radius-md); border:none; cursor:pointer; font-size:.8rem; font-weight:600; text-decoration:none; transition:.15s; }
    .btn-primary { background:var(--color-primary); color:#fff; }
    .btn-primary:hover { opacity:.9; }
    .btn-outline { background:transparent; color:var(--color-text-secondary); border:1px solid var(--color-border); }
    .btn-outline:hover { background:var(--color-bg); }
    .btn-sm { padding:.3rem .6rem; font-size:.75rem; }
    .empty-msg { padding:2rem; text-align:center; color:var(--color-text-secondary); }

    /* Reports Section */
    .reports-bar { background:var(--color-surface); border-radius:var(--radius-lg); padding:1rem 1.25rem; border:1px solid var(--color-border); margin-bottom:1.25rem; }
    .reports-bar h4 { font-size:.8rem; font-weight:700; margin-bottom:.6rem; display:flex; align-items:center; gap:.4rem; color:var(--color-text); }
    .reports-row { display:flex; flex-wrap:wrap; gap:.5rem; align-items:flex-end; }
    .report-btn { display:inline-flex; align-items:center; gap:.35rem; padding:.45rem .85rem; border-radius:var(--radius-md); border:1px solid var(--color-border); cursor:pointer; font-size:.78rem; font-weight:600; text-decoration:none; transition:.15s; background:var(--color-bg); color:var(--color-text); }
    .report-btn:hover { background:var(--color-primary); color:#fff; border-color:var(--color-primary); }
    .report-btn.primary { background:var(--color-primary); color:#fff; border-color:var(--color-primary); }
    .report-btn.primary:hover { opacity:.9; }
    .report-btn i { width:.85rem; height:.85rem; }
    .report-separator { width:1px; height:28px; background:var(--color-border); margin:0 .25rem; }

    /* Receipt Modal */
    .modal-overlay { display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,.5); z-index:9000; justify-content:center; align-items:center; backdrop-filter:blur(3px); }
    .modal-overlay.active { display:flex; }
    .receipt-modal { background:var(--color-surface); border-radius:var(--radius-lg); padding:1.5rem; width:90%; max-width:440px; box-shadow:0 20px 60px rgba(0,0,0,.25); }
    .receipt-modal h3 { font-size:1rem; font-weight:700; margin-bottom:1rem; display:flex; align-items:center; gap:.4rem; }
    .receipt-modal .form-group { margin-bottom:.75rem; }
    .receipt-modal .form-group label { display:block; font-size:.75rem; font-weight:600; text-transform:uppercase; color:var(--color-text-secondary); margin-bottom:.25rem; }
    .receipt-modal .form-group select { width:100%; font-size:.85rem; padding:.45rem .6rem; border:1px solid var(--color-border); border-radius:var(--radius-md); background:var(--color-bg); color:var(--color-text); }
    .receipt-modal-actions { display:flex; justify-content:flex-end; gap:.5rem; margin-top:1rem; }

    @media(max-width:640px) {
        .filters-form { flex-direction:column; }
        .filter-group { width:100%; }
        .filter-group input, .filter-group select { width:100%; }
        .reports-row { flex-direction:column; }
        .report-separator { display:none; }
    }
</style>

<!-- Stats -->
<div class="stats-row">
    <div class="mini-stat">
        <div class="label">Total</div>
        <div class="value">{{ $stats['total'] }}</div>
    </div>
    <div class="mini-stat">
        <div class="label">Pendentes</div>
        <div class="value" style="color:var(--color-warning);">{{ $stats['pending'] }}</div>
    </div>
    <div class="mini-stat">
        <div class="label">Aprovadas</div>
        <div class="value" style="color:var(--color-success);">{{ $stats['approved'] }}</div>
    </div>
    <div class="mini-stat">
        <div class="label">Rejeitadas</div>
        <div class="value" style="color:var(--color-danger);">{{ $stats['rejected'] }}</div>
    </div>
</div>

<!-- Stock Summary -->
@if($stockSummary->isNotEmpty())
<div class="section-card">
    <div class="section-card-header">
        <h3><i data-lucide="warehouse" style="width:1rem;height:1rem;color:var(--color-primary);"></i> Resumo de Estoque (Aprovadas)</h3>
    </div>
    <div class="stock-grid">
        @foreach($stockSummary as $item)
        <div class="stock-item">
            <div>
                <div class="product-name">{{ $item['product_name'] }}</div>
                <div class="product-count">{{ $item['total_deliveries'] }} entregas</div>
            </div>
            <div class="product-qty">{{ number_format($item['total_quantity'], 1, ',', '.') }} <small style="font-size:.6em;font-weight:400;">kg</small></div>
        </div>
        @endforeach
    </div>
</div>
@endif

<!-- Filters -->
<div class="filters-bar">
    <form method="GET" action="{{ route('delivery.all-deliveries', ['tenant' => $currentTenant->slug]) }}" class="filters-form">
        <div class="filter-group">
            <label>Buscar</label>
            <input type="text" name="search" value="{{ $search }}" placeholder="Produto ou associado...">
        </div>
        <div class="filter-group">
            <label>Status</label>
            <select name="status">
                <option value="">Todos</option>
                <option value="pending" {{ $statusFilter === 'pending' ? 'selected' : '' }}>Pendente</option>
                <option value="approved" {{ $statusFilter === 'approved' ? 'selected' : '' }}>Aprovada</option>
                <option value="rejected" {{ $statusFilter === 'rejected' ? 'selected' : '' }}>Rejeitada</option>
                <option value="cancelled" {{ $statusFilter === 'cancelled' ? 'selected' : '' }}>Cancelada</option>
            </select>
        </div>
        <div class="filter-group">
            <label>Projeto</label>
            <select name="project_id">
                <option value="">Todos</option>
                @foreach($projects as $id => $title)
                    <option value="{{ $id }}" {{ $projectFilter == $id ? 'selected' : '' }}>{{ \Illuminate\Support\Str::limit($title, 30) }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-group">
            <label>Data de</label>
            <input type="date" name="date_from" value="{{ $dateFrom }}">
        </div>
        <div class="filter-group">
            <label>Data até</label>
            <input type="date" name="date_to" value="{{ $dateTo }}">
        </div>
        <div class="filter-group" style="justify-content:flex-end;">
            <button type="submit" class="btn btn-primary btn-sm">
                <i data-lucide="search" style="width:.8rem;height:.8rem;"></i> Filtrar
            </button>
            <a href="{{ route('delivery.all-deliveries', ['tenant' => $currentTenant->slug]) }}" class="btn btn-outline btn-sm" style="margin-top:.25rem;">Limpar</a>
        </div>
    </form>
</div>

<!-- Reports -->
<div class="reports-bar">
    <h4><i data-lucide="file-text" style="width:.9rem;height:.9rem;color:var(--color-primary);"></i> Relatórios PDF</h4>
    <div class="reports-row">
        {{-- Grouped reports inherit current filters --}}
        <a href="{{ route('delivery.reports.by-associate', array_merge(['tenant' => $currentTenant->slug], request()->only('status', 'project_id', 'date_from', 'date_to', 'search'))) }}" class="report-btn" target="_blank">
            <i data-lucide="users"></i> Agrupado por Associado
        </a>
        <a href="{{ route('delivery.reports.by-product', array_merge(['tenant' => $currentTenant->slug], request()->only('status', 'project_id', 'date_from', 'date_to', 'search'))) }}" class="report-btn" target="_blank">
            <i data-lucide="box"></i> Agrupado por Produto
        </a>
        <div class="report-separator"></div>
        <button type="button" class="report-btn primary" onclick="openReceiptModal()">
            <i data-lucide="file-signature"></i> Comprovante por Associado (Projeto)
        </button>
    </div>
</div>

<!-- Receipt Modal -->
<div class="modal-overlay" id="receiptModal">
    <div class="receipt-modal">
        <h3><i data-lucide="file-signature" style="width:1rem;height:1rem;color:var(--color-primary);"></i> Gerar Comprovante de Entrega</h3>
        <p style="font-size:.8rem;color:var(--color-text-secondary);margin-bottom:1rem;">Selecione o projeto e o associado para gerar o comprovante com campo de assinatura.</p>
        <form method="GET" action="{{ route('delivery.reports.project-associate', ['tenant' => $currentTenant->slug]) }}" target="_blank">
            <div class="form-group">
                <label>Projeto</label>
                <select name="project_id" required>
                    <option value="">Selecione o projeto...</option>
                    @foreach($projects as $id => $title)
                        <option value="{{ $id }}" {{ $projectFilter == $id ? 'selected' : '' }}>{{ $title }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Associado</label>
                <select name="associate_id" required>
                    <option value="">Selecione o associado...</option>
                    @foreach($associates as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="receipt-modal-actions">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeReceiptModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i data-lucide="download" style="width:.8rem;height:.8rem;"></i> Gerar PDF
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Deliveries Table -->
<div class="section-card">
    <div class="section-card-header">
        <h3><i data-lucide="package" style="width:1rem;height:1rem;color:var(--color-primary);"></i> Entregas ({{ $deliveries->total() }})</h3>
    </div>
    @if($deliveries->isEmpty())
        <div class="empty-msg">Nenhuma entrega encontrada com os filtros aplicados.</div>
    @else
    <div class="table-scroll">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Projeto</th>
                    <th>Produto</th>
                    <th>Associado</th>
                    <th>Qtd (kg)</th>
                    <th>Valor Unit.</th>
                    <th>Valor Bruto</th>
                    <th>Status</th>
                    <th>Qualidade</th>
                </tr>
            </thead>
            <tbody>
                @foreach($deliveries as $d)
                <tr>
                    <td style="white-space:nowrap;">{{ $d->delivery_date?->format('d/m/Y') }}</td>
                    <td>{{ \Illuminate\Support\Str::limit(optional($d->salesProject)->title, 25) ?? 'Avulsa' }}</td>
                    <td>{{ optional($d->product)->name ?? '-' }}</td>
                    <td>{{ optional(optional($d->associate)->user)->name ?? '-' }}</td>
                    <td style="font-weight:600;">{{ number_format($d->quantity, 1, ',', '.') }}</td>
                    <td>R$ {{ number_format($d->unit_price, 2, ',', '.') }}</td>
                    <td>R$ {{ number_format($d->gross_value, 2, ',', '.') }}</td>
                    <td>
                        <span class="badge-status {{ $d->status->value }}">{{ $d->status->getLabel() }}</span>
                    </td>
                    <td>{{ $d->quality_grade ?? '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="pagination-wrap">
        {{ $deliveries->links() }}
    </div>
    @endif
</div>

@endsection

@push('scripts')
<script>
    function openReceiptModal() {
        document.getElementById('receiptModal').classList.add('active');
    }
    function closeReceiptModal() {
        document.getElementById('receiptModal').classList.remove('active');
    }
    document.getElementById('receiptModal').addEventListener('click', function(e) {
        if (e.target === this) closeReceiptModal();
    });
</script>
@endpush
