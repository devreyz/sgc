import {getBundle, queued, guardedUpdate, refreshBundle, requestedTenant, hasOfflineAccess} from './provider-offline-store.js';
import {estimate, currency, beneficiaryPdf, sharePdf} from './provider-offline-document.js';

const get = id => document.getElementById(id);
const message = get('message'), form = get('order-form'), actions = get('order-actions');
let bundle, pending = [], active, syncing = false, saving = false;
let agendaState = '';
let agendaView = 'calendar', selectedDay = '', currentMonth, renderedRoute;
const picker = get('field-picker');
const pickerSearch = get('field-picker-search');
const pickerOptions = get('field-picker-options');
let pickerSelect, pickerButton;
const initialLink = new URL(location.href);
const oldOrder = initialLink.searchParams.get('os') || location.pathname.match(/\/provider\/orders\/(\d+)\/?$/)?.[1];
const initialRoute = oldOrder && /^\d+$/.test(oldOrder) ? 'os/'+oldOrder
    : initialLink.searchParams.get('new') === '1' || /\/provider\/orders\/create\/?$/.test(location.pathname) ? 'nova'
        : initialLink.searchParams.get('screen') !== 'inicio' && requestedTenant() ? 'agenda' : 'inicio';
const route = () => location.hash.slice(1) || initialRoute;
const openRoute = path => { location.hash = path; };
if (!location.hash) history.replaceState(null, '', '#'+initialRoute);

function element(tag, text) {
    const node = document.createElement(tag);
    if (text) node.textContent = text;
    return node;
}

function button(text, action, primary = false) {
    const node = element('button', text);
    node.type = 'button';
    if (primary) node.className = 'primary';
    node.addEventListener('click', action);
    return node;
}

function field(parent, label, name, value = '', type = 'text') {
    const wrapper = element('label', label);
    const input = element(type === 'textarea' ? 'textarea' : 'input');
    if (input.tagName === 'INPUT') input.type = type;
    input.name = name;
    if (type !== 'file') input.value = value ?? '';
    wrapper.append(input);
    parent.append(wrapper);
    return input;
}

function select(parent, label, name, options, value) {
    const enhanced = typeof picker.showModal === 'function';
    const wrapper = element(enhanced ? 'div' : 'label', label), input = element('select');
    input.name = name;
    input.append(new Option('Selecione', ''));
    for (const [id, text] of options) input.append(new Option(text, id));
    input.value = value ?? '';
    wrapper.append(input);
    parent.append(wrapper);
    if (!enhanced) return input;

    wrapper.classList.add('field-select');
    input.classList.add('field-select-native');
    input.tabIndex = -1;
    input.setAttribute('aria-hidden', 'true');
    const trigger = button('', () => {
        pickerSelect = input;
        pickerButton = trigger;
        get('field-picker-title').textContent = label;
        pickerSearch.value = '';
        pickerSearch.hidden = input.options.length <= 6;
        renderPicker();
        picker.showModal();
        (pickerSearch.hidden ? pickerOptions.querySelector('[aria-pressed=true]') || pickerOptions.querySelector('button') : pickerSearch)?.focus();
    });
    trigger.className = 'field-select-trigger';
    trigger.setAttribute('aria-haspopup', 'dialog');
    trigger.setAttribute('aria-controls', 'field-picker');
    trigger.dataset.fieldSelect = label;
    const error = element('small', 'Selecione uma opção para continuar.');
    error.className = 'field-select-error';
    error.hidden = true;
    input.addEventListener('invalid', event => {
        event.preventDefault();
        error.hidden = false;
        trigger.setAttribute('aria-invalid', 'true');
        if (!form.querySelector('.field-select-trigger:focus[aria-invalid=true]')) trigger.focus();
    });
    input.addEventListener('change', () => {
        error.hidden = true;
        trigger.removeAttribute('aria-invalid');
        refreshSelectTriggers(parent);
    });
    wrapper.append(trigger, error);
    refreshSelectTriggers(wrapper);
    return input;
}

const searchable = value => String(value ?? '').normalize('NFD').replace(/\p{Diacritic}/gu, '').toLocaleLowerCase('pt-BR').trim();

function refreshSelectTriggers(scope = form) {
    for (const trigger of scope.querySelectorAll('[data-field-select]')) {
        const select = trigger.parentElement.querySelector('select');
        trigger.textContent = select.selectedOptions[0]?.text || 'Selecione';
        trigger.disabled = select.disabled;
        trigger.setAttribute('aria-label', trigger.dataset.fieldSelect + ': ' + trigger.textContent);
    }
}

function renderPicker() {
    pickerOptions.replaceChildren();
    if (!pickerSelect) return;
    const query = searchable(pickerSearch.value);
    for (const option of pickerSelect.options) {
        if (option.disabled || (!option.value && pickerSelect.required) || !searchable(option.text).includes(query)) continue;
        const choice = button(option.value ? option.text : 'Sem seleção', () => {
            pickerSelect.value = option.value;
            pickerSelect.dispatchEvent(new Event('input', {bubbles: true}));
            pickerSelect.dispatchEvent(new Event('change', {bubbles: true}));
            picker.close();
        });
        choice.setAttribute('aria-pressed', String(option.selected));
        pickerOptions.append(choice);
    }
    if (!pickerOptions.childElementCount) pickerOptions.append(element('p', 'Nenhuma opção encontrada.'));
}

function formGroup(label) {
    const group = element('fieldset');
    group.className = 'order-field-group';
    group.append(element('legend', label));
    form.append(group);
    return group;
}

const editableState = order => ['draft','in_progress','rejected'].includes(order.execution);
const displayDate = value => value?.slice(0, 10).split('-').reverse().join('/');
const orderDay = order => (order?.executionDate || order?.scheduled || '').slice(0, 10);
const rowDay = row => orderDay(row.orderSnapshot) || row.payload.creation?.scheduled_at?.slice(0, 10) || '';
const today = () => new Intl.DateTimeFormat('sv-SE', {timeZone:bundle.timezone || 'America/Sao_Paulo'}).format(new Date());
const monthStart = (month, offset = 0) => {
    const [year, number] = month.split('-').map(Number);
    return new Date(Date.UTC(year, number - 1 + offset, 1)).toISOString().slice(0, 7);
};

function renderCalendar(orders) {
    get('calendar').hidden = agendaView !== 'calendar';
    get('view-calendar').setAttribute('aria-pressed', String(agendaView === 'calendar'));
    get('view-list').setAttribute('aria-pressed', String(agendaView === 'list'));
    const minimum = bundle.window.from.slice(0, 7), maximum = bundle.window.to.slice(0, 7);
    const todayDate = today();
    currentMonth = currentMonth >= minimum && currentMonth <= maximum ? currentMonth : todayDate.slice(0, 7);
    if (selectedDay < bundle.window.from || selectedDay > bundle.window.to) selectedDay = '';
    const date = new Date(currentMonth+'-01T00:00:00Z');
    get('calendar-month').textContent = new Intl.DateTimeFormat('pt-BR', {month:'long',year:'numeric',timeZone:'UTC'}).format(date);
    get('previous-month').disabled = currentMonth <= minimum;
    get('next-month').disabled = currentMonth >= maximum;
    const focusDay = document.activeElement?.dataset.calendarDay;
    const grid = get('calendar-days');
    grid.replaceChildren();
    const counts = new Map();
    for (const key of [...orders.map(orderDay), ...pending.map(rowDay)]) {
        if (key) counts.set(key, (counts.get(key) || 0) + 1);
    }
    for (let slot = 0; slot < date.getUTCDay(); slot++) grid.append(element('span'));
    while (date.toISOString().slice(0, 7) === currentMonth) {
        const day = date.toISOString().slice(0, 10);
        const count = counts.get(day) || 0;
        const cell = button(String(date.getUTCDate()), () => {
            selectedDay = selectedDay === day ? '' : day;
            renderList();
        });
        cell.dataset.calendarDay = day;
        cell.disabled = day < bundle.window.from || day > bundle.window.to;
        cell.setAttribute('aria-pressed', String(day === selectedDay));
        cell.setAttribute('aria-label', displayDate(day) + (count ? ', '+count+' ordens' : ''));
        if (day === todayDate) cell.setAttribute('aria-current', 'date');
        if (count) cell.append(element('small', String(count)));
        grid.append(cell);
        if (focusDay === day) cell.focus({preventScroll:true});
        date.setUTCDate(date.getUTCDate() + 1);
    }
    get('agenda-results-heading').textContent = agendaView === 'calendar' && selectedDay ? 'Ordens de '+displayDate(selectedDay) : 'Ordens na agenda';
}
const localStatus = row => ({
    schedule: 'Agendamento salvo no aparelho', draft: 'Rascunho salvo no aparelho',
    start: 'Em execução no aparelho', finish: 'Finalizado no aparelho • Aguardando envio',
})[row.payload.action];

function renderList() {
    const currentRoute = route();
    const home = !bundle || currentRoute === 'inicio';
    get('offline-home').hidden = !home;
    get('workspace').hidden = home || !!active;
    get('editor').hidden = !bundle || !active;
    get('offline-help').hidden = !home;
    get('offline-account').hidden = !bundle;
    get('open-provider').hidden = !bundle;
    get('portal-unavailable').hidden = !!bundle;
    get('account-name').textContent = bundle?.accountName || bundle?.provider || '';
    get('account-role').textContent = bundle ? (bundle.operator ? 'Operação de serviços' : 'Prestador de serviços') : '';
    for (const link of document.querySelectorAll('[data-offline-link]')) {
        const tab = link.dataset.offlineLink;
        link.hidden = tab !== 'inicio' && (!bundle || (tab === 'nova' && !bundle.catalog.length));
        if (tab === (home ? 'inicio' : currentRoute === 'nova' ? 'nova' : 'agenda')) link.setAttribute('aria-current', 'page');
        else link.removeAttribute('aria-current');
    }
    if (!bundle) {
        get('organization').textContent = '';
        get('orders').replaceChildren();
        get('queue-orders').replaceChildren();
        form.replaceChildren();
        actions.replaceChildren();
        get('calendar-days').replaceChildren();
        for (const id of ['agenda-summary', 'updated', 'agenda-period', 'editor-title']) get(id).textContent = '';
        get('order-status').textContent = '';
        get('estimate').textContent = '';
        get('portal').href = '/app';
        return;
    }
    get('organization').textContent = bundle.organization;
    get('portal').href = bundle.portalUrl;
    get('updated').textContent = 'Atualizado em '+new Date(bundle.issuedAt).toLocaleString('pt-BR');
    get('agenda-period').textContent = displayDate(bundle.window.from)+' a '+displayDate(bundle.window.to);
    const scheduled = bundle.orders.filter(order => order.state === 'scheduled').length;
    get('agenda-summary').textContent = scheduled+' agendada(s) · '+(bundle.orders.length - scheduled)+' em execução · '+pending.length+' na fila';
    get('create').disabled = bundle.catalog.length === 0;
    get('catalog-status').hidden = bundle.catalog.length > 0;

    const queue = get('queue-orders');
    queue.replaceChildren();
    queue.hidden = pending.length === 0;
    if (pending.length) queue.append(element('h3', 'Aguardando sincronização'));
    for (const row of pending) {
        const current = bundle.orders.find(order => order.id === row.payload.order_id);
        const order = current || row.orderSnapshot;
        const item = button(row.display.beneficiary || row.display.service || 'Nova ordem', () => {
            if (!order && row.payload.order_id) {
                message.textContent = 'Esta ordem está fora da agenda de campo. O registro salvo continua na fila de sincronização.';
                return;
            }
            openRoute('registro/'+row.id);
        });
        item.className = 'order-row';
        item.append(element('small', [order?.number, row.error || localStatus(row)].filter(Boolean).join(' · ')));
        queue.append(item);
    }

    const root = get('orders');
    root.replaceChildren();
    const query = searchable(get('order-search').value);
    const queuedIds = new Set(pending.map(row => row.payload.order_id));
    const matching = bundle.orders.filter(order => !queuedIds.has(order.id)
        && (!agendaState || agendaState === order.state)
        && searchable([order.number, order.beneficiary, order.service, order.location].filter(Boolean).join(' ')).includes(query));
    renderCalendar(matching);
    for (const order of matching) {
        if (agendaView === 'calendar' && selectedDay && orderDay(order) !== selectedDay) continue;
        const item = button(order.beneficiary || order.number, () => openRoute('os/'+order.id));
        item.className = 'order-row';
        item.append(element('small', [order.number, order.service, displayDate(order.executionDate || order.scheduled)].filter(Boolean).join(' · ')));
        const status = element('span', order.state === 'in_progress' ? 'Em execução' : 'Agendada');
        status.className = 'order-status';
        status.dataset.state = order.state;
        item.append(status);
        root.append(item);
    }
    if (!root.childElementCount) root.append(element('p', query || agendaState ? 'Nenhuma ordem encontrada com esses filtros.' : 'Nenhuma ordem agendada ou em execução neste período.'));
}

function chosenVersion() {
    const field = form.elements.namedItem('service_version_id');
    return bundle.catalog.find(item => String(item.id) === field?.value);
}

function renderFields(catalog, target, values, files, writable, isNew) {
    target.replaceChildren();
    const groups = {};
    for (const item of catalog?.fields || []) {
        if (item.phase === 'review') continue;
        const editable = writable && item.editable_by_provider && (isNew || item.phase !== 'order');
        const evidence = ['image','file','signature'].includes(item.type);
        if (evidence && (!editable || item.phase === 'order')) continue;
        const phase = evidence ? 'evidence' : item.phase || 'execution';
        if (!groups[phase]) {
            const group = element('fieldset');
            group.className = 'order-field-group';
            group.append(element('legend', ({order:'Dados da ordem', start:'Início do serviço', finish:'Finalização', evidence:'Evidências'})[phase] || 'Medições da execução'));
            groups[phase] = group;
        }
        const group = groups[phase];
        if (evidence) {
            const input = field(group,item.label,'file:'+item.key,'','file');
            input.accept = item.accepted_mime_types?.length ? item.accepted_mime_types.join(',') : item.type === 'file' ? 'image/*,application/pdf' : 'image/*';
            if (item.type !== 'file') input.setAttribute('capture','environment');
            if (files[item.key] || active.order?.evidences?.includes(item.key)) group.append(element('small',files[item.key]?.name || 'Evidência já registrada no servidor'));
            continue;
        }
        let input;
        const name = (item.phase === 'order' ? 'order:' : 'value:')+item.key;
        const current = values[item.key] ?? item.default_value ?? '';
        if (item.type === 'select') {
            const choices = item.options || {};
            input = select(group,item.label,name,Array.isArray(choices) ? choices.map(choice => [choice,choice]) : Object.entries(choices),current);
        } else if (item.type === 'boolean') {
            input = select(group,item.label,name,[['1','Sim'],['0','Não']],current === true ? '1' : current === false ? '0' : current);
        } else {
            const type = {date:'date',time:'time',datetime:'datetime-local',textarea:'textarea'}[item.type] || 'text';
            input = field(group,item.label+(item.unit ? ' ('+item.unit+')' : ''),name,current,type);
            if (['meter','integer','decimal','money','quantity'].includes(item.type)) input.inputMode = item.type === 'integer' ? 'numeric' : 'decimal';
        }
        input.disabled = !editable;
        if (item.required && item.phase === 'order' && !item.conditional_rule) input.required = editable;
    }
    for (const phase of ['order', 'start', 'execution', 'finish', 'evidence']) {
        if (groups[phase]) target.append(groups[phase]);
    }
    const startKey = catalog?.meter_pair?.[0], endKey = catalog?.meter_pair?.[1];
    if (startKey && writable && catalog.fields.some(item => item.key === endKey && item.editable_by_provider)) {
        const pauses = element('fieldset');
        pauses.dataset.meterPauses = '';
        pauses.append(element('legend','Pausas de medição'));
        function addPause(pause = {}) {
            const row = element('div'), inputs = element('div');
            inputs.className = 'pair';
            field(inputs,'Pausa','pause-stop',pause.stop).inputMode = 'decimal';
            field(inputs,'Retorno','pause-resume',pause.resume).inputMode = 'decimal';
            row.append(inputs, button('Remover pausa',() => {row.remove(); preview();}));
            pauses.insertBefore(row,add);
        }
        const add = button('Adicionar pausa',() => addPause(),true);
        pauses.append(add);
        try {JSON.parse(values['meter_pauses_'+startKey] || '[]').forEach(addPause);} catch {}
        target.append(pauses);
    }
    refreshSelectTriggers(target);
}

function openOrder(order, row = null) {
    active = {order:order || null,row,scope:bundle.scope,accountEpoch:bundle.accountEpoch};
    const reference = row?.payload.creation || {};
    const writable = !row?.sent && !row?.conflict && (!order || editableState(order));
    get('editor-title').textContent = order?.number || 'Nova ordem';
    get('order-status').textContent = row?.error || (row ? localStatus(row) : order?.status || '');
    form.replaceChildren();
    actions.replaceChildren();
    if (!order) {
        const serviceFields = formGroup('Serviço e prestador');
        const version = select(serviceFields,'Serviço','service_version_id', bundle.catalog.map(item => [item.id,item.name]),reference.service_version_id);
        version.required = !bundle.catalog.length ? false : writable;
        version.disabled = !writable;
        if (!version.value && bundle.catalog.length === 1) version.value = bundle.catalog[0].id;
        if (bundle.operator) {
            const assignedProvider = select(serviceFields,'Prestador responsável','service_provider_id',
                bundle.providers.map(item => [item.id,item.name]), reference.service_provider_id ?? bundle.providerId);
            assignedProvider.disabled = !writable;
            assignedProvider.required = writable;
            const updateProviders = () => {
                const eligible = (chosenVersion()?.providerIds || []).map(String);
                for (const option of assignedProvider.options) {
                    option.disabled = !!option.value && !eligible.includes(option.value);
                }
                if (assignedProvider.selectedOptions[0]?.disabled) assignedProvider.value = '';
                if (!assignedProvider.value && eligible.length === 1) assignedProvider.value = eligible[0];
                refreshSelectTriggers();
            };
            version.addEventListener('change',updateProviders);
            updateProviders();
        }
        const contact = formGroup('Beneficiário e contato');
        const member = select(contact,'Membro associado','associate_id', bundle.members.map(item => [item.id,item.name]),reference.associate_id);
        member.disabled = !writable;
        const beneficiary = field(contact,'Beneficiário','beneficiary_name',reference.beneficiary_name);
        beneficiary.disabled = !writable;
        member.addEventListener('change',() => {
            if (!beneficiary.value) beneficiary.value = bundle.members.find(item => String(item.id) === member.value)?.name || '';
        });
        beneficiary.required = writable;
        const whatsapp = field(contact,'WhatsApp','beneficiary_whatsapp',reference.beneficiary_whatsapp,'tel');
        whatsapp.maxLength = 25; whatsapp.disabled = !writable;
        const schedule = formGroup('Agendamento e local');
        const scheduledDate = field(schedule,'Agendamento','scheduled_at',reference.scheduled_at || (selectedDay ? selectedDay+'T08:00' : ''),'datetime-local');
        scheduledDate.required = writable;
        scheduledDate.disabled = !writable;
        const locationField = field(schedule,'Local','location',reference.location);
        locationField.maxLength = 191;
        locationField.disabled = !writable;
        version.addEventListener('change',() => {
            member.required = Boolean(chosenVersion()?.membersOnly);
            renderFields(chosenVersion()?.catalog, dynamic, {}, {}, writable, true);
            preview();
        });
    } else {
        form.append(element('p',[order.beneficiary,order.service,order.location].filter(Boolean).join(' • ')));
    }
    const dynamic = element('div');
    dynamic.style.display = 'grid'; dynamic.style.gap = '.65rem';
    form.append(dynamic);
    if (!order) form.elements.namedItem('associate_id').required = !!chosenVersion()?.membersOnly && writable;
    renderFields(order?.catalog || chosenVersion()?.catalog, dynamic, {...order?.values,...reference.order_data,...row?.payload.values},row?.files || {},writable,!order);
    refreshSelectTriggers();
    if (writable) {
        if (!order) actions.append(button(row ? 'Salvar ordem' : 'Criar ordem',() => save('schedule'),true));
        actions.append(button('Salvar rascunho',() => save('draft')));
        if (order?.execution !== 'in_progress') actions.append(button('Iniciar serviço',() => save('start')));
        actions.append(button('Finalizar para enviar',() => save('finish'),Boolean(order)));
    }
    if (row?.error && !row.sent) {
        if (row.conflict && order && editableState(order)) actions.append(button('Revisar com os dados atuais', async () => {
            if (!confirm('Conferir as medições locais antes de aplicar sobre a versão atual da ordem?')) return;
            const copy = {...row, conflict:false, error:null,
                payload:{...row.payload,lock_version:order.lockVersion}};
            openOrder(order,copy);
        }));
    }
    if (writable && row) actions.append(button('Descartar registro local',async () => {
        if (!confirm('Descartar os dados e anexos salvos somente neste aparelho?')) return;
        if (!await guardedUpdate(null,row)) {
            message.textContent = 'O registro está sendo sincronizado. Atualize a lista.';
            return;
        }
        openRoute('agenda');
    }));
    actions.append(button('Compartilhar via em PDF', async () => {
        if (!hasOfflineAccess(bundle)) {await redraw();return;}
        const model = currentOrder();
        if (!model.beneficiary || !model.service) { message.textContent='Informe o serviço e o beneficiário.'; return; }
        const provisional = Boolean(row) || !model.totals;
        let totals;
        try { totals = estimate(model, values(model.catalog)); } catch {}
        if (!provisional && model.totals) totals = model.totals;
        const blob = beneficiaryPdf({organization:bundle.organization,logo:bundle.logo,provider:model.provider ?? bundle.provider,order:model,
            status:row ? localStatus(row) : order?.status || 'Rascunho no aparelho',totals,provisional});
        try { await sharePdf(blob,(model.number || 'ordem-local')+'-beneficiario.pdf'); }
        catch (error) { if (error.name !== 'AbortError') message.textContent = 'O aparelho não conseguiu compartilhar o PDF.'; }
    }));
    const phone = currentOrder().whatsapp;
    if (phone) {
        const link = element('a','Abrir WhatsApp');
        const digits = String(phone).replace(/\D/g,'');
        link.href = 'https://wa.me/'+(digits.length === 10 || digits.length === 11 ? '55'+digits : digits);
        link.target = '_blank'; link.rel = 'noopener';
        actions.append(link);
    }
    renderList();
    get('editor-title').focus({preventScroll:true});
    window.scrollTo({top:0});
    preview();
}

function currentOrder() {
    if (active.order) return active.order;
    const version = chosenVersion();
    const selectedProvider = form.elements.namedItem('service_provider_id')?.value;
    const compensation = version?.providerRates?.[selectedProvider];
    const catalog = compensation ? {...version.catalog,provider_compensation:compensation} : version?.catalog;
    const provider = bundle.providers?.find(item => String(item.id) === selectedProvider)?.name ?? bundle.provider;
    return {service:version?.name,unit:version?.unit,catalog,provider,beneficiary:form.elements.namedItem('beneficiary_name')?.value,
        whatsapp:form.elements.namedItem('beneficiary_whatsapp')?.value,scheduled:form.elements.namedItem('scheduled_at')?.value,location:form.elements.namedItem('location')?.value};
}

function values(catalog) {
    const result = {...active.order?.values,...active.row?.payload.values};
    const payload = new FormData(form), pauses = payload.getAll('pause-stop');
    for (const [name,value] of payload) if (name.startsWith('value:') || name.startsWith('order:')) result[name.slice(6)] = value;
    const startKey = catalog?.meter_pair?.[0];
    if (startKey && form.querySelector('[data-meter-pauses]')) {
        const resumes = payload.getAll('pause-resume');
        result['meter_pauses_'+startKey] = JSON.stringify(pauses.map((stop,index) => ({stop,resume:resumes[index]})));
    }
    return result;
}

function preview() {
    if (!active || !bundle) return;
    const order = currentOrder();
    try {
        const totals = order.totals || estimate(order,values(order.catalog));
        get('estimate').textContent = (order.totals ? 'Valores registrados: ' : 'Estimativa no aparelho: ') + totals.quantity+' '+(order.unit || '')+' · '+currency(totals.receivable)+' pelo serviço · '+currency(totals.payable)+' ao prestador';
    } catch (error) {
        get('estimate').textContent = error.message;
    }
}

async function save(action) {
    if (saving) return;
    if (!hasOfflineAccess(bundle)) {await redraw();return;}
    if (!active || active.scope !== bundle.scope || !form.reportValidity()) return;
    const model = currentOrder(), row = active.row;
    const data = new FormData(form), currentValues = values(model.catalog), creation = {};
    if (!model.id) {
        for (const key of ['service_version_id','service_provider_id','beneficiary_name','beneficiary_whatsapp','associate_id','scheduled_at','location']) {
            if (data.has(key)) creation[key] = data.get(key) || '';
        }
        creation.order_data = Object.fromEntries([...data].filter(([name]) => name.startsWith('order:')).map(([name,value]) => [name.slice(6),value]));
    }
    const files = {...row?.files};
    for (const [key,value] of data) {
        if (!key.startsWith('file:') || !(value instanceof File) || !value.size) continue;
        if (value.size > 12*1024*1024) {message.textContent='Cada evidência deve ter até 12 MB.';return;}
        files[key.slice(5)] = value;
    }
    if (action === 'schedule' && (row?.payload.started_at || row?.payload.action === 'finish')) {
        action = row.payload.action;
    } else if (action === 'schedule' && (Object.keys(files).length
        || [...data].some(([key,value]) => (key.startsWith('value:') || key.startsWith('pause-')) && String(value).trim()))) {
        action = 'draft';
    }
    if (action === 'finish' || action === 'start') {
        const phases = action === 'start' ? ['start'] : ['order','start','execution','finish'];
        for (const item of model.catalog.fields) {
            if (!item.required || item.conditional_rule || !phases.includes(item.phase) || !item.editable_by_provider) continue;
            const evidence = ['image','file','signature'].includes(item.type);
            if (evidence ? !files[item.key] && !model.evidences?.includes(item.key) : !String(currentValues[item.key] ?? '').trim()) {
                message.textContent = 'Preencha '+item.label+' para continuar.'; return;
            }
        }
        if (action === 'finish') {
            try {estimate(model,currentValues);} catch(error) {message.textContent=error.message;return;}
        }
    }
    const now = new Date().toISOString();
    const next = {id:row && !row.rejected ? row.id : crypto.randomUUID(),scope:bundle.scope,
        revision:crypto.randomUUID(),display:{beneficiary:model.beneficiary,service:model.service},files,
        orderSnapshot: model.id ? model : null,
        payload:{order_id:model.id,lock_version:model.lockVersion,action,creation:model.id ? null : creation,
            values:currentValues,recorded_at:now,started_at:row?.payload.started_at || (action === 'start' ? now : null)}};
    saving = true;
    try {
        if (!await guardedUpdate(next,row)) throw new Error('Este registro mudou em outra aba. Volte à lista e confira a versão mais recente.');
        active = null;
        history.replaceState(null, '', '#agenda');
        message.textContent = model.id ? 'Registro salvo neste aparelho.' : 'Ordem salva neste aparelho. O número será atribuído após a sincronização.';
        await redraw();
        if (navigator.onLine) synchronize(true);
    } catch (error) {
        message.textContent = error.message;
    } finally {
        saving = false;
    }
}

let redrawVersion = 0, accessTimer;
async function redraw() {
    const version = ++redrawVersion;
    const stored = await getBundle();
    const queue = stored ? await queued(stored.scope) : [];
    if (version !== redrawVersion) return;
    bundle = hasOfflineAccess(stored) ? stored : null;
    const currentRoute = route();
    if (!bundle || renderedRoute !== currentRoute
        || (active && (active.scope !== bundle.scope || active.accountEpoch !== bundle.accountEpoch))) {
        if (picker.open) picker.close();
        active = null;
        form.replaceChildren();
        actions.replaceChildren();
    }
    const switched = renderedRoute !== currentRoute;
    renderedRoute = currentRoute;
    pending = bundle ? queue : [];
    clearTimeout(accessTimer);
    if (bundle) accessTimer = setTimeout(redraw, Math.max(0, Date.parse(bundle.expiresAt) - Date.now()));
    get('connection').textContent = navigator.onLine ? 'Online' : 'Sem internet';
    get('connection').dataset.online = String(navigator.onLine);
    get('refresh').hidden = !bundle;
    if (!bundle) message.textContent = 'Conecte-se para confirmar a sessão e carregar os portais.';
    else if (!active) {
        if (currentRoute === 'nova' && bundle.catalog.length) return openOrder(null);
        const id = currentRoute.match(/^os\/(\d+)$/)?.[1];
        const queueId = currentRoute.match(/^registro\/([a-f0-9-]+)$/i)?.[1];
        const row = pending.find(item => queueId ? item.id === queueId : id && String(item.payload.order_id) === id);
        const order = bundle.orders.find(item => String(item.id) === (id || String(row?.payload.order_id)));
        if (order || row?.orderSnapshot || (row && !row.payload.order_id)) return openOrder(order || row.orderSnapshot, row);
        if (id || queueId) message.textContent = 'Esta ordem não está disponível na agenda deste aparelho.';
    }
    renderList();
    if (switched) {
        get(!bundle || currentRoute === 'inicio' ? 'offline-home-title' : 'agenda-heading').focus({preventScroll:true});
        window.scrollTo({top:0});
    }
}

async function synchronize(force = false) {
    if (syncing || !navigator.onLine || (!requestedTenant() && !await getBundle())) return;
    syncing = true;
    get('refresh').disabled = true;
    try {
        message.textContent = pending.length ? 'Sincronizando '+pending.length+' registro(s)…' : 'Atualizando a agenda e o catálogo…';
        await refreshBundle({force});
        await redraw();
        if (bundle) message.textContent = pending.length ? 'Há registros que precisam de revisão ou envio.' : 'Dados atualizados neste aparelho.';
    } catch (error) {
        await redraw();
        message.textContent = error.message;
    } finally {
        syncing = false;
        get('refresh').disabled = false;
    }
}

form.addEventListener('submit',event => {
    event.preventDefault();
    if (active && !active.order && !active.row?.sent && !active.row?.conflict) save('schedule');
});
form.addEventListener('input',preview);
pickerSearch.addEventListener('input', renderPicker);
get('field-picker-close').addEventListener('click', () => picker.close());
picker.addEventListener('close', () => {
    pickerButton?.focus();
    pickerButton = pickerSelect = null;
    pickerOptions.replaceChildren();
});
get('order-search').addEventListener('input', renderList);
document.querySelectorAll('[data-agenda-state]').forEach(filter => filter.addEventListener('click', () => {
    agendaState = filter.dataset.agendaState;
    document.querySelectorAll('[data-agenda-state]').forEach(item => item.setAttribute('aria-pressed', String(item === filter)));
    renderList();
}));
get('create').addEventListener('click',() => openRoute('nova'));
get('view-calendar').addEventListener('click', () => {agendaView = 'calendar'; renderList();});
get('view-list').addEventListener('click', () => {agendaView = 'list'; renderList();});
get('previous-month').addEventListener('click', () => {currentMonth = monthStart(currentMonth, -1); selectedDay = ''; renderList();});
get('next-month').addEventListener('click', () => {currentMonth = monthStart(currentMonth, 1); selectedDay = ''; renderList();});
get('agenda-today').addEventListener('click', () => {selectedDay = today(); currentMonth = selectedDay.slice(0, 7); renderList();});
get('agenda-all').addEventListener('click', () => {selectedDay = ''; renderList();});
window.sgcOverlayBack = () => {
    if (picker.open) {
        picker.close();
        return true;
    }
    if (!active) return false;
    openRoute('agenda');
    return true;
};
get('refresh').addEventListener('click',() => synchronize(true));
window.addEventListener('sgc-provider-data',redraw);
window.addEventListener('hashchange', redraw);
window.addEventListener('pageshow', event => { if (event.persisted) redraw(); });
window.addEventListener('online',() => synchronize(true));
window.addEventListener('offline',redraw);
document.addEventListener('visibilitychange',() => {
    if (!document.hidden) navigator.onLine ? synchronize() : redraw();
});
setInterval(() => navigator.onLine ? synchronize() : redraw(),10*60*1000);
try {
    await redraw();
    if (navigator.onLine) synchronize();
} catch {
    message.textContent = 'O navegador não disponibilizou armazenamento local para o modo offline.';
}

