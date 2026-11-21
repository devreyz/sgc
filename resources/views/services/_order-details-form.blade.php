@php
    $detailsRouteTenant = $tenant ?? $currentTenant ?? request()->route('tenant');
    $detailsTenantSlug = is_object($detailsRouteTenant) ? $detailsRouteTenant->slug : $detailsRouteTenant;
    $detailsRetry = (int) old('details_order') === (int) $order->id && $errors->any();
    $detailValue = fn ($key, $default = null) => $detailsRetry ? old($key, $default) : $default;
@endphp
@once
<style>
.order-details-form{max-height:86dvh;overflow:auto;overscroll-behavior:contain;display:grid;gap:1rem;min-width:0;padding:1.1rem;color:inherit}.order-details-form h3{font-size:1.1rem;font-weight: 700}.order-details-form p{font-size:.85rem;line-height:1.5}.order-details-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.85rem}.order-details-fields label{display:grid;gap:.35rem;font-size:.85rem;min-width:0}.order-details-fields .wide{grid-column:1/-1}.order-details-fields :is(input,textarea){width:100%;min-width:0;min-height:44px;border:1px solid var(--cm-line,var(--ui-color-border,#d4dfd8));border-radius:9px;padding:.6rem .7rem;background:var(--cm-panel,var(--ui-color-surface,var(--ui-color-surface)));color:inherit;font:inherit}.order-details-form .sg-actions{display:flex;gap:.6rem;flex-wrap:wrap}.order-details-form button{min-height:44px}.order-details-form button:disabled{opacity:.6;cursor:wait}@media(max-width:560px){.order-details-fields{grid-template-columns:1fr}}
</style>
@endonce
<form class="order-details-form" method="post" action="{{ route('services.management.update', [$detailsTenantSlug, $order]) }}">
    @csrf @method('PUT')
    <input type="hidden" name="details_only" value="1">
    <input type="hidden" name="details_order" value="{{ $order->id }}">
    <input type="hidden" name="details_token" value="{{ \App\Services\Services\ServiceOrderDetails::token($order) }}">
    <h3 id="details-title-{{ $order->id }}">Editar detalhes · {{ $order->number }}</h3>
    <p>Corrija os dados desta ordem. As quantidades, tarifas, a situação e os pagamentos permanecem registrados.</p>
    @if($detailsRetry)<ul role="alert">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>@endif
    <div class="order-details-fields">
        <label class="wide">Nome ou apelido do beneficiário<input name="beneficiary_name" value="{{ $detailValue('beneficiary_name', data_get($order->beneficiary_snapshot, 'name')) }}" required maxlength="191"></label>
        <label>Dia da execução, se conhecido<input type="date" name="execution_date" max="{{ today()->toDateString() }}" value="{{ $detailValue('execution_date', $order->execution_date?->toDateString()) }}"></label>
        <label>Agendamento, se houver<input type="datetime-local" name="scheduled_at" value="{{ $detailValue('scheduled_at', $order->scheduled_at?->format('Y-m-d\TH:i')) }}"></label>
        <label>Hora inicial<input type="time" name="start_time" value="{{ $detailValue('start_time', substr((string) $order->start_time, 0, 5)) }}"></label>
        <label>Hora final<input type="time" name="end_time" value="{{ $detailValue('end_time', substr((string) $order->end_time, 0, 5)) }}"></label>
        <label class="wide">Local do serviço<input name="location" value="{{ $detailValue('location', $order->location) }}" maxlength="191"></label>
        <label class="wide">Observações e detalhes<textarea name="notes" rows="3" maxlength="4000">{{ $detailValue('notes', data_get($order->order_data, 'notes')) }}</textarea></label>
        <label class="wide">Motivo da correção<input name="reason" value="{{ $detailValue('reason') }}" placeholder="Ex.: corrigir a data anotada no lançamento" minlength="5" maxlength="500" required></label>
    </div>
    <p>As próximas emissões e consultas usarão os dados corrigidos. Documentos já emitidos preservam o registro anterior.</p>
    <div class="sg-actions">
        @if(in_array($order->execution?->status, ['draft', 'in_progress', 'rejected'], true))
            <a class="sg-button ds-button secondary" href="{{ route('services.management.edit', [$detailsTenantSlug, $order]) }}">
                {{ \App\Services\Services\ServiceOrderDetails::canChangeVersion($order) ? 'Alterar versão do serviço' : 'Serviço, prestador e tarifa' }}
            </a>
        @endif
        <button class="sg-button sg-primary ds-button primary" type="submit">Salvar correções</button>
        @if($detailsClose ?? false)<button class="sg-button ds-button secondary" type="button" @if($detailsDossier ?? false) data-close-dialog="order-details" @else data-dialog-close @endif>Cancelar</button>@else<a class="sg-button" href="{{ route('services.management.show', [$detailsTenantSlug, $order]) }}">Voltar à OS</a>@endif
    </div>
</form>
