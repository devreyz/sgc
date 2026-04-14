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
    <a href="{{ route('delivery.sheet.index', ['tenant' => $tenant->slug]) }}" class="nav-tab">
        <i data-lucide="file-text" style="width:14px;height:14px"></i> Fichas
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
                            <button class="receipt-btn"
                                onclick="openReceiptModal({{ $p['associate']->id }}, '{{ addslashes($p['name']) }}')"
                                title="Gerar Comprovante por período / seleção">
                                <i data-lucide="file-down" style="width:12px;height:12px"></i>
                                Comprovante
                            </button>
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

{{-- ── MODAL: COMPROVANTE POR PERÍODO ── --}}
<div class="modal-overlay hidden" id="receipt-modal">
    <div class="modal-box">
        <div class="modal-title">
            <i data-lucide="file-down" style="width:16px;height:16px;color:var(--color-primary)"></i>
            Gerar Comprovante
        </div>
        <div style="font-size:.82rem;color:var(--color-text-secondary);margin-bottom:1rem;">
            Produtor: <strong id="modal-assoc-name">—</strong>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Data Início</label>
                <input type="date" id="modal-from" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label">Data Fim</label>
                <input type="date" id="modal-to" class="form-control">
            </div>
        </div>
        <div style="display:flex;gap:.5rem;margin-bottom:.75rem;">
            <button class="btn btn-ghost btn-sm" onclick="loadModalDeliveries()" style="flex:1;">
                <i data-lucide="search" style="width:12px;height:12px"></i> Buscar entregas
            </button>
            <button class="btn btn-ghost btn-sm" onclick="clearModalDates()">
                <i data-lucide="x" style="width:12px;height:12px"></i> Limpar datas
            </button>
        </div>

        <div id="modal-deliveries-area" style="min-height:60px;max-height:300px;overflow-y:auto;border:1px solid var(--color-border);border-radius:var(--radius-md);margin-bottom:.75rem;">
            <p style="padding:.75rem;font-size:.8rem;color:var(--color-text-secondary)">Defina um período ou clique em "Buscar entregas" para ver todas.</p>
        </div>

        <div style="font-size:.8rem;color:var(--color-text-secondary);margin-bottom:.75rem;">
            <strong id="modal-sel-count">0</strong> entrega(s) selecionada(s) &nbsp;·&nbsp;
            Líquido: <strong style="color:var(--color-success)">R$ <span id="modal-sel-total">0,00</span></strong>
        </div>

        <div style="display:flex;gap:.5rem;justify-content:flex-end;border-top:1px solid var(--color-border);padding-top:.75rem;">
            <button class="btn btn-ghost btn-sm" onclick="closeReceiptModal()">Cancelar</button>
            <button class="btn btn-primary btn-sm" id="modal-gen-btn" onclick="generateModalReceipt()">
                <i data-lucide="file-down" style="width:13px;height:13px"></i> Gerar PDF
            </button>
        </div>
    </div>
</div>

<style>
.modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.45); display:flex; align-items:center; justify-content:center; z-index:100000; }
.modal-overlay.hidden { display:none; }
.modal-box { background:var(--color-surface); border-radius:var(--radius-lg); padding:1.5rem; width:min(520px,95vw); box-shadow:0 8px 32px rgba(0,0,0,.22); }
.modal-title { font-size:1rem; font-weight:700; margin-bottom:.75rem; display:flex; align-items:center; gap:.4rem; }
.form-group { margin-bottom:.75rem; }
.form-label { display:block; font-size:.72rem; font-weight:600; margin-bottom:.25rem; color:var(--color-text-secondary); text-transform:uppercase; letter-spacing:.03em; }
.form-control { width:100%; padding:.42rem .7rem; border:1px solid var(--color-border); border-radius:var(--radius-md); font-size:.86rem; background:var(--color-bg); color:var(--color-text); }
.form-row { display:grid; grid-template-columns:1fr 1fr; gap:.75rem; }
.delivery-item { display:flex; align-items:center; gap:.5rem; padding:.4rem .6rem; border-bottom:1px solid var(--color-border); font-size:.8rem; }
.delivery-item:last-child { border-bottom:none; }
.delivery-item input[type=checkbox] { width:15px;height:15px;cursor:pointer;accent-color:var(--color-primary);flex-shrink:0; }
</style>

<div id="pp-toasts" style="position:fixed;bottom:1.5rem;right:1.5rem;z-index:99999;display:flex;flex-direction:column;gap:.5rem;"></div>

<script>
const PP_TENANT  = '{{ $tenant->slug }}';
const PP_CSRF    = '{{ csrf_token() }}';
const PP_PROJECT = {{ $project->id }};
let   PP_ASSOCIATE = null;

function ppToast(msg, type = 'success') {
    const c = document.getElementById('pp-toasts');
    const el = document.createElement('div');
    el.style.cssText = 'background:var(--color-surface);border:1px solid var(--color-border);border-radius:6px;padding:.7rem 1rem;display:flex;align-items:center;gap:.5rem;font-size:.85rem;box-shadow:0 4px 14px rgba(0,0,0,.14);min-width:240px;max-width:340px;';
    el.style.borderLeft = '3px solid ' + (type === 'success' ? 'var(--color-success)' : 'var(--color-danger)');
    el.innerHTML = `<span>${type === 'success' ? '✅' : '❌'}</span><span>${msg}</span>`;
    c.appendChild(el);
    setTimeout(() => { el.style.opacity = 0; setTimeout(() => el.remove(), 300); }, 4500);
}

function openReceiptModal(associateId, name) {
    PP_ASSOCIATE = associateId;
    document.getElementById('modal-assoc-name').textContent = name;
    document.getElementById('modal-from').value = '';
    document.getElementById('modal-to').value   = '';
    document.getElementById('modal-sel-count').textContent = '0';
    document.getElementById('modal-sel-total').textContent = '0,00';
    document.getElementById('modal-deliveries-area').innerHTML =
        '<p style="padding:.75rem;font-size:.8rem;color:var(--color-text-secondary)">Defina um período ou clique em "Buscar entregas" para ver todas.</p>';
    document.getElementById('receipt-modal').classList.remove('hidden');
}

function closeReceiptModal() {
    document.getElementById('receipt-modal').classList.add('hidden');
    PP_ASSOCIATE = null;
}

document.getElementById('receipt-modal')?.addEventListener('click', function(e) {
    if (e.target === this) closeReceiptModal();
});

function clearModalDates() {
    document.getElementById('modal-from').value = '';
    document.getElementById('modal-to').value   = '';
}

function updateModalSummary() {
    const checks = document.querySelectorAll('.modal-delivery-chk:checked');
    let total = 0;
    checks.forEach(c => total += parseFloat(c.dataset.net || 0));
    document.getElementById('modal-sel-count').textContent = checks.length;
    document.getElementById('modal-sel-total').textContent = total.toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2});
}

async function loadModalDeliveries() {
    if (!PP_ASSOCIATE) return;
    const from = document.getElementById('modal-from').value;
    const to   = document.getElementById('modal-to').value;
    const area = document.getElementById('modal-deliveries-area');
    area.innerHTML = '<p style="padding:.75rem;font-size:.8rem;color:var(--color-text-secondary)">Carregando...</p>';

    try {
        let url = `/${PP_TENANT}/delivery/projects/${PP_PROJECT}/associates/${PP_ASSOCIATE}/deliveries?approved_only=1`;
        if (from) url += `&from_date=${from}`;
        if (to)   url += `&to_date=${to}`;

        const res  = await fetch(url, { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': PP_CSRF } });
        const data = await res.json();

        if (!data.length) {
            area.innerHTML = '<p style="padding:.75rem;font-size:.8rem;color:var(--color-text-secondary)">Nenhuma entrega aprovada encontrada neste período.</p>';
            return;
        }

        let html = '<div style="padding:.3rem 0;">';
        // Cabeçalho
        html += `<div style="display:flex;align-items:center;gap:.5rem;padding:.3rem .6rem;background:var(--color-bg);font-size:.72rem;font-weight:700;color:var(--color-text-secondary);text-transform:uppercase;letter-spacing:.04em;">
            <input type="checkbox" id="modal-sel-all" style="width:15px;height:15px;cursor:pointer;accent-color:var(--color-primary);">
            <span style="flex:1">Produto</span><span style="width:75px;text-align:right">Data</span><span style="width:70px;text-align:right">Qtd</span><span style="width:70px;text-align:right">Líquido</span>
        </div>`;
        data.forEach(d => {
            html += `<div class="delivery-item">
                <input type="checkbox" class="modal-delivery-chk" value="${d.id}" data-net="${d.net_value}" checked>
                <span style="flex:1">${d.product_name}</span>
                <span style="width:75px;text-align:right;white-space:nowrap;">${d.delivery_date}</span>
                <span style="width:70px;text-align:right;white-space:nowrap;">${parseFloat(d.quantity).toLocaleString('pt-BR',{minimumFractionDigits:3})} ${d.unit}</span>
                <span style="width:70px;text-align:right;white-space:nowrap;color:var(--color-success);">R$\u00a0${parseFloat(d.net_value).toLocaleString('pt-BR',{minimumFractionDigits:2})}</span>
            </div>`;
        });
        html += '</div>';
        area.innerHTML = html;

        // Ativar seleção
        document.getElementById('modal-sel-all')?.addEventListener('change', function() {
            document.querySelectorAll('.modal-delivery-chk').forEach(c => c.checked = this.checked);
            updateModalSummary();
        });
        document.querySelectorAll('.modal-delivery-chk').forEach(c => c.addEventListener('change', updateModalSummary));
        updateModalSummary();
    } catch(err) {
        area.innerHTML = '<p style="padding:.75rem;font-size:.8rem;color:var(--color-danger)">Erro ao carregar entregas.</p>';
    }
}

async function generateModalReceipt() {
    const checks = document.querySelectorAll('.modal-delivery-chk:checked');
    if (!checks.length) { ppToast('Selecione ao menos uma entrega.', 'error'); return; }

    const ids = Array.from(checks).map(c => parseInt(c.value));
    const btn = document.getElementById('modal-gen-btn');
    btn.disabled = true;
    btn.innerHTML = 'Gerando...';

    try {
        const res  = await fetch(`/${PP_TENANT}/delivery/projects/${PP_PROJECT}/receipt-selected`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': PP_CSRF, 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ delivery_ids: ids })
        });
        const data = await res.json();
        if (data.success) {
            const byteChars = atob(data.pdf);
            const byteArray = new Uint8Array(byteChars.length);
            for (let i = 0; i < byteChars.length; i++) byteArray[i] = byteChars.charCodeAt(i);
            const blob = new Blob([byteArray], { type: 'application/pdf' });
            const url  = URL.createObjectURL(blob);
            const a    = document.createElement('a');
            a.href = url; a.download = data.filename; a.click();
            URL.revokeObjectURL(url);
            ppToast(`Comprovante nº ${data.receipt_number} gerado com ${ids.length} entrega(s)!`);
            closeReceiptModal();
        } else {
            ppToast(data.message || 'Erro ao gerar comprovante.', 'error');
        }
    } catch(err) {
        ppToast('Erro de comunicação com o servidor.', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="file-down" style="width:13px;height:13px"></i> Gerar PDF';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }
}
</script>
@endsection