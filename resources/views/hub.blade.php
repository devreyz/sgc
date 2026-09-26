@extends('layouts.bento')

@section('title', 'Central da organização')

@php
    // O superadministrador opera em contexto global e pode não ter organização.
    $currentTenant = $currentTenant ?? null;
    $displayName = session('tenant_id')
        && method_exists($user, 'getTenantName')
            ? ($user->getTenantName(session('tenant_id')) ?: 'Membro')
            : ($user->name ?: 'Membro');

    $rolesCollection = collect($roles ?? []);
    $hasSuperAdmin = $user->hasRole('super_admin');
    $isSystemContext = $hasSuperAdmin && ! $currentTenant;
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
        'currency' => 'ph-currency-dollar',
        'eye' => 'ph-eye',
        'monitor' => 'ph-monitor',
        'building' => 'ph-buildings',
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
            'primary' => 'primary',
            'success', 'green' => 'success',
            'info', 'blue' => 'info',
            'warning', 'orange', 'amber' => 'warning',
            'danger', 'red' => 'danger',
            'violet', 'purple', 'indigo' => 'violet',
            'cyan', 'sky' => 'cyan',
            'secondary', 'slate', 'gray', 'neutral' => 'neutral',
            default => 'neutral',
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

        $tone = $resolveTone($fallbackColor);
        $icon = $resolvePhosphorIcon($fallbackIcon);
        $hint = 'Ferramentas deste painel';

        if (
            str_contains($normalized, 'super admin')
            || str_contains($normalized, 'superadmin')
            || str_contains($normalized, 'master')
        ) {
            $tone = 'violet';
            $icon = 'ph-crown-simple';
            $hint = 'Controle geral do sistema';
        } elseif (
            str_contains($normalized, 'admin')
            || str_contains($normalized, 'administrador')
        ) {
            $icon = 'ph-shield-check';
            $hint = 'Configuração e controle';
        } elseif (
            str_contains($normalized, 'finance')
            || str_contains($normalized, 'tesour')
        ) {
            $icon = 'ph-wallet';
            $hint = 'Recebimentos, pagamentos e conferências';
        } elseif (
            str_contains($normalized, 'servico')
            || str_contains($normalized, 'serviço')
            || str_contains($normalized, 'prestador')
        ) {
            $icon = 'ph-briefcase';
            $hint = 'Ordens e operação de serviços';
        } elseif (
            str_contains($normalized, 'contab')
        ) {
            $icon = 'ph-calculator';
            $hint = 'Fila contábil e dossiês financeiros';
        } elseif (
            str_contains($normalized, 'secretar')
            || str_contains($normalized, 'document')
        ) {
            $icon = 'ph-file-text';
            $hint = 'Atas, modelos e documentos';
        } elseif (
            str_contains($normalized, 'membro')
            || str_contains($normalized, 'associado')
            || str_contains($normalized, 'produtor')
        ) {
            $icon = 'ph-users-three';
            $hint = 'Projetos e entregas';
        } elseif (
            str_contains($normalized, 'registrador')
        ) {
            $icon = 'ph-package';
            $hint = 'Registro de entregas de produção';
        } elseif (
            str_contains($normalized, 'acompanhamento')
            || str_contains($normalized, 'visualizador')
        ) {
            $icon = 'ph-eye';
            $hint = 'Consulta de entregas, limites e distribuições';
        } elseif (
            str_contains($normalized, 'pdv')
            || str_contains($normalized, 'ponto de venda')
        ) {
            $icon = 'ph-monitor';
            $hint = 'Vendas e controle de caixa';
        } elseif (
            str_contains($normalized, 'compradora')
            || str_contains($normalized, 'comprador')
        ) {
            $icon = 'ph-buildings';
            $hint = 'Solicitações e distribuições';
        }

        return compact('tone', 'icon', 'hint');
    };

    $normalizeUiTone = static fn ($tone): string => $resolveTone($tone);

    $routeTenant = request()->route('tenant');
    $tenantSlug = $currentTenant?->slug
        ?? (is_string($routeTenant)
            ? $routeTenant
            : (is_object($routeTenant)
                ? ($routeTenant->slug ?? null)
                : null));

    $tenantSettings = data_get($currentTenant, 'settings', []);
    if (is_string($tenantSettings)) {
        $tenantSettings = json_decode($tenantSettings, true) ?: [];
    }

    $tenantValue = static function (array $paths) use ($currentTenant, $tenantSettings) {
        foreach ($paths as $path) {
            $value = data_get($currentTenant, $path);
            $value ??= data_get($tenantSettings, $path);

            if (filled($value)) {
                return is_string($value) ? trim($value) : $value;
            }
        }

        return null;
    };

    $normalizeWebUrl = static function (?string $value): ?string {
        if (blank($value)) return null;

        $value = trim($value);
        return \Illuminate\Support\Str::startsWith($value, ['http://', 'https://'])
            ? $value
            : 'https://' . ltrim($value, '/');
    };

    $resolveNamedRoute = static function (array $names, array $parameters = []): ?string {
        foreach ($names as $name) {
            if (! \Illuminate\Support\Facades\Route::has($name)) continue;

            try {
                return route($name, $parameters);
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    };

    $tenantDescription = $tenantValue([
        'description',
        'about',
        'hub.description',
        'institutional.description',
    ]) ?: ($isSystemContext
        ? 'Administração global do SGC, independente de organização.'
        : 'Central de serviços, comunicação e acesso da organização.');

    $tenantDocument = $tenantValue(['cnpj', 'document', 'tax_id']);
    $tenantCity = $tenantValue(['city', 'address.city', 'contact.city']);
    $tenantState = $tenantValue(['state', 'address.state', 'contact.state']);
    $tenantLocation = collect([$tenantCity, $tenantState])->filter()->implode(' · ');
    $tenantEmail = $tenantValue(['contact_email', 'email', 'contact.email']);
    $tenantPhone = $tenantValue(['phone', 'telephone', 'contact.phone']);
    $tenantWhatsapp = $tenantValue(['whatsapp', 'contact.whatsapp']);
    $tenantWebsite = $normalizeWebUrl($tenantValue(['website', 'site', 'social.website']));

    $phoneDigits = preg_replace('/\D+/', '', (string) $tenantPhone);
    $whatsappDigits = preg_replace('/\D+/', '', (string) $tenantWhatsapp);
    if ($whatsappDigits && ! str_starts_with($whatsappDigits, '55')) {
        $whatsappDigits = '55' . $whatsappDigits;
    }

    $tenantPhoneUrl = $phoneDigits ? 'tel:+' . $phoneDigits : null;
    $tenantWhatsappUrl = $whatsappDigits ? 'https://wa.me/' . $whatsappDigits : null;
    $tenantEmailUrl = $tenantEmail ? 'mailto:' . $tenantEmail : null;

    $socialCandidates = [
        ['Instagram', 'ph-instagram-logo', $tenantValue(['instagram', 'social.instagram', 'socials.instagram']), 'https://instagram.com/'],
        ['Facebook', 'ph-facebook-logo', $tenantValue(['facebook', 'social.facebook', 'socials.facebook']), 'https://facebook.com/'],
        ['YouTube', 'ph-youtube-logo', $tenantValue(['youtube', 'social.youtube', 'socials.youtube']), 'https://youtube.com/'],
        ['LinkedIn', 'ph-linkedin-logo', $tenantValue(['linkedin', 'social.linkedin', 'socials.linkedin']), 'https://linkedin.com/'],
    ];

    $tenantSocials = collect($socialCandidates)
        ->map(static function (array $social): ?array {
            [$label, $icon, $value, $baseUrl] = $social;
            if (blank($value)) return null;

            $value = trim((string) $value);
            $url = \Illuminate\Support\Str::startsWith($value, ['http://', 'https://'])
                ? $value
                : $baseUrl . ltrim($value, '@/');

            return compact('label', 'icon', 'url');
        })
        ->filter()
        ->values();

    $tenantRouteParameters = $tenantSlug ? ['tenant' => $tenantSlug] : [];
    $minutesUrl = $resolveNamedRoute([
        'minutes.index',
        'secretary.minutes.index',
        'secretariat.minutes.index',
        'documents.minutes.index',
        'atas.index',
    ], $tenantRouteParameters);
    $documentsUrl = $resolveNamedRoute([
        'documents.index',
        'secretary.documents.index',
        'secretariat.documents.index',
        'tenant.documents.index',
    ], $tenantRouteParameters);
    $eventsUrl = $resolveNamedRoute([
        'events.index',
        'calendar.index',
        'tenant.events.index',
    ], $tenantRouteParameters);

    $normalizeResourceUrl = static function ($value): ?string {
        if (blank($value)) return null;

        $value = trim((string) $value);
        if (
            \Illuminate\Support\Str::startsWith(
                $value,
                ['http://', 'https://', 'mailto:', 'tel:']
            )
        ) {
            return $value;
        }

        return \Illuminate\Support\Str::startsWith($value, '/')
            ? url($value)
            : url('/' . ltrim($value, '/'));
    };

    $minutesUrl ??= $normalizeResourceUrl(
        $tenantValue(['hub.links.minutes', 'links.minutes', 'links.atas'])
    );
    $documentsUrl ??= $normalizeResourceUrl(
        $tenantValue(['hub.links.documents', 'links.documents'])
    );
    $eventsUrl ??= $normalizeResourceUrl(
        $tenantValue(['hub.links.events', 'links.events', 'links.calendar'])
    );
    $notificationsUrl = $tenantSlug
        ? $resolveNamedRoute(['notifications.index'], $tenantRouteParameters)
        : null;
    $securityUrl = $resolveNamedRoute(['security.index']);
    $profileUrl = $tenantSlug ? url('/' . $tenantSlug . '/profile') : null;
    $walletUrl = $tenantSlug ? url('/' . $tenantSlug . '/wallet') : null;

    $hubResources = collect([
        ['label' => 'Atas e reuniões', 'description' => 'Decisões e registros oficiais', 'icon' => 'ph-notebook', 'tone' => 'violet', 'url' => $minutesUrl],
        ['label' => 'Documentos', 'description' => 'Arquivos da organização', 'icon' => 'ph-folder-open', 'tone' => 'blue', 'url' => $documentsUrl],
        ['label' => 'Agenda e eventos', 'description' => 'Compromissos e atividades', 'icon' => 'ph-calendar-dots', 'tone' => 'amber', 'url' => $eventsUrl],
        ['label' => 'Notificações', 'description' => 'Avisos e atualizações', 'icon' => 'ph-bell-ringing', 'tone' => 'sky', 'url' => $notificationsUrl],
        ['label' => 'Meu perfil', 'description' => 'Dados pessoais e acesso', 'icon' => 'ph-user-circle', 'tone' => 'green', 'url' => $profileUrl],
        ['label' => 'Segurança', 'description' => 'Passkeys e conta Google', 'icon' => 'ph-key', 'tone' => 'slate', 'url' => $securityUrl],
        ['label' => 'Minha carteira', 'description' => 'Carteirinha e extrato', 'icon' => 'ph-wallet', 'tone' => 'amber', 'url' => $walletUrl],
    ])->filter(fn (array $resource) => filled($resource['url']))->values();

    $configuredNews = data_get($tenantSettings, 'hub.news')
        ?? data_get($tenantSettings, 'hub_news')
        ?? data_get($tenantSettings, 'news')
        ?? [];

    if (is_string($configuredNews)) {
        $configuredNews = json_decode($configuredNews, true) ?: [];
    }

    $hubNews = collect(is_iterable($configuredNews) ? $configuredNews : [])
        ->map(static function ($item): ?array {
            $item = is_object($item) ? (array) $item : $item;
            if (! is_array($item) || blank($item['title'] ?? null)) return null;

            $tone = in_array($item['tone'] ?? null, ['green', 'blue', 'sky', 'violet', 'amber', 'red', 'slate'], true)
                ? $item['tone']
                : 'blue';

            return [
                'title' => $item['title'],
                'description' => $item['description'] ?? $item['body'] ?? '',
                'label' => $item['label'] ?? $item['date'] ?? 'Novidade',
                'icon' => $item['icon'] ?? 'ph-megaphone-simple',
                'tone' => $tone,
                'url' => $item['url'] ?? null,
            ];
        })
        ->filter()
        ->values();

    if ($hubNews->isEmpty()) {
        $hubNews = collect([
            [
                'title' => 'Uma central mais completa',
                'description' => 'Painéis, informações institucionais, documentos e canais agora ficam reunidos neste hub.',
                'label' => 'Novo hub',
                'icon' => 'ph-sparkle',
                'tone' => 'violet',
                'url' => null,
            ],
            [
                'title' => $minutesUrl || $documentsUrl ? 'Documentos sempre à mão' : 'Acompanhe os avisos',
                'description' => $minutesUrl || $documentsUrl
                    ? 'Consulte atas, decisões e arquivos publicados pela organização.'
                    : 'Consulte notificações e acompanhe as atualizações importantes da organização.',
                'label' => 'Recursos',
                'icon' => $minutesUrl || $documentsUrl ? 'ph-files' : 'ph-bell-ringing',
                'tone' => 'blue',
                'url' => $minutesUrl ?: ($documentsUrl ?: $notificationsUrl),
            ],
            [
                'title' => $tenantWhatsappUrl || $tenantEmailUrl ? 'Fale com a organização' : 'Mantenha seus dados atualizados',
                'description' => $tenantWhatsappUrl || $tenantEmailUrl
                    ? 'Use os canais oficiais para tirar dúvidas ou solicitar atendimento.'
                    : 'Revise seus dados pessoais e mantenha seu acesso sempre seguro.',
                'label' => 'Atendimento',
                'icon' => $tenantWhatsappUrl || $tenantEmailUrl ? 'ph-chats-circle' : 'ph-user-circle-gear',
                'tone' => 'green',
                'url' => $tenantWhatsappUrl ?: ($tenantEmailUrl ?: $profileUrl),
            ],
        ]);
    }
@endphp

@section('page-title', $isSystemContext ? 'Administração do SGC' : 'Central da organização')
@section(
    'page-subtitle',
    $isSystemContext
        ? 'Contexto global · gestão do sistema e das organizações.'
        : (($currentTenant?->name ?? 'Sua organização') . ' · Portais, recursos e comunicação.')
)
@section('user-role', 'Hub institucional')

@section('content')
@once
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/regular/style.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/fill/style.css">
@endonce

<style>
    /*
     * CSS específico do Hub.
     * Os componentes visuais vêm de theme.css + design-system.css.
     * Aqui ficam somente composição, integração mobile e estados do Hub.
     */

    .hub-page {
        padding-bottom: var(--ui-space-6);
    }

    .hub-brand-logo {
        display: grid;
        width: 42px;
        height: 42px;
        flex: 0 0 auto;
        place-items: center;
        overflow: hidden;
        border: 1px solid var(--ui-color-border);
        border-radius: var(--ui-radius-md);
        background: var(--ui-color-surface);
    }

    .hub-brand-logo img {
        display: block;
        width: 100%;
        height: 100%;
        padding: var(--ui-space-1);
        object-fit: contain;
    }

    .hub-head-meta-count {
        color: var(--ui-color-text-secondary);
        font-weight: var(--ui-weight-bold);
    }

    .hub-screen[hidden],
    .hub-no-results[hidden],
    .hub-search-clear[hidden] {
        display: none !important;
    }

    .hub-portals-toolbar {
        margin-bottom: var(--ui-space-4);
    }

    .hub-search {
        position: relative;
        width: min(100%, 24rem);
        margin-left: auto;
    }

    .hub-search > .ph {
        position: absolute;
        z-index: 2;
        top: 50%;
        left: var(--ui-space-3);
        color: var(--ui-color-text-muted);
        pointer-events: none;
        transform: translateY(-50%);
    }

    .hub-search-input {
        padding-left: 2rem;
        padding-right: 2.45rem;
    }

    .hub-search-clear {
        position: absolute;
        z-index: 3;
        top: 50%;
        right: var(--ui-space-1);
        transform: translateY(-50%);
    }

    .hub-search:not(.has-value) .hub-search-clear {
        display: none;
    }

    .hub-action-list {
        width: 100%;
    }

    .hub-action-row.is-opening {
        pointer-events: none;
        opacity: .58;
    }

    .hub-action-row.is-opening .ui-icon-box {
        animation: hub-open-pulse .42s ease-in-out infinite alternate;
    }

    @keyframes hub-open-pulse {
        to {
            opacity: .62;
            transform: scale(.94);
        }
    }

    .hub-news-list {
        display: block;
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--ui-color-border);
        border-radius: var(--ui-radius-md);
        background: var(--ui-color-surface);
    }

    .hub-news-row {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr);
        gap: var(--ui-space-3);
        align-items: start;
        padding: .56rem .58rem;
        border-bottom: 1px solid var(--ui-color-border);
        background: var(--ui-color-surface);
    }

    .hub-news-row:last-child {
        border-bottom: 0;
    }

    .hub-news-copy {
        min-width: 0;
    }

    .hub-news-label,
    .hub-news-title,
    .hub-news-description {
        display: block;
    }

    .hub-news-label {
        color: var(--ui-tone, var(--ui-color-info));
        font-size: .56rem;
        font-weight: 800;
        letter-spacing: .035em;
        text-transform: uppercase;
    }

    .hub-news-title {
        margin-top: .05rem;
        color: var(--ui-color-text);
        font-size: .72rem;
        font-weight: 820;
        line-height: 1.35;
    }

    .hub-news-description {
        display: -webkit-box;
        overflow: hidden;
        margin-top: .1rem;
        color: var(--ui-color-text-muted);
        font-size: .62rem;
        line-height: 1.42;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 3;
    }

    .hub-news-action {
        margin-top: .24rem;
    }

    .hub-org-card {
        display: grid;
        gap: var(--ui-space-3);
        margin-bottom: var(--ui-space-4);
        padding: .6rem;
        background: var(--ui-color-surface-soft);
    }

    .hub-org-main {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr);
        gap: var(--ui-space-3);
        align-items: center;
    }

    .hub-org-copy {
        min-width: 0;
    }

    .hub-org-copy strong,
    .hub-org-copy span {
        display: block;
    }

    .hub-org-copy strong {
        overflow: hidden;
        color: var(--ui-color-text);
        font-size: .74rem;
        font-weight: 820;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .hub-org-copy span {
        display: -webkit-box;
        overflow: hidden;
        margin-top: .06rem;
        color: var(--ui-color-text-muted);
        font-size: .62rem;
        line-height: 1.42;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 3;
    }

    .hub-org-facts,
    .hub-socials {
        display: flex;
        gap: var(--ui-space-2);
        flex-wrap: wrap;
        align-items: center;
    }

    .hub-socials {
        margin-top: var(--ui-space-4);
    }

    .hub-bottom-nav {
        display: none;
    }

    @media (max-width: 1080px) and (min-width: 821px) {
        .hub-page .ui-workspace-grid {
            grid-template-columns:
                minmax(0, 1.28fr)
                minmax(290px, .72fr);
        }
    }

    @media (max-width: 820px) {
        /*
         * Integração intencional com a navegação global:
         * dentro do Hub a bottom-nav própria assume a navegação entre telas.
         */
        body.hub-app-navigation .app-nav-layer {
            display: none !important;
        }

        body.hub-app-navigation.has-app-nav .bento-container {
            padding-bottom:
                calc(
                    70px
                    + var(--ui-space-4)
                    + env(safe-area-inset-bottom, 0px)
                ) !important;
        }

        .hub-page {
            padding-bottom:
                calc(
                    70px
                    + var(--ui-space-5)
                    + env(safe-area-inset-bottom, 0px)
                );
        }

        .hub-page .ui-workspace-grid {
            display: block;
        }

        .hub-main-stack,
        .hub-side-stack {
            display: contents;
        }

        .hub-screen {
            display: none;
        }

        .hub-screen.is-active {
            display: block;
            animation: hub-screen-enter var(--ui-transition-fast) both;
        }

        .hub-search {
            width: 100%;
            margin-left: 0;
        }

        .hub-search-input {
            min-height: 46px;
            font-size: 16px;
        }

        .hub-bottom-nav {
            position: fixed;
            z-index: 140;
            right: max(var(--ui-space-2), env(safe-area-inset-right, 0px));
            bottom: max(var(--ui-space-2), env(safe-area-inset-bottom, 0px));
            left: max(var(--ui-space-2), env(safe-area-inset-left, 0px));
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: var(--ui-space-1);
            padding: var(--ui-space-1);
            border: 1px solid var(--ui-color-border);
            border-radius: var(--ui-radius-lg);
            background: var(--ui-color-surface);
            box-shadow: var(--ui-shadow-md);
        }

        .hub-bottom-item {
            display: grid;
            min-width: 0;
            min-height: 54px;
            place-items: center;
            align-content: center;
            gap: var(--ui-space-1);
            padding: var(--ui-space-1);
            border: 0;
            border-radius: var(--ui-radius-md);
            background: transparent;
            color: var(--ui-color-text-muted);
            cursor: pointer;
        }

        .hub-bottom-item i {
            font-size: 1.08rem;
        }

        .hub-bottom-item span {
            overflow: hidden;
            max-width: 100%;
            font-size: .58rem;
            font-weight: 760;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .hub-bottom-item.is-active {
            background: var(--ui-color-primary-soft);
            color: var(--ui-color-primary-strong);
        }

        .hub-bottom-item:focus-visible {
            outline: 2px solid var(--ui-color-primary-border);
            outline-offset: -2px;
        }

        @keyframes hub-screen-enter {
            from {
                opacity: 0;
                transform: translateY(4px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    }

    @media (max-width: 460px) {
        .hub-page-head-site span {
            display: none;
        }

        .hub-bottom-nav {
            gap: 0;
            padding-inline: .25rem;
        }

        .hub-bottom-item span {
            font-size: .55rem;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .hub-action-row.is-opening .ui-icon-box,
        .hub-screen.is-active {
            animation: none;
        }
    }
</style>

<main class="ui-page ui-scope hub-page" data-hub-root>
    <header
        class="ui-page-head"
        data-tone="primary"
        aria-labelledby="hub-title"
    >
        <div class="ui-page-head__start">
            <span class="hub-brand-logo" aria-hidden="true">
                <img
                    src="{{ !empty($currentTenant?->logo)
                        ? asset('storage/' . $currentTenant->logo)
                        : asset('assets/sgc-symbol.png') }}"
                    alt=""
                >
            </span>

            <div class="ui-page-head__copy">
                <h1 class="ui-page-head__title" id="hub-title">
                    {{ $isSystemContext ? 'SGC' : ($currentTenant?->name ?? 'Sua organização') }}
                </h1>

                <div class="ui-page-head__meta">
                    <span>
                        <i class="ph ph-user-circle" aria-hidden="true"></i>
                        {{ $displayName }}
                    </span>

                    <span>
                        <i class="ph ph-squares-four" aria-hidden="true"></i>
                        <strong class="hub-head-meta-count" data-visible-count>
                            {{ $availablePanelsCount }}
                        </strong>
                        {{ $availablePanelsCount === 1 ? 'portal disponível' : 'portais disponíveis' }}
                    </span>

                    @if($tenantLocation)
                        <span>
                            <i class="ph ph-map-pin" aria-hidden="true"></i>
                            {{ $tenantLocation }}
                        </span>
                    @endif
                </div>
            </div>
        </div>

        @if($tenantWebsite)
            <div class="ui-page-head__actions">
                <a
                    class="ui-btn ui-btn--sm hub-page-head-site"
                    data-tone="info"
                    href="{{ $tenantWebsite }}"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    <i class="ph ph-globe" aria-hidden="true"></i>
                    <span>Site oficial</span>
                </a>
            </div>
        @endif
    </header>

    <div class="ui-workspace-grid">
        <div class="ui-stack hub-main-stack">
            <section
                class="ui-section hub-screen is-active"
                id="hub-screen-portals"
                data-hub-screen="portals"
                aria-labelledby="hub-portals-title"
            >
                <header class="ui-section__header">
                    <div class="ui-section__heading">
                        <span
                            class="ui-icon-box"
                            data-tone="violet"
                            aria-hidden="true"
                        >
                            <i class="ph-fill ph-squares-four"></i>
                        </span>

                        <div class="ui-section__copy">
                            <h2 id="hub-portals-title">Portais de acesso</h2>
                            <p>Escolha a área de trabalho que deseja abrir.</p>
                        </div>
                    </div>

                    <div class="ui-section__actions">
                        <span class="ui-count" data-visible-count>
                            {{ $availablePanelsCount }}
                        </span>
                    </div>
                </header>

                <div class="ui-section__body">
                    @if($availablePanelsCount > 5)
                        <div class="ui-toolbar hub-portals-toolbar">
                            <div class="ui-toolbar__main">
                                <div class="hub-search" data-hub-search>
                                    <i class="ph ph-magnifying-glass" aria-hidden="true"></i>

                                    <label class="ui-sr-only" for="hub-panel-search">
                                        Buscar portal
                                    </label>

                                    <input
                                        id="hub-panel-search"
                                        class="ui-input hub-search-input"
                                        type="search"
                                        placeholder="Buscar portal"
                                        autocomplete="off"
                                        spellcheck="false"
                                        data-hub-search-input
                                    >

                                    <button
                                        class="ui-btn ui-btn--ghost ui-btn--icon ui-btn--sm hub-search-clear"
                                        type="button"
                                        aria-label="Limpar busca"
                                        data-hub-search-clear
                                    >
                                        <i class="ph ph-x" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endif

                    <nav
                        class="ui-action-list hub-action-list"
                        aria-label="Portais disponíveis"
                        data-hub-list
                    >
                        @if($hasSuperAdmin)
                            @php($superVisual = $resolvePortalVisual('Super Admin', 'shield', 'violet'))

                            <a
                                class="ui-action-row hub-action-row"
                                data-tone="{{ $superVisual['tone'] }}"
                                href="{{ url('super-admin') }}"
                                aria-label="Acessar portal Super Admin"
                                data-hub-link
                                data-panel-name="Super Admin"
                                data-panel-description="{{ $superVisual['hint'] }}"
                            >
                                <span
                                    class="ui-icon-box ui-icon-box--sm"
                                    aria-hidden="true"
                                >
                                    <i class="ph-fill {{ $superVisual['icon'] }}"></i>
                                </span>

                                <span class="ui-action-row__copy">
                                    <strong class="ui-action-row__title">
                                        Super Admin
                                    </strong>
                                    <span class="ui-action-row__description">
                                        {{ $superVisual['hint'] }}
                                    </span>
                                </span>

                                <span class="ui-action-row__action" aria-hidden="true">
                                    <i class="ph ph-arrow-right"></i>
                                </span>
                            </a>
                        @endif

                        @foreach($rolesCollection as $role)
                            <?php
                                $visual = $resolvePortalVisual(
                                    $role['name'],
                                    $role['icon'] ?? 'layout-dashboard',
                                    $role['color'] ?? 'primary'
                                );

                                $portalDescription = filled($role['description'] ?? null)
                                    ? $role['description']
                                    : $visual['hint'];
                            ?>

                            <a
                                class="ui-action-row hub-action-row"
                                data-tone="{{ $visual['tone'] }}"
                                href="{{ $role['url'] }}"
                                aria-label="Acessar portal {{ $role['name'] }}"
                                data-hub-link
                                data-panel-name="{{ $role['name'] }}"
                                data-panel-description="{{ $portalDescription }}"
                            >
                                <span
                                    class="ui-icon-box ui-icon-box--sm"
                                    aria-hidden="true"
                                >
                                    <i class="ph-fill {{ $visual['icon'] }}"></i>
                                </span>

                                <span class="ui-action-row__copy">
                                    <strong class="ui-action-row__title">
                                        {{ $role['name'] }}
                                    </strong>

                                    <span
                                        class="ui-action-row__description"
                                        title="{{ $portalDescription }}"
                                    >
                                        {{ $portalDescription }}
                                    </span>
                                </span>

                                <span class="ui-action-row__action" aria-hidden="true">
                                    <i class="ph ph-arrow-right"></i>
                                </span>
                            </a>
                        @endforeach

                        @if($availablePanelsCount === 0)
                            <div class="ui-state">
                                <div class="ui-state__content">
                                    <span
                                        class="ui-state__icon"
                                        data-tone="neutral"
                                        aria-hidden="true"
                                    >
                                        <i class="ph-fill ph-lock-key"></i>
                                    </span>

                                    <strong>Nenhum portal disponível</strong>
                                    <span>
                                        Solicite acesso a um administrador da organização.
                                    </span>
                                </div>
                            </div>
                        @endif

                        <div
                            class="ui-state hub-no-results"
                            role="status"
                            aria-live="polite"
                            hidden
                            data-hub-no-results
                        >
                            <div class="ui-state__content">
                                <span
                                    class="ui-state__icon"
                                    data-tone="neutral"
                                    aria-hidden="true"
                                >
                                    <i class="ph-fill ph-magnifying-glass"></i>
                                </span>

                                <strong>Nenhum portal encontrado</strong>
                                <span>Tente outro termo de busca.</span>
                            </div>
                        </div>
                    </nav>
                </div>
            </section>

            <section
                class="ui-section hub-screen"
                id="hub-screen-resources"
                data-hub-screen="resources"
                aria-labelledby="hub-resources-title"
            >
                <header class="ui-section__header">
                    <div class="ui-section__heading">
                        <span
                            class="ui-icon-box"
                            data-tone="cyan"
                            aria-hidden="true"
                        >
                            <i class="ph-fill ph-compass-tool"></i>
                        </span>

                        <div class="ui-section__copy">
                            <h2 id="hub-resources-title">Recursos</h2>
                            <p>Documentos, conta e serviços complementares.</p>
                        </div>
                    </div>

                    <div class="ui-section__actions">
                        <span class="ui-count">{{ $hubResources->count() }}</span>
                    </div>
                </header>

                <div class="ui-section__body">
                    @if($hubResources->isNotEmpty())
                        <nav class="ui-action-list" aria-label="Recursos disponíveis">
                            @foreach($hubResources as $resource)
                                @php($resourceTone = $normalizeUiTone($resource['tone'] ?? 'neutral'))

                                <a
                                    class="ui-action-row"
                                    data-tone="{{ $resourceTone }}"
                                    href="{{ $resource['url'] }}"
                                >
                                    <span
                                        class="ui-icon-box ui-icon-box--sm"
                                        aria-hidden="true"
                                    >
                                        <i class="ph-fill {{ $resource['icon'] }}"></i>
                                    </span>

                                    <span class="ui-action-row__copy">
                                        <strong class="ui-action-row__title">
                                            {{ $resource['label'] }}
                                        </strong>

                                        <span class="ui-action-row__description">
                                            {{ $resource['description'] }}
                                        </span>
                                    </span>

                                    <span class="ui-action-row__action" aria-hidden="true">
                                        <i class="ph ph-caret-right"></i>
                                    </span>
                                </a>
                            @endforeach
                        </nav>
                    @else
                        <div class="ui-state">
                            <div class="ui-state__content">
                                <span
                                    class="ui-state__icon"
                                    data-tone="neutral"
                                    aria-hidden="true"
                                >
                                    <i class="ph-fill ph-compass-tool"></i>
                                </span>

                                <strong>Nenhum recurso adicional</strong>
                                <span>
                                    A organização ainda não disponibilizou outros recursos.
                                </span>
                            </div>
                        </div>
                    @endif
                </div>
            </section>
        </div>

        <aside class="ui-stack hub-side-stack" aria-label="Informações da organização">
            <section
                class="ui-section ui-section--flat hub-screen"
                id="hub-screen-news"
                data-hub-screen="news"
                aria-labelledby="hub-news-title"
            >
                <header class="ui-section__header">
                    <div class="ui-section__heading">
                        <span
                            class="ui-icon-box"
                            data-tone="warning"
                            aria-hidden="true"
                        >
                            <i class="ph-fill ph-megaphone-simple"></i>
                        </span>

                        <div class="ui-section__copy">
                            <h2 id="hub-news-title">Novidades</h2>
                            <p>Atualizações e informações em destaque.</p>
                        </div>
                    </div>
                </header>

                <div class="ui-section__body">
                    <div class="hub-news-list">
                        @foreach($hubNews as $news)
                            @php($newsTone = $normalizeUiTone($news['tone'] ?? 'info'))

                            <article
                                class="hub-news-row"
                                data-tone="{{ $newsTone }}"
                            >
                                <span
                                    class="ui-icon-box ui-icon-box--sm"
                                    aria-hidden="true"
                                >
                                    <i class="ph-fill {{ $news['icon'] }}"></i>
                                </span>

                                <div class="hub-news-copy">
                                    <span class="hub-news-label">
                                        {{ $news['label'] }}
                                    </span>

                                    <strong class="hub-news-title">
                                        {{ $news['title'] }}
                                    </strong>

                                    @if(filled($news['description']))
                                        <span class="hub-news-description">
                                            {{ $news['description'] }}
                                        </span>
                                    @endif

                                    @if(filled($news['url']))
                                        <a
                                            class="ui-btn ui-btn--ghost ui-btn--sm hub-news-action"
                                            data-tone="{{ $newsTone }}"
                                            href="{{ $news['url'] }}"
                                        >
                                            Saiba mais
                                            <i class="ph ph-arrow-right" aria-hidden="true"></i>
                                        </a>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>

            <section
                class="ui-section ui-section--flat hub-screen"
                id="hub-screen-contact"
                data-hub-screen="contact"
                aria-labelledby="hub-contact-title"
            >
                <header class="ui-section__header">
                    <div class="ui-section__heading">
                        <span
                            class="ui-icon-box"
                            data-tone="primary"
                            aria-hidden="true"
                        >
                            <i class="ph-fill ph-chats-circle"></i>
                        </span>

                        <div class="ui-section__copy">
                            <h2 id="hub-contact-title">Contato</h2>
                            <p>Informações e canais oficiais.</p>
                        </div>
                    </div>
                </header>

                <div class="ui-section__body">
                    <article class="ui-card hub-org-card">
                        <div class="hub-org-main">
                            <span class="hub-brand-logo" aria-hidden="true">
                                <img
                                    src="{{ !empty($currentTenant?->logo)
                                        ? asset('storage/' . $currentTenant->logo)
                                        : asset('assets/sgc-symbol.png') }}"
                                    alt=""
                                >
                            </span>

                            <div class="hub-org-copy">
                                <strong>
                                    {{ $currentTenant->name ?? 'Sua organização' }}
                                </strong>

                                <span>
                                    {{ $tenantDescription }}
                                </span>
                            </div>
                        </div>

                        @if($tenantDocument || $tenantLocation)
                            <div class="hub-org-facts">
                                @if($tenantDocument)
                                    <span
                                        class="ui-badge ui-badge--outline"
                                        data-tone="neutral"
                                    >
                                        <i class="ph ph-identification-card" aria-hidden="true"></i>
                                        {{ $tenantDocument }}
                                    </span>
                                @endif

                                @if($tenantLocation)
                                    <span
                                        class="ui-badge ui-badge--outline"
                                        data-tone="neutral"
                                    >
                                        <i class="ph ph-map-pin" aria-hidden="true"></i>
                                        {{ $tenantLocation }}
                                    </span>
                                @endif
                            </div>
                        @endif
                    </article>

                    @if($tenantWhatsappUrl || $tenantPhoneUrl || $tenantEmailUrl || $tenantWebsite)
                        <nav class="ui-action-list" aria-label="Canais oficiais">
                            @if($tenantWhatsappUrl)
                                <a
                                    class="ui-action-row"
                                    data-tone="success"
                                    href="{{ $tenantWhatsappUrl }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <span
                                        class="ui-icon-box ui-icon-box--sm"
                                        aria-hidden="true"
                                    >
                                        <i class="ph-fill ph-whatsapp-logo"></i>
                                    </span>

                                    <span class="ui-action-row__copy">
                                        <strong class="ui-action-row__title">WhatsApp</strong>
                                        <span class="ui-action-row__description">
                                            {{ $tenantWhatsapp }}
                                        </span>
                                    </span>

                                    <span class="ui-action-row__action" aria-hidden="true">
                                        <i class="ph ph-arrow-square-out"></i>
                                    </span>
                                </a>
                            @endif

                            @if($tenantPhoneUrl)
                                <a
                                    class="ui-action-row"
                                    data-tone="info"
                                    href="{{ $tenantPhoneUrl }}"
                                >
                                    <span
                                        class="ui-icon-box ui-icon-box--sm"
                                        aria-hidden="true"
                                    >
                                        <i class="ph-fill ph-phone"></i>
                                    </span>

                                    <span class="ui-action-row__copy">
                                        <strong class="ui-action-row__title">Telefone</strong>
                                        <span class="ui-action-row__description">
                                            {{ $tenantPhone }}
                                        </span>
                                    </span>

                                    <span class="ui-action-row__action" aria-hidden="true">
                                        <i class="ph ph-caret-right"></i>
                                    </span>
                                </a>
                            @endif

                            @if($tenantEmailUrl)
                                <a
                                    class="ui-action-row"
                                    data-tone="violet"
                                    href="{{ $tenantEmailUrl }}"
                                >
                                    <span
                                        class="ui-icon-box ui-icon-box--sm"
                                        aria-hidden="true"
                                    >
                                        <i class="ph-fill ph-envelope-simple"></i>
                                    </span>

                                    <span class="ui-action-row__copy">
                                        <strong class="ui-action-row__title">E-mail</strong>
                                        <span class="ui-action-row__description">
                                            {{ $tenantEmail }}
                                        </span>
                                    </span>

                                    <span class="ui-action-row__action" aria-hidden="true">
                                        <i class="ph ph-caret-right"></i>
                                    </span>
                                </a>
                            @endif

                            @if($tenantWebsite)
                                <a
                                    class="ui-action-row"
                                    data-tone="cyan"
                                    href="{{ $tenantWebsite }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <span
                                        class="ui-icon-box ui-icon-box--sm"
                                        aria-hidden="true"
                                    >
                                        <i class="ph-fill ph-globe"></i>
                                    </span>

                                    <span class="ui-action-row__copy">
                                        <strong class="ui-action-row__title">Site oficial</strong>
                                        <span class="ui-action-row__description">
                                            {{ parse_url($tenantWebsite, PHP_URL_HOST) ?: $tenantWebsite }}
                                        </span>
                                    </span>

                                    <span class="ui-action-row__action" aria-hidden="true">
                                        <i class="ph ph-arrow-square-out"></i>
                                    </span>
                                </a>
                            @endif
                        </nav>
                    @else
                        <div class="ui-state">
                            <div class="ui-state__content">
                                <span
                                    class="ui-state__icon"
                                    data-tone="neutral"
                                    aria-hidden="true"
                                >
                                    <i class="ph-fill ph-chats-circle"></i>
                                </span>

                                <strong>Canais ainda não informados</strong>
                                <span>
                                    A organização ainda não cadastrou canais oficiais.
                                </span>
                            </div>
                        </div>
                    @endif

                    @if($tenantSocials->isNotEmpty())
                        <div class="hub-socials" aria-label="Redes sociais">
                            @foreach($tenantSocials as $social)
                                <a
                                    class="ui-btn ui-btn--sm"
                                    data-tone="info"
                                    href="{{ $social['url'] }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    aria-label="{{ $social['label'] }}"
                                >
                                    <i class="ph {{ $social['icon'] }}" aria-hidden="true"></i>
                                    {{ $social['label'] }}
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>
        </aside>
    </div>
</main>

<nav
    class="hub-bottom-nav"
    aria-label="Navegação do Hub"
    data-hub-bottom-nav
>
    <button
        class="hub-bottom-item is-active"
        type="button"
        data-hub-screen-target="portals"
        aria-current="page"
    >
        <i class="ph-fill ph-squares-four" aria-hidden="true"></i>
        <span>Portais</span>
    </button>

    <button
        class="hub-bottom-item"
        type="button"
        data-hub-screen-target="news"
    >
        <i class="ph-fill ph-megaphone-simple" aria-hidden="true"></i>
        <span>Novidades</span>
    </button>

    <button
        class="hub-bottom-item"
        type="button"
        data-hub-screen-target="resources"
    >
        <i class="ph-fill ph-compass-tool" aria-hidden="true"></i>
        <span>Recursos</span>
    </button>

    <button
        class="hub-bottom-item"
        type="button"
        data-hub-screen-target="contact"
    >
        <i class="ph-fill ph-chats-circle" aria-hidden="true"></i>
        <span>Contato</span>
    </button>
</nav>

<script>
(() => {
    'use strict';

    const root = document.querySelector('[data-hub-root]');
    if (!root) return;

    document.body.classList.add('hub-app-navigation');

    const MOBILE_QUERY = '(max-width: 820px)';
    const SCREEN_STATE_KEY = '__sgcHubScreen';

    const screenNames = new Set([
        'portals',
        'news',
        'resources',
        'contact',
    ]);

    const screens = [
        ...root.querySelectorAll('[data-hub-screen]')
    ];

    const navItems = [
        ...document.querySelectorAll('[data-hub-screen-target]')
    ];

    const isMobile = () =>
        window.matchMedia(MOBILE_QUERY).matches;

    function setScreen(
        name,
        {
            push = false,
            resetScroll = true,
        } = {}
    ) {
        const next =
            screenNames.has(name)
                ? name
                : 'portals';

        screens.forEach(screen => {
            const active =
                screen.dataset.hubScreen
                === next;

            if (isMobile()) {
                screen.classList.toggle(
                    'is-active',
                    active
                );

                screen.hidden =
                    !active;
            } else {
                screen.hidden =
                    false;

                screen.classList.add(
                    'is-active'
                );
            }
        });

        navItems.forEach(item => {
            const active =
                item.dataset.hubScreenTarget
                === next;

            item.classList.toggle(
                'is-active',
                active
            );

            if (active) {
                item.setAttribute(
                    'aria-current',
                    'page'
                );
            } else {
                item.removeAttribute(
                    'aria-current'
                );
            }
        });

        if (
            push
            && isMobile()
            && history.state?.[SCREEN_STATE_KEY]
                !== next
        ) {
            history.pushState(
                {
                    ...(history.state || {}),
                    [SCREEN_STATE_KEY]: next,
                },
                ''
            );
        }

        if (
            isMobile()
            && resetScroll
        ) {
            window.scrollTo({
                top: 0,
                behavior: 'auto',
            });
        }
    }

    function ensureScreenHistory() {
        if (!isMobile()) {
            return;
        }

        const current =
            history.state
                ?.[SCREEN_STATE_KEY];

        if (
            !screenNames.has(current)
        ) {
            history.replaceState(
                {
                    ...(history.state || {}),
                    [SCREEN_STATE_KEY]:
                        'portals',
                },
                ''
            );
        }
    }

    navItems.forEach(item => {
        item.addEventListener(
            'click',
            () => {
                const next =
                    item.dataset
                        .hubScreenTarget;

                const current =
                    history.state
                        ?.[SCREEN_STATE_KEY]
                    || 'portals';

                if (
                    next === current
                    && isMobile()
                ) {
                    return;
                }

                setScreen(
                    next,
                    {
                        push: true,
                        resetScroll: true,
                    }
                );
            }
        );
    });

    window.addEventListener(
        'popstate',
        event => {
            if (!isMobile()) {
                return;
            }

            setScreen(
                event.state
                    ?.[SCREEN_STATE_KEY]
                || 'portals',
                {
                    push: false,
                    resetScroll: true,
                }
            );
        }
    );

    window.addEventListener(
        'resize',
        () => {
            if (isMobile()) {
                ensureScreenHistory();

                setScreen(
                    history.state
                        ?.[SCREEN_STATE_KEY]
                    || 'portals',
                    {
                        push: false,
                        resetScroll: false,
                    }
                );
            } else {
                screens.forEach(screen => {
                    screen.hidden =
                        false;

                    screen.classList.add(
                        'is-active'
                    );
                });
            }
        },
        {
            passive: true,
        }
    );

    const portalLinks = [
        ...root.querySelectorAll(
            '[data-hub-link]'
        )
    ];

    const search =
        root.querySelector(
            '[data-hub-search]'
        );

    const searchInput =
        root.querySelector(
            '[data-hub-search-input]'
        );

    const searchClear =
        root.querySelector(
            '[data-hub-search-clear]'
        );

    const noResults =
        root.querySelector(
            '[data-hub-no-results]'
        );

    const visibleCounts = [
        ...root.querySelectorAll(
            '[data-visible-count]'
        )
    ];

    const normalize =
        value =>
            String(value || '')
                .normalize('NFD')
                .replace(
                    /[\u0300-\u036f]/g,
                    ''
                )
                .toLocaleLowerCase(
                    'pt-BR'
                )
                .trim();

    function filterPortals() {
        if (!searchInput) {
            return;
        }

        const query =
            normalize(
                searchInput.value
            );

        let matches = 0;

        portalLinks.forEach(link => {
            const text =
                normalize(
                    `${
                        link.dataset
                            .panelName
                        || ''
                    } ${
                        link.dataset
                            .panelDescription
                        || ''
                    }`
                );

            const match =
                !query
                || text.includes(query);

            link.hidden =
                !match;

            if (match) {
                matches += 1;
            }
        });

        search?.classList.toggle(
            'has-value',
            Boolean(
                searchInput.value
            )
        );

        if (noResults) {
            noResults.hidden =
                matches !== 0;
        }

        visibleCounts.forEach(
            counter => {
                counter.textContent =
                    String(matches);
            }
        );
    }

    searchInput?.addEventListener(
        'input',
        filterPortals
    );

    searchClear?.addEventListener(
        'click',
        () => {
            searchInput.value =
                '';

            filterPortals();

            if (!isMobile()) {
                searchInput.focus();
            } else {
                searchInput.blur();
            }
        }
    );

    function resetPortalOpeningState() {
        portalLinks.forEach(link => {
            link.classList.remove(
                'is-opening'
            );

            link.removeAttribute(
                'aria-busy'
            );
        });
    }

    portalLinks.forEach(link => {
        link.addEventListener(
            'click',
            event => {
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

                event.preventDefault();

                resetPortalOpeningState();

                link.classList.add(
                    'is-opening'
                );

                link.setAttribute(
                    'aria-busy',
                    'true'
                );

                const href =
                    link.href;

                const reducedMotion =
                    window.matchMedia(
                        '(prefers-reduced-motion: reduce)'
                    ).matches;

                const delay =
                    reducedMotion
                        ? 40
                        : 160;

                window.setTimeout(
                    () => {
                        window.location.assign(
                            href
                        );
                    },
                    delay
                );
            }
        );
    });

    ensureScreenHistory();

    setScreen(
        isMobile()
            ? (
                history.state
                    ?.[SCREEN_STATE_KEY]
                || 'portals'
            )
            : 'portals',
        {
            push: false,
            resetScroll: false,
        }
    );

    filterPortals();

    window.addEventListener(
        'pageshow',
        () => {
            resetPortalOpeningState();
            filterPortals();

            if (isMobile()) {
                setScreen(
                    history.state
                        ?.[SCREEN_STATE_KEY]
                    || 'portals',
                    {
                        push: false,
                        resetScroll: false,
                    }
                );
            } else {
                screens.forEach(screen => {
                    screen.hidden =
                        false;

                    screen.classList.add(
                        'is-active'
                    );
                });
            }
        }
    );
})();
</script>
@endsection