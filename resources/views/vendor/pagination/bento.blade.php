@if ($paginator->hasPages())
<nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="bento-pagination">
    <style>
        .bento-pagination {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .35rem;
            flex-wrap: wrap;
        }
        .bento-pagination a,
        .bento-pagination span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 34px;
            height: 34px;
            padding: 0 .55rem;
            border-radius: var(--radius-md, 8px);
            font-size: .82rem;
            font-weight: 600;
            border: 1px solid var(--color-border, #e5e7eb);
            background: var(--color-surface, #fff);
            color: var(--color-text, #111827);
            text-decoration: none;
            transition: background .15s, border-color .15s, color .15s;
            cursor: pointer;
            white-space: nowrap;
        }
        .bento-pagination a:hover {
            background: color-mix(in srgb, var(--color-primary, #4f46e5) 8%, var(--color-surface, #fff));
            border-color: var(--color-primary, #4f46e5);
            color: var(--color-primary, #4f46e5);
        }
        .bento-pagination span[aria-current="page"] > span {
            background: var(--color-primary, #4f46e5);
            border-color: var(--color-primary, #4f46e5);
            color: #fff;
        }
        .bento-pagination span.disabled {
            opacity: .4;
            cursor: not-allowed;
            pointer-events: none;
        }
        .bento-pagination .pg-info {
            font-size: .78rem;
            color: var(--color-text-secondary, #6b7280);
            padding: 0 .35rem;
        }
    </style>

    {{-- Previous --}}
    @if ($paginator->onFirstPage())
        <span class="disabled" aria-disabled="true" aria-label="{{ __('pagination.previous') }}">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="15 18 9 12 15 6"/>
            </svg>
        </span>
    @else
        <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('pagination.previous') }}">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="15 18 9 12 15 6"/>
            </svg>
        </a>
    @endif

    {{-- Page numbers --}}
    @foreach ($elements as $element)
        {{-- "Three Dots" separator --}}
        @if (is_string($element))
            <span aria-disabled="true">{{ $element }}</span>
        @endif

        @if (is_array($element))
            @foreach ($element as $page => $url)
                @if ($page == $paginator->currentPage())
                    <span aria-current="page">
                        <span>{{ $page }}</span>
                    </span>
                @else
                    <a href="{{ $url }}" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</a>
                @endif
            @endforeach
        @endif
    @endforeach

    {{-- Next --}}
    @if ($paginator->hasMorePages())
        <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('pagination.next') }}">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="9 18 15 12 9 6"/>
            </svg>
        </a>
    @else
        <span class="disabled" aria-disabled="true" aria-label="{{ __('pagination.next') }}">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="9 18 15 12 9 6"/>
            </svg>
        </span>
    @endif

    <span class="pg-info">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} de {{ $paginator->total() }}</span>
</nav>
@endif
