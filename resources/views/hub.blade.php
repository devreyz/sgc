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
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/duotone/style.css">
@endonce


<style>
    /*
     * Hub alinhado ao Project Workspace / Dashboard do associado.
     *
     * Regra visual:
     * - superfície branca e neutra como base;
     * - 12px nos shells principais;
     * - ícones compactos;
     * - cor restrita à identidade/estado, nunca no card inteiro;
     * - desktop como workspace operacional;
     * - mobile continua usando as quatro telas do JS original.
     */

    .hub-page,
    .hub-bottom-nav {
        --hub-green: #219653;
        --hub-green-strong: #177c43;
        --hub-green-soft: #edf8f1;
        --hub-green-border: #cde8d6;

        --hub-blue: #3478d4;
        --hub-blue-soft: #eef4ff;
        --hub-blue-border: #d4e2f8;

        --hub-sky: #168eae;
        --hub-sky-soft: #edf8fb;
        --hub-sky-border: #d2eaf0;

        --hub-violet: #8a4bd2;
        --hub-violet-soft: #f5effc;
        --hub-violet-border: #e5d8f5;

        --hub-amber: #c38418;
        --hub-amber-soft: #fff7e8;
        --hub-amber-border: #efdcb8;

        --hub-red: #cf5050;
        --hub-red-soft: #fff1f1;
        --hub-red-border: #f1cccc;

        --hub-slate: #64748b;
        --hub-slate-soft: #f2f5f7;
        --hub-slate-border: #dfe5e9;

        --hub-text: var(--color-text, #17251c);
        --hub-text-2: var(--color-text-secondary, #58685e);
        --hub-muted: var(--color-text-muted, #87938b);
        --hub-border: var(--color-border, #d7e2da);
        --hub-border-strong: var(--color-border-strong, #becdc3);
        --hub-surface: var(--color-surface, #fff);
        --hub-soft: var(--color-surface-soft, #f7faf8);

        --hub-shadow: 0 5px 18px rgba(25, 61, 39, .05);
    }

    .hub-page {
        display: grid;
        width: min(100%, 1380px);
        min-width: 0;
        grid-column: 1 / -1;
        gap: .78rem;
        margin: 0 auto;
        padding-bottom: 1rem;
        color: var(--hub-text);
    }

    .hub-page *,
    .hub-page *::before,
    .hub-page *::after,
    .hub-bottom-nav *,
    .hub-bottom-nav *::before,
    .hub-bottom-nav *::after {
        box-sizing: border-box;
    }

    .hub-page a {
        color: inherit;
        text-decoration: none;
        -webkit-tap-highlight-color: transparent;
    }

    .hub-page button,
    .hub-page input,
    .hub-bottom-nav button {
        font: inherit;
    }

    .hub-screen[hidden] {
        display: none !important;
    }

    /* =========================================================
       CONTEXTO DA ORGANIZAÇÃO
       ========================================================= */

    .hub-context {
        display: grid;
        min-width: 0;
        min-height: 76px;
        grid-template-columns: 44px minmax(0, 1fr) auto;
        gap: .68rem;
        align-items: center;
        padding: .74rem .8rem;
        border: 1px solid var(--hub-border);
        border-radius: 12px;
        background: var(--hub-surface);
        box-shadow: var(--hub-shadow);
    }

    .hub-context-logo {
        display: grid;
        width: 44px;
        height: 44px;
        place-items: center;
        overflow: hidden;
        border: 1px solid var(--hub-border);
        border-radius: 9px;
        background: var(--hub-soft);
    }

    .hub-context-logo img {
        display: block;
        width: 100%;
        height: 100%;
        padding: 5px;
        object-fit: contain;
    }

    .hub-context-copy {
        min-width: 0;
    }

    .hub-context-kicker {
        display: flex;
        gap: .32rem;
        align-items: center;
        color: var(--hub-muted);
        font-size: .66rem;
        font-weight: 760;
        letter-spacing: .025em;
        text-transform: uppercase;
    }

    .hub-context-kicker::before {
        width: 6px;
        height: 6px;
        flex: 0 0 auto;
        border-radius: 50%;
        background: var(--hub-green);
        content: "";
    }

    .hub-context-name {
        margin: .06rem 0 0;
        overflow: hidden;
        color: var(--hub-text);
        font-size: clamp(1.03rem, 2vw, 1.25rem);
        font-weight: 850;
        letter-spacing: -.025em;
        line-height: 1.22;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .hub-context-meta {
        display: flex;
        min-width: 0;
        gap: .18rem .68rem;
        align-items: center;
        flex-wrap: wrap;
        margin-top: .18rem;
        color: var(--hub-muted);
        font-size: .68rem;
        font-weight: 620;
    }

    .hub-context-meta span {
        display: inline-flex;
        min-width: 0;
        gap: .24rem;
        align-items: center;
    }

    .hub-context-meta i {
        color: var(--hub-blue);
        font-size: .75rem;
    }

    .hub-context-meta strong {
        color: var(--hub-text-2);
        font-weight: 780;
    }

    .hub-context-site {
        display: inline-flex;
        min-height: 38px;
        gap: .3rem;
        align-items: center;
        justify-content: center;
        padding: .4rem .58rem;
        border: 1px solid var(--hub-border);
        border-radius: 8px;
        background: #fff;
        color: var(--hub-text-2);
        font-size: .69rem;
        font-weight: 760;
        white-space: nowrap;
    }

    .hub-context-site:hover,
    .hub-context-site:focus-visible {
        border-color: var(--hub-blue-border);
        background: var(--hub-blue-soft);
        color: var(--hub-blue);
        outline: 0;
    }

    /* =========================================================
       WORKSPACE DESKTOP
       ========================================================= */

    .hub-desktop-layout {
        display: grid;
        min-width: 0;
        grid-template-columns:
            minmax(0, 1.48fr)
            minmax(320px, .52fr);
        gap: .78rem;
        align-items: start;
    }

    .hub-main-stack,
    .hub-side-stack {
        display: grid;
        min-width: 0;
        gap: .78rem;
        align-content: start;
    }

    .hub-screen {
        min-width: 0;
    }

    .hub-panel {
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--hub-border);
        border-radius: 12px;
        background: var(--hub-surface);
        box-shadow: var(--hub-shadow);
    }

    .hub-panel-head {
        display: grid;
        min-width: 0;
        min-height: 62px;
        grid-template-columns: 39px minmax(0, 1fr) auto;
        gap: .58rem;
        align-items: center;
        padding: .65rem .72rem;
        border-bottom: 1px solid var(--hub-border);
        background: #fff;
    }

    .hub-panel-icon {
        display: grid;
        width: 39px;
        height: 39px;
        place-items: center;
        border-radius: 9px;
        background: var(--hub-slate-soft);
        color: var(--hub-slate);
    }

    .hub-panel-icon.violet {
        background: var(--hub-violet-soft);
        color: var(--hub-violet);
    }

    .hub-panel-icon.blue {
        background: var(--hub-blue-soft);
        color: var(--hub-blue);
    }

    .hub-panel-icon.amber {
        background: var(--hub-amber-soft);
        color: var(--hub-amber);
    }

    .hub-panel-icon.green {
        background: var(--hub-green-soft);
        color: var(--hub-green);
    }

    .hub-panel-icon i {
        font-size: 1.02rem;
    }

    .hub-panel-title {
        min-width: 0;
    }

    .hub-panel-title h2,
    .hub-panel-title h3,
    .hub-panel-title p {
        margin: 0;
    }

    .hub-panel-title h2,
    .hub-panel-title h3 {
        overflow: hidden;
        color: var(--hub-text);
        font-size: .92rem;
        font-weight: 840;
        letter-spacing: -.02em;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .hub-panel-title p {
        margin-top: .08rem;
        overflow: hidden;
        color: var(--hub-muted);
        font-size: .69rem;
        line-height: 1.35;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .hub-count {
        display: inline-flex;
        min-width: 29px;
        min-height: 29px;
        align-items: center;
        justify-content: center;
        padding: .24rem .42rem;
        border-radius: 7px;
        background: var(--hub-slate-soft);
        color: var(--hub-text-2);
        font-size: .65rem;
        font-weight: 790;
        white-space: nowrap;
    }

    /* =========================================================
       PORTAIS
       ========================================================= */

    .hub-portal-area {
        padding: .68rem .7rem .72rem;
    }

    .hub-portal-explainer {
        display: grid;
        min-width: 0;
        grid-template-columns: minmax(0, 1fr) minmax(190px, 260px);
        gap: .55rem;
        align-items: center;
        margin-bottom: .6rem;
    }

    .hub-portal-explainer-copy {
        min-width: 0;
    }

    .hub-portal-explainer strong,
    .hub-portal-explainer span {
        display: block;
    }

    .hub-portal-explainer strong {
        color: var(--hub-text-2);
        font-size: .7rem;
        font-weight: 790;
    }

    .hub-portal-explainer span {
        margin-top: .04rem;
        color: var(--hub-muted);
        font-size: .63rem;
        line-height: 1.4;
    }

    .hub-search {
        position: relative;
        min-width: 0;
    }

    .hub-search > i {
        position: absolute;
        z-index: 2;
        top: 50%;
        left: .64rem;
        color: var(--hub-muted);
        font-size: .82rem;
        pointer-events: none;
        transform: translateY(-50%);
    }

    .hub-search-input {
        width: 100%;
        min-height: 40px;
        padding: .46rem 2rem .46rem 2rem;
        border: 1px solid var(--hub-border-strong);
        border-radius: 8px;
        outline: 0;
        background: #fff;
        color: var(--hub-text);
        font-size: .72rem;
    }

    .hub-search-input:focus {
        border-color: var(--hub-blue);
        box-shadow: 0 0 0 3px rgba(52, 120, 212, .1);
    }

    .hub-search-clear {
        position: absolute;
        top: 50%;
        right: .36rem;
        display: none;
        width: 31px;
        height: 31px;
        place-items: center;
        border: 0;
        border-radius: 7px;
        background: transparent;
        color: var(--hub-muted);
        cursor: pointer;
        transform: translateY(-50%);
    }

    .hub-search.has-value .hub-search-clear {
        display: grid;
    }

    .hub-search-clear:hover,
    .hub-search-clear:focus-visible {
        background: var(--hub-soft);
        color: var(--hub-text);
        outline: 0;
    }

    .hub-portal-grid {
        display: grid;
        min-width: 0;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        overflow: hidden;
        border: 1px solid var(--hub-border);
        border-radius: 9px;
        background: #fff;
    }

    .hub-portal-link {
        --portal-tone: var(--hub-slate);
        --portal-soft: var(--hub-slate-soft);

        position: relative;
        display: grid;
        min-width: 0;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .5rem;
        align-items: center;
        min-height: 78px;
        padding: .58rem .62rem;
        border-bottom: 1px solid var(--hub-border);
        background: #fff;
        color: inherit;
        transition:
            background 150ms ease,
            box-shadow 150ms ease;
    }

    .hub-portal-link:nth-child(odd) {
        border-right: 1px solid var(--hub-border);
    }

    .hub-portal-link.tone-green {
        --portal-tone: var(--hub-green);
        --portal-soft: var(--hub-green-soft);
    }

    .hub-portal-link.tone-blue {
        --portal-tone: var(--hub-blue);
        --portal-soft: var(--hub-blue-soft);
    }

    .hub-portal-link.tone-sky {
        --portal-tone: var(--hub-sky);
        --portal-soft: var(--hub-sky-soft);
    }

    .hub-portal-link.tone-violet {
        --portal-tone: var(--hub-violet);
        --portal-soft: var(--hub-violet-soft);
    }

    .hub-portal-link.tone-amber {
        --portal-tone: var(--hub-amber);
        --portal-soft: var(--hub-amber-soft);
    }

    .hub-portal-link.tone-red {
        --portal-tone: var(--hub-red);
        --portal-soft: var(--hub-red-soft);
    }

    .hub-portal-link.tone-slate {
        --portal-tone: var(--hub-slate);
        --portal-soft: var(--hub-slate-soft);
    }

    .hub-portal-link:hover,
    .hub-portal-link:focus-visible {
        z-index: 1;
        background: #fafcfb;
        color: inherit;
        outline: 0;
        box-shadow: inset 3px 0 0 var(--portal-tone);
    }

    .hub-portal-main {
        display: grid;
        min-width: 0;
        grid-template-columns: 38px minmax(0, 1fr);
        gap: .48rem;
        align-items: center;
    }

    .hub-portal-icon {
        display: grid;
        width: 38px;
        height: 38px;
        place-items: center;
        border-radius: 8px;
        background: var(--portal-soft);
        color: var(--portal-tone);
    }

    .hub-portal-icon i {
        font-size: 1rem;
    }

    .hub-portal-copy {
        min-width: 0;
    }

    .hub-portal-badge {
        display: inline-flex;
        gap: .2rem;
        align-items: center;
        color: var(--hub-muted);
        font-size: .54rem;
        font-weight: 730;
        letter-spacing: .025em;
        text-transform: uppercase;
    }

    .hub-portal-badge i {
        color: var(--portal-tone);
        font-size: .62rem;
    }

    .hub-portal-name {
        display: block;
        min-width: 0;
        margin-top: .05rem;
        overflow: hidden;
        color: var(--hub-text);
        font-size: .76rem;
        font-weight: 820;
        line-height: 1.3;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .hub-portal-description {
        display: block;
        min-width: 0;
        margin-top: .05rem;
        overflow: hidden;
        color: var(--hub-muted);
        font-size: .62rem;
        line-height: 1.36;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .hub-portal-cta {
        display: inline-flex;
        min-height: 32px;
        gap: .24rem;
        align-items: center;
        justify-content: center;
        padding: .3rem .4rem;
        border: 1px solid var(--hub-border);
        border-radius: 7px;
        background: #fff;
        color: var(--hub-text-2);
        font-size: .61rem;
        font-weight: 760;
        white-space: nowrap;
    }

    .hub-portal-cta i {
        color: var(--portal-tone);
        font-size: .72rem;
        transition: transform 150ms ease;
    }

    .hub-portal-link:hover .hub-portal-cta,
    .hub-portal-link:focus-visible .hub-portal-cta {
        border-color: color-mix(in srgb, var(--portal-tone) 22%, var(--hub-border));
        color: var(--portal-tone);
    }

    .hub-portal-link:hover .hub-portal-cta i,
    .hub-portal-link:focus-visible .hub-portal-cta i {
        transform: translateX(2px);
    }

    .hub-portal-link.is-opening {
        pointer-events: none;
        opacity: .68;
    }

    .hub-portal-link.is-opening .hub-portal-icon i {
        animation: hub-icon-pulse .65s ease-in-out infinite alternate;
    }

    @keyframes hub-icon-pulse {
        to {
            transform: scale(.9);
            opacity: .62;
        }
    }

    .hub-portal-link[hidden],
    .hub-no-results[hidden] {
        display: none !important;
    }

    .hub-no-results {
        grid-column: 1 / -1;
        padding: 1rem;
        color: var(--hub-muted);
        font-size: .7rem;
        line-height: 1.45;
        text-align: center;
    }

    /* =========================================================
       LISTAS DE RECURSOS / CONTATO
       ========================================================= */

    .hub-list {
        display: block;
        min-width: 0;
        margin: .62rem .7rem .7rem;
        overflow: hidden;
        border: 1px solid var(--hub-border);
        border-radius: 9px;
        background: #fff;
    }

    .hub-list-link {
        --item-tone: var(--hub-slate);
        --item-soft: var(--hub-slate-soft);

        display: grid;
        min-width: 0;
        grid-template-columns: 34px minmax(0, 1fr) 28px;
        gap: .44rem;
        align-items: center;
        min-height: 58px;
        padding: .48rem .52rem;
        border-bottom: 1px solid var(--hub-border);
        background: #fff;
        color: inherit;
        transition: background 150ms ease;
    }

    .hub-list-link:last-child {
        border-bottom: 0;
    }

    .hub-list-link.tone-green {
        --item-tone: var(--hub-green);
        --item-soft: var(--hub-green-soft);
    }

    .hub-list-link.tone-blue {
        --item-tone: var(--hub-blue);
        --item-soft: var(--hub-blue-soft);
    }

    .hub-list-link.tone-sky {
        --item-tone: var(--hub-sky);
        --item-soft: var(--hub-sky-soft);
    }

    .hub-list-link.tone-violet {
        --item-tone: var(--hub-violet);
        --item-soft: var(--hub-violet-soft);
    }

    .hub-list-link.tone-amber {
        --item-tone: var(--hub-amber);
        --item-soft: var(--hub-amber-soft);
    }

    .hub-list-link.tone-red {
        --item-tone: var(--hub-red);
        --item-soft: var(--hub-red-soft);
    }

    .hub-list-link.tone-slate {
        --item-tone: var(--hub-slate);
        --item-soft: var(--hub-slate-soft);
    }

    .hub-list-link:hover,
    .hub-list-link:focus-visible {
        background: #fafcfb;
        color: inherit;
        outline: 0;
    }

    .hub-list-icon {
        display: grid;
        width: 34px;
        height: 34px;
        place-items: center;
        border-radius: 7px;
        background: var(--item-soft);
        color: var(--item-tone);
    }

    .hub-list-icon i {
        font-size: .88rem;
    }

    .hub-list-copy {
        min-width: 0;
    }

    .hub-list-copy strong,
    .hub-list-copy span {
        display: block;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .hub-list-copy strong {
        color: var(--hub-text);
        font-size: .72rem;
        font-weight: 800;
    }

    .hub-list-copy span {
        margin-top: .04rem;
        color: var(--hub-muted);
        font-size: .61rem;
    }

    .hub-list-arrow {
        justify-self: end;
        color: var(--hub-muted);
        font-size: .72rem;
    }

    .hub-list-link:hover .hub-list-arrow,
    .hub-list-link:focus-visible .hub-list-arrow {
        color: var(--item-tone);
    }

    .hub-empty {
        padding: 1rem .75rem;
        color: var(--hub-muted);
        font-size: .69rem;
        line-height: 1.45;
        text-align: center;
    }

    /* =========================================================
       NOVIDADES
       ========================================================= */

    .hub-news-list {
        display: block;
        min-width: 0;
        margin: .62rem .7rem .7rem;
        overflow: hidden;
        border: 1px solid var(--hub-border);
        border-radius: 9px;
        background: #fff;
    }

    .hub-news-item {
        --news-tone: var(--hub-slate);
        --news-soft: var(--hub-slate-soft);

        display: grid;
        min-width: 0;
        grid-template-columns: 34px minmax(0, 1fr);
        gap: .46rem;
        align-items: start;
        padding: .56rem .54rem;
        border-bottom: 1px solid var(--hub-border);
        background: #fff;
    }

    .hub-news-item:last-child {
        border-bottom: 0;
    }

    .hub-news-item.tone-green {
        --news-tone: var(--hub-green);
        --news-soft: var(--hub-green-soft);
    }

    .hub-news-item.tone-blue {
        --news-tone: var(--hub-blue);
        --news-soft: var(--hub-blue-soft);
    }

    .hub-news-item.tone-sky {
        --news-tone: var(--hub-sky);
        --news-soft: var(--hub-sky-soft);
    }

    .hub-news-item.tone-violet {
        --news-tone: var(--hub-violet);
        --news-soft: var(--hub-violet-soft);
    }

    .hub-news-item.tone-amber {
        --news-tone: var(--hub-amber);
        --news-soft: var(--hub-amber-soft);
    }

    .hub-news-item.tone-red {
        --news-tone: var(--hub-red);
        --news-soft: var(--hub-red-soft);
    }

    .hub-news-item.tone-slate {
        --news-tone: var(--hub-slate);
        --news-soft: var(--hub-slate-soft);
    }

    .hub-news-icon {
        display: grid;
        width: 34px;
        height: 34px;
        place-items: center;
        border-radius: 7px;
        background: var(--news-soft);
        color: var(--news-tone);
    }

    .hub-news-icon i {
        font-size: .88rem;
    }

    .hub-news-copy {
        min-width: 0;
    }

    .hub-news-label {
        display: block;
        color: var(--news-tone);
        font-size: .54rem;
        font-weight: 790;
        letter-spacing: .025em;
        text-transform: uppercase;
    }

    .hub-news-title {
        display: block;
        margin-top: .04rem;
        color: var(--hub-text);
        font-size: .72rem;
        font-weight: 810;
        line-height: 1.35;
    }

    .hub-news-description {
        display: -webkit-box;
        overflow: hidden;
        margin-top: .1rem;
        color: var(--hub-muted);
        font-size: .62rem;
        line-height: 1.42;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 3;
    }

    .hub-news-action {
        display: inline-flex;
        min-height: 28px;
        gap: .22rem;
        align-items: center;
        margin-top: .3rem;
        padding: .22rem 0;
        color: var(--hub-blue);
        font-size: .61rem;
        font-weight: 780;
    }

    .hub-news-action:hover,
    .hub-news-action:focus-visible {
        color: var(--hub-blue);
        outline: 0;
        text-decoration: underline;
        text-underline-offset: 2px;
    }

    /* =========================================================
       ORGANIZAÇÃO / CONTATO
       ========================================================= */

    .hub-org-summary {
        display: grid;
        gap: .5rem;
        margin: .62rem .7rem 0;
        padding: .6rem;
        border: 1px solid var(--hub-border);
        border-radius: 9px;
        background: var(--hub-soft);
    }

    .hub-org-main {
        display: grid;
        min-width: 0;
        grid-template-columns: 40px minmax(0, 1fr);
        gap: .48rem;
        align-items: center;
    }

    .hub-org-logo {
        display: grid;
        width: 40px;
        height: 40px;
        place-items: center;
        overflow: hidden;
        border: 1px solid var(--hub-border);
        border-radius: 8px;
        background: #fff;
    }

    .hub-org-logo img {
        display: block;
        width: 100%;
        height: 100%;
        padding: 5px;
        object-fit: contain;
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
        color: var(--hub-text);
        font-size: .75rem;
        font-weight: 820;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .hub-org-copy span {
        display: -webkit-box;
        overflow: hidden;
        margin-top: .05rem;
        color: var(--hub-muted);
        font-size: .62rem;
        line-height: 1.42;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 2;
    }

    .hub-org-facts {
        display: flex;
        gap: .32rem;
        flex-wrap: wrap;
    }

    .hub-org-fact {
        display: inline-flex;
        min-height: 27px;
        gap: .22rem;
        align-items: center;
        padding: .22rem .36rem;
        border: 1px solid var(--hub-border);
        border-radius: 7px;
        background: #fff;
        color: var(--hub-text-2);
        font-size: .58rem;
        font-weight: 700;
    }

    .hub-org-fact i {
        color: var(--hub-muted);
    }

    .hub-socials {
        display: flex;
        gap: .32rem;
        flex-wrap: wrap;
        padding: 0 .7rem .7rem;
    }

    .hub-social-link {
        display: inline-flex;
        min-height: 32px;
        gap: .24rem;
        align-items: center;
        padding: .28rem .42rem;
        border: 1px solid var(--hub-border);
        border-radius: 8px;
        background: #fff;
        color: var(--hub-text-2);
        font-size: .6rem;
        font-weight: 740;
    }

    .hub-social-link:hover,
    .hub-social-link:focus-visible {
        border-color: var(--hub-blue-border);
        background: var(--hub-blue-soft);
        color: var(--hub-blue);
        outline: 0;
    }

    /* =========================================================
       ELEMENTOS EXCLUSIVOS DO MOBILE
       ========================================================= */

    .hub-mobile-screen-title,
    .hub-bottom-nav {
        display: none;
    }

    @media (max-width: 1100px) and (min-width: 821px) {
        .hub-desktop-layout {
            grid-template-columns:
                minmax(0, 1.32fr)
                minmax(290px, .68fr);
        }

        .hub-portal-grid {
            grid-template-columns: 1fr;
        }

        .hub-portal-link:nth-child(odd) {
            border-right: 0;
        }
    }

    @media (max-width: 820px) {
        body.hub-app-navigation .app-nav-layer {
            display: none !important;
        }

        body.hub-app-navigation.has-app-nav .bento-container {
            padding-bottom:
                calc(
                    72px
                    + 12px
                    + env(safe-area-inset-bottom, 0px)
                ) !important;
        }

        .hub-page {
            width: 100%;
            gap: .58rem;
            padding-bottom:
                calc(
                    72px
                    + 14px
                    + env(safe-area-inset-bottom, 0px)
                );
        }

        .hub-context {
            min-height: 62px;
            grid-template-columns: 38px minmax(0, 1fr);
            gap: .48rem;
            padding: .52rem .58rem;
            border-radius: 11px;
            box-shadow: none;
        }

        .hub-context-logo {
            width: 38px;
            height: 38px;
            border-radius: 8px;
        }

        .hub-context-kicker,
        .hub-context-site,
        .hub-context-extra {
            display: none;
        }

        .hub-context-name {
            font-size: .96rem;
        }

        .hub-context-meta {
            margin-top: .1rem;
            font-size: .62rem;
        }

        .hub-mobile-screen-title {
            display: grid;
            min-width: 0;
            grid-template-columns: 35px minmax(0, 1fr);
            gap: .46rem;
            align-items: center;
            padding: .48rem .56rem;
            border: 1px solid var(--hub-border);
            border-radius: 10px;
            background: #fff;
        }

        .hub-mobile-screen-icon {
            display: grid;
            width: 35px;
            height: 35px;
            place-items: center;
            border-radius: 8px;
            background: var(--hub-violet-soft);
            color: var(--hub-violet);
        }

        .hub-mobile-screen-icon i {
            font-size: .9rem;
        }

        .hub-mobile-screen-copy {
            min-width: 0;
        }

        .hub-mobile-screen-copy strong,
        .hub-mobile-screen-copy span {
            display: block;
        }

        .hub-mobile-screen-copy strong {
            color: var(--hub-text);
            font-size: .78rem;
            font-weight: 810;
        }

        .hub-mobile-screen-copy span {
            margin-top: .03rem;
            overflow: hidden;
            color: var(--hub-muted);
            font-size: .61rem;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .hub-desktop-layout {
            display: block;
        }

        .hub-main-stack,
        .hub-side-stack {
            display: contents;
        }

        .hub-screen {
            display: none;
            min-width: 0;
        }

        .hub-screen.is-active {
            display: block;
            animation: hub-screen-enter 160ms ease-out both;
        }

        .hub-panel {
            border-radius: 11px;
            box-shadow: none;
        }

        .hub-panel-head {
            min-height: 58px;
            grid-template-columns: 36px minmax(0, 1fr) auto;
            gap: .48rem;
            padding: .56rem .6rem;
        }

        .hub-panel-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
        }

        .hub-panel-title p {
            display: none;
        }

        .hub-portal-area {
            padding: .58rem;
        }

        .hub-portal-explainer {
            grid-template-columns: 1fr;
            gap: .45rem;
            margin-bottom: .5rem;
        }

        .hub-portal-explainer-copy {
            display: none;
        }

        .hub-search-input {
            min-height: 46px;
            font-size: 16px;
        }

        .hub-portal-grid {
            grid-template-columns: 1fr;
            border-radius: 9px;
        }

        .hub-portal-link {
            min-height: 72px;
            padding: .54rem;
            border-right: 0 !important;
        }

        .hub-portal-main {
            grid-template-columns: 36px minmax(0, 1fr);
            gap: .44rem;
        }

        .hub-portal-icon {
            width: 36px;
            height: 36px;
        }

        .hub-portal-name {
            font-size: .75rem;
        }

        .hub-portal-description {
            font-size: .61rem;
        }

        .hub-portal-cta {
            width: 32px;
            min-width: 32px;
            padding: 0;
        }

        .hub-portal-cta span {
            display: none;
        }

        .hub-list,
        .hub-news-list {
            margin: .58rem;
        }

        .hub-list-link {
            min-height: 60px;
            padding: .5rem;
        }

        .hub-news-item {
            padding: .54rem .5rem;
        }

        .hub-org-summary {
            margin: .58rem .58rem 0;
        }

        .hub-socials {
            padding: 0 .58rem .58rem;
        }

        .hub-bottom-nav {
            position: fixed;
            z-index: 140;
            right: max(8px, env(safe-area-inset-right, 0px));
            bottom:
                max(
                    8px,
                    env(safe-area-inset-bottom, 0px)
                );
            left: max(8px, env(safe-area-inset-left, 0px));
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: .2rem;
            min-height: 62px;
            padding: .35rem;
            border: 1px solid var(--hub-border);
            border-radius: 13px;
            background: rgba(255, 255, 255, .97);
            box-shadow: 0 10px 28px rgba(20, 48, 31, .14);
        }

        .hub-bottom-item {
            --nav-tone: var(--hub-green);

            display: grid;
            min-width: 0;
            min-height: 52px;
            place-items: center;
            align-content: center;
            gap: .18rem;
            padding: .2rem;
            border: 0;
            border-radius: 9px;
            background: transparent;
            color: var(--hub-muted);
            cursor: pointer;
        }

        /*
         * A barra inferior mantém uma identidade única.
         * A cor ativa permanece verde como no restante do app.
         */
        .hub-bottom-item[data-tone] {
            --nav-tone: var(--hub-green);
        }

        .hub-bottom-item i {
            font-size: 1.15rem;
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
            background: var(--hub-green-soft);
            color: var(--hub-green);
        }

        .hub-bottom-item:focus-visible {
            outline: 2px solid var(--hub-green-border);
            outline-offset: -2px;
        }

        .hub-bottom-item:active {
            transform: scale(.98);
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

    @media (max-width: 410px) {
        .hub-context {
            padding-inline: .48rem;
        }

        .hub-context-meta span:nth-child(2) {
            display: none;
        }

        .hub-panel-head {
            padding-inline: .52rem;
        }

        .hub-count {
            min-width: 27px;
        }

        .hub-portal-link {
            grid-template-columns: minmax(0, 1fr) 30px;
        }

        .hub-list,
        .hub-news-list {
            margin-inline: .5rem;
        }

        .hub-org-summary {
            margin-inline: .5rem;
        }

        .hub-socials {
            padding-inline: .5rem;
        }

        .hub-bottom-nav {
            gap: .1rem;
            padding-inline: .26rem;
        }

        .hub-bottom-item span {
            font-size: .55rem;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .hub-page *,
        .hub-page *::before,
        .hub-page *::after,
        .hub-bottom-nav *,
        .hub-bottom-nav *::before,
        .hub-bottom-nav *::after {
            animation-duration: .01ms !important;
            animation-iteration-count: 1 !important;
            scroll-behavior: auto !important;
            transition-duration: .01ms !important;
        }
    }
</style>


<main class="hub-page" data-hub-root>
    <header class="hub-context" aria-labelledby="hub-title">
        <span class="hub-context-logo" aria-hidden="true">
            <img
                src="{{ !empty($currentTenant?->logo)
                    ? asset('storage/' . $currentTenant->logo)
                    : asset('assets/sgc-symbol.png') }}"
                alt=""
            >
        </span>

        <div class="hub-context-copy">
            <div class="hub-context-kicker">{{ $isSystemContext ? 'Contexto global' : 'Organização atual' }}</div>

            <h2 class="hub-context-name" id="hub-title">
                {{ $isSystemContext ? 'SGC' : ($currentTenant?->name ?? 'Sua organização') }}
            </h2>

            <div class="hub-context-meta">
                <span>
                    <i class="ph ph-user-circle" aria-hidden="true"></i>
                    {{ $displayName }}
                </span>

                <span>
                    <i class="ph ph-squares-four" aria-hidden="true"></i>
                    <strong data-visible-count>{{ $availablePanelsCount }}</strong>
                    {{ $availablePanelsCount === 1 ? 'portal' : 'portais' }}
                </span>

                @if($tenantLocation)
                    <span class="hub-context-extra">
                        <i class="ph ph-map-pin" aria-hidden="true"></i>
                        {{ $tenantLocation }}
                    </span>
                @endif
            </div>
        </div>

        @if($tenantWebsite)
            <a
                class="hub-context-site"
                href="{{ $tenantWebsite }}"
                target="_blank"
                rel="noopener noreferrer"
            >
                <i class="ph ph-globe" aria-hidden="true"></i>
                Site oficial
            </a>
        @endif
    </header>

    <div class="hub-mobile-screen-title" aria-live="polite">
        <span class="hub-mobile-screen-icon" data-mobile-screen-icon-wrap aria-hidden="true">
            <i class="ph-duotone ph-squares-four" data-mobile-screen-icon></i>
        </span>

        <span class="hub-mobile-screen-copy">
            <strong data-mobile-screen-title>Portais</strong>
            <span data-mobile-screen-subtitle>Escolha a área que deseja acessar</span>
        </span>
    </div>

    <div class="hub-desktop-layout">
        <div class="hub-main-stack">
            <section
                class="hub-screen is-active"
                id="hub-screen-portals"
                data-hub-screen="portals"
                aria-labelledby="hub-portals-title"
            >
                <div class="hub-panel">
                    <header class="hub-panel-head">
                        <span class="hub-panel-icon violet" aria-hidden="true">
                            <i class="ph-duotone ph-squares-four"></i>
                        </span>

                        <div class="hub-panel-title">
                            <h2 id="hub-portals-title">Portais de acesso</h2>
                            <p>As áreas abaixo abrem módulos de trabalho do SGC.</p>
                        </div>

                        <span class="hub-count" data-visible-count>
                            {{ $availablePanelsCount }}
                        </span>
                    </header>

                    <div class="hub-portal-area">
                        <div class="hub-portal-explainer">
                            <div class="hub-portal-explainer-copy">
                                <strong>Escolha um portal para entrar</strong>
                               
                            </div>

                            @if($availablePanelsCount > 5)
                                <div class="hub-search" data-hub-search>
                                    <i class="ph ph-magnifying-glass" aria-hidden="true"></i>

                                    <label class="sr-only" for="hub-panel-search">
                                        Buscar portal
                                    </label>

                                    <input
                                        id="hub-panel-search"
                                        class="hub-search-input"
                                        type="search"
                                        placeholder="Buscar portal"
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
                            @endif
                        </div>

                        <nav
                            class="hub-portal-grid"
                            aria-label="Portais disponíveis"
                            data-hub-list
                        >
                            @if($hasSuperAdmin)
                                @php($superVisual = $resolvePortalVisual('Super Admin'))

                                <a
                                    class="hub-portal-link tone-{{ $superVisual['tone'] }}"
                                    href="{{ url('super-admin') }}"
                                    aria-label="Acessar portal Super Admin"
                                    data-hub-link
                                    data-panel-name="Super Admin"
                                    data-panel-description="{{ $superVisual['hint'] }}"
                                >
                                    <span class="hub-portal-main">
                                        <span class="hub-portal-icon" aria-hidden="true">
                                            <i class="ph-duotone {{ $superVisual['icon'] }}"></i>
                                        </span>

                                        <span class="hub-portal-copy">
                                            <span class="hub-portal-badge">
                                                <i class="ph ph-arrow-square-out" aria-hidden="true"></i>
                                                Portal
                                            </span>

                                            <strong class="hub-portal-name">Super Admin</strong>
                                            <span class="hub-portal-description">
                                                {{ $superVisual['hint'] }}
                                            </span>
                                        </span>
                                    </span>

                                    <span class="hub-portal-cta">
                                        <span>Acessar portal</span>
                                        <i class="ph ph-arrow-right" aria-hidden="true"></i>
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
                                    class="hub-portal-link tone-{{ $visual['tone'] }}"
                                    href="{{ $role['url'] }}"
                                    aria-label="Acessar portal {{ $role['name'] }}"
                                    data-hub-link
                                    data-panel-name="{{ $role['name'] }}"
                                    data-panel-description="{{ $portalDescription }}"
                                >
                                    <span class="hub-portal-main">
                                        <span class="hub-portal-icon" aria-hidden="true">
                                            <i class="ph-duotone {{ $visual['icon'] }}"></i>
                                        </span>

                                        <span class="hub-portal-copy">
                                            <span class="hub-portal-badge">
                                                <i class="ph ph-arrow-square-out" aria-hidden="true"></i>
                                                Portal
                                            </span>

                                            <strong class="hub-portal-name">
                                                {{ $role['name'] }}
                                            </strong>

                                            <span
                                                class="hub-portal-description"
                                                title="{{ $portalDescription }}"
                                            >
                                                {{ $portalDescription }}
                                            </span>
                                        </span>
                                    </span>

                                    <span class="hub-portal-cta">
                                        <span>Acessar portal</span>
                                        <i class="ph ph-arrow-right" aria-hidden="true"></i>
                                    </span>
                                </a>
                            @endforeach

                            @if($availablePanelsCount === 0)
                                <div class="hub-no-results">
                                    Nenhum portal está disponível para o seu perfil.
                                    Solicite acesso a um administrador.
                                </div>
                            @endif

                            <div
                                class="hub-no-results"
                                role="status"
                                aria-live="polite"
                                hidden
                                data-hub-no-results
                            >
                                Nenhum portal corresponde à busca.
                            </div>
                        </nav>
                    </div>
                </div>
            </section>

            <section
                class="hub-screen"
                id="hub-screen-resources"
                data-hub-screen="resources"
                aria-labelledby="hub-resources-title"
            >
                <div class="hub-panel">
                    <header class="hub-panel-head">
                        <span class="hub-panel-icon blue" aria-hidden="true">
                            <i class="ph-duotone ph-compass-tool"></i>
                        </span>

                        <div class="hub-panel-title">
                            <h2 id="hub-resources-title">Recursos</h2>
                            <p>Documentos, conta e serviços complementares.</p>
                        </div>

                        <span class="hub-count">{{ $hubResources->count() }}</span>
                    </header>

                    <nav class="hub-list" aria-label="Recursos disponíveis">
                        @forelse($hubResources as $resource)
                            <a
                                class="hub-list-link tone-{{ $resource['tone'] }}"
                                href="{{ $resource['url'] }}"
                            >
                                <span class="hub-list-icon" aria-hidden="true">
                                    <i class="ph-duotone {{ $resource['icon'] }}"></i>
                                </span>

                                <span class="hub-list-copy">
                                    <strong>{{ $resource['label'] }}</strong>
                                    <span>{{ $resource['description'] }}</span>
                                </span>

                                <i class="hub-list-arrow ph ph-caret-right" aria-hidden="true"></i>
                            </a>
                        @empty
                            <div class="hub-empty">
                                Nenhum recurso adicional foi disponibilizado para esta organização.
                            </div>
                        @endforelse
                    </nav>
                </div>
            </section>
        </div>

        <aside class="hub-side-stack" aria-label="Informações da organização">
            <section
                class="hub-screen"
                id="hub-screen-news"
                data-hub-screen="news"
                aria-labelledby="hub-news-title"
            >
                <div class="hub-panel">
                    <header class="hub-panel-head">
                        <span class="hub-panel-icon amber" aria-hidden="true">
                            <i class="ph-duotone ph-megaphone-simple"></i>
                        </span>

                        <div class="hub-panel-title">
                            <h2 id="hub-news-title">Novidades</h2>
                            <p>Atualizações e informações em destaque.</p>
                        </div>
                    </header>

                    <div class="hub-news-list">
                        @foreach($hubNews as $news)
                            <article class="hub-news-item tone-{{ $news['tone'] }}">
                                <span class="hub-news-icon" aria-hidden="true">
                                    <i class="ph-duotone {{ $news['icon'] }}"></i>
                                </span>

                                <div class="hub-news-copy">
                                    <span class="hub-news-label">{{ $news['label'] }}</span>
                                    <strong class="hub-news-title">{{ $news['title'] }}</strong>

                                    @if(filled($news['description']))
                                        <span class="hub-news-description">
                                            {{ $news['description'] }}
                                        </span>
                                    @endif

                                    @if(filled($news['url']))
                                        <a class="hub-news-action" href="{{ $news['url'] }}">
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
                class="hub-screen"
                id="hub-screen-contact"
                data-hub-screen="contact"
                aria-labelledby="hub-contact-title"
            >
                <div class="hub-panel">
                    <header class="hub-panel-head">
                        <span class="hub-panel-icon green" aria-hidden="true">
                            <i class="ph-duotone ph-chats-circle"></i>
                        </span>

                        <div class="hub-panel-title">
                            <h2 id="hub-contact-title">Contato</h2>
                            <p>Informações e canais oficiais.</p>
                        </div>
                    </header>

                    <div class="hub-org-summary">
                        <div class="hub-org-main">
                            <span class="hub-org-logo" aria-hidden="true">
                                <img
                                    src="{{ !empty($currentTenant?->logo)
                                        ? asset('storage/' . $currentTenant->logo)
                                        : asset('assets/sgc-symbol.png') }}"
                                    alt=""
                                >
                            </span>

                            <div class="hub-org-copy">
                                <strong>{{ $currentTenant->name ?? 'Sua organização' }}</strong>
                                <span>{{ $tenantDescription }}</span>
                            </div>
                        </div>

                        @if($tenantDocument || $tenantLocation)
                            <div class="hub-org-facts">
                                @if($tenantDocument)
                                    <span class="hub-org-fact">
                                        <i class="ph ph-identification-card" aria-hidden="true"></i>
                                        {{ $tenantDocument }}
                                    </span>
                                @endif

                                @if($tenantLocation)
                                    <span class="hub-org-fact">
                                        <i class="ph ph-map-pin" aria-hidden="true"></i>
                                        {{ $tenantLocation }}
                                    </span>
                                @endif
                            </div>
                        @endif
                    </div>

                    @if($tenantWhatsappUrl || $tenantPhoneUrl || $tenantEmailUrl || $tenantWebsite)
                        <div class="hub-list">
                            @if($tenantWhatsappUrl)
                                <a
                                    class="hub-list-link tone-green"
                                    href="{{ $tenantWhatsappUrl }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <span class="hub-list-icon" aria-hidden="true">
                                        <i class="ph-duotone ph-whatsapp-logo"></i>
                                    </span>

                                    <span class="hub-list-copy">
                                        <strong>WhatsApp</strong>
                                        <span>{{ $tenantWhatsapp }}</span>
                                    </span>

                                    <i class="hub-list-arrow ph ph-caret-right" aria-hidden="true"></i>
                                </a>
                            @endif

                            @if($tenantPhoneUrl)
                                <a class="hub-list-link tone-blue" href="{{ $tenantPhoneUrl }}">
                                    <span class="hub-list-icon" aria-hidden="true">
                                        <i class="ph-duotone ph-phone"></i>
                                    </span>

                                    <span class="hub-list-copy">
                                        <strong>Telefone</strong>
                                        <span>{{ $tenantPhone }}</span>
                                    </span>

                                    <i class="hub-list-arrow ph ph-caret-right" aria-hidden="true"></i>
                                </a>
                            @endif

                            @if($tenantEmailUrl)
                                <a class="hub-list-link tone-violet" href="{{ $tenantEmailUrl }}">
                                    <span class="hub-list-icon" aria-hidden="true">
                                        <i class="ph-duotone ph-envelope-simple"></i>
                                    </span>

                                    <span class="hub-list-copy">
                                        <strong>E-mail</strong>
                                        <span>{{ $tenantEmail }}</span>
                                    </span>

                                    <i class="hub-list-arrow ph ph-caret-right" aria-hidden="true"></i>
                                </a>
                            @endif

                            @if($tenantWebsite)
                                <a
                                    class="hub-list-link tone-sky"
                                    href="{{ $tenantWebsite }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <span class="hub-list-icon" aria-hidden="true">
                                        <i class="ph-duotone ph-globe"></i>
                                    </span>

                                    <span class="hub-list-copy">
                                        <strong>Site oficial</strong>
                                        <span>
                                            {{ parse_url($tenantWebsite, PHP_URL_HOST) ?: $tenantWebsite }}
                                        </span>
                                    </span>

                                    <i class="hub-list-arrow ph ph-caret-right" aria-hidden="true"></i>
                                </a>
                            @endif
                        </div>
                    @else
                        <div class="hub-empty">
                            Os canais oficiais ainda não foram informados pela organização.
                        </div>
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
        data-tone="violet"
        aria-current="page"
    >
        <i class="ph-duotone ph-squares-four" aria-hidden="true"></i>
        <span>Portais</span>
    </button>

    <button
        class="hub-bottom-item"
        type="button"
        data-hub-screen-target="news"
        data-tone="amber"
    >
        <i class="ph-duotone ph-megaphone-simple" aria-hidden="true"></i>
        <span>Novidades</span>
    </button>

    <button
        class="hub-bottom-item"
        type="button"
        data-hub-screen-target="resources"
        data-tone="blue"
    >
        <i class="ph-duotone ph-compass-tool" aria-hidden="true"></i>
        <span>Recursos</span>
    </button>

    <button
        class="hub-bottom-item"
        type="button"
        data-hub-screen-target="contact"
        data-tone="green"
    >
        <i class="ph-duotone ph-chats-circle" aria-hidden="true"></i>
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

    const screenMeta = {
        portals: {
            title:'Portais',
            subtitle:'Escolha a área que deseja acessar',
            icon:'ph-squares-four',
            tone:'violet',
        },
        news: {
            title:'Novidades',
            subtitle:'Atualizações da organização',
            icon:'ph-megaphone-simple',
            tone:'amber',
        },
        resources: {
            title:'Recursos',
            subtitle:'Documentos e serviços disponíveis',
            icon:'ph-compass-tool',
            tone:'blue',
        },
        contact: {
            title:'Contato',
            subtitle:'Informações e canais oficiais',
            icon:'ph-chats-circle',
            tone:'green',
        },
    };

    const screenOrder = ['portals','news','resources','contact'];
    const screens = [...root.querySelectorAll('[data-hub-screen]')];
    const navItems = [...document.querySelectorAll('[data-hub-screen-target]')];

    const mobileTitle = document.querySelector('[data-mobile-screen-title]');
    const mobileSubtitle = document.querySelector('[data-mobile-screen-subtitle]');
    const mobileIcon = document.querySelector('[data-mobile-screen-icon]');
    const mobileIconWrap = document.querySelector('[data-mobile-screen-icon-wrap]');

    const isMobile = () => window.matchMedia(MOBILE_QUERY).matches;

    function toneVars(tone) {
        return {
            color:`var(--hub-${tone})`,
            soft:`var(--hub-${tone}-soft)`,
        };
    }

    function setScreen(name, {push=false, resetScroll=true} = {}) {
        const next = screenMeta[name] ? name : 'portals';
        const currentItem = navItems.find(item => item.classList.contains('is-active'));
        const current = currentItem?.dataset.hubScreenTarget || 'portals';

        const currentIndex = screenOrder.indexOf(current);
        const nextIndex = screenOrder.indexOf(next);
        root.style.setProperty(
            '--hub-screen-shift',
            nextIndex < currentIndex ? '-8px' : '8px'
        );

        screens.forEach(screen => {
            const active = screen.dataset.hubScreen === next;

            if (isMobile()) {
                screen.classList.toggle('is-active', active);
                screen.hidden = !active;
            } else {
                screen.hidden = false;
                screen.classList.add('is-active');
            }
        });

        navItems.forEach(item => {
            const active = item.dataset.hubScreenTarget === next;
            item.classList.toggle('is-active', active);

            if (active) {
                item.setAttribute('aria-current','page');
            } else {
                item.removeAttribute('aria-current');
            }
        });

        const meta = screenMeta[next];

        if (mobileTitle) mobileTitle.textContent = meta.title;
        if (mobileSubtitle) mobileSubtitle.textContent = meta.subtitle;

        if (mobileIcon) {
            mobileIcon.className = `ph-duotone ${meta.icon}`;
        }

        if (mobileIconWrap) {
            const vars = toneVars(meta.tone);
            mobileIconWrap.style.background = vars.soft;
            mobileIconWrap.style.color = vars.color;
        }

        if (
            push
            && isMobile()
            && history.state?.[SCREEN_STATE_KEY] !== next
        ) {
            history.pushState(
                {
                    ...(history.state || {}),
                    [SCREEN_STATE_KEY]:next,
                },
                ''
            );
        }

        if (isMobile() && resetScroll) {
            window.scrollTo({top:0,behavior:'auto'});
        }
    }

    function ensureScreenHistory() {
        if (!isMobile()) return;

        const current = history.state?.[SCREEN_STATE_KEY];

        if (!screenMeta[current]) {
            history.replaceState(
                {
                    ...(history.state || {}),
                    [SCREEN_STATE_KEY]:'portals',
                },
                ''
            );
        }
    }

    navItems.forEach(item => {
        item.addEventListener('click', () => {
            const next = item.dataset.hubScreenTarget;
            const current = history.state?.[SCREEN_STATE_KEY] || 'portals';

            if (next === current && isMobile()) return;

            setScreen(next,{push:true,resetScroll:true});
        });
    });

    window.addEventListener('popstate', event => {
        if (!isMobile()) return;

        setScreen(
            event.state?.[SCREEN_STATE_KEY] || 'portals',
            {push:false,resetScroll:true}
        );
    });

    window.addEventListener(
        'resize',
        () => {
            if (isMobile()) {
                ensureScreenHistory();
                setScreen(
                    history.state?.[SCREEN_STATE_KEY] || 'portals',
                    {push:false,resetScroll:false}
                );
            } else {
                screens.forEach(screen => {
                    screen.hidden = false;
                    screen.classList.add('is-active');
                });
            }
        },
        {passive:true}
    );

    /* ---------------------------------------------------------
       Busca de portais
       --------------------------------------------------------- */

    const portalLinks = [...root.querySelectorAll('[data-hub-link]')];
    const search = root.querySelector('[data-hub-search]');
    const searchInput = root.querySelector('[data-hub-search-input]');
    const searchClear = root.querySelector('[data-hub-search-clear]');
    const noResults = root.querySelector('[data-hub-no-results]');
    const visibleCounts = [...root.querySelectorAll('[data-visible-count]')];

    const normalize = value => String(value || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g,'')
        .toLocaleLowerCase('pt-BR')
        .trim();

    function filterPortals() {
        if (!searchInput) return;

        const query = normalize(searchInput.value);
        let matches = 0;

        portalLinks.forEach(link => {
            const text = normalize(
                `${link.dataset.panelName || ''} ${link.dataset.panelDescription || ''}`
            );

            const match = !query || text.includes(query);
            link.hidden = !match;
            if (match) matches += 1;
        });

        search?.classList.toggle('has-value',Boolean(searchInput.value));
        if (noResults) noResults.hidden = matches !== 0;

        visibleCounts.forEach(counter => {
            counter.textContent = String(matches);
        });
    }

    searchInput?.addEventListener('input',filterPortals);

    searchClear?.addEventListener('click',() => {
        searchInput.value = '';
        filterPortals();

        if (!isMobile()) {
            searchInput.focus();
        } else {
            searchInput.blur();
        }
    });

    /* ---------------------------------------------------------
       Animação forte de clique antes de abrir o portal
       --------------------------------------------------------- */

    function resetPortalOpeningState() {
        portalLinks.forEach(link => {
            link.classList.remove('is-opening');
            link.removeAttribute('aria-busy');
        });
    }

    portalLinks.forEach(link => {
        link.addEventListener('click',event => {
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

            link.classList.add('is-opening');
            link.setAttribute('aria-busy','true');

            const href = link.href;
            const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            const delay = reducedMotion ? 60 : 320;

            window.setTimeout(() => {
                window.location.assign(href);
            },delay);
        });
    });

    /* ---------------------------------------------------------
       Inicialização
       --------------------------------------------------------- */

    ensureScreenHistory();

    setScreen(
        isMobile()
            ? (history.state?.[SCREEN_STATE_KEY] || 'portals')
            : 'portals',
        {push:false,resetScroll:false}
    );

    filterPortals();

    window.addEventListener('pageshow',() => {
        resetPortalOpeningState();
        filterPortals();

        if (isMobile()) {
            setScreen(
                history.state?.[SCREEN_STATE_KEY] || 'portals',
                {push:false,resetScroll:false}
            );
        } else {
            screens.forEach(screen => {
                screen.hidden = false;
                screen.classList.add('is-active');
            });
        }
    });
})();
</script>
@endsection