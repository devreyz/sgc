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
/* ═══════════════════════════════════════════════════════════════════════
   DIST MODAL — camada visual/UX
   A lógica JavaScript e os contratos do componente permanecem intactos.
   ═══════════════════════════════════════════════════════════════════════ */

#dm-overlay,
#dm-confirm-overlay,
#dm-notice-overlay {
    --dm-green: #168a4d;
    --dm-green-soft: #eaf8ef;
    --dm-blue: #2563eb;
    --dm-blue-soft: #eef4ff;
    --dm-sky: #0284c7;
    --dm-sky-soft: #edf8fe;
    --dm-violet: #7c3aed;
    --dm-violet-soft: #f4f0ff;
    --dm-amber: #c87408;
    --dm-amber-soft: #fff7e8;
    --dm-red: #cf3f3f;
    --dm-red-soft: #fff0f0;
    --dm-slate: #64748b;
    --dm-slate-soft: #f1f5f9;

    --dm-surface: var(--color-surface, #fff);
    --dm-soft: var(--color-surface-soft, #f8faf9);
    --dm-border: var(--color-border, #dce7e0);
    --dm-border-strong: var(--color-border-strong, #c8d6cd);
    --dm-text: var(--color-text, #102018);
    --dm-text-2: var(--color-text-secondary, #52645a);
    --dm-text-3: var(--color-text-muted, #809087);
    --dm-shadow: 0 24px 68px rgba(15, 23, 42, .22);
}

#dm-overlay *,
#dm-overlay *::before,
#dm-overlay *::after,
#dm-confirm-overlay *,
#dm-confirm-overlay *::before,
#dm-confirm-overlay *::after,
#dm-notice-overlay *,
#dm-notice-overlay *::before,
#dm-notice-overlay *::after {
    box-sizing: border-box;
}

/* ── Overlay principal ─────────────────────────────────────────────── */
#dm-overlay {
    position: fixed;
    z-index: 300000;
    inset: 0;
    display: none;
    align-items: center;
    justify-content: center;
    padding:
        max(1rem, env(safe-area-inset-top))
        max(1rem, env(safe-area-inset-right))
        max(1rem, env(safe-area-inset-bottom))
        max(1rem, env(safe-area-inset-left));
    background: rgba(15, 23, 42, .48);
    backdrop-filter: blur(4px);
}

#dm-overlay.dm-open {
    display: flex;
}

/* ── Painel ───────────────────────────────────────────────────────── */
.dm-box {
    display: flex;
    width: min(680px, 100%);
    max-height: min(92dvh, 860px);
    min-height: 0;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid var(--dm-border);
    border-radius: 15px;
    background: var(--dm-surface);
    color: var(--dm-text);
    box-shadow: var(--dm-shadow);
}

/* ── Cabeçalho ────────────────────────────────────────────────────── */
.dm-head {
    display: flex;
    flex: 0 0 auto;
    min-width: 0;
    gap: .65rem;
    align-items: flex-start;
    justify-content: space-between;
    padding: .72rem .78rem;
    border-bottom: 1px solid var(--dm-border);
    background:
        radial-gradient(circle at 100% 0, rgba(124, 58, 237, .10), transparent 16rem),
        linear-gradient(180deg, var(--dm-soft), #fff);
}

.dm-head-info {
    min-width: 0;
    flex: 1 1 auto;
}

.dm-title {
    display: flex;
    min-width: 0;
    gap: .38rem;
    align-items: center;
    margin: 0;
    color: var(--dm-text);
    font-size: .92rem;
    font-weight: 850;
    letter-spacing: -.025em;
    line-height: 1.25;
}

.dm-title > svg {
    display: block;
    width: 17px;
    height: 17px;
    flex: 0 0 auto;
    margin: 0;
}

.dm-subtitle {
    max-width: 100%;
    margin-top: .12rem;
    overflow: hidden;
    color: var(--dm-text-3);
    font-size: .69rem;
    line-height: 1.35;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.dm-head-info .dm-notes-btn {
    margin-top: .34rem;
}

.dm-close-btn,
.dm-notes-btn,
.dm-del-btn,
.dm-edit-btn,
.dm-action-disabled,
.dm-mini-btn,
.dm-row .dm-rm-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    line-height: 0;
}

.dm-close-btn {
    width: 36px;
    height: 36px;
    border: 1px solid var(--dm-border);
    border-radius: 9px;
    background: #fff;
    color: var(--dm-text-2);
    cursor: pointer;
    font: inherit;
    font-size: .95rem;
    transition:
        border-color 140ms ease,
        background 140ms ease,
        color 140ms ease,
        transform 140ms ease;
}

.dm-close-btn:hover,
.dm-close-btn:focus-visible {
    border-color: rgba(124, 58, 237, .20);
    background: var(--dm-violet-soft);
    color: var(--dm-violet);
    outline: none;
}

.dm-notes-btn {
    display: none;
    min-height: 28px;
    gap: .26rem;
    padding: .28rem .46rem;
    border: 1px solid var(--dm-border);
    border-radius: 8px;
    background: #fff;
    color: var(--dm-slate);
    cursor: pointer;
    font: inherit;
    font-size: .63rem;
    font-weight: 760;
}

.dm-notes-btn.visible {
    display: inline-flex;
}

.dm-notes-btn:hover,
.dm-notes-btn:focus-visible {
    border-color: rgba(100, 116, 139, .22);
    background: var(--dm-slate-soft);
    outline: none;
}

/* ── Progresso ────────────────────────────────────────────────────── */
.dm-progress-wrap {
    display: grid;
    flex: 0 0 auto;
    gap: .34rem;
    padding: .58rem .78rem;
    border-bottom: 1px solid var(--dm-border);
    background: #fff;
}

.dm-progress-track {
    display: flex;
    height: 8px;
    margin: 0;
    overflow: hidden;
    border-radius: 999px;
    background: var(--dm-slate-soft);
}

.dm-bar-existing,
.dm-bar-new {
    height: 100%;
    transition: width .18s ease;
}

.dm-bar-existing {
    background: #86efac;
}

.dm-bar-new {
    background:
        linear-gradient(
            90deg,
            color-mix(in srgb, var(--dm-violet) 52%, #fff),
            var(--dm-violet)
        );
}

.dm-progress-labels {
    display: grid;
    min-width: 0;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: .6rem;
    align-items: center;
    color: var(--dm-text-3);
    font-size: .64rem;
    line-height: 1.35;
}

.dm-progress-labels > span {
    min-width: 0;
}

.dm-progress-labels > span:last-child {
    text-align: right;
    white-space: nowrap;
}

.dm-progress-labels strong {
    color: var(--dm-text);
    font-weight: 820;
}

#dm-warning-overflow,
#dm-done-badge {
    display: none;
    width: fit-content;
    max-width: 100%;
    min-height: 25px;
    align-items: center;
    gap: .28rem;
    padding: .2rem .38rem;
    border-radius: 8px;
    font-size: .62rem;
    font-weight: 780;
    line-height: 1.3;
}

#dm-warning-overflow.visible,
#dm-done-badge.visible {
    display: inline-flex;
}

#dm-warning-overflow {
    border: 1px solid rgba(207, 63, 63, .14);
    background: var(--dm-red-soft);
    color: var(--dm-red);
}

#dm-done-badge {
    border: 1px solid rgba(22, 138, 77, .14);
    background: var(--dm-green-soft);
    color: var(--dm-green);
}

/* ── Corpo ────────────────────────────────────────────────────────── */
.dm-body {
    display: flex;
    min-height: 0;
    flex: 1 1 auto;
    flex-direction: column;
    gap: .7rem;
    padding: .68rem .78rem .78rem;
    overflow-y: auto;
    overscroll-behavior: contain;
    scrollbar-width: thin;
}

.dm-section-lbl {
    margin: 0 0 .3rem;
    color: var(--dm-text-3);
    font-size: .62rem;
    font-weight: 800;
    letter-spacing: .035em;
    line-height: 1.3;
    text-transform: uppercase;
}

/* ── Distribuições existentes ─────────────────────────────────────── */
#dm-existing-section {
    min-width: 0;
}

.dm-existing-block {
    min-width: 0;
    overflow: hidden;
    border: 1px solid var(--dm-border);
    border-radius: 11px;
    background: #fff;
}

.dm-existing-row {
    display: grid;
    min-width: 0;
    grid-template-columns:
        minmax(150px, 1.4fr)
        minmax(90px, auto)
        minmax(82px, auto)
        auto;
    gap: .48rem;
    align-items: center;
    min-height: 58px;
    padding: .48rem .52rem;
    font-size: .72rem;
}

.dm-existing-row + .dm-existing-row {
    border-top: 1px solid var(--dm-border);
}

.dm-existing-row:hover {
    background:
        linear-gradient(
            90deg,
            color-mix(in srgb, var(--dm-violet-soft) 32%, #fff),
            #fff 42%
        );
}

.dm-existing-customer {
    display: flex;
    min-width: 0;
    flex-direction: column;
    align-items: flex-start;
    justify-content: center;
    color: var(--dm-text);
    font-size: .73rem;
    font-weight: 790;
    line-height: 1.3;
}

.dm-existing-price {
    display: block;
    margin-top: .08rem;
    color: var(--dm-text-3);
    font-size: .61rem;
    font-weight: 680;
}

.dm-existing-qty {
    color: var(--dm-text-2);
    font-size: .69rem;
    font-weight: 760;
    text-align: right;
    white-space: nowrap;
}

.dm-existing-net {
    min-width: 72px;
    color: var(--dm-green);
    font-size: .69rem;
    font-weight: 820;
    text-align: right;
    white-space: nowrap;
}

.dm-existing-actions {
    display: flex;
    gap: .22rem;
    align-items: center;
    justify-content: flex-end;
    flex-shrink: 0;
}

.dm-edit-btn,
.dm-del-btn,
.dm-action-disabled {
    width: 32px;
    height: 32px;
    border: 1px solid var(--dm-border);
    border-radius: 8px;
    background: #fff;
    font: inherit;
    font-size: .82rem;
    transition:
        border-color 140ms ease,
        background 140ms ease,
        color 140ms ease,
        opacity 140ms ease;
}

.dm-edit-btn {
    color: var(--dm-blue);
    cursor: pointer;
}

.dm-edit-btn:hover,
.dm-edit-btn:focus-visible {
    border-color: rgba(37, 99, 235, .18);
    background: var(--dm-blue-soft);
    outline: none;
}

.dm-del-btn {
    color: var(--dm-red);
    cursor: pointer;
}

.dm-del-btn:hover,
.dm-del-btn:focus-visible {
    border-color: rgba(207, 63, 63, .18);
    background: var(--dm-red-soft);
    outline: none;
}

.dm-action-disabled {
    color: var(--dm-text-3);
    cursor: not-allowed;
    opacity: .46;
}

.dm-status-badges {
    display: flex;
    max-width: 100%;
    gap: .2rem;
    flex-wrap: wrap;
    justify-content: flex-start;
    margin-top: .18rem;
}

.dm-status-badge {
    display: inline-flex;
    min-height: 20px;
    align-items: center;
    padding: .1rem .3rem;
    border: 1px solid transparent;
    border-radius: 999px;
    font-size: .56rem;
    font-weight: 790;
    white-space: nowrap;
}

.dm-status-badge.receipt {
    border-color: rgba(124, 58, 237, .12);
    background: var(--dm-violet-soft);
    color: var(--dm-violet);
}

.dm-status-badge.billed {
    border-color: rgba(37, 99, 235, .12);
    background: var(--dm-blue-soft);
    color: var(--dm-blue);
}

.dm-status-badge.paid {
    border-color: rgba(22, 138, 77, .12);
    background: var(--dm-green-soft);
    color: var(--dm-green);
}

/* ── Edição inline de distribuição existente ──────────────────────── */
.dm-inline-edit {
    display: grid;
    width: 100%;
    grid-column: 1 / -1;
    grid-template-columns: minmax(0, 1fr) 118px auto;
    gap: .4rem;
    align-items: center;
}

.dm-inline-edit select,
.dm-inline-edit input,
.dm-row select,
.dm-row input[type=number] {
    width: 100%;
    min-width: 0;
    min-height: 40px;
    padding: .46rem .55rem;
    border: 1px solid var(--dm-border-strong);
    border-radius: 9px;
    outline: none;
    background: #fff;
    color: var(--dm-text);
    font: inherit;
    font-size: .72rem;
    transition:
        border-color 140ms ease,
        box-shadow 140ms ease;
}

.dm-inline-edit select:focus,
.dm-inline-edit input:focus,
.dm-row select:focus,
.dm-row input[type=number]:focus {
    border-color: var(--dm-violet);
    box-shadow: 0 0 0 3px rgba(124, 58, 237, .07);
}

.dm-inline-edit-actions {
    display: flex;
    gap: .24rem;
}

.dm-mini-btn {
    width: 36px;
    height: 36px;
    border: 1px solid var(--dm-border);
    border-radius: 9px;
    background: #fff;
    cursor: pointer;
    font: inherit;
}

.dm-mini-btn.save {
    border-color: rgba(22, 138, 77, .15);
    background: var(--dm-green-soft);
    color: var(--dm-green);
}

.dm-mini-btn.cancel {
    color: var(--dm-slate);
}

/* ── Novas linhas ─────────────────────────────────────────────────── */
#dm-new-section {
    min-width: 0;
}

#dm-new-rows {
    gap: .42rem !important;
}

.dm-row {
    display: grid;
    min-width: 0;
    grid-template-columns: minmax(0, 1fr) 112px 38px;
    gap: .34rem;
    align-items: center;
    padding: .46rem;
    border: 1px solid var(--dm-border);
    border-radius: 10px;
    background:
        linear-gradient(
            180deg,
            #fff,
            color-mix(in srgb, var(--dm-violet-soft) 22%, #fff)
        );
}

.dm-row .dm-rm-btn {
    width: 38px;
    height: 40px;
    border: 1px solid var(--dm-border);
    border-radius: 9px;
    background: #fff;
    color: var(--dm-red);
    cursor: pointer;
    font: inherit;
    font-size: 1rem;
}

.dm-row .dm-rm-btn:hover,
.dm-row .dm-rm-btn:focus-visible {
    border-color: rgba(207, 63, 63, .18);
    background: var(--dm-red-soft);
    outline: none;
}

.dm-row-price {
    display: flex;
    min-height: 20px;
    grid-column: 1 / -1;
    gap: .32rem;
    align-items: center;
    padding: 0 .05rem;
    color: var(--dm-text-3);
    font-size: .61rem;
    font-weight: 680;
    line-height: 1.35;
}

.dm-row-price.available {
    color: var(--dm-green);
}

.dm-row-price.missing {
    color: var(--dm-amber);
}

.dm-row-price button {
    border: 0;
    padding: 0;
    background: transparent;
    color: var(--dm-violet);
    cursor: pointer;
    font: inherit;
    font-size: .61rem;
    font-weight: 820;
    text-decoration: underline;
}

/* ── Ações de inclusão ────────────────────────────────────────────── */
.dm-add-btn {
    display: inline-flex;
    min-height: 34px;
    align-items: center;
    gap: .28rem;
    padding: .34rem .48rem;
    border: 1px dashed var(--dm-border-strong);
    border-radius: 8px;
    background: #fff;
    color: var(--dm-text-2);
    cursor: pointer;
    font: inherit;
    font-size: .62rem;
    font-weight: 760;
    line-height: 1.2;
    text-align: left;
    transition:
        border-color 140ms ease,
        background 140ms ease,
        color 140ms ease,
        transform 140ms ease;
}

.dm-add-btn + .dm-add-btn {
    margin-left: .26rem;
}

.dm-add-btn:hover,
.dm-add-btn:focus-visible {
    border-color: rgba(124, 58, 237, .30);
    background: var(--dm-violet-soft);
    color: var(--dm-violet);
    outline: none;
    transform: translateY(-1px);
}

.dm-add-btn > svg {
    display: block;
    width: 13px;
    height: 13px;
    flex: 0 0 auto;
    margin: 0;
}

/* ── Rodapé ───────────────────────────────────────────────────────── */
.dm-foot {
    display: flex;
    flex: 0 0 auto;
    min-width: 0;
    gap: .4rem;
    align-items: center;
    padding:
        .62rem .78rem
        max(.62rem, env(safe-area-inset-bottom));
    border-top: 1px solid var(--dm-border);
    background: #fff;
}

.dm-shortcuts {
    min-width: 0;
    margin-right: auto;
    color: var(--dm-text-3);
    font-size: .58rem;
    line-height: 1.3;
}

#dm-overlay .dm-foot .btn,
#dm-confirm-overlay .btn,
#dm-notice-overlay .btn {
    display: inline-flex;
    min-height: 38px;
    align-items: center;
    justify-content: center;
    gap: .28rem;
    padding: .42rem .58rem;
    border: 1px solid var(--dm-border);
    border-radius: 9px;
    background: #fff;
    color: var(--dm-text-2);
    cursor: pointer;
    font: inherit;
    font-size: .67rem;
    font-weight: 780;
    line-height: 1;
    white-space: nowrap;
    transition:
        transform 140ms ease,
        border-color 140ms ease,
        background 140ms ease,
        color 140ms ease;
}

#dm-overlay .dm-foot .btn:hover:not(:disabled),
#dm-overlay .dm-foot .btn:focus-visible:not(:disabled),
#dm-confirm-overlay .btn:hover:not(:disabled),
#dm-confirm-overlay .btn:focus-visible:not(:disabled),
#dm-notice-overlay .btn:hover:not(:disabled),
#dm-notice-overlay .btn:focus-visible:not(:disabled) {
    outline: none;
    transform: translateY(-1px);
}

#dm-overlay .dm-foot .btn-primary,
#dm-notice-overlay .btn-primary {
    border-color: rgba(124, 58, 237, .18);
    background: var(--dm-violet-soft);
    color: var(--dm-violet);
}

#dm-confirm-overlay .btn-danger {
    border-color: rgba(207, 63, 63, .18);
    background: var(--dm-red-soft);
    color: var(--dm-red);
}

#dm-overlay .dm-foot .btn:disabled,
#dm-confirm-overlay .btn:disabled,
#dm-notice-overlay .btn:disabled {
    cursor: wait;
    opacity: .48;
    transform: none;
}

/* ── Confirmação destrutiva ───────────────────────────────────────── */
#dm-confirm-overlay {
    position: fixed;
    z-index: 310000;
    inset: 0;
    display: none;
    align-items: center;
    justify-content: center;
    padding:
        max(1rem, env(safe-area-inset-top))
        max(1rem, env(safe-area-inset-right))
        max(1rem, env(safe-area-inset-bottom))
        max(1rem, env(safe-area-inset-left));
    background: rgba(15, 23, 42, .52);
    backdrop-filter: blur(3px);
}

#dm-confirm-overlay.open {
    display: flex;
}

.dm-confirm-box {
    width: min(440px, 100%);
    overflow: hidden;
    padding: .78rem;
    border: 1px solid var(--dm-border);
    border-radius: 14px;
    background:
        radial-gradient(circle at 100% 0, rgba(207, 63, 63, .08), transparent 12rem),
        #fff;
    box-shadow: var(--dm-shadow);
}

.dm-confirm-title {
    margin: 0;
    color: var(--dm-text);
    font-size: .86rem;
    font-weight: 850;
}

.dm-confirm-text {
    margin-top: .28rem;
    color: var(--dm-text-2);
    font-size: .7rem;
    line-height: 1.48;
}

.dm-confirm-math {
    display: grid;
    gap: .28rem;
    margin-top: .68rem;
    padding: .52rem;
    border-radius: 9px;
    background: var(--dm-red-soft);
}

.dm-confirm-math label {
    color: #991b1b;
    font-size: .64rem;
    font-weight: 780;
    line-height: 1.3;
    text-transform: none;
}

.dm-confirm-math input {
    width: 100%;
    min-height: 40px;
    padding: .46rem .56rem;
    border: 1px solid rgba(207, 63, 63, .20);
    border-radius: 9px;
    outline: none;
    background: #fff;
    color: var(--dm-text);
    font: inherit;
    font-size: .8rem;
}

.dm-confirm-math input:focus {
    border-color: var(--dm-red);
    box-shadow: 0 0 0 3px rgba(207, 63, 63, .07);
}

.dm-confirm-actions {
    display: flex;
    gap: .4rem;
    justify-content: flex-end;
    margin-top: .68rem;
}

/* ── Avisos / configuração de preço ───────────────────────────────── */
#dm-notice-overlay {
    position: fixed;
    z-index: 320000;
    inset: 0;
    display: none;
    align-items: center;
    justify-content: center;
    padding:
        max(1rem, env(safe-area-inset-top))
        max(1rem, env(safe-area-inset-right))
        max(1rem, env(safe-area-inset-bottom))
        max(1rem, env(safe-area-inset-left));
    background: rgba(15, 23, 42, .52);
    backdrop-filter: blur(3px);
}

#dm-notice-overlay.open {
    display: flex;
}

.dm-notice-box {
    width: min(460px, 100%);
    overflow: hidden;
    border: 1px solid var(--dm-border);
    border-radius: 14px;
    background: #fff;
    box-shadow: var(--dm-shadow);
}

.dm-notice-head {
    display: grid;
    min-width: 0;
    grid-template-columns: auto minmax(0, 1fr);
    gap: .56rem;
    align-items: start;
    padding: .72rem .76rem .58rem;
}

.dm-notice-icon {
    display: inline-flex;
    width: 34px;
    height: 34px;
    align-items: center;
    justify-content: center;
    border-radius: 9px;
    background: var(--dm-amber-soft);
    color: var(--dm-amber);
}

.dm-notice-icon > svg {
    display: block;
    width: 17px;
    height: 17px;
}

.dm-notice-box.error .dm-notice-icon {
    background: var(--dm-red-soft);
    color: var(--dm-red);
}

.dm-notice-box.info .dm-notice-icon {
    background: var(--dm-blue-soft);
    color: var(--dm-blue);
}

.dm-notice-copy {
    min-width: 0;
}

.dm-notice-title,
.dm-notice-message {
    margin: 0;
}

.dm-notice-title {
    color: var(--dm-text);
    font-size: .84rem;
    font-weight: 840;
    line-height: 1.25;
}

.dm-notice-message {
    margin-top: .14rem;
    color: var(--dm-text-2);
    font-size: .7rem;
    line-height: 1.46;
}

.dm-notice-form {
    display: none;
    padding: 0 .76rem .68rem;
}

.dm-notice-form.visible {
    display: block;
}

.dm-notice-form label {
    display: block;
    margin-bottom: .26rem;
    color: var(--dm-text-2);
    font-size: .65rem;
    font-weight: 760;
}

.dm-price-input-wrap {
    position: relative;
}

.dm-price-prefix {
    position: absolute;
    top: 50%;
    left: .62rem;
    color: var(--dm-text-3);
    font-size: .7rem;
    transform: translateY(-50%);
}

.dm-notice-form input {
    width: 100%;
    min-height: 41px;
    padding: .48rem .56rem .48rem 2.15rem;
    border: 1px solid var(--dm-border-strong);
    border-radius: 9px;
    outline: none;
    background: #fff;
    color: var(--dm-text);
    font: inherit;
    font-size: .75rem;
}

.dm-notice-form input:focus {
    border-color: var(--dm-violet);
    box-shadow: 0 0 0 3px rgba(124, 58, 237, .07);
}

.dm-notice-actions {
    display: flex;
    gap: .4rem;
    justify-content: flex-end;
    padding: .58rem .76rem
        max(.58rem, env(safe-area-inset-bottom));
    border-top: 1px solid var(--dm-border);
    background: var(--dm-soft);
}

/* ── Mobile / tablet ──────────────────────────────────────────────── */
@media (max-width: 680px) {
    #dm-overlay {
        align-items: flex-end;
        padding: 0;
        background: rgba(15, 23, 42, .44);
    }

    .dm-box {
        position: relative;
        width: 100%;
        max-height: 94dvh;
        border-right: 0;
        border-bottom: 0;
        border-left: 0;
        border-radius: 17px 17px 0 0;
        box-shadow: 0 -16px 44px rgba(15, 23, 42, .22);
    }

    .dm-box::before {
        position: absolute;
        z-index: 5;
        top: 6px;
        left: 50%;
        width: 38px;
        height: 4px;
        border-radius: 999px;
        background: rgba(100, 116, 139, .30);
        content: "";
        transform: translateX(-50%);
    }

    .dm-head {
        padding: .82rem .62rem .56rem;
    }

    .dm-title {
        font-size: .86rem;
    }

    .dm-subtitle {
        font-size: .63rem;
    }

    .dm-close-btn {
        width: 34px;
        height: 34px;
    }

    .dm-progress-wrap {
        padding: .5rem .62rem;
    }

    .dm-progress-labels {
        font-size: .6rem;
    }

    .dm-body {
        gap: .58rem;
        padding: .58rem .62rem .68rem;
    }

    .dm-existing-row {
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .28rem .46rem;
        min-height: 0;
        padding: .48rem;
    }

    .dm-existing-customer {
        grid-column: 1;
        grid-row: 1;
    }

    .dm-existing-actions {
        grid-column: 2;
        grid-row: 1;
    }

    .dm-existing-qty,
    .dm-existing-net {
        grid-row: 2;
        text-align: left;
    }

    .dm-existing-qty {
        grid-column: 1;
    }

    .dm-existing-net {
        grid-column: 2;
        min-width: 0;
        text-align: right;
    }

    .dm-inline-edit {
        grid-template-columns: minmax(0, 1fr) 102px auto;
    }

    .dm-row {
        grid-template-columns: minmax(0, 1fr) 96px 38px;
        gap: .3rem;
        padding: .4rem;
    }

    .dm-row select,
    .dm-row input[type=number] {
        min-height: 42px;
        padding-right: .42rem;
        padding-left: .42rem;
        font-size: .69rem;
    }

    .dm-row .dm-rm-btn {
        height: 42px;
    }

    .dm-add-btn {
        min-height: 35px;
        padding: .34rem .42rem;
        font-size: .59rem;
    }

    .dm-foot {
        gap: .34rem;
        padding-right: .62rem;
        padding-left: .62rem;
    }

    .dm-shortcuts {
        display: none;
    }

    #dm-overlay .dm-foot .btn {
        min-height: 42px;
    }

    #dm-overlay .dm-foot .btn-ghost {
        width: 42px;
        min-width: 42px;
        padding: 0;
        overflow: hidden;
        font-size: 0;
    }

    #dm-overlay .dm-foot .btn-ghost::before {
        content: "×";
        font-size: 1rem;
        line-height: 1;
    }

    #dm-overlay .dm-foot .btn-primary {
        flex: 1 1 auto;
    }

    #dm-confirm-overlay,
    #dm-notice-overlay {
        align-items: flex-end;
        padding: 0;
    }

    .dm-confirm-box,
    .dm-notice-box {
        width: 100%;
        max-width: none;
        border-right: 0;
        border-bottom: 0;
        border-left: 0;
        border-radius: 16px 16px 0 0;
    }

    .dm-confirm-box {
        padding:
            .76rem .68rem
            max(.76rem, env(safe-area-inset-bottom));
    }

    .dm-confirm-actions .btn,
    .dm-notice-actions .btn {
        flex: 1 1 0;
        min-height: 42px;
    }
}

@media (max-width: 420px) {
    .dm-progress-labels {
        grid-template-columns: 1fr;
        gap: .12rem;
    }

    .dm-progress-labels > span:last-child {
        text-align: left;
    }

    .dm-row {
        grid-template-columns: minmax(0, 1fr) 88px 36px;
    }

    .dm-inline-edit {
        grid-template-columns: minmax(0, 1fr) 92px;
    }

    .dm-inline-edit-actions {
        grid-column: 1 / -1;
        justify-content: flex-end;
    }

    .dm-add-btn + .dm-add-btn {
        margin-left: .1rem;
    }
}

@media (max-width: 350px) {
    .dm-row {
        grid-template-columns: minmax(0, 1fr) 36px;
    }

    .dm-row select {
        grid-column: 1 / -1;
    }

    .dm-row input[type=number] {
        grid-column: 1;
    }

    .dm-row .dm-rm-btn {
        grid-column: 2;
    }

    .dm-add-btn {
        width: 100%;
        justify-content: center;
        margin-left: 0 !important;
    }
}

@media (prefers-reduced-motion: reduce) {
    #dm-overlay *,
    #dm-overlay *::before,
    #dm-overlay *::after,
    #dm-confirm-overlay *,
    #dm-confirm-overlay *::before,
    #dm-confirm-overlay *::after,
    #dm-notice-overlay *,
    #dm-notice-overlay *::before,
    #dm-notice-overlay *::after {
        animation-duration: .01ms !important;
        animation-iteration-count: 1 !important;
        scroll-behavior: auto !important;
        transition-duration: .01ms !important;
    }
}
</style>

{{-- ══ HTML ═════════════════════════════════════════════════════════════ --}}
<div id="dm-overlay">
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
                <button type="button" class="dm-notes-btn" id="dm-notes-btn">Observações</button>
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
                <button type="button" class="dm-add-btn" id="dm-fill-default-btn" style="margin-top:.4rem;display:none" onclick="DistModal.fillAvailable()" aria-label="Preencher o saldo para o cliente padrão">
                    Preencher saldo disponível
                </button>
            </div>
        </div>

        {{-- Footer --}}
        <div class="dm-foot">
            <span class="dm-shortcuts">Enter: salvar · Esc: fechar · Alt+A: adicionar cliente</span>
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
<div id="dm-confirm-overlay">
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

<div id="dm-notice-overlay">
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
let _defaultCustomerId = null;
let _singleDefaultCustomerId = null;
let _notes = '';

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
function buildRow(preselectId = null, autofocus = false, initialQuantity = null) {
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
    if (initialQuantity !== null && Number(initialQuantity) > 0) {
        inp.value = Number(initialQuantity).toFixed(3).replace(/\.?0+$/, '');
    }
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
            defaultCustomerId: btn.dataset.defaultCustomerId || null,
            notes: btn.dataset.notes || '',
        });
    },

    /** Open with explicit config */
    open(cfg) {
        _id       = cfg.id;
        _unit     = cfg.unit      || 'un';
        _totalQty = cfg.qty       || 0;
        _distQty  = cfg.distributed || 0;
        _defaultCustomerId = cfg.defaultCustomerId ? String(cfg.defaultCustomerId) : null;
        _notes = String(cfg.notes || '').trim();

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
        _singleDefaultCustomerId = _defaultCustomerId
            && _activeCustomers.length === 1
            && String(_activeCustomers[0].id) === _defaultCustomerId
                ? _defaultCustomerId
                : null;
        const availableQuantity = Math.max(0, _totalQty - _distQty);
        // Determine which customers are already fully existing (all listed = skip pre-populating those)
        const existingIds = new Set((cfg.existing || []).map(d => d.customer_id || d.customerId).filter(Boolean).map(String));
        // Filter participants to those not yet in existing
        const toPreload = participants.filter(id => _activeCustomers.some(c => c.id == id) && !existingIds.has(String(id)));
        if (_singleDefaultCustomerId && availableQuantity > 0.0005) {
            $('dm-new-rows').appendChild(buildRow(_singleDefaultCustomerId, false, availableQuantity));
        } else if (toPreload.length > 0) {
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
        $('dm-fill-default-btn').style.display = _singleDefaultCustomerId ? '' : 'none';
        $('dm-notes-btn').classList.toggle('visible', !!_notes);
        $('dm-notes-btn').onclick = () => window.DeliveryNotesModal?.open(
            _notes,
            'Observações da entrega',
            (cfg.product || '') + ' · ' + fmt(_totalQty, _unit),
        );

        // Focus first useful field for keyboard accessibility
        setTimeout(() => {
            const target = (_singleDefaultCustomerId || toPreload.length > 0)
                ? $('dm-new-rows').querySelector('input[type=number]')
                : $('dm-new-rows').querySelector('select');
            target?.focus();
            if (target?.select) target.select();
        }, 80);
    },

    close() {
        $('dm-overlay').classList.remove('dm-open');
        _id = null;
        _notes = '';
        _singleDefaultCustomerId = null;
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

    fillAvailable() {
        if (!_singleDefaultCustomerId) return;
        const available = Math.max(0, _totalQty - _distQty);
        const rows = $('dm-new-rows');
        rows.innerHTML = '';
        const row = buildRow(_singleDefaultCustomerId, false, available);
        rows.appendChild(row);
        updateProgress();
        setTimeout(() => row.querySelector('input')?.focus(), 30);
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
        if (!_id || $('dm-save-btn').disabled) return;

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
        if (!distributions.length) { 
                this.close();
                if (typeof window._DistModalReload === 'function') {
                    window._DistModalReload(data);
                } else {
                    location.reload();
                }
                
            
         }

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
        _noticeAction = () => this.saveQuickPrice();
        action.onclick = _noticeAction;
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
document.addEventListener('keydown', event => {
    if (!$('dm-overlay').classList.contains('dm-open')) return;

    if ($('dm-confirm-overlay').classList.contains('open')) {
        if (event.key === 'Escape') {
            event.preventDefault();
            event.stopImmediatePropagation();
            DistModal.cancelDangerConfirm();
        } else if (event.key === 'Enter') {
            event.preventDefault();
            event.stopImmediatePropagation();
            DistModal.acceptDangerConfirm();
        }
        return;
    }

    if ($('dm-notice-overlay').classList.contains('open')) {
        if (event.key === 'Escape') {
            event.preventDefault();
            event.stopImmediatePropagation();
            DistModal.closeNotice();
        } else if (event.key === 'Enter' && _noticeAction) {
            event.preventDefault();
            event.stopImmediatePropagation();
            _noticeAction();
        }
        return;
    }

    if (window.DeliveryNotesModal?.isOpen?.()) return;

    if (event.key === 'Escape') {
        event.preventDefault();
        event.stopImmediatePropagation();
        DistModal.close();
        return;
    }

    if (event.altKey && event.key.toLowerCase() === 'a') {
        event.preventDefault();
        event.stopImmediatePropagation();
        DistModal.addRow();
        return;
    }

    if (event.key !== 'Enter' || event.shiftKey || event.isComposing) return;
    const inlineEdit = event.target.closest?.('.dm-inline-edit');
    if (inlineEdit) {
        const distributionId = inlineEdit.closest('[id^="dmex-"]')?.id.replace('dmex-', '');
        if (distributionId) {
            event.preventDefault();
            event.stopImmediatePropagation();
            DistModal.saveExistingEdit(distributionId);
        }
        return;
    }
    if (event.target.matches?.('select, button')) {
        event.stopImmediatePropagation();
        return;
    }
    event.preventDefault();
    event.stopImmediatePropagation();
    DistModal.save();
});

window.openDistModal  = (btn) => DistModal.openFromBtn(btn);
window.closeDistModal = ()    => DistModal.close();

})();
</script>