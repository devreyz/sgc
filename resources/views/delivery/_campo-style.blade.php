@once
<style>
.delivery-campo{grid-column:1/-1;display:grid;width:100%;min-width:0;gap:1rem;color:var(--ui-color-text);font-family:var(--ui-font-sans);font-size:.875rem;line-height:1.5}
.delivery-campo>*{min-width:0;max-width:100%}
.delivery-campo :is(h1,h2,h3){color:var(--ui-color-text);font-weight:700;line-height:1.25}
.delivery-campo :is(input,select,textarea){color:var(--ui-color-text);background-color:var(--ui-color-surface);border-color:var(--ui-color-border);font:inherit;min-height:44px}
.delivery-campo :is(input[type=checkbox],input[type=radio]){min-height:0;accent-color:var(--ui-color-primary)}
.delivery-campo :is(button,a,input,select,textarea):focus-visible{outline:2px solid var(--ui-color-primary);outline-offset:3px}
.delivery-campo :is(button,input,select,textarea):disabled{cursor:not-allowed;opacity:.6}
.delivery-campo :is(.btn-primary,.button-primary){background:var(--sgc-accent);color:var(--sgc-on-accent);border-color:var(--sgc-accent)}
.delivery-campo :is(.btn-primary,.button-primary):hover:not(:disabled){background:var(--sgc-accent-hover)}
.delivery-campo :is(th,td){border-color:var(--ui-color-border)}
.delivery-campo :is(.planning,.delivery-admin-page,.project-limits-page,.project-producers-page){width:100%;max-width:none}
.delivery-campo :is(dialog,[role=dialog]){color:var(--ui-color-text);background:var(--ui-color-surface);max-height:90dvh;overscroll-behavior:contain}
.delivery-campo dialog::backdrop{background:var(--ui-color-overlay)}
.delivery-campo :is(.ph,.ph-duotone){font-style:normal;font-weight:400}
@media(max-width:640px){.delivery-campo{gap:.75rem}.delivery-campo :is(input,select,textarea){font-size:1rem}}
@media(prefers-reduced-motion:reduce){.delivery-campo *{scroll-behavior:auto!important}}
</style>
@endonce
