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
    <h4><i data-lucide="file-text" style="width:.9rem;height:.9rem;color:var(--color-primary);"></i> Relatórios PDF</h4>
    <div class="reports-row">
        {{-- Grouped reports inherit current filters --}}
        <a href="{{ route('delivery.reports.by-associate', array_merge(['tenant' => $currentTenant->slug], request()->only('status', 'project_id', 'date_from', 'date_to', 'search'))) }}" class="report-btn" target="_blank">
            <i data-lucide="users"></i> Agrupado por Associado
        </a>
        <a href="{{ route('delivery.reports.by-product', array_merge(['tenant' => $currentTenant->slug], request()->only('status', 'project_id', 'date_from', 'date_to', 'search'))) }}" class="report-btn" target="_blank">
            <i data-lucide="box"></i> Agrupado por Produto
        </a>
    </div>
    {{-- Relatórios de distribuição com filtro de organização --}}
    <div style="margin-top:.75rem;">
        <form method="GET" id="dist-report-form" style="display:flex;flex-wrap:wrap;gap:.6rem;align-items:flex-end;">
            <input type="hidden" name="tenant" value="{{ $currentTenant->slug }}">
            @foreach(request()->only('project_id','date_from','date_to') as $k => $v)
            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
            @endforeach
            <div class="filter-group">
                <label style="font-size:.68rem;">Organização (distribuições)</label>
                <select name="organization_id" style="min-width:180px;font-size:.82rem;padding:.35rem .55rem;border:1px solid var(--color-border);border-radius:var(--radius-md);background:var(--color-bg);color:var(--color-text);">
                    <option value="">Todas as organizações</option>
                    @foreach($organizations as $orgId => $orgName)
                    <option value="{{ $orgId }}" {{ request('organization_id') == $orgId ? 'selected' : '' }}>{{ $orgName }}</option>
                    @endforeach
                </select>
            </div>
            <a id="dist-full-btn" href="{{ route('delivery.reports.distributions-by-customer', array_merge(['tenant' => $currentTenant->slug], request()->only('project_id', 'date_from', 'date_to', 'organization_id'))) }}" class="report-btn" target="_blank">
                <i data-lucide="building-2"></i> Distribuições por Org/Cliente
            </a>
            <a id="dist-compact-btn" href="{{ route('delivery.reports.distributions-by-customer-compact', array_merge(['tenant' => $currentTenant->slug], request()->only('project_id', 'date_from', 'date_to', 'organization_id'))) }}" class="report-btn" target="_blank" style="border-color:#059669;color:#059669;">
                <i data-lucide="file-check"></i> Resumo p/ Cobrança
            </a>
        </form>
    </div>
    <script>
    (function(){
        const sel = document.querySelector('[name="organization_id"]');
        if (!sel) return;
        function updateLinks() {
            const oid = sel.value;
            function applyOrg(id) {
                const btn = document.getElementById(id);
                if (!btn) return;
                const url = new URL(btn.href, location.href);
                if (oid) url.searchParams.set('organization_id', oid);
                else url.searchParams.delete('organization_id');
                btn.href = url.toString();
            }
            applyOrg('dist-full-btn');
            applyOrg('dist-compact-btn');
        }
        sel.addEventListener('change', updateLinks);
        updateLinks();
    })();
    </script>
    <div style="margin-top:.75rem;" class="reports-row">
        <div class="report-separator"></div>
        <button type="button" class="report-btn primary" onclick="openReceiptModal()">
            <i data-lucide="file-signature"></i> Comprovante por Associado (Projeto)
        </button>
        <button type="button" class="report-btn" onclick="openCustomerReportModal()" style="border-color:#1d4ed8;color:#1d4ed8;background:#eff6ff;">
            <i data-lucide="file-badge"></i> Relatório Individual por Cliente
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

{{-- ── MODAL: RELATÓRIO POR CLIENTE ── --}}
<div class="modal-overlay hidden" id="customerReportModal">
    <div style="background:var(--color-surface);border-radius:var(--radius-lg);padding:1.5rem;width:min(560px,96vw);max-height:90vh;overflow-y:auto;box-shadow:0 8px 32px rgba(0,0,0,.22);">
        <div style="font-size:1rem;font-weight:700;margin-bottom:1rem;display:flex;align-items:center;gap:.4rem;">
            <i data-lucide="file-badge" style="width:18px;height:18px;color:#1d4ed8"></i>
            Relatório por Cliente
        </div>

        <div id="crm-step-loading" style="text-align:center;padding:2rem 0;color:var(--color-text-secondary);">
            <svg style="width:32px;height:32px;animation:crm-spin 1s linear infinite;" fill="none" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" opacity=".25"/>
                <path d="M22 12a10 10 0 00-10-10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
            </svg>
            <p style="margin-top:.5rem;font-size:.82rem;">Carregando opções...</p>
        </div>
        <style>@keyframes crm-spin{to{transform:rotate(360deg)}}</style>

        <div id="crm-step-form" style="display:none;">
            {{-- Tipo --}}
            <div style="margin-bottom:.85rem;">
                <label class="form-label">Tipo de Relatório</label>
                <div style="display:flex;gap:.35rem;flex-wrap:wrap;" id="crm-type-btns">
                    <button type="button" class="crm-type-btn active" data-type="statement">Extrato Simples</button>
                    <button type="button" class="crm-type-btn" data-type="full">Com Associados</button>
                    <button type="button" class="crm-type-btn" data-type="compact">Resumo Compacto</button>
                </div>
                <div id="crm-type-desc" style="font-size:.72rem;color:var(--color-text-secondary);margin-top:.3rem;min-height:1rem;"></div>
            </div>

            {{-- Cliente --}}
            <div style="margin-bottom:.85rem;">
                <label class="form-label">Cliente *</label>
                <select id="crm-customer" class="form-control" onchange="crmOnCustomerChange()">
                    <option value="">— Selecione um cliente —</option>
                </select>
            </div>

            {{-- Projeto --}}
            <div style="margin-bottom:.85rem;">
                <label class="form-label">Projeto (opcional)</label>
                <select id="crm-project" class="form-control" onchange="crmReloadWithProject()">
                    <option value="">Todos os projetos</option>
                    @foreach($projects as $pid => $ptitle)
                    <option value="{{ $pid }}" {{ request('project_id') == $pid ? 'selected' : '' }}>{{ $ptitle }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Período --}}
            <div style="margin-bottom:.85rem;">
                <label class="form-label">Período de Entregas</label>
                <div id="crm-date-chips" style="display:flex;flex-wrap:wrap;gap:.3rem;margin-bottom:.4rem;min-height:.5rem;"></div>
                <details style="margin-bottom:.4rem;">
                    <summary style="font-size:.7rem;color:var(--color-text-secondary);cursor:pointer;list-style:none;padding:.1rem 0;">▸ Ver por mês</summary>
                    <div id="crm-month-chips" style="display:flex;flex-wrap:wrap;gap:.3rem;margin-top:.35rem;padding-left:.25rem;"></div>
                </details>
                <div style="font-size:.7rem;color:var(--color-text-secondary);margin-bottom:.4rem;">Ou defina manualmente:</div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                    <div>
                        <label class="form-label" style="font-size:.7rem;">De</label>
                        <input type="date" id="crm-date-from" class="form-control" oninput="crmClearActiveChip()">
                    </div>
                    <div>
                        <label class="form-label" style="font-size:.7rem;">Até</label>
                        <input type="date" id="crm-date-to" class="form-control" oninput="crmClearActiveChip()">
                    </div>
                </div>
                <div style="margin-top:.4rem;display:flex;gap:.4rem;">
                    <button type="button" class="report-btn" style="font-size:.7rem;padding:.25rem .55rem;" onclick="crmSetAllDates()">Todo o período</button>
                    <button type="button" class="report-btn" style="font-size:.7rem;padding:.25rem .55rem;" onclick="crmClearDates()">Limpar</button>
                </div>
            </div>

            <div id="crm-availability" style="display:none;margin-bottom:.75rem;padding:.4rem .7rem;border-radius:0 4px 4px 0;font-size:.77rem;"></div>

            {{-- Colunas (só para Extrato Simples) --}}
            <div id="crm-col-section" style="margin-bottom:.85rem;">
                <label class="form-label">Colunas exibidas</label>
                <div style="display:flex;gap:1.2rem;flex-wrap:wrap;margin-top:.25rem;">
                    <label style="display:flex;align-items:center;gap:.35rem;font-size:.82rem;cursor:pointer;">
                        <input type="checkbox" id="crm-col-unit-price" checked style="width:14px;height:14px;"> Preço Unitário
                    </label>
                    <label style="display:flex;align-items:center;gap:.35rem;font-size:.82rem;cursor:pointer;">
                        <input type="checkbox" id="crm-col-total" checked style="width:14px;height:14px;"> Preço Total
                    </label>
                </div>
            </div>

            <div style="display:flex;gap:.5rem;justify-content:flex-end;margin-top:1.2rem;border-top:1px solid var(--color-border);padding-top:1rem;">
                <button type="button" class="btn btn-ghost btn-sm" onclick="closeCustomerReportModal()">Cancelar</button>
                <button type="button" class="btn btn-sm" id="crm-btn-generate" style="background:#1d4ed8;color:#fff;" onclick="crmGenerate()" disabled>
                    <i data-lucide="download" style="width:13px;height:13px"></i> Gerar PDF
                </button>
            </div>
        </div>

        <div id="crm-step-error" style="display:none;padding:1.5rem 0;text-align:center;color:var(--color-danger);">
            <p style="font-size:.85rem;">Erro ao carregar opções. Tente novamente.</p>
            <button type="button" class="btn btn-ghost btn-sm" style="margin-top:.75rem;" onclick="crmLoadOptions()">Tentar novamente</button>
        </div>
    </div>
</div>

<script>
/* Customer Report Modal (all-deliveries) */
let _crmOptions = null, _crmActiveChip = null, _crmType = 'statement';
const _CRM_TENANT = '{{ $currentTenant->slug }}';
const _CRM_CSRF   = '{{ csrf_token() }}';

const _crmTypeDesc = {
    statement: 'Data · produto · valor por item — para cobrar o cliente.',
    full:      'Inclui associado (origem) por linha — para conferência interna.',
    compact:   'Só totais por produto — visão rápida de cobrança.',
};
const _crmEndpoints = {
    statement: 'customer-delivery-statement',
    full:      'distributions-by-customer',
    compact:   'distributions-by-customer-compact',
};

document.getElementById('crm-type-btns').addEventListener('click', function(e) {
    const btn = e.target.closest('.crm-type-btn'); if (!btn) return;
    _crmType = btn.dataset.type;
    document.querySelectorAll('.crm-type-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('crm-type-desc').textContent = _crmTypeDesc[_crmType] || '';
    document.getElementById('crm-col-section').style.display = _crmType === 'statement' ? 'block' : 'none';
});
document.getElementById('crm-type-desc').textContent = _crmTypeDesc['statement'];

function openCustomerReportModal() {
    document.getElementById('customerReportModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    crmLoadOptions();
}
function closeCustomerReportModal() {
    document.getElementById('customerReportModal').classList.add('hidden');
    document.body.style.overflow = '';
}
document.getElementById('customerReportModal').addEventListener('click', function(e) { if (e.target === this) closeCustomerReportModal(); });

async function crmLoadOptions() {
    document.getElementById('crm-step-loading').style.display = '';
    document.getElementById('crm-step-form').style.display = 'none';
    document.getElementById('crm-step-error').style.display = 'none';
    try {
        const pid = document.getElementById('crm-project')?.value || '';
        const url = `/${_CRM_TENANT}/delivery/reports/customer-delivery-options${pid ? '?project_id=' + pid : ''}`;
        const res = await fetch(url, { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': _CRM_CSRF } });
        if (!res.ok) throw new Error();
        _crmOptions = await res.json();
        crmRenderForm();
    } catch { document.getElementById('crm-step-loading').style.display = 'none'; document.getElementById('crm-step-error').style.display = ''; }
}

function crmRenderForm() {
    document.getElementById('crm-step-loading').style.display = 'none';
    document.getElementById('crm-step-form').style.display = '';
    const sel = document.getElementById('crm-customer');
    sel.innerHTML = '<option value="">— Selecione um cliente —</option>';
    (_crmOptions.customers || []).forEach(c => { const o = document.createElement('option'); o.value = c.id; o.textContent = c.name; sel.appendChild(o); });
    crmRenderDateChips(_crmOptions.date_groups || []);
    crmRenderMonthChips(_crmOptions.dates_by_month || []);
    crmUpdateGenerateBtn();
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function crmRenderDateChips(groups) {
    const c = document.getElementById('crm-date-chips'); c.innerHTML = '';
    groups.forEach((g, idx) => {
        const btn = document.createElement('button'); btn.type = 'button';
        btn.dataset.from = g.date_from; btn.dataset.to = g.date_to; btn.dataset.idx = idx;
        btn.innerHTML = `${g.label}${g.count > 1 ? ` <span style="opacity:.55;font-size:.78em;">(${g.count})</span>` : ''}`;
        btn.style.cssText = 'padding:.28rem .65rem;border-radius:999px;border:1px solid #bbb;font-size:.72rem;font-weight:600;cursor:pointer;background:#f5f5f5;color:#333;transition:.12s;white-space:nowrap;';
        btn.addEventListener('click', () => crmSelectChip(btn, g)); c.appendChild(btn);
    });
}

function crmRenderMonthChips(months) {
    const c = document.getElementById('crm-month-chips'); c.innerHTML = '';
    months.forEach((m, idx) => {
        const btn = document.createElement('button'); btn.type = 'button';
        btn.dataset.from = m.date_from; btn.dataset.to = m.date_to; btn.dataset.idx = 'm' + idx;
        btn.innerHTML = `${m.label} <span style="opacity:.6;font-size:.8em;">(${m.count})</span>`;
        btn.style.cssText = 'padding:.25rem .6rem;border-radius:999px;border:1px solid #d1d5db;font-size:.7rem;cursor:pointer;background:#f9fafb;color:#555;white-space:nowrap;';
        btn.addEventListener('click', () => crmSelectChip(btn, m)); c.appendChild(btn);
    });
}

function crmSelectChip(btn, p) {
    if (_crmActiveChip) {
        const isMonth = _crmActiveChip.dataset.idx?.startsWith('m');
        _crmActiveChip.style.background = isMonth ? '#f9fafb' : '#f5f5f5';
        _crmActiveChip.style.color = isMonth ? '#555' : '#333';
        _crmActiveChip.style.borderColor = isMonth ? '#d1d5db' : '#bbb';
    }
    _crmActiveChip = btn; btn.style.background='#1d4ed8'; btn.style.color='#fff'; btn.style.borderColor='#1d4ed8';
    document.getElementById('crm-date-from').value = p.date_from; document.getElementById('crm-date-to').value = p.date_to;
    crmUpdateAvailability(); crmUpdateGenerateBtn();
}
function crmClearActiveChip() {
    if (_crmActiveChip) {
        const isMonth = _crmActiveChip.dataset.idx?.startsWith('m');
        _crmActiveChip.style.background = isMonth ? '#f9fafb' : '#f5f5f5';
        _crmActiveChip.style.color = isMonth ? '#555' : '#333';
        _crmActiveChip.style.borderColor = isMonth ? '#d1d5db' : '#bbb';
        _crmActiveChip = null;
    }
    crmUpdateAvailability(); crmUpdateGenerateBtn();
}
function crmSetAllDates() {
    if (!_crmOptions?.all_dates?.length) return; crmClearActiveChip();
    document.getElementById('crm-date-from').value = _crmOptions.all_dates[0];
    document.getElementById('crm-date-to').value = _crmOptions.all_dates[_crmOptions.all_dates.length - 1];
    crmUpdateAvailability(); crmUpdateGenerateBtn();
}
function crmClearDates() { crmClearActiveChip(); document.getElementById('crm-date-from').value=''; document.getElementById('crm-date-to').value=''; crmUpdateAvailability(); crmUpdateGenerateBtn(); }
function crmOnCustomerChange() { crmUpdateAvailability(); crmUpdateGenerateBtn(); }
function crmReloadWithProject() { crmLoadOptions(); }
function crmUpdateAvailability() {
    const box = document.getElementById('crm-availability');
    const cid = document.getElementById('crm-customer').value;
    const df  = document.getElementById('crm-date-from').value;
    const dt  = document.getElementById('crm-date-to').value;
    if (!cid) { box.style.display='none'; return; }
    if (df && dt && dt < df) {
        box.style.cssText = 'display:block;background:#fef2f2;border-left:3px solid #dc2626;padding:.4rem .7rem;font-size:.77rem;color:#991b1b;margin-bottom:.75rem;';
        box.textContent = '⚠ A data final deve ser igual ou posterior à data inicial.'; return;
    }
    box.style.cssText = 'display:block;background:#f0fdf4;border-left:3px solid #16a34a;padding:.4rem .7rem;font-size:.77rem;color:#166534;margin-bottom:.75rem;';
    const from = df ? new Date(df).toLocaleDateString('pt-BR') : '—';
    const to   = dt ? new Date(dt).toLocaleDateString('pt-BR') : '—';
    box.textContent = !df && !dt ? 'Todas as entregas disponíveis para este cliente.' : `Período: ${from} a ${to}`;
}
function crmUpdateGenerateBtn() {
    const btn = document.getElementById('crm-btn-generate');
    const cid = document.getElementById('crm-customer').value;
    const df  = document.getElementById('crm-date-from').value;
    const dt  = document.getElementById('crm-date-to').value;
    btn.disabled = !cid || (df && dt && dt < df);
}
function crmGenerate() {
    const cid = document.getElementById('crm-customer').value; if (!cid) return;
    const df  = document.getElementById('crm-date-from').value;
    const dt  = document.getElementById('crm-date-to').value;
    const pid = document.getElementById('crm-project')?.value || '';
    const p = new URLSearchParams({ customer_id: cid });
    if (df) p.set('date_from', df); if (dt) p.set('date_to', dt); if (pid) p.set('project_id', pid);
    if (_crmType === 'statement') {
        const colUP  = document.getElementById('crm-col-unit-price');
        const colTot = document.getElementById('crm-col-total');
        if (colUP  && !colUP.checked)  p.set('col_unit_price', '0');
        if (colTot && !colTot.checked) p.set('col_total', '0');
    }
    const endpoint = _crmEndpoints[_crmType] || 'customer-delivery-statement';
    window.open(`/${_CRM_TENANT}/delivery/reports/${endpoint}?${p.toString()}`, '_blank');
    closeCustomerReportModal();
}
</script>

<!-- Distribution Modal -->
<div class="modal-overlay" id="distModal" style="display:none"><!-- removido: substituído por x-delivery.dist-modal --></div>


@push('scripts')
<script>
const TENANT_SLUG = '{{ $currentTenant->slug }}';
const CSRF_TOKEN  = '{{ csrf_token() }}';
const ALL_CUSTOMERS = @json($customers->map(fn($c) => ['id' => $c->id, 'name' => $c->trade_name ?: $c->name]));

/* ── Receipt Modal ── */
function openReceiptModal() {
    document.getElementById('receiptModal').classList.add('active');
}
function closeReceiptModal() {
    document.getElementById('receiptModal').classList.remove('active');
}
document.getElementById('receiptModal').addEventListener('click', function(e) {
    if (e.target === this) closeReceiptModal();
});

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

