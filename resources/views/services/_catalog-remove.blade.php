@if(! $service->service_orders_exists && $service->versions->every(fn ($version) => $version->status === 'draft'))
    <button type="button" class="ct-open draft" onclick="this.nextElementSibling.showModal()">Excluir cadastro</button>
    <dialog class="sg-dialog">
        <form method="post" action="{{ route('services.catalog.remove', [$tenantSlug, $service->id]) }}">
            @csrf
            @method('DELETE')
            <h2>Excluir {{ $service->name }}</h2>
            <label>Motivo <textarea name="reason" minlength="5" maxlength="500" required rows="3"></textarea></label>
            <div class="sg-actions">
                <button class="sg-button" type="button" onclick="this.closest('dialog').close()">Voltar</button>
                <button class="sg-button sg-primary" type="submit">Excluir cadastro</button>
            </div>
        </form>
    </dialog>
    @once
        @include('services._workspace-style')
    @endonce
@endif
