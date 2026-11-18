@if($order->operational_status !== 'cancelled' && auth()->user()->checkPermissionTo('operate_all_service_orders_portal'))
    <button type="button" class="ds-button secondary" style="grid-column:1/-1;justify-self:start" data-open-dialog="beneficiary-dialog"><i class="ph-fill ph-pencil-simple" aria-hidden="true"></i> Corrigir nome</button>
    <dialog class="dossier-dialog" id="beneficiary-dialog" aria-labelledby="beneficiary-title">
        <form class="ds-dialog-layout" method="post" action="{{ route('services.management.beneficiary.update', [$tenantSlug, $order]) }}">
            @csrf @method('PATCH')
            <header class="ds-dialog-head"><strong id="beneficiary-title">Nome do beneficiário</strong><button class="ds-dialog-close" type="button" data-close-dialog="beneficiary-dialog" aria-label="Fechar">×</button></header>
            <div class="ds-dialog-body ds-dialog-form">
                <p>Corrija o nome ou apelido desta OS. O vínculo com o membro e os valores permanecem os mesmos.</p>
                <input type="hidden" name="previous_name" value="{{ $beneficiaryName }}">
                <label class="ds-field"><span class="ds-label">Nome ou apelido</span><input class="ds-control" name="beneficiary_name" value="{{ old('beneficiary_name', $beneficiaryName) }}" maxlength="191" required autocomplete="off"></label>
                <label class="ds-field"><span class="ds-label">Motivo da correção</span><input class="ds-control" name="reason" value="{{ old('reason') }}" minlength="5" maxlength="500" placeholder="Ex.: substituir apelido pelo nome completo" required></label>
                <p>Documentos já emitidos guardam o nome anterior. Após salvar, emita uma nova via com o nome corrigido.</p>
            </div>
            <footer class="ds-dialog-foot"><button class="ds-button secondary" type="button" data-close-dialog="beneficiary-dialog">Cancelar</button><button class="ds-button primary" type="submit">Salvar nome</button></footer>
        </form>
    </dialog>
@endif
