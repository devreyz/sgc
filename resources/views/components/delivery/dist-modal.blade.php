{{--
    Componente: x-delivery.dist-modal
    Uso:
        <x-delivery.dist-modal
            :tenant-slug="$currentTenant->slug"
            :csrf="csrf_token()"
            :customers="$customers->map(fn($c)=>['id'=>$c->id,'name'=>$c->trade_name?:$c->name,'organization_name'=>optional($c->organization)->short_name??optional($c->organization)->name])->values()->all()"
        />

    O componente expõe window.DistModal com:
        DistModal.openFromBtn(btn)   — lê data-* do elemento botão
        DistModal.open(cfg)          — {id, product, unit, qty, distributed, existing:[{id,customer,qty,net}]}
        DistModal.close()
        DistModal.reload()           — recarrega a página após salvar (padrão)

    Variável global que pode ser sobrescrita:
        window._DistModalReload = function(savedData){ location.reload(); }
--}}
@props([
    'tenantSlug',
    'csrf',
    'customers' => [],
])

{{-- ══ CSS ══════════════════════════════════════════════════════════════ --}}
<style>
/* ── Overlay ──────────────────────────────────────────────────────────── */
#dm-overlay {
    position: fixed; inset: 0;
    background: rgba(0,0,0,.52);
    backdrop-filter: blur(3px);
    display: none; align-items: center; justify-content: center;
    padding: 1rem; z-index: 300000;
}
#dm-overlay.dm-open { display: flex; }

/* ── Box ──────────────────────────────────────────────────────────────── */
.dm-box {
    background: var(--color-surface);
    border-radius: var(--radius-lg);
    width: min(560px, 96vw);
    max-height: 92vh;
    display: flex; flex-direction: column;
    box-shadow: 0 24px 64px rgba(0,0,0,.32);
    overflow: hidden;
}

/* ── Header ───────────────────────────────────────────────────────────── */
.dm-head {
    padding: .85rem 1.1rem;
    border-bottom: 1px solid var(--color-border);
    display: flex; justify-content: space-between; align-items: flex-start;
    gap: .75rem; flex-shrink: 0;
}
.dm-head-info { min-width: 0; flex: 1; }
.dm-title {
    font-size: .95rem; font-weight: 700;
    display: flex; align-items: center; gap: .4rem; margin: 0 0 .18rem;
}
.dm-subtitle {
    font-size: .79rem; color: var(--color-text-secondary);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.dm-close-btn {
    width: 28px; height: 28px; border: none; background: transparent;
    cursor: pointer; border-radius: var(--radius-md);
    display: flex; align-items: center; justify-content: center;
    color: var(--color-text-muted); flex-shrink: 0; font-size: 1.1rem;
}
.dm-close-btn:hover { background: var(--color-border); }

/* ── Progress ─────────────────────────────────────────────────────────── */
.dm-progress-wrap {
    padding: .75rem 1.1rem .55rem;
    border-bottom: 1px solid var(--color-border);
    flex-shrink: 0;
}
.dm-progress-track {
    height: 10px; border-radius: 99px;
    background: color-mix(in srgb, var(--color-border) 70%, transparent);
    overflow: hidden; display: flex;
    margin-bottom: .4rem;
}
.dm-bar-existing { background: #9ca3af; transition: width .2s; }
.dm-bar-new      { background: #4f46e5; transition: width .2s; }
.dm-progress-labels {
    display: flex; justify-content: space-between;
    font-size: .72rem; color: var(--color-text-secondary);
}
.dm-progress-labels strong { color: var(--color-text); }
#dm-warning-overflow {
    margin-top: .35rem; font-size: .75rem; font-weight: 600;
    color: #dc2626; display: none; align-items: center; gap: .3rem;
}
#dm-warning-overflow.visible { display: flex; }
#dm-done-badge {
    margin-top: .35rem; font-size: .75rem; font-weight: 600;
    color: #059669; display: none; align-items: center; gap: .3rem;
}
#dm-done-badge.visible { display: flex; }

/* ── Body ─────────────────────────────────────────────────────────────── */
.dm-body {
    padding: .85rem 1.1rem;
    overflow-y: auto; flex: 1;
    display: flex; flex-direction: column; gap: .55rem;
}

/* ── Section labels ───────────────────────────────────────────────────── */
.dm-section-lbl {
    font-size: .68rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: .05em;
    color: var(--color-text-muted); margin-bottom: .3rem;
}

/* ── Existing distributions ───────────────────────────────────────────── */
.dm-existing-block {
    background: color-mix(in srgb, var(--color-border) 40%, transparent);
    border-radius: var(--radius-md); overflow: hidden;
}
.dm-existing-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: .32rem .65rem; gap: .5rem;
    border-bottom: 1px solid color-mix(in srgb, var(--color-border) 60%, transparent);
    font-size: .82rem;
}
.dm-existing-row:last-child { border-bottom: none; }
.dm-existing-customer { flex: 1; display: flex; flex-direction: column; justify-content: center; align-items: start; font-weight: 500; }
.dm-existing-price { margin-top:.08rem; color:var(--color-text-muted); font-size:.68rem; font-weight:600; }
.dm-existing-qty { white-space: nowrap; color: var(--color-text-secondary); }
.dm-existing-net {
    white-space: nowrap; font-size: .78rem;
    color: var(--color-success); font-weight: 600;
    min-width: 70px; text-align: right;
}
.dm-del-btn {
    width: 30px; height: 30px; border: 1px solid transparent;
    background: transparent; cursor: pointer;
    color: var(--color-danger); font-size: .9rem;
    border-radius: var(--radius-md); flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    opacity: .65; transition: opacity .15s, background .15s, border-color .15s;
}
.dm-del-btn:hover { opacity: 1; background: rgba(220,38,38,.12); border-color: var(--color-danger); }
.dm-del-btn:focus { outline: none; opacity: 1; border-color: var(--color-danger); box-shadow: 0 0 0 3px rgba(220,38,38,.2); }
.dm-existing-actions { display:flex; align-items:center; gap:.25rem; flex-shrink:0; }
.dm-edit-btn {
    width: 30px; height: 30px; border: 1px solid transparent;
    background: transparent; cursor: pointer;
    color: #2563eb; font-size: .9rem;
    border-radius: var(--radius-md); flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    opacity: .7; transition: opacity .15s, background .15s, border-color .15s;
}
.dm-edit-btn:hover { opacity: 1; background: rgba(37,99,235,.12); border-color:#2563eb; }
.dm-action-disabled {
    width: 30px; height: 30px; border: 1px solid transparent;
    background: transparent; color: var(--color-text-muted);
    opacity: .55; display:flex; align-items:center; justify-content:center;
    cursor:not-allowed; border-radius:var(--radius-md);
}
.dm-status-badges { display:flex; gap:.25rem; flex-wrap:wrap; justify-content:flex-end; }
.dm-status-badge {
    font-size:.62rem; font-weight:700; padding:.12rem .35rem;
    border-radius:999px; border:1px solid transparent; white-space:nowrap;
}
.dm-status-badge.receipt { color:#7c3aed; background:#ede9fe; border-color:#ddd6fe; }
.dm-status-badge.billed { color:#1d4ed8; background:#dbeafe; border-color:#bfdbfe; }
.dm-status-badge.paid { color:#047857; background:#d1fae5; border-color:#a7f3d0; }
.dm-inline-edit {
    display:grid; grid-template-columns:1fr 110px auto; gap:.4rem;
    width:100%; align-items:center;
}
.dm-inline-edit select,
.dm-inline-edit input {
    min-height:36px; border:1.5px solid var(--color-border);
    border-radius:var(--radius-md); background:var(--color-surface);
    color:var(--color-text); padding:.4rem .55rem; font-size:.82rem;
}
.dm-inline-edit-actions { display:flex; gap:.25rem; }
.dm-mini-btn {
    width:32px; height:32px; border-radius:var(--radius-md);
    border:1px solid var(--color-border); background:var(--color-surface);
    cursor:pointer; display:flex; align-items:center; justify-content:center;
}
.dm-mini-btn.save { color:#059669; }
.dm-mini-btn.cancel { color:var(--color-text-muted); }

#dm-confirm-overlay {
    position: fixed; inset:0; z-index:310000; display:none;
    align-items:center; justify-content:center; padding:1rem;
    background:rgba(0,0,0,.5);
}
#dm-confirm-overlay.open { display:flex; }
#dm-notice-overlay {
    position:fixed; inset:0; z-index:320000; display:none;
    align-items:center; justify-content:center; padding:1rem;
    background:rgba(15,23,42,.52); backdrop-filter:blur(3px);
}
#dm-notice-overlay.open { display:flex; }
.dm-notice-box {
    width:min(440px,95vw); overflow:hidden;
    border:1px solid var(--color-border); border-radius:var(--radius-lg);
    background:var(--color-surface); box-shadow:0 22px 56px rgba(15,23,42,.28);
}
.dm-notice-head { display:flex; gap:.7rem; align-items:flex-start; padding:1rem 1rem .65rem; }
.dm-notice-icon {
    width:34px; height:34px; flex:0 0 auto; display:grid; place-items:center;
    border-radius:var(--radius-md); color:#b45309; background:#fef3c7;
}
.dm-notice-box.error .dm-notice-icon { color:#b91c1c; background:#fee2e2; }
.dm-notice-box.info .dm-notice-icon { color:#1d4ed8; background:#dbeafe; }
.dm-notice-copy { min-width:0; flex:1; }
.dm-notice-title { margin:0; color:var(--color-text); font-size:.94rem; font-weight:800; }
.dm-notice-message { margin:.25rem 0 0; color:var(--color-text-secondary); font-size:.82rem; line-height:1.48; }
.dm-notice-form { display:none; padding:0 1rem .8rem; }
.dm-notice-form.visible { display:block; }
.dm-notice-form label { display:block; margin-bottom:.3rem; color:var(--color-text-secondary); font-size:.72rem; font-weight:700; }
.dm-price-input-wrap { position:relative; }
.dm-price-prefix { position:absolute; top:50%; left:.7rem; color:var(--color-text-muted); font-size:.82rem; transform:translateY(-50%); }
.dm-notice-form input {
    width:100%; min-height:42px; padding:.55rem .7rem .55rem 2.5rem;
    border:1.5px solid var(--color-border); border-radius:var(--radius-md);
    background:var(--color-bg); color:var(--color-text);
}
.dm-notice-actions { display:flex; justify-content:flex-end; gap:.5rem; padding:.75rem 1rem; border-top:1px solid var(--color-border); }
.dm-confirm-box {
    width:min(420px,95vw); background:var(--color-surface);
    border:1px solid var(--color-border); border-radius:var(--radius-lg);
    box-shadow:0 20px 50px rgba(0,0,0,.28); padding:1rem;
}
.dm-confirm-title { font-size:.95rem; font-weight:800; margin-bottom:.45rem; }
.dm-confirm-text { font-size:.82rem; color:var(--color-text-secondary); line-height:1.45; }
.dm-confirm-math { margin-top:.8rem; display:flex; flex-direction:column; gap:.3rem; }
.dm-confirm-math label { font-size:.72rem; font-weight:700; color:var(--color-text-secondary); text-transform:uppercase; }
.dm-confirm-math input {
    min-height:40px; border:1.5px solid var(--color-border);
    border-radius:var(--radius-md); padding:.45rem .65rem; font-size:.9rem;
    background:var(--color-bg); color:var(--color-text);
}
.dm-confirm-actions { margin-top:1rem; display:flex; justify-content:flex-end; gap:.5rem; }

/* ── New-row inputs ───────────────────────────────────────────────────── */
.dm-row {
    display:grid; grid-template-columns:minmax(0,1fr) 110px 36px; gap:.4rem; align-items:center;
}
.dm-row select {
    flex: 1; font-size: .88rem;
    padding: .5rem .65rem;
    border: 1.5px solid var(--color-border);
    border-radius: var(--radius-md);
    background: var(--color-surface); color: var(--color-text);
    transition: border-color .15s, box-shadow .15s;
    min-height: 40px;
}
.dm-row select:focus {
    outline: none;
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-primary) 15%, transparent);
}
.dm-row input[type=number] {
    width: 110px; font-size: .88rem;
    padding: .5rem .65rem;
    border: 1.5px solid var(--color-border);
    border-radius: var(--radius-md);
    background: var(--color-surface); color: var(--color-text);
    transition: border-color .15s, box-shadow .15s;
    min-height: 40px;
}
.dm-row input[type=number]:focus {
    outline: none;
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-primary) 15%, transparent);
}
.dm-row .dm-rm-btn {
    width: 36px; height: 40px; flex-shrink: 0;
    border: 1.5px solid var(--color-border); background: transparent;
    border-radius: var(--radius-md); cursor: pointer;
    color: var(--color-danger); font-size: 1.2rem; line-height: 1;
    display: flex; align-items: center; justify-content: center;
    transition: background .15s, border-color .15s;
}
.dm-row .dm-rm-btn:hover { background: rgba(220,38,38,.12); border-color: var(--color-danger); }
.dm-row .dm-rm-btn:focus { outline: none; border-color: var(--color-danger); box-shadow: 0 0 0 3px rgba(220,38,38,.2); }
.dm-row-price {
    grid-column:1 / -1; min-height:20px; display:flex; align-items:center; gap:.35rem;
    padding:0 .1rem; color:var(--color-text-muted); font-size:.7rem; font-weight:600;
}
.dm-row-price.available { color:#047857; }
.dm-row-price.missing { color:#b45309; }
.dm-row-price button {
    border:0; padding:0; background:transparent; color:var(--color-primary-dark);
    font-size:.7rem; font-weight:800; cursor:pointer; text-decoration:underline;
}

/* ── Add-row button ───────────────────────────────────────────────────── */
.dm-add-btn {
    font-size: .82rem; color: #4f46e5;
    background: transparent;
    border: 1.5px dashed #c7d2fe;
    border-radius: var(--radius-md);
    padding: .45rem 1rem; cursor: pointer;
    text-align: left; transition: .15s; align-self: flex-start;
    min-height: 38px; display: inline-flex; align-items: center; gap: .35rem;
    font-weight: 600;
}
.dm-add-btn:hover { background: #ede9fe; border-color: #4f46e5; }
.dm-add-btn:focus { outline: none; border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79,70,229,.2); }

/* ── Footer ───────────────────────────────────────────────────────────── */
.dm-foot {
    padding: .75rem 1.1rem;
    border-top: 1px solid var(--color-border);
    display: flex; justify-content: flex-end; gap: .5rem;
    flex-shrink: 0;
}
</style>

{{-- ══ HTML ═════════════════════════════════════════════════════════════ --}}
<div id="dm-overlay" onclick="if(event.target===this)DistModal.close()">
    <div class="dm-box">

        {{-- Head --}}
        <div class="dm-head">
            <div class="dm-head-info">
                <div class="dm-title">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                         fill="none" stroke="#4f46e5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="6" y1="3" x2="6" y2="15"/><circle cx="18" cy="6" r="3"/>
                        <circle cx="6" cy="18" r="3"/><path d="M18 9a9 9 0 0 1-9 9"/>
                    </svg>
                    Distribuir Recepção
                </div>
                <div id="dm-subtitle" class="dm-subtitle"></div>
            </div>
            <button class="dm-close-btn" onclick="DistModal.close()" aria-label="Fechar modal de distribuição">✕</button>
        </div>

        {{-- Progress bar --}}
        <div class="dm-progress-wrap">
            <div class="dm-progress-track">
                <div id="dm-bar-existing" class="dm-bar-existing" style="width:0%"></div>
                <div id="dm-bar-new"      class="dm-bar-new"      style="width:0%"></div>
            </div>
            <div class="dm-progress-labels">
                <span id="dm-lbl-existing"></span>
                <span id="dm-lbl-available"></span>
            </div>
            <div id="dm-warning-overflow">
                ⚠ Total excede a quantidade disponível
            </div>
            <div id="dm-done-badge">
                ✓ 100 % distribuído
            </div>
        </div>

        {{-- Body --}}
        <div class="dm-body">

            {{-- Existing distributions --}}
            <div id="dm-existing-section" style="display:none">
                <div class="dm-section-lbl">Já distribuído</div>
                <div class="dm-existing-block" id="dm-existing-list"></div>
            </div>

            {{-- New rows --}}
            <div id="dm-new-section">
                <div class="dm-section-lbl">Adicionar distribuições</div>
                <div id="dm-new-rows" style="display:flex;flex-direction:column;gap:.4rem"></div>
                <button type="button" class="dm-add-btn" style="margin-top:.4rem" onclick="DistModal.addRow()" aria-label="Adicionar linha de distribuição">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Adicionar cliente
                </button>
                <button type="button" class="dm-add-btn" style="margin-top:.4rem" onclick="DistModal.restoreDefaultCustomers()" aria-label="Restaurar clientes padrao">
                    Restaurar clientes padrao
                </button>
            </div>
        </div>

        {{-- Footer --}}
        <div class="dm-foot">
            <button class="btn btn-ghost btn-sm" onclick="DistModal.close()" aria-label="Cancelar e fechar modal">Cancelar</button>
            <button class="btn btn-primary btn-sm" id="dm-save-btn" onclick="DistModal.save()" aria-label="Salvar distribuições">
                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
                Salvar distribuições
            </button>
        </div>
    </div>
</div>

{{-- ══ JS ══════════════════════════════════════════════════════════════ --}}
<div id="dm-confirm-overlay" onclick="if(event.target===this)DistModal.cancelDangerConfirm()">
    <div class="dm-confirm-box" role="dialog" aria-modal="true" aria-labelledby="dm-confirm-title">
        <div class="dm-confirm-title" id="dm-confirm-title">Confirmar exclusao</div>
        <div class="dm-confirm-text" id="dm-confirm-text"></div>
        <div class="dm-confirm-math">
            <label for="dm-confirm-answer">Quanto e 1 + 1?</label>
            <input id="dm-confirm-answer" type="number" inputmode="numeric" autocomplete="off" placeholder="Digite o resultado">
        </div>
        <div class="dm-confirm-actions">
            <button type="button" class="btn btn-ghost btn-sm" onclick="DistModal.cancelDangerConfirm()">Cancelar</button>
            <button type="button" class="btn btn-danger btn-sm" id="dm-confirm-ok" onclick="DistModal.acceptDangerConfirm()">Excluir distribuicao</button>
        </div>
    </div>
</div>

<div id="dm-notice-overlay" onclick="if(event.target===this)DistModal.closeNotice()">
    <div class="dm-notice-box" id="dm-notice-box" role="alertdialog" aria-modal="true" aria-labelledby="dm-notice-title">
        <div class="dm-notice-head">
            <div class="dm-notice-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
            </div>
            <div class="dm-notice-copy">
                <h3 class="dm-notice-title" id="dm-notice-title">Atencao</h3>
                <p class="dm-notice-message" id="dm-notice-message"></p>
            </div>
        </div>
        <div class="dm-notice-form" id="dm-price-form">
            <label for="dm-price-input">Valor unitario</label>
            <div class="dm-price-input-wrap">
                <span class="dm-price-prefix">R$</span>
                <input id="dm-price-input" type="number" inputmode="decimal" min="0.0001" max="999999.9999" step="0.0001" autocomplete="off">
            </div>
        </div>
        <div class="dm-notice-actions">
            <button type="button" class="btn btn-ghost btn-sm" onclick="DistModal.closeNotice()">Fechar</button>
            <button type="button" class="btn btn-primary btn-sm" id="dm-notice-action" style="display:none"></button>
        </div>
    </div>
</div>

<script>
(function () {
'use strict';

/* ── Config injetada pelo Blade ────────────────────────────────────── */
const DM_TENANT    = @json($tenantSlug);
const DM_CSRF      = @json($csrf);
const DM_CUSTOMERS = @json($customers);

/* ── State ─────────────────────────────────────────────────────────── */
let _id        = null;  // reception delivery id
let _unit      = 'un';
let _totalQty  = 0;
let _distQty   = 0;     // already distributed (existing, from DB)
let _activeCustomers = DM_CUSTOMERS;
let _existing = [];
let _pendingDangerDelete = null;
let _customerStateKey = null;
const _customerStates = new Map();
const _priceCache = new Map();
let _noticeAction = null;
let _priceEditor = null;

/* ── Helpers ───────────────────────────────────────────────────────── */
function esc(s) {
    return (s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function fmt(n, unit) {
    return parseFloat(n).toLocaleString('pt-BR', {minimumFractionDigits: 3, maximumFractionDigits: 3}) + ' ' + (unit || 'un');
}
function fmtR(n) {
    return 'R$ ' + parseFloat(n).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}
function $ (id) { return document.getElementById(id); }

function showNotice(message, options = {}) {
    const box = $('dm-notice-box');
    box.className = 'dm-notice-box ' + (options.type || 'warning');
    $('dm-notice-title').textContent = options.title || 'Atencao';
    $('dm-notice-message').textContent = message || 'Nao foi possivel concluir esta acao.';
    $('dm-price-form').classList.remove('visible');
    const action = $('dm-notice-action');
    _noticeAction = typeof options.onAction === 'function' ? options.onAction : null;
    action.style.display = _noticeAction ? '' : 'none';
    action.textContent = options.actionLabel || 'Continuar';
    action.disabled = false;
    action.onclick = () => _noticeAction?.();
    $('dm-notice-overlay').classList.add('open');
    setTimeout(() => (_noticeAction ? action : $('dm-notice-overlay').querySelector('.btn-ghost'))?.focus(), 50);
}

function priceCacheKey(customerId) {
    return `${_id}:${customerId}`;
}

function renderRowPrice(row, pricing) {
    const target = row.querySelector('.dm-row-price');
    if (!target) return;
    target.className = 'dm-row-price';
    if (!pricing) {
        target.textContent = '';
        return;
    }
    if (pricing.unit_price !== null && pricing.unit_price !== undefined) {
        target.classList.add('available');
        target.textContent = `Valor unitario: ${fmtR(pricing.unit_price)}`;
        return;
    }
    target.classList.add('missing');
    target.innerHTML = '<span>Sem preco configurado</span>';
    if (pricing.can_configure_price) {
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = 'Configurar preco';
        button.onclick = () => DistModal.openPriceEditor(pricing);
        target.appendChild(button);
    }
}

async function loadRowPrice(row, customerId, force = false) {
    if (!customerId || !_id) return renderRowPrice(row, null);
    const key = priceCacheKey(customerId);
    if (!force && _priceCache.has(key)) return renderRowPrice(row, _priceCache.get(key));
    const target = row.querySelector('.dm-row-price');
    if (target) target.textContent = 'Consultando preco...';
    try {
        const response = await fetch(`/${DM_TENANT}/delivery/deliveries/${_id}/customers/${customerId}/price`, {
            headers: { 'Accept': 'application/json' },
        });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'Nao foi possivel consultar o preco.');
        _priceCache.set(key, data);
        renderRowPrice(row, data);
    } catch (error) {
        if (target) {
            target.className = 'dm-row-price missing';
            target.textContent = error.message;
        }
    }
}

function customerState(participants, context) {
    const defaultIds = (participants.length > 0
        ? DM_CUSTOMERS.filter(customer => participants.some(id => String(id) === String(customer.id)))
        : DM_CUSTOMERS).map(customer => String(customer.id));
    const nextKey = context || 'customers:' + defaultIds.slice().sort().join(',');
    if (_customerStateKey && _customerStateKey !== nextKey) {
        _customerStates.delete(_customerStateKey);
    }
    _customerStateKey = nextKey;
    if (!_customerStates.has(_customerStateKey)) {
        _customerStates.set(_customerStateKey, { defaultIds, activeIds: defaultIds.slice(), excludedIds: [] });
    }
    const state = _customerStates.get(_customerStateKey);
    state.defaultIds = defaultIds;
    state.activeIds = state.activeIds.filter(id => defaultIds.includes(String(id)));
    _activeCustomers = DM_CUSTOMERS.filter(customer => state.activeIds.includes(String(customer.id)));
    return state;
}

function excludeCustomer(customerId) {
    if (!customerId || !_customerStateKey) return;
    const state = _customerStates.get(_customerStateKey);
    if (!state) return;
    const id = String(customerId);
    state.activeIds = state.activeIds.filter(item => String(item) !== id);
    if (!state.excludedIds.includes(id)) state.excludedIds.push(id);
    _activeCustomers = DM_CUSTOMERS.filter(customer => state.activeIds.includes(String(customer.id)));
}

function focusQtyInput(currentInput, direction) {
    const inputs = Array.from($('dm-new-rows').querySelectorAll('.dm-row input[type=number]'));
    const currentIndex = inputs.indexOf(currentInput);
    if (currentIndex < 0) return;

    const nextInput = inputs[currentIndex + direction];
    if (nextInput) {
        nextInput.focus();
        nextInput.select();
    }
}

function syncOpenButtonsAfterDelete(deliveryId, distributionId, data) {
    if (!deliveryId) return;
    document.querySelectorAll(`.btn-distribute[data-id="${deliveryId}"]`).forEach(btn => {
        let existing = [];
        try { existing = JSON.parse(btn.dataset.existing || '[]'); } catch {}
        btn.dataset.existing = JSON.stringify(existing.filter(item => String(item.id) !== String(distributionId)));
        if (data && data.dist_total_qty !== undefined) {
            btn.dataset.distributed = data.dist_total_qty;
        }
    });
}

function normalizeExisting(d) {
    return {
        id: d.id || 0,
        customer_id: d.customer_id || d.customerId || null,
        customer: d.customer || '?',
        qty: parseFloat(d.qty || d.quantity || 0),
        unit_price: parseFloat(d.unit_price || d.unitPrice || 0),
        price_inconsistent: !!d.price_inconsistent,
        net: parseFloat(d.net || 0),
        billed: !!d.billed,
        paid: !!d.paid,
        in_receipt: !!d.in_receipt,
        receipt_id: d.receipt_id || null,
        receipt_number: d.receipt_number || null,
        billing_receipt_id: d.billing_receipt_id || null,
        locked: !!d.locked,
        billing_status: d.billing_status || null,
    };
}

function distributionIsEditable(d) {
    return d.id && !d.locked && !d.paid && !d.billed && !d.billing_receipt_id;
}

function distributionCanDelete(d) {
    return d.id && !d.locked && !d.paid && !d.billed && !d.billing_receipt_id;
}

function statusBadges(d) {
    const badges = [];
    if (d.price_inconsistent) badges.push('<span class="dm-status-badge" style="background:#fff7ed;color:#9a3412" title="Edite e salve para recalcular o preco pela tabela do cliente">Recalcular preco</span>');
    if (d.in_receipt) badges.push(`<span class="dm-status-badge receipt" title="${d.receipt_number ? 'Comprovante ' + esc(d.receipt_number) : 'Em comprovante'}">Em comprovante</span>`);
    if (d.billed && !d.paid) badges.push('<span class="dm-status-badge billed">Faturada</span>');
    if (d.paid) badges.push('<span class="dm-status-badge paid">Paga</span>');
    return badges.length ? `<span class="dm-status-badges">${badges.join('')}</span>` : '';
}

/* ── Progress update (called on every input event) ─────────────────── */
function updateProgress() {
    const newRows  = $('dm-new-rows').querySelectorAll('.dm-row');
    let   newTotal = 0;
    newRows.forEach(r => { newTotal += parseFloat(r.querySelector('input')?.value || 0); });

    const existPct  = _totalQty > 0 ? Math.min(100, _distQty / _totalQty * 100) : 0;
    const newPct    = _totalQty > 0 ? Math.min(100 - existPct, newTotal / _totalQty * 100) : 0;
    const avail     = Math.max(0, _totalQty - _distQty - newTotal);
    const overflow  = (_distQty + newTotal) > (_totalQty + 0.0005);

    $('dm-bar-existing').style.width = existPct + '%';
    $('dm-bar-new').style.width      = newPct   + '%';

    $('dm-lbl-existing').innerHTML  =
        'Já distribuído: <strong>' + fmt(_distQty, _unit) + '</strong>' +
        (newTotal > 0 ? ' + <strong style="color:#4f46e5">' + fmt(newTotal, _unit) + '</strong>' : '');
    $('dm-lbl-available').innerHTML =
        'Disponível: <strong style="color:' + (avail <= 0 && !overflow ? '#059669' : avail < 0 ? '#dc2626' : 'var(--color-text)') + '">' +
        fmt(avail, _unit) + '</strong>';

    $('dm-warning-overflow').classList.toggle('visible', overflow);
    $('dm-done-badge').classList.toggle('visible', !overflow && avail <= 0.0005 && (_distQty + newTotal) > 0);
}

/* ── Build existing distributions list ─────────────────────────────── */
function renderExisting(existing) {
    const section = $('dm-existing-section');
    const list    = $('dm-existing-list');
    if (!existing || existing.length === 0) {
        _existing = [];
        section.style.display = 'none';
        return;
    }
    section.style.display = '';
    _existing = existing.map(normalizeExisting);
    list.innerHTML = _existing.map(d => `
        <div class="dm-existing-row" id="dmex-${d.id}">
            <span class="dm-existing-customer"> ${esc(d.customer)} <small class="dm-existing-price">Valor unitario: ${fmtR(d.unit_price)}</small> ${statusBadges(d)} </span>
            <span class="dm-existing-qty">${fmt(d.qty, _unit)}</span>
            <span class="dm-existing-net">${d.net > 0 ? fmtR(d.net) : ''}</span>
            <span class="dm-existing-actions">
                ${distributionIsEditable(d)
                    ? `<button class="dm-edit-btn" title="Editar distribuicao" aria-label="Editar distribuicao de ${esc(d.customer)}" onclick="DistModal.editExisting(${d.id})">✎</button>`
                    : `<span class="dm-action-disabled" title="${d.in_receipt ? 'Remova do comprovante antes de editar' : 'Distribuicao faturada, paga ou bloqueada'}">✎</span>`}
                ${distributionCanDelete(d)
                    ? `<button class="dm-del-btn" title="${d.in_receipt ? 'Remover do comprovante e excluir' : 'Remover distribuicao'}" aria-label="Remover distribuicao de ${esc(d.customer)}" onclick="DistModal.deleteExisting(${d.id})">×</button>`
                    : `<span class="dm-action-disabled" title="Distribuicao faturada, paga ou bloqueada">×</span>`}
            </span>
        </div>
    `).join('');
    return;
    list.innerHTML = existing.map(d => `
        <div class="dm-existing-row" id="dmex-${d.id}">
            <span class="dm-existing-customer">${esc(d.customer)}</span>
            <span class="dm-existing-qty">${fmt(d.qty, _unit)}</span>
            <span class="dm-existing-net">${d.net > 0 ? fmtR(d.net) : ''}</span>
            ${d.billed
                ? `<span title="Distribuição faturada — não pode ser removida" style="width:30px;height:30px;display:flex;align-items:center;justify-content:center;color:#4f46e5;flex-shrink:0;" aria-label="Faturado"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span>`
                : (d.id ? `<button class="dm-del-btn" title="Remover distribuição" aria-label="Remover distribuição de ${esc(d.customer)}"
                    onclick="DistModal.deleteExisting(${d.id})">✕</button>` : '')
            }
        </div>
    `).join('');
}

/* ── Build one new-row ──────────────────────────────────────────────── */
function buildRow(preselectId = null, autofocus = false) {
    const row = document.createElement('div');
    row.className = 'dm-row';
    row.setAttribute('role', 'group');
    row.setAttribute('aria-label', 'Linha de distribuição');

    const rowIdx = $('dm-new-rows').children.length + 1;

    const sel = document.createElement('select');
    sel.setAttribute('aria-label', 'Selecionar cliente para distribuição ' + rowIdx);
    sel.innerHTML = '<option value="">Selecionar cliente…</option>' +
        DM_CUSTOMERS.map(c => `<option value="${c.id}"${c.id == preselectId ? ' selected' : ''}>${esc(c.name)}</option>`).join('');

    const activeIds = new Set(_activeCustomers.map(c => String(c.id)));
    Array.from(sel.options).forEach(option => {
        if (option.value && !activeIds.has(String(option.value))) option.remove();
    });
    sel.addEventListener('change', () => loadRowPrice(row, sel.value));

    const inp = document.createElement('input');
    inp.type        = 'number';
    inp.min         = '0.001';
    inp.step        = '0.001';
    inp.placeholder = '0';
    inp.setAttribute('aria-label', 'Quantidade para distribuição ' + rowIdx);
    inp.addEventListener('input', updateProgress);
    inp.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            focusQtyInput(inp, 1);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            focusQtyInput(inp, -1);
        }
    });

    const rm = document.createElement('button');
    rm.type      = 'button';
    rm.className = 'dm-rm-btn';
    rm.setAttribute('aria-label', 'Remover esta linha de distribuição');
    rm.textContent = '×';
    rm.onclick = () => { excludeCustomer(sel.value); row.remove(); updateProgress(); };

    row.appendChild(sel);
    row.appendChild(inp);
    row.appendChild(rm);
    const price = document.createElement('div');
    price.className = 'dm-row-price';
    row.appendChild(price);
    if (preselectId) queueMicrotask(() => loadRowPrice(row, preselectId));
    return row;
}

function customerOptions(selectedId) {
    const options = _activeCustomers.slice();
    const selected = DM_CUSTOMERS.find(c => String(c.id) === String(selectedId));
    if (selected && !options.some(c => String(c.id) === String(selectedId))) options.push(selected);
    return options.map(c =>
        `<option value="${c.id}"${String(c.id) === String(selectedId) ? ' selected' : ''}>${esc(c.name)}${c.organization_name ? ' · ' + esc(c.organization_name) : ''}</option>`
    ).join('');
}

function setExistingFromServer(distribution) {
    const index = _existing.findIndex(d => String(d.id) === String(distribution.id));
    const next = normalizeExisting(distribution);
    if (index >= 0) _existing[index] = next;
    renderExisting(_existing);
}

/* ══ Public API ════════════════════════════════════════════════════════ */
window.DistModal = {

    /** Open from a data-* button (used by project-deliveries & all-deliveries) */
    openFromBtn(btn) {
        let existing = [];
        let participants = [];
        try { existing     = JSON.parse(btn.dataset.existing     || '[]'); } catch {}
        try { participants = JSON.parse(btn.dataset.participants  || '[]'); } catch {}
        this.open({
            id:          btn.dataset.id,
            product:     btn.dataset.product || '-',
            unit:        btn.dataset.unit    || 'un',
            qty:         parseFloat(btn.dataset.qty) || 0,
            distributed: parseFloat(btn.dataset.distributed) || 0,
            existing,
            participants,
            context: btn.dataset.context || null,
        });
    },

    /** Open with explicit config */
    open(cfg) {
        _id       = cfg.id;
        _unit     = cfg.unit      || 'un';
        _totalQty = cfg.qty       || 0;
        _distQty  = cfg.distributed || 0;

        // Subtitle
        $('dm-subtitle').textContent =
            (cfg.product || '') + '  ·  Total: ' +
            fmt(_totalQty, _unit) + '  ·  Disponível: ' +
            fmt(Math.max(0, _totalQty - _distQty), _unit);

        // Existing
        renderExisting(cfg.existing || []);

        // New rows — pre-populate per participant if available, else one blank row
        $('dm-new-rows').innerHTML = '';
        const participants = Array.isArray(cfg.participants) ? cfg.participants : [];
        customerState(participants, cfg.context || null);
        // Determine which customers are already fully existing (all listed = skip pre-populating those)
        const existingIds = new Set((cfg.existing || []).map(d => d.customer_id || d.customerId).filter(Boolean).map(String));
        // Filter participants to those not yet in existing
        const toPreload = participants.filter(id => _activeCustomers.some(c => c.id == id) && !existingIds.has(String(id)));
        if (toPreload.length > 0) {
            toPreload.forEach(id => {
                $('dm-new-rows').appendChild(buildRow(id));
            });
        } else {
            $('dm-new-rows').appendChild(buildRow());
        }

        // Progress
        updateProgress();

        // Open
        $('dm-overlay').classList.add('dm-open');
        $('dm-save-btn').disabled = false;

        // Focus first useful field for keyboard accessibility
        setTimeout(() => {
            const target = toPreload.length > 0
                ? $('dm-new-rows').querySelector('input[type=number]')
                : $('dm-new-rows').querySelector('select');
            target?.focus();
            if (target?.select) target.select();
        }, 80);
    },

    close() {
        $('dm-overlay').classList.remove('dm-open');
        _id = null;
    },

    addRow() {
        if (_activeCustomers.length === 0) {
            showNotice('Nenhum cliente ativo. Restaure os clientes padrao para continuar.', { type: 'info' });
            return;
        }
        const row = buildRow();
        $('dm-new-rows').appendChild(row);
        updateProgress();
        setTimeout(() => row.querySelector('select')?.focus(), 30);
    },

    restoreDefaultCustomers() {
        if (!_customerStateKey) return;
        const state = _customerStates.get(_customerStateKey);
        if (!state) return;
        state.activeIds = state.defaultIds.slice();
        state.excludedIds = [];
        _activeCustomers = DM_CUSTOMERS.filter(customer => state.activeIds.includes(String(customer.id)));
        $('dm-new-rows').innerHTML = '';
        $('dm-new-rows').appendChild(buildRow());
        updateProgress();
    },

    editExisting(distributionId) {
        const d = _existing.find(item => String(item.id) === String(distributionId));
        if (!d || !distributionIsEditable(d)) return;

        const row = document.getElementById('dmex-' + distributionId);
        if (!row) return;

        row.innerHTML = `
            <div class="dm-inline-edit">
                <select aria-label="Cliente da distribuicao">${customerOptions(d.customer_id)}</select>
                <input type="number" min="0.001" step="0.001" value="${d.qty}" aria-label="Quantidade da distribuicao">
                <span class="dm-inline-edit-actions">
                    <button type="button" class="dm-mini-btn save" title="Salvar alteracao" onclick="DistModal.saveExistingEdit(${d.id})">✓</button>
                    <button type="button" class="dm-mini-btn cancel" title="Cancelar edicao" onclick="DistModal.cancelExistingEdit()">×</button>
                </span>
            </div>
        `;
        setTimeout(() => row.querySelector('input')?.focus(), 40);
    },

    cancelExistingEdit() {
        renderExisting(_existing);
    },

    async saveExistingEdit(distributionId) {
        const row = document.getElementById('dmex-' + distributionId);
        if (!row) return;
        const customerId = row.querySelector('select')?.value;
        const quantity = parseFloat(row.querySelector('input')?.value || 0);
        if (!customerId || quantity <= 0) {
            showNotice('Informe cliente e quantidade validos.', { type: 'error' });
            return;
        }

        const existing = _existing.find(item => String(item.id) === String(distributionId));
        if (existing?.in_receipt && !confirm('Esta distribuicao ja esta em um comprovante. Ao editar, o comprovante sera marcado como obsoleto e precisara ser conferido. Deseja continuar?')) {
            renderExisting(_existing);
            return;
        }

        const saveBtn = row.querySelector('.dm-mini-btn.save');
        if (saveBtn) saveBtn.disabled = true;

        try {
            const res = await fetch(`/${DM_TENANT}/delivery/distributions/${distributionId}`, {
                method: 'PUT',
                headers: { 'X-CSRF-TOKEN': DM_CSRF, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ customer_id: parseInt(customerId), quantity }),
            });
            const data = await res.json();
            if (data.success) {
                _distQty = data.dist_total_qty;
                if (data.distribution) setExistingFromServer(data.distribution);
                updateProgress();
                if (typeof window._DistModalOnUpdate === 'function') {
                    window._DistModalOnUpdate(_id, data);
                }
            } else {
                this.handleError(data, 'Erro ao editar distribuicao.');
                renderExisting(_existing);
            }
        } catch (e) {
            showNotice(e.message, { type: 'error', title: 'Erro de comunicacao' });
            renderExisting(_existing);
        }
    },

    /** Delete an existing (already-saved) distribution */
    async deleteExisting(distributionId) {
        const d = _existing.find(item => String(item.id) === String(distributionId));
        if (!d || !distributionCanDelete(d)) return;
        if (d.in_receipt) {
            _pendingDangerDelete = d;
            $('dm-confirm-text').textContent = `Esta distribuicao esta no comprovante ${d.receipt_number || '#' + d.receipt_id}. Ao excluir, ela sera removida do comprovante e os totais serao recalculados.`;
            $('dm-confirm-answer').value = '';
            $('dm-confirm-overlay').classList.add('open');
            setTimeout(() => $('dm-confirm-answer')?.focus(), 60);
            return;
        }
        if (!confirm('Remover esta distribuicao?')) return;
        return this.performDelete(distributionId, {});
    },

    cancelDangerConfirm() {
        _pendingDangerDelete = null;
        $('dm-confirm-overlay').classList.remove('open');
    },

    async acceptDangerConfirm() {
        if (!_pendingDangerDelete) return;
        const answer = parseInt($('dm-confirm-answer').value || '', 10);
        if (answer !== 2) {
            $('dm-confirm-answer').focus();
            return;
        }
        const d = _pendingDangerDelete;
        this.cancelDangerConfirm();
        return this.performDelete(d.id, { impact_confirmed: true, math_answer: answer });
    },

    async performDelete(distributionId, payload = {}) {
        const saveBtn = $('dm-save-btn');
        saveBtn.disabled = true;
        try {
            const res  = await fetch(`/${DM_TENANT}/delivery/distributions/${distributionId}`, {
                method:  'DELETE',
                headers: { 'X-CSRF-TOKEN': DM_CSRF, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(payload),
            });
            const data = await res.json();
            if (data.success) {
                // Remove row from UI
                _existing = _existing.filter(item => String(item.id) !== String(distributionId));
                document.getElementById('dmex-' + distributionId)?.remove();
                syncOpenButtonsAfterDelete(_id, distributionId, data);
                // Update _distQty
                _distQty  = data.dist_total_qty;
                // Check if existing section is now empty
                if ($('dm-existing-list').children.length === 0) {
                    $('dm-existing-section').style.display = 'none';
                }
                updateProgress();
                // Notify parent page to refresh badge
                if (typeof window._DistModalOnDelete === 'function') {
                    window._DistModalOnDelete(_id, data);
                }
            } else {
                if (data.requires_confirmation) {
                    const d = _existing.find(item => String(item.id) === String(distributionId)) || { id: distributionId };
                    _pendingDangerDelete = d;
                    $('dm-confirm-text').textContent = data.message || 'Esta distribuicao exige confirmacao antes de excluir.';
                    $('dm-confirm-answer').value = '';
                    $('dm-confirm-overlay').classList.add('open');
                    setTimeout(() => $('dm-confirm-answer')?.focus(), 60);
                    return;
                }
                this.handleError(data, 'Erro ao remover.');
            }
        } catch (e) {
            showNotice(e.message, { type: 'error', title: 'Erro de comunicacao' });
        } finally {
            saveBtn.disabled = false;
        }
    },

    async save() {
        if (!_id) return;

        const rows = $('dm-new-rows').querySelectorAll('.dm-row');
        const distributions = [];
        for (const row of rows) {
            const sel = row.querySelector('select');
            const inp = row.querySelector('input');
            const cid = sel?.value;
            const qty = parseFloat(inp?.value || 0);
            // Skip completely empty rows
            if (!cid && qty <= 0) continue;
            // Customer selected but no qty — skip silently
            if (cid && qty <= 0) continue;
            // Qty informed but no customer — warn
            if (!cid && qty > 0) { showNotice('Selecione o cliente para a linha com quantidade informada.', { type: 'error' }); return; }
            distributions.push({ customer_id: parseInt(cid), quantity: qty });
        }
        if (!distributions.length) { showNotice('Adicione pelo menos uma distribuicao.', { type: 'info' }); return; }

        const newTotal = distributions.reduce((s, d) => s + d.quantity, 0);
        const avail    = _totalQty - _distQty;
        if (newTotal > avail + 0.0005) {
            showNotice('Total (' + fmt(newTotal, _unit) + ') excede disponivel (' + fmt(avail, _unit) + ').', { type: 'error', title: 'Quantidade excedida' });
            return;
        }

        const saveBtn = $('dm-save-btn');
        saveBtn.disabled  = true;
        saveBtn.textContent = 'Salvando…';

        try {
            const res  = await fetch(`/${DM_TENANT}/delivery/deliveries/${_id}/distribute`, {
                method:  'POST',
                headers: { 'X-CSRF-TOKEN': DM_CSRF, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body:    JSON.stringify({ distributions }),
            });
            const data = await res.json();
            if (data.success) {
                this.close();
                if (typeof window._DistModalReload === 'function') {
                    window._DistModalReload(data);
                } else {
                    location.reload();
                }
            } else {
                this.handleError(data, 'Erro ao distribuir.');
            }
        } catch (e) {
            showNotice(e.message, { type: 'error', title: 'Erro de comunicacao' });
        } finally {
            saveBtn.disabled    = false;
            saveBtn.textContent = '';
            saveBtn.innerHTML   =
                '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Salvar distribuições';
        }
    },

    handleError(data, fallback) {
        const message = data?.message || fallback;
        if (data?.code === 'missing_price' && data.can_configure_price) {
            showNotice(message, {
                title: 'Preco nao configurado',
                actionLabel: 'Configurar preco',
                onAction: () => this.openPriceEditor(data),
            });
            return;
        }
        showNotice(message, { type: 'error' });
    },

    closeNotice() {
        $('dm-notice-overlay').classList.remove('open');
        $('dm-price-form').classList.remove('visible');
        _noticeAction = null;
        _priceEditor = null;
    },

    openPriceEditor(pricing) {
        if (!pricing?.can_configure_price) return;
        _priceEditor = pricing;
        $('dm-notice-title').textContent = 'Configurar preco';
        $('dm-notice-message').textContent = `${pricing.product_name} para ${pricing.customer_name}${pricing.price_table_name ? ' - ' + pricing.price_table_name : ''}`;
        $('dm-price-form').classList.add('visible');
        $('dm-price-input').value = pricing.unit_price || '';
        const action = $('dm-notice-action');
        action.style.display = '';
        action.textContent = 'Salvar preco';
        action.onclick = () => this.saveQuickPrice();
        setTimeout(() => $('dm-price-input')?.focus(), 40);
    },

    async saveQuickPrice() {
        if (!_priceEditor || !_id) return;
        const salePrice = parseFloat($('dm-price-input').value || 0);
        if (salePrice <= 0) {
            $('dm-price-input').focus();
            return;
        }
        const action = $('dm-notice-action');
        action.disabled = true;
        try {
            const response = await fetch(`/${DM_TENANT}/delivery/deliveries/${_id}/customers/${_priceEditor.customer_id}/price`, {
                method: 'PUT',
                headers: { 'X-CSRF-TOKEN': DM_CSRF, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ sale_price: salePrice }),
            });
            const data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.message || Object.values(data.errors || {}).flat()[0] || 'Nao foi possivel salvar o preco.');
            }
            _priceCache.set(priceCacheKey(data.customer_id), data);
            $('dm-new-rows').querySelectorAll('.dm-row').forEach(row => {
                if (String(row.querySelector('select')?.value) === String(data.customer_id)) renderRowPrice(row, data);
            });
            this.closeNotice();
        } catch (error) {
            $('dm-notice-message').textContent = error.message;
            $('dm-notice-box').className = 'dm-notice-box error';
        } finally {
            action.disabled = false;
        }
    },
};

/* ── Backward-compat aliases ────────────────────────────────────────── */
window.openDistModal  = (btn) => DistModal.openFromBtn(btn);
window.closeDistModal = ()    => DistModal.close();

})();
</script>
