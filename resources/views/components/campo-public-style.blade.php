<style>
    .campo-public-theme-toggle {
        display: inline-grid;
        width: 44px;
        height: 44px;
        flex: 0 0 44px;
        place-items: center;
        border: 1px solid var(--ui-color-border);
        border-radius: var(--ui-radius-sm);
        background: var(--ui-color-surface);
        color: var(--ui-color-text);
        font-size: 1.2rem;
        cursor: pointer;
    }
    .campo-public-theme-toggle:hover { background: var(--ui-color-surface-soft); }
    .campo-public-theme-toggle:focus-visible { outline: 2px solid var(--ui-color-focus); outline-offset: 2px; }
    html:not([data-theme="dark"]) .campo-theme-sun,
    html[data-theme="dark"] .campo-theme-moon { display: none; }
    .campo-public-brand > img { display: block; width: 100%; height: 100%; object-fit: contain; }
    html:not([data-theme="dark"]) .campo-public-brand > img.sgc-mark-dark,
    html[data-theme="dark"] .campo-public-brand > img.sgc-mark-light { display: none; }
</style>
