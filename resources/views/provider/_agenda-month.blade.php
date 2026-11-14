@php
    $holidays = \App\Support\BrazilianHolidays::forYear($start->year);
@endphp
<section class="svc-agenda-month" data-month="{{ $start->format('Y-m') }}"
    data-previous="{{ $agendaWindow['previous'] }}" data-next="{{ $agendaWindow['next'] }}"
    aria-labelledby="agenda-month-{{ $start->format('Y-m') }}">
    <h3 id="agenda-month-{{ $start->format('Y-m') }}">{{ $start->locale('pt_BR')->translatedFormat('F \d\e Y') }}</h3>
    <div class="svc-month-grid">
        @foreach(['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'] as $weekday)
            <span class="svc-weekday" aria-hidden="true">{{ $weekday }}</span>
        @endforeach
        @for($blank = 0; $blank < $start->dayOfWeek; $blank++)
            <span class="svc-day-blank" aria-hidden="true"></span>
        @endfor
        @for($day = $start->copy(); $day->lt($end); $day->addDay())
            @php
                $date = $day->toDateString();
                $items = $agendaDays->get($date, collect());
                $planned = $items->whereIn('operational_status', ['draft', 'scheduled'])->count();
                $processed = $items->whereIn('operational_status', ['submitted', 'validated'])->count();
                $holiday = $holidays[$day->format('m-d')] ?? null;
            @endphp
            <button type="button" class="svc-calendar-cell" data-day="{{ $date }}" data-label="{{ $day->locale('pt_BR')->translatedFormat('l, d/m/Y') }}"
                @if($day->isSunday() || $holiday) data-red-day @endif
                @if($holiday) data-holiday title="{{ $holiday }}" @endif
                aria-controls="agenda-day-{{ $date }}" aria-pressed="false"
                @if($day->isSameDay($today)) aria-current="date" @endif
                aria-label="{{ $day->locale('pt_BR')->translatedFormat('l, d/m/Y') }}{{ $holiday ? ', feriado: '.$holiday : '' }}: {{ $items->count() }} ordens, {{ $planned }} previstas e {{ $processed }} executadas">
                <span>{{ $day->format('d') }}</span>
                @if($items->isNotEmpty())
                    <span class="svc-day-count" aria-hidden="true"><span class="svc-day-marker" @if($planned) data-planned @endif @if($processed) data-completed @endif></span>{{ $items->count() }}</span>
                @endif
            </button>
        @endfor
    </div>
    <div class="svc-month-details">
        @for($day = $start->copy(); $day->lt($end); $day->addDay())
            @php
                $date = $day->toDateString();
                $items = $agendaDays->get($date, collect());
                $planned = $items->whereIn('operational_status', ['draft', 'scheduled'])->count();
                $processed = $items->whereIn('operational_status', ['submitted', 'validated'])->count();
                $isToday = $day->isSameDay($today);
                $holiday = $holidays[$day->format('m-d')] ?? null;
            @endphp
            <details class="svc-month-day {{ $items->isEmpty() && !$holiday && !$isToday && !$day->isSameDay($selectedDay) ? 'is-empty' : '' }}" id="agenda-day-{{ $date }}" @if($isToday) data-agenda-today @endif @if($day->isSameDay($selectedDay)) open @endif>
                <summary @if($isToday) aria-current="date" @endif>
                    <time datetime="{{ $date }}">
                        <span class="svc-day-number" @if($day->isSunday() || $holiday) data-red-day @endif>{{ $day->format('d') }}</span>
                        <span class="svc-day-description"> · {{ $isToday ? 'Hoje' : $day->locale('pt_BR')->translatedFormat('l') }}</span>
                    </time>
                    @if($items->isNotEmpty())
                        <span class="svc-day-count"><span class="svc-day-marker" @if($planned) data-planned @endif @if($processed) data-completed @endif aria-hidden="true"></span>{{ $items->count() }} <span class="svc-day-description">{{ $items->count() === 1 ? 'ordem' : 'ordens' }}</span></span>
                        <span class="svc-day-accessible">{{ $planned }} agendadas ou em rascunho, {{ $processed }} executadas</span>
                    @endif
                </summary>
                <div class="svc-day-orders">
                    @if($holiday)
                        <p class="svc-holiday-name">Feriado · {{ $holiday }}</p>
                    @endif
                    @if($items->isNotEmpty())
                        @include('provider._dashboard-order-list', ['items' => $items, 'agendaMode' => true])
                    @else
                        <p class="svc-agenda-empty">Nenhuma ordem neste dia.</p>
                    @endif
                    <a class="svc-day-create" href="{{ route('provider.orders.create', [$tenantSlug, 'agenda_date' => $date]) }}">Agendar serviço</a>
                </div>
            </details>
        @endfor
    </div>
</section>
