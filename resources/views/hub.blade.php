@extends('layouts.bento')

@section('title', 'Escolher painel')

@php
    $displayName = session('tenant_id')
        && method_exists($user, 'getTenantName')
            ? ($user->getTenantName(session('tenant_id')) ?: 'Membro')
            : ($user->name ?: 'Membro');

    $rolesCollection = collect($roles ?? []);
    $hasSuperAdmin = $user->hasRole('super_admin');
    $availablePanelsCount = $rolesCollection->count() + ($hasSuperAdmin ? 1 : 0);

    $phosphorIcons = [
        'settings' => 'ph-gear-six',
        'settings-2' => 'ph-gear-six',
        'layout-dashboard' => 'ph-squares-four',
        'panels-top-left' => 'ph-squares-four',
        'shield' => 'ph-shield-check',
        'shield-check' => 'ph-shield-check',
        'users' => 'ph-users-three',
        'user-round' => 'ph-user-circle',
        'user-cog' => 'ph-user-gear',
        'landmark' => 'ph-bank',
        'building-2' => 'ph-buildings',
        'wallet' => 'ph-wallet',
        'wallet-cards' => 'ph-wallet',
        'receipt' => 'ph-receipt',
        'package' => 'ph-package',
        'truck' => 'ph-truck',
        'clipboard-list' => 'ph-clipboard-text',
        'file-text' => 'ph-file-text',
        'chart-bar' => 'ph-chart-bar',
        'bar-chart-3' => 'ph-chart-bar',
        'calculator' => 'ph-calculator',
        'hand-coins' => 'ph-hand-coins',
        'sprout' => 'ph-plant',
        'store' => 'ph-storefront',
        'briefcase' => 'ph-briefcase',
        'database' => 'ph-database',
        'calendar' => 'ph-calendar-dots',
        'calendar-days' => 'ph-calendar-dots',
        'folder' => 'ph-folder-open',
        'folder-open' => 'ph-folder-open',
    ];

    $resolvePhosphorIcon = static fn ($icon): string =>
        $phosphorIcons[$icon ?? ''] ?? 'ph-squares-four';

    $resolveTone = static function ($color): string {
        return match ($color) {
            'info', 'blue', 'cyan' => 'blue',
            'warning', 'orange' => 'amber',
            'danger', 'red' => 'red',
            'secondary', 'indigo', 'violet', 'purple' => 'violet',
            'slate', 'gray' => 'slate',
            default => 'green',
        };
    };

    $resolvePortalVisual = static function (
        string $name,
        ?string $fallbackIcon = null,
        ?string $fallbackColor = null
    ) use ($resolvePhosphorIcon, $resolveTone): array {
        $normalized = \Illuminate\Support\Str::lower(
            \Illuminate\Support\Str::ascii($name)
        );

        if (
            str_contains($normalized, 'super admin')
            || str_contains($normalized, 'superadmin')
            || str_contains($normalized, 'master')
        ) {
            return [
                'tone' => 'red',
                'icon' => 'ph-crown-simple',
                'hint' => 'Controle geral do sistema',
            ];
        }

        if (
            str_contains($normalized, 'admin')
            || str_contains($normalized, 'administrador')
        ) {
            return [
                'tone' => 'violet',
                'icon' => 'ph-shield-check',
                'hint' => 'Configuração e controle',
            ];
        }

        if (
            str_contains($normalized, 'gestor')
            || str_contains($normalized, 'gestao')
            || str_contains($normalized, 'gerente')
        ) {
            return [
                'tone' => 'blue',
                'icon' => 'ph-chart-line-up',
                'hint' => 'Gestão e projetos',
            ];
        }

        if (
            str_contains($normalized, 'finance')
            || str_contains($normalized, 'tesour')
            || str_contains($normalized, 'contab')
        ) {
            return [
                'tone' => 'amber',
                'icon' => 'ph-wallet',
                'hint' => 'Valores e conferências',
            ];
        }

        if (
            str_contains($normalized, 'secretar')
            || str_contains($normalized, 'document')
        ) {
            return [
                'tone' => 'sky',
                'icon' => 'ph-file-text',
                'hint' => 'Cadastros e documentos',
            ];
        }

        if (
            str_contains($normalized, 'entrega')
            || str_contains($normalized, 'operac')
            || str_contains($normalized, 'campo')
        ) {
            return [
                'tone' => 'green',
                'icon' => 'ph-package',
                'hint' => 'Entregas e operação',
            ];
        }

        if (
            str_contains($normalized, 'estoque')
            || str_contains($normalized, 'almox')
        ) {
            return [
                'tone' => 'slate',
                'icon' => 'ph-warehouse',
                'hint' => 'Itens e movimentações',
            ];
        }

        if (
            str_contains($normalized, 'membro')
            || str_contains($normalized, 'associado')
            || str_contains($normalized, 'produtor')
        ) {
            return [
                'tone' => 'green',
                'icon' => 'ph-user-circle',
                'hint' => 'Participação e histórico',
            ];
        }

        return [
            'tone' => $resolveTone($fallbackColor),
            'icon' => $resolvePhosphorIcon($fallbackIcon),
            'hint' => 'Ferramentas deste painel',
        ];
    };
@endphp

@section('page-title')
Painéis
@endsection

@section('page-subtitle', ($currentTenant->name ?? 'Sua organização') . ' · Acesse as áreas disponíveis para o seu perfil.')
@section('user-role', 'Central de acesso')

@section('content')
<style>
    .hub-page {
        --hub-green: #168a4d;
        --hub-green-deep: #0e542e;
        --hub-green-soft: #eaf8ef;
        --hub-blue: #2563eb;
        --hub-blue-soft: #eef4ff;
        --hub-sky: #0284c7;
        --hub-sky-soft: #edf8fe;
        --hub-violet: #7c3aed;
        --hub-violet-soft: #f4f0ff;
        --hub-amber: #c87408;
        --hub-amber-soft: #fff7e8;
        --hub-red: #cf3f3f;
        --hub-red-soft: #fff0f0;
        --hub-slate: #596b61;
        --hub-slate-soft: #eef2ef;
        --hub-ease: cubic-bezier(.2, .8, .2, 1);

        width: min(100%, calc(100dvw - 40px), 1500px);
        min-width: 0;
        grid-column: 1 / -1;
        margin: 0 auto;
    }

    .hub-page,
    .hub-page *,
    .hub-page *::before,
    .hub-page *::after {
        box-sizing: border-box;
    }

    .hub-shell {
        width: 100%;
        min-width: 0;
    }

    .hub-toolbar {
        display: flex;
        min-width: 0;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
    }

    .hub-tenant-logo {
        width: 38px;
        height: 38px;
        border: 1px solid rgba(22, 138, 77, .12);
        border-radius: 11px;
        background: var(--hub-green-soft);
    }

    .hub-tenant-logo img {
        width: 25px;
        height: 25px;
        object-fit: contain;
    }

    .hub-context-name {
        color: var(--color-text, #102018);
        font-size: 13px;
        font-weight: 800;
        line-height: 1.25;
    }

    .hub-context-meta {
        margin-top: 2px;
        color: var(--color-text-muted, #809087);
        font-size: 10px;
        font-weight: 650;
        line-height: 1.3;
    }

    .hub-search {
        position: relative;
        width: min(100%, 280px);
    }

    .hub-search-icon {
        position: absolute;
        top: 50%;
        left: .78rem;
        color: var(--color-text-muted, #809087);
        transform: translateY(-50%);
        pointer-events: none;
    }

    .hub-search-input {
        width: 100%;
        height: 40px;
        border: 1px solid var(--color-border, #dce6df);
        border-radius: 12px;
        background: rgba(255, 255, 255, .86);
        padding: 0 2.3rem 0 2.25rem;
        color: var(--color-text, #102018);
        font-size: 12px;
        font-weight: 650;
        outline: none;
        transition: border-color 150ms ease, box-shadow 150ms ease, background 150ms ease;
    }

    .hub-search-input::placeholder {
        color: var(--color-text-muted, #809087);
        font-weight: 550;
    }

    .hub-search-input:focus {
        border-color: color-mix(in srgb, var(--hub-green) 50%, transparent);
        background: #fff;
        box-shadow: 0 0 0 4px rgba(22, 138, 77, .09);
    }

    .hub-search-clear {
        position: absolute;
        top: 50%;
        right: .45rem;
        display: none;
        width: 28px;
        height: 28px;
        place-items: center;
        border: 0;
        border-radius: 8px;
        background: transparent;
        color: var(--color-text-muted, #809087);
        cursor: pointer;
        transform: translateY(-50%);
    }

    .hub-search.has-value .hub-search-clear { display: grid; }

    .hub-search-clear:hover,
    .hub-search-clear:focus-visible {
        background: var(--color-surface-muted, #edf2ef);
        color: var(--color-text, #102018);
        outline: none;
    }

    .hub-list {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 13px;
    }

    .hub-link {
        --portal-tone: var(--hub-green);
        --portal-soft: var(--hub-green-soft);
        position: relative;
        display: grid;
        min-height: 102px;
        grid-template-columns: auto minmax(0, 1fr);
        align-items: center;
        gap: 13px;
        overflow: hidden;
        border: 1px solid var(--color-border, #dce6df);
        border-radius: 15px;
        background:
            linear-gradient(145deg, rgba(255, 255, 255, .98), rgba(249, 251, 250, .96));
        padding: 15px 46px 15px 15px;
        color: inherit;
        text-decoration: none;
        box-shadow: 0 3px 12px rgba(15, 35, 24, .035);
        transition:
            border-color 150ms ease,
            background 150ms ease,
            box-shadow 150ms ease,
            transform 150ms ease;
    }

    .hub-link.tone-blue {
        --portal-tone: var(--hub-blue);
        --portal-soft: var(--hub-blue-soft);
    }

    .hub-link.tone-sky {
        --portal-tone: var(--hub-sky);
        --portal-soft: var(--hub-sky-soft);
    }

    .hub-link.tone-violet {
        --portal-tone: var(--hub-violet);
        --portal-soft: var(--hub-violet-soft);
    }

    .hub-link.tone-amber {
        --portal-tone: var(--hub-amber);
        --portal-soft: var(--hub-amber-soft);
    }

    .hub-link.tone-red {
        --portal-tone: var(--hub-red);
        --portal-soft: var(--hub-red-soft);
    }

    .hub-link.tone-slate {
        --portal-tone: var(--hub-slate);
        --portal-soft: var(--hub-slate-soft);
    }

    .hub-link:hover,
    .hub-link:focus-visible {
        border-color: color-mix(in srgb, var(--portal-tone) 28%, transparent);
        background:
            linear-gradient(145deg, #fff, color-mix(in srgb, var(--portal-soft) 42%, #fff));
        color: inherit;
        outline: none;
        box-shadow: 0 12px 28px rgba(15, 35, 24, .085);
        transform: translateY(-2px);
    }

    .hub-link:active { transform: translateY(0); }

    .hub-role-icon {
        width: 48px;
        height: 48px;
        border: 1px solid color-mix(in srgb, var(--portal-tone) 10%, transparent);
        background: var(--portal-soft);
        color: var(--portal-tone);
    }

    .hub-link-arrow {
        position: absolute;
        top: 50%;
        right: 12px;
        color: var(--color-text-muted, #809087);
        transform: translateY(-50%);
        transition: background 150ms ease, color 150ms ease, transform 150ms ease;
    }

    .hub-link:hover .hub-link-arrow,
    .hub-link:focus-visible .hub-link-arrow {
        background: var(--portal-soft);
        color: var(--portal-tone);
        transform: translate(2px, -50%);
    }

    .hub-link.is-opening {
        border-color: color-mix(in srgb, var(--portal-tone) 34%, transparent);
        background: var(--portal-soft);
        pointer-events: none;
    }

    .hub-link.is-opening::after {
        position: absolute;
        top: 0;
        bottom: 0;
        left: -34%;
        width: 30%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, .65), transparent);
        content: "";
        animation: hub-scan 1.05s ease-in-out infinite;
        pointer-events: none;
    }

    @keyframes hub-scan {
        from { left: -34%; }
        to { left: 112%; }
    }

    .hub-empty-icon {
        background: var(--hub-amber-soft);
        color: var(--hub-amber);
    }

    .hub-link[hidden],
    .hub-no-results[hidden] {
        display: none !important;
    }

    .hub-no-results {
        grid-column: 1 / -1;
    }

    @media (max-width: 1180px) {
        .hub-list { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }

    @media (max-width: 880px) {
        .hub-list { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    @media (max-width: 640px) {
        .hub-page {
            width: min(100%, calc(100dvw - 20px));
        }

        .hub-toolbar {
            display: grid;
            align-items: flex-start;
            gap: 10px;
        }

        .hub-toolbar-actions {
            width: 100%;
        }

        .hub-search {
            width: 100%;
            max-width: none;
        }

        .hub-list {
            grid-template-columns: minmax(0, 1fr);
            gap: 9px;
        }

        .hub-link {
            min-height: 72px;
            align-items: center;
            padding: 11px 12px;
            padding-right: 42px;
        }

        .hub-role-icon {
            width: 42px;
            height: 42px;
        }
    }

    @media (max-height: 720px) {
        .hub-link { min-height: 78px; }
    }

    @media (prefers-reduced-motion: reduce) {
        .hub-page *,
        .hub-page *::before,
        .hub-page *::after {
            animation-duration: .01ms !important;
            animation-iteration-count: 1 !important;
            scroll-behavior: auto !important;
            transition-duration: .01ms !important;
        }
    }
</style>

<main class="hub-page">
    <section class="hub-shell" aria-labelledby="hub-title">
        <div class="hub-shell-content grid min-w-0 gap-3">
            <header class="hub-toolbar">
                <div class="flex min-w-0 items-center gap-2.5">
                    <span class="hub-tenant-logo grid shrink-0 place-items-center" aria-hidden="true">
                        <img
                            src="{{ !empty($currentTenant?->logo)
                                ? asset('storage/' . $currentTenant->logo)
                                : asset('assets/sgc-symbol.png') }}"
                            alt=""
                        >
                    </span>

                    <span class="block min-w-0">
                        <h2 class="hub-context-name m-0 truncate" id="hub-title">
                            {{ $currentTenant->name ?? 'Sua organização' }}
                        </h2>
                        <span class="hub-context-meta flex items-center gap-1.5">
                            <span class="truncate">{{ $displayName }}</span>
                            <span aria-hidden="true">·</span>
                            <span aria-label="{{ $availablePanelsCount }} {{ $availablePanelsCount === 1 ? 'painel disponível' : 'painéis disponíveis' }}">
                                <span data-visible-count>{{ $availablePanelsCount }}</span>
                                {{ $availablePanelsCount === 1 ? 'painel' : 'painéis' }}
                            </span>
                        </span>
                    </span>
                </div>

                @if($availablePanelsCount > 6)
                    <div class="hub-toolbar-actions flex shrink-0 items-center gap-2">
                        <div class="hub-search" data-hub-search>
                            <label class="sr-only" for="hub-panel-search">Buscar painel</label>
                            <i class="hub-search-icon ph ph-magnifying-glass text-sm" aria-hidden="true"></i>
                            <input
                                id="hub-panel-search"
                                class="hub-search-input"
                                type="search"
                                placeholder="Buscar entre os painéis"
                                autocomplete="off"
                                spellcheck="false"
                                data-hub-search-input
                            >
                            <button
                                class="hub-search-clear"
                                type="button"
                                aria-label="Limpar busca"
                                data-hub-search-clear
                            >
                                <i class="ph ph-x text-sm" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                @endif
            </header>

            <section class="grid min-w-0 gap-2" aria-label="Painéis disponíveis">

                <nav
                    class="hub-list min-w-0"
                    aria-label="Painéis disponíveis"
                    data-hub-list
                >
                    @if($hasSuperAdmin)
                        @php
                            $superVisual = $resolvePortalVisual('Super Admin');
                        @endphp

                        <a
                            class="hub-link tone-{{ $superVisual['tone'] }} min-w-0 text-left"
                            href="{{ url('super-admin') }}"
                            aria-label="Abrir Super Admin"
                            data-hub-link
                        >
                            <span
                                class="hub-role-icon grid shrink-0 place-items-center rounded-xl"
                                aria-hidden="true"
                            >
                                <i class="ph-duotone {{ $superVisual['icon'] }} text-lg"></i>
                            </span>

                            <span class="block min-w-0">
                                <strong class="block truncate text-[13px] font-extrabold text-[var(--color-text,#102018)]">
                                    Super Admin
                                </strong>
                                <span class="mt-1 block truncate text-[11px] leading-[1.45] text-[var(--color-text-muted,#809087)]">
                                    {{ $superVisual['hint'] }}
                                </span>
                            </span>

                            <span
                                class="hub-link-arrow grid size-7 place-items-center rounded-lg"
                                aria-hidden="true"
                            >
                                <i class="ph ph-arrow-right text-sm"></i>
                            </span>
                        </a>
                    @endif

                    @foreach($rolesCollection as $role)
                        @php
                            $visual = $resolvePortalVisual(
                                $role['name'],
                                $role['icon'] ?? 'layout-dashboard',
                                $role['color'] ?? 'primary'
                            );

                            $portalDescription = filled($role['description'] ?? null)
                                ? $role['description']
                                : $visual['hint'];
                        @endphp

                        <a
                            class="hub-link tone-{{ $visual['tone'] }} min-w-0 text-left"
                            href="{{ $role['url'] }}"
                            aria-label="Abrir {{ $role['name'] }}"
                            data-hub-link
                        >
                            <span
                                class="hub-role-icon grid shrink-0 place-items-center rounded-xl"
                                aria-hidden="true"
                            >
                                <i class="ph-duotone {{ $visual['icon'] }} text-lg"></i>
                            </span>

                            <span class="block min-w-0">
                                <strong class="block truncate text-[13px] font-extrabold text-[var(--color-text,#102018)]">
                                    {{ $role['name'] }}
                                </strong>
                                <span
                                    class="mt-1 block truncate text-[11px] leading-[1.45] text-[var(--color-text-muted,#809087)]"
                                    title="{{ $portalDescription }}"
                                >
                                    {{ $portalDescription }}
                                </span>
                            </span>

                            <span
                                class="hub-link-arrow grid size-7 place-items-center rounded-lg"
                                aria-hidden="true"
                            >
                                <i class="ph ph-arrow-right text-sm"></i>
                            </span>
                        </a>
                    @endforeach

                    @if($availablePanelsCount === 0)
                        <div
                            class="grid justify-items-center rounded-[13px] border border-dashed border-[var(--color-border-strong,#c8d6cd)] bg-[var(--color-surface-soft,#f8faf9)] px-4 py-6 text-center"
                            role="status"
                        >
                            <span
                                class="hub-empty-icon mb-2 grid size-11 place-items-center rounded-[13px]"
                                aria-hidden="true"
                            >
                                <i class="ph-duotone ph-shield-warning text-xl"></i>
                            </span>
                            <strong class="text-[13px] font-extrabold text-[var(--color-text,#102018)]">
                                Nenhum painel disponível
                            </strong>
                            <p class="mb-0 mt-1 max-w-[30ch] text-[11px] leading-relaxed text-[var(--color-text-secondary,#52645a)]">
                                Solicite acesso a um administrador.
                            </p>
                        </div>
                    @endif

                    <div
                        class="hub-no-results grid justify-items-center rounded-[14px] border border-dashed border-[var(--color-border-strong,#c8d6cd)] bg-[var(--color-surface-soft,#f8faf9)] px-4 py-8 text-center"
                        role="status"
                        aria-live="polite"
                        hidden
                        data-hub-no-results
                    >
                        <span class="hub-empty-icon mb-2 grid size-11 place-items-center rounded-[13px]" aria-hidden="true">
                            <i class="ph-duotone ph-magnifying-glass text-xl"></i>
                        </span>
                        <strong class="text-[13px] font-extrabold text-[var(--color-text,#102018)]">
                            Nenhum painel encontrado
                        </strong>
                        <p class="mb-0 mt-1 text-[11px] text-[var(--color-text-secondary,#52645a)]">
                            Tente buscar por outro nome.
                        </p>
                    </div>
                </nav>
            </section>
        </div>
    </section>
</main>

@if($availablePanelsCount > 0)
<script>
    (() => {
        const hubLinks = [
            ...document.querySelectorAll('[data-hub-link]')
        ];

        function resetLinks() {
            hubLinks.forEach(link => {
                link.classList.remove('is-opening');
                link.removeAttribute('aria-busy');
            });
        }

        const search = document.querySelector('[data-hub-search]');
        const searchInput = document.querySelector('[data-hub-search-input]');
        const searchClear = document.querySelector('[data-hub-search-clear]');
        const noResults = document.querySelector('[data-hub-no-results]');
        const visibleCount = document.querySelector('[data-visible-count]');

        const normalize = value => value
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLocaleLowerCase('pt-BR')
            .trim();

        function filterPanels() {
            if (!searchInput) return;

            const query = normalize(searchInput.value);
            let matches = 0;

            hubLinks.forEach(link => {
                const isMatch = !query || normalize(link.textContent).includes(query);
                link.hidden = !isMatch;
                if (isMatch) matches += 1;
            });

            search?.classList.toggle('has-value', Boolean(searchInput.value));
            if (noResults) noResults.hidden = matches !== 0;
            if (visibleCount) visibleCount.textContent = String(matches);
        }

        searchInput?.addEventListener('input', filterPanels);
        searchClear?.addEventListener('click', () => {
            searchInput.value = '';
            filterPanels();
            searchInput.focus();
        });

        hubLinks.forEach(link => {
            link.addEventListener('click', event => {
                if (
                    event.defaultPrevented
                    || event.button !== 0
                    || event.metaKey
                    || event.ctrlKey
                    || event.shiftKey
                    || event.altKey
                ) {
                    return;
                }

                link.classList.add('is-opening');
                link.setAttribute('aria-busy', 'true');
            });
        });

        window.addEventListener('pageshow', () => {
            resetLinks();
            filterPanels();
        });
    })();
</script>
@endif
@endsection