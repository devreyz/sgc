<section class="svc-section svc-agenda" id="agenda" data-provider-agenda data-mode="calendar" data-url="{{ route('provider.dashboard', $tenantSlug) }}" data-today="{{ $today->toDateString() }}" aria-labelledby="svc-agenda-title">
    <script>
        try {
            if (localStorage.getItem('sgc-provider-agenda-view') === 'list') {
                document.currentScript.parentElement.dataset.mode = 'list';
            }
        } catch {}
    </script>
    <header class="svc-section-head">
        <div class="svc-section-title">
            <span class="svc-section-icon" aria-hidden="true"><i class="ph-fill ph-calendar-dots"></i></span>
            <div class="svc-section-copy"><h2 id="svc-agenda-title">Agenda de serviços</h2></div>
        </div>
        <div class="svc-agenda-controls" role="group" aria-label="Navegação do calendário">
            <button type="button" data-month-direction="previous" aria-label="Mês anterior">‹</button>
            <button type="button" class="svc-agenda-today" data-show-today>Hoje</button>
            <button type="button" data-month-direction="next" aria-label="Próximo mês">›</button>
        </div>
    </header>
    <div class="svc-agenda-switch" role="group" aria-label="Visualização da agenda" hidden>
        <button type="button" data-agenda-view="calendar" aria-pressed="true">Calendário</button>
        <button type="button" data-agenda-view="list" aria-pressed="false">Lista</button>
    </div>
    <p class="svc-agenda-legend"><span><i data-planned></i> Agendada ou em rascunho</span><span><i data-completed></i> Executada</span><span><i></i> Outra situação</span></p>
    <div class="svc-agenda-scroll" tabindex="0" role="region" aria-label="Calendário de ordens">
        <div data-agenda-months>@include('provider._agenda-month')</div>
    </div>
    <div class="svc-agenda-feedback">
        <span role="status" aria-live="polite" data-agenda-message></span>
        <button type="button" data-agenda-retry hidden>Tentar novamente</button>
    </div>
</section>

<dialog class="svc-agenda-dialog" data-agenda-dialog data-back-history aria-labelledby="agenda-dialog-title">
    <header class="svc-agenda-dialog-head">
        <h3 id="agenda-dialog-title">Ordens do dia</h3>
        <button type="button" data-close-day aria-label="Fechar detalhes">×</button>
    </header>
    <div class="svc-agenda-dialog-body" data-dialog-day tabindex="0" role="region" aria-labelledby="agenda-dialog-title"></div>
</dialog>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const agenda = document.querySelector('[data-provider-agenda]');
        const months = agenda.querySelector('[data-agenda-months]');
        const message = agenda.querySelector('[data-agenda-message]');
        const retry = agenda.querySelector('[data-agenda-retry]');
        const dialog = document.querySelector('[data-agenda-dialog]');
        const sidebar = document.querySelector('[data-agenda-side]');
        const mobile = matchMedia('(max-width: 767px)');
        const switcher = agenda.querySelector('.svc-agenda-switch');
        const cache = new Map([[months.firstElementChild.dataset.month, months.firstElementChild]]);
        let request;
        let failed;
        let selectedCell;
        let startTouch;
        let ignoreClickUntil = 0;
        let dialogHistory = false;

        function setMode(mode) {
            agenda.dataset.mode = mode;
            switcher.querySelectorAll('button').forEach(button => {
                button.setAttribute('aria-pressed', String(button.dataset.agendaView === mode));
            });
        }

        function selectDay(cell, open = false, focus = false) {
            if (!cell) return;
            selectedCell?.setAttribute('aria-pressed', 'false');
            selectedCell = cell;
            cell.setAttribute('aria-pressed', 'true');
            const panel = document.getElementById(cell.getAttribute('aria-controls'));
            const content = panel.querySelector('.svc-day-orders');
            const heading = document.createElement('h3');
            heading.textContent = cell.dataset.label;
            sidebar.replaceChildren(heading, content.cloneNode(true));
            if (open && mobile.matches) {
                dialog.querySelector('#agenda-dialog-title').textContent = cell.dataset.label;
                dialog.querySelector('[data-dialog-day]').replaceChildren(content.cloneNode(true));
                if (!dialog.open) {
                    history.pushState({...history.state, sgcAgendaDay: true}, '');
                    dialogHistory = true;
                    dialog.showModal();
                    dialog.querySelector('[data-dialog-day]').scrollTop = 0;
                }
            } else if (focus) {
                cell.focus({preventScroll: true});
            }
        }

        function showPlaceholder(date, direction) {
            const key = date.slice(0, 7);
            const month = document.createElement('section');
            month.className = 'svc-agenda-month';
            month.dataset.month = key;
            month.dataset.slide = direction;
            const [year, number] = key.split('-').map(Number);
            const first = new Date(year, number - 1, 1);
            const heading = document.createElement('h3');
            heading.textContent = new Intl.DateTimeFormat('pt-BR', {month: 'long', year: 'numeric'}).format(first);
            const grid = document.createElement('div');
            grid.className = 'svc-month-grid';
            grid.setAttribute('aria-hidden', 'true');
            for (const label of ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb']) {
                const weekday = document.createElement('span');
                weekday.className = 'svc-weekday';
                weekday.textContent = label;
                grid.append(weekday);
            }
            for (let offset = first.getDay(); offset > 0; offset--) grid.append(document.createElement('span'));
            const count = new Date(year, number, 0).getDate();
            for (let day = 1; day <= count; day++) {
                const cell = document.createElement('span');
                cell.className = 'svc-calendar-cell';
                cell.textContent = String(day).padStart(2, '0');
                cell.toggleAttribute('data-red-day', new Date(year, number - 1, day).getDay() === 0);
                grid.append(cell);
            }
            for (const [name, offset] of [['previous', -1], ['next', 1]]) {
                const adjacent = new Date(year, number - 1 + offset, 1);
                month.dataset[name] = String(adjacent.getFullYear()).padStart(4, '0') + '-' + String(adjacent.getMonth() + 1).padStart(2, '0') + '-01';
            }
            month.append(heading, grid);
            months.replaceChildren(month);
            return month;
        }

        async function showMonth(direction, date, focus = false) {
            if (!date) return;
            request?.abort();
            const controller = request = new AbortController();
            const timeout = setTimeout(() => controller.abort(), 12000);
            failed = null;
            retry.hidden = true;
            months.setAttribute('aria-busy', 'true');
            try {
                const key = date.slice(0, 7);
                let month = cache.get(key);
                if (!month) {
                    const placeholder = months.firstElementChild.dataset.month === key
                        ? months.firstElementChild : showPlaceholder(date, direction);
                    const previewCells = placeholder.querySelectorAll('.svc-calendar-cell');
                    previewCells.forEach((cell, index) => cell.toggleAttribute('data-loading-selected', index + 1 === Number(date.slice(-2))));
                    sidebar.textContent = 'Carregando ordens…';
                    message.textContent = 'Carregando ordens…';
                    const url = new URL(agenda.dataset.url, location.href);
                    url.searchParams.set('agenda_fragment', '1');
                    url.searchParams.set('agenda_date', date);
                    const response = await fetch(url, {headers: {'X-Requested-With': 'XMLHttpRequest'}, globalLoader: false, signal: controller.signal});
                    if (!response.ok || response.redirected) throw new Error('Agenda indisponível');
                    const template = document.createElement('template');
                    template.innerHTML = await response.text();
                    if (request !== controller) return;
                    month = template.content.querySelector('[data-month]');
                    if (!month || month.dataset.month !== key) throw new Error('Resposta inválida');
                    // Atualiza dentro da seção em movimento, preservando a animação em curso.
                    placeholder.replaceChildren(...month.childNodes);
                    placeholder.setAttribute('aria-labelledby', month.getAttribute('aria-labelledby'));
                    placeholder.dataset.previous = month.dataset.previous;
                    placeholder.dataset.next = month.dataset.next;
                    month = placeholder;
                    cache.set(key, month);
                    if (cache.size > 8) cache.delete(cache.keys().next().value);
                }

                if (month !== months.firstElementChild) {
                    delete month.dataset.slide;
                    months.replaceChildren(month);
                    month.dataset.slide = direction;
                }
                selectDay(month.querySelector(`[data-day="${CSS.escape(date)}"]`) || month.querySelector('[data-day]'), false, focus);
                message.textContent = '';
            } catch {
                if (request !== controller) return;
                failed = {direction, date, focus};
                retry.hidden = false;
                message.textContent = 'Não foi possível carregar as ordens.';
                sidebar.textContent = 'Ordens indisponíveis.';
            } finally {
                clearTimeout(timeout);
                if (request === controller) {
                    request = null;
                    months.removeAttribute('aria-busy');
                }
            }
        }

        function move(direction, focus = false) {
            const adjacent = months.firstElementChild.dataset[direction];
            if (!adjacent) return;
            const [year, month] = adjacent.split('-').map(Number);
            const currentDay = months.querySelector('[data-loading-selected], [data-day][aria-pressed="true"]');
            const selectedDate = currentDay?.dataset.day;
            const dayNumber = selectedDate ? Number(selectedDate.slice(-2)) : Number(currentDay?.textContent);
            const day = Math.min(dayNumber || 1, new Date(year, month, 0).getDate());
            showMonth(direction, adjacent.slice(0, 7) + '-' + String(day).padStart(2, '0'), focus);
        }

        let savedMode;
        try { savedMode = localStorage.getItem('sgc-provider-agenda-view'); } catch {}
        setMode(savedMode === 'list' ? 'list' : 'calendar');
        switcher.hidden = false;
        selectDay(months.querySelector('[aria-current="date"]') || months.querySelector('[data-day]'));

        switcher.addEventListener('click', event => {
            const mode = event.target.closest('button')?.dataset.agendaView;
            if (!mode) return;
            setMode(mode);
            try { localStorage.setItem('sgc-provider-agenda-view', mode); } catch {}
        });
        agenda.querySelector('[data-show-today]').addEventListener('click', () => showMonth('today', agenda.dataset.today, agenda.dataset.mode === 'calendar'));
        agenda.querySelectorAll('[data-month-direction]').forEach(button => {
            button.addEventListener('click', () => move(button.dataset.monthDirection));
        });
        retry.addEventListener('click', () => { if (failed) showMonth(failed.direction, failed.date, failed.focus); });

        months.addEventListener('click', event => {
            const cell = event.target.closest('[data-day]');
            if (cell && performance.now() >= ignoreClickUntil) {
                selectDay(cell, true);
                return;
            }
            const summary = event.target.closest('.svc-month-day > summary');
            if (!summary || !mobile.matches) return;
            event.preventDefault();
            selectDay(months.querySelector(`[aria-controls="${CSS.escape(summary.parentElement.id)}"]`), true);
        });
        months.addEventListener('touchstart', event => {
            startTouch = event.touches.length === 1 && event.target.closest('.svc-month-grid') ? event.touches[0] : null;
        }, {passive: true});
        months.addEventListener('touchcancel', () => { startTouch = null; }, {passive: true});
        months.addEventListener('touchend', event => {
            if (!startTouch) return;
            const dx = event.changedTouches[0].clientX - startTouch.clientX;
            const dy = event.changedTouches[0].clientY - startTouch.clientY;
            startTouch = null;
            if (Math.abs(dx) < 45 || Math.abs(dx) < Math.abs(dy) * 1.5) return;
            ignoreClickUntil = performance.now() + 350;
            move(dx < 0 ? 'next' : 'previous');
        }, {passive: true});
        months.addEventListener('keydown', event => {
            const cell = event.target.closest('[data-day]');
            const offset = {ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7}[event.key];
            if (!cell || !offset) return;
            event.preventDefault();
            const cells = [...months.querySelectorAll('[data-day]')];
            const target = cells[cells.indexOf(cell) + offset];
            if (target) target.focus();
            else move(offset < 0 ? 'previous' : 'next', true);
        });

        dialog.querySelector('[data-close-day]').addEventListener('click', () => dialog.close());
        dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
        window.addEventListener('popstate', () => {
            if (!dialogHistory) return;
            dialogHistory = false;
            if (dialog.open) dialog.close();
        });
        dialog.addEventListener('close', () => {
            if (dialogHistory) {
                dialogHistory = false;
                if (history.state?.sgcAgendaDay) history.back();
            }
            if (agenda.dataset.mode === 'calendar') selectedCell?.focus({preventScroll: true});
            else if (selectedCell) document.getElementById(selectedCell.getAttribute('aria-controls'))?.querySelector('summary').focus({preventScroll: true});
        });
    });
</script>
