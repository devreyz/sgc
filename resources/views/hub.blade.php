@extends('layouts.bento')

@section('title', 'Central da organização')

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
    ]) ?: 'Central de serviços, comunicação e acesso da organização.';

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

@section('page-title', 'Central da organização')

@section('page-subtitle', ($currentTenant->name ?? 'Sua organização') . ' · Serviços, comunicação e áreas de trabalho.')
@section('user-role', 'Hub institucional')

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
    /* =========================================================
       HUB INSTITUCIONAL — CENTRAL, LATERAIS E DESTAQUES
       ========================================================= */

    .hub-page {
        width: min(100%, calc(100dvw - 32px), 1500px);
    }

    .hub-home {
        display: grid;
        min-width: 0;
        gap: 14px;
    }

    .hub-hero,
    .hub-panel {
        min-width: 0;
        border: 1px solid var(--color-border, #dce6df);
        background: #fff;
        box-shadow: 0 3px 14px rgba(15, 35, 24, .045);
    }

    .hub-hero {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: 14px;
        align-items: center;
        padding: 16px;
        overflow: hidden;
        border-radius: 17px;
        background:
            radial-gradient(circle at 96% 0, rgba(124, 58, 237, .055), transparent 260px),
            linear-gradient(180deg, #fff, #fbfdfc);
    }

    .hub-hero-logo {
        display: grid;
        width: 58px;
        height: 58px;
        place-items: center;
        overflow: hidden;
        border: 1px solid rgba(22, 138, 77, .12);
        border-radius: 15px;
        background: var(--hub-green-soft);
    }

    .hub-hero-logo img {
        width: 38px;
        height: 38px;
        object-fit: contain;
    }

    .hub-eyebrow {
        display: flex;
        align-items: center;
        gap: 6px;
        margin: 0 0 3px;
        color: var(--hub-green);
        font-size: 10px;
        font-weight: 820;
        letter-spacing: .06em;
        text-transform: uppercase;
    }

    .hub-eyebrow::before {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: var(--hub-green);
        box-shadow: 0 0 0 4px var(--hub-green-soft);
        content: "";
    }

    .hub-hero h2,
    .hub-hero p {
        margin: 0;
    }

    .hub-hero h2 {
        color: var(--color-text, #102018);
        font-size: clamp(16px, 2vw, 20px);
        font-weight: 880;
        letter-spacing: -.025em;
        line-height: 1.25;
    }

    .hub-hero p {
        max-width: 72ch;
        margin-top: 4px;
        color: var(--color-text-secondary, #52645a);
        font-size: 11px;
        line-height: 1.55;
    }

    .hub-hero-meta {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
        margin-top: 9px;
    }

    .hub-chip {
        display: inline-flex;
        min-height: 25px;
        align-items: center;
        gap: 5px;
        padding: 4px 8px;
        border: 1px solid var(--color-border, #dce6df);
        border-radius: 999px;
        background: #fff;
        color: var(--color-text-secondary, #52645a);
        font-size: 10px;
        font-weight: 720;
    }

    .hub-chip i {
        color: var(--hub-blue);
        font-size: 12px;
    }

    .hub-hero-action {
        display: inline-flex;
        min-height: 38px;
        align-items: center;
        justify-content: center;
        gap: 7px;
        padding: 8px 11px;
        border: 1px solid var(--color-border-strong, #c8d6cd);
        border-radius: 10px;
        background: #fff;
        color: var(--color-text, #102018);
        font-size: 11px;
        font-weight: 780;
        text-decoration: none;
    }

    .hub-hero-action i {
        color: var(--hub-blue);
        font-size: 14px;
    }

    .hub-hero-action:hover,
    .hub-hero-action:focus-visible {
        border-color: rgba(37, 99, 235, .24);
        background: var(--hub-blue-soft);
        color: var(--color-text, #102018);
        outline: none;
    }

    .hub-layout {
        display: grid;
        min-width: 0;
        grid-template-columns: 244px minmax(440px, 1fr) 304px;
        grid-template-areas: "left main right";
        gap: 14px;
        align-items: start;
    }

    .hub-main-column { grid-area: main; }
    .hub-left-column { grid-area: left; }
    .hub-right-column { grid-area: right; }

    .hub-left-column,
    .hub-right-column {
        display: grid;
        min-width: 0;
        gap: 12px;
    }

    .hub-panel {
        overflow: hidden;
        border-radius: 15px;
    }

    .hub-panel-head {
        display: flex;
        min-width: 0;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        min-height: 54px;
        padding: 10px 12px;
        border-bottom: 1px solid var(--color-border, #dce6df);
        background: linear-gradient(180deg, var(--color-surface-soft, #f8faf9), #fff);
    }

    .hub-panel-title {
        display: flex;
        min-width: 0;
        align-items: center;
        gap: 9px;
    }

    .hub-panel-icon,
    .hub-tone-icon {
        --item-tone: var(--hub-slate);
        --item-soft: var(--hub-slate-soft);
        display: grid;
        flex: none;
        place-items: center;
        background: var(--item-soft);
        color: var(--item-tone);
    }

    .hub-panel-icon {
        width: 34px;
        height: 34px;
        border-radius: 10px;
    }

    .hub-tone-icon {
        width: 38px;
        height: 38px;
        border-radius: 11px;
    }

    .tone-green { --item-tone: var(--hub-green); --item-soft: var(--hub-green-soft); }
    .tone-blue { --item-tone: var(--hub-blue); --item-soft: var(--hub-blue-soft); }
    .tone-sky { --item-tone: var(--hub-sky); --item-soft: var(--hub-sky-soft); }
    .tone-violet { --item-tone: var(--hub-violet); --item-soft: var(--hub-violet-soft); }
    .tone-amber { --item-tone: var(--hub-amber); --item-soft: var(--hub-amber-soft); }
    .tone-red { --item-tone: var(--hub-red); --item-soft: var(--hub-red-soft); }
    .tone-slate { --item-tone: var(--hub-slate); --item-soft: var(--hub-slate-soft); }

    .hub-panel-title h3,
    .hub-panel-title p {
        margin: 0;
    }

    .hub-panel-title h3 {
        color: var(--color-text, #102018);
        font-size: 13px;
        font-weight: 840;
        line-height: 1.25;
    }

    .hub-panel-title p {
        margin-top: 2px;
        color: var(--color-text-muted, #809087);
        font-size: 10px;
        line-height: 1.35;
    }

    .hub-count {
        min-width: 28px;
        padding: 4px 7px;
        border-radius: 999px;
        background: var(--color-surface-muted, #eef4f0);
        color: var(--color-text-secondary, #52645a);
        font-size: 10px;
        font-weight: 800;
        text-align: center;
    }

    .hub-portal-tools {
        padding: 10px 11px 0;
    }

    .hub-portal-tools .hub-search {
        width: 100%;
        max-width: none;
    }

    .hub-portal-tools .hub-search-input {
        background: #fff;
    }

    .hub-portal-body {
        padding: 11px;
    }

    .hub-main-column .hub-list {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 9px;
    }

    .hub-main-column .hub-link {
        min-height: 86px;
        gap: 11px;
        padding: 12px 40px 12px 12px;
        border-radius: 12px;
        background: #fff;
        box-shadow: none;
    }

    .hub-main-column .hub-link:hover,
    .hub-main-column .hub-link:focus-visible {
        background: var(--color-surface-soft, #f8faf9);
        box-shadow: 0 6px 16px rgba(15, 35, 24, .055);
        transform: translateY(-1px);
    }

    .hub-main-column .hub-role-icon {
        width: 42px;
        height: 42px;
        border-radius: 11px;
    }

    .hub-main-column .hub-link-arrow {
        right: 8px;
        background: transparent !important;
    }

    .hub-tenant-body {
        padding: 13px;
    }

    .hub-tenant-brand {
        display: flex;
        min-width: 0;
        align-items: center;
        gap: 10px;
    }

    .hub-tenant-avatar {
        display: grid;
        width: 46px;
        height: 46px;
        flex: none;
        place-items: center;
        overflow: hidden;
        border: 1px solid rgba(22, 138, 77, .12);
        border-radius: 12px;
        background: var(--hub-green-soft);
    }

    .hub-tenant-avatar img {
        width: 31px;
        height: 31px;
        object-fit: contain;
    }

    .hub-tenant-brand strong,
    .hub-tenant-brand span {
        display: block;
    }

    .hub-tenant-brand strong {
        color: var(--color-text, #102018);
        font-size: 12px;
        font-weight: 840;
        line-height: 1.3;
    }

    .hub-tenant-brand span {
        margin-top: 2px;
        color: var(--color-text-muted, #809087);
        font-size: 10px;
    }

    .hub-tenant-description {
        margin: 11px 0 0;
        color: var(--color-text-secondary, #52645a);
        font-size: 10.5px;
        line-height: 1.55;
    }

    .hub-facts {
        display: grid;
        gap: 0;
        margin-top: 11px;
        border-top: 1px solid var(--color-border, #dce6df);
    }

    .hub-fact {
        display: grid;
        min-width: 0;
        grid-template-columns: 26px minmax(0, 1fr);
        gap: 7px;
        align-items: center;
        padding: 8px 0;
        border-bottom: 1px solid var(--color-border, #dce6df);
    }

    .hub-fact:last-child { border-bottom: 0; padding-bottom: 0; }

    .hub-fact > i {
        color: var(--hub-blue);
        font-size: 15px;
        text-align: center;
    }

    .hub-fact span,
    .hub-fact strong {
        display: block;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .hub-fact span {
        color: var(--color-text-muted, #809087);
        font-size: 9px;
    }

    .hub-fact strong {
        margin-top: 1px;
        color: var(--color-text-secondary, #52645a);
        font-size: 10.5px;
        font-weight: 760;
    }

    .hub-resource-list,
    .hub-contact-list {
        display: grid;
        min-width: 0;
        gap: 6px;
        padding: 9px;
    }

    .hub-resource-link,
    .hub-contact-link {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: 8px;
        align-items: center;
        min-height: 48px;
        padding: 6px 7px;
        border: 1px solid transparent;
        border-radius: 10px;
        background: transparent;
        color: inherit;
        text-decoration: none;
    }

    .hub-resource-link:hover,
    .hub-resource-link:focus-visible,
    .hub-contact-link:hover,
    .hub-contact-link:focus-visible {
        border-color: var(--color-border, #dce6df);
        background: var(--color-surface-soft, #f8faf9);
        color: inherit;
        outline: none;
    }

    .hub-resource-link .hub-tone-icon,
    .hub-contact-link .hub-tone-icon {
        width: 34px;
        height: 34px;
        border-radius: 9px;
        font-size: 15px;
    }

    .hub-resource-copy,
    .hub-contact-copy {
        min-width: 0;
    }

    .hub-resource-copy strong,
    .hub-resource-copy span,
    .hub-contact-copy strong,
    .hub-contact-copy span {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .hub-resource-copy strong,
    .hub-contact-copy strong {
        color: var(--color-text, #102018);
        font-size: 10.5px;
        font-weight: 790;
    }

    .hub-resource-copy span,
    .hub-contact-copy span {
        margin-top: 1px;
        color: var(--color-text-muted, #809087);
        font-size: 9px;
    }

    .hub-resource-link > .ph-caret-right,
    .hub-contact-link > .ph-caret-right {
        color: var(--color-text-muted, #809087);
        font-size: 12px;
    }

    .hub-carousel-controls {
        display: flex;
        gap: 5px;
        align-items: center;
    }

    .hub-carousel-button {
        display: grid;
        width: 29px;
        height: 29px;
        place-items: center;
        border: 1px solid var(--color-border, #dce6df);
        border-radius: 8px;
        background: #fff;
        color: var(--color-text-secondary, #52645a);
        cursor: pointer;
    }

    .hub-carousel-button:hover:not(:disabled),
    .hub-carousel-button:focus-visible:not(:disabled) {
        background: var(--color-surface-muted, #eef4f0);
        color: var(--color-text, #102018);
        outline: none;
    }

    .hub-carousel-button:disabled {
        cursor: default;
        opacity: .38;
    }

    .hub-news-viewport {
        min-width: 0;
        overflow: hidden;
    }

    .hub-news-track {
        display: flex;
        min-width: 0;
        gap: 8px;
        padding: 10px;
        overflow-x: auto;
        scroll-behavior: smooth;
        scroll-snap-type: x mandatory;
        scrollbar-width: none;
        overscroll-behavior-inline: contain;
    }

    .hub-news-track::-webkit-scrollbar { display: none; }

    .hub-news-slide {
        --news-tone: var(--hub-blue);
        --news-soft: var(--hub-blue-soft);
        display: grid;
        min-width: 0;
        min-height: 175px;
        flex: 0 0 100%;
        align-content: space-between;
        gap: 15px;
        padding: 13px;
        border: 1px solid var(--color-border, #dce6df);
        border-radius: 12px;
        background:
            radial-gradient(circle at 100% 0, color-mix(in srgb, var(--news-tone) 7%, transparent), transparent 150px),
            #fff;
        scroll-snap-align: start;
    }

    .hub-news-slide.tone-green { --news-tone: var(--hub-green); --news-soft: var(--hub-green-soft); }
    .hub-news-slide.tone-blue { --news-tone: var(--hub-blue); --news-soft: var(--hub-blue-soft); }
    .hub-news-slide.tone-sky { --news-tone: var(--hub-sky); --news-soft: var(--hub-sky-soft); }
    .hub-news-slide.tone-violet { --news-tone: var(--hub-violet); --news-soft: var(--hub-violet-soft); }
    .hub-news-slide.tone-amber { --news-tone: var(--hub-amber); --news-soft: var(--hub-amber-soft); }
    .hub-news-slide.tone-red { --news-tone: var(--hub-red); --news-soft: var(--hub-red-soft); }
    .hub-news-slide.tone-slate { --news-tone: var(--hub-slate); --news-soft: var(--hub-slate-soft); }

    .hub-news-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 8px;
    }

    .hub-news-icon {
        display: grid;
        width: 39px;
        height: 39px;
        place-items: center;
        border-radius: 11px;
        background: var(--news-soft);
        color: var(--news-tone);
        font-size: 17px;
    }

    .hub-news-label {
        display: inline-flex;
        min-height: 23px;
        align-items: center;
        padding: 4px 7px;
        border-radius: 999px;
        background: var(--news-soft);
        color: var(--news-tone);
        font-size: 9px;
        font-weight: 800;
    }

    .hub-news-slide h4,
    .hub-news-slide p {
        margin: 0;
    }

    .hub-news-slide h4 {
        color: var(--color-text, #102018);
        font-size: 13px;
        font-weight: 840;
        line-height: 1.35;
    }

    .hub-news-slide p {
        margin-top: 5px;
        color: var(--color-text-secondary, #52645a);
        font-size: 10px;
        line-height: 1.55;
    }

    .hub-news-action {
        display: inline-flex;
        width: max-content;
        align-items: center;
        gap: 5px;
        color: var(--news-tone);
        font-size: 10px;
        font-weight: 790;
        text-decoration: none;
    }

    .hub-news-action:hover,
    .hub-news-action:focus-visible {
        text-decoration: underline;
        outline: none;
    }

    .hub-carousel-dots {
        display: flex;
        justify-content: center;
        gap: 5px;
        padding: 0 10px 10px;
    }

    .hub-carousel-dot {
        width: 6px;
        height: 6px;
        padding: 0;
        border: 0;
        border-radius: 999px;
        background: #cbd5cf;
        cursor: pointer;
        transition: width 150ms ease, background 150ms ease;
    }

    .hub-carousel-dot.active {
        width: 18px;
        background: var(--hub-violet);
    }

    .hub-contact-empty {
        margin: 0;
        padding: 13px;
        color: var(--color-text-secondary, #52645a);
        font-size: 10px;
        line-height: 1.55;
    }

    .hub-socials {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
        padding: 0 9px 10px;
    }

    .hub-social-link {
        display: inline-flex;
        min-height: 31px;
        align-items: center;
        gap: 5px;
        padding: 5px 8px;
        border: 1px solid var(--color-border, #dce6df);
        border-radius: 9px;
        background: #fff;
        color: var(--color-text-secondary, #52645a);
        font-size: 9px;
        font-weight: 740;
        text-decoration: none;
    }

    .hub-social-link i {
        color: var(--hub-violet);
        font-size: 14px;
    }

    .hub-social-link:hover,
    .hub-social-link:focus-visible {
        background: var(--color-surface-soft, #f8faf9);
        color: var(--color-text, #102018);
        outline: none;
    }

    @media (min-width: 1181px) {
        .hub-left-column,
        .hub-right-column {
            position: sticky;
            top: 12px;
        }
    }

    @media (max-width: 1180px) {
        .hub-layout {
            grid-template-columns: minmax(0, 1fr) 300px;
            grid-template-areas:
                "main right"
                "left right";
        }

        .hub-left-column {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .hub-right-column {
            position: sticky;
            top: 12px;
        }
    }

    @media (max-width: 820px) {
        .hub-page {
            width: min(100%, calc(100dvw - 20px));
        }

        .hub-hero {
            grid-template-columns: auto minmax(0, 1fr);
            padding: 13px;
        }

        .hub-hero-action {
            grid-column: 1 / -1;
            justify-self: stretch;
        }

        .hub-layout {
            display: flex;
            flex-direction: column;
        }

        .hub-main-column { order: 1; width: 100%; }
        .hub-right-column { position: static; order: 2; width: 100%; }
        .hub-left-column { order: 3; width: 100%; }

        .hub-left-column {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .hub-news-slide {
            min-height: 160px;
            flex-basis: calc(100% - 28px);
        }
    }

    @media (max-width: 620px) {
        .hub-home { gap: 10px; }

        .hub-hero-logo {
            width: 48px;
            height: 48px;
            border-radius: 13px;
        }

        .hub-hero-logo img {
            width: 31px;
            height: 31px;
        }

        .hub-hero p {
            display: -webkit-box;
            overflow: hidden;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
        }

        .hub-chip {
            min-height: 23px;
            font-size: 9px;
        }

        .hub-main-column .hub-list,
        .hub-left-column {
            grid-template-columns: 1fr;
        }

        .hub-main-column .hub-link {
            min-height: 72px;
            padding: 10px 38px 10px 10px;
        }

        .hub-main-column .hub-role-icon {
            width: 40px;
            height: 40px;
        }

        .hub-panel-head {
            min-height: 51px;
            padding: 9px 10px;
        }

        .hub-panel-title p {
            display: none;
        }

        .hub-news-track {
            padding: 8px;
        }

        .hub-news-slide {
            min-height: 154px;
            padding: 11px;
        }
    }

    /* =========================================================
       REFINAMENTO PREMIUM — HIERARQUIA, SUPERFÍCIES E NAVEGAÇÃO
       ========================================================= */

    .hub-page {
        --hub-nav-height: 58px;
        --hub-premium-ink: #17352a;
        --hub-premium-border: #dce8e1;
        --hub-premium-surface: #f7faf8;
    }

    .hub-home {
        gap: 16px;
    }

    .hub-hero {
        position: relative;
        padding: 18px;
        border-color: rgba(22, 138, 77, .16);
        border-radius: 20px;
        background:
            radial-gradient(circle at 88% -30%, rgba(124, 58, 237, .11), transparent 310px),
            radial-gradient(circle at 8% 120%, rgba(22, 138, 77, .10), transparent 280px),
            linear-gradient(145deg, #ffffff 0%, #f9fcfa 58%, #f7f5ff 100%);
        box-shadow:
            0 14px 38px rgba(17, 49, 34, .07),
            0 2px 7px rgba(17, 49, 34, .035);
    }

    .hub-hero::after {
        position: absolute;
        inset: 0 auto 0 0;
        width: 4px;
        border-radius: 20px 0 0 20px;
        background: linear-gradient(180deg, var(--hub-green), var(--hub-violet));
        content: "";
    }

    .hub-hero-logo {
        border-color: rgba(22, 138, 77, .16);
        background: rgba(255, 255, 255, .88);
        box-shadow: inset 0 0 0 5px var(--hub-green-soft);
    }

    .hub-chip {
        border-color: rgba(23, 53, 42, .10);
        background: rgba(255, 255, 255, .78);
    }

    .hub-hero-action {
        border-color: rgba(23, 53, 42, .13);
        box-shadow: 0 3px 10px rgba(17, 49, 34, .05);
    }

    .hub-layout {
        grid-template-columns: 252px minmax(460px, 1fr) 316px;
        gap: 16px;
    }

    .hub-left-column,
    .hub-right-column {
        gap: 14px;
    }

    .hub-panel {
        border-color: var(--hub-premium-border);
        border-radius: 18px;
        background: #fff;
        box-shadow:
            0 9px 26px rgba(17, 49, 34, .055),
            0 1px 3px rgba(17, 49, 34, .035);
    }

    .hub-panel-head {
        min-height: 60px;
        padding: 12px 14px;
        border-bottom-color: #e7eee9;
        background: linear-gradient(180deg, #fbfdfc, #f8fbf9);
    }

    .hub-main-column .hub-portal-body {
        background: var(--hub-premium-surface);
    }

    .hub-main-column .hub-list {
        gap: 10px;
        padding: 12px;
    }

    .hub-main-column .hub-link {
        border-color: #e2ebe5;
        border-radius: 13px;
        background: #fff;
        box-shadow: 0 2px 7px rgba(17, 49, 34, .035);
    }

    .hub-main-column .hub-link:hover,
    .hub-main-column .hub-link:focus-visible {
        border-color: color-mix(in srgb, var(--item-tone) 30%, #dce8e1);
        box-shadow: 0 8px 20px rgba(17, 49, 34, .075);
        transform: translateY(-1px);
    }

    .hub-resource-link,
    .hub-contact-link {
        border-radius: 11px;
    }

    .hub-news-slide {
        border-color: color-mix(in srgb, var(--item-tone) 17%, #e0e9e3);
        border-radius: 14px;
        background:
            linear-gradient(145deg, #fff 0%, color-mix(in srgb, var(--item-soft) 65%, #fff) 100%);
    }

    .hub-mobile-nav {
        --hub-green: #168a4d;
        --hub-blue: #2563eb;
        --hub-violet: #7c3aed;
        --hub-amber: #c87408;
        --hub-slate: #596b61;
        --hub-premium-ink: #17352a;
        display: none;
    }

    [data-hub-nav-section] {
        scroll-margin-top: 82px;
    }

    @media (max-width: 1180px) and (min-width: 821px) {
        .hub-layout {
            grid-template-columns: minmax(0, 1fr) 310px;
        }
    }

    @media (max-width: 820px) {
        body.hub-context-navigation .app-nav-layer {
            display: none !important;
        }

        body.hub-context-navigation.has-app-nav .bento-container {
            padding-bottom: calc(58px + 22px + env(safe-area-inset-bottom, 0px)) !important;
        }

        .hub-page {
            width: min(100%, calc(100dvw - 18px));
            padding-bottom: calc(var(--hub-nav-height) + 14px + env(safe-area-inset-bottom, 0px));
        }

        .hub-home {
            gap: 11px;
        }

        .hub-hero {
            padding: 14px;
            border-radius: 17px;
            box-shadow: 0 8px 24px rgba(17, 49, 34, .06);
        }

        .hub-hero::after {
            width: 3px;
            border-radius: 17px 0 0 17px;
        }

        .hub-layout {
            gap: 11px;
        }

        .hub-left-column,
        .hub-right-column {
            gap: 11px;
        }

        .hub-panel {
            border-radius: 15px;
            box-shadow: 0 5px 16px rgba(17, 49, 34, .045);
        }

        .hub-panel-head {
            min-height: 54px;
            padding: 10px 11px;
        }

        [data-hub-nav-section] {
            scroll-margin-top: 72px;
        }

        .hub-mobile-nav {
            position: fixed;
            z-index: 680;
            right: 8px;
            bottom: max(8px, env(safe-area-inset-bottom, 0px));
            left: 8px;
            display: grid;
            min-height: 58px;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 3px;
            padding: 5px;
            border: 1px solid rgba(23, 53, 42, .12);
            border-radius: 17px;
            background: #fff;
            box-shadow:
                0 14px 34px rgba(17, 49, 34, .18),
                0 3px 9px rgba(17, 49, 34, .08);
        }

        .hub-mobile-nav-item {
            --nav-tone: var(--hub-slate);
            display: flex;
            min-width: 0;
            min-height: 46px;
            align-items: center;
            justify-content: center;
            gap: 3px;
            padding: 4px 3px;
            border: 0;
            border-radius: 12px;
            background: transparent;
            color: #849087;
            cursor: pointer;
            flex-direction: column;
            font: inherit;
            -webkit-tap-highlight-color: transparent;
            transition: background-color .18s ease, color .18s ease, transform .18s ease;
        }

        .hub-mobile-nav-item[data-tone="green"] { --nav-tone: var(--hub-green); }
        .hub-mobile-nav-item[data-tone="violet"] { --nav-tone: var(--hub-violet); }
        .hub-mobile-nav-item[data-tone="blue"] { --nav-tone: var(--hub-blue); }
        .hub-mobile-nav-item[data-tone="amber"] { --nav-tone: var(--hub-amber); }

        .hub-mobile-nav-item i {
            color: #97a19b;
            font-size: 19px;
            line-height: 1;
            transition: color .18s ease, transform .18s ease;
        }

        .hub-mobile-nav-item span {
            max-width: 100%;
            overflow: hidden;
            font-size: 9px;
            font-weight: 760;
            letter-spacing: -.01em;
            line-height: 1.1;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .hub-mobile-nav-item.active {
            background: #f2f6f3;
            color: var(--hub-premium-ink);
        }

        .hub-mobile-nav-item.active i {
            color: var(--nav-tone);
            transform: translateY(-1px);
        }

        .hub-mobile-nav-item:focus-visible {
            outline: 2px solid color-mix(in srgb, var(--nav-tone) 46%, transparent);
            outline-offset: -2px;
        }

        .hub-mobile-nav-item:active {
            transform: scale(.97);
        }
    }

    @media (max-width: 420px) {
        .hub-mobile-nav {
            right: 6px;
            bottom: max(6px, env(safe-area-inset-bottom, 0px));
            left: 6px;
            border-radius: 15px;
        }

        .hub-mobile-nav-item {
            border-radius: 10px;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .hub-news-track { scroll-behavior: auto; }

        .hub-mobile-nav-item,
        .hub-mobile-nav-item i {
            transition: none;
        }
    }
</style>

<main class="hub-page" data-hub-root>
    <div class="hub-home">
        <header class="hub-hero" aria-labelledby="hub-title">
            <span class="hub-hero-logo" aria-hidden="true">
                <img
                    src="{{ !empty($currentTenant?->logo)
                        ? asset('storage/' . $currentTenant->logo)
                        : asset('assets/sgc-symbol.png') }}"
                    alt=""
                >
            </span>

            <div class="min-w-0">
                <div class="hub-eyebrow">Organização atual</div>
                <h2 id="hub-title">{{ $currentTenant->name ?? 'Sua organização' }}</h2>
                <p>{{ $tenantDescription }}</p>

                <div class="hub-hero-meta">
                    <span class="hub-chip">
                        <i class="ph ph-user-circle" aria-hidden="true"></i>
                        {{ $displayName }}
                    </span>

                    <span class="hub-chip">
                        <i class="ph ph-squares-four" aria-hidden="true"></i>
                        <span data-visible-count>{{ $availablePanelsCount }}</span>
                        {{ $availablePanelsCount === 1 ? 'painel' : 'painéis' }}
                    </span>

                    @if($tenantLocation)
                        <span class="hub-chip">
                            <i class="ph ph-map-pin" aria-hidden="true"></i>
                            {{ $tenantLocation }}
                        </span>
                    @endif
                </div>
            </div>

            @if($tenantWebsite)
                <a
                    class="hub-hero-action"
                    href="{{ $tenantWebsite }}"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    <i class="ph ph-globe" aria-hidden="true"></i>
                    Site oficial
                </a>
            @endif
        </header>

        <div class="hub-layout">
            <section
                id="hub-portals-panel"
                class="hub-main-column hub-panel"
                aria-labelledby="hub-portals-title"
                data-hub-nav-section
            >
                <header class="hub-panel-head">
                    <div class="hub-panel-title">
                        <span class="hub-panel-icon tone-violet" aria-hidden="true">
                            <i class="ph-duotone ph-squares-four"></i>
                        </span>

                        <div>
                            <h3 id="hub-portals-title">Áreas de trabalho</h3>
                            <p>Acesse somente os painéis disponíveis para o seu perfil.</p>
                        </div>
                    </div>

                    <span
                        class="hub-count"
                        aria-label="{{ $availablePanelsCount }} painéis"
                        data-visible-count
                    >
                        {{ $availablePanelsCount }}
                    </span>
                </header>

                @if($availablePanelsCount > 6)
                    <div class="hub-portal-tools">
                        <div class="hub-search" data-hub-search>
                            <label class="sr-only" for="hub-panel-search">Buscar painel</label>
                            <i class="hub-search-icon ph ph-magnifying-glass" aria-hidden="true"></i>

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
                                <i class="ph ph-x" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                @endif

                <div class="hub-portal-body">
                    <nav
                        class="hub-list"
                        aria-label="Painéis disponíveis"
                        data-hub-list
                    >
                        @if($hasSuperAdmin)
                            @php($superVisual = $resolvePortalVisual('Super Admin'))

                            <a
                                class="hub-link tone-{{ $superVisual['tone'] }}"
                                href="{{ url('super-admin') }}"
                                aria-label="Abrir Super Admin"
                                data-hub-link
                            >
                                <span class="hub-role-icon grid shrink-0 place-items-center" aria-hidden="true">
                                    <i class="ph-duotone {{ $superVisual['icon'] }}"></i>
                                </span>

                                <span class="block min-w-0">
                                    <strong class="block truncate text-[12px] font-extrabold text-[var(--color-text,#102018)]">
                                        Super Admin
                                    </strong>
                                    <span class="mt-1 block truncate text-[10px] leading-[1.4] text-[var(--color-text-muted,#809087)]">
                                        {{ $superVisual['hint'] }}
                                    </span>
                                </span>

                                <span class="hub-link-arrow grid size-7 place-items-center" aria-hidden="true">
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
                                class="hub-link tone-{{ $visual['tone'] }}"
                                href="{{ $role['url'] }}"
                                aria-label="Abrir {{ $role['name'] }}"
                                data-hub-link
                            >
                                <span class="hub-role-icon grid shrink-0 place-items-center" aria-hidden="true">
                                    <i class="ph-duotone {{ $visual['icon'] }}"></i>
                                </span>

                                <span class="block min-w-0">
                                    <strong class="block truncate text-[12px] font-extrabold text-[var(--color-text,#102018)]">
                                        {{ $role['name'] }}
                                    </strong>
                                    <span
                                        class="mt-1 block truncate text-[10px] leading-[1.4] text-[var(--color-text-muted,#809087)]"
                                        title="{{ $portalDescription }}"
                                    >
                                        {{ $portalDescription }}
                                    </span>
                                </span>

                                <span class="hub-link-arrow grid size-7 place-items-center" aria-hidden="true">
                                    <i class="ph ph-arrow-right"></i>
                                </span>
                            </a>
                        @endforeach

                        @if($availablePanelsCount === 0)
                            <div class="hub-no-results grid justify-items-center rounded-xl border border-dashed border-[var(--color-border-strong,#c8d6cd)] bg-[var(--color-surface-soft,#f8faf9)] px-4 py-7 text-center" role="status">
                                <span class="hub-empty-icon mb-2 grid size-11 place-items-center rounded-xl" aria-hidden="true">
                                    <i class="ph-duotone ph-shield-warning text-xl"></i>
                                </span>
                                <strong class="text-[12px] font-extrabold text-[var(--color-text,#102018)]">
                                    Nenhum painel disponível
                                </strong>
                                <p class="mb-0 mt-1 text-[10px] text-[var(--color-text-secondary,#52645a)]">
                                    Solicite acesso a um administrador.
                                </p>
                            </div>
                        @endif

                        <div
                            class="hub-no-results grid justify-items-center rounded-xl border border-dashed border-[var(--color-border-strong,#c8d6cd)] bg-[var(--color-surface-soft,#f8faf9)] px-4 py-7 text-center"
                            role="status"
                            aria-live="polite"
                            hidden
                            data-hub-no-results
                        >
                            <span class="hub-empty-icon mb-2 grid size-11 place-items-center rounded-xl" aria-hidden="true">
                                <i class="ph-duotone ph-magnifying-glass text-xl"></i>
                            </span>
                            <strong class="text-[12px] font-extrabold text-[var(--color-text,#102018)]">
                                Nenhum painel encontrado
                            </strong>
                            <p class="mb-0 mt-1 text-[10px] text-[var(--color-text-secondary,#52645a)]">
                                Tente buscar por outro nome.
                            </p>
                        </div>
                    </nav>
                </div>
            </section>

            <aside class="hub-left-column" aria-label="Organização e recursos">
                <section class="hub-panel" aria-labelledby="hub-tenant-title">
                    <header class="hub-panel-head">
                        <div class="hub-panel-title">
                            <span class="hub-panel-icon tone-green" aria-hidden="true">
                                <i class="ph-duotone ph-buildings"></i>
                            </span>
                            <div>
                                <h3 id="hub-tenant-title">Sua organização</h3>
                                <p>Informações institucionais</p>
                            </div>
                        </div>
                    </header>

                    <div class="hub-tenant-body">
                        <div class="hub-tenant-brand">
                            <span class="hub-tenant-avatar" aria-hidden="true">
                                <img
                                    src="{{ !empty($currentTenant?->logo)
                                        ? asset('storage/' . $currentTenant->logo)
                                        : asset('assets/sgc-symbol.png') }}"
                                    alt=""
                                >
                            </span>

                            <div class="min-w-0">
                                <strong>{{ $currentTenant->name ?? 'Sua organização' }}</strong>
                                <span>Organização atual</span>
                            </div>
                        </div>

                        <p class="hub-tenant-description">{{ $tenantDescription }}</p>

                        <div class="hub-facts">
                            @if($tenantDocument)
                                <div class="hub-fact">
                                    <i class="ph ph-identification-card" aria-hidden="true"></i>
                                    <div>
                                        <span>Documento</span>
                                        <strong title="{{ $tenantDocument }}">{{ $tenantDocument }}</strong>
                                    </div>
                                </div>
                            @endif

                            @if($tenantLocation)
                                <div class="hub-fact">
                                    <i class="ph ph-map-pin" aria-hidden="true"></i>
                                    <div>
                                        <span>Localização</span>
                                        <strong title="{{ $tenantLocation }}">{{ $tenantLocation }}</strong>
                                    </div>
                                </div>
                            @endif

                            @if($tenantEmail)
                                <div class="hub-fact">
                                    <i class="ph ph-envelope-simple" aria-hidden="true"></i>
                                    <div>
                                        <span>E-mail</span>
                                        <strong title="{{ $tenantEmail }}">{{ $tenantEmail }}</strong>
                                    </div>
                                </div>
                            @endif

                            @if($tenantWebsite)
                                <div class="hub-fact">
                                    <i class="ph ph-globe" aria-hidden="true"></i>
                                    <div>
                                        <span>Site</span>
                                        <strong title="{{ $tenantWebsite }}">{{ parse_url($tenantWebsite, PHP_URL_HOST) ?: $tenantWebsite }}</strong>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </section>

                <section
                    id="hub-resources-panel"
                    class="hub-panel"
                    aria-labelledby="hub-resources-title"
                    data-hub-nav-section
                >
                    <header class="hub-panel-head">
                        <div class="hub-panel-title">
                            <span class="hub-panel-icon tone-blue" aria-hidden="true">
                                <i class="ph-duotone ph-compass-tool"></i>
                            </span>
                            <div>
                                <h3 id="hub-resources-title">Outros recursos</h3>
                                <p>Documentos e serviços</p>
                            </div>
                        </div>
                        <span class="hub-count">{{ $hubResources->count() }}</span>
                    </header>

                    <nav class="hub-resource-list" aria-label="Outros recursos">
                        @forelse($hubResources as $resource)
                            <a class="hub-resource-link" href="{{ $resource['url'] }}">
                                <span class="hub-tone-icon tone-{{ $resource['tone'] }}" aria-hidden="true">
                                    <i class="ph-duotone {{ $resource['icon'] }}"></i>
                                </span>
                                <span class="hub-resource-copy">
                                    <strong>{{ $resource['label'] }}</strong>
                                    <span>{{ $resource['description'] }}</span>
                                </span>
                                <i class="ph ph-caret-right" aria-hidden="true"></i>
                            </a>
                        @empty
                            <p class="hub-contact-empty">
                                Nenhum recurso adicional foi publicado para esta organização.
                            </p>
                        @endforelse
                    </nav>
                </section>
            </aside>

            <aside class="hub-right-column" aria-label="Novidades e contato">
                <section
                    id="hub-news-panel"
                    class="hub-panel"
                    aria-labelledby="hub-news-title"
                    data-hub-nav-section
                >
                    <header class="hub-panel-head">
                        <div class="hub-panel-title">
                            <span class="hub-panel-icon tone-violet" aria-hidden="true">
                                <i class="ph-duotone ph-megaphone-simple"></i>
                            </span>
                            <div>
                                <h3 id="hub-news-title">Novidades</h3>
                                <p>Informações em destaque</p>
                            </div>
                        </div>

                        @if($hubNews->count() > 1)
                            <div class="hub-carousel-controls">
                                <button
                                    class="hub-carousel-button"
                                    type="button"
                                    aria-label="Novidade anterior"
                                    data-news-prev
                                >
                                    <i class="ph ph-caret-left" aria-hidden="true"></i>
                                </button>
                                <button
                                    class="hub-carousel-button"
                                    type="button"
                                    aria-label="Próxima novidade"
                                    data-news-next
                                >
                                    <i class="ph ph-caret-right" aria-hidden="true"></i>
                                </button>
                            </div>
                        @endif
                    </header>

                    <div class="hub-news-viewport">
                        <div
                            class="hub-news-track"
                            data-news-track
                            @if($hubNews->count() > 1) tabindex="0" @endif
                        >
                            @foreach($hubNews as $news)
                                <article class="hub-news-slide tone-{{ $news['tone'] }}" data-news-slide>
                                    <div>
                                        <div class="hub-news-top">
                                            <span class="hub-news-icon" aria-hidden="true">
                                                <i class="ph-duotone {{ $news['icon'] }}"></i>
                                            </span>
                                            <span class="hub-news-label">{{ $news['label'] }}</span>
                                        </div>

                                        <h4 class="mt-3">{{ $news['title'] }}</h4>
                                        <p>{{ $news['description'] }}</p>
                                    </div>

                                    @if(filled($news['url']))
                                        <a class="hub-news-action" href="{{ $news['url'] }}">
                                            Saiba mais
                                            <i class="ph ph-arrow-right" aria-hidden="true"></i>
                                        </a>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    </div>

                    @if($hubNews->count() > 1)
                        <div class="hub-carousel-dots" aria-label="Selecionar novidade">
                            @foreach($hubNews as $news)
                                <button
                                    class="hub-carousel-dot {{ $loop->first ? 'active' : '' }}"
                                    type="button"
                                    aria-label="Ir para novidade {{ $loop->iteration }}"
                                    aria-current="{{ $loop->first ? 'true' : 'false' }}"
                                    data-news-dot="{{ $loop->index }}"
                                ></button>
                            @endforeach
                        </div>
                    @endif
                </section>

                <section
                    id="hub-contact-panel"
                    class="hub-panel"
                    aria-labelledby="hub-contact-title"
                    data-hub-nav-section
                >
                    <header class="hub-panel-head">
                        <div class="hub-panel-title">
                            <span class="hub-panel-icon tone-green" aria-hidden="true">
                                <i class="ph-duotone ph-chats-circle"></i>
                            </span>
                            <div>
                                <h3 id="hub-contact-title">Contato</h3>
                                <p>Canais oficiais</p>
                            </div>
                        </div>
                    </header>

                    @if($tenantWhatsappUrl || $tenantPhoneUrl || $tenantEmailUrl || $tenantWebsite)
                        <div class="hub-contact-list">
                            @if($tenantWhatsappUrl)
                                <a
                                    class="hub-contact-link"
                                    href="{{ $tenantWhatsappUrl }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <span class="hub-tone-icon tone-green" aria-hidden="true">
                                        <i class="ph-duotone ph-whatsapp-logo"></i>
                                    </span>
                                    <span class="hub-contact-copy">
                                        <strong>WhatsApp</strong>
                                        <span>{{ $tenantWhatsapp }}</span>
                                    </span>
                                    <i class="ph ph-caret-right" aria-hidden="true"></i>
                                </a>
                            @endif

                            @if($tenantPhoneUrl)
                                <a class="hub-contact-link" href="{{ $tenantPhoneUrl }}">
                                    <span class="hub-tone-icon tone-blue" aria-hidden="true">
                                        <i class="ph-duotone ph-phone"></i>
                                    </span>
                                    <span class="hub-contact-copy">
                                        <strong>Telefone</strong>
                                        <span>{{ $tenantPhone }}</span>
                                    </span>
                                    <i class="ph ph-caret-right" aria-hidden="true"></i>
                                </a>
                            @endif

                            @if($tenantEmailUrl)
                                <a class="hub-contact-link" href="{{ $tenantEmailUrl }}">
                                    <span class="hub-tone-icon tone-violet" aria-hidden="true">
                                        <i class="ph-duotone ph-envelope-simple"></i>
                                    </span>
                                    <span class="hub-contact-copy">
                                        <strong>E-mail</strong>
                                        <span>{{ $tenantEmail }}</span>
                                    </span>
                                    <i class="ph ph-caret-right" aria-hidden="true"></i>
                                </a>
                            @endif

                            @if($tenantWebsite)
                                <a
                                    class="hub-contact-link"
                                    href="{{ $tenantWebsite }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <span class="hub-tone-icon tone-sky" aria-hidden="true">
                                        <i class="ph-duotone ph-globe"></i>
                                    </span>
                                    <span class="hub-contact-copy">
                                        <strong>Site oficial</strong>
                                        <span>{{ parse_url($tenantWebsite, PHP_URL_HOST) ?: $tenantWebsite }}</span>
                                    </span>
                                    <i class="ph ph-caret-right" aria-hidden="true"></i>
                                </a>
                            @endif
                        </div>
                    @else
                        <p class="hub-contact-empty">
                            Os canais oficiais ainda não foram informados pela organização.
                        </p>
                    @endif

                    @if($tenantSocials->isNotEmpty())
                        <div class="hub-socials" aria-label="Redes sociais">
                            @foreach($tenantSocials as $social)
                                <a
                                    class="hub-social-link"
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
                </section>
            </aside>
        </div>
    </div>
</main>

<nav
    class="hub-mobile-nav"
    aria-label="Navegação rápida do hub"
    data-hub-mobile-nav
>
    <button
        class="hub-mobile-nav-item active"
        type="button"
        data-hub-nav-target="hub-portals-panel"
        data-tone="violet"
        aria-current="page"
    >
        <i class="ph-duotone ph-squares-four" aria-hidden="true"></i>
        <span>Painéis</span>
    </button>

    <button
        class="hub-mobile-nav-item"
        type="button"
        data-hub-nav-target="hub-news-panel"
        data-tone="amber"
    >
        <i class="ph-duotone ph-megaphone-simple" aria-hidden="true"></i>
        <span>Novidades</span>
    </button>

    <button
        class="hub-mobile-nav-item"
        type="button"
        data-hub-nav-target="hub-contact-panel"
        data-tone="green"
    >
        <i class="ph-duotone ph-chats-circle" aria-hidden="true"></i>
        <span>Contato</span>
    </button>

    <button
        class="hub-mobile-nav-item"
        type="button"
        data-hub-nav-target="hub-resources-panel"
        data-tone="blue"
    >
        <i class="ph-duotone ph-compass-tool" aria-hidden="true"></i>
        <span>Recursos</span>
    </button>
</nav>

<script>
    (() => {
        const root = document.querySelector('[data-hub-root]');
        if (!root) return;

        document.body.classList.add('hub-context-navigation');

        const mobileNavigation = document.querySelector('[data-hub-mobile-nav]');
        const mobileNavigationItems = [
            ...document.querySelectorAll('[data-hub-nav-target]')
        ];
        const mobileNavigationSections = mobileNavigationItems
            .map(item => document.getElementById(item.dataset.hubNavTarget))
            .filter(Boolean);
        let navigationFrame = null;

        function setActiveNavigation(targetId) {
            mobileNavigationItems.forEach(item => {
                const active = item.dataset.hubNavTarget === targetId;
                item.classList.toggle('active', active);

                if (active) {
                    item.setAttribute('aria-current', 'page');
                } else {
                    item.removeAttribute('aria-current');
                }
            });
        }

        function detectActiveNavigation() {
            if (!mobileNavigation || window.innerWidth > 820) return;

            const marker = Math.min(190, window.innerHeight * .3);
            const closest = mobileNavigationSections.reduce(
                (best, section) => {
                    const distance = Math.abs(
                        section.getBoundingClientRect().top - marker
                    );

                    return distance < best.distance
                        ? { id: section.id, distance }
                        : best;
                },
                { id: 'hub-portals-panel', distance: Infinity }
            );

            setActiveNavigation(closest.id);
        }

        mobileNavigationItems.forEach(item => {
            item.addEventListener('click', () => {
                const target = document.getElementById(item.dataset.hubNavTarget);
                if (!target) return;

                setActiveNavigation(target.id);
                target.scrollIntoView({
                    behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches
                        ? 'auto'
                        : 'smooth',
                    block: 'start',
                });
            });
        });

        const queueNavigationDetection = () => {
            if (navigationFrame) cancelAnimationFrame(navigationFrame);
            navigationFrame = requestAnimationFrame(detectActiveNavigation);
        };

        window.addEventListener('scroll', queueNavigationDetection, { passive: true });
        window.addEventListener('resize', queueNavigationDetection, { passive: true });

        const hubLinks = [
            ...root.querySelectorAll('[data-hub-link]')
        ];

        function resetLinks() {
            hubLinks.forEach(link => {
                link.classList.remove('is-opening');
                link.removeAttribute('aria-busy');
            });
        }

        const search = root.querySelector('[data-hub-search]');
        const searchInput = root.querySelector('[data-hub-search-input]');
        const searchClear = root.querySelector('[data-hub-search-clear]');
        const noResults = root.querySelector('[data-hub-no-results]');
        const visibleCounts = [
            ...root.querySelectorAll('[data-visible-count]')
        ];

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
            visibleCounts.forEach(counter => {
                counter.textContent = String(matches);
            });
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

        const newsTrack = root.querySelector('[data-news-track]');
        const newsSlides = [
            ...root.querySelectorAll('[data-news-slide]')
        ];
        const newsDots = [
            ...root.querySelectorAll('[data-news-dot]')
        ];
        const newsPrev = root.querySelector('[data-news-prev]');
        const newsNext = root.querySelector('[data-news-next]');
        let newsIndex = 0;
        let newsFrame = null;

        function setNewsIndex(index, scroll = false) {
            if (!newsSlides.length) return;

            newsIndex = Math.max(
                0,
                Math.min(index, newsSlides.length - 1)
            );

            newsDots.forEach((dot, dotIndex) => {
                const active = dotIndex === newsIndex;
                dot.classList.toggle('active', active);
                dot.setAttribute(
                    'aria-current',
                    active ? 'true' : 'false'
                );
            });

            if (newsPrev) newsPrev.disabled = newsIndex === 0;
            if (newsNext) {
                newsNext.disabled = newsIndex === newsSlides.length - 1;
            }

            if (scroll) {
                const firstOffset = newsSlides[0]?.offsetLeft || 0;
                newsTrack?.scrollTo({
                    left:
                        newsSlides[newsIndex].offsetLeft
                        - firstOffset,
                    behavior: 'smooth',
                });
            }
        }

        function detectNewsIndex() {
            if (!newsTrack || !newsSlides.length) return;

            const left = newsTrack.scrollLeft;
            const closest = newsSlides.reduce(
                (best, slide, index) => {
                    const firstOffset = newsSlides[0]?.offsetLeft || 0;
                    const distance = Math.abs(
                        slide.offsetLeft - firstOffset - left
                    );

                    return distance < best.distance
                        ? { index, distance }
                        : best;
                },
                { index: 0, distance: Infinity }
            );

            setNewsIndex(closest.index);
        }

        newsTrack?.addEventListener(
            'scroll',
            () => {
                if (newsFrame) cancelAnimationFrame(newsFrame);
                newsFrame = requestAnimationFrame(detectNewsIndex);
            },
            { passive: true }
        );

        newsTrack?.addEventListener('keydown', event => {
            if (event.key === 'ArrowLeft') {
                event.preventDefault();
                setNewsIndex(newsIndex - 1, true);
            }

            if (event.key === 'ArrowRight') {
                event.preventDefault();
                setNewsIndex(newsIndex + 1, true);
            }
        });

        newsPrev?.addEventListener('click', () => {
            setNewsIndex(newsIndex - 1, true);
        });

        newsNext?.addEventListener('click', () => {
            setNewsIndex(newsIndex + 1, true);
        });

        newsDots.forEach(dot => {
            dot.addEventListener('click', () => {
                setNewsIndex(
                    Number(dot.dataset.newsDot || 0),
                    true
                );
            });
        });

        if ('ResizeObserver' in window && newsTrack) {
            new ResizeObserver(() => {
                detectNewsIndex();
            }).observe(newsTrack);
        }

        setNewsIndex(0);
        detectActiveNavigation();

        window.addEventListener('pageshow', () => {
            resetLinks();
            filterPanels();
            detectNewsIndex();
            detectActiveNavigation();
        });
    })();
</script>
@endsection