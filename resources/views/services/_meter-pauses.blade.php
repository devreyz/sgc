    @once
        <style>
            .sgc-meter-pauses {
                grid-column: 1 / -1;
                min-width: 0;
                margin: .25rem 0;
                padding: .875rem;
                border: 1px solid var(--ui-color-primary-border, #8fac98);
                border-radius: .875rem;
                background: var(--ui-color-primary-soft, #e7f0e7);
                color: var(--ui-color-text, #202922);
            }
            .sgc-meter-pauses legend { padding-inline: .375rem; font-weight: 700; }
            .sgc-meter-pauses__hint { margin: 0 0 .75rem; font-size: .875rem; }
            .sgc-meter-pauses__list { display: grid; gap: .625rem; }
            .sgc-meter-pauses__row {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: .625rem;
                padding: .75rem;
                border: 1px solid var(--ui-color-border, #cdd8ce);
                border-radius: .75rem;
                background: var(--ui-color-surface, #fff);
            }
            .sgc-meter-pauses__row label { min-width: 0; }
            .sgc-meter-pauses__row input { width: 100%; min-width: 0; }
            .sgc-meter-pauses button {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: .375rem;
                min-height: 44px;
                padding: .5rem .75rem;
                border-radius: .625rem;
                font-weight: 700;
            }
            .sgc-meter-pauses button[data-add] {
                background: var(--sgc-accent, #d9f172);
                color: var(--sgc-on-accent, #173c2e);
            }
            .sgc-meter-pauses button[data-add]:hover { background: var(--sgc-accent-hover, #e5fa98); }
            .sgc-meter-pauses__list:not(:empty) { margin-top: .75rem; }
            .sgc-meter-pauses button[data-remove] {
                grid-column: 1 / -1;
                justify-self: start;
                border: 1px solid var(--ui-color-border, #cdd8ce);
                color: var(--ui-color-text, #202922);
            }
            .sgc-meter-pauses button:focus-visible {
                outline: 2px solid var(--ui-color-primary, #176146);
                outline-offset: 3px;
            }
        </style>
    @endonce
    @php
        $pauseKey = 'meter_pauses_'.$meterStartField;
        $savedPauses = data_get($execution->values, $pauseKey, '[]');
    @endphp
    <fieldset class="sgc-meter-pauses" data-meter-pauses>
        <legend>Pausas do horímetro</legend>
        <p class="sgc-meter-pauses__hint">O intervalo entre a pausa e o retorno é descontado das horas do serviço.</p>
        <button type="button" data-add><i class="ph ph-plus" aria-hidden="true"></i> Adicionar pausa</button>
        <input type="hidden" name="values[{{ $pauseKey }}]" value="{{ ($restoreOld ?? true) ? old('values.'.$pauseKey, $savedPauses) : $savedPauses }}">
        <div class="sgc-meter-pauses__list" data-pause-list></div>
        <template>
            <div class="sgc-meter-pauses__row">
                <label>Leitura na pausa <input type="number" min="0" step="any" required></label>
                <label>Leitura no retorno <input type="number" min="0" step="any" required></label>
                <button type="button" data-remove>Remover pausa</button>
            </div>
        </template>
    </fieldset>
    <script>
    (() => {
        const box = document.currentScript.previousElementSibling;
        const list = box.querySelector('[data-pause-list]');
        const stored = box.querySelector('input[type=hidden]');
        function sync() {
            stored.value = JSON.stringify(Array.from(list.children, row => {
                const [stop, resume] = row.querySelectorAll('input');
                return {stop: stop.value, resume: resume.value};
            }));
            stored.dispatchEvent(new Event('input', {bubbles:true}));
        }
        function add(pause = {}) {
            const row = box.querySelector('template').content.firstElementChild.cloneNode(true);
            const [stop, resume] = row.querySelectorAll('input');
            stop.value = pause.stop ?? '';
            resume.value = pause.resume ?? '';
            row.querySelector('[data-remove]').onclick = () => { row.remove(); sync(); };
            list.append(row);
            return stop;
        }
        function restore() {
            list.replaceChildren();
            try {
                const pauses = JSON.parse(stored.value || '[]');
                if (Array.isArray(pauses)) pauses.forEach(add);
            } catch { stored.value = '[]'; }
        }
        stored.addEventListener('restore-pauses', restore);
        restore();
        list.addEventListener('input', sync);
        box.querySelector('[data-add]').onclick = () => {
            add().focus();
            sync();
        };
    })();
    </script>
