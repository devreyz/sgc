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
@section(
    'page-subtitle',
    ($currentTenant->name ?? 'Sua organização') . ' · Portais, recursos e comunicação.'
)
@section('user-role', 'Hub institucional')

@section('content')
<style>
    .hub-page {
        --hub-green:#168a4d;
        --hub-green-soft:#eaf8ef;
        --hub-blue:#2563eb;
        --hub-blue-soft:#eef4ff;
        --hub-sky:#0284c7;
        --hub-sky-soft:#edf8fe;
        --hub-violet:#7c3aed;
        --hub-violet-soft:#f4f0ff;
        --hub-amber:#c87408;
        --hub-amber-soft:#fff7e8;
        --hub-red:#cf3f3f;
        --hub-red-soft:#fff0f0;
        --hub-slate:#64748b;
        --hub-slate-soft:#f1f5f9;

        --hub-surface:var(--color-surface,#fff);
        --hub-soft:var(--color-surface-soft,#f8faf9);
        --hub-border:var(--color-border,#dce7e0);
        --hub-border-strong:var(--color-border-strong,#c8d6cd);
        --hub-text:var(--color-text,#102018);
        --hub-text-2:var(--color-text-secondary,#52645a);
        --hub-text-3:var(--color-text-muted,#809087);

        width:min(100%,calc(100dvw - 34px),1480px);
        min-width:0;
        grid-column:1 / -1;
        margin:0 auto;
        padding-bottom:18px;
    }

    .hub-page,
    .hub-page *,
    .hub-page *::before,
    .hub-page *::after {
        box-sizing:border-box;
    }

    .hub-page a {
        -webkit-tap-highlight-color:transparent;
    }

    /* =========================================================
       CABEÇALHO CONTEXTUAL — pequeno e claro
       ========================================================= */

    .hub-context {
        display:grid;
        min-width:0;
        min-height:68px;
        grid-template-columns:auto minmax(0,1fr) auto;
        gap:.62rem;
        align-items:center;
        margin-bottom:.7rem;
        padding:.56rem .64rem;
        border:1px solid rgba(22,138,77,.13);
        border-radius:15px;
        background:
            radial-gradient(circle at 100% 0,rgba(124,58,237,.07),transparent 15rem),
            linear-gradient(145deg,#fff,#fbfdfc);
        box-shadow:0 4px 16px rgba(15,35,24,.035);
    }

    .hub-context-logo {
        display:grid;
        width:43px;
        height:43px;
        place-items:center;
        overflow:hidden;
        border:1px solid rgba(22,138,77,.13);
        border-radius:11px;
        background:var(--hub-green-soft);
    }

    .hub-context-logo img {
        width:28px;
        height:28px;
        object-fit:contain;
    }

    .hub-context-copy {
        min-width:0;
    }

    .hub-context-kicker {
        display:flex;
        align-items:center;
        gap:.28rem;
        color:var(--hub-green);
        font-size:.52rem;
        font-weight:880;
        letter-spacing:.055em;
        text-transform:uppercase;
    }

    .hub-context-kicker::before {
        width:6px;
        height:6px;
        border-radius:999px;
        background:var(--hub-green);
        content:"";
        box-shadow:0 0 0 3px var(--hub-green-soft);
    }

    .hub-context-name {
        margin:.08rem 0 0;
        overflow:hidden;
        color:var(--hub-text);
        font-size:.86rem;
        font-weight:900;
        letter-spacing:-.025em;
        line-height:1.25;
        text-overflow:ellipsis;
        white-space:nowrap;
    }

    .hub-context-meta {
        display:flex;
        gap:.24rem .55rem;
        align-items:center;
        flex-wrap:wrap;
        margin-top:.15rem;
        color:var(--hub-text-3);
        font-size:.56rem;
        font-weight:680;
    }

    .hub-context-meta span {
        display:inline-flex;
        align-items:center;
        gap:.16rem;
    }

    .hub-context-meta .ph {
        color:var(--hub-blue);
        font-size:.68rem;
    }

    .hub-context-site {
        display:inline-flex;
        min-height:35px;
        align-items:center;
        justify-content:center;
        gap:.25rem;
        padding:.34rem .48rem;
        border:1px solid var(--hub-border);
        border-radius:9px;
        background:#fff;
        color:var(--hub-text-2);
        font-size:.59rem;
        font-weight:790;
        text-decoration:none;
        transition:background .14s ease,border-color .14s ease,color .14s ease;
    }

    .hub-context-site:hover,
    .hub-context-site:focus-visible {
        border-color:rgba(37,99,235,.18);
        background:var(--hub-blue-soft);
        color:var(--hub-blue);
        outline:none;
    }

    /* =========================================================
       LAYOUT DESKTOP — portais dominam, demais conteúdos separados
       ========================================================= */

    .hub-desktop-layout {
        display:grid;
        min-width:0;
        grid-template-columns:minmax(0,1.7fr) minmax(300px,.7fr);
        gap:.72rem;
        align-items:start;
    }

    .hub-main-stack,
    .hub-side-stack {
        display:grid;
        min-width:0;
        gap:.72rem;
    }

    .hub-side-stack {
        position:sticky;
        top:var(--app-sticky-top,90px);
    }

    .hub-screen {
        min-width:0;
    }

    .hub-panel {
        min-width:0;
        overflow:hidden;
        border:1px solid var(--hub-border);
        border-radius:14px;
        background:#fff;
        box-shadow:0 3px 13px rgba(15,35,24,.032);
    }

    .hub-panel-head {
        display:grid;
        min-width:0;
        min-height:55px;
        grid-template-columns:auto minmax(0,1fr) auto;
        gap:.46rem;
        align-items:center;
        padding:.48rem .56rem;
        border-bottom:1px solid var(--hub-border);
        background:linear-gradient(180deg,var(--hub-soft),#fff);
    }

    .hub-panel-icon {
        display:inline-flex;
        width:34px;
        height:34px;
        align-items:center;
        justify-content:center;
        border-radius:9px;
        background:var(--hub-slate-soft);
        color:var(--hub-slate);
    }

    .hub-panel-icon.violet { background:var(--hub-violet-soft); color:var(--hub-violet); }
    .hub-panel-icon.amber  { background:var(--hub-amber-soft);  color:var(--hub-amber); }
    .hub-panel-icon.blue   { background:var(--hub-blue-soft);   color:var(--hub-blue); }
    .hub-panel-icon.green  { background:var(--hub-green-soft);  color:var(--hub-green); }

    .hub-panel-icon .ph-duotone {
        font-size:16px;
    }

    .hub-panel-title {
        min-width:0;
    }

    .hub-panel-title h2,
    .hub-panel-title h3,
    .hub-panel-title p {
        margin:0;
    }

    .hub-panel-title h2,
    .hub-panel-title h3 {
        overflow:hidden;
        color:var(--hub-text);
        font-size:.71rem;
        font-weight:860;
        line-height:1.25;
        text-overflow:ellipsis;
        white-space:nowrap;
    }

    .hub-panel-title p {
        overflow:hidden;
        margin-top:.04rem;
        color:var(--hub-text-3);
        font-size:.54rem;
        line-height:1.35;
        text-overflow:ellipsis;
        white-space:nowrap;
    }

    .hub-count {
        display:inline-flex;
        min-width:29px;
        min-height:23px;
        align-items:center;
        justify-content:center;
        padding:.1rem .32rem;
        border-radius:999px;
        background:var(--hub-violet-soft);
        color:var(--hub-violet);
        font-size:.54rem;
        font-weight:860;
    }

    /* =========================================================
       PORTAIS — parecem links, não cards informativos
       ========================================================= */

    .hub-portal-area {
        padding:.52rem;
        background:var(--hub-soft);
    }

    .hub-portal-explainer {
        display:flex;
        min-width:0;
        align-items:center;
        justify-content:space-between;
        gap:.55rem;
        margin-bottom:.46rem;
        padding:.38rem .42rem;
        border:1px solid var(--hub-border);
        border-radius:9px;
        background:#fff;
    }

    .hub-portal-explainer-copy {
        min-width:0;
    }

    .hub-portal-explainer strong,
    .hub-portal-explainer span {
        display:block;
    }

    .hub-portal-explainer strong {
        color:var(--hub-text);
        font-size:.62rem;
        font-weight:820;
    }

    .hub-portal-explainer span {
        margin-top:.03rem;
        color:var(--hub-text-3);
        font-size:.52rem;
        line-height:1.4;
    }

    .hub-search {
        position:relative;
        width:min(220px,100%);
        flex:0 0 auto;
    }

    .hub-search > .ph {
        position:absolute;
        top:50%;
        left:.54rem;
        color:var(--hub-text-3);
        font-size:.7rem;
        transform:translateY(-50%);
        pointer-events:none;
    }

    .hub-search-input {
        width:100%;
        min-height:36px;
        padding:.3rem 1.9rem .3rem 1.65rem;
        border:1px solid var(--hub-border);
        border-radius:8px;
        outline:none;
        background:#fff;
        color:var(--hub-text);
        font:inherit;
        font-size:.59rem;
    }

    .hub-search-input:focus {
        border-color:rgba(124,58,237,.34);
        box-shadow:0 0 0 3px rgba(124,58,237,.06);
    }

    .hub-search-clear {
        position:absolute;
        top:50%;
        right:.22rem;
        display:none;
        width:28px;
        height:28px;
        place-items:center;
        border:0;
        border-radius:7px;
        background:transparent;
        color:var(--hub-text-3);
        cursor:pointer;
        transform:translateY(-50%);
    }

    .hub-search.has-value .hub-search-clear {
        display:grid;
    }

    .hub-portal-grid {
        display:grid;
        min-width:0;
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:.45rem;
    }

    .hub-portal-link {
        --portal-tone:var(--hub-green);
        --portal-soft:var(--hub-green-soft);

        position:relative;
        display:grid;
        min-width:0;
        min-height:126px;
        grid-template-rows:1fr auto;
        overflow:hidden;
        border:1px solid var(--hub-border);
        border-left:3px solid var(--portal-tone);
        border-radius:11px;
        background:#fff;
        color:inherit;
        text-decoration:none;
        isolation:isolate;
        box-shadow:0 1px 4px rgba(15,35,24,.025);
        transition:
            border-color .14s ease,
            box-shadow .14s ease,
            transform .14s ease,
            background .14s ease;
    }

    .hub-portal-link.tone-blue   { --portal-tone:var(--hub-blue);   --portal-soft:var(--hub-blue-soft); }
    .hub-portal-link.tone-sky    { --portal-tone:var(--hub-sky);    --portal-soft:var(--hub-sky-soft); }
    .hub-portal-link.tone-violet { --portal-tone:var(--hub-violet); --portal-soft:var(--hub-violet-soft); }
    .hub-portal-link.tone-amber  { --portal-tone:var(--hub-amber);  --portal-soft:var(--hub-amber-soft); }
    .hub-portal-link.tone-red    { --portal-tone:var(--hub-red);    --portal-soft:var(--hub-red-soft); }
    .hub-portal-link.tone-slate  { --portal-tone:var(--hub-slate);  --portal-soft:var(--hub-slate-soft); }

    .hub-portal-link:hover,
    .hub-portal-link:focus-visible {
        border-color:color-mix(in srgb,var(--portal-tone) 28%,var(--hub-border));
        background:linear-gradient(145deg,#fff,color-mix(in srgb,var(--portal-soft) 28%,#fff));
        box-shadow:0 8px 20px rgba(15,35,24,.07);
        outline:none;
        transform:translateY(-1px);
    }

    .hub-portal-main {
        display:grid;
        min-width:0;
        grid-template-columns:auto minmax(0,1fr);
        gap:.46rem;
        align-content:start;
        padding:.52rem;
        background:
            radial-gradient(
                circle at 100% 0,
                color-mix(in srgb,var(--portal-tone) 8%,transparent),
                transparent 9rem
            );
    }

    .hub-portal-icon {
        display:inline-flex;
        width:41px;
        height:41px;
        align-items:center;
        justify-content:center;
        border:1px solid color-mix(in srgb,var(--portal-tone) 10%,transparent);
        border-radius:10px;
        background:var(--portal-soft);
        color:var(--portal-tone);
        transition:transform .18s ease,box-shadow .18s ease;
    }

    .hub-portal-icon .ph-duotone {
        font-size:19px;
    }

    .hub-portal-copy {
        min-width:0;
    }

    .hub-portal-badge {
        display:inline-flex;
        min-height:18px;
        align-items:center;
        gap:.13rem;
        padding:.05rem .24rem;
        border-radius:999px;
        background:var(--portal-soft);
        color:var(--portal-tone);
        font-size:.46rem;
        font-weight:920;
        letter-spacing:.055em;
        text-transform:uppercase;
    }

    .hub-portal-name {
        display:block;
        margin-top:.14rem;
        overflow:hidden;
        color:var(--hub-text);
        font-size:.74rem;
        font-weight:900;
        letter-spacing:-.015em;
        line-height:1.26;
        text-overflow:ellipsis;
        white-space:nowrap;
    }

    .hub-portal-description {
        display:-webkit-box;
        overflow:hidden;
        margin-top:.08rem;
        color:var(--hub-text-3);
        font-size:.55rem;
        line-height:1.42;
        -webkit-box-orient:vertical;
        -webkit-line-clamp:2;
    }

    .hub-portal-cta {
        display:flex;
        min-height:35px;
        align-items:center;
        justify-content:space-between;
        gap:.4rem;
        padding:.3rem .48rem;
        border-top:1px solid color-mix(in srgb,var(--portal-tone) 10%,var(--hub-border));
        background:var(--portal-soft);
        color:var(--portal-tone);
        font-size:.57rem;
        font-weight:880;
        transition:background .16s ease,color .16s ease;
    }

    .hub-portal-cta .ph {
        font-size:.72rem;
        transition:transform .16s ease;
    }

    .hub-portal-link:hover .hub-portal-cta .ph,
    .hub-portal-link:focus-visible .hub-portal-cta .ph {
        transform:translateX(2px);
    }

    /* Clique: halo + brilho bem visível antes da navegação. */
    .hub-portal-link.is-opening {
        border-color:color-mix(in srgb,var(--portal-tone) 64%,var(--hub-border));
        background:color-mix(in srgb,var(--portal-soft) 56%,#fff);
        box-shadow:
            0 0 0 3px color-mix(in srgb,var(--portal-tone) 14%,transparent),
            0 14px 34px color-mix(in srgb,var(--portal-tone) 20%,transparent);
        pointer-events:none;
        transform:translateY(-1px) scale(.995);
        animation:hub-click-pulse .48s ease both;
    }

    .hub-portal-link.is-opening .hub-portal-icon {
        box-shadow:0 0 0 5px color-mix(in srgb,var(--portal-tone) 11%,transparent);
        transform:scale(1.08);
    }

    .hub-portal-link.is-opening .hub-portal-cta {
        background:var(--portal-tone);
        color:#fff;
    }

    .hub-portal-link.is-opening::after {
        position:absolute;
        z-index:4;
        top:-35%;
        bottom:-35%;
        left:-55%;
        width:42%;
        background:
            linear-gradient(
                105deg,
                transparent 0%,
                rgba(255,255,255,0) 12%,
                rgba(255,255,255,.78) 40%,
                color-mix(in srgb,var(--portal-tone) 28%,#fff) 50%,
                rgba(255,255,255,.92) 60%,
                rgba(255,255,255,0) 88%,
                transparent 100%
            );
        content:"";
        filter:blur(.2px);
        opacity:.95;
        pointer-events:none;
        transform:skewX(-17deg);
        animation:hub-portal-shine .72s cubic-bezier(.2,.75,.2,1) both;
    }

    @keyframes hub-portal-shine {
        from { left:-55%; }
        to { left:125%; }
    }

    @keyframes hub-click-pulse {
        0% { filter:saturate(1); }
        45% { filter:saturate(1.18) brightness(1.025); }
        100% { filter:saturate(1.06); }
    }

    .hub-portal-link[hidden],
    .hub-no-results[hidden] {
        display:none !important;
    }

    .hub-no-results {
        grid-column:1 / -1;
        padding:.9rem;
        border:1px dashed var(--hub-border-strong);
        border-radius:9px;
        background:#fff;
        color:var(--hub-text-3);
        font-size:.59rem;
        line-height:1.5;
        text-align:center;
    }

    /* =========================================================
       RECURSOS — deliberadamente parecem lista, não portais
       ========================================================= */

    .hub-list {
        display:grid;
        min-width:0;
        gap:.3rem;
        padding:.44rem;
    }

    .hub-list-link {
        --item-tone:var(--hub-slate);
        --item-soft:var(--hub-slate-soft);

        display:grid;
        min-width:0;
        min-height:49px;
        grid-template-columns:auto minmax(0,1fr) auto;
        gap:.4rem;
        align-items:center;
        padding:.36rem .4rem;
        border:1px solid transparent;
        border-radius:9px;
        color:inherit;
        text-decoration:none;
        transition:background .14s ease,border-color .14s ease;
    }

    .hub-list-link.tone-green  { --item-tone:var(--hub-green);  --item-soft:var(--hub-green-soft); }
    .hub-list-link.tone-blue   { --item-tone:var(--hub-blue);   --item-soft:var(--hub-blue-soft); }
    .hub-list-link.tone-sky    { --item-tone:var(--hub-sky);    --item-soft:var(--hub-sky-soft); }
    .hub-list-link.tone-violet { --item-tone:var(--hub-violet); --item-soft:var(--hub-violet-soft); }
    .hub-list-link.tone-amber  { --item-tone:var(--hub-amber);  --item-soft:var(--hub-amber-soft); }
    .hub-list-link.tone-red    { --item-tone:var(--hub-red);    --item-soft:var(--hub-red-soft); }
    .hub-list-link.tone-slate  { --item-tone:var(--hub-slate);  --item-soft:var(--hub-slate-soft); }

    .hub-list-link:hover,
    .hub-list-link:focus-visible {
        border-color:var(--hub-border);
        background:var(--hub-soft);
        outline:none;
    }

    .hub-list-icon {
        display:inline-flex;
        width:34px;
        height:34px;
        align-items:center;
        justify-content:center;
        border-radius:9px;
        background:var(--item-soft);
        color:var(--item-tone);
    }

    .hub-list-icon .ph-duotone {
        font-size:15px;
    }

    .hub-list-copy {
        min-width:0;
    }

    .hub-list-copy strong,
    .hub-list-copy span {
        display:block;
        overflow:hidden;
        text-overflow:ellipsis;
        white-space:nowrap;
    }

    .hub-list-copy strong {
        color:var(--hub-text);
        font-size:.61rem;
        font-weight:810;
    }

    .hub-list-copy span {
        margin-top:.02rem;
        color:var(--hub-text-3);
        font-size:.51rem;
    }

    .hub-list-arrow {
        color:var(--hub-text-3);
        font-size:.7rem;
    }

    .hub-empty {
        padding:.75rem;
        color:var(--hub-text-3);
        font-size:.56rem;
        line-height:1.5;
        text-align:center;
    }

    /* =========================================================
       NOVIDADES — leitura, não acesso principal
       ========================================================= */

    .hub-news-list {
        display:grid;
        min-width:0;
        gap:.32rem;
        padding:.44rem;
    }

    .hub-news-item {
        --news-tone:var(--hub-blue);
        --news-soft:var(--hub-blue-soft);

        display:grid;
        min-width:0;
        grid-template-columns:auto minmax(0,1fr);
        gap:.4rem;
        padding:.4rem;
        border:1px solid var(--hub-border);
        border-left:3px solid var(--news-tone);
        border-radius:9px;
        background:linear-gradient(90deg,var(--news-soft),#fff 72%);
    }

    .hub-news-item.tone-green  { --news-tone:var(--hub-green);  --news-soft:var(--hub-green-soft); }
    .hub-news-item.tone-blue   { --news-tone:var(--hub-blue);   --news-soft:var(--hub-blue-soft); }
    .hub-news-item.tone-sky    { --news-tone:var(--hub-sky);    --news-soft:var(--hub-sky-soft); }
    .hub-news-item.tone-violet { --news-tone:var(--hub-violet); --news-soft:var(--hub-violet-soft); }
    .hub-news-item.tone-amber  { --news-tone:var(--hub-amber);  --news-soft:var(--hub-amber-soft); }
    .hub-news-item.tone-red    { --news-tone:var(--hub-red);    --news-soft:var(--hub-red-soft); }
    .hub-news-item.tone-slate  { --news-tone:var(--hub-slate);  --news-soft:var(--hub-slate-soft); }

    .hub-news-icon {
        display:inline-flex;
        width:33px;
        height:33px;
        align-items:center;
        justify-content:center;
        border-radius:8px;
        background:#fff;
        color:var(--news-tone);
    }

    .hub-news-icon .ph-duotone {
        font-size:15px;
    }

    .hub-news-copy {
        min-width:0;
    }

    .hub-news-label {
        display:block;
        color:var(--news-tone);
        font-size:.46rem;
        font-weight:900;
        letter-spacing:.045em;
        text-transform:uppercase;
    }

    .hub-news-title {
        display:block;
        margin-top:.04rem;
        color:var(--hub-text);
        font-size:.62rem;
        font-weight:830;
        line-height:1.35;
    }

    .hub-news-description {
        display:-webkit-box;
        overflow:hidden;
        margin-top:.06rem;
        color:var(--hub-text-2);
        font-size:.52rem;
        line-height:1.45;
        -webkit-box-orient:vertical;
        -webkit-line-clamp:3;
    }

    .hub-news-action {
        display:inline-flex;
        align-items:center;
        gap:.13rem;
        margin-top:.18rem;
        color:var(--news-tone);
        font-size:.51rem;
        font-weight:800;
        text-decoration:none;
    }

    /* =========================================================
       CONTATO + organização
       ========================================================= */

    .hub-org-summary {
        display:grid;
        gap:.38rem;
        padding:.44rem;
        border-bottom:1px solid var(--hub-border);
        background:var(--hub-soft);
    }

    .hub-org-main {
        display:grid;
        min-width:0;
        grid-template-columns:auto minmax(0,1fr);
        gap:.38rem;
        align-items:center;
    }

    .hub-org-logo {
        display:grid;
        width:36px;
        height:36px;
        place-items:center;
        overflow:hidden;
        border-radius:9px;
        background:var(--hub-green-soft);
    }

    .hub-org-logo img {
        width:23px;
        height:23px;
        object-fit:contain;
    }

    .hub-org-copy {
        min-width:0;
    }

    .hub-org-copy strong {
        display:block;
        overflow:hidden;
        color:var(--hub-text);
        font-size:.61rem;
        font-weight:840;
        text-overflow:ellipsis;
        white-space:nowrap;
    }

    .hub-org-copy span {
        display:-webkit-box;
        overflow:hidden;
        margin-top:.02rem;
        color:var(--hub-text-3);
        font-size:.5rem;
        line-height:1.38;
        -webkit-box-orient:vertical;
        -webkit-line-clamp:2;
    }

    .hub-org-facts {
        display:flex;
        gap:.22rem;
        flex-wrap:wrap;
    }

    .hub-org-fact {
        display:inline-flex;
        min-height:22px;
        align-items:center;
        gap:.16rem;
        padding:.12rem .26rem;
        border:1px solid var(--hub-border);
        border-radius:999px;
        background:#fff;
        color:var(--hub-text-2);
        font-size:.48rem;
        font-weight:700;
    }

    .hub-socials {
        display:flex;
        gap:.24rem;
        flex-wrap:wrap;
        padding:0 .44rem .44rem;
    }

    .hub-social-link {
        display:inline-flex;
        min-height:30px;
        align-items:center;
        gap:.18rem;
        padding:.26rem .34rem;
        border:1px solid var(--hub-border);
        border-radius:8px;
        background:#fff;
        color:var(--hub-text-2);
        font-size:.5rem;
        font-weight:750;
        text-decoration:none;
    }

    /* =========================================================
       MOBILE — cada item da bottom bar é uma TELA
       ========================================================= */

    .hub-mobile-screen-title,
    .hub-bottom-nav {
        display:none;
    }

    @media(max-width:1080px) and (min-width:821px) {
        .hub-desktop-layout {
            grid-template-columns:minmax(0,1.4fr) minmax(270px,.65fr);
        }

        .hub-portal-grid {
            grid-template-columns:1fr;
        }
    }

    @media(max-width:820px) {
        body.hub-app-navigation .app-nav-layer {
            display:none !important;
        }

        body.hub-app-navigation.has-app-nav .bento-container {
            padding-bottom:calc(70px + 12px + env(safe-area-inset-bottom,0px)) !important;
        }

        .hub-page {
            width:min(100%,calc(100dvw - 16px));
            padding-bottom:calc(70px + 14px + env(safe-area-inset-bottom,0px));
        }

        .hub-context {
            min-height:58px;
            grid-template-columns:auto minmax(0,1fr);
            gap:.46rem;
            margin-bottom:.4rem;
            padding:.42rem .46rem;
            border-radius:12px;
        }

        .hub-context-logo {
            width:38px;
            height:38px;
            border-radius:10px;
        }

        .hub-context-logo img {
            width:24px;
            height:24px;
        }

        .hub-context-kicker,
        .hub-context-site,
        .hub-context-extra {
            display:none !important;
        }

        .hub-context-name {
            font-size:.75rem;
        }

        .hub-context-meta {
            margin-top:.08rem;
            font-size:.5rem;
        }

        .hub-mobile-screen-title {
            display:grid;
            min-height:47px;
            grid-template-columns:auto minmax(0,1fr);
            gap:.38rem;
            align-items:center;
            margin-bottom:.4rem;
            padding:.38rem .42rem;
            border:1px solid var(--hub-border);
            border-radius:11px;
            background:#fff;
            box-shadow:0 2px 9px rgba(15,35,24,.025);
        }

        .hub-mobile-screen-icon {
            display:inline-flex;
            width:32px;
            height:32px;
            align-items:center;
            justify-content:center;
            border-radius:8px;
            background:var(--hub-violet-soft);
            color:var(--hub-violet);
        }

        .hub-mobile-screen-icon .ph-duotone {
            font-size:15px;
        }

        .hub-mobile-screen-copy {
            min-width:0;
        }

        .hub-mobile-screen-copy strong,
        .hub-mobile-screen-copy span {
            display:block;
        }

        .hub-mobile-screen-copy strong {
            color:var(--hub-text);
            font-size:.64rem;
            font-weight:850;
        }

        .hub-mobile-screen-copy span {
            overflow:hidden;
            margin-top:.02rem;
            color:var(--hub-text-3);
            font-size:.5rem;
            text-overflow:ellipsis;
            white-space:nowrap;
        }

        .hub-desktop-layout,
        .hub-main-stack,
        .hub-side-stack {
            display:block;
        }

        .hub-side-stack {
            position:static;
        }

        .hub-screen {
            display:none;
        }

        .hub-screen.is-active {
            display:block;
            animation:hub-screen-enter .18s ease both;
        }

        .hub-panel {
            border-radius:12px;
            box-shadow:0 2px 10px rgba(15,35,24,.025);
        }

        .hub-panel-head {
            min-height:50px;
            padding:.42rem .46rem;
        }

        .hub-panel-title p {
            display:none;
        }

        .hub-portal-area {
            padding:.42rem;
        }

        .hub-portal-explainer {
            display:block;
            padding:.36rem;
        }

        .hub-portal-explainer-copy {
            margin-bottom:.32rem;
        }

        .hub-search {
            width:100%;
        }

        .hub-search-input {
            min-height:39px;
            font-size:.6rem;
        }

        .hub-portal-grid {
            grid-template-columns:1fr;
            gap:.38rem;
        }

        .hub-portal-link {
            min-height:105px;
            border-radius:10px;
        }

        .hub-portal-main {
            gap:.4rem;
            padding:.46rem;
        }

        .hub-portal-icon {
            width:38px;
            height:38px;
        }

        .hub-portal-name {
            font-size:.68rem;
        }

        .hub-portal-description {
            font-size:.53rem;
            -webkit-line-clamp:2;
        }

        .hub-portal-cta {
            min-height:34px;
            padding:.28rem .44rem;
            font-size:.55rem;
        }

        .hub-list,
        .hub-news-list {
            gap:.3rem;
            padding:.4rem;
        }

        .hub-list-link {
            min-height:53px;
            padding:.38rem;
        }

        .hub-list-icon {
            width:36px;
            height:36px;
        }

        .hub-news-item {
            min-height:75px;
            padding:.4rem;
        }

        .hub-news-title {
            font-size:.63rem;
        }

        .hub-news-description {
            font-size:.52rem;
            -webkit-line-clamp:4;
        }

        .hub-bottom-nav {
            position:fixed;
            z-index:690;
            right:7px;
            bottom:max(7px,env(safe-area-inset-bottom,0px));
            left:7px;
            display:grid;
            min-height:62px;
            grid-template-columns:repeat(4,minmax(0,1fr));
            gap:3px;
            padding:5px;
            border:1px solid rgba(23,53,42,.12);
            border-radius:17px;
            background:rgba(255,255,255,.98);
            box-shadow:
                0 14px 34px rgba(17,49,34,.18),
                0 3px 9px rgba(17,49,34,.08);
            backdrop-filter:blur(12px);
        }

        .hub-bottom-item {
            --nav-tone:var(--hub-slate);

            display:flex;
            min-width:0;
            min-height:50px;
            flex-direction:column;
            align-items:center;
            justify-content:center;
            gap:3px;
            padding:4px 3px;
            border:0;
            border-radius:12px;
            background:transparent;
            color:#86918b;
            cursor:pointer;
            font:inherit;
            -webkit-tap-highlight-color:transparent;
            transition:background .16s ease,color .16s ease,transform .16s ease;
        }

        .hub-bottom-item[data-tone="violet"] { --nav-tone:var(--hub-violet); }
        .hub-bottom-item[data-tone="amber"]  { --nav-tone:var(--hub-amber); }
        .hub-bottom-item[data-tone="blue"]   { --nav-tone:var(--hub-blue); }
        .hub-bottom-item[data-tone="green"]  { --nav-tone:var(--hub-green); }

        .hub-bottom-item .ph-duotone {
            color:#98a39d;
            font-size:19px;
            transition:color .16s ease,transform .16s ease;
        }

        .hub-bottom-item span {
            overflow:hidden;
            max-width:100%;
            font-size:.5rem;
            font-weight:790;
            line-height:1.1;
            text-overflow:ellipsis;
            white-space:nowrap;
        }

        .hub-bottom-item.is-active {
            background:#f1f5f2;
            color:var(--hub-text);
        }

        .hub-bottom-item.is-active .ph-duotone {
            color:var(--nav-tone);
            transform:translateY(-1px);
        }

        .hub-bottom-item:active {
            transform:scale(.97);
        }

        .hub-bottom-item:focus-visible {
            outline:2px solid color-mix(in srgb,var(--nav-tone) 38%,transparent);
            outline-offset:-2px;
        }

        @keyframes hub-screen-enter {
            from {
                opacity:.62;
                transform:translateX(var(--hub-screen-shift,8px));
            }
            to {
                opacity:1;
                transform:translateX(0);
            }
        }
    }

    @media(max-width:390px) {
        .hub-page {
            width:min(100%,calc(100dvw - 12px));
        }

        .hub-bottom-nav {
            right:5px;
            bottom:max(5px,env(safe-area-inset-bottom,0px));
            left:5px;
            border-radius:15px;
        }

        .hub-bottom-item {
            border-radius:10px;
        }
    }

    @media(prefers-reduced-motion:reduce) {
        .hub-page *,
        .hub-page *::before,
        .hub-page *::after {
            animation-duration:.01ms !important;
            transition-duration:.01ms !important;
        }
    }


    /* =========================================================
       REFINAMENTO DE LEGIBILIDADE — escala normal, sem exageros
       ========================================================= */

    .hub-context {
        min-height:72px;
        padding:.62rem .72rem;
    }

    .hub-context-logo {
        width:46px;
        height:46px;
    }

    .hub-context-logo img {
        width:30px;
        height:30px;
    }

    .hub-context-kicker {
        font-size:.64rem;
    }

    .hub-context-name {
        font-size:1rem;
    }

    .hub-context-meta {
        font-size:.7rem;
    }

    .hub-context-meta .ph {
        font-size:.78rem;
    }

    .hub-context-site {
        min-height:38px;
        padding:.38rem .56rem;
        font-size:.7rem;
    }

    .hub-desktop-layout {
        grid-template-columns:minmax(0,1.62fr) minmax(330px,.72fr);
        gap:.82rem;
    }

    .hub-main-stack,
    .hub-side-stack {
        gap:.82rem;
    }

    .hub-panel {
        border-radius:15px;
    }

    .hub-panel-head {
        min-height:62px;
        gap:.55rem;
        padding:.58rem .68rem;
    }

    .hub-panel-icon {
        width:38px;
        height:38px;
        border-radius:10px;
    }

    .hub-panel-icon .ph-duotone {
        font-size:18px;
    }

    .hub-panel-title h2,
    .hub-panel-title h3 {
        font-size:.86rem;
    }

    .hub-panel-title p {
        margin-top:.08rem;
        font-size:.68rem;
    }

    .hub-count {
        min-width:32px;
        min-height:26px;
        font-size:.66rem;
    }

    .hub-portal-area {
        padding:.62rem;
    }

    .hub-portal-explainer {
        margin-bottom:.58rem;
        padding:.48rem .52rem;
    }

    .hub-portal-explainer strong {
        font-size:.75rem;
    }

    .hub-portal-explainer span {
        margin-top:.06rem;
        font-size:.66rem;
    }

    .hub-search {
        width:min(260px,100%);
    }

    .hub-search > .ph {
        left:.62rem;
        font-size:.82rem;
    }

    .hub-search-input {
        min-height:40px;
        padding:.36rem 2rem .36rem 1.9rem;
        font-size:.72rem;
    }

    .hub-portal-grid {
        gap:.56rem;
    }

    .hub-portal-link {
        min-height:140px;
        border-radius:12px;
    }

    .hub-portal-main {
        gap:.56rem;
        padding:.64rem;
    }

    .hub-portal-icon {
        width:46px;
        height:46px;
        border-radius:11px;
    }

    .hub-portal-icon .ph-duotone {
        font-size:21px;
    }

    .hub-portal-badge {
        min-height:20px;
        padding:.07rem .28rem;
        font-size:.58rem;
    }

    .hub-portal-name {
        margin-top:.18rem;
        font-size:.88rem;
    }

    .hub-portal-description {
        margin-top:.12rem;
        font-size:.68rem;
        line-height:1.45;
    }

    .hub-portal-cta {
        min-height:40px;
        padding:.36rem .58rem;
        font-size:.7rem;
    }

    .hub-portal-cta .ph {
        font-size:.82rem;
    }

    .hub-no-results {
        padding:1rem;
        font-size:.7rem;
    }

    .hub-list,
    .hub-news-list {
        gap:.38rem;
        padding:.52rem;
    }

    .hub-list-link {
        min-height:58px;
        gap:.5rem;
        padding:.44rem .48rem;
    }

    .hub-list-icon {
        width:38px;
        height:38px;
        border-radius:10px;
    }

    .hub-list-icon .ph-duotone {
        font-size:17px;
    }

    .hub-list-copy strong {
        font-size:.74rem;
    }

    .hub-list-copy span {
        margin-top:.04rem;
        font-size:.65rem;
    }

    .hub-list-arrow {
        font-size:.8rem;
    }

    .hub-empty {
        padding:.9rem;
        font-size:.68rem;
    }

    .hub-news-item {
        gap:.5rem;
        padding:.5rem;
    }

    .hub-news-icon {
        width:37px;
        height:37px;
        border-radius:9px;
    }

    .hub-news-icon .ph-duotone {
        font-size:17px;
    }

    .hub-news-label {
        font-size:.58rem;
    }

    .hub-news-title {
        margin-top:.07rem;
        font-size:.76rem;
    }

    .hub-news-description {
        margin-top:.08rem;
        font-size:.66rem;
        line-height:1.48;
    }

    .hub-news-action {
        margin-top:.22rem;
        font-size:.65rem;
    }

    .hub-org-summary {
        gap:.46rem;
        padding:.52rem;
    }

    .hub-org-main {
        gap:.46rem;
    }

    .hub-org-logo {
        width:40px;
        height:40px;
    }

    .hub-org-logo img {
        width:26px;
        height:26px;
    }

    .hub-org-copy strong {
        font-size:.74rem;
    }

    .hub-org-copy span {
        margin-top:.04rem;
        font-size:.64rem;
    }

    .hub-org-fact {
        min-height:25px;
        padding:.16rem .32rem;
        font-size:.6rem;
    }

    .hub-socials {
        gap:.3rem;
        padding:0 .52rem .52rem;
    }

    .hub-social-link {
        min-height:34px;
        padding:.3rem .4rem;
        font-size:.63rem;
    }

    @media(max-width:1080px) and (min-width:821px) {
        .hub-desktop-layout {
            grid-template-columns:minmax(0,1.35fr) minmax(300px,.7fr);
        }
    }

    @media(max-width:820px) {
        .hub-page {
            width:min(100%,calc(100dvw - 18px));
        }

        .hub-context {
            min-height:64px;
            gap:.52rem;
            margin-bottom:.5rem;
            padding:.5rem .54rem;
        }

        .hub-context-logo {
            width:42px;
            height:42px;
        }

        .hub-context-logo img {
            width:27px;
            height:27px;
        }

        .hub-context-name {
            font-size:.9rem;
        }

        .hub-context-meta {
            font-size:.64rem;
        }

        .hub-mobile-screen-title {
            min-height:54px;
            gap:.44rem;
            margin-bottom:.48rem;
            padding:.44rem .5rem;
        }

        .hub-mobile-screen-icon {
            width:36px;
            height:36px;
            border-radius:9px;
        }

        .hub-mobile-screen-icon .ph-duotone {
            font-size:17px;
        }

        .hub-mobile-screen-copy strong {
            font-size:.76rem;
        }

        .hub-mobile-screen-copy span {
            margin-top:.04rem;
            font-size:.63rem;
        }

        .hub-panel-head {
            min-height:57px;
            padding:.5rem .56rem;
        }

        .hub-panel-title h2,
        .hub-panel-title h3 {
            font-size:.82rem;
        }

        .hub-portal-area {
            padding:.5rem;
        }

        .hub-portal-explainer {
            padding:.44rem;
        }

        .hub-portal-explainer strong {
            font-size:.72rem;
        }

        .hub-portal-explainer span {
            font-size:.64rem;
        }

        .hub-search-input {
            min-height:42px;
            font-size:.72rem;
        }

        .hub-portal-grid {
            gap:.46rem;
        }

        .hub-portal-link {
            min-height:118px;
        }

        .hub-portal-main {
            gap:.48rem;
            padding:.54rem;
        }

        .hub-portal-icon {
            width:43px;
            height:43px;
        }

        .hub-portal-name {
            font-size:.82rem;
        }

        .hub-portal-description {
            font-size:.66rem;
        }

        .hub-portal-cta {
            min-height:38px;
            padding:.34rem .52rem;
            font-size:.68rem;
        }

        .hub-list,
        .hub-news-list {
            gap:.36rem;
            padding:.46rem;
        }

        .hub-list-link {
            min-height:58px;
            padding:.44rem;
        }

        .hub-list-copy strong {
            font-size:.73rem;
        }

        .hub-list-copy span {
            font-size:.64rem;
        }

        .hub-news-item {
            min-height:82px;
            padding:.48rem;
        }

        .hub-news-title {
            font-size:.74rem;
        }

        .hub-news-description {
            font-size:.65rem;
        }

        .hub-bottom-nav {
            min-height:68px;
            padding:5px;
        }

        .hub-bottom-item {
            min-height:56px;
            gap:4px;
        }

        .hub-bottom-item .ph-duotone {
            font-size:21px;
        }

        .hub-bottom-item span {
            font-size:.62rem;
            font-weight:800;
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
            <div class="hub-context-kicker">Organização atual</div>

            <h2 class="hub-context-name" id="hub-title">
                {{ $currentTenant->name ?? 'Sua organização' }}
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
                                <span>
                                    Os cartões desta área são acessos. Recursos, notícias e contato ficam separados.
                                </span>
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