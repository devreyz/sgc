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


<style>
/* Refinamento visual SGC — mantém HTML e JS funcionais intactos */
#em-overlay{--em-blue:#2563eb;--em-blue-soft:#eef4ff;--em-violet:#7c3aed;--em-violet-soft:#f4f0ff;--em-green:#168a4d;--em-green-soft:#eaf8ef;--em-amber:#c87408;--em-amber-soft:#fff7e8;--em-red:#cf3f3f;--em-red-soft:#fff0f0;--em-slate:#64748b;--em-slate-soft:#f1f5f9;--em-border:var(--color-border,#dce7e0);--em-text:var(--color-text,#102018);--em-text-2:var(--color-text-secondary,#52645a);--em-text-3:var(--color-text-muted,#809087);background:rgba(15,23,42,.46);backdrop-filter:blur(4px);padding:max(1rem,env(safe-area-inset-top)) max(1rem,env(safe-area-inset-right)) max(1rem,env(safe-area-inset-bottom)) max(1rem,env(safe-area-inset-left))}
#em-overlay *,#em-overlay *::before,#em-overlay *::after{box-sizing:border-box}
.em-box{width:min(620px,100%);max-height:min(92dvh,820px);border:1px solid var(--em-border);border-radius:15px;background:var(--color-surface,#fff);box-shadow:0 24px 68px rgba(15,23,42,.22)}
.em-head{min-height:64px;padding:.68rem .76rem;gap:.65rem;background:radial-gradient(circle at 100% 0,rgba(37,99,235,.09),transparent 15rem),linear-gradient(180deg,var(--color-bg,#f8faf9),#fff)}
.em-title{gap:.42rem;font-size:.92rem;font-weight:850;letter-spacing:-.025em;color:var(--em-text)}
.em-title>svg{display:block;width:17px;height:17px;flex:0 0 auto;margin:0}
.em-close-btn{width:36px;height:36px;border:1px solid var(--em-border);border-radius:9px;background:#fff;color:var(--em-text-2);font-size:.95rem;line-height:0}
.em-close-btn:hover,.em-close-btn:focus-visible{background:var(--em-blue-soft);border-color:rgba(37,99,235,.18);color:var(--em-blue);outline:none}
.em-body{gap:.68rem;padding:.72rem .76rem .8rem;overscroll-behavior:contain;scrollbar-width:thin}
.em-row{gap:.52rem}.em-group{gap:.26rem}
.em-label{font-size:.64rem;font-weight:780;letter-spacing:0;text-transform:none;color:var(--em-text-2)}
.em-label span{color:var(--em-text-3)!important;font-size:.61rem!important;font-weight:680!important}
.em-input{min-height:42px;padding:.48rem .56rem;border:1px solid color-mix(in srgb,var(--em-border) 78%,#9cab9f);border-radius:9px;background:#fff;font-size:.75rem;color:var(--em-text);transition:border-color .14s,box-shadow .14s,background .14s}
.em-input:hover{border-color:color-mix(in srgb,var(--em-blue) 18%,var(--em-border))}.em-input:focus{border-color:var(--em-blue);box-shadow:0 0 0 3px rgba(37,99,235,.07);background:#fff}
#em-date,#em-qty{background:linear-gradient(180deg,#fff,color-mix(in srgb,var(--em-blue-soft) 22%,#fff))}
#em-price,#em-quality{background:linear-gradient(180deg,#fff,color-mix(in srgb,var(--em-slate-soft) 20%,#fff))}
textarea.em-input{min-height:72px;max-height:170px;line-height:1.45}
#em-qty-warning{padding:.5rem .56rem;gap:.4rem;border:1px solid rgba(200,116,8,.17);border-radius:9px;background:var(--em-amber-soft);color:#92400e;font-size:.67rem;font-weight:680;line-height:1.42}
.em-dist-section{padding-top:.66rem;margin-top:.02rem}.em-dist-header{margin-bottom:.34rem;font-size:.64rem;font-weight:790;letter-spacing:0;text-transform:none;color:var(--em-text-2)}
#em-dist-count{display:inline-flex;min-height:23px;align-items:center;padding:.12rem .34rem;border-radius:999px;background:var(--em-violet-soft);color:var(--em-violet)!important;font-size:.58rem!important;font-weight:780!important}
.em-dist-list{border:1px solid var(--em-border);border-radius:10px 10px 0 0;background:#fff}
.em-dist-row{display:grid;grid-template-columns:minmax(0,1fr) auto auto;gap:.48rem;align-items:center;min-height:44px;padding:.4rem .48rem;font-size:.69rem;background:#fff}
.em-dist-row+.em-dist-row{border-top:1px solid var(--em-border)}.em-dist-row:hover{background:linear-gradient(90deg,color-mix(in srgb,var(--em-violet-soft) 28%,#fff),#fff 48%)}
.em-dist-customer{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.7rem;font-weight:770;color:var(--em-text)}
.em-dist-qty{font-size:.66rem;font-weight:720;color:var(--em-text-2)}.em-dist-net{min-width:72px;font-size:.67rem;font-weight:820;color:var(--em-green)}
.em-dist-total{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:.5rem;align-items:center;min-height:42px;padding:.4rem .48rem;border:1px solid var(--em-border);border-top:0;border-radius:0 0 10px 10px;background:linear-gradient(180deg,var(--color-bg,#f8faf9),#fff);font-size:.67rem;font-weight:740;color:var(--em-text-2)}
.em-dist-total>span:last-child{font-weight:800;color:var(--em-text);text-align:right}
.em-foot{gap:.4rem;padding:.62rem .76rem max(.62rem,env(safe-area-inset-bottom));background:#fff}
#em-overlay .em-foot .btn{display:inline-flex;min-height:39px;align-items:center;justify-content:center;gap:.3rem;padding:.42rem .6rem;border:1px solid var(--em-border);border-radius:9px;background:#fff;color:var(--em-text-2);font-size:.68rem;font-weight:790;white-space:nowrap}
#em-overlay .em-foot .btn-primary{border-color:rgba(37,99,235,.16);background:var(--em-blue-soft);color:var(--em-blue)}
#em-overlay .em-foot .btn-ghost:hover{background:var(--em-slate-soft);color:var(--em-slate)}#em-overlay .em-foot .btn>svg{display:block;width:14px;height:14px;margin:0}
@media(max-width:680px){#em-overlay{align-items:flex-end;padding:0;background:rgba(15,23,42,.42)}.em-box{position:relative;width:100%;max-height:94dvh;border-left:0;border-right:0;border-bottom:0;border-radius:17px 17px 0 0;box-shadow:0 -16px 44px rgba(15,23,42,.22)}.em-box::before{content:"";position:absolute;z-index:4;top:6px;left:50%;width:38px;height:4px;border-radius:999px;background:rgba(100,116,139,.30);transform:translateX(-50%)}.em-head{min-height:60px;padding:.8rem .62rem .54rem}.em-title{font-size:.86rem}.em-close-btn{width:34px;height:34px}.em-body{gap:.58rem;padding:.58rem .62rem .68rem}.em-row{gap:.4rem}.em-input{min-height:43px;padding-left:.46rem;padding-right:.46rem;font-size:.72rem}textarea.em-input{min-height:64px}.em-dist-row{grid-template-columns:minmax(0,1fr) auto;gap:.18rem .42rem;min-height:48px;padding:.42rem .46rem}.em-dist-customer{grid-column:1;grid-row:1}.em-dist-qty{grid-column:1;grid-row:2}.em-dist-net{grid-column:2;grid-row:1/span 2;align-self:center}.em-foot{padding-left:.62rem;padding-right:.62rem}#em-overlay .em-foot .btn{min-height:42px}#em-overlay .em-foot .btn-ghost{width:42px;min-width:42px;padding:0;overflow:hidden;font-size:0}#em-overlay .em-foot .btn-ghost::before{content:"×";font-size:1rem;line-height:1}#em-overlay .em-foot .btn-primary{flex:1 1 auto}}
@media(max-width:390px){.em-row{grid-template-columns:1fr}.em-dist-total{grid-template-columns:1fr;gap:.14rem}.em-dist-total>span:last-child{text-align:left}}
@media(prefers-reduced-motion:reduce){#em-overlay *,#em-overlay *::before,#em-overlay *::after{transition-duration:.01ms!important;animation-duration:.01ms!important}}
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
</script>{{--
    Componente: x-delivery.edit-delivery-modal

    Mantém a API pública original:
        EditModal.openFromBtn(btn)
        EditModal.open(cfg)
        EditModal.close()
        EditModal.onSaved

    Contrato de open(cfg):
        {id, date, qty, price, quality, notes, unit, distributions:[{id,customer,qty,net}]}
--}}

@props([
    'tenantSlug',
    'csrf',
])

<style>
/* ==========================================================================
   EDIT DELIVERY MODAL — tema SGC / Project Deliveries
   ========================================================================== */

#em-overlay,
#em-notice-overlay {
    --em-green:#168a4d;
    --em-green-soft:#eaf8ef;
    --em-blue:#2563eb;
    --em-blue-soft:#eef4ff;
    --em-sky:#0284c7;
    --em-sky-soft:#edf8fe;
    --em-violet:#7c3aed;
    --em-violet-soft:#f4f0ff;
    --em-amber:#c87408;
    --em-amber-soft:#fff7e8;
    --em-red:#cf3f3f;
    --em-red-soft:#fff0f0;
    --em-slate:#64748b;
    --em-slate-soft:#f1f5f9;

    --em-surface:var(--color-surface,#fff);
    --em-soft:var(--color-surface-soft,#f8faf9);
    --em-border:var(--color-border,#dce7e0);
    --em-border-strong:var(--color-border-strong,#c8d6cd);
    --em-text:var(--color-text,#102018);
    --em-text-2:var(--color-text-secondary,#52645a);
    --em-text-3:var(--color-text-muted,#809087);
    --em-shadow:0 26px 70px rgba(8,24,15,.24);
}

#em-overlay *,
#em-overlay *::before,
#em-overlay *::after,
#em-notice-overlay *,
#em-notice-overlay *::before,
#em-notice-overlay *::after {
    box-sizing:border-box;
}

html.em-page-locked,
body.em-page-locked {
    overflow:hidden !important;
    overscroll-behavior-y:none !important;
}

/* ---------- Overlay ---------- */

#em-overlay,
#em-notice-overlay {
    position:fixed;
    inset:0;
    display:none;
    align-items:center;
    justify-content:center;
    padding:
        max(.8rem,env(safe-area-inset-top))
        max(.8rem,env(safe-area-inset-right))
        max(.8rem,env(safe-area-inset-bottom))
        max(.8rem,env(safe-area-inset-left));
    background:rgba(8,24,15,.52);
    backdrop-filter:blur(6px);
}

#em-overlay {
    z-index:200001;
}

#em-notice-overlay {
    z-index:210001;
}

#em-overlay.em-open,
#em-notice-overlay.open {
    display:flex;
    animation:em-overlay-in .17s ease both;
}

/* ---------- Panel ---------- */

.em-box {
    position:relative;
    display:flex;
    width:min(650px,100%);
    max-height:min(var(--em-vv-height,92dvh),850px);
    min-height:0;
    flex-direction:column;
    overflow:hidden;
    border:1px solid var(--em-border);
    border-radius:16px;
    background:#fff;
    color:var(--em-text);
    box-shadow:var(--em-shadow);
    animation:em-panel-in .24s cubic-bezier(.22,.78,.24,1) both;
}

/* ---------- Header ---------- */

.em-head {
    display:grid;
    flex:0 0 auto;
    min-width:0;
    min-height:70px;
    grid-template-columns:minmax(0,1fr) auto;
    gap:.58rem;
    align-items:center;
    padding:.62rem .68rem;
    border-bottom:1px solid var(--em-border);
    background:
        radial-gradient(circle at 100% 0,rgba(37,99,235,.10),transparent 17rem),
        linear-gradient(180deg,var(--em-soft),#fff);
}

.em-head-main {
    display:grid;
    min-width:0;
    grid-template-columns:auto minmax(0,1fr);
    gap:.5rem;
    align-items:center;
}

.em-title-icon {
    display:inline-flex;
    width:40px;
    height:40px;
    align-items:center;
    justify-content:center;
    border-radius:11px;
    background:var(--em-blue-soft);
    color:var(--em-blue);
}

.em-title-icon .ph-duotone {
    font-size:19px !important;
}

.em-title-copy {
    min-width:0;
}

.em-title {
    margin:0;
    color:var(--em-text);
    font-size:.9rem;
    font-weight:870;
    letter-spacing:-.025em;
    line-height:1.25;
}

.em-subtitle {
    margin-top:.04rem;
    overflow:hidden;
    color:var(--em-text-3);
    font-size:.62rem;
    line-height:1.35;
    text-overflow:ellipsis;
    white-space:nowrap;
}

.em-close-btn {
    display:inline-flex;
    width:35px;
    height:35px;
    align-items:center;
    justify-content:center;
    border:1px solid var(--em-border);
    border-radius:9px;
    background:#fff;
    color:var(--em-text-2);
    cursor:pointer;
    font:inherit;
    transition:.14s ease;
}

.em-close-btn:hover,
.em-close-btn:focus-visible {
    border-color:rgba(37,99,235,.18);
    background:var(--em-blue-soft);
    color:var(--em-blue);
    outline:none;
}

.em-close-btn .ph-duotone {
    font-size:16px !important;
}

/* ---------- Body ---------- */

.em-body {
    display:grid;
    min-height:0;
    flex:1 1 auto;
    gap:.58rem;
    padding:.64rem .68rem .72rem;
    overflow-y:auto;
    overscroll-behavior:contain;
    -webkit-overflow-scrolling:touch;
    background:var(--em-soft);
    scrollbar-width:none;
}

.em-body::-webkit-scrollbar {
    width:0;
    height:0;
}

.em-form-card {
    display:grid;
    gap:.58rem;
    padding:.55rem;
    border:1px solid var(--em-border);
    border-radius:12px;
    background:#fff;
}

.em-row {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:.48rem;
}

.em-group {
    display:grid;
    min-width:0;
    gap:.24rem;
}

.em-label {
    display:flex;
    min-width:0;
    align-items:center;
    justify-content:space-between;
    gap:.35rem;
    color:var(--em-text-2);
    font-size:.61rem;
    font-weight:780;
    line-height:1.3;
}

.em-label-note {
    color:var(--em-text-3);
    font-size:.56rem;
    font-weight:680;
}

.em-input-wrap {
    position:relative;
    min-width:0;
}

.em-input-icon {
    position:absolute;
    top:50%;
    left:.58rem;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    color:var(--em-text-3);
    pointer-events:none;
    transform:translateY(-50%);
}

.em-input-icon .ph-duotone {
    font-size:14px !important;
}

.em-input {
    width:100%;
    min-width:0;
    min-height:43px;
    padding:.46rem .54rem;
    border:1px solid var(--em-border-strong);
    border-radius:9px;
    outline:none;
    background:#fff;
    color:var(--em-text);
    font:inherit;
    font-size:.72rem;
    transition:
        border-color .14s ease,
        box-shadow .14s ease,
        background .14s ease;
}

.em-input.has-icon {
    padding-left:1.82rem;
}

.em-input:hover {
    border-color:color-mix(in srgb,var(--em-blue) 18%,var(--em-border));
}

.em-input:focus {
    border-color:var(--em-blue);
    background:#fff;
    box-shadow:0 0 0 3px rgba(37,99,235,.07);
}

#em-date,
#em-qty {
    background:
        linear-gradient(180deg,#fff,color-mix(in srgb,var(--em-blue-soft) 22%,#fff));
}

#em-price {
    background:
        linear-gradient(180deg,#fff,color-mix(in srgb,var(--em-green-soft) 24%,#fff));
}

#em-quality {
    background:
        linear-gradient(180deg,#fff,color-mix(in srgb,var(--em-violet-soft) 24%,#fff));
}

#em-qty,
#em-price {
    font-size:.82rem;
    font-weight:860;
    font-variant-numeric:tabular-nums;
}

textarea.em-input {
    min-height:76px;
    max-height:180px;
    padding-top:.52rem;
    line-height:1.45;
    resize:vertical;
}

/* ---------- Warning ---------- */

#em-qty-warning {
    display:none;
    grid-template-columns:auto minmax(0,1fr);
    gap:.38rem;
    align-items:start;
    padding:.48rem .52rem;
    border:1px solid rgba(200,116,8,.16);
    border-radius:9px;
    background:var(--em-amber-soft);
    color:#92400e;
    font-size:.65rem;
    font-weight:680;
    line-height:1.42;
}

#em-qty-warning.visible {
    display:grid;
}

.em-warning-icon {
    display:inline-flex;
    width:28px;
    height:28px;
    align-items:center;
    justify-content:center;
    border-radius:8px;
    background:#fff;
    color:var(--em-amber);
}

.em-warning-icon .ph-duotone {
    font-size:14px !important;
}

/* ---------- Distribution preview ---------- */

.em-dist-section {
    display:grid;
    gap:.38rem;
    padding:.5rem;
    border:1px solid var(--em-border);
    border-radius:12px;
    background:#fff;
}

.em-dist-header {
    display:grid;
    min-width:0;
    grid-template-columns:minmax(0,1fr) auto;
    gap:.4rem;
    align-items:center;
}

.em-dist-title-wrap {
    display:flex;
    min-width:0;
    align-items:center;
    gap:.34rem;
}

.em-dist-icon {
    display:inline-flex;
    width:30px;
    height:30px;
    flex:0 0 30px;
    align-items:center;
    justify-content:center;
    border-radius:8px;
    background:var(--em-violet-soft);
    color:var(--em-violet);
}

.em-dist-icon .ph-duotone {
    font-size:14px !important;
}

.em-dist-header strong {
    min-width:0;
    overflow:hidden;
    color:var(--em-text);
    font-size:.68rem;
    font-weight:830;
    text-overflow:ellipsis;
    white-space:nowrap;
}

#em-dist-count {
    display:inline-flex;
    min-height:23px;
    align-items:center;
    padding:.1rem .32rem;
    border-radius:999px;
    background:var(--em-violet-soft);
    color:var(--em-violet);
    font-size:.55rem;
    font-weight:800;
    white-space:nowrap;
}

.em-dist-summary {
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:.3rem;
}

.em-dist-metric {
    min-width:0;
    padding:.36rem .4rem;
    border-radius:9px;
    background:var(--em-soft);
}

.em-dist-metric span {
    display:block;
    color:var(--em-text-3);
    font-size:.52rem;
    font-weight:720;
}

.em-dist-metric strong {
    display:block;
    margin-top:.04rem;
    overflow:hidden;
    color:var(--em-text);
    font-size:.67rem;
    font-weight:860;
    font-variant-numeric:tabular-nums;
    text-overflow:ellipsis;
    white-space:nowrap;
}

.em-dist-metric.value strong {
    color:var(--em-green);
}

.em-dist-list {
    display:grid;
    gap:.3rem;
}

.em-dist-row {
    display:grid;
    min-width:0;
    grid-template-columns:auto minmax(0,1fr) auto;
    gap:.4rem;
    align-items:center;
    min-height:50px;
    padding:.4rem .44rem;
    border:1px solid var(--em-border);
    border-left:3px solid var(--em-violet);
    border-radius:9px;
    background:
        linear-gradient(90deg,var(--em-violet-soft),#fff 54%);
}

.em-dist-row-icon {
    display:inline-flex;
    width:30px;
    height:30px;
    align-items:center;
    justify-content:center;
    border-radius:8px;
    background:#fff;
    color:var(--em-violet);
}

.em-dist-row-icon .ph-duotone {
    font-size:14px !important;
}

.em-dist-row-main {
    min-width:0;
}

.em-dist-customer {
    display:block;
    overflow:hidden;
    color:var(--em-text);
    font-size:.68rem;
    font-weight:800;
    text-overflow:ellipsis;
    white-space:nowrap;
}

.em-dist-row-main small {
    display:block;
    margin-top:.03rem;
    color:var(--em-text-3);
    font-size:.54rem;
}

.em-dist-values {
    text-align:right;
}

.em-dist-qty {
    display:block;
    color:var(--em-text);
    font-size:.65rem;
    font-weight:850;
    font-variant-numeric:tabular-nums;
    white-space:nowrap;
}

.em-dist-net {
    display:block;
    margin-top:.03rem;
    color:var(--em-green);
    font-size:.56rem;
    font-weight:760;
    font-variant-numeric:tabular-nums;
    white-space:nowrap;
}

/* ---------- Footer ---------- */

.em-foot {
    display:flex;
    flex:0 0 auto;
    gap:.38rem;
    align-items:center;
    justify-content:flex-end;
    padding:.52rem .68rem max(.52rem,env(safe-area-inset-bottom));
    border-top:1px solid var(--em-border);
    background:
        linear-gradient(180deg,#fff,var(--em-soft));
}

#em-overlay .em-foot .btn {
    display:inline-flex;
    min-height:41px;
    align-items:center;
    justify-content:center;
    gap:.28rem;
    padding:.42rem .6rem;
    border:1px solid var(--em-border);
    border-radius:9px;
    background:#fff;
    color:var(--em-text-2);
    cursor:pointer;
    font:inherit;
    font-size:.67rem;
    font-weight:790;
    white-space:nowrap;
}

#em-overlay .em-foot .btn:hover:not(:disabled),
#em-overlay .em-foot .btn:focus-visible:not(:disabled) {
    outline:none;
    transform:translateY(-1px);
}

#em-overlay .em-foot .btn-ghost:hover,
#em-overlay .em-foot .btn-ghost:focus-visible {
    background:var(--em-slate-soft);
    color:var(--em-slate);
}

#em-overlay .em-foot .btn-primary {
    border-color:rgba(37,99,235,.16);
    background:var(--em-blue-soft);
    color:var(--em-blue);
}

#em-overlay .em-foot .btn:disabled {
    cursor:wait;
    opacity:.48;
    transform:none;
}

#em-overlay .em-foot .ph-duotone {
    font-size:14px !important;
}

/* ---------- Notice / validation ---------- */

.em-notice-box {
    display:flex;
    width:min(430px,100%);
    min-height:0;
    flex-direction:column;
    overflow:hidden;
    border:1px solid var(--em-border);
    border-radius:15px;
    background:#fff;
    box-shadow:var(--em-shadow);
    animation:em-panel-in .22s cubic-bezier(.22,.78,.24,1) both;
}

.em-notice-head {
    display:grid;
    grid-template-columns:auto minmax(0,1fr);
    gap:.5rem;
    align-items:start;
    padding:.68rem;
}

.em-notice-icon {
    display:inline-flex;
    width:38px;
    height:38px;
    align-items:center;
    justify-content:center;
    border-radius:10px;
    background:var(--em-amber-soft);
    color:var(--em-amber);
}

.em-notice-box.error .em-notice-icon {
    background:var(--em-red-soft);
    color:var(--em-red);
}

.em-notice-box.info .em-notice-icon {
    background:var(--em-blue-soft);
    color:var(--em-blue);
}

.em-notice-icon .ph-duotone {
    font-size:18px !important;
}

.em-notice-copy {
    min-width:0;
}

.em-notice-title {
    margin:0;
    color:var(--em-text);
    font-size:.8rem;
    font-weight:850;
}

.em-notice-message {
    margin:.12rem 0 0;
    color:var(--em-text-2);
    font-size:.67rem;
    line-height:1.48;
}

.em-notice-actions {
    display:flex;
    justify-content:flex-end;
    padding:.52rem .68rem max(.52rem,env(safe-area-inset-bottom));
    border-top:1px solid var(--em-border);
    background:var(--em-soft);
}

.em-notice-actions button {
    min-height:41px;
    padding:.42rem .7rem;
    border:1px solid var(--em-border);
    border-radius:9px;
    background:#fff;
    color:var(--em-text-2);
    cursor:pointer;
    font:inherit;
    font-size:.67rem;
    font-weight:790;
}

/* ---------- Phosphor ---------- */

#em-overlay .ph-duotone,
#em-notice-overlay .ph-duotone {
    font-family:"Phosphor-Duotone" !important;
    font-style:normal !important;
    font-weight:normal !important;
    line-height:1 !important;
}

/* ---------- Animation ---------- */

@keyframes em-overlay-in {
    from { opacity:0; }
    to { opacity:1; }
}

@keyframes em-panel-in {
    from {
        opacity:.74;
        transform:translateY(18px) scale(.994);
    }
    to {
        opacity:1;
        transform:translateY(0) scale(1);
    }
}

/* ---------- Mobile / tablet ---------- */

@media(max-width:767px) {
    #em-overlay,
    #em-notice-overlay {
        align-items:flex-end;
        padding:0;
    }

    .em-box,
    .em-notice-box {
        width:100%;
        max-width:none;
        margin:0;
        border-right:0;
        border-bottom:0;
        border-left:0;
        border-radius:18px 18px 0 0;
    }

    .em-box {
        max-height:min(var(--em-vv-height,96dvh),96dvh);
        box-shadow:0 -18px 48px rgba(8,24,15,.22);
    }

    .em-box::before {
        position:absolute;
        z-index:4;
        top:6px;
        left:50%;
        width:38px;
        height:4px;
        border-radius:999px;
        background:rgba(100,116,139,.28);
        content:"";
        transform:translateX(-50%);
    }

    .em-head {
        min-height:68px;
        padding:.78rem .56rem .52rem;
    }

    .em-title-icon {
        width:37px;
        height:37px;
    }

    .em-title {
        font-size:.84rem;
    }

    .em-subtitle {
        font-size:.59rem;
    }

    .em-body {
        gap:.52rem;
        padding:.54rem .56rem .64rem;
    }

    .em-form-card,
    .em-dist-section {
        padding:.44rem;
        border-radius:11px;
    }

    .em-row {
        gap:.38rem;
    }

    .em-input {
        min-height:44px;
        font-size:.7rem;
    }

    #em-qty,
    #em-price {
        font-size:.8rem;
    }

    textarea.em-input {
        min-height:70px;
    }

    .em-dist-row {
        min-height:48px;
        padding:.4rem;
    }

    .em-foot {
        min-height:60px;
        padding:.5rem .56rem max(.5rem,env(safe-area-inset-bottom));
    }

    #em-overlay .em-foot .btn {
        min-height:44px;
    }

    #em-overlay .em-foot .btn-ghost {
        width:44px;
        min-width:44px;
        padding:0;
        font-size:0;
    }

    #em-overlay .em-foot .btn-ghost span {
        display:none;
    }

    #em-overlay .em-foot .btn-ghost .ph-duotone {
        font-size:16px !important;
    }

    #em-overlay .em-foot .btn-primary {
        flex:1 1 auto;
    }

    .em-notice-box {
        max-height:min(var(--em-vv-height,88dvh),88dvh);
    }

    .em-notice-actions button {
        width:100%;
        min-height:45px;
    }
}

@media(max-width:440px) {
    .em-row {
        grid-template-columns:1fr;
    }

    .em-dist-summary {
        grid-template-columns:1fr 1fr;
    }

    .em-dist-metric.value {
        grid-column:1 / -1;
    }
}

@media(prefers-reduced-motion:reduce) {
    #em-overlay *,
    #em-overlay *::before,
    #em-overlay *::after,
    #em-notice-overlay *,
    #em-notice-overlay *::before,
    #em-notice-overlay *::after {
        animation-duration:.01ms !important;
        animation-iteration-count:1 !important;
        transition-duration:.01ms !important;
    }
}
</style>

<div id="em-overlay" aria-hidden="true">
    <div class="em-box" role="dialog" aria-modal="true" aria-labelledby="em-title">

        <div class="em-head">
            <div class="em-head-main">
                <span class="em-title-icon" aria-hidden="true">
                    <i class="ph-duotone ph-pencil-simple"></i>
                </span>

                <div class="em-title-copy">
                    <h3 class="em-title" id="em-title">Editar entrega</h3>
                    <div class="em-subtitle" id="em-subtitle">Ajuste os dados do registro</div>
                </div>
            </div>

            <button type="button" class="em-close-btn" onclick="EditModal.close()" aria-label="Fechar edição">
                <i class="ph-duotone ph-x"></i>
            </button>
        </div>

        <div class="em-body">
            <input type="hidden" id="em-id">
            <input type="hidden" id="em-unit">

            <section class="em-form-card" aria-label="Dados da entrega">
                <div class="em-row">
                    <div class="em-group">
                        <label class="em-label" for="em-date">
                            <span>Data da entrega</span>
                            <span class="em-label-note">Obrigatório</span>
                        </label>

                        <div class="em-input-wrap">
                            <span class="em-input-icon" aria-hidden="true">
                                <i class="ph-duotone ph-calendar-dots"></i>
                            </span>
                            <input type="date" id="em-date" class="em-input has-icon" required>
                        </div>
                    </div>

                    <div class="em-group">
                        <label class="em-label" for="em-qty">
                            <span>Quantidade</span>
                            <span class="em-label-note" id="em-unit-lbl"></span>
                        </label>

                        <div class="em-input-wrap">
                            <span class="em-input-icon" aria-hidden="true">
                                <i class="ph-duotone ph-scale"></i>
                            </span>
                            <input
                                type="number"
                                id="em-qty"
                                class="em-input has-icon"
                                step="0.001"
                                min="0.001"
                                required
                                inputmode="decimal"
                            >
                        </div>
                    </div>
                </div>

                <div class="em-row">
                    <div class="em-group">
                        <label class="em-label" for="em-price">
                            <span>Preço unitário</span>
                            <span class="em-label-note">Opcional</span>
                        </label>

                        <div class="em-input-wrap">
                            <span class="em-input-icon" aria-hidden="true">
                                <i class="ph-duotone ph-currency-circle-dollar"></i>
                            </span>
                            <input
                                type="number"
                                id="em-price"
                                class="em-input has-icon"
                                step="0.01"
                                min="0"
                                placeholder="0,00"
                                inputmode="decimal"
                            >
                        </div>
                    </div>

                    <div class="em-group">
                        <label class="em-label" for="em-quality">
                            <span>Classificação</span>
                            <span class="em-label-note">Opcional</span>
                        </label>

                        <div class="em-input-wrap">
                            <span class="em-input-icon" aria-hidden="true">
                                <i class="ph-duotone ph-seal-check"></i>
                            </span>
                            <input
                                type="text"
                                id="em-quality"
                                class="em-input has-icon"
                                maxlength="50"
                                placeholder="A, B, C…"
                                autocomplete="off"
                            >
                        </div>
                    </div>
                </div>

                <div class="em-group">
                    <label class="em-label" for="em-notes">
                        <span>Observações</span>
                        <span class="em-label-note">Opcional</span>
                    </label>

                    <textarea
                        id="em-notes"
                        class="em-input"
                        rows="2"
                        maxlength="1000"
                        placeholder="Observações opcionais…"
                    ></textarea>
                </div>
            </section>

            <div id="em-qty-warning" role="status" aria-live="polite">
                <span class="em-warning-icon" aria-hidden="true">
                    <i class="ph-duotone ph-warning"></i>
                </span>
                <span id="em-qty-warning-msg"></span>
            </div>

            <section class="em-dist-section" id="em-dist-section" style="display:none">
                <div class="em-dist-header">
                    <div class="em-dist-title-wrap">
                        <span class="em-dist-icon" aria-hidden="true">
                            <i class="ph-duotone ph-git-merge"></i>
                        </span>
                        <strong>Distribuições vinculadas</strong>
                    </div>

                    <span id="em-dist-count"></span>
                </div>

                <div class="em-dist-summary" id="em-dist-summary"></div>
                <div class="em-dist-list" id="em-dist-list"></div>
            </section>
        </div>

        <div class="em-foot">
            <button type="button" class="btn btn-ghost btn-sm" onclick="EditModal.close()">
                <i class="ph-duotone ph-x"></i>
                <span>Cancelar</span>
            </button>

            <button type="button" class="btn btn-primary btn-sm" id="em-save-btn" onclick="EditModal.save()">
                <i class="ph-duotone ph-floppy-disk"></i>
                <span>Salvar alterações</span>
            </button>
        </div>
    </div>
</div>

<div id="em-notice-overlay" aria-hidden="true">
    <div class="em-notice-box" id="em-notice-box" role="alertdialog" aria-modal="true" aria-labelledby="em-notice-title">
        <div class="em-notice-head">
            <span class="em-notice-icon" id="em-notice-icon" aria-hidden="true">
                <i class="ph-duotone ph-warning-circle"></i>
            </span>

            <div class="em-notice-copy">
                <h3 class="em-notice-title" id="em-notice-title">Atenção</h3>
                <p class="em-notice-message" id="em-notice-message"></p>
            </div>
        </div>

        <div class="em-notice-actions">
            <button type="button" onclick="EditModal.closeNotice()">Fechar</button>
        </div>
    </div>
</div>

<script>
(function () {
'use strict';

const EM_TENANT = @json($tenantSlug);
const EM_CSRF   = @json($csrf);

let _cfg = null;
let _emOwnedBodyLock = false;
let _emScrollY = 0;

function $(id) {
    return document.getElementById(id);
}

function esc(value) {
    return String(value || '')
        .replace(/&/g,'&amp;')
        .replace(/</g,'&lt;')
        .replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;');
}

function fmtQty(value, unit) {
    return parseFloat(value || 0).toLocaleString(
        'pt-BR',
        {
            minimumFractionDigits:3,
            maximumFractionDigits:3,
        }
    ) + ' ' + (unit || 'un');
}

function fmtR(value) {
    return 'R$ ' + parseFloat(value || 0).toLocaleString(
        'pt-BR',
        {
            minimumFractionDigits:2,
            maximumFractionDigits:2,
        }
    );
}

function emCompact() {
    return window.matchMedia('(max-width: 767px)').matches;
}

function emShouldAutoFocus() {
    return !emCompact();
}

function syncEmViewport() {
    const viewport = window.visualViewport;
    const height = viewport?.height || window.innerHeight;

    document.documentElement.style.setProperty(
        '--em-vv-height',
        `${Math.round(height)}px`
    );
}

function lockEmPage() {
    /*
     * Se a página hospedeira já possui seu próprio controlador de sheet,
     * não competimos com ele. Só assumimos o lock quando necessário.
     */
    if (
        document.body.classList.contains('register-sheet-open')
        || document.body.classList.contains('dm-page-locked')
        || document.body.classList.contains('em-page-locked')
    ) {
        syncEmViewport();
        return;
    }

    _emOwnedBodyLock = true;
    _emScrollY = window.scrollY || window.pageYOffset || 0;

    document.documentElement.classList.add('em-page-locked');
    document.body.classList.add('em-page-locked');

    document.body.style.position = 'fixed';
    document.body.style.top = `-${_emScrollY}px`;
    document.body.style.left = '0';
    document.body.style.right = '0';
    document.body.style.width = '100%';

    syncEmViewport();
}

function unlockEmPage() {
    if (!_emOwnedBodyLock) return;

    _emOwnedBodyLock = false;

    document.documentElement.classList.remove('em-page-locked');
    document.body.classList.remove('em-page-locked');

    document.body.style.position = '';
    document.body.style.top = '';
    document.body.style.left = '';
    document.body.style.right = '';
    document.body.style.width = '';

    window.scrollTo(0, _emScrollY);
}

function showNotice(message, options = {}) {
    const overlay = $('em-notice-overlay');
    const box = $('em-notice-box');
    const type = options.type || 'warning';

    if (!overlay || !box) return;

    box.className = 'em-notice-box ' + type;

    $('em-notice-title').textContent =
        options.title
        || (type === 'error' ? 'Não foi possível concluir' : 'Atenção');

    $('em-notice-message').textContent =
        message || 'Não foi possível concluir esta ação.';

    const icon =
        type === 'error'
            ? 'x-circle'
            : type === 'info'
                ? 'info'
                : 'warning-circle';

    $('em-notice-icon').innerHTML =
        `<i class="ph-duotone ph-${icon}"></i>`;

    overlay.classList.add('open');
    overlay.setAttribute('aria-hidden', 'false');

    if (emShouldAutoFocus()) {
        window.setTimeout(
            () => overlay.querySelector('button')?.focus({ preventScroll:true }),
            35
        );
    }
}

function renderDistributions(dists, unit) {
    const section = $('em-dist-section');
    const list = $('em-dist-list');
    const summary = $('em-dist-summary');

    if (!dists || dists.length === 0) {
        section.style.display = 'none';
        list.innerHTML = '';
        summary.innerHTML = '';
        return;
    }

    section.style.display = '';

    const totalQty = dists.reduce(
        (sum, item) => sum + parseFloat(item.qty || 0),
        0
    );

    const totalNet = dists.reduce(
        (sum, item) => sum + parseFloat(item.net || 0),
        0
    );

    $('em-dist-count').textContent =
        dists.length + (dists.length === 1 ? ' destino' : ' destinos');

    summary.innerHTML = `
        <div class="em-dist-metric">
            <span>Destinos</span>
            <strong>${dists.length}</strong>
        </div>

        <div class="em-dist-metric">
            <span>Distribuído</span>
            <strong>${fmtQty(totalQty, unit)}</strong>
        </div>

        <div class="em-dist-metric value">
            <span>Valor líquido</span>
            <strong>${totalNet > 0 ? fmtR(totalNet) : '—'}</strong>
        </div>
    `;

    list.innerHTML = dists.map((item, index) => `
        <div class="em-dist-row">
            <span class="em-dist-row-icon" aria-hidden="true">
                <i class="ph-duotone ph-buildings"></i>
            </span>

            <div class="em-dist-row-main">
                <span class="em-dist-customer">${esc(item.customer || 'Cliente')}</span>
                <small>Destino ${index + 1}</small>
            </div>

            <div class="em-dist-values">
                <span class="em-dist-qty">${fmtQty(item.qty, unit)}</span>
                <span class="em-dist-net">${parseFloat(item.net || 0) > 0 ? fmtR(item.net) : ''}</span>
            </div>
        </div>
    `).join('');
}

function checkQtyWarning() {
    if (!_cfg) return;

    const newQty = parseFloat($('em-qty').value || 0);
    const dists = _cfg.distributions || [];

    const distTotal = dists.reduce(
        (sum, item) => sum + parseFloat(item.qty || 0),
        0
    );

    if (
        distTotal > 0
        && newQty < distTotal - 0.0005
    ) {
        $('em-qty-warning-msg').textContent =
            'A nova quantidade (' + fmtQty(newQty, _cfg.unit) +
            ') é menor que o total já distribuído (' +
            fmtQty(distTotal, _cfg.unit) +
            '). As distribuições serão mantidas, mas ficarão inconsistentes.';

        $('em-qty-warning').classList.add('visible');
    } else {
        $('em-qty-warning').classList.remove('visible');
    }
}

window.EditModal = {
    openFromBtn(btn) {
        let dists = [];

        try {
            dists = JSON.parse(btn.dataset.distributions || '[]');
        } catch {}

        this.open({
            id:btn.dataset.id,
            date:btn.dataset.date || '',
            qty:btn.dataset.qty || '',
            price:btn.dataset.price || '',
            quality:btn.dataset.quality || '',
            notes:btn.dataset.notes || '',
            unit:btn.dataset.unit || 'un',
            distributions:dists,
        });
    },

    open(cfg) {
        _cfg = cfg;

        $('em-id').value = cfg.id || '';
        $('em-date').value = cfg.date || '';
        $('em-qty').value = cfg.qty || '';
        $('em-price').value = cfg.price || '';
        $('em-quality').value = cfg.quality || '';
        $('em-notes').value = cfg.notes || '';
        $('em-unit').value = cfg.unit || 'un';

        $('em-unit-lbl').textContent =
            cfg.unit ? cfg.unit : 'Obrigatório';

        $('em-subtitle').textContent =
            cfg.unit
                ? `Quantidade em ${cfg.unit} · demais campos são opcionais`
                : 'Ajuste os dados do registro';

        $('em-qty-warning').classList.remove('visible');

        renderDistributions(
            cfg.distributions || [],
            cfg.unit || 'un'
        );

        lockEmPage();
        syncEmViewport();

        $('em-overlay').classList.add('em-open');
        $('em-overlay').setAttribute('aria-hidden', 'false');
        $('em-save-btn').disabled = false;

        /*
         * Desktop mantém foco rápido. No mobile não abrimos
         * o teclado virtual automaticamente.
         */
        if (emShouldAutoFocus()) {
            window.setTimeout(() => {
                $('em-qty')?.focus({ preventScroll:true });
                $('em-qty')?.select?.();
            }, 65);
        }
    },

    close() {
        $('em-overlay').classList.remove('em-open');
        $('em-overlay').setAttribute('aria-hidden', 'true');

        this.closeNotice();

        _cfg = null;
        unlockEmPage();
    },

    closeNotice() {
        $('em-notice-overlay').classList.remove('open');
        $('em-notice-overlay').setAttribute('aria-hidden', 'true');
    },

    async save() {
        const id = $('em-id').value;
        const date = $('em-date').value;
        const qty = $('em-qty').value;
        const price = $('em-price').value;

        if (!date || !qty) {
            showNotice(
                'Preencha a data e a quantidade antes de salvar.',
                {
                    type:'warning',
                    title:'Campos obrigatórios',
                }
            );
            return;
        }

        const parsedQty = parseFloat(qty);

        if (!Number.isFinite(parsedQty) || parsedQty <= 0) {
            showNotice(
                'Informe uma quantidade válida maior que zero.',
                {
                    type:'warning',
                    title:'Quantidade inválida',
                }
            );
            return;
        }

        const saveBtn = $('em-save-btn');
        saveBtn.disabled = true;

        try {
            const response = await fetch(
                `/${EM_TENANT}/delivery/deliveries/${id}`,
                {
                    method:'PUT',
                    headers:{
                        'X-CSRF-TOKEN':EM_CSRF,
                        'Content-Type':'application/json',
                        'Accept':'application/json',
                    },
                    body:JSON.stringify({
                        delivery_date:date,
                        quantity:parsedQty,
                        unit_price:price ? parseFloat(price) : null,
                        quality_grade:$('em-quality').value || null,
                        notes:$('em-notes').value || null,
                    }),
                }
            );

            const data = await response.json();

            if (!response.ok || !data.success) {
                showNotice(
                    data.message || 'Erro ao salvar a entrega.',
                    {
                        type:'error',
                        title:'Não foi possível salvar',
                    }
                );
                return;
            }

            this.close();

            if (typeof EditModal.onSaved === 'function') {
                await EditModal.onSaved(data.delivery);
            } else {
                location.reload();
            }
        } catch (error) {
            showNotice(
                error.message || 'Erro de comunicação com o servidor.',
                {
                    type:'error',
                    title:'Erro de comunicação',
                }
            );
        } finally {
            saveBtn.disabled = false;
        }
    },

    onSaved:null,
};

$('em-qty')?.addEventListener('input', checkQtyWarning);

$('em-overlay')?.addEventListener('click', event => {
    if (event.target === $('em-overlay')) {
        EditModal.close();
    }
});

$('em-notice-overlay')?.addEventListener('click', event => {
    if (event.target === $('em-notice-overlay')) {
        EditModal.closeNotice();
    }
});

document.addEventListener('keydown', event => {
    if (!$('em-overlay')?.classList.contains('em-open')) return;

    if (
        event.key === 'Escape'
        && !$('em-notice-overlay')?.classList.contains('open')
    ) {
        event.preventDefault();
        EditModal.close();
        return;
    }

    if (
        event.key === 'Enter'
        && (event.ctrlKey || event.metaKey)
    ) {
        event.preventDefault();
        EditModal.save();
    }
});

if (window.visualViewport) {
    window.visualViewport.addEventListener(
        'resize',
        syncEmViewport,
        { passive:true }
    );

    window.visualViewport.addEventListener(
        'scroll',
        syncEmViewport,
        { passive:true }
    );
}

syncEmViewport();

/* Backward compatibility */
window.openEditModal = btn => EditModal.openFromBtn(btn);
window.closeEditModal = () => EditModal.close();

})();
</script>