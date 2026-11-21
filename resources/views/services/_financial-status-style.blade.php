@once
    <style>
        [data-money-flow="incoming"] {
            --flow-text: var(--ui-color-cyan-strong);
            --flow-bg: var(--ui-color-cyan-soft);
            --flow-border: var(--ui-color-cyan-border);
        }
        [data-money-flow="outgoing"] {
            --flow-text: var(--ui-color-violet-strong);
            --flow-bg: var(--ui-color-violet-soft);
            --flow-border: var(--ui-color-violet-border);
        }
        [data-money-flow="both"] {
            --flow-text: var(--ui-color-warning-strong);
            --flow-bg: var(--ui-color-warning-soft);
            --flow-border: var(--ui-color-warning-border);
        }
        [data-money-flow="settled"] {
            --flow-text: var(--ui-color-success-strong);
            --flow-bg: var(--ui-color-success-soft);
            --flow-border: var(--ui-color-success-border);
        }
        .svc-financial-status {
            display: inline-flex;
            align-items: center;
            gap: .32rem;
            width: fit-content;
            max-width: 100%;
            padding: .24rem .45rem;
            border: 1px solid var(--flow-border, var(--ui-color-border));
            border-radius: .5rem;
            background: var(--flow-bg, var(--ui-color-surface-soft));
            color: var(--flow-text, var(--ui-color-text-secondary));
            font-size: .75rem;
            font-weight: 700;
            line-height: 1.25;
        }
        .svc-financial-status i { flex: none; font-size: 1rem; }
        .cm-order .cm-icon-action[data-money-flow] {
            border-color: var(--flow-border);
            background: var(--flow-bg);
            color: var(--flow-text);
        }
        @media (hover: hover) {
            .cm-order .cm-icon-action[data-money-flow]:hover {
                border-color: var(--flow-text);
                color: var(--flow-text);
                background: var(--flow-bg);
            }
        }
    </style>
@endonce
