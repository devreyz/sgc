@props([
    'items' => [],
    'active' => null,
    'portal' => 'custom',
    'ariaLabel' => 'Navegação principal',
])

@php
    /*
    |--------------------------------------------------------------------------
    | Ícones Phosphor Duotone
    |--------------------------------------------------------------------------
    |
    | Mantemos apenas o nome do ícone.
    | O componente adiciona automaticamente:
    |
    | ph-duotone ph-{icon}
    |
    */

    $icons = [
        'dashboard'   => 'squares-four',
        'projects'    => 'folder-open',
        'deliveries'  => 'package',
        'ledger'      => 'notebook',
        'register'    => 'plus',
        'printables'  => 'printer',
        'orders'      => 'clipboard-text',
        'financial'   => 'wallet',
        'create'      => 'plus',
        'history'     => 'clock-counter-clockwise',
        'default'     => 'squares-four',
    ];
@endphp

@if(! empty($items))
    <nav
        class="nav-tabs"
        data-portal-nav="{{ $portal }}"
        aria-label="{{ $ariaLabel }}"
    >
        @foreach($items as $item)
            @php
                $key = $item['key'] ?? 'default';
                $type = $item['type'] ?? 'link';

                $isActive = ($item['active'] ?? null)
                    ?? ($active === $key);

                /*
                 * Permite:
                 *
                 * 'icon' => 'projects'
                 *
                 * ou diretamente:
                 *
                 * 'icon' => 'folder-open'
                 */
                $requestedIcon = $item['icon'] ?? $key;

                $icon = $icons[$requestedIcon]
                    ?? $requestedIcon
                    ?? $icons['default'];

                $content = '
                    <span class="app-nav-icon" aria-hidden="true">
                        <i class="ph-duotone ph-' . e($icon) . '"></i>
                    </span>

                    <span class="app-nav-label">'
                        . e($item['label'] ?? '') .
                    '</span>
                ';
            @endphp

            @if($type === 'form')
                <form
                    action="{{ $item['url'] ?? '#' }}"
                    method="POST"
                >
                    @csrf

                    @if(! in_array(
                        strtoupper($item['method'] ?? 'POST'),
                        ['GET', 'POST'],
                        true
                    ))
                        @method($item['method'])
                    @endif

                    <button
                        type="submit"
                        class="nav-tab {{ $isActive ? 'active' : '' }}"
                        data-nav-key="{{ $key }}"
                        @if($isActive)
                            aria-current="page"
                        @endif
                    >
                        {!! $content !!}
                    </button>
                </form>

            @elseif($type === 'button')
                <button
                    type="button"
                    class="nav-tab {{ $isActive ? 'active' : '' }}"
                    data-nav-key="{{ $key }}"
                    data-nav-event="{{ $item['action'] ?? $key }}"
                    @if($isActive)
                        aria-current="page"
                    @endif
                >
                    {!! $content !!}
                </button>

            @else
                <a
                    href="{{ $item['url'] ?? '#' }}"
                    class="nav-tab {{ $isActive ? 'active' : '' }}"
                    data-nav-key="{{ $key }}"
                    @if($isActive)
                        aria-current="page"
                    @endif
                >
                    {!! $content !!}
                </a>
            @endif
        @endforeach
    </nav>
@endif