@props([
    'items' => [],
    'active' => null,
    'portal' => 'custom',
    'ariaLabel' => 'Navegacao principal',
])

@php
    $icons = [
        'dashboard' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="8" rx="1.5"></rect><rect x="14" y="3" width="7" height="5" rx="1.5"></rect><rect x="14" y="12" width="7" height="9" rx="1.5"></rect><rect x="3" y="15" width="7" height="6" rx="1.5"></rect></svg>',
        'projects' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7.5A2.5 2.5 0 0 1 5.5 5H10l2 2h6.5A2.5 2.5 0 0 1 21 9.5v7A2.5 2.5 0 0 1 18.5 19h-13A2.5 2.5 0 0 1 3 16.5Z"></path></svg>',
        'deliveries' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"></path><path d="m3.3 7 8.7 5 8.7-5"></path><path d="M12 22V12"></path></svg>',
        'ledger' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3" width="16" height="18" rx="2"></rect><path d="M8 8h8M8 12h8M8 16h5"></path></svg>',
        'register' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round"><path d="M12 5v14M5 12h14"></path></svg>',
        'printables' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V2h12v7"></path><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect width="12" height="8" x="6" y="14"></rect></svg>',
        'orders' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3" width="16" height="18" rx="2"></rect><path d="M9 5h6M9 12h6M9 19h6"></path></svg>',
        'financial' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="M3 10h18M7 15h3"></path></svg>',
        'create' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round"><path d="M12 5v14M5 12h14"></path></svg>',
        'history' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v5h5"></path><path d="M3.05 13A9 9 0 1 0 6 5.3L3 8"></path><path d="M12 7v5l4 2"></path></svg>',
        'default' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="7" height="7" rx="1.5"></rect><rect x="13" y="4" width="7" height="7" rx="1.5"></rect><rect x="4" y="13" width="7" height="7" rx="1.5"></rect><rect x="13" y="13" width="7" height="7" rx="1.5"></rect></svg>',
    ];
@endphp

@if(! empty($items))
    <nav class="nav-tabs" data-portal-nav="{{ $portal }}" aria-label="{{ $ariaLabel }}">
        @foreach($items as $item)
            @php
                $key = $item['key'] ?? 'default';
                $type = $item['type'] ?? 'link';
                $isActive = ($item['active'] ?? null) ?? ($active === $key);
                $content = '<span class="app-nav-icon" aria-hidden="true">'.($icons[$item['icon'] ?? $key] ?? $icons['default']).'</span>'
                    .'<span class="app-nav-label">'.e($item['label'] ?? '').'</span>';
            @endphp

            @if($type === 'form')
                <form action="{{ $item['url'] ?? '#' }}" method="POST">
                    @csrf
                    @if(! in_array(strtoupper($item['method'] ?? 'POST'), ['GET', 'POST'], true))
                        @method($item['method'])
                    @endif
                    <button type="submit" class="nav-tab {{ $isActive ? 'active' : '' }}" data-nav-key="{{ $key }}">
                        {!! $content !!}
                    </button>
                </form>
            @elseif($type === 'button')
                <button type="button" class="nav-tab {{ $isActive ? 'active' : '' }}" data-nav-key="{{ $key }}" data-nav-event="{{ $item['action'] ?? $key }}">
                    {!! $content !!}
                </button>
            @else
                <a href="{{ $item['url'] ?? '#' }}" class="nav-tab {{ $isActive ? 'active' : '' }}" data-nav-key="{{ $key }}" @if($isActive) aria-current="page" @endif>
                    {!! $content !!}
                </a>
            @endif
        @endforeach
    </nav>
@endif
