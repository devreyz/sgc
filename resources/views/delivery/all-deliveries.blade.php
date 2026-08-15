@extends('layouts.bento')

@section('title', 'Todas as Entregas')
@section('page-title', 'Gestão de Entregas e Estoque')
@section('user-role', 'Registrador')

{{-- Componente unificado de distribuição --}}
<x-delivery.dist-modal
    :tenant-slug="$currentTenant->slug"
    :csrf="csrf_token()"
    :customers="$customers->map(fn($c)=>['id'=>$c->id,'name'=>$c->trade_name?:$c->name])->values()->all()"
/>
<x-delivery.notes-modal />
@php
    $bentoNavigation = \App\Support\PortalNavigation::make(
        'delivery',
        'deliveries',
        $currentTenant->slug ?? request()->route('tenant'),
    );
@endphp

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
    .action-btns { display:flex; gap:.3rem; }
    .btn-xs { padding:.22rem .5rem; font-size:.7rem; border-radius:var(--radius-md); border:none; cursor:pointer; font-weight:600; display:inline-flex; align-items:center; gap:.2rem; transition:.15s; white-space:nowrap; }
    .btn-xs:disabled { opacity:.45; cursor:not-allowed; }
    .btn-approve { background:rgba(16,185,129,.12); color:#059669; }
    .btn-approve:hover:not(:disabled) { background:var(--color-success); color:#fff; }
    .btn-reject  { background:rgba(239,68,68,.12); color:#dc2626; }
    .btn-reject:hover:not(:disabled)  { background:var(--color-danger); color:#fff; }
    .btn-delete-approved { background:rgba(239,68,68,.08); color:#dc2626; }
    .btn-delete-approved:hover:not(:disabled) { background:var(--color-danger); color:#fff; }

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

    .btn-distribute { background:rgba(99,102,241,.12); color:#4f46e5; }
    .btn-distribute:hover:not(:disabled) { background:#4f46e5; color:#fff; }
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
    <h4><i data-lucide="file-text" style="width:.9rem;height:.9rem;color:var(--color-primary);"></i> Relatórios e planilhas</h4>
    <div class="reports-row">
        <button type="button" class="report-btn primary" onclick="DeliveryReports.open()">
            <i data-lucide="sliders-horizontal"></i> Gerar relatório
        </button>
    </div>
</div>

@include('delivery.partials.report-export-modal', [
    'reportProjects' => $projects,
    'selectedReportProject' => (int) request('project_id'),
])

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
                    <th>Qtd</th>
                    <th>Val. Bruto</th>
                    <th>Status</th>
                    <th>Faturado</th>
                    <th>Qual.</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                @foreach($deliveries as $d)
                <tr id="row-{{ $d->id }}">
                    <td style="white-space:nowrap;">{{ $d->delivery_date?->format('d/m/Y') }}</td>
                    <td style="max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ optional($d->salesProject)->title ?? 'Avulsa' }}">{{ \Illuminate\Support\Str::limit(optional($d->salesProject)->title, 22) ?? '<em>Avulsa</em>' }}</td>
                    <td>{{ optional($d->product)->name ?? '-' }}</td>
                    <td style="white-space:nowrap;">{{ optional(optional($d->associate)->user)->name ?? '-' }}</td>
                    <td style="font-weight:600;white-space:nowrap;">{{ number_format($d->quantity, 3, ',', '.') }} <small style="font-weight:400;font-size:.7em;">{{ optional($d->product)->unit ?? 'un' }}</small></td>
                    <td style="white-space:nowrap;">R$ {{ number_format($d->gross_value, 2, ',', '.') }}</td>
                    <td>
                        <span class="badge-status {{ $d->status->value }}">{{ $d->status->getLabel() }}</span>
                    </td>
                    <td>
                        @php
                            $paidDists   = $d->distributions->filter(fn($dist) => $dist->billing_status === \App\Enums\BillingStatus::PAID)->count();
                            $billedDists = $d->distributions->filter(fn($dist) => $dist->billing_status === \App\Enums\BillingStatus::BILLED)->count();
                            $totalDists  = $d->distributions->count();
                        @endphp
                        @if($totalDists > 0 && $paidDists === $totalDists)
                            <span style="display:inline-flex;align-items:center;padding:.15rem .5rem;border-radius:99px;font-size:.68rem;font-weight:600;background:rgba(16,185,129,.15);color:#059669;">Pago</span>
                        @elseif($paidDists > 0 || $billedDists > 0)
                            <span style="display:inline-flex;align-items:center;padding:.15rem .5rem;border-radius:99px;font-size:.68rem;font-weight:600;background:rgba(99,102,241,.12);color:#4f46e5;">Faturado</span>
                        @else
                            <span style="font-size:.68rem;color:var(--color-text-muted);">—</span>
                        @endif
                    </td>
                    <td>{{ $d->quality_grade ?? '-' }}</td>
                    <td>
                        @if(filled($d->notes))
                        <button type="button" class="delivery-note-trigger"
                            data-delivery-notes="{{ $d->notes }}"
                            data-delivery-notes-title="Observações da entrega"
                            data-delivery-notes-meta="{{ optional($d->product)->name ?? 'Produto' }} · {{ $d->delivery_date?->format('d/m/Y') }}">
                            Observações
                        </button>
                        @endif
                        @if($d->status->value === 'pending')
                        <div class="action-btns">
                            <button class="btn-xs btn-approve" data-id="{{ $d->id }}" title="Aprovar">
                                <i data-lucide="check" style="width:11px;height:11px"></i> Aprovar
                            </button>
                            <button class="btn-xs btn-reject" data-id="{{ $d->id }}" title="Rejeitar">
                                <i data-lucide="x" style="width:11px;height:11px"></i> Rejeitar
                            </button>
                        </div>
                        @elseif($d->status->value === 'approved' && is_null($d->customer_id))
                        @php
                            $hasBilledDists = $d->distributions->contains(fn($dist) =>
                                $dist->billing_status instanceof \App\Enums\BillingStatus
                                && $dist->billing_status !== \App\Enums\BillingStatus::UNBILLED
                            );
                        @endphp
                        <div class="action-btns">
                            <button class="btn-xs btn-distribute"
                                data-id="{{ $d->id }}"
                                data-product="{{ optional($d->product)->name ?? '-' }}"
                                data-unit="{{ optional($d->product)->unit ?? 'un' }}"
                                data-qty="{{ $d->quantity }}"
                                data-distributed="{{ $d->distributions->sum('quantity') }}"
                                data-notes="{{ $d->notes ?? '' }}"
                                data-existing="{{ json_encode($d->distributions->map(fn($dist) => ['id' => $dist->id, 'customer_id' => $dist->customer_id, 'customer' => optional($dist->customer)->name ?? '?', 'qty' => $dist->quantity, 'net' => (float)$dist->net_value, 'billed' => $dist->billing_status instanceof \App\Enums\BillingStatus && $dist->billing_status !== \App\Enums\BillingStatus::UNBILLED])) }}"
                                title="Distribuir para clientes">
                                <i data-lucide="git-branch" style="width:11px;height:11px"></i> Distribuir
                            </button>
                            @if($hasBilledDists)
                            <button class="btn-xs" disabled title="Entrega faturada — exclusão bloqueada" style="opacity:.4;cursor:not-allowed;display:inline-flex;align-items:center;gap:.2rem;padding:.22rem .5rem;font-size:.7rem;background:rgba(239,68,68,.08);color:#dc2626;border-radius:var(--radius-md);border:none;">
                                <i data-lucide="lock" style="width:11px;height:11px"></i> Bloqueado
                            </button>
                            @else
                            <button class="btn-xs btn-delete-approved" data-id="{{ $d->id }}" title="Excluir entrega aprovada" aria-label="Excluir entrega aprovada">
                                <i data-lucide="trash-2" style="width:11px;height:11px"></i> Excluir
                            </button>
                            @endif
                        </div>
                        @else
                        <span style="font-size:.7rem;color:var(--color-text-secondary)">—</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="pagination-wrap">
        {{ $deliveries->links('vendor.pagination.bento') }}
    </div>
    @endif
</div>

@endsection

<!-- Distribution Modal -->
<div class="modal-overlay" id="distModal" style="display:none"><!-- removido: substituído por x-delivery.dist-modal --></div>


@push('scripts')
<script>
const TENANT_SLUG = '{{ $currentTenant->slug }}';
const CSRF_TOKEN  = '{{ csrf_token() }}';
const ALL_CUSTOMERS = @json($customers->map(fn($c) => ['id' => $c->id, 'name' => $c->trade_name ?: $c->name]));

/* ── Distribute button click → component DistModal ── */
document.addEventListener('click', function(e) {
    const distBtn = e.target.closest('.btn-distribute');
    if (distBtn) { DistModal.openFromBtn(distBtn); return; }
});

/* ── Inline approve/reject ── */
document.addEventListener('click', async function(e) {
    const approveBtn = e.target.closest('.btn-approve');
    const rejectBtn  = e.target.closest('.btn-reject');
    if (!approveBtn && !rejectBtn) return;

    const btn    = approveBtn || rejectBtn;
    const id     = btn.dataset.id;
    const action = approveBtn ? 'approve' : 'reject';

    if (!confirm(action === 'approve' ? 'Aprovar esta entrega?' : 'Rejeitar esta entrega?')) return;

    btn.disabled = true;
    const row     = document.getElementById('row-' + id);
    const allBtns = row ? row.querySelectorAll('.btn-xs') : [btn];
    allBtns.forEach(b => b.disabled = true);

    try {
        const res  = await fetch(`/${TENANT_SLUG}/delivery/deliveries/${id}/${action}`, {
            method : 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Content-Type': 'application/json', 'Accept': 'application/json' }
        });
        const data = await res.json();
        if (data.success) {
            if (row) {
                const statusCell = row.querySelector('.badge-status');
                const actionCell = row.querySelector('.action-btns');
                if (statusCell) {
                    statusCell.className  = 'badge-status ' + (action === 'approve' ? 'approved' : 'rejected');
                    statusCell.textContent = action === 'approve' ? 'Aprovada' : 'Rejeitada';
                }
                if (actionCell) {
                    if (action === 'approve') {
                        location.reload();
                    } else {
                        actionCell.innerHTML = '<span style="font-size:.7rem;color:var(--color-text-secondary)">—</span>';
                    }
                }
            }
        } else {
            alert(data.message || 'Erro ao processar.');
            allBtns.forEach(b => b.disabled = false);
        }
    } catch(err) {
        alert('Erro de comunicação com o servidor.');
        allBtns.forEach(b => b.disabled = false);
    }
});

function esc(s) {
    return (s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

/* ── Delete approved delivery ── */
document.addEventListener('click', async function(e) {
    const btn = e.target.closest('.btn-delete-approved');
    if (!btn) return;
    const id = btn.dataset.id;
    if (!confirm('Excluir esta entrega aprovada? Esta ação também removerá as distribuições associadas e não pode ser desfeita.')) return;
    btn.disabled = true;
    const row = document.getElementById('row-' + id);
    try {
        const res  = await fetch(`/${TENANT_SLUG}/delivery/deliveries/${id}`, {
            method : 'DELETE',
            headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' }
        });
        const data = await res.json();
        if (data.success) {
            row?.remove();
        } else {
            alert(data.message || 'Erro ao excluir.');
            btn.disabled = false;
        }
    } catch(err) {
        alert('Erro de comunicação com o servidor.');
        btn.disabled = false;
    }
});
</script>
@endpush

