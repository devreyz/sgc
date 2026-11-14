<ul class="svc-agenda-orders">
    @foreach($items as $order)
        @php
            $date = $agendaMode
                ? ($order->scheduled_at ?? $order->scheduled_date)
                : ($order->execution_date ?? $order->scheduled_at ?? $order->scheduled_date ?? $order->created_at);
        @endphp
        <li>
            <a class="svc-agenda-order" href="{{ route('provider.orders.show', [$tenantSlug, $order]) }}">
                <time datetime="{{ $date?->toIso8601String() }}">
                    {{ $agendaMode ? ($order->scheduled_at?->format('H:i') ?? 'Sem horário') : ($date?->format('d/m/Y') ?? '—') }}
                </time>
                <span class="svc-agenda-order-copy">
                    <strong>{{ data_get($order->beneficiary_snapshot, 'name') ?: 'Beneficiário não informado' }}</strong>
                    <small>{{ collect([$order->service?->name, $order->number, $order->location])->filter()->join(' · ') }}</small>
                </span>
                <span class="svc-agenda-status" data-status="{{ $order->operational_status }}">{{ $statuses->resolve($order) }}</span>
                <i class="ph ph-caret-right" aria-hidden="true"></i>
            </a>
        </li>
    @endforeach
</ul>
