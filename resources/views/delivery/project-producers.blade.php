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
    <a href="{{ route('delivery.projects-list', ['tenant' => $tenant->slug]) }}" class="nav-tab">
        <i data-lucide="folder-open" style="width:14px;height:14px"></i> Projetos
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

{{-- ── MODAL: COMPROVANTE (multi-estado) ── --}}
<div class="modal-overlay hidden" id="receipt-modal" role="dialog" aria-modal="true" aria-labelledby="rm-title">
    <div class="modal-box">
        <div class="modal-title" id="rm-title">
            <i data-lucide="file-down" style="width:16px;height:16px;color:var(--color-primary)"></i>
            Comprovante de Entrega
        </div>
        <div style="font-size:.82rem;color:var(--color-text-secondary);margin-bottom:1rem;">
            Produtor: <strong id="modal-assoc-name">—</strong>
        </div>

        {{-- Estado: verificando --}}
        <div id="rm-checking" style="padding:1.5rem 0;text-align:center;font-size:.85rem;color:var(--color-text-secondary);">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                 style="animation:spin 1s linear infinite;display:inline-block;vertical-align:middle;margin-right:.4rem;">
                <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
            </svg>
            Verificando comprovantes existentes…
        </div>

        {{-- Estado: sem comprovante --}}
        <div id="rm-no-receipt" class="hidden">
            <div class="rm-info rm-info-blue">
                <i data-lucide="info" style="width:15px;height:15px;flex-shrink:0"></i>
                <span>Nenhum comprovante gerado ainda. <strong id="rm-dist-count">0</strong> distribuição(ões) aprovada(s) serão incluídas automaticamente.</span>
            </div>
            <div class="rm-col-selector">
                <div class="rm-col-title">Colunas do comprovante</div>
                <div class="rm-col-grid">
                    <label class="rm-col-lbl"><input type="checkbox" class="rm-col-chk" value="unit_price" checked> Vlr. Unitário</label>
                    <label class="rm-col-lbl"><input type="checkbox" class="rm-col-chk" value="gross" checked> Vlr. Bruto</label>
                    <label class="rm-col-lbl"><input type="checkbox" class="rm-col-chk" value="admin_fee"> Taxa Adm.</label>
                    <label class="rm-col-lbl"><input type="checkbox" class="rm-col-chk" value="net"> Vlr. Líquido</label>
                </div>
            </div>
            <div class="rm-footer">
                <button class="btn btn-ghost btn-sm" onclick="closeReceiptModal()">Cancelar</button>
                <button class="btn btn-primary btn-sm" id="rm-btn-gen-all" onclick="generateAllDeliveries()">
                    <i data-lucide="file-down" style="width:13px;height:13px"></i> Gerar Comprovante
                </button>
            </div>
        </div>

        {{-- Estado: tem comprovante(s) --}}
        <div id="rm-has-receipt" class="hidden">
            <div class="rm-info rm-info-yellow">
                <i data-lucide="alert-triangle" style="width:15px;height:15px;flex-shrink:0"></i>
                <span>Já existe(m) <strong id="rm-receipt-count">0</strong> comprovante(s) para este produtor neste projeto.</span>
            </div>
            <div id="rm-receipts-list" style="margin:.6rem 0;display:flex;flex-direction:column;gap:.35rem;"></div>
            <div id="rm-uncovered-warn" class="rm-info rm-info-red hidden">
                <i data-lucide="alert-circle" style="width:15px;height:15px;flex-shrink:0"></i>
                <span><strong id="rm-uncovered-count">0</strong> distribuição(ões) ainda não estão cobertas por nenhum comprovante!</span>
            </div>
            <div class="rm-footer" style="margin-top:.75rem;">
                <button class="btn btn-ghost btn-sm" onclick="closeReceiptModal()">Fechar</button>
                <button class="btn btn-primary btn-sm" onclick="enterSelectingMode()">
                    <i data-lucide="plus-circle" style="width:13px;height:13px"></i> Criar comprovante adicional
                </button>
            </div>
        </div>

        {{-- Estado: selecionando entregas para comprovante adicional --}}
        <div id="rm-selecting" class="hidden">
            <div class="rm-info rm-info-blue" style="margin-bottom:.75rem;">
                <i data-lucide="info" style="width:15px;height:15px;flex-shrink:0"></i>
                <span>Selecione as distribuições que farão parte deste comprovante adicional:</span>
            </div>
            <div id="rm-sel-area" style="min-height:60px;max-height:260px;overflow-y:auto;border:1px solid var(--color-border);border-radius:var(--radius-md);margin-bottom:.6rem;">
                <p style="padding:.75rem;font-size:.8rem;color:var(--color-text-secondary)">Carregando distribuições…</p>
            </div>
            <div style="font-size:.8rem;color:var(--color-text-secondary);margin-bottom:.6rem;">
                <strong id="rm-sel-count">0</strong> selecionada(s) &nbsp;·&nbsp;
                Líquido: <strong style="color:var(--color-success)">R$ <span id="rm-sel-total">0,00</span></strong>
            </div>
            <div class="rm-col-selector">
                <div class="rm-col-title">Colunas do comprovante</div>
                <div class="rm-col-grid">
                    <label class="rm-col-lbl"><input type="checkbox" class="rm-col-chk" value="unit_price" checked> Vlr. Unitário</label>
                    <label class="rm-col-lbl"><input type="checkbox" class="rm-col-chk" value="gross" checked> Vlr. Bruto</label>
                    <label class="rm-col-lbl"><input type="checkbox" class="rm-col-chk" value="admin_fee"> Taxa Adm.</label>
                    <label class="rm-col-lbl"><input type="checkbox" class="rm-col-chk" value="net"> Vlr. Líquido</label>
                </div>
            </div>
            <div class="rm-footer">
                <button class="btn btn-ghost btn-sm" onclick="showHasReceiptState()">← Voltar</button>
                <button class="btn btn-primary btn-sm" id="rm-btn-gen-sel" onclick="generateSelectedReceipt()">
                    <i data-lucide="file-down" style="width:13px;height:13px"></i> Gerar PDF
                </button>
            </div>
        </div>
    </div>
</div>

<style>
@keyframes spin { to { transform:rotate(360deg); } }
.modal-overlay { position:fixed;inset:0;background:rgba(0,0,0,.45);display:flex;align-items:center;justify-content:center;z-index:100000; }
.modal-overlay.hidden { display:none; }
.modal-box { background:var(--color-surface);border-radius:var(--radius-lg);padding:1.5rem;width:min(540px,95vw);max-height:90vh;overflow-y:auto;box-shadow:0 8px 32px rgba(0,0,0,.22); }
.modal-title { font-size:1rem;font-weight:700;margin-bottom:.75rem;display:flex;align-items:center;gap:.4rem; }
.hidden { display:none !important; }

.rm-info { display:flex;align-items:flex-start;gap:.5rem;padding:.6rem .9rem;border-radius:var(--radius-md);font-size:.82rem;margin-bottom:.6rem; }
.rm-info-blue  { background:#eff6ff;border:1px solid #93c5fd;color:#1e40af; }
.rm-info-yellow { background:#fef9c3;border:1px solid #fde047;color:#78350f; }
.rm-info-red   { background:#fef2f2;border:1px solid #fca5a5;color:#991b1b; }

.rm-receipt-item { display:flex;align-items:center;justify-content:space-between;padding:.45rem .75rem;background:var(--color-bg);border:1px solid var(--color-border);border-radius:var(--radius-md);font-size:.82rem; }
.rm-receipt-item a { color:var(--color-primary);text-decoration:none;font-size:.75rem;font-weight:600;white-space:nowrap; }
.rm-receipt-item a:hover { text-decoration:underline; }

.rm-col-selector { border:1px solid var(--color-border);border-radius:var(--radius-md);padding:.6rem .9rem;margin-bottom:.75rem;background:var(--color-bg); }
.rm-col-title { font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--color-text-secondary);margin-bottom:.5rem; }
.rm-col-grid { display:grid;grid-template-columns:1fr 1fr;gap:.25rem .75rem; }
.rm-col-lbl { display:flex;align-items:center;gap:.4rem;font-size:.8rem;cursor:pointer; }
.rm-col-lbl input { width:14px;height:14px;accent-color:var(--color-primary); }

.rm-footer { display:flex;gap:.5rem;justify-content:flex-end;border-top:1px solid var(--color-border);padding-top:.75rem; }

.delivery-item { display:flex;align-items:center;gap:.5rem;padding:.4rem .6rem;border-bottom:1px solid var(--color-border);font-size:.8rem; }
.delivery-item:last-child { border-bottom:none; }
.delivery-item input[type=checkbox] { width:15px;height:15px;cursor:pointer;accent-color:var(--color-primary);flex-shrink:0; }
</style>

<div id="pp-toasts" style="position:fixed;bottom:1.5rem;right:1.5rem;z-index:99999;display:flex;flex-direction:column;gap:.5rem;"></div>

<script>
const PP_TENANT  = '{{ $tenant->slug }}';
const PP_CSRF    = '{{ csrf_token() }}';
const PP_PROJECT = {{ $project->id }};
let   PP_ASSOCIATE     = null;
let   PP_ALL_DIST_IDS  = [];   // IDs de todas as distribuições aprovadas do associado
let   PP_CHECK_DATA    = null; // último resultado de receipt-check

/* ── Toasts ── */
function ppToast(msg, type = 'success') {
    const c = document.getElementById('pp-toasts');
    const el = document.createElement('div');
    el.style.cssText = 'background:var(--color-surface);border:1px solid var(--color-border);border-radius:6px;padding:.7rem 1rem;display:flex;align-items:center;gap:.5rem;font-size:.85rem;box-shadow:0 4px 14px rgba(0,0,0,.14);min-width:240px;max-width:340px;';
    el.style.borderLeft = '3px solid ' + (type === 'success' ? 'var(--color-success)' : 'var(--color-danger)');
    el.innerHTML = `<span>${type === 'success' ? '✅' : '❌'}</span><span>${msg}</span>`;
    c.appendChild(el);
    setTimeout(() => { el.style.opacity = 0; setTimeout(() => el.remove(), 300); }, 4500);
}

/* ── Estado modal ── */
function setModalState(state) {
    ['checking','no-receipt','has-receipt','selecting'].forEach(s => {
        document.getElementById('rm-' + s).classList.add('hidden');
    });
    document.getElementById('rm-' + state).classList.remove('hidden');
}

/* ── Abrir modal ── */
async function openReceiptModal(associateId, name) {
    PP_ASSOCIATE    = associateId;
    PP_ALL_DIST_IDS = [];
    PP_CHECK_DATA   = null;
    document.getElementById('modal-assoc-name').textContent = name;
    document.getElementById('receipt-modal').classList.remove('hidden');
    setModalState('checking');

    try {
        const res  = await fetch(`/${PP_TENANT}/delivery/projects/${PP_PROJECT}/associates/${associateId}/receipt-check`, {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': PP_CSRF }
        });
        PP_CHECK_DATA = await res.json();

        document.getElementById('rm-dist-count').textContent = PP_CHECK_DATA.total_dist ?? 0;
        document.getElementById('rm-receipt-count').textContent = PP_CHECK_DATA.receipt_count ?? 0;

        if (!PP_CHECK_DATA.has_receipts) {
            setModalState('no-receipt');
        } else {
            // Montar lista de comprovantes existentes
            const listEl = document.getElementById('rm-receipts-list');
            listEl.innerHTML = '';
            const allPaid = (PP_CHECK_DATA.receipts || []).every(r => r.is_paid);
            (PP_CHECK_DATA.receipts || []).forEach(r => {
                const statusColors = {
                    'draft':           { bg: '#f3f4f6', text: '#374151', label: r.status_label },
                    'pending_payment': { bg: '#fef9c3', text: '#78350f', label: r.status_label },
                    'paid':            { bg: '#d1fae5', text: '#065f46', label: r.status_label },
                };
                const sc = statusColors[r.status] ?? statusColors['draft'];
                const netBadge = r.total_net
                    ? `<span style="font-size:.72rem;color:#059669;font-weight:700;margin-left:.4rem">R$\u00a0${r.total_net}</span>`
                    : '';
                const statusBadge = `<span style="display:inline-block;padding:1px 7px;border-radius:999px;font-size:.68rem;font-weight:600;background:${sc.bg};color:${sc.text}">${sc.label}</span>`;
                const item = document.createElement('div');
                item.className = 'rm-receipt-item';
                item.innerHTML = `
                    <div style="display:flex;align-items:center;gap:.4rem;flex-wrap:wrap">
                        <strong>Nº ${r.number}</strong>
                        ${statusBadge}
                        ${netBadge}
                        <span style="color:#6b7280;font-size:.75rem">&mdash; ${r.issued_at}</span>
                    </div>
                    <a href="${r.reprint_url}" target="_blank">
                        <i data-lucide="printer" style="width:12px;height:12px;vertical-align:middle"></i> Reimprimir
                    </a>`;
                listEl.appendChild(item);
            });
            if (typeof lucide !== 'undefined') lucide.createIcons();

            // Se todos os comprovantes estão pagos, ocultar botão de criar adicional
            const addBtn = document.querySelector('#rm-has-receipt .btn-primary');
            if (addBtn) {
                if (allPaid && (PP_CHECK_DATA.uncovered_count ?? 0) === 0) {
                    addBtn.style.display = 'none';
                } else {
                    addBtn.style.display = '';
                }
            }

            // Aviso distribuições não cobertas
            const uncovWarn = document.getElementById('rm-uncovered-warn');
            const uncovCount = PP_CHECK_DATA.uncovered_count ?? 0;
            document.getElementById('rm-uncovered-count').textContent = uncovCount;
            uncovWarn.classList.toggle('hidden', uncovCount === 0);

            setModalState('has-receipt');
        }
    } catch(err) {
        ppToast('Erro ao verificar comprovantes.', 'error');
        closeReceiptModal();
    }
}

function closeReceiptModal() {
    document.getElementById('receipt-modal').classList.add('hidden');
    PP_ASSOCIATE = null;
}

document.getElementById('receipt-modal')?.addEventListener('click', function(e) {
    if (e.target === this) closeReceiptModal();
});

/* ── Gerar comprovante com TODAS as distribuições (caso único comprovante) ── */
async function generateAllDeliveries() {
    if (!PP_ASSOCIATE) return;
    const btn = document.getElementById('rm-btn-gen-all');
    btn.disabled = true;
    btn.textContent = 'Gerando…';

    try {
        // Primeiro busca todos os IDs de distribuições aprovadas
        const distRes  = await fetch(`/${PP_TENANT}/delivery/projects/${PP_PROJECT}/associates/${PP_ASSOCIATE}/deliveries?approved_only=1`, {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': PP_CSRF }
        });
        const distData = await distRes.json();

        if (!distData.length) {
            ppToast('Nenhuma distribuição aprovada encontrada para este produtor.', 'error');
            return;
        }

        const ids = distData.map(d => d.id);
        const visibleColumns = Array.from(document.querySelectorAll('#rm-no-receipt .rm-col-chk:checked')).map(c => c.value);

        await doGenerateReceipt(ids, visibleColumns, btn, 'Gerar Comprovante');
    } catch(err) {
        ppToast('Erro ao gerar comprovante.', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="file-down" style="width:13px;height:13px"></i> Gerar Comprovante';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }
}

/* ── Entrar no modo de seleção (comprovante adicional) ── */
async function enterSelectingMode() {
    setModalState('selecting');
    const area = document.getElementById('rm-sel-area');
    area.innerHTML = '<p style="padding:.75rem;font-size:.8rem;color:var(--color-text-secondary)">Carregando distribuições…</p>';

    try {
        const res  = await fetch(`/${PP_TENANT}/delivery/projects/${PP_PROJECT}/associates/${PP_ASSOCIATE}/deliveries?approved_only=1`, {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': PP_CSRF }
        });
        const data = await res.json();

        if (!data.length) {
            area.innerHTML = '<p style="padding:.75rem;font-size:.8rem;color:var(--color-text-secondary)">Nenhuma distribuição aprovada encontrada.</p>';
            return;
        }

        PP_ALL_DIST_IDS = data.map(d => d.id);

        let html = '<div>';
        html += `<div style="display:flex;align-items:center;gap:.5rem;padding:.3rem .6rem;background:var(--color-bg);font-size:.72rem;font-weight:700;color:var(--color-text-secondary);text-transform:uppercase;letter-spacing:.04em;border-bottom:1px solid var(--color-border);">
            <input type="checkbox" id="rm-sel-all" style="width:15px;height:15px;cursor:pointer;accent-color:var(--color-primary);">
            <span style="flex:1">Produto</span><span style="width:80px">Cliente</span><span style="width:75px;text-align:right">Data</span><span style="width:70px;text-align:right">Qtd</span><span style="width:72px;text-align:right">Líquido</span>
        </div>`;
        data.forEach(d => {
            html += `<div class="delivery-item">
                <input type="checkbox" class="rm-dist-chk" value="${d.id}" data-net="${d.net_value}">
                <span style="flex:1">${d.product_name}</span>
                <span style="width:80px;font-size:.75rem;color:var(--color-text-secondary)">${d.customer_name ?? ''}</span>
                <span style="width:75px;text-align:right;white-space:nowrap">${d.delivery_date}</span>
                <span style="width:70px;text-align:right;white-space:nowrap">${parseFloat(d.quantity).toLocaleString('pt-BR',{minimumFractionDigits:3})} ${d.unit}</span>
                <span style="width:72px;text-align:right;white-space:nowrap;color:var(--color-success)">R$\u00a0${parseFloat(d.net_value).toLocaleString('pt-BR',{minimumFractionDigits:2})}</span>
            </div>`;
        });
        html += '</div>';
        area.innerHTML = html;

        document.getElementById('rm-sel-all')?.addEventListener('change', function() {
            document.querySelectorAll('.rm-dist-chk').forEach(c => c.checked = this.checked);
            updateSelSummary();
        });
        document.querySelectorAll('.rm-dist-chk').forEach(c => c.addEventListener('change', updateSelSummary));
        updateSelSummary();
    } catch(err) {
        area.innerHTML = '<p style="padding:.75rem;font-size:.8rem;color:var(--color-danger)">Erro ao carregar distribuições.</p>';
    }
}

function showHasReceiptState() {
    setModalState('has-receipt');
}

function updateSelSummary() {
    const checks = document.querySelectorAll('.rm-dist-chk:checked');
    let total = 0;
    checks.forEach(c => total += parseFloat(c.dataset.net || 0));
    document.getElementById('rm-sel-count').textContent  = checks.length;
    document.getElementById('rm-sel-total').textContent   = total.toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2});
}

/* ── Gerar comprovante adicional (seleção) ── */
async function generateSelectedReceipt() {
    const checks = document.querySelectorAll('.rm-dist-chk:checked');
    if (!checks.length) { ppToast('Selecione ao menos uma distribuição.', 'error'); return; }

    const ids = Array.from(checks).map(c => parseInt(c.value));
    const visibleColumns = Array.from(document.querySelectorAll('#rm-selecting .rm-col-chk:checked')).map(c => c.value);
    const btn = document.getElementById('rm-btn-gen-sel');

    await doGenerateReceipt(ids, visibleColumns, btn, 'Gerar PDF');
}

/* ── Função central de geração via POST ── */
async function doGenerateReceipt(ids, visibleColumns, btn, originalLabel) {
    btn.disabled = true;
    btn.textContent = 'Gerando…';

    try {
        const res  = await fetch(`/${PP_TENANT}/delivery/projects/${PP_PROJECT}/receipt-selected`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': PP_CSRF, 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ delivery_ids: ids, visible_columns: visibleColumns })
        });
        const data = await res.json();

        if (data.success) {
            // Download do PDF
            const byteChars = atob(data.pdf);
            const byteArray = new Uint8Array(byteChars.length);
            for (let i = 0; i < byteChars.length; i++) byteArray[i] = byteChars.charCodeAt(i);
            const blob = new Blob([byteArray], { type: 'application/pdf' });
            const url  = URL.createObjectURL(blob);
            const a    = document.createElement('a');
            a.href = url; a.download = data.filename; a.click();
            URL.revokeObjectURL(url);

            ppToast(`Comprovante nº ${data.receipt_number} gerado com ${ids.length} entrega(s)!`);

            // Aviso de distribuições não cobertas
            if ((data.uncovered_count ?? 0) > 0) {
                ppToast(`⚠️ Atenção: ${data.uncovered_count} distribuição(ões) ainda não estão cobertas por nenhum comprovante!`, 'error');
            }

            closeReceiptModal();
        } else {
            ppToast(data.message || 'Erro ao gerar comprovante.', 'error');
        }
    } catch(err) {
        ppToast('Erro de comunicação com o servidor.', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = `<i data-lucide="file-down" style="width:13px;height:13px"></i> ${originalLabel}`;
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }
}
</script>
@endsection
