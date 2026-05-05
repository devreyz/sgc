{{--
    Componente: x-delivery.dist-modal
    Uso:
        <x-delivery.dist-modal
            :tenant-slug="$currentTenant->slug"
            :csrf="csrf_token()"
            :customers="$customers->map(fn($c)=>['id'=>$c->id,'name'=>$c->trade_name?:$c->name])->values()->all()"
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
.dm-existing-customer { flex: 1; font-weight: 500; }
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

/* ── New-row inputs ───────────────────────────────────────────────────── */
.dm-row {
    display: flex; gap: .4rem; align-items: center;
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
        section.style.display = 'none';
        return;
    }
    section.style.display = '';
    list.innerHTML = existing.map(d => `
        <div class="dm-existing-row" id="dmex-${d.id}">
            <span class="dm-existing-customer">${esc(d.customer)}</span>
            <span class="dm-existing-qty">${fmt(d.qty, _unit)}</span>
            <span class="dm-existing-net">${d.net > 0 ? fmtR(d.net) : ''}</span>
            <button class="dm-del-btn" title="Remover distribuição" aria-label="Remover distribuição de ${esc(d.customer)}"
                    onclick="DistModal.deleteExisting(${d.id})"
                    style="${d.id ? '' : 'display:none'}">✕</button>
        </div>
    `).join('');
}

/* ── Build one new-row ──────────────────────────────────────────────── */
function buildRow(autofocus = false) {
    const row = document.createElement('div');
    row.className = 'dm-row';
    row.setAttribute('role', 'group');
    row.setAttribute('aria-label', 'Linha de distribuição');

    const rowIdx = $('dm-new-rows').children.length + 1;

    const sel = document.createElement('select');
    sel.setAttribute('aria-label', 'Selecionar cliente para distribuição ' + rowIdx);
    sel.innerHTML = '<option value="">Selecionar cliente…</option>' +
        DM_CUSTOMERS.map(c => `<option value="${c.id}">${esc(c.name)}</option>`).join('');

    const inp = document.createElement('input');
    inp.type        = 'number';
    inp.min         = '0.001';
    inp.step        = '0.001';
    inp.placeholder = '0';
    inp.setAttribute('aria-label', 'Quantidade para distribuição ' + rowIdx);
    inp.addEventListener('input', updateProgress);

    const rm = document.createElement('button');
    rm.type      = 'button';
    rm.className = 'dm-rm-btn';
    rm.setAttribute('aria-label', 'Remover esta linha de distribuição');
    rm.textContent = '×';
    rm.onclick = () => { row.remove(); updateProgress(); };

    row.appendChild(sel);
    row.appendChild(inp);
    row.appendChild(rm);
    return row;
}

/* ══ Public API ════════════════════════════════════════════════════════ */
window.DistModal = {

    /** Open from a data-* button (used by project-deliveries & all-deliveries) */
    openFromBtn(btn) {
        let existing = [];
        try { existing = JSON.parse(btn.dataset.existing || '[]'); } catch {}
        this.open({
            id:          btn.dataset.id,
            product:     btn.dataset.product || '-',
            unit:        btn.dataset.unit    || 'un',
            qty:         parseFloat(btn.dataset.qty) || 0,
            distributed: parseFloat(btn.dataset.distributed) || 0,
            existing,
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

        // New rows — start with one blank
        $('dm-new-rows').innerHTML = '';
        $('dm-new-rows').appendChild(buildRow());

        // Progress
        updateProgress();

        // Open
        $('dm-overlay').classList.add('dm-open');
        $('dm-save-btn').disabled = false;

        // Focus first select for keyboard accessibility
        setTimeout(() => $('dm-new-rows').querySelector('select')?.focus(), 80);
    },

    close() {
        $('dm-overlay').classList.remove('dm-open');
        _id = null;
    },

    addRow() {
        const row = buildRow();
        $('dm-new-rows').appendChild(row);
        updateProgress();
        setTimeout(() => row.querySelector('select')?.focus(), 30);
    },

    /** Delete an existing (already-saved) distribution */
    async deleteExisting(distributionId) {
        if (!confirm('Remover esta distribuição?')) return;
        const saveBtn = $('dm-save-btn');
        saveBtn.disabled = true;
        try {
            const res  = await fetch(`/${DM_TENANT}/delivery/distributions/${distributionId}`, {
                method:  'DELETE',
                headers: { 'X-CSRF-TOKEN': DM_CSRF, 'Accept': 'application/json' },
            });
            const data = await res.json();
            if (data.success) {
                // Remove row from UI
                document.getElementById('dmex-' + distributionId)?.remove();
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
                alert(data.message || 'Erro ao remover.');
            }
        } catch (e) {
            alert('Erro: ' + e.message);
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
            if (!cid && qty === 0) continue;
            if (!cid) { alert('Selecione o cliente para cada linha.'); return; }
            if (qty <= 0) { alert('Informe a quantidade para cada cliente.'); return; }
            distributions.push({ customer_id: parseInt(cid), quantity: qty });
        }
        if (!distributions.length) { alert('Adicione pelo menos uma distribuição.'); return; }

        const newTotal = distributions.reduce((s, d) => s + d.quantity, 0);
        const avail    = _totalQty - _distQty;
        if (newTotal > avail + 0.0005) {
            alert('Total (' + fmt(newTotal, _unit) + ') excede disponível (' + fmt(avail, _unit) + ').');
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
                alert(data.message || 'Erro ao distribuir.');
            }
        } catch (e) {
            alert('Erro: ' + e.message);
        } finally {
            saveBtn.disabled    = false;
            saveBtn.textContent = '';
            saveBtn.innerHTML   =
                '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Salvar distribuições';
        }
    },
};

/* ── Backward-compat aliases ────────────────────────────────────────── */
window.openDistModal  = (btn) => DistModal.openFromBtn(btn);
window.closeDistModal = ()    => DistModal.close();

})();
</script>
