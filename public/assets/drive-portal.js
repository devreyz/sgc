(() => {
    const portal = document.querySelector('[data-drive-portal]');
    if (!portal || portal.dataset.connected !== '1') return;

    const find = selector => portal.querySelector(selector);
    const api = portal.dataset.filesUrl;
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const canManage = portal.dataset.manage === '1';
    const panel = find('[data-drive-list]');
    const table = panel.querySelector('table');
    const rows = find('[data-drive-rows]');
    const path = find('[data-drive-path]');
    const status = find('[data-drive-status]');
    const empty = find('[data-drive-empty]');
    const loading = find('[data-drive-loading]');
    const emptyTitle = find('[data-drive-empty-title]');
    const footer = find('[data-drive-footer]');
    const more = find('[data-drive-more]');
    const search = find('[data-drive-search]');
    const trashSwitch = find('[data-drive-trash]');
    const create = find('[data-drive-create]');
    const upload = find('[data-drive-upload]');
    const input = find('[data-drive-file-input]');
    const dialog = find('[data-drive-dialog]');
    const dialogTitle = dialog.querySelector('h2');
    const dialogBody = find('[data-drive-dialog-body]');
    const dialogFeedback = find('[data-drive-dialog-feedback]');
    const cache = new Map();
    const inFlight = new Map();
    const folderPaths = new Map();
    const dateFormat = new Intl.DateTimeFormat(document.documentElement.lang || 'pt-BR', {dateStyle: 'medium', timeStyle: 'short'});
    const monthFormat = new Intl.DateTimeFormat('pt-BR', {month: 'long', year: 'numeric', timeZone: 'UTC'});

    let route = readRoute();
    let searchTerm = '';
    let searchTimer;
    let data;
    let displayedRoute;
    let listingRequest = 0;
    let generation = 0;
    let dialogRequest = 0;
    let uploading = false;

    function element(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    }

    function icon(name) {
        const node = element('i', 'ph ph-' + name);
        node.setAttribute('aria-hidden', 'true');
        return node;
    }

    function button(label, callback, symbol, className = 'drive-button') {
        const node = element('button', className);
        node.type = 'button';
        if (symbol) node.append(icon(symbol));
        node.append(document.createTextNode(label));
        node.addEventListener('click', callback);
        return node;
    }

    function downloadLink(item) {
        const link = element('a', 'drive-button');
        link.href = itemUrl(item.id);
        link.append(icon('download-simple'), document.createTextNode('Baixar arquivo'));
        return link;
    }

    function itemUrl(id, download = true) {
        return api + '/' + encodeURIComponent(id) + (download ? '/download' : '');
    }

    async function request(url, options = {}) {
        const headers = {'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, ...options.headers};
        const response = await fetch(url, {...options, headers, credentials: 'same-origin', globalLoader: false});
        if (!response.ok || !response.headers.get('content-type')?.includes('application/json')) {
            const body = await response.json().catch(() => ({}));
            const expired = response.redirected || response.status === 401 || response.status === 419;
            throw new Error(expired ? 'Sua sessão expirou. Entre no SGC novamente.' :
                (Object.values(body.errors || {}).flat()[0] || body.message || 'O Drive está indisponível no momento.'));
        }
        return response.json();
    }

    function readRoute() {
        const url = new URL(window.location.href);
        return {folder: url.searchParams.get('folder') || '', trash: url.searchParams.get('trash') === '1'};
    }

    function routeKey({folder, trash}, term = '') {
        return JSON.stringify([folder, trash, term]);
    }

    function navigate(folder = '', trash = false, entry = null) {
        const next = {folder, trash};
        if (routeKey(next) === routeKey(route) && !searchTerm) return;
        if (entry && data?.path) folderPaths.set(folder, [...data.path, entry]);
        const url = new URL(window.location.href);
        if (folder) url.searchParams.set('folder', folder); else url.searchParams.delete('folder');
        if (trash) url.searchParams.set('trash', '1'); else url.searchParams.delete('trash');
        if (routeKey(next) !== routeKey(route)) history.pushState({drive: true}, '', url);
        clearTimeout(searchTimer);
        searchTerm = search.value = '';
        route = next;
        load();
    }

    window.addEventListener('popstate', () => {
        const next = readRoute();
        if (routeKey(next) === routeKey(route)) return;
        clearTimeout(searchTimer);
        searchTerm = search.value = '';
        route = next;
        load();
    });

    function listUrl(state, term, page) {
        const url = new URL(api);
        if (state.folder) url.searchParams.set('folder', state.folder);
        if (state.trash) url.searchParams.set('trash', '1');
        if (term) url.searchParams.set('search', term);
        if (page) url.searchParams.set('page', page);
        return url;
    }

    function remember(key, page) {
        cache.delete(key);
        cache.set(key, {page, fetchedAt: Date.now()});
        if (cache.size > 40) cache.delete(cache.keys().next().value);
    }

    function firstPage(state, term = '') {
        const key = routeKey(state, term);
        const revision = generation;
        const flight = revision + key;
        if (!inFlight.has(flight)) {
            inFlight.set(flight, request(listUrl(state, term)).then(page => {
                if (generation === revision) remember(key, page);
                return page;
            }).finally(() => inFlight.delete(flight)));
        }
        return inFlight.get(flight);
    }

    function setStatus(message, error = false) {
        status.textContent = message;
        status.dataset.error = String(error);
    }

    function setActions() {
        const enabled = data?.canAdd && !route.trash && !uploading && panel.getAttribute('aria-busy') !== 'true';
        if (create) create.disabled = !enabled;
        if (upload) upload.disabled = !enabled;
    }

    async function load({force = false, append = false, foreground = false} = {}) {
        const current = ++listingRequest;
        const state = {...route};
        const term = searchTerm;
        const key = routeKey(state, term);
        const stored = cache.get(key);
        trashSwitch.setAttribute('aria-pressed', String(state.trash));
        const trashLabel = state.trash ? 'Ver arquivos' : 'Lixeira da pasta';
        trashSwitch.setAttribute('aria-label', trashLabel);
        trashSwitch.title = trashLabel;
        trashSwitch.replaceChildren(icon(state.trash ? 'folder-open' : 'trash'), element('span', '', trashLabel));

        if (stored && !append) render(stored.page);
        if (stored && !force && !append && Date.now() - stored.fetchedAt < 30000) {
            panel.setAttribute('aria-busy', 'false');
            setStatus('');
            setActions();
            return;
        }

        panel.setAttribute('aria-busy', 'true');
        const openingFolder = !stored && !append && displayedRoute !== routeKey(state);
        if (openingFolder) {
            const root = {id: '', name: 'Arquivos'};
            const knownPath = folderPaths.get(state.folder) ?? (state.folder ? [root, {id: state.folder, name: 'Pasta'}] : [root]);
            render({path: knownPath, items: [], canAdd: false}, true);
        }
        loading.hidden = !openingFolder;
        more.disabled = true;
        setActions();
        if (!uploading) setStatus(stored || foreground ? 'Atualizando…' : 'Carregando arquivos…');

        try {
            const page = append ? await request(listUrl(state, term, data.nextPage)) : await firstPage(state, term);
            if (current !== listingRequest) return;
            if (append) {
                const seen = new Set(data.items.map(item => item.id));
                page.items = [...data.items, ...page.items.filter(item => !seen.has(item.id))];
                remember(key, page);
            }
            render(page);
            if (!uploading) setStatus('');
        } catch (error) {
            if (current !== listingRequest) return;
            setStatus(error.message, true);
        } finally {
            if (current === listingRequest) {
                loading.hidden = true;
                panel.setAttribute('aria-busy', 'false');
                more.disabled = false;
                setActions();
            }
        }
    }

    function folderLabel(entry) {
        if (entry.label) return entry.label;
        if (!/^\d{4}-(0[1-9]|1[0-2])$/.test(entry.name)) return entry.name;
        const formatted = monthFormat.format(new Date(entry.name + '-01T00:00:00Z'));
        return formatted.charAt(0).toUpperCase() + formatted.slice(1);
    }

    function render(page, pending = false) {
        const reveal = !pending && (loading.hidden === false || displayedRoute !== routeKey(route));
        data = {...page, trash: route.trash};
        displayedRoute = routeKey(route);
        loading.hidden = !pending;
        if (!pending) {
            page.path.forEach((entry, index) => folderPaths.set(index === 0 ? '' : entry.id, page.path.slice(0, index + 1)));
        }
        const crumbs = element('div', 'drive-path-trail');
        const currentEntry = page.path.at(-1);
        page.path.forEach((entry, index) => {
            if (index > 0) crumbs.append(icon('caret-right'));
            const name = index === 0 ? 'Arquivos' : folderLabel(entry);
            if (entry === currentEntry) {
                const current = element('span', '', name);
                current.title = name;
                current.setAttribute('aria-current', 'location');
                crumbs.append(current);
            } else {
                const link = button(name, () => navigate(index === 0 ? '' : entry.id), index === 0 ? 'house' : null, '');
                link.title = name;
                crumbs.append(link);
            }
        });
        const mobilePath = element('div', 'drive-path-mobile');
        const currentName = route.trash ? 'Lixeira' : (page.path.length === 1 ? 'Arquivos' : folderLabel(currentEntry));
        if (page.path.length > 1 || route.trash) {
            const parent = button('', () => route.trash ? navigate(route.folder) : navigate(page.path.length > 2 ? page.path.at(-2).id : ''), 'arrow-left', 'drive-icon-button');
            parent.setAttribute('aria-label', route.trash ? 'Voltar aos arquivos' : 'Voltar à pasta anterior');
            const location = button('', () => {
                const folders = page.path.map((entry, index) => {
                    const entryButton = button(index === 0 ? 'Arquivos' : folderLabel(entry), () => {
                        dialog.close();
                        navigate(index === 0 ? '' : entry.id);
                    }, index === 0 ? 'house' : 'folder');
                    if (!route.trash && entry === currentEntry) entryButton.setAttribute('aria-current', 'location');
                    return entryButton;
                });
                showDialog('Localização da pasta', ...folders);
            }, 'caret-down', 'drive-location');
            location.prepend(element('span', '', currentName));
            location.setAttribute('aria-label', currentName + ' · Ver caminho da pasta');
            mobilePath.append(parent, location);
        } else {
            mobilePath.append(element('span', 'drive-location', currentName));
        }
        path.replaceChildren(crumbs, mobilePath);
        crumbs.scrollLeft = crumbs.scrollWidth;

        const fragment = document.createDocumentFragment();
        const sorted = [...page.items.filter(item => item.folder), ...page.items.filter(item => !item.folder)];
        const groups = new Map();
        for (const item of sorted) {
            const match = !item.folder && item.name.match(/^([A-Z]{2,4}-[A-Z0-9-]+) - ([^·]+?)(?: · .*|\.[^.]+)?$/i);
            const key = item.group?.slug || match?.[1] || item.id;
            if (!groups.has(key)) groups.set(key, {
                title: item.group?.name || match?.[2]?.trim(),
                reference: item.group?.reference || match?.[1],
                alwaysShow: Boolean(item.group),
                items: [],
            });
            groups.get(key).items.push(item);
        }
        for (const group of groups.values()) {
            if (group.title && (group.alwaysShow || group.items.length > 1)) {
                const header = element('tr', 'drive-document-group');
                header.setAttribute('role', 'row');
                const label = element('th');
                label.setAttribute('role', 'rowheader');
                label.colSpan = 4;
                label.append(element('span', '', group.title), element('span', 'drive-reference', group.reference));
                header.append(label);
                fragment.append(header);
            }
            for (const item of group.items) fragment.append(rowFor(item));
        }
        rows.replaceChildren(fragment);
        table.hidden = sorted.length === 0;
        if (reveal && sorted.length && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            rows.animate?.([{opacity: .65}, {opacity: 1}], {duration: 160, easing: 'ease-out'});
        }
        empty.hidden = pending || sorted.length > 0 || Boolean(page.nextPage);
        emptyTitle.textContent = searchTerm ? 'Nenhum item encontrado' : route.trash ? 'Lixeira vazia nesta pasta' : 'Pasta vazia';
        footer.hidden = !page.nextPage;
        setActions();
    }

    function rowFor(item) {
        const row = element('tr');
        row.setAttribute('role', 'row');
        const name = element('td');
        const trash = route.trash;
        const interactive = !trash && (item.folder || item.preview || item.download);
        const label = interactive ? button('', () => openItem(item), null, 'drive-file') : element('span', 'drive-file');
        const glyph = item.folder ? 'folder' : item.preview === 'application/pdf' ? 'file-pdf' : item.preview?.startsWith('image/') ? 'image' : 'file';
        const mark = element('span', 'drive-file-icon');
        mark.append(icon(glyph));
        const displayName = item.folder ? folderLabel(item) : item.label || item.name;
        const title = element('span', 'drive-file-name', displayName);
        title.title = displayName;
        const caption = element('span', 'drive-file-caption');
        caption.append(title);
        if (item.reference) caption.append(element('span', 'drive-reference', item.reference));
        label.append(mark, caption);
        if (item.folder && !trash) {
            const prefetch = () => {
                const child = {folder: item.id, trash: false};
                if (!cache.has(routeKey(child)) && inFlight.size < 3) firstPage(child).catch(() => {});
            };
            label.addEventListener('pointerenter', prefetch, {once: true});
            label.addEventListener('focus', prefetch, {once: true});
        }
        name.append(label);
        const date = item.modified ? new Date(item.modified) : null;
        const dateText = date && Number.isFinite(date.getTime()) ? dateFormat.format(date) : '—';
        const time = element('td', 'drive-secondary-col', dateText);
        const size = element('td', 'drive-secondary-col', humanSize(item.size));
        const actions = element('td');
        for (const cell of [name, time, size, actions]) cell.setAttribute('role', 'cell');
        if (trash ? canManage && item.restore : item.download || canManage && (item.rename || item.move || item.trash)) {
            const menu = button('', () => openActions(item, trash), 'dots-three', 'drive-icon-button');
            menu.setAttribute('aria-label', 'Ações de ' + item.name);
            actions.append(menu);
        }
        row.append(name, time, size, actions);
        return row;
    }

    function humanSize(value) {
        if (value == null || value === '') return '—';
        const bytes = Number(value);
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1).replace('.', ',') + ' KB';
        return (bytes / 1048576).toFixed(1).replace('.', ',') + ' MB';
    }

    function feedback(message = '', error = false) {
        dialogFeedback.textContent = message;
        dialogFeedback.hidden = !message;
        dialogFeedback.dataset.error = String(error);
    }

    function showDialog(title, ...children) {
        feedback();
        dialogRequest++;
        delete dialog.dataset.preview;
        dialogTitle.textContent = title;
        dialogBody.replaceChildren(...children);
        if (!dialog.open) dialog.showModal();
    }

    find('[data-drive-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => {
        dialogRequest++;
        dialogBody.replaceChildren();
    });

    function openItem(item) {
        if (item.folder) {
            navigate(item.id, false, item);
        } else if (item.preview) {
            preview(item);
        } else if (item.download) {
            window.location.assign(itemUrl(item.id));
        }
    }

    function preview(item) {
        const url = itemUrl(item.id) + '?inline=1';
        const progress = element('div', 'drive-progress');
        const progressText = element('span', '', 'Carregando prévia…');
        progressText.setAttribute('role', 'status');
        const progressBar = element('progress');
        progressBar.setAttribute('aria-label', 'Progresso da prévia');
        progress.append(progressText, progressBar);
        if (item.preview === 'application/pdf' && window.SgcDocuments?.openPdf) {
            showDialog(item.name, progress, downloadLink(item));
            dialog.dataset.preview = 'loading';
            const turn = dialogRequest;
            fetch(url, {credentials: 'same-origin', globalLoader: false, headers: {Accept: 'application/pdf'}})
                .then(async response => {
                    if (!response.ok || !response.headers.get('Content-Type')?.includes('application/pdf')) throw new Error();
                    const reader = response.body?.getReader();
                    if (!reader) return response.blob();
                    const total = Number(response.headers.get('X-SGC-Content-Length')) || Number(item.size);
                    const chunks = [];
                    let received = 0;
                    if (total > 0) progressBar.max = total;
                    while (turn === dialogRequest) {
                        const {done, value} = await reader.read();
                        if (done) return new Blob(chunks, {type: 'application/pdf'});
                        chunks.push(value);
                        received += value.byteLength;
                        if (total > 0) {
                            progressBar.value = received;
                            progressText.textContent = 'Carregando prévia · ' + Math.min(100, Math.floor(received / total * 100)) + '%';
                        }
                    }
                    await reader.cancel();
                    return null;
                })
                .then(blob => {
                    if (turn !== dialogRequest || !blob) return;
                    const filename = /\.pdf$/i.test(item.name) ? item.name : item.name + '.pdf';
                    return window.SgcDocuments.openPdf(blob, filename, item.name).then(() => {
                        if (turn === dialogRequest && dialog.open) dialog.close();
                    });
                })
                .catch(() => {
                    if (turn !== dialogRequest) return;
                    progress.remove();
                    feedback('Não foi possível abrir a prévia. Você pode baixar o arquivo.', true);
                });
            return;
        }
        const kind = item.preview.startsWith('image/') ? 'img' :
            item.preview.startsWith('video/') ? 'video' : item.preview.startsWith('audio/') ? 'audio' : 'iframe';
        const media = element(kind, 'drive-preview');
        if (kind === 'img') media.alt = item.name;
        if (kind === 'iframe') {
            media.title = item.name;
            media.setAttribute('sandbox', '');
        }
        if (kind === 'video' || kind === 'audio') media.controls = true;
        showDialog(item.name, progress, media, downloadLink(item));
        const turn = dialogRequest;
        media.addEventListener(kind === 'audio' || kind === 'video' ? 'loadedmetadata' : 'load', () => progress.remove(), {once: true});
        media.addEventListener('error', () => {
            progress.remove();
            if (turn === dialogRequest) feedback('Não foi possível abrir a prévia. Você pode baixar o arquivo.', true);
        }, {once: true});
        media.src = url;
        dialog.dataset.preview = '';
    }

    function openActions(item, trash) {
        const actions = [];
        if (trash) {
            if (canManage && item.restore) actions.push(button('Restaurar', () => confirmTrash(item, 'restore'), 'arrow-counter-clockwise'));
        } else {
            if (item.preview) actions.push(button('Abrir prévia', () => preview(item), 'arrow-up-right'));
            if (item.download) actions.push(downloadLink(item));
            if (canManage && item.rename) actions.push(button('Renomear', () => nameDialog(item), 'pencil-simple'));
            if (canManage && item.move) actions.push(button('Mover', () => moveDialog(item), 'folder-simple-dashed'));
            if (canManage && item.trash) actions.push(button('Mover para a lixeira', () => confirmTrash(item, 'trash'), 'trash'));
        }
        showDialog(item.name, ...actions);
    }

    function bodyJson(data) {
        return {headers: {'Content-Type': 'application/json'}, body: JSON.stringify(data)};
    }

    function change(item, fields) {
        return request(itemUrl(item.id, false), {method: 'PATCH', ...bodyJson(fields)});
    }

    function invalidate() {
        generation++;
        cache.clear();
    }

    async function runAction(trigger, action, message) {
        const turn = dialogRequest;
        trigger.disabled = true;
        feedback('Salvando…');
        try {
            await action();
            invalidate();
            if (turn === dialogRequest) dialog.close();
            await load({force: true, foreground: true});
            setStatus(message);
        } catch (error) {
            if (turn === dialogRequest) feedback(error.message, true);
            else setStatus(error.message, true);
        } finally {
            trigger.disabled = false;
        }
    }

    function nameDialog(item = null) {
        const form = element('form');
        const label = element('label', '', 'Nome');
        const field = element('input');
        field.name = 'name';
        field.required = true;
        field.maxLength = 180;
        field.value = item?.name || '';
        label.append(field);
        const submit = element('button', 'drive-button drive-primary', item ? 'Salvar nome' : 'Criar pasta');
        submit.type = 'submit';
        form.append(label, submit);
        form.addEventListener('submit', event => {
            event.preventDefault();
            const name = field.value.trim();
            if (!name) {
                feedback('Informe o nome.', true);
                return;
            }
            runAction(submit, () => item ? change(item, {action: 'rename', name}) :
                request(api, {method: 'POST', ...bodyJson({kind: 'folder', folder: route.folder || null, name})}),
            item ? 'Nome atualizado.' : 'Pasta criada.');
        });
        showDialog(item ? 'Renomear item' : 'Nova pasta', form);
        field.focus();
        field.select();
    }

    function confirmTrash(item, action) {
        const isTrash = action === 'trash';
        const explanation = element('p', '', isTrash
            ? 'O item poderá ser restaurado na lixeira desta pasta.'
            : 'O item retornará à pasta original.');
        const submit = button(isTrash ? 'Mover para a lixeira' : 'Restaurar item',
            () => runAction(submit, () => change(item, {action}), isTrash ? 'Item movido para a lixeira.' : 'Item restaurado.'),
            isTrash ? 'trash' : 'arrow-counter-clockwise', 'drive-button drive-primary');
        showDialog(item.name, explanation, submit);
    }

    function moveDialog(item) {
        const location = element('p');
        const choices = element('div', 'drive-folder-choices');
        const next = button('Carregar mais pastas', () => chooseFolder(currentFolder, nextPage), 'caret-down');
        const up = button('Voltar uma pasta', () => chooseFolder(parentFolder), 'arrow-up');
        const submit = button('Mover para esta pasta', () => runAction(submit,
            () => change(item, {action: 'move', destination: currentFolder || null}), 'Item movido.'),
        'arrow-bend-up-right', 'drive-button drive-primary');
        let currentFolder = '';
        let parentFolder = '';
        let nextPage;
        let sequence = 0;
        showDialog('Mover ' + item.name, location, up, choices, next, submit);
        const turn = dialogRequest;

        async function chooseFolder(folder, cursor = null) {
            const requestId = ++sequence;
            submit.disabled = next.disabled = up.disabled = true;
            feedback('Carregando pastas…');
            try {
                const state = {folder, trash: false};
                const page = cursor ? await request(listUrl(state, '', cursor)) :
                    (cache.get(routeKey(state))?.page || await firstPage(state));
                if (requestId !== sequence || turn !== dialogRequest) return;
                currentFolder = folder;
                parentFolder = page.path.length > 2 ? page.path.at(-2).id : '';
                up.hidden = page.path.length < 2;
                location.textContent = page.path.map(entry => entry.label || entry.name).join(' / ');
                const options = page.items.filter(entry => entry.folder && entry.id !== item.id);
                if (!cursor) choices.replaceChildren();
                for (const entry of options) {
                    choices.append(button(entry.label || entry.name, () => chooseFolder(entry.id), 'folder'));
                }
                if (!choices.childNodes.length && !page.nextPage) choices.textContent = 'Nenhuma subpasta.';
                nextPage = page.nextPage;
                next.hidden = !nextPage;
                submit.disabled = !page.canAdd || page.path.some(entry => entry.id === item.id);
                feedback();
            } catch (error) {
                if (requestId === sequence && turn === dialogRequest) feedback(error.message, true);
            } finally {
                if (requestId === sequence && turn === dialogRequest) next.disabled = up.disabled = false;
            }
        }

        chooseFolder('');
    }

    search.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            searchTerm = search.value.trim();
            load();
        }, 260);
    });
    trashSwitch.addEventListener('click', () => navigate(route.folder, !route.trash));
    find('[data-drive-refresh]').addEventListener('click', () => load({force: true, foreground: true}));
    more.addEventListener('click', () => {
        if (data?.nextPage && !more.disabled) load({append: true});
    });

    create?.addEventListener('click', () => nameDialog());
    upload?.addEventListener('click', () => input.click());
    input?.addEventListener('change', async () => {
        const selected = Array.from(input.files);
        input.value = '';
        if (!selected.length || uploading) return;
        const destination = route.folder || null;
        const failed = [];
        uploading = true;
        setActions();
        for (const [index, file] of selected.entries()) {
            setStatus('Enviando ' + (index + 1) + ' de ' + selected.length + ' · ' + file.name);
            const body = new FormData();
            body.set('kind', 'file');
            if (destination) body.set('folder', destination);
            body.set('file', file);
            try {
                await request(api, {method: 'POST', body});
            } catch (error) {
                failed.push(file.name + ' (' + error.message + ')');
            }
        }
        uploading = false;
        invalidate();
        await load({force: true, foreground: true});
        setStatus(failed.length ? 'Falha ao enviar: ' + failed.join('; ') : 'Arquivos enviados.', failed.length > 0);
    });

    load();
})();
