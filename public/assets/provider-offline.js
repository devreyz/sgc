import {getBundle, queued, guardedUpdate, refreshBundle, requestedTenant, hasOfflineAccess} from './provider-offline-store.js';
import {estimate, currency, beneficiaryPdf, sharePdf} from './provider-offline-document.js';

const get = id => document.getElementById(id);
const message = get('message'), form = get('order-form'), actions = get('order-actions');
let bundle, pending = [], active, syncing = false;
let requestedOrderId = new URL(location.href).searchParams.get('os') || location.pathname.match(/\/provider\/orders\/(\d+)$/)?.[1];

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
    const wrapper = element('label', label), input = element('select');
    input.name = name;
    input.append(new Option('Selecione', ''));
    for (const [id, text] of options) input.append(new Option(text, id));
    input.value = value ?? '';
    wrapper.append(input);
    parent.append(wrapper);
    return input;
}

const editableState = order => ['draft','in_progress','rejected'].includes(order.execution);
const localStatus = row => ({
    schedule: 'Agendamento salvo no aparelho', draft: 'Rascunho salvo no aparelho',
    start: 'Em execução no aparelho', finish: 'Finalizado no aparelho • Aguardando envio',
})[row.payload.action];

function renderList() {
    get('workspace').hidden = !bundle || !!active;
    get('editor').hidden = !bundle || !active;
    const root = get('orders');
    root.replaceChildren();
    if (!bundle) return;
    get('organization').textContent = bundle.organization;
    get('portal').href = bundle.portalUrl;
    get('updated').textContent = 'Dados de '+new Date(bundle.issuedAt).toLocaleString('pt-BR')+' • '+pending.length+' pendente(s)';
    get('create').disabled = bundle.catalog.length === 0;
    for (const row of pending) {
        const item = button(row.display.beneficiary || row.display.service || 'Nova ordem', () => openOrder(bundle.orders.find(order => order.id === row.payload.order_id),row));
        item.className = 'order-row';
        item.append(element('small', row.error || localStatus(row)));
        root.append(item);
    }
    for (const order of bundle.orders) {
        if (pending.some(row => row.payload.order_id === order.id)) continue;
        const label = [order.number, order.beneficiary].filter(Boolean).join(' · ');
        const item = button(label, () => openOrder(order));
        item.className = 'order-row';
        item.append(element('small', [order.service, order.scheduled?.slice(0,10).split('-').reverse().join('/'), order.status].filter(Boolean).join(' • ')));
        root.append(item);
    }
    if (!root.childElementCount) root.append(element('p','Nenhuma ordem baixada.'));
}

function chosenVersion() {
    const field = form.elements.namedItem('service_version_id');
    return bundle.catalog.find(item => String(item.id) === field?.value);
}

function renderFields(catalog, target, values, files, writable, isNew) {
    target.replaceChildren();
    for (const item of catalog?.fields || []) {
        if (item.phase === 'review') continue;
        const editable = writable && item.editable_by_provider && (isNew || item.phase !== 'order');
        if (['image','file','signature'].includes(item.type)) {
            if (!editable || item.phase === 'order') continue;
            const input = field(target,item.label,'file:'+item.key,'','file');
            input.accept = item.accepted_mime_types?.length ? item.accepted_mime_types.join(',') : item.type === 'file' ? 'image/*,application/pdf' : 'image/*';
            if (item.type !== 'file') input.setAttribute('capture','environment');
            if (files[item.key] || active.order?.evidences?.includes(item.key)) target.append(element('small',files[item.key]?.name || 'Evidência já registrada no servidor'));
            continue;
        }
        let input;
        const name = (item.phase === 'order' ? 'order:' : 'value:')+item.key;
        const current = values[item.key] ?? item.default_value ?? '';
        if (item.type === 'select') {
            const choices = item.options || {};
            input = select(target,item.label,name,Array.isArray(choices) ? choices.map(choice => [choice,choice]) : Object.entries(choices),current);
        } else if (item.type === 'boolean') {
            input = select(target,item.label,name,[['1','Sim'],['0','Não']],current === true ? '1' : current === false ? '0' : current);
        } else {
            const type = {date:'date',time:'time',datetime:'datetime-local',textarea:'textarea'}[item.type] || 'text';
            input = field(target,item.label+(item.unit ? ' ('+item.unit+')' : ''),name,current,type);
            if (['meter','integer','decimal','money','quantity'].includes(item.type)) input.inputMode = item.type === 'integer' ? 'numeric' : 'decimal';
        }
        input.disabled = !editable;
        if (item.required && item.phase === 'order' && !item.conditional_rule) input.required = editable;
    }
    const startKey = catalog?.meter_pair?.[0], endKey = catalog?.meter_pair?.[1];
    if (startKey && writable && catalog.fields.some(item => item.key === endKey && item.editable_by_provider)) {
        const pauses = element('fieldset');
        pauses.append(element('legend','Pausas de medição'));
        function addPause(pause = {}) {
            const row = element('div'), inputs = element('div');
            inputs.className = 'pair';
            field(inputs,'Pausa','pause-stop',pause.stop).inputMode = 'decimal';
            field(inputs,'Retorno','pause-resume',pause.resume).inputMode = 'decimal';
            row.append(inputs, button('Remover pausa',() => {row.remove(); preview();}));
            pauses.insertBefore(row,add);
        }
        const add = button('Adicionar pausa',() => addPause());
        pauses.append(add);
        try {JSON.parse(values['meter_pauses_'+startKey] || '[]').forEach(addPause);} catch {}
        target.append(pauses);
    }
}

function openOrder(order, row = null) {
    active = {order:order || null,row};
    const reference = row?.payload.creation || {};
    const writable = !row?.sent && !row?.conflict && (!order || editableState(order));
    get('editor-title').textContent = order?.number || 'Nova ordem';
    get('order-status').textContent = row?.error || (row ? localStatus(row) : order?.status || '');
    form.replaceChildren();
    actions.replaceChildren();
    if (!order) {
        const version = select(form,'Serviço','service_version_id', bundle.catalog.map(item => [item.id,item.name]),reference.service_version_id);
        version.required = !bundle.catalog.length ? false : writable;
        version.disabled = !writable;
        if (!version.value && bundle.catalog.length === 1) version.value = bundle.catalog[0].id;
        const member = select(form,'Membro associado','associate_id', bundle.members.map(item => [item.id,item.name]),reference.associate_id);
        member.disabled = !writable;
        const beneficiary = field(form,'Beneficiário','beneficiary_name',reference.beneficiary_name);
        beneficiary.disabled = !writable;
        member.addEventListener('change',() => {
            if (!beneficiary.value) beneficiary.value = bundle.members.find(item => String(item.id) === member.value)?.name || '';
        });
        beneficiary.required = writable;
        const whatsapp = field(form,'WhatsApp','beneficiary_whatsapp',reference.beneficiary_whatsapp,'tel');
        whatsapp.maxLength = 25; whatsapp.disabled = !writable;
        field(form,'Agendamento','scheduled_at',reference.scheduled_at,'datetime-local').required = writable;
        field(form,'Local','location',reference.location).maxLength = 191;
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
    if (writable) {
        if (!order) actions.append(button('Salvar agendamento',() => save('schedule')));
        actions.append(button('Salvar rascunho',() => save('draft')));
        if (order?.execution !== 'in_progress') actions.append(button('Iniciar serviço',() => save('start')));
        actions.append(button('Finalizar para enviar',() => save('finish'),true));
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
        active = null; await redraw();
    }));
    actions.append(button('Compartilhar via em PDF', async () => {
        if (!hasOfflineAccess(bundle)) {await redraw();return;}
        const model = currentOrder();
        if (!model.beneficiary || !model.service) { message.textContent='Informe o serviço e o beneficiário.'; return; }
        const provisional = Boolean(row) || !model.totals;
        let totals;
        try { totals = estimate(model, values(model.catalog)); } catch {}
        if (!provisional && model.totals) totals = model.totals;
        const blob = beneficiaryPdf({organization:bundle.organization,logo:bundle.logo,provider:bundle.provider,order:model,
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
    get('editor').scrollIntoView({block:'start'});
    preview();
}

function currentOrder() {
    if (active.order) return active.order;
    const version = chosenVersion();
    return {service:version?.name,unit:version?.unit,catalog:version?.catalog,beneficiary:form.elements.namedItem('beneficiary_name')?.value,
        whatsapp:form.elements.namedItem('beneficiary_whatsapp')?.value,scheduled:form.elements.namedItem('scheduled_at')?.value,location:form.elements.namedItem('location')?.value};
}

function values(catalog) {
    const result = {...active.order?.values,...active.row?.payload.values};
    const payload = new FormData(form), pauses = payload.getAll('pause-stop');
    for (const [name,value] of payload) if (name.startsWith('value:') || name.startsWith('order:')) result[name.slice(6)] = value;
    const startKey = catalog?.meter_pair?.[0];
    if (startKey && form.querySelector('fieldset')) {
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
    if (!hasOfflineAccess(bundle)) {await redraw();return;}
    if (!form.reportValidity() || !active) return;
    const model = currentOrder(), row = active.row;
    const data = new FormData(form), currentValues = values(model.catalog), creation = {};
    if (!model.id) {
        for (const key of ['service_version_id','beneficiary_name','beneficiary_whatsapp','associate_id','scheduled_at','location']) creation[key] = data.get(key) || '';
        creation.order_data = Object.fromEntries([...data].filter(([name]) => name.startsWith('order:')).map(([name,value]) => [name.slice(6),value]));
    }
    const files = {...row?.files};
    for (const [key,value] of data) {
        if (!key.startsWith('file:') || !(value instanceof File) || !value.size) continue;
        if (value.size > 12*1024*1024) {message.textContent='Cada evidência deve ter até 12 MB.';return;}
        files[key.slice(5)] = value;
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
        payload:{order_id:model.id,lock_version:model.lockVersion,action,creation:model.id ? null : creation,
            values:currentValues,recorded_at:now,started_at:row?.payload.started_at || (action === 'start' ? now : null)}};
    try {
        if (!await guardedUpdate(next,row)) throw new Error('Este registro mudou em outra aba. Volte à lista e confira a versão mais recente.');
        active = null;
        message.textContent = 'Registro salvo neste aparelho.';
        await redraw();
        if (navigator.onLine) synchronize(true);
    } catch (error) {
        message.textContent = error.message;
    }
}

async function redraw() {
    bundle = await getBundle();
    pending = bundle ? await queued(bundle.scope) : [];
    get('connection').textContent = navigator.onLine ? 'Online' : 'Sem internet';
    get('refresh').hidden = !bundle;
    if (!bundle) {
        active = null;
        message.textContent = 'Abra o portal do prestador com internet e entre na sua conta para baixar os dados deste aparelho.';
    }
    renderList();
    const requestedOrder = bundle?.orders.find(order => String(order.id) === requestedOrderId);
    if (requestedOrder) {
        requestedOrderId = null;
        openOrder(requestedOrder,pending.find(row => row.payload.order_id === requestedOrder.id));
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

form.addEventListener('submit',event => event.preventDefault());
form.addEventListener('input',preview);
get('create').addEventListener('click',() => openOrder(null));
get('back').addEventListener('click',() => {active=null;renderList();});
window.sgcOverlayBack = () => {
    if (!active) return false;
    active = null;
    renderList();
    return true;
};
get('refresh').addEventListener('click',() => synchronize(true));
window.addEventListener('sgc-provider-data',redraw);
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

