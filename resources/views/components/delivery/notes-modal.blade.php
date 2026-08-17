<style>
/* ═══════════════════════════════════════════════════════════════════════
   DELIVERY NOTES MODAL — somente camada visual/UX.
   A lógica JavaScript permanece intacta.
   ═══════════════════════════════════════════════════════════════════════ */

#delivery-notes-overlay {
    --dn-blue: #2563eb;
    --dn-blue-soft: #eef4ff;
    --dn-violet: #7c3aed;
    --dn-violet-soft: #f4f0ff;
    --dn-slate: #64748b;
    --dn-slate-soft: #f1f5f9;

    --dn-surface: var(--color-surface, #fff);
    --dn-soft: var(--color-surface-soft, #f8faf9);
    --dn-border: var(--color-border, #dce7e0);
    --dn-border-strong: var(--color-border-strong, #c8d6cd);
    --dn-text: var(--color-text, #102018);
    --dn-text-2: var(--color-text-secondary, #52645a);
    --dn-text-3: var(--color-text-muted, #809087);

    position: fixed;
    z-index: 330000;
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

#delivery-notes-overlay.open {
    display: flex;
}

#delivery-notes-overlay *,
#delivery-notes-overlay *::before,
#delivery-notes-overlay *::after {
    box-sizing: border-box;
}

/* ── Painel ───────────────────────────────────────────────────────── */
.delivery-notes-box {
    display: flex;
    width: min(560px, 100%);
    max-height: min(82dvh, 650px);
    min-height: 0;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid var(--dn-border);
    border-radius: 15px;
    background: var(--dn-surface);
    color: var(--dn-text);
    box-shadow: 0 24px 68px rgba(15, 23, 42, .22);
}

/* ── Cabeçalho ────────────────────────────────────────────────────── */
.delivery-notes-head {
    display: grid;
    flex: 0 0 auto;
    min-width: 0;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: .65rem;
    align-items: center;
    min-height: 66px;
    padding: .7rem .76rem;
    border-bottom: 1px solid var(--dn-border);
    background:
        radial-gradient(circle at 100% 0, rgba(37, 99, 235, .09), transparent 14rem),
        linear-gradient(180deg, var(--dn-soft), #fff);
}

.delivery-notes-head > div {
    min-width: 0;
}

.delivery-notes-title,
.delivery-notes-meta {
    margin: 0;
}

.delivery-notes-title {
    color: var(--dn-text);
    font-size: .92rem;
    font-weight: 850;
    letter-spacing: -.025em;
    line-height: 1.28;
}

.delivery-notes-meta {
    margin-top: .12rem;
    overflow: hidden;
    color: var(--dn-text-3);
    font-size: .65rem;
    font-weight: 650;
    line-height: 1.35;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.delivery-notes-meta[hidden] {
    display: none;
}

.delivery-notes-close {
    display: inline-flex;
    width: 36px;
    height: 36px;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    border: 1px solid var(--dn-border);
    border-radius: 9px;
    background: #fff;
    color: var(--dn-text-2);
    cursor: pointer;
    font: inherit;
    font-size: 1rem;
    line-height: 1;
    transition:
        border-color 140ms ease,
        background 140ms ease,
        color 140ms ease,
        transform 140ms ease;
}

.delivery-notes-close:hover,
.delivery-notes-close:focus-visible {
    border-color: rgba(37, 99, 235, .18);
    background: var(--dn-blue-soft);
    color: var(--dn-blue);
    outline: none;
}

/* ── Conteúdo ─────────────────────────────────────────────────────── */
.delivery-notes-content {
    min-height: 0;
    flex: 1 1 auto;
    max-height: none;
    margin: 0;
    padding: .78rem;
    overflow: auto;
    overscroll-behavior: contain;
    color: var(--dn-text);
    font-size: .76rem;
    line-height: 1.62;
    overflow-wrap: anywhere;
    white-space: pre-wrap;
    scrollbar-width: thin;
}

/*
 * A observação ganha uma superfície de leitura própria sem criar
 * um "card dentro do card" pesado.
 */
.delivery-notes-content::first-line {
    font-weight: 500;
}

/* ── Rodapé ───────────────────────────────────────────────────────── */
.delivery-notes-foot {
    display: flex;
    flex: 0 0 auto;
    justify-content: flex-end;
    gap: .4rem;
    padding:
        .6rem .76rem
        max(.6rem, env(safe-area-inset-bottom));
    border-top: 1px solid var(--dn-border);
    background:
        linear-gradient(
            180deg,
            rgba(255,255,255,.96),
            #fff
        );
}

#delivery-notes-overlay .delivery-notes-foot .btn {
    display: inline-flex;
    min-height: 38px;
    align-items: center;
    justify-content: center;
    padding: .42rem .62rem;
    border: 1px solid rgba(37, 99, 235, .16);
    border-radius: 9px;
    background: var(--dn-blue-soft);
    color: var(--dn-blue);
    cursor: pointer;
    font: inherit;
    font-size: .67rem;
    font-weight: 790;
    line-height: 1;
    transition:
        transform 140ms ease,
        border-color 140ms ease,
        background 140ms ease;
}

#delivery-notes-overlay .delivery-notes-foot .btn:hover,
#delivery-notes-overlay .delivery-notes-foot .btn:focus-visible {
    outline: none;
    transform: translateY(-1px);
}

/* ── Gatilho usado em outras telas ────────────────────────────────── */
.delivery-note-trigger {
    display: inline-flex;
    min-height: 30px;
    align-items: center;
    justify-content: center;
    gap: .26rem;
    padding: .28rem .42rem;
    border: 1px solid var(--dn-border, var(--color-border));
    border-radius: 8px;
    background: #fff;
    color: var(--dn-slate, var(--color-text-secondary));
    cursor: pointer;
    font: inherit;
    font-size: .64rem;
    font-weight: 760;
    line-height: 1;
    white-space: nowrap;
    transition:
        border-color 140ms ease,
        background 140ms ease,
        color 140ms ease,
        transform 140ms ease;
}

.delivery-note-trigger:hover,
.delivery-note-trigger:focus-visible {
    border-color: rgba(100, 116, 139, .22);
    background: var(--dn-slate-soft, #f1f5f9);
    color: var(--dn-slate, #64748b);
    outline: none;
    transform: translateY(-1px);
}

/* ── Mobile: bottom sheet ─────────────────────────────────────────── */
@media (max-width: 680px) {
    #delivery-notes-overlay {
        align-items: flex-end;
        padding: 0;
        background: rgba(15, 23, 42, .42);
    }

    .delivery-notes-box {
        position: relative;
        width: 100%;
        max-height: min(82dvh, 680px);
        border-right: 0;
        border-bottom: 0;
        border-left: 0;
        border-radius: 17px 17px 0 0;
        box-shadow: 0 -16px 44px rgba(15, 23, 42, .22);
    }

    .delivery-notes-box::before {
        position: absolute;
        z-index: 3;
        top: 6px;
        left: 50%;
        width: 38px;
        height: 4px;
        border-radius: 999px;
        background: rgba(100, 116, 139, .30);
        content: "";
        transform: translateX(-50%);
    }

    .delivery-notes-head {
        min-height: 60px;
        padding: .82rem .62rem .52rem;
    }

    .delivery-notes-title {
        font-size: .86rem;
    }

    .delivery-notes-meta {
        font-size: .61rem;
    }

    .delivery-notes-close {
        width: 34px;
        height: 34px;
    }

    .delivery-notes-content {
        padding: .68rem .62rem .76rem;
        font-size: .74rem;
        line-height: 1.58;
    }

    .delivery-notes-foot {
        padding-right: .62rem;
        padding-left: .62rem;
    }

    #delivery-notes-overlay .delivery-notes-foot .btn {
        width: 100%;
        min-height: 42px;
    }
}

@media (prefers-reduced-motion: reduce) {
    #delivery-notes-overlay *,
    #delivery-notes-overlay *::before,
    #delivery-notes-overlay *::after {
        animation-duration: .01ms !important;
        animation-iteration-count: 1 !important;
        scroll-behavior: auto !important;
        transition-duration: .01ms !important;
    }
}
</style>

<div id="delivery-notes-overlay" aria-hidden="true">
    <section class="delivery-notes-box" role="dialog" aria-modal="true" aria-labelledby="delivery-notes-title">
        <header class="delivery-notes-head">
            <div>
                <h2 class="delivery-notes-title" id="delivery-notes-title">Observações da entrega</h2>
                <p class="delivery-notes-meta" id="delivery-notes-meta"></p>
            </div>
            <button class="delivery-notes-close" type="button" data-close-delivery-notes aria-label="Fechar observações">×</button>
        </header>
        <p class="delivery-notes-content" id="delivery-notes-content"></p>
        <footer class="delivery-notes-foot">
            <button class="btn btn-primary btn-sm" type="button" data-close-delivery-notes>Fechar</button>
        </footer>
    </section>
</div>

<script>
(() => {
    const overlay = document.getElementById('delivery-notes-overlay');
    if (!overlay || window.DeliveryNotesModal) return;

    const title = document.getElementById('delivery-notes-title');
    const meta = document.getElementById('delivery-notes-meta');
    const content = document.getElementById('delivery-notes-content');
    let returnFocus = null;

    window.DeliveryNotesModal = {
        open(notes, heading = 'Observações da entrega', context = '') {
            const value = String(notes || '').trim();
            if (!value) return;
            returnFocus = document.activeElement;
            title.textContent = heading || 'Observações da entrega';
            meta.textContent = context || '';
            meta.hidden = !context;
            content.textContent = value;
            overlay.classList.add('open');
            overlay.setAttribute('aria-hidden', 'false');
            setTimeout(() => overlay.querySelector('[data-close-delivery-notes]')?.focus(), 30);
        },
        close() {
            overlay.classList.remove('open');
            overlay.setAttribute('aria-hidden', 'true');
            returnFocus?.focus?.();
            returnFocus = null;
        },
        isOpen() {
            return overlay.classList.contains('open');
        },
    };

    document.addEventListener('click', event => {
        const trigger = event.target.closest('[data-delivery-notes]');
        if (trigger) {
            event.preventDefault();
            window.DeliveryNotesModal.open(
                trigger.dataset.deliveryNotes,
                trigger.dataset.deliveryNotesTitle,
                trigger.dataset.deliveryNotesMeta,
            );
            return;
        }
        if (event.target.closest('[data-close-delivery-notes]')) {
            window.DeliveryNotesModal.close();
        }
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && window.DeliveryNotesModal.isOpen()) {
            event.preventDefault();
            event.stopImmediatePropagation();
            window.DeliveryNotesModal.close();
        }
    });
})();
</script>