<div class="order-view-switch" role="group" aria-label="Formato da listagem" hidden>
    <button type="button" data-view="table" aria-pressed="false"><i class="ph ph-table" aria-hidden="true"></i> Tabela</button>
    <button type="button" data-view="cards" aria-pressed="false"><i class="ph ph-squares-four" aria-hidden="true"></i> Cards</button>
</div>
<style>
    .order-view-switch[hidden] { display: none; }
    .order-view-switch { display: inline-flex; gap: .2rem; padding: .2rem; border: 1px solid var(--ui-color-border); border-radius: 10px; background: var(--ui-color-surface-soft); }
    .order-view-switch button { display: inline-flex; align-items: center; gap: .35rem; padding: .45rem .65rem; min-height: 40px; border-radius: 7px; color: var(--ui-color-text-secondary); font-weight: 700; font-size: .76rem; }
    .order-view-switch button[aria-pressed="true"] { background: var(--sgc-rail, #173c2e); color: var(--sgc-rail-text, #fff); }
    .order-view-switch button:focus-visible { outline: 2px solid var(--ui-color-primary); outline-offset: 2px; }
</style>
<script>
(() => {
    const list = document.currentScript.closest('[data-order-list]');
    const control = list.querySelector('.order-view-switch');
    const buttons = control.querySelectorAll('[data-view]');
    const breakpoint = matchMedia(list.dataset.compactQuery);
    const key = 'sgc.orders.view.' + list.dataset.orderList;
    let preference;
    try { preference = localStorage.getItem(key); } catch {}

    function update() {
        const view = preference === 'table' || preference === 'cards'
            ? preference : (breakpoint.matches ? 'cards' : 'table');
        list.dataset.orderView = view;
        buttons.forEach(button => button.setAttribute('aria-pressed', String(button.dataset.view === view)));
    }

    control.addEventListener('click', event => {
        const button = event.target.closest('[data-view]');
        if (!button) return;
        preference = button.dataset.view;
        try { localStorage.setItem(key, preference); } catch {}
        update();
    });
    breakpoint.addEventListener('change', update);
    update();
    control.hidden = false;
})();
</script>
