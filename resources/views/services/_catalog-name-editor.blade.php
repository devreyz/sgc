<section class="cc-panel">
    <header class="cc-panel-head"><strong>Nome do serviço</strong></header>
    <div class="cc-panel-body">
        <p>O nome é único para este serviço. A alteração aparece em todas as versões, nas ordens já registradas e nas próximas emissões. Os valores permanecem os mesmos.</p>
        <form class="cc-form" method="post" action="{{ route('services.catalog.rename', [$tenantSlug, $version->service_id]) }}">
            @csrf @method('PATCH')
            <input type="hidden" name="previous_name" value="{{ $version->service->name }}">
            <label class="cc-field"><span class="cc-label">Nome mostrado nas ordens e relatórios</span><input class="cc-control" name="name" maxlength="191" required value="{{ old('name', $version->service->name) }}"></label>
            <div class="cc-form-actions"><button class="cc-action primary" type="submit"><i class="ph-fill ph-floppy-disk" aria-hidden="true"></i> Salvar nome em todas as versões</button></div>
        </form>
    </div>
</section>
