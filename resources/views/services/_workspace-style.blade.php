<style>
    .service-grid { grid-column: 1 / -1; display: grid; gap: 1rem; min-width: 0; width: 100%; color: var(--text-primary, #172823); }
    .service-grid h1, .service-grid h2, .service-grid p { margin: 0; }
    .service-grid h1 { font-size: clamp(1.45rem, 3vw, 2rem); line-height: 1.12; }
    .sg-toolbar, .sg-filters, .sg-panel { padding: 1rem; border: 1px solid #d9e4de; border-radius: .85rem; background: #fff; }
    .sg-toolbar, .sg-actions, .sg-filters { display: flex; flex-wrap: wrap; align-items: center; gap: .65rem; }
    .sg-toolbar { justify-content: space-between; }
    .sg-toolbar p, .sg-hint, .sg-table small { color: #52645c; font-size: .82rem; }
    .sg-toolbar p { margin-top: .3rem; }
    .sg-panel { display: grid; gap: .8rem; }
    .sg-panel h2, .sg-dialog h2 { font-size: 1.05rem; }
    .sg-filters { align-items: end; }
    .sg-filters label:first-child { flex: 1 1 18rem; }
    .service-grid label { display: grid; gap: .32rem; font-weight: 600; font-size: .84rem; }
    .service-grid input, .service-grid select, .service-grid textarea { min-width: 0; width: 100%; min-height: 2.45rem; padding: .45rem .6rem; border: 1px solid #c8d8d0; border-radius: .4rem; color: inherit; background: #fff; font: inherit; font-size: .88rem; }
    .service-grid input:focus-visible, .service-grid select:focus-visible, .service-grid button:focus-visible, .service-grid a:focus-visible { outline: 2px solid #26775d; outline-offset: 2px; }
    .sg-button { display: inline-flex; align-items: center; justify-content: center; min-height: 2.45rem; padding: .45rem .8rem; border: 1px solid #c7d8ce; border-radius: .5rem; background: #fff; color: #214638; text-decoration: none; font: inherit; font-size: .85rem; font-weight: 650; cursor: pointer; }
    .sg-primary { background: #176348; color: #fff; border-color: #176348; }
    .sg-button:disabled { opacity: .55; cursor: default; }
    .sg-scroll { overflow: auto; max-height: min(70dvh, 55rem); border: 1px solid #d9e4de; border-radius: .65rem; background: #fff; }
    .sg-table { width: 100%; min-width: 48rem; border-spacing: 0; font-size: .86rem; }
    .sg-table th, .sg-table td { text-align: left; padding: .62rem .7rem; border-bottom: 1px solid #e1e9e4; border-right: 1px solid #e1e9e4; vertical-align: middle; }
    .sg-table tr > :last-child { border-right: 0; }
    .sg-table thead th { position: sticky; top: 0; z-index: 2; background: #eaf2ed; white-space: nowrap; }
    .sg-table tbody tr:nth-child(even) { background: #f7faf8; }
    .sg-table tbody tr:focus-within, .sg-table tbody tr:hover { background: #eff7f2; }
    .sg-table small { display: block; margin-top: .15rem; font-weight: 400; }
    .sg-table a, .sg-link { color: #14604a; font-weight: 650; }
    .sg-table .sg-number { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .sg-table .sg-empty { padding: 2rem; text-align: center; }
    .sg-row-actions { flex-wrap: nowrap; white-space: nowrap; }
    .sg-orders .sg-table th:first-child { min-width: 9rem; }
    .sg-orders .sg-table tbody th[scope="row"] { background: #f3f7f4; }
    .sg-orders .sg-table tbody td:nth-child(2) { white-space: nowrap; }
    .sg-orders .sg-table tbody th a { font-variant-numeric: tabular-nums; text-decoration-thickness: 1px; text-underline-offset: 3px; }
    .sg-orders .sg-row-actions .sg-button { min-height: 2rem; padding: .25rem .5rem; }
    .sg-orders .sg-row-edit { background: #edf6f1; }
    .sg-inline > td { background: #f1f7f3; padding: .9rem; }
    .sg-inline form { display: grid; gap: .75rem; max-width: 75rem; }
    .sg-inline .sg-fields { grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr)); }
    .sg-inline .sg-actions { margin-top: .15rem; }
    .sg-inline[hidden], .agreements-workspace [hidden] { display: none !important; }
    .sg-link { background: transparent; border: 0; padding: 0; cursor: pointer; font: inherit; text-decoration: underline; }
    .sg-danger { color: #a33129; }
    .sg-state { display: inline-block; white-space: nowrap; padding: .18rem .48rem; border-radius: 1rem; background: #e8eff9; color: #244675; font-size: .76rem; font-weight: 650; }
    .sg-state[data-state="validated"] { background: #dcefe4; color: #21543f; }
    .sg-state[data-state="cancelled"], .sg-state[data-state="rejected"] { background: #fae7e3; color: #862c26; }
    .sg-state[data-state="submitted"] { background: #fff0ce; color: #76530b; }
    .sg-dialog { width: min(94vw, 38rem); max-height: 88dvh; margin: auto; border: 1px solid #c7d8ce; border-radius: .85rem; padding: 1.1rem; color: #172823; }
    .sg-dialog:not([open]) { display: none; }
    .sg-dialog::backdrop { background: #11271d80; }
    .sg-dialog form, .sg-dialog > div, .sg-dialog label { display: grid; gap: .8rem; }
    .sg-message { margin: 0; padding: .65rem .85rem; border-radius: .5rem; background: #e7f4ec; color: #1b503a; }
    .sg-error { padding-left: 1.85rem; background: #ffede9; color: #822c25; }
    .sg-entry .sg-table { min-width: 66rem; }
    .sg-entry .sg-table td { padding: .25rem; vertical-align: top; }
    .sg-entry .sg-table td input, .sg-entry .sg-table td select { min-width: 8rem; }
    .sg-entry [hidden] { display: none !important; }
    .sg-fields { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 13rem), 1fr)); gap: .65rem; }
    .sg-mobile-label { display: none; }
    @media (max-width: 680px) {
        .sg-orders .sg-filters > label { flex: 1 1 8rem; }
        .sg-orders .sg-filters > label:first-child { flex-basis: 100%; }
        .sg-toolbar, .sg-panel, .sg-filters { padding: .75rem; }
        .sg-entry .sg-scroll { max-height: none; }
        .sg-entry .sg-table { display: block; min-width: 0; }
        .sg-entry .sg-table thead { display: none; }
        .sg-entry .sg-table tbody, .sg-entry .sg-table tr { display: grid; }
        .sg-entry .sg-table tr { grid-template-columns: repeat(2, minmax(0, 1fr)); padding: .55rem; border-bottom: 1px solid #cadad1; }
        .sg-entry .sg-table td { display: block; border: 0; }
        .sg-entry .sg-table td input, .sg-entry .sg-table td select { min-width: 0; }
        .sg-mobile-label { display: block; font-size: .75rem; font-weight: 650; margin-bottom: .2rem; }
    }
</style>
