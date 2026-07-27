<style>
    #delivery-notes-overlay {
        position:fixed; inset:0; z-index:330000; display:none;
        align-items:center; justify-content:center; padding:1rem;
        background:rgba(15,23,42,.54); backdrop-filter:blur(3px);
    }
    #delivery-notes-overlay.open { display:flex; }
    .delivery-notes-box {
        width:min(500px,96vw); max-height:min(620px,88vh); overflow:hidden;
        border:1px solid var(--color-border); border-radius:8px;
        background:var(--color-surface); color:var(--color-text);
        box-shadow:0 24px 64px rgba(15,23,42,.3);
    }
    .delivery-notes-head {
        display:flex; align-items:flex-start; justify-content:space-between; gap:.75rem;
        padding:.9rem 1rem; border-bottom:1px solid var(--color-border);
    }
    .delivery-notes-title { margin:0; font-size:.96rem; font-weight:800; }
    .delivery-notes-meta { margin:.2rem 0 0; color:var(--color-text-secondary); font-size:.72rem; }
    .delivery-notes-close {
        width:34px; height:34px; display:grid; place-items:center; flex:none;
        border:1px solid var(--color-border); border-radius:7px;
        background:var(--color-surface); color:var(--color-text); cursor:pointer;
    }
    .delivery-notes-content {
        max-height:440px; overflow:auto; margin:0; padding:1rem;
        white-space:pre-wrap; overflow-wrap:anywhere; font-size:.86rem; line-height:1.6;
    }
    .delivery-notes-foot {
        display:flex; justify-content:flex-end; padding:.75rem 1rem;
        border-top:1px solid var(--color-border);
    }
    .delivery-note-trigger {
        display:inline-flex; align-items:center; justify-content:center; gap:.3rem;
        min-height:32px; padding:.32rem .48rem; border:1px solid var(--color-border);
        border-radius:6px; background:var(--color-surface); color:var(--color-text-secondary);
        font:inherit; font-size:.7rem; font-weight:750; cursor:pointer;
    }
    .delivery-note-trigger:hover { border-color:var(--color-primary); color:var(--color-primary); }
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
