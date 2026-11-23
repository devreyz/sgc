@php
    $manager = $resourceManagement ?? false;
    $resourceOptions = ['information_only' => 'Apenas registrar'];
    if ($manager) {
        if (auth()->user()->checkPermissionTo('manage_service_receivables')) {
            $resourceOptions += ['deduct_from_receivable' => 'Descontar da cobrança', 'add_to_receivable' => 'Acrescentar à cobrança'];
        }
        if (auth()->user()->checkPermissionTo('manage_service_payables')) {
            $resourceOptions['reimburse_provider'] = 'Reembolsar o prestador';
        }
        $resourceOptions['create_association_expense'] = 'Criar despesa da associação';
    }
    $owners = ['organization' => 'Associação', 'provider' => 'Prestador', 'customer' => 'Beneficiário', 'third_party' => 'Terceiro'];
    $resourceAction = route($manager ? 'services.management.resources.store' : 'provider.orders.resources.store', [$tenantSlug, $order]);
    $evidenceRoute = $manager ? 'services.management.evidences.download' : 'provider.evidences.download';
    $listedResources = $manager ? $execution->resources : $execution->resources->where('created_by', auth()->id())->where('effect', 'information_only');
    $resourcesOpen = $execution->status !== 'cancelled' && $order->operational_status !== 'cancelled';
@endphp
<section class="sgc-resource-panel" id="recursos-da-ordem" aria-labelledby="sgc-resource-title">
    <header>
        <h2 id="sgc-resource-title"><i class="ph ph-package" aria-hidden="true"></i> {{ $manager ? 'Recursos e comprovantes' : 'Recursos da ordem' }}</h2>
        @if($resourcesOpen && !$order->isBilled())
            <button type="button" class="sgc-resource-action" data-resource-open="resource-wizard-new"><i class="ph ph-plus" aria-hidden="true"></i> Adicionar recurso</button>
        @endif
    </header>
    @forelse($listedResources as $item)
        <article>
            <div>
                <strong>{{ $item->description }}</strong>
                <p>{{ $owners[$item->provided_by] ?? 'Origem não informada' }} · R$ {{ number_format((float) $item->amount, 2, ',', '.') }}</p>
                @foreach($execution->evidences->where('field_key', $item->evidenceKey()) as $photo)
                    <a href="{{ route($evidenceRoute, [$tenantSlug, $photo, 'inline' => 1]) }}" target="_blank" rel="noopener">Foto {{ $loop->iteration }}</a>
                @endforeach
            </div>
            @if($resourcesOpen)
                <button type="button" data-resource-open="resource-wizard-{{ $item->id }}">{{ $order->isBilled() || $item->effect !== 'information_only' ? 'Anexar fotos' : 'Editar recurso' }}</button>
            @endif
        </article>
    @empty
        <p>Nenhum recurso registrado.</p>
    @endforelse
</section>
@if($resourcesOpen)
    @foreach(($order->isBilled() ? $listedResources : collect([null])->concat($listedResources)) as $item)
        @php
            $dialogId = 'resource-wizard-'.($item?->id ?? 'new');
            $canEditData = !$order->isBilled() && (!$item || $item->effect === 'information_only');
            $failed = old('resource_wizard') === $dialogId && $errors->any();
            $value = fn ($key, $default = '') => $failed ? old($key, $item?->{$key} ?? $default) : ($item?->{$key} ?? $default);
            $photoCount = $item ? $execution->evidences->where('field_key', $item->evidenceKey())->count() : 0;
        @endphp
        <dialog class="sgc-resource-wizard" id="{{ $dialogId }}" aria-labelledby="{{ $dialogId }}-title" @if($failed) data-resource-reopen @endif>
            <form method="post" action="{{ $resourceAction }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="resource_wizard" value="{{ $dialogId }}">
                <input type="hidden" name="operation_key" value="{{ $failed ? old('operation_key', (string) \Illuminate\Support\Str::uuid()) : \Illuminate\Support\Str::uuid() }}">
                @if($item)<input type="hidden" name="resource_id" value="{{ $item->id }}">@endif
                <header>
                    <div><p aria-live="polite" data-resource-progress></p><h2 id="{{ $dialogId }}-title">{{ !$item ? 'Adicionar recurso' : ($canEditData ? 'Editar recurso' : $item->description) }}</h2></div>
                    <button type="button" data-resource-close aria-label="Fechar"><i class="ph ph-x" aria-hidden="true"></i></button>
                </header>
                @if($failed)<ul role="alert">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
                @if($canEditData)
                    <fieldset data-resource-step="Identificação">
                        <legend>O que foi usado?</legend>
                        <label>Descrição <input name="description" required maxlength="191" placeholder="Ex.: combustível para o trator" value="{{ $value('description') }}"></label>
                        @if($manager)
                            <label>Fornecido por <select name="provided_by">@foreach($owners as $key => $label)<option value="{{ $key }}" @selected($value('provided_by', 'organization') === $key)>{{ $label }}</option>@endforeach</select></label>
                        @else
                            <p>O recurso fica registrado nesta OS para conferência da associação.</p>
                        @endif
                    </fieldset>
                    <fieldset data-resource-step="Valor">
                        <legend>Quanto custou?</legend>
                        <label>Valor total (R$) <input name="amount" type="number" min="0" max="999999999" step="0.01" inputmode="decimal" value="{{ $value('amount') }}"></label>
                        <details>
                            <summary>Informar quantidade e preço por unidade</summary>
                            <label>Quantidade <input name="quantity" type="number" min="0" max="999999999" step="0.0001" inputmode="decimal" value="{{ $value('quantity') }}"></label>
                            <label>Unidade <input name="unit" maxlength="30" placeholder="Ex.: litro" value="{{ $value('unit') }}"></label>
                            <label>Preço por unidade (R$) <input name="unit_price" type="number" min="0" max="999999999" step="0.0001" inputmode="decimal" value="{{ $value('unit_price') }}"></label>
                            <p>Com o total em branco, o sistema multiplica a quantidade pelo preço por unidade.</p>
                        </details>
                        @if($manager && !$item)
                            <label>Como lançar <select name="effect">@foreach($resourceOptions as $key => $label)<option value="{{ $key }}" @selected($value('effect') === $key)>{{ $label }}</option>@endforeach</select></label>
                        @endif
                    </fieldset>
                @endif
                <fieldset data-resource-step="Fotos">
                    <legend>Comprovantes</legend>
                    <p>{{ $photoCount ? $photoCount.' de 5 fotos anexadas.' : 'Anexe uma foto do comprovante ou volte depois para incluí-la.' }}</p>
                    @for($slot = $photoCount; $slot < 5; $slot++)
                        <details @if($slot === $photoCount) open @endif>
                            <summary>Foto {{ $slot + 1 }}</summary>
                            <label class="sgc-resource-file"><span><i class="ph ph-camera" aria-hidden="true"></i> Tirar foto</span><input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" capture="environment" data-resource-photo></label>
                            <label class="sgc-resource-file"><span><i class="ph ph-image" aria-hidden="true"></i> Escolher imagem</span><input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" data-resource-photo></label>
                            <p data-resource-file-name aria-live="polite"></p>
                        </details>
                    @endfor
                </fieldset>
                <footer>
                    <button type="button" data-resource-back>Voltar</button>
                    <button type="button" class="sgc-resource-action" data-resource-next>Continuar <i class="ph ph-arrow-right" aria-hidden="true"></i></button>
                    <button type="submit" class="sgc-resource-action" data-resource-save>Salvar recurso</button>
                </footer>
            </form>
        </dialog>
    @endforeach
@endif
@once
<style>
    .sgc-resource-panel, .sgc-resource-wizard { --rw-bg:var(--campo-panel,var(--ds-surface,#fafbf6)); --rw-ink:var(--campo-ink,var(--ds-text,#203729)); --rw-line:var(--campo-line,#cbd6c7); --rw-accent:#d9ee90; color:var(--rw-ink); }
    .sgc-resource-panel { margin:1rem 0; border:1px solid var(--rw-line); border-radius:1rem; padding:1rem; background:var(--rw-bg); }
    .sgc-resource-panel header, .sgc-resource-panel article, .sgc-resource-wizard header, .sgc-resource-wizard footer { display:flex; align-items:center; justify-content:space-between; gap:.65rem; flex-wrap:wrap; }
    .sgc-resource-panel h2, .sgc-resource-wizard h2 { font-size:1.1rem; font-weight:750; }
    .sgc-resource-panel article { margin-top:.75rem; padding-top:.75rem; border-top:1px solid var(--rw-line); }
    .sgc-resource-panel a { text-decoration:underline; margin-inline-end:.5rem; }
    .sgc-resource-panel button, .sgc-resource-wizard button { padding:.55rem .8rem; min-height:44px; border:1px solid var(--rw-line); border-radius:.65rem; font-weight:650; }
    .sgc-resource-action { background:var(--rw-accent); color:#203729; }
    .sgc-resource-wizard { width:min(calc(100% - 1rem),34rem); max-height:90dvh; margin:auto; border:1px solid var(--rw-line); border-radius:1rem; background:var(--rw-bg); padding:1.1rem; }
    .sgc-resource-wizard::backdrop { background:#0e231bbb; }
    .sgc-resource-wizard form, .sgc-resource-wizard fieldset, .sgc-resource-wizard label { display:grid; gap:.65rem; min-width:0; }
    .sgc-resource-wizard fieldset[hidden], .sgc-resource-wizard button[hidden] { display:none; }
    .sgc-resource-wizard legend { font-weight:650; margin-bottom:.45rem; }
    .sgc-resource-wizard input:not([type=hidden]), .sgc-resource-wizard select { width:100%; min-height:44px; padding:.5rem; border:1px solid var(--rw-line); border-radius:.55rem; background:transparent; color:inherit; font-size:1rem; }
    .sgc-resource-wizard details { padding:.45rem .6rem; border-radius:.55rem; border:1px solid var(--rw-line); }
    .sgc-resource-wizard details label { margin-top:.55rem; }
    .sgc-resource-wizard .sgc-resource-file { position:relative; display:inline-flex; align-items:center; padding:.55rem .7rem; min-height:44px; border:1px solid var(--rw-line); border-radius:.55rem; cursor:pointer; }
    .sgc-resource-wizard .sgc-resource-file input { position:absolute; inset:0; opacity:0; cursor:pointer; }
    .sgc-resource-wizard .sgc-resource-file:focus-within { outline:2px solid currentColor; outline-offset:2px; }
    .sgc-resource-wizard summary { cursor:pointer; }
    .sgc-resource-wizard footer { border-top:1px solid var(--rw-line); padding-top:.65rem; }
    .sgc-resource-wizard footer [data-resource-next], .sgc-resource-wizard footer [data-resource-save] { margin-inline-start:auto; }
    .sgc-resource-wizard :focus-visible, .sgc-resource-panel :focus-visible { outline:2px solid currentColor; outline-offset:2px; }
    :is(.dark,[data-theme=dark]) :is(.sgc-resource-panel,.sgc-resource-wizard) { --rw-bg:#1a291f; --rw-ink:#ebf3e6; --rw-line:#405347; }
</style>
<script>
(() => {
    document.querySelectorAll('.sgc-resource-wizard').forEach(dialog => {
        const form = dialog.querySelector('form');
        const steps = [...dialog.querySelectorAll('[data-resource-step]')];
        const back = dialog.querySelector('[data-resource-back]');
        const next = dialog.querySelector('[data-resource-next]');
        const save = dialog.querySelector('[data-resource-save]');
        let index = 0;
        const show = () => {
            steps.forEach((step, number) => { step.hidden = number !== index; });
            back.hidden = index === 0;
            next.hidden = index === steps.length - 1;
            save.hidden = !next.hidden;
            dialog.querySelector('[data-resource-progress]').textContent = (index + 1) + ' de ' + steps.length + ' · ' + steps[index].dataset.resourceStep;
        };
        const advance = () => {
            if (![...steps[index].querySelectorAll('input,select')].every(field => field.reportValidity())) return;
            index++; show();
            steps[index].querySelector('input,select,summary')?.focus();
        };
        form.addEventListener('submit', event => {
            if (index < steps.length - 1) { event.preventDefault(); advance(); }
        });
        form.addEventListener('invalid', event => {
            const step = event.target.closest('[data-resource-step]');
            if (step && step.hidden) { index = steps.indexOf(step); show(); }
            const details = event.target.closest('details');
            if (details) details.open = true;
        }, true);
        next.addEventListener('click', advance);
        back.addEventListener('click', () => { index--; show(); });
        dialog.querySelector('[data-resource-close]').addEventListener('click', () => dialog.close());
        dialog.querySelectorAll('[data-resource-photo]').forEach(input => input.addEventListener('change', () => {
            if (!input.files.length) return;
            const slot = input.closest('details');
            slot.querySelectorAll('[data-resource-photo]').forEach(other => { if (other !== input) other.value = ''; });
            slot.querySelector('[data-resource-file-name]').textContent = input.files[0].name;
        }));
        show();
        if (dialog.hasAttribute('data-resource-reopen')) dialog.showModal();
    });
    document.querySelectorAll('[data-resource-open]').forEach(button => button.addEventListener('click', () => {
        document.getElementById(button.dataset.resourceOpen)?.showModal();
    }));
})();
</script>
@endonce
