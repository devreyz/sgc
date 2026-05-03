{{--
    Componente: x-delivery.edit-delivery-modal
    Uso:
        <x-delivery.edit-delivery-modal
            :tenant-slug="$currentTenant->slug"
            :csrf="csrf_token()"
        />

    Expõe window.EditModal com:
        EditModal.openFromBtn(btn)   — lê data-* do botão
        EditModal.open(cfg)          — {id, date, qty, price, quality, notes, unit, distributions:[{id,customer,qty,net}]}
        EditModal.close()
        EditModal.onSaved            — callback: function(savedDelivery) { ... }  (opcional)
--}}
@props([
    'tenantSlug',
    'csrf',
])

<style>
/* ── Overlay ──────────────────────────────────────────────────────────── */
#em-overlay {
    position: fixed; inset: 0;
    background: rgba(0,0,0,.45);
    display: none; align-items: center; justify-content: center;
    padding: 1rem; z-index: 200001;
}
#em-overlay.em-open { display: flex; }

/* ── Box ──────────────────────────────────────────────────────────────── */
.em-box {
    background: var(--color-surface);
    border-radius: var(--radius-lg);
    width: min(560px, 96vw);
    max-height: 92vh;
    display: flex; flex-direction: column;
    box-shadow: 0 8px 40px rgba(0,0,0,.26);
    overflow: hidden;
}

/* ── Header ───────────────────────────────────────────────────────────── */
.em-head {
    padding: .85rem 1.2rem;
    border-bottom: 1px solid var(--color-border);
    display: flex; justify-content: space-between; align-items: center;
    flex-shrink: 0;
}
.em-title {
    font-size: .95rem; font-weight: 700;
    display: flex; align-items: center; gap: .45rem; margin: 0;
}
.em-close-btn {
    width: 28px; height: 28px; border: none; background: transparent;
    cursor: pointer; border-radius: var(--radius-md);
    display: flex; align-items: center; justify-content: center;
    color: var(--color-text-muted); font-size: 1.1rem;
}
.em-close-btn:hover { background: var(--color-border); }

/* ── Body ─────────────────────────────────────────────────────────────── */
.em-body {
    padding: 1rem 1.2rem;
    overflow-y: auto; flex: 1;
    display: flex; flex-direction: column; gap: .8rem;
}

/* ── Form fields ──────────────────────────────────────────────────────── */
.em-row { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; }
.em-group { display: flex; flex-direction: column; gap: .3rem; }
.em-label {
    font-size: .72rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: .04em;
    color: var(--color-text-secondary);
}
.em-input {
    padding: .45rem .7rem;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    font-size: .88rem;
    background: var(--color-bg); color: var(--color-text);
    width: 100%;
}
.em-input:focus {
    outline: none; border-color: var(--color-primary);
    box-shadow: 0 0 0 2px rgba(79,70,229,.15);
}
textarea.em-input { resize: vertical; min-height: 56px; }

/* ── Warning banner ───────────────────────────────────────────────────── */
#em-qty-warning {
    display: none; padding: .55rem .75rem;
    background: rgba(245,158,11,.12);
    border: 1px solid rgba(245,158,11,.35);
    border-radius: var(--radius-md);
    font-size: .8rem; color: #92400e;
    align-items: flex-start; gap: .4rem;
}
#em-qty-warning.visible { display: flex; }

/* ── Distribution preview ─────────────────────────────────────────────── */
.em-dist-section {
    border-top: 1px solid var(--color-border);
    padding-top: .7rem;
}
.em-dist-header {
    font-size: .72rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: .04em;
    color: var(--color-text-secondary); margin-bottom: .4rem;
    display: flex; align-items: center; justify-content: space-between;
}
.em-dist-list {
    background: color-mix(in srgb, var(--color-border) 35%, transparent);
    border-radius: var(--radius-md); overflow: hidden;
}
.em-dist-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: .3rem .65rem; gap: .5rem; font-size: .82rem;
    border-bottom: 1px solid color-mix(in srgb, var(--color-border) 50%, transparent);
}
.em-dist-row:last-child { border-bottom: none; }
.em-dist-customer { flex: 1; font-weight: 500; }
.em-dist-qty { color: var(--color-text-secondary); white-space: nowrap; }
.em-dist-net { color: var(--color-success); font-weight: 600; white-space: nowrap; min-width: 70px; text-align: right; }
.em-dist-total {
    font-size: .8rem; font-weight: 700;
    padding: .25rem .65rem;
    background: color-mix(in srgb, var(--color-border) 60%, transparent);
    display: flex; justify-content: space-between;
}

/* ── Footer ───────────────────────────────────────────────────────────── */
.em-foot {
    padding: .75rem 1.2rem;
    border-top: 1px solid var(--color-border);
    display: flex; justify-content: flex-end; gap: .5rem;
    flex-shrink: 0;
}
</style>

<div id="em-overlay" onclick="if(event.target===this)EditModal.close()">
    <div class="em-box">

        {{-- Header --}}
        <div class="em-head">
            <h3 class="em-title">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                     fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                </svg>
                Editar Entrega
            </h3>
            <button class="em-close-btn" onclick="EditModal.close()">✕</button>
        </div>

        {{-- Body --}}
        <div class="em-body">

            <input type="hidden" id="em-id">
            <input type="hidden" id="em-unit">

            {{-- Date + Qty --}}
            <div class="em-row">
                <div class="em-group">
                    <label class="em-label">Data da Entrega *</label>
                    <input type="date" id="em-date" class="em-input" required>
                </div>
                <div class="em-group">
                    <label class="em-label">Quantidade * <span id="em-unit-lbl" style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--color-text-muted)"></span></label>
                    <input type="number" id="em-qty" class="em-input" step="0.001" min="0.001" required>
                </div>
            </div>

            {{-- Price + Quality --}}
            <div class="em-row">
                <div class="em-group">
                    <label class="em-label">Preço Unitário</label>
                    <input type="number" id="em-price" class="em-input" step="0.01" min="0" placeholder="0,00">
                </div>
                <div class="em-group">
                    <label class="em-label">Classificação</label>
                    <input type="text" id="em-quality" class="em-input" maxlength="50" placeholder="A, B, C…">
                </div>
            </div>

            {{-- Notes --}}
            <div class="em-group">
                <label class="em-label">Observações</label>
                <textarea id="em-notes" class="em-input" rows="2" maxlength="1000" placeholder="Observações opcionais…"></textarea>
            </div>

            {{-- Warning: qty reduces below distributed --}}
            <div id="em-qty-warning">
                ⚠ <span id="em-qty-warning-msg"></span>
            </div>

            {{-- Distributions preview --}}
            <div class="em-dist-section" id="em-dist-section" style="display:none">
                <div class="em-dist-header">
                    <span>Distribuições desta recepção</span>
                    <span id="em-dist-count" style="font-weight:400;text-transform:none;letter-spacing:0"></span>
                </div>
                <div class="em-dist-list" id="em-dist-list"></div>
                <div class="em-dist-total" id="em-dist-total"></div>
            </div>

        </div>

        {{-- Footer --}}
        <div class="em-foot">
            <button class="btn btn-ghost btn-sm" onclick="EditModal.close()">Cancelar</button>
            <button class="btn btn-primary btn-sm" id="em-save-btn" onclick="EditModal.save()">
                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                    <polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>
                </svg>
                Salvar
            </button>
        </div>
    </div>
</div>

<script>
(function () {
'use strict';

const EM_TENANT = @json($tenantSlug);
const EM_CSRF   = @json($csrf);

let _cfg = null;  // current config

function $ (id) { return document.getElementById(id); }
function esc(s) {
    return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function fmtQty(n, unit) {
    return parseFloat(n).toLocaleString('pt-BR',{minimumFractionDigits:3,maximumFractionDigits:3}) + ' ' + (unit||'un');
}
function fmtR(n) {
    return 'R$ ' + parseFloat(n).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});
}

function renderDistributions(dists, unit) {
    const section = $('em-dist-section');
    if (!dists || dists.length === 0) { section.style.display = 'none'; return; }
    section.style.display = '';

    $('em-dist-count').textContent = dists.length + ' distribuição(ões)';

    $('em-dist-list').innerHTML = dists.map(d => `
        <div class="em-dist-row">
            <span class="em-dist-customer">${esc(d.customer)}</span>
            <span class="em-dist-qty">${fmtQty(d.qty, unit)}</span>
            <span class="em-dist-net">${d.net > 0 ? fmtR(d.net) : ''}</span>
        </div>
    `).join('');

    const totalQty = dists.reduce((s, d) => s + parseFloat(d.qty), 0);
    const totalNet = dists.reduce((s, d) => s + parseFloat(d.net || 0), 0);
    $('em-dist-total').innerHTML =
        '<span>Total distribuído</span>' +
        '<span>' + fmtQty(totalQty, unit) +
        (totalNet > 0 ? ' &nbsp;·&nbsp; <span style="color:var(--color-success)">' + fmtR(totalNet) + '</span>' : '') +
        '</span>';
}

function checkQtyWarning() {
    if (!_cfg) return;
    const newQty = parseFloat($('em-qty').value || 0);
    const dists  = _cfg.distributions || [];
    const distTotal = dists.reduce((s, d) => s + parseFloat(d.qty), 0);

    if (distTotal > 0 && newQty < distTotal - 0.0005) {
        $('em-qty-warning-msg').textContent =
            'A nova quantidade (' + fmtQty(newQty, _cfg.unit) +
            ') é menor que o total já distribuído (' + fmtQty(distTotal, _cfg.unit) +
            '). As distribuições serão mantidas mas ficarão inconsistentes.';
        $('em-qty-warning').classList.add('visible');
    } else {
        $('em-qty-warning').classList.remove('visible');
    }
}

window.EditModal = {

    openFromBtn(btn) {
        let dists = [];
        try { dists = JSON.parse(btn.dataset.distributions || '[]'); } catch {}
        this.open({
            id:            btn.dataset.id,
            date:          btn.dataset.date    || '',
            qty:           btn.dataset.qty     || '',
            price:         btn.dataset.price   || '',
            quality:       btn.dataset.quality || '',
            notes:         btn.dataset.notes   || '',
            unit:          btn.dataset.unit    || 'un',
            distributions: dists,
        });
    },

    open(cfg) {
        _cfg = cfg;
        $('em-id').value      = cfg.id      || '';
        $('em-date').value    = cfg.date    || '';
        $('em-qty').value     = cfg.qty     || '';
        $('em-price').value   = cfg.price   || '';
        $('em-quality').value = cfg.quality || '';
        $('em-notes').value   = cfg.notes   || '';
        $('em-unit').value    = cfg.unit    || 'un';
        $('em-unit-lbl').textContent = cfg.unit ? '(' + cfg.unit + ')' : '';

        $('em-qty-warning').classList.remove('visible');
        renderDistributions(cfg.distributions || [], cfg.unit || 'un');

        $('em-overlay').classList.add('em-open');
        $('em-save-btn').disabled = false;
        setTimeout(() => $('em-qty')?.focus(), 80);
    },

    close() {
        $('em-overlay').classList.remove('em-open');
        _cfg = null;
    },

    async save() {
        const id    = $('em-id').value;
        const date  = $('em-date').value;
        const qty   = $('em-qty').value;
        const price = $('em-price').value;
        if (!date || !qty) { alert('Preencha a data e a quantidade.'); return; }

        const saveBtn = $('em-save-btn');
        saveBtn.disabled = true;

        try {
            const res  = await fetch(`/${EM_TENANT}/delivery/deliveries/${id}`, {
                method:  'PUT',
                headers: { 'X-CSRF-TOKEN': EM_CSRF, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body:    JSON.stringify({
                    delivery_date: date,
                    quantity:      parseFloat(qty),
                    unit_price:    price ? parseFloat(price) : null,
                    quality_grade: $('em-quality').value || null,
                    notes:         $('em-notes').value   || null,
                }),
            });
            const data = await res.json();
            if (data.success) {
                this.close();
                if (typeof EditModal.onSaved === 'function') {
                    EditModal.onSaved(data.delivery);
                } else {
                    location.reload();
                }
            } else {
                alert(data.message || 'Erro ao salvar.');
            }
        } catch (e) {
            alert('Erro: ' + e.message);
        } finally {
            saveBtn.disabled = false;
        }
    },

    onSaved: null,  // override per-page: EditModal.onSaved = function(d){ ... }
};

/* qty input → live warning */
document.getElementById('em-qty')?.addEventListener('input', checkQtyWarning);

/* Backward-compat shim for pages that call openEditModal(btn) */
window.openEditModal  = (btn) => EditModal.openFromBtn(btn);
window.closeEditModal = ()    => EditModal.close();

})();
</script>
