@extends('layouts.campo')

@section('title', 'Configurar PDF')
@section('page-title', 'Configurar PDF')

@php
    $bentoNavigation = \App\Support\PortalNavigation::make('secretary', 'system', $tenant->slug);
    $sections = $definition['sections'] ?? [];
    $columns = $definition['columns'] ?? [];
    $control = 'w-full rounded-lg border border-[var(--color-border)] bg-[var(--color-bg)] px-3 py-2 text-sm';
    $panel = 'grid gap-3 rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] p-4';
    $previewUrl = route('secretary.layouts.fragment', ['tenant' => $tenant->slug, 'layout' => 0]);
    $sampleUrl = in_array($template->system_template_key, \App\Services\SecretarySystemPdfSample::SUPPORTED, true)
        ? route('secretary.system.preview', ['tenant' => $tenant->slug, 'key' => $template->system_template_key])
        : null;
    $themes = collect(array_keys(\App\Models\DocumentTemplate::COLOR_THEMES))->mapWithKeys(fn ($key) => [
        $key => \App\Models\DocumentTemplate::getThemeColors($key, $tenant->primary_color, $tenant->accent_color),
    ]);
@endphp

@section('content')
<main class="mx-auto grid max-w-6xl gap-4">
    <header class="grid gap-2">
        <a class="text-sm font-semibold" href="{{ route('secretary.system.index', ['tenant' => $tenant->slug]) }}"><i class="ph ph-arrow-left" aria-hidden="true"></i> PDFs do sistema</a>
        <h1 class="text-xl font-bold">{{ $definition['label'] }}</h1>
        <p class="text-sm text-[var(--color-text-secondary)]">{{ $definition['description'] }}</p>
    </header>
    <div class="flex flex-wrap items-center gap-2 lg:hidden" aria-label="Área de trabalho">
        <button type="button" data-pdf-tab="options" aria-pressed="true" class="rounded-lg border border-[var(--color-border)] px-3 py-2 text-sm font-semibold">Opções</button>
        <button type="button" data-pdf-tab="preview" aria-pressed="false" class="rounded-lg border border-[var(--color-border)] px-3 py-2 text-sm font-semibold">Prévia visual</button>
    </div>
    <div class="system-pdf-workspace">
        <form id="system-pdf-form" class="grid min-w-0 gap-3" method="post" action="{{ $template->exists
            ? route('secretary.templates.update', ['tenant' => $tenant->slug, 'template' => $template])
            : route('secretary.system.store', ['tenant' => $tenant->slug, 'key' => $template->system_template_key]) }}">
            @csrf
            @if($template->exists) @method('PUT') @endif
            <section class="{{ $panel }}">
                <h2 class="font-bold">Identificação</h2>
                <label class="grid gap-1 text-sm">Nome da configuração<input class="{{ $control }}" name="name" maxlength="180" value="{{ $template->name }}" required></label>
                <label class="grid gap-1 text-sm">Descrição<textarea class="{{ $control }}" name="description" maxlength="500" rows="2">{{ $template->description }}</textarea></label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" @checked($template->is_active)> Usar esta configuração na emissão</label>
            </section>
            @foreach(['visible_sections' => ['Seções', $sections, $template->visible_sections ?? ($definition['default_sections'] ?? array_keys($sections))], 'visible_columns' => ['Colunas', $columns, $template->visible_columns ?? ($definition['default_columns'] ?? array_keys($columns))]] as $field => [$label, $choices, $selected])
                @continue(!$choices)
                <fieldset class="{{ $panel }}">
                    <legend class="px-1 font-bold">{{ $label }}</legend>
                    @foreach($choices as $key => $name)
                        <label class="flex min-h-8 items-center gap-2 text-sm"><input type="checkbox" name="{{ $field }}" value="{{ $key }}" @checked(in_array($key, $selected, true))> {{ $name }}</label>
                    @endforeach
                </fieldset>
            @endforeach
            <details class="{{ $panel }}">
                <summary class="cursor-pointer font-bold">Papel, cabeçalho e rodapé</summary>
                <div class="mt-3 grid gap-3">
                    @foreach([
                        'paper_size' => ['Papel', \App\Models\DocumentTemplate::PAPER_SIZES, $template->paper_size ?? 'a4'],
                        'paper_orientation' => ['Orientação', \App\Models\DocumentTemplate::PAPER_ORIENTATIONS, $template->paper_orientation ?? 'portrait'],
                        'table_scale' => ['Escala da tabela', [70 => '70%', 80 => '80%', 90 => '90%', 100 => '100%'], $template->table_scale ?? 100],
                        'color_theme' => ['Tema', \App\Models\DocumentTemplate::COLOR_THEMES, $template->color_theme ?? 'org'],
                    ] as $field => [$name, $choices, $selected])
                        <label class="grid gap-1 text-sm">{{ $name }}<select class="{{ $control }}" name="{{ $field }}">
                            @foreach($choices as $key => $label)<option value="{{ $key }}" @selected((string) $selected === (string) $key)>{{ $label }}</option>@endforeach
                        </select></label>
                    @endforeach
                    @foreach(['header' => 'Cabeçalho', 'footer' => 'Rodapé'] as $kind => $label)
                        @php($field = $kind.'_layout_id')
                        <label class="grid gap-1 text-sm">{{ $label }}<select class="{{ $control }}" name="{{ $field }}">
                            <option value="">Padrão do documento</option>
                            @foreach($layouts->whereIn('layout_type', [$kind, 'both']) as $layout)
                                <option value="{{ $layout->id }}" @selected((int) $template->{$field} === $layout->id)>{{ $layout->name }}</option>
                            @endforeach
                        </select></label>
                    @endforeach
                </div>
            </details>
            <details class="{{ $panel }}">
                <summary class="cursor-pointer font-bold">Contexto e textos opcionais</summary>
                <div class="mt-3 grid gap-3 text-sm">
                    <label class="grid gap-1">Tipo de projeto<input class="{{ $control }}" name="project_type" value="{{ $template->project_type }}" maxlength="80" placeholder="Todos"></label>
                    @foreach($reportScopes as $field => $choices)
                        <label class="grid gap-1">{{ ['sales_project_id' => 'Projeto específico', 'customer_id' => 'Cliente específico', 'organization_id' => 'Organização compradora'][$field] }}
                            <select class="{{ $control }}" name="{{ $field }}"><option value="">Todos</option>
                                @foreach($choices as $key => $label)<option value="{{ $key }}" @selected((int) $template->{$field} === $key)>{{ $label }}</option>@endforeach
                            </select>
                        </label>
                    @endforeach
                    @if($reportScopes)
                        <label class="grid gap-1">Ordem das seções<input class="{{ $control }}" name="section_order" value="{{ implode(', ', $template->section_order ?? []) }}" placeholder="{{ implode(', ', array_keys($sections)) }}"></label>
                        @foreach(\App\Services\Accounting\BillingDossierReportService::CONTENT_SLOTS as $slot => $label)
                            <label class="grid gap-1">{{ $label }}<textarea class="{{ $control }}" data-report-content="{{ $slot }}" rows="2">{{ $reportContents[$slot] ?? '' }}</textarea></label>
                        @endforeach
                    @endif
                    @foreach(['consent_enabled' => 'Exibir declaração', 'show_recipient_signature' => 'Assinatura do destinatário', 'show_representative_signature' => 'Assinatura do representante'] as $field => $label)
                        <label class="flex min-h-8 items-center gap-2"><input type="checkbox" name="{{ $field }}" @checked($template->{$field})> {{ $label }}</label>
                    @endforeach
                    <label class="grid gap-1">Posição da declaração<select class="{{ $control }}" name="consent_position">
                        @foreach(['before' => 'Antes da tabela', 'after' => 'Depois da tabela', 'both' => 'Antes e depois'] as $key => $label)
                            <option value="{{ $key }}" @selected(($template->consent_position ?? 'after') === $key)>{{ $label }}</option>
                        @endforeach
                    </select></label>
                    @foreach(['consent_content_before' => 'Declaração antes da tabela', 'consent_content' => 'Declaração depois da tabela'] as $field => $label)
                        <label class="grid gap-1">{{ $label }}<textarea class="{{ $control }}" name="{{ $field }}" rows="2">{{ $template->{$field} }}</textarea></label>
                    @endforeach
                </div>
            </details>
            <div class="flex flex-wrap items-center gap-3">
                <button class="rounded-lg bg-[#d6ef74] px-4 py-2 text-sm font-bold text-[#203519]" type="submit">Salvar configuração</button>
                <p id="system-pdf-status" class="text-sm" role="status"></p>
            </div>
        </form>
        <section id="system-pdf-preview" class="{{ $panel }} min-w-0">
            <div>
                <h2 class="font-bold">Prévia visual</h2>
                <p class="text-sm text-[var(--color-text-secondary)]">Esquema com dados ilustrativos. O formato da folha acompanha as opções selecionadas.</p>
            </div>
            @if($sampleUrl)
                <button type="button" id="system-pdf-sample" class="justify-self-start rounded-lg border border-[var(--color-border)] px-3 py-2 text-sm font-semibold"><i class="ph ph-file-pdf" aria-hidden="true"></i> Abrir amostra em PDF</button>
                <p id="system-pdf-sample-status" class="text-sm" role="status"></p>
            @endif
            <div class="system-pdf-stage" id="system-pdf-stage">
                <article class="system-pdf-sheet" id="system-pdf-sheet">
                    <header data-preview-header></header>
                    <h3>{{ $definition['label'] }}</h3>
                    <div data-preview-sections></div>
                    <footer data-preview-footer></footer>
                </article>
            </div>
        </section>
    </div>
</main>
<style>
    .system-pdf-workspace{display:grid;grid-template-columns:minmax(17rem,25rem) minmax(0,1fr);align-items:start;gap:1rem}
    #system-pdf-preview{position:sticky;top:calc(var(--header-height,64px) + .75rem)}
    .system-pdf-stage{padding:.65rem;background:var(--color-bg);overflow:auto}
    .system-pdf-sheet{box-sizing:border-box;display:flex;flex-direction:column;gap:4mm;margin:auto;padding:14mm;background:white;color:#20252b;font:10pt/1.35 Arial,sans-serif;border:1px solid #e0e4df;overflow-wrap:anywhere}
    .system-pdf-sheet h3{font-weight:700;font-size:14pt}.system-pdf-sheet h4{font-weight:700}
    .system-pdf-sheet table{width:100%;border-collapse:collapse;table-layout:fixed}.system-pdf-sheet th,.system-pdf-sheet td{text-align:left;border:1px solid #cbd3ce;padding:2mm}
    .system-pdf-sheet th{background:#edf1ed}.system-pdf-sheet footer{margin-top:auto;border-top:1px solid #cbd3ce;padding-top:3mm}
    .system-pdf-sample-section{margin-bottom:3mm;padding:2mm 3mm;border-left:2px solid var(--sample-accent,#64786f);background:#f4f6f4}
    .system-pdf-signatures{display:flex;gap:8mm;margin-top:6mm}.system-pdf-signatures span{flex:1;border-top:1px solid #8b948d;text-align:center}
    .system-pdf-brand{display:flex;align-items:center;gap:3mm}.system-pdf-brand img{width:auto;max-width:14mm;max-height:14mm}
    [data-pdf-tab][aria-pressed="true"]{background:#d6ef74;color:#203519}
    @media(max-width:1023px){.system-pdf-workspace{display:block}#system-pdf-preview{position:static}[data-preview-open="true"] #system-pdf-form,.system-pdf-workspace:not([data-preview-open="true"]) #system-pdf-preview{display:none}}
</style>
<script>
(() => {
    const form = document.getElementById('system-pdf-form');
    const stage = document.getElementById('system-pdf-stage');
    const sheet = document.getElementById('system-pdf-sheet');
    const status = document.getElementById('system-pdf-status');
    const branding = @js($branding);
    const logo = @js($logo);
    const themes = @js($themes);
    const layoutUrl = @js($previewUrl);
    const sampleUrl = @js($sampleUrl);
    const dimensions = {a4:[210,297], a3:[297,420], letter:[215.9,279.4], legal:[215.9,355.6]};
    const fragments = new Map();
    const value = name => form.elements[name]?.value ?? '';
    const checked = name => Array.from(form.querySelectorAll(`input[name="${name}"]:checked`));
    const node = (tag, text = '') => Object.assign(document.createElement(tag), {textContent:text});
    function fit() {
        const [width, height] = dimensions[value('paper_size')] || dimensions.a4;
        const landscape = value('paper_orientation') === 'landscape';
        const paperWidth = landscape ? height : width;
        sheet.style.width = `${paperWidth}mm`;
        sheet.style.minHeight = `${landscape ? width : height}mm`;
        const available = stage.clientWidth - 2 * parseFloat(getComputedStyle(stage).paddingLeft);
        if (available > 0) sheet.style.zoom = Math.min(1, available / (paperWidth * 96 / 25.4));
    }
    function showFragment(target, id) {
        const html = fragments.get(id);
        if (!html) return;
        const fragment = document.createElement('template');
        fragment.innerHTML = html;
        const tokens = {'cooperativa.nome':branding.name, 'cooperativa.cnpj':branding.documentNumber,
            'organization.name':branding.name, 'organization.document_number':branding.documentNumber,
            'documento.titulo':sheet.querySelector('h3').textContent};
        const walker = document.createTreeWalker(fragment.content, NodeFilter.SHOW_TEXT);
        const texts = [];
        while (walker.nextNode()) texts.push(walker.currentNode);
        for (const text of texts) {
            const parts = text.data.split(/(\{\{\s*[\w.]+\s*\}\})/g);
            text.replaceWith(...parts.map((part, index) => {
                if (index % 2 === 0) return part;
                const key = part.slice(2,-2).trim();
                if (key === 'cooperativa.logo_img' || key === 'organization.logo') {
                    return logo ? Object.assign(node('img'), {src:logo, alt:''}) : '';
                }
                return Object.hasOwn(tokens, key) ? tokens[key] ?? '' : part;
            }));
        }
        target.replaceChildren(fragment.content);
    }
    function render() {
        fit();
        const header = sheet.querySelector('[data-preview-header]');
        const brand = node('div');
        brand.className = 'system-pdf-brand';
        if (logo) brand.append(Object.assign(node('img'), {src:logo, alt:''}));
        const identity = node('div');
        identity.append(node('strong', branding.name));
        if (branding.registration) identity.append(node('p', branding.registration));
        brand.append(identity);
        header.replaceChildren(brand);
        showFragment(header, value('header_layout_id'));
        const footer = sheet.querySelector('[data-preview-footer]');
        footer.replaceChildren(node('span', branding.name));
        showFragment(footer, value('footer_layout_id'));
        const body = sheet.querySelector('[data-preview-sections]');
        body.replaceChildren();
        const selection = checked('visible_sections');
        const order = value('section_order').split(',').map(item => item.trim()).filter(Boolean);
        const rank = key => order.includes(key) ? order.indexOf(key) : order.length;
        selection.sort((left, right) => rank(left.value) - rank(right.value));
        const columns = checked('visible_columns').map(input => input.parentElement.textContent.trim());
        const table = node('table');
        table.style.fontSize = `${10 * Number(value('table_scale')) / 100}pt`;
        const declaration = (field, fallback) => {
            const source = document.createElement('template');
            source.innerHTML = value(field);
            return node('p', source.content.textContent.trim() || fallback);
        };
        const consent = form.elements.consent_enabled.checked;
        const placement = value('consent_position');
        if (consent && placement !== 'after') body.append(declaration('consent_content_before', 'Declaração antes da tabela.'));
        const heading = table.createTHead().insertRow();
        for (const label of columns) heading.append(node('th', label));
        const examples = table.createTBody();
        for (let i = 1; i <= 2; i++) {
            const row = examples.insertRow();
            columns.forEach((label, index) => { row.insertCell().textContent = index === 0 ? `Exemplo ${i}` : '—'; });
        }
        let tablePlaced = false;
        for (const option of selection) {
            const block = node('section');
            block.className = 'system-pdf-sample-section';
            block.append(node('h4', option.parentElement.textContent.trim()));
            if (!tablePlaced && columns.length && /table|deliveries|items|rows|products|entries/.test(option.value)) {
                block.append(table);
                tablePlaced = true;
            }
            if (/signature/.test(option.value)) {
                const signatures = node('div');
                signatures.className = 'system-pdf-signatures';
                if (form.elements.show_recipient_signature.checked) signatures.append(node('span', 'Destinatário'));
                if (form.elements.show_representative_signature.checked) signatures.append(node('span', 'Representante'));
                if (signatures.childNodes.length) block.append(signatures);
            }
            body.append(block);
        }
        if (!tablePlaced && columns.length && selection.length) body.append(table);
        if (consent && placement !== 'before') body.append(declaration('consent_content', 'Declaração depois da tabela.'));
        if (!selection.length && !columns.length) body.append(node('p', 'Nenhuma seção ou coluna selecionada.'));
        const colors = themes[value('color_theme')] ?? themes.org;
        sheet.style.setProperty('--sample-accent', colors.accent);
        sheet.querySelector('h3').style.color = colors.primary;
    }
    async function loadLayouts() {
        const ids = new Set([value('header_layout_id'), value('footer_layout_id')].filter(Boolean));
        await Promise.all(Array.from(ids, async id => {
            if (fragments.has(id)) return;
            try {
                const result = await fetch(layoutUrl.replace(/\/0\/fragment$/, `/${id}/fragment`), {headers:{Accept:'application/json'}});
                if (result.ok) fragments.set(id, (await result.json()).html);
            } catch {}
        }));
        render();
    }
    form.addEventListener('input', render);
    form.addEventListener('change', event => { if (['header_layout_id','footer_layout_id'].includes(event.target.name)) loadLayouts(); });
    for (const tab of document.querySelectorAll('[data-pdf-tab]')) tab.addEventListener('click', () => {
        stage.closest('.system-pdf-workspace').dataset.previewOpen = String(tab.dataset.pdfTab === 'preview');
        document.querySelectorAll('[data-pdf-tab]').forEach(button => button.setAttribute('aria-pressed', String(button === tab)));
        fit();
    });
    function settings() {
        const payload = Object.fromEntries(new FormData(form));
        delete payload._token; delete payload._method;
        for (const name of ['visible_sections','visible_columns']) payload[name] = checked(name).map(input => input.value);
        for (const name of ['is_active','consent_enabled','show_recipient_signature','show_representative_signature']) payload[name] = form.elements[name].checked;
        if (form.elements.section_order) {
            payload.section_order = value('section_order').split(',').map(item => item.trim()).filter(Boolean);
            payload.report_contents = Object.fromEntries(Array.from(form.querySelectorAll('[data-report-content]'), field => [field.dataset.reportContent, field.value]));
        }
        return payload;
    }
    const headers = {'Content-Type':'application/json', Accept:'application/json', 'X-CSRF-TOKEN':value('_token')};
    const sample = document.getElementById('system-pdf-sample');
    sample?.addEventListener('click', async () => {
        if (!form.reportValidity()) return;
        const popup = window.open('about:blank', '_blank');
        const feedback = document.getElementById('system-pdf-sample-status');
        if (popup) popup.opener = null;
        sample.disabled = true;
        feedback.textContent = 'Gerando PDF…';
        try {
            const response = await fetch(sampleUrl, {method:'POST', headers, body:JSON.stringify(settings())});
            if (!response.ok || !response.headers.get('Content-Type')?.includes('application/pdf')) {
                const error = await response.json().catch(() => ({}));
                throw new Error(Object.values(error.errors ?? {}).flat()[0] || error.message || 'Erro ao gerar a amostra.');
            }
            const url = URL.createObjectURL(await response.blob());
            if (popup) {
                popup.location.replace(url);
                feedback.textContent = '';
                setTimeout(() => URL.revokeObjectURL(url), 120000);
            } else {
                const link = node('a', 'Abrir amostra gerada');
                link.href = url;
                link.target = '_blank';
                link.rel = 'noopener';
                feedback.replaceChildren(link);
            }
        } catch (error) { popup?.close(); feedback.textContent = error.message; }
        finally { sample.disabled = false; }
    });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        const submit = form.querySelector('button[type=submit]');
        submit.disabled = true;
        status.textContent = 'Salvando…';
        try {
            const response = await fetch(form.action, {method:value('_method') || 'POST', headers, body:JSON.stringify(settings())});
            const result = await response.json();
            if (!response.ok) throw new Error(Object.values(result.errors ?? {}).flat()[0] || result.message || 'Erro ao salvar.');
            status.textContent = result.message;
            if (result.redirect_url) location.assign(result.redirect_url);
        } catch (error) { status.textContent = error.message; }
        finally { submit.disabled = false; }
    });
    if ('ResizeObserver' in window) new ResizeObserver(fit).observe(stage, {box:'border-box'});
    render();
    loadLayouts();
})();
</script>
@endsection
