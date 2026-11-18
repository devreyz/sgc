@once
<style>
.services-campo{grid-column:1/-1;display:grid;gap:1rem;width:100%;min-width:0;color:var(--ui-color-text);font-family:var(--ui-font-sans);font-size:.875rem;line-height:1.5}
.services-campo> *{min-width:0;max-width:100%}
.services-campo h1{font-size:clamp(1.35rem,2.5vw,1.8rem);font-weight:700;line-height:1.2;letter-spacing:-.025em}
.services-campo h2{font-weight:700;line-height:1.3}
.services-campo :is(input,select,textarea){color:var(--ui-color-text);background:var(--ui-color-surface);border-color:var(--ui-color-border);font-family:var(--ui-font-sans);font-size:.875rem;min-height:44px}
.services-campo :is(input,select,textarea):focus-visible{outline:2px solid var(--ui-color-primary);outline-offset:2px}
.services-campo :is(input[type=checkbox],input[type=radio]){min-height:0;accent-color:var(--ui-color-primary)}
.services-campo :is(button,a,input,select,textarea){transition:background-color .16s,color .16s,border-color .16s,box-shadow .16s}
.services-campo :is(button,a):focus-visible{outline:2px solid var(--ui-color-primary);outline-offset:3px}
.services-campo :is(button:disabled,input:disabled,select:disabled){opacity:.6;cursor:not-allowed}
.services-campo :is(.primary,.sg-primary,.btn-primary,.campo-button--accent){background:var(--sgc-accent)!important;border-color:var(--sgc-accent)!important;color:var(--sgc-on-accent)!important;box-shadow:none}
.services-campo :is(.primary,.sg-primary,.btn-primary):hover{background:var(--sgc-accent-hover)!important}
.services-campo :is(.service-builder,.catalog-workspace,.catalog-config,.provider-preview,.providers-workspace,.provider-form-page,.reports-workspace,.agreements-workspace,.sim-workspace,.simulation-result){width:100%;max-width:none;margin-inline:0}
.services-campo :is(.service-builder,.catalog-workspace,.catalog-config,.provider-preview,.providers-workspace,.provider-form-page,.reports-workspace,.agreements-workspace,.sim-workspace,.simulation-result)>header:first-of-type{background:var(--sgc-rail,#173c2e);border-color:var(--sgc-rail,#173c2e);color:var(--sgc-rail-text,#f3f5ee);padding:clamp(1rem,3vw,1.5rem);border-radius:16px;box-shadow:none}
.services-campo :is(.service-builder,.catalog-workspace,.catalog-config,.provider-preview,.providers-workspace,.provider-form-page,.reports-workspace,.agreements-workspace,.sim-workspace,.simulation-result)>header:first-of-type :is(h1,h2,strong,p,small){color:inherit}
.services-campo :is(.service-builder,.catalog-workspace,.catalog-config,.provider-preview,.providers-workspace,.provider-form-page,.reports-workspace,.agreements-workspace,.sim-workspace,.simulation-result)>header:first-of-type :is(p,small){opacity:.78}
.services-campo dialog{max-height:88dvh;overscroll-behavior:contain;color:var(--ui-color-text);background:var(--ui-color-surface);border-color:var(--ui-color-border)}
.services-campo dialog::backdrop{background:rgb(10 24 17/.6)}
.services-campo dialog[open]{animation:services-dialog-enter .18s ease-out}
.services-campo :is(th,td){border-color:var(--ui-color-border)}
.services-campo th{font-size:.75rem;font-weight:600}
.services-campo td{font-size:.8125rem}
.services-campo :is(.ph-duotone,.ph){font-weight:400;font-style:normal;line-height:1}
@keyframes services-dialog-enter{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
@media(max-width:640px){.services-campo{gap:.75rem}.services-campo dialog{max-height:88dvh}.services-campo :is(input,select,textarea){font-size:1rem}}
@media(prefers-reduced-motion:reduce){.services-campo *{animation:none!important;transition:none!important}}
</style>
@endonce
