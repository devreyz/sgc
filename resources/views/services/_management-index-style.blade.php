<style>
    .sg-orders {
        grid-column: 1 / -1;
        display: grid;
        gap: 1rem;
        min-width: 0;
        container: service-orders / inline-size;
        color: var(--ui-color-text);
    }

    .sg-orders :is(.sg-toolbar, .sg-filters, .sg-scroll, .sg-dialog) {
        border: 1px solid var(--ui-color-border);
        border-radius: var(--ui-radius-lg);
        background: var(--ui-color-surface);
        box-shadow: var(--ui-shadow-xs);
    }

    .sg-orders .sg-toolbar { padding: clamp(1rem, 2vw, 1.5rem); }
    .sg-orders .sg-toolbar h2 { margin: 0; font-size: var(--ui-font-size-2xl); font-weight: 800; line-height: 1.15; }
    .sg-orders .sg-toolbar p { margin: .35rem 0 0; }
    .sg-orders :is(.sg-toolbar p, small, .sg-hint) { color: var(--ui-color-text-secondary); font-size: .8125rem; }

    .sg-orders :is(.sg-toolbar, .sg-actions) { display: flex; align-items: center; flex-wrap: wrap; gap: .5rem; }
    .sg-orders .sg-toolbar { justify-content: space-between; gap: 1rem; }

    .sg-orders .sg-filters {
        display: grid;
        grid-template-columns: minmax(12rem, 2fr) repeat(3, minmax(8rem, 1fr)) auto;
        align-items: end;
        gap: .75rem;
        padding: 1rem;
    }

    .sg-orders label { display: grid; align-content: start; gap: .35rem; min-width: 0; font-size: .8125rem; font-weight: 600; }
    .sg-orders :is(input:not([type="hidden"]), select, textarea) {
        width: 100%;
        min-width: 0;
        min-height: 2.625rem;
        padding: .5rem .65rem;
        border: 1px solid var(--ui-color-border-strong);
        border-radius: var(--ui-radius-sm);
        background: var(--ui-color-surface);
        color: inherit;
        font-size: .875rem;
    }

    .sg-orders :focus-visible { outline: 2px solid var(--ui-color-focus); outline-offset: 2px; }
    .sg-orders :is(.sg-button, .sg-link) { font-size: .8125rem; font-weight: 700; cursor: pointer; }
    .sg-orders .sg-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .4rem;
        min-height: 2.625rem;
        padding: .45rem .7rem;
        border: 1px solid var(--ui-color-border);
        border-radius: var(--ui-radius-sm);
        background: var(--ui-color-surface);
        color: var(--ui-color-text);
        text-decoration: none;
    }
    .sg-orders .sg-button:hover { background: var(--ui-color-surface-soft); }
    .sg-orders .sg-primary { background: var(--ui-color-primary); border-color: transparent; color: var(--sgc-on-primary); }
    .sg-orders .sg-primary:hover { background: var(--ui-color-primary-strong); }
    .sg-orders .sg-link { color: var(--ui-color-primary); text-decoration: underline; text-underline-offset: .18em; }
    .sg-orders .sg-danger { color: var(--ui-color-danger-strong); }

    .sg-orders .sg-scroll { overflow: auto; }
    .sg-orders .sg-table { width: 100%; min-width: 63rem; border-collapse: separate; border-spacing: 0; font-size: .875rem; }
    .sg-orders .sg-table :is(th, td) { padding: .8rem .7rem; text-align: left; vertical-align: top; }
    .sg-orders .sg-table thead th {
        background: var(--ui-color-surface-soft);
        color: var(--ui-color-text-secondary);
        font-size: .75rem;
        font-weight: 700;
    }
    .sg-orders .sg-table tbody > tr > * { border-top: 1px solid var(--ui-color-border); }
    .sg-orders .sg-order:hover { background: var(--ui-color-surface-soft); }
    .sg-orders .sg-table small { display: block; margin-top: .2rem; font-weight: 400; }
    .sg-orders .sg-number { font-variant-numeric: tabular-nums; white-space: nowrap; }
    .sg-orders .sg-row-actions .sg-button { min-height: 2.125rem; padding: .25rem .5rem; }
    .sg-orders .sg-empty { padding: 2rem 1rem; text-align: center; }
    .sg-orders .sg-empty i { display: block; margin-bottom: .5rem; color: var(--ui-color-primary); font-size: 1.5rem; }
    .sg-orders .sg-mobile-label { display: none; }

    .sg-orders .sg-state {
        display: inline-block;
        padding: .2rem .5rem;
        border-radius: 999px;
        background: var(--ui-color-neutral-soft);
        color: var(--ui-color-neutral-strong);
        font-size: .75rem;
        font-weight: 700;
    }
    .sg-orders .sg-state[data-state="in_progress"] { background: var(--ui-color-info-soft); color: var(--ui-color-info-strong); }
    .sg-orders .sg-state[data-state="validated"], .sg-orders .sg-message { background: var(--ui-color-success-soft); color: var(--ui-color-success-strong); }
    .sg-orders .sg-state[data-state="submitted"] { background: var(--ui-color-warning-soft); color: var(--ui-color-warning-strong); }
    .sg-orders .sg-state:is([data-state="rejected"], [data-state="cancelled"]), .sg-orders .sg-error { background: var(--ui-color-danger-soft); color: var(--ui-color-danger-strong); }

    .sg-orders .sg-message { margin: 0; padding: .75rem 1rem; border-radius: var(--ui-radius-sm); }
    .sg-orders .sg-error { list-style: disc; padding-inline-start: 2rem; }
    .sg-orders .sg-inline > td { background: var(--ui-color-surface-soft); }
    .sg-orders .sg-inline form, .sg-orders .sg-dialog form { display: grid; gap: .85rem; }
    .sg-orders .sg-fields { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 10rem), 1fr)); gap: .75rem; }

    .sg-orders .sg-dialog { width: min(calc(100% - 2rem), 28rem); max-height: 88dvh; margin: auto; padding: 1.25rem; color: inherit; }
    .sg-orders .sg-dialog::backdrop { background: var(--ui-color-overlay); }
    .sg-orders .sg-dialog h3 { margin: 0; font-size: 1.125rem; font-weight: 800; }
    .sg-orders .sg-dialog .sg-actions { justify-content: flex-end; }

    @container service-orders (max-width: 63rem) {
        .sg-orders .sg-filters { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        .sg-orders .sg-search { grid-column: span 2; }
        .sg-orders .sg-filter-actions { grid-column: span 2; }
        .sg-orders .sg-table { display: block; min-width: 0; }
        .sg-orders .sg-table thead { position: absolute; width: 1px; height: 1px; overflow: hidden; clip-path: inset(50%); }
        .sg-orders .sg-table tbody { display: grid; }
        .sg-orders .sg-table tr.sg-order { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .75rem; padding: 1rem; border-top: 1px solid var(--ui-color-border); }
        .sg-orders .sg-table tr.sg-order:first-child { border-top: 0; }
        .sg-orders .sg-table tr.sg-order > * { min-width: 0; padding: 0; border: 0; overflow-wrap: anywhere; }
        .sg-orders .sg-order th { grid-column: span 2; }
        .sg-orders .sg-table .sg-status { grid-column: 3; grid-row: 1; text-align: right; }
        .sg-orders .sg-order-actions { grid-column: 1 / -1; }
        .sg-orders .sg-mobile-label { display: block; margin-bottom: .2rem; color: var(--ui-color-text-secondary); font-size: .75rem; font-weight: 600; }
        .sg-orders .sg-table tr:not(.sg-order) { display: grid; }
        .sg-orders .sg-table tr[hidden] { display: none; }
    }

    @container service-orders (max-width: 36rem) {
        .sg-orders .sg-filters, .sg-orders .sg-table tr.sg-order { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .sg-orders .sg-order th { grid-column: 1; }
        .sg-orders .sg-table .sg-status { grid-column: 2; }
    }
</style>
