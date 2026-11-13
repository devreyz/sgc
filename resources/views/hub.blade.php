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

{{-- Design específico do hub, compatível com SGC Campo e com o tema legado. --}}
<style>
/* Identidade SGC Campo: marfim + floresta / grafite + ouro */
.hub-page {
    --hub-bg: #f4f4ef;
    --hub-panel: #fffefb;
    --hub-soft: #f1f2ec;
    --hub-pressed: #e9eee7;
    --hub-line: #dce2d8;
    --hub-ink: #1d2922;
    --hub-muted: #617166;
    --hub-faint: #879288;
    --hub-primary: #173c2e;
    --hub-primary-hover: #25513d;
    --hub-primary-text: #fffef9;
    --hub-light-tint: #e9f1e8;
    --hub-accent: #d9f172;
    --hub-accent-text: #253924;
    --hub-link: #266c4c;
    --hub-status: #1c7149;
    --hub-focus: #37885e;
    --hub-shadow: 0 7px 27px rgb(24 44 30 / .035);
    display: grid;
    width: min(100%, 1510px);
    min-width: 0;
    grid-column: 1 / -1;
    gap: 20px;
    margin-inline: auto;
    padding-bottom: 34px;
    color: var(--hub-ink);
    font-size: .875rem;
    font-family: Inter, 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
    -webkit-font-smoothing: antialiased;
}
/* O dark é grafite e ouro, sem verde dominante nas superfícies. */
:where(html[data-theme='dark'],html.dark,body.dark,html[data-theme='grafite-ouro'],html[data-theme='graphite-gold']) .hub-page,
.hub-page[data-theme='dark'] {
    color-scheme: dark;
    --hub-bg: #111418;
    --hub-panel: #191e24;
    --hub-soft: #222830;
    --hub-pressed: #2c3440;
    --hub-line: #35404a;
    --hub-ink: #edf2f6;
    --hub-muted: #b3c0cb;
    --hub-faint: #8c9ba9;
    --hub-primary: #222932;
    --hub-primary-hover: #303944;
    --hub-primary-text: #f2f5f7;
    --hub-light-tint: #2d3440;
    --hub-accent: #e7c87d;
    --hub-accent-text: #2e2619;
    --hub-link: #e7c87d;
    --hub-status: #a4d6ac;
    --hub-focus: #e7c87d;
    --hub-shadow: none;
}
.hub-page *, .hub-page *::before, .hub-page *::after, .hub-bottom-nav * { box-sizing: border-box; }
.hub-page [hidden], .hub-bottom-nav [hidden] { display: none !important; }
.hub-page :is(h1,h2,h3,p) { margin-top: 0; }
.hub-page :is(a,button,input):focus-visible,.hub-bottom-nav button:focus-visible { outline: 3px solid var(--hub-focus); outline-offset: 2px; }
.hub-page a { text-decoration: none; }
.hub-page a:hover { text-decoration: none; }

/* 01 — Cabeçalho como identidade, não como mais um card */
.hub-page > .ui-page-head {
    position: relative;
    isolation: isolate;
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-height: 195px;
    padding: clamp(24px,3vw,38px) clamp(22px,3vw,42px);
    gap: 22px;
    border: 1px solid var(--hub-primary);
    border-radius: 14px;
    background: var(--hub-primary);
    color: var(--hub-primary-text);
    box-shadow: none;
    overflow: hidden;
}
.hub-page > .ui-page-head::before {
    content: '';
    position: absolute;
    z-index: -1;
    width: 430px;
    height: 430px;
    top: -285px;
    right: -95px;
    border: 1px solid rgb(225 241 184 / .13);
    border-radius: 50%;
    box-shadow: 0 0 0 57px rgb(225 241 184 / .035), 0 0 0 115px rgb(225 241 184 / .026);
    pointer-events: none;
}
.hub-page > .ui-page-head .ui-page-head__start { min-width: 0; gap: 21px; align-items: center; }
.hub-page .hub-brand-logo {
    display: grid;
    width: 72px;
    height: 72px;
    flex: 0 0 72px;
    place-items: center;
    overflow: hidden;
    border: 1px solid rgb(243 255 238 / .24);
    border-radius: 12px;
    background: rgb(255 255 255 / .96);
}
.hub-page .hub-brand-logo img { display: block; width: 100%; height: 100%; padding: 9px; object-fit: contain; }
.hub-page > .ui-page-head .ui-page-head__copy { min-width: 0; }
.hub-page > .ui-page-head .ui-page-head__copy::before {
    content: 'SGC  /  ESPAÇO DE TRABALHO';
    display: block;
    margin-bottom: 9px;
    color: var(--hub-accent);
    font-size: .66rem;
    font-weight: 850;
    letter-spacing: .16em;
    line-height: 1.3;
}
.hub-page > .ui-page-head .ui-page-head__title {
    margin: 0;
    color: var(--hub-primary-text);
    font-size: clamp(1.75rem,3.8vw,2.8rem);
    font-weight: 850;
    line-height: 1.07;
    letter-spacing: -.058em;
    overflow-wrap: anywhere;
}
.hub-page .ui-page-head__meta { display: flex; flex-wrap: wrap; gap: 6px 18px; margin-top: 15px; color: rgb(243 249 245 / .77); }
.hub-page .ui-page-head__meta > span { display: inline-flex; align-items: center; gap: 6px; font-size: .75rem; }
.hub-page .ui-page-head__meta i { color: var(--hub-accent); font-size: 1rem; }
.hub-page .hub-head-meta-count { color: var(--hub-accent); font-weight: 900; }
.hub-page .ui-page-head__actions { flex-shrink: 0; }
.hub-page .hub-page-head-site {
    min-height: 40px;
    padding: 9px 14px;
    border: 1px solid rgb(245 251 245 / .28);
    border-radius: 8px;
    background: rgb(255 255 255 / .055);
    color: #f9fcf7;
    font-size: .75rem;
    font-weight: 760;
}
.hub-page .hub-page-head-site:hover { background: rgb(255 255 255 / .12); }

/* 02 — Faixa editorial após a identificação */
.hub-page .hub-overview-note { display: flex; align-items: center; justify-content: space-between; gap: 12px; min-width: 0; padding: 1px 1px 2px; }
.hub-page .hub-overview-note__copy { display: grid; gap: 4px; }
.hub-page .hub-overview-note__eyebrow { color: var(--hub-link); font-size: .65rem; font-weight: 850; letter-spacing: .15em; text-transform: uppercase; }
.hub-page .hub-overview-note__title { margin: 0; font-size: 1.07rem; font-weight: 780; letter-spacing: -.03em; color: var(--hub-ink); }
.hub-page .hub-overview-note__caption { max-width: 440px; color: var(--hub-muted); font-size: .76rem; line-height: 1.5; }
.hub-page .hub-overview-note__shortcut { display: inline-flex; align-items: center; gap: 7px; font-size: .72rem; color: var(--hub-muted); white-space: nowrap; }
.hub-page .hub-overview-note__shortcut i { color: var(--hub-link); font-size: 1.1rem; }

/* 03 — Workspace com áreas bem delimitadas */
.hub-page > .ui-workspace-grid {
    display: grid;
    grid-template-columns: minmax(0,1.55fr) minmax(280px,.75fr);
    gap: 19px;
    min-width: 0;
    align-items: start;
}
.hub-page :is(.hub-main-stack,.hub-side-stack) { display: grid; min-width: 0; gap: 19px; align-content: start; }
.hub-page .ui-section {
    min-width: 0;
    margin: 0;
    padding: 0;
    overflow: hidden;
    border: 1px solid var(--hub-line);
    border-radius: 12px;
    background: var(--hub-panel);
    box-shadow: var(--hub-shadow);
    color: var(--hub-ink);
}
.hub-page .ui-section__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 14px;
    padding: 18px 20px 17px;
    border-bottom: 1px solid var(--hub-line);
    background: var(--hub-panel);
}
.hub-page .ui-section__heading { display: flex; min-width: 0; gap: 12px; align-items: center; }
.hub-page .ui-section__heading > .ui-icon-box { display: grid; place-items: center; width: 39px; height: 39px; flex: 0 0 39px; border-radius: 9px; border: 0; background: var(--hub-light-tint); color: var(--hub-link); }
.hub-page .ui-section__heading > .ui-icon-box i { font-size: 1.25rem; }
.hub-page .ui-section__copy { min-width: 0; }
.hub-page .ui-section__copy h2 { margin: 0; font-size: 1rem; font-weight: 850; letter-spacing: -.03em; line-height: 1.25; color: var(--hub-ink); }
.hub-page .ui-section__copy p { color: var(--hub-muted); margin: 3px 0 0; font-size: .75rem; line-height: 1.4; }
.hub-page .ui-section__actions { flex-shrink: 0; }
.hub-page .ui-count { display: inline-flex; min-width: 29px; height: 26px; align-items: center; justify-content: center; padding: 1px 7px; background: var(--hub-soft); color: var(--hub-ink); border: 1px solid var(--hub-line); border-radius: 6px; font-size: .71rem; font-weight: 850; font-variant-numeric: tabular-nums; }
.hub-page .ui-section__body { min-width: 0; padding: 0; background: var(--hub-panel); }

/* 04 — Portais: grade com índice e affordance verdadeira */
.hub-page .hub-portals-toolbar { padding: 16px 19px 13px; margin: 0; border: 0; background: var(--hub-panel); }
.hub-page .hub-portals-toolbar .ui-toolbar__main { width: 100%; min-width: 0; }
.hub-page .hub-search { position: relative; width: 100%; max-width: none; margin: 0; }
.hub-page .hub-search > i { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: var(--hub-muted); z-index: 2; pointer-events: none; font-size: 1rem; }
.hub-page .hub-search .hub-search-input { display: block; width: 100%; min-width: 0; min-height: 44px; padding: 10px 40px 10px 40px; border: 1px solid var(--hub-line); border-radius: 8px; background: var(--hub-soft); color: var(--hub-ink); font: inherit; font-size: .78rem; transition: border-color .15s,background-color .15s; box-shadow: none; }
.hub-page .hub-search .hub-search-input::placeholder { color: var(--hub-faint); }
.hub-page .hub-search .hub-search-input:focus { background: var(--hub-panel); border-color: var(--hub-focus); outline: 3px solid color-mix(in srgb, var(--hub-focus) 18%, transparent); outline-offset: 0; }
.hub-page .hub-search-clear { position: absolute; z-index: 3; top: 50%; right: 5px; display: grid; place-items: center; width: 34px; height: 34px; transform: translateY(-50%); border: 0; border-radius: 6px; color: var(--hub-muted); background: transparent; cursor: pointer; }
.hub-page .hub-search-clear:hover { background: var(--hub-pressed); }
.hub-page .hub-search:not(.has-value) .hub-search-clear { display: none; }
.hub-page .hub-portals-list {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    counter-reset: hubPortals;
    gap: 11px;
    align-content: start;
    padding: 6px 18px 18px;
    border: 0;
    border-radius: 0;
    background: transparent;
}
.hub-page .hub-portals-list > [data-hub-link] {
    position: relative;
    display: grid;
    grid-template-columns: 43px minmax(0,1fr) 24px;
    grid-template-rows: auto 1fr;
    min-width: 0;
    min-height: 145px;
    align-content: start;
    align-items: center;
    column-gap: 12px;
    row-gap: 16px;
    padding: 17px;
    border: 1px solid var(--hub-line);
    border-radius: 10px;
    background: var(--hub-panel);
    color: var(--hub-ink);
    text-decoration: none;
    transition: border-color .14s,background-color .14s,transform .14s;
    counter-increment: hubPortals;
}
.hub-page .hub-portals-list > [data-hub-link]::before {
    content: 'PORTAL  ' counter(hubPortals, decimal-leading-zero);
    grid-column: 1 / -1;
    grid-row: 1;
    align-self: start;
    color: var(--hub-faint);
    font-size: .59rem;
    font-weight: 900;
    letter-spacing: .14em;
}
.hub-page .hub-portals-list > [data-hub-link]:hover { border-color: var(--hub-link); background: var(--hub-soft); transform: translateY(-1px); }
.hub-page .hub-portals-list > [data-hub-link]:focus-visible { border-color: var(--hub-focus); }
.hub-page .hub-portals-list > [data-hub-link].is-opening { pointer-events: none; opacity: .64; }
.hub-page .hub-portals-list > [data-hub-link] .ui-icon-box {
    grid-column: 1;
    grid-row: 2;
    display: grid;
    place-items: center;
    width: 43px;
    height: 43px;
    background: var(--hub-light-tint);
    color: var(--hub-link);
    border-radius: 9px;
    border: 0;
}
.hub-page .hub-portals-list > [data-hub-link] .ui-icon-box i { font-size: 1.4rem; }
.hub-page .hub-portals-list > [data-hub-link] .ui-action-row__copy { display: grid; grid-column: 2; grid-row: 2; gap: 3px; min-width: 0; }
.hub-page .hub-portals-list .ui-action-row__title { display: block; margin: 0; font-size: .91rem; color: var(--hub-ink); font-weight: 840; line-height: 1.25; letter-spacing: -.015em; overflow-wrap: anywhere; }
.hub-page .hub-portals-list .ui-action-row__description { display: -webkit-box; overflow: hidden; margin-top: 2px; color: var(--hub-muted); font-size: .72rem; line-height: 1.45; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }
.hub-page .hub-portals-list > [data-hub-link] .ui-action-row__action { display: grid; place-items: center; grid-column: 3; grid-row: 2; width: 24px; height: 24px; margin: 0; align-self: center; color: var(--hub-link); }
.hub-page .hub-portals-list > [data-hub-link] .ui-action-row__action i { font-size: 1rem; }
.hub-page .hub-portals-list > [data-hub-link][hidden] { display: none !important; }
.hub-page .hub-portals-list > .hub-no-results, .hub-page .hub-portals-list > .ui-state { grid-column: 1 / -1; margin: 0; padding: 25px; border: 1px dashed var(--hub-line); border-radius: 10px; background: var(--hub-soft); }
.hub-page .hub-no-results[hidden] { display: none !important; }
.hub-page .ui-state__content { display: grid; gap: 7px; justify-items: center; text-align: center; color: var(--hub-muted); font-size: .78rem; }
.hub-page .ui-state__content strong { color: var(--hub-ink); font-size: .89rem; }
.hub-page .ui-state__icon { display: grid; place-items: center; width: 40px; height: 40px; border-radius: 10px; background: var(--hub-light-tint); color: var(--hub-link); }
.hub-page .ui-state__icon i { font-size: 1.4rem; }

/* 05 — Recursos e contato: linhas horizontais com leitura densa */
.hub-page .ui-section:not(#hub-screen-portals) .ui-action-list { display: grid; min-width: 0; gap: 0; overflow: hidden; border: 0; border-radius: 0; background: var(--hub-panel); }
.hub-page .ui-section:not(#hub-screen-portals) .ui-action-row {
    display: grid;
    width: 100%;
    grid-template-columns: 37px minmax(0,1fr) 20px;
    gap: 11px;
    align-items: center;
    min-width: 0;
    min-height: 64px;
    padding: 12px 19px;
    border: 0;
    border-bottom: 1px solid var(--hub-line);
    border-radius: 0;
    background: var(--hub-panel);
    color: var(--hub-ink);
    transition: background .15s;
}
.hub-page .ui-section:not(#hub-screen-portals) .ui-action-row:last-child { border-bottom: 0; }
.hub-page .ui-section:not(#hub-screen-portals) .ui-action-row:hover { background: var(--hub-soft); }
.hub-page .ui-section:not(#hub-screen-portals) .ui-action-row .ui-icon-box { display: grid; width: 37px; height: 37px; place-items: center; border: 0; border-radius: 8px; background: var(--hub-light-tint); color: var(--hub-link); }
.hub-page .ui-section:not(#hub-screen-portals) .ui-action-row .ui-icon-box i { font-size: 1.15rem; }
.hub-page .ui-section:not(#hub-screen-portals) .ui-action-row__copy { display: grid; min-width: 0; gap: 2px; }
.hub-page .ui-section:not(#hub-screen-portals) .ui-action-row__title { margin: 0; font-size: .8rem; font-weight: 810; color: var(--hub-ink); }
.hub-page .ui-section:not(#hub-screen-portals) .ui-action-row__description { font-size: .69rem; color: var(--hub-muted); overflow-wrap: anywhere; }
.hub-page .ui-section:not(#hub-screen-portals) .ui-action-row__action { display: grid; place-items: center; color: var(--hub-faint); font-size: .95rem; }
.hub-page .ui-section:not(#hub-screen-portals) .ui-action-row:hover .ui-action-row__action { color: var(--hub-link); }

/* 06 — Novidades como boletim da organização */
.hub-page .hub-news-list { display: block; overflow: hidden; border: 0; border-radius: 0; background: var(--hub-panel); }
.hub-page .hub-news-row { display: grid; grid-template-columns: 30px minmax(0,1fr); gap: 12px; align-items: start; padding: 16px 19px; border: 0; border-bottom: 1px solid var(--hub-line); background: var(--hub-panel); }
.hub-page .hub-news-row:last-child { border: 0; }
.hub-page .hub-news-row > .ui-icon-box { display: grid; width: 30px; height: 30px; place-items: center; background: var(--hub-soft); border: 0; border-radius: 7px; color: var(--hub-link); }
.hub-page .hub-news-row > .ui-icon-box i { font-size: 1.1rem; }
.hub-page .hub-news-copy { display: grid; min-width: 0; }
.hub-page .hub-news-label { font-size: .62rem; font-weight: 850; letter-spacing: .1em; text-transform: uppercase; color: var(--hub-link); }
.hub-page .hub-news-title { display: block; margin-top: 4px; font-size: .81rem; line-height: 1.3; color: var(--hub-ink); font-weight: 840; }
.hub-page .hub-news-description { display: -webkit-box; margin-top: 5px; overflow: hidden; color: var(--hub-muted); font-size: .71rem; line-height: 1.5; -webkit-box-orient: vertical; -webkit-line-clamp: 3; }
.hub-page .hub-news-action { display: inline-flex; align-items: center; gap: 5px; justify-self: start; min-height: 30px; margin-top: 8px; padding: 0; background: transparent; color: var(--hub-link); border: 0; font-size: .7rem; font-weight: 790; }
.hub-page .hub-news-action:hover { text-decoration: underline; }

/* 07 — Informações da organização */
.hub-page .hub-org-card { display: grid; gap: 11px; padding: 17px 19px; margin: 0; border: 0; border-bottom: 1px solid var(--hub-line); border-radius: 0; background: var(--hub-soft); box-shadow: none; }
.hub-page .hub-org-main { display: grid; grid-template-columns: 47px minmax(0,1fr); min-width: 0; gap: 11px; align-items: center; }
.hub-page .hub-org-main .hub-brand-logo { width: 47px; height: 47px; flex-basis: 47px; border-color: var(--hub-line); border-radius: 9px; }
.hub-page .hub-org-main .hub-brand-logo img { padding: 5px; }
.hub-page .hub-org-copy { min-width: 0; }
.hub-page .hub-org-copy strong { display: block; color: var(--hub-ink); font-size: .81rem; font-weight: 830; overflow-wrap: anywhere; }
.hub-page .hub-org-copy span { display: -webkit-box; overflow: hidden; margin-top: 4px; color: var(--hub-muted); font-size: .7rem; line-height: 1.42; -webkit-box-orient: vertical; -webkit-line-clamp: 3; }
.hub-page .hub-org-facts { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
.hub-page .hub-org-facts .ui-badge { display: inline-flex; align-items: center; gap: 6px; padding: 5px 8px; border: 1px solid var(--hub-line); border-radius: 6px; background: var(--hub-panel); color: var(--hub-muted); font-size: .66rem; }
.hub-page .hub-socials { display: flex; flex-wrap: wrap; align-items: center; gap: 7px; padding: 13px 19px 17px; margin: 0; border-top: 1px solid var(--hub-line); }
.hub-page .hub-socials > a { display: inline-flex; align-items: center; gap: 6px; min-height: 33px; padding: 7px 10px; background: var(--hub-soft); border: 1px solid var(--hub-line); border-radius: 7px; color: var(--hub-ink); font-size: .71rem; font-weight: 730; }
.hub-page .hub-socials > a:hover { border-color: var(--hub-link); }
.hub-page .hub-screen[hidden] { display: none !important; }
.hub-bottom-nav { display: none; }

/* 08 — Tablet: conteúdo principal primeiro e sidebar global não alterada */
@media (min-width: 821px) and (max-width: 1190px) {
    .hub-page > .ui-workspace-grid { grid-template-columns: 1fr; }
    .hub-page .hub-side-stack { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 15px; align-items: start; }
    .hub-page .hub-side-stack > section { min-height: 100%; }
}
/* Mobile: o próprio hub controla 4 abas, mas a barra integra-se à borda inferior. */
@media (max-width: 820px) {
    body.hub-app-navigation .app-nav-layer { display: none !important; }
    body.hub-app-navigation.has-app-nav .bento-container { padding-bottom: calc(78px + env(safe-area-inset-bottom,0px)) !important; }
    .hub-page { gap: 14px; padding-bottom: calc(80px + env(safe-area-inset-bottom,0px)); }
    .hub-page > .ui-page-head { min-height: 0; align-items: flex-start; padding: 21px 18px; flex-direction: column; gap: 13px; border-radius: 11px; }
    .hub-page > .ui-page-head .ui-page-head__start { gap: 12px; }
    .hub-page .hub-brand-logo { width: 50px; height: 50px; flex-basis: 50px; border-radius: 9px; }
    .hub-page > .ui-page-head .ui-page-head__copy::before { margin-bottom: 5px; font-size: .55rem; letter-spacing: .12em; }
    .hub-page > .ui-page-head .ui-page-head__title { font-size: clamp(1.35rem,5vw,2.1rem); }
    .hub-page .ui-page-head__meta { gap: 5px 12px; margin-top: 8px; }
    .hub-page .ui-page-head__meta > span { font-size: .66rem; }
    .hub-page .hub-page-head-site { min-height: 34px; padding: 6px 11px; font-size: .68rem; }
    .hub-page .hub-overview-note { align-items: flex-start; }
    .hub-page .hub-overview-note__title { font-size: .95rem; }
    .hub-page .hub-overview-note__shortcut { display: none; }
    .hub-page > .ui-workspace-grid { display: block; }
    .hub-page .hub-main-stack,.hub-page .hub-side-stack { display: contents; }
    .hub-page .hub-screen { display: none; }
    .hub-page .hub-screen.is-active { display: block; }
    .hub-page .ui-section__header { padding: 15px; }
    .hub-page .ui-section__copy h2 { font-size: .92rem; }
    .hub-page .ui-section__copy p { font-size: .69rem; }
    .hub-page .hub-portals-toolbar { padding: 12px 13px 9px; }
    .hub-page .hub-search .hub-search-input { font-size: 16px; min-height: 44px; }
    .hub-page .hub-portals-list { padding: 4px 12px 13px; gap: 9px; }
    .hub-page .hub-portals-list > [data-hub-link] { min-height: 130px; padding: 13px; gap: 9px; grid-template-columns: 34px minmax(0,1fr) 17px; }
    .hub-page .hub-portals-list > [data-hub-link] .ui-icon-box { width: 34px; height: 34px; }
    .hub-page .hub-portals-list > [data-hub-link] .ui-icon-box i { font-size: 1.1rem; }
    .hub-page .hub-portals-list .ui-action-row__title { font-size: .79rem; }
    .hub-page .hub-portals-list .ui-action-row__description { font-size: .66rem; }
    .hub-page .hub-portals-list > [data-hub-link] .ui-action-row__action { width: 17px; }
    .hub-page .ui-section:not(#hub-screen-portals) .ui-action-row { padding: 14px; }
    .hub-page .hub-news-row { padding: 14px; }
    .hub-bottom-nav {
        position: fixed;
        z-index: 640;
        right: 0;
        bottom: 0;
        left: 0;
        display: grid;
        grid-template-columns: repeat(4,minmax(0,1fr));
        gap: 0;
        width: 100%;
        min-height: calc(66px + env(safe-area-inset-bottom,0px));
        padding: 5px max(5px,env(safe-area-inset-right,0px)) max(6px,env(safe-area-inset-bottom,0px)) max(5px,env(safe-area-inset-left,0px));
        background: var(--hub-nav-bg,#fffefb);
        border: 0;
        border-top: 1px solid var(--hub-nav-line,#dce2d8);
        border-radius: 0;
        box-shadow: 0 -5px 18px rgb(10 26 15 / .055);
    }
    .hub-bottom-item { display: grid; min-width: 0; min-height: 53px; align-content: center; justify-items: center; gap: 3px; padding: 5px 2px; background: transparent; border: 0; border-radius: 7px; color: var(--hub-nav-muted,#748278); cursor: pointer; }
    .hub-bottom-item i { font-size: 1.35rem; line-height: 1; }
    .hub-bottom-item span { font-size: .66rem; font-weight: 750; white-space: nowrap; overflow: hidden; max-width: 100%; text-overflow: ellipsis; }
    .hub-bottom-item.is-active { background: transparent; color: var(--hub-nav-active,#245d43); }
    .hub-bottom-item.is-active::after { content: ''; display: block; width: 19px; height: 3px; border-radius: 3px; margin-top: 1px; background: var(--hub-nav-active,#245d43); }
}
@media (max-width: 540px) {
    .hub-page .hub-portals-list { grid-template-columns: 1fr; }
    .hub-page .hub-portals-list > [data-hub-link] { min-height: 98px; grid-template-columns: 40px minmax(0,1fr) 21px; row-gap: 10px; padding: 13px 14px; }
    .hub-page .hub-portals-list > [data-hub-link] .ui-icon-box { width: 39px; height: 39px; }
    .hub-page .hub-portals-list > [data-hub-link] .ui-icon-box i { font-size: 1.24rem; }
    .hub-page .hub-portals-list > [data-hub-link]::before { font-size: .56rem; }
    .hub-page .hub-portals-list .ui-action-row__title { font-size: .87rem; }
    .hub-page .hub-portals-list .ui-action-row__description { font-size: .7rem; }
    .hub-page .hub-page-head-site span { display: initial; }
}
@media (prefers-reduced-motion: reduce) { .hub-page *, .hub-bottom-nav * { animation: none !important; transition: none !important; scroll-behavior: auto !important; } }
/* Theme handling on nav, which is outside .hub-page */
@media (max-width: 820px) {
    :where(html[data-theme='dark'],html.dark,body.dark,html[data-theme='graphite-gold'],html[data-theme='grafite-ouro']) .hub-bottom-nav {
        --hub-nav-bg: #191e24;
        --hub-nav-line: #35404a;
        --hub-nav-muted: #aab9c5;
        --hub-nav-active: #e7c87d;
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

    <div class="hub-overview-note" aria-label="Resumo do espaço de trabalho">
        <div class="hub-overview-note__copy">
            <span class="hub-overview-note__eyebrow">SEU ESPAÇO / ACESSO ORGANIZADO</span>
            <h2 class="hub-overview-note__title">Tudo que você precisa, em um só lugar.</h2>
            <span class="hub-overview-note__caption">Acesse seus portais, consulte os comunicados e encontre os canais oficiais da organização.</span>
        </div>
        <span class="hub-overview-note__shortcut"><i class="ph ph-check-circle" aria-hidden="true"></i> Seus acessos aparecem conforme suas permissões</span>
    </div>

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
                            <h2 id="hub-portals-title">Áreas de trabalho</h2>
                            <p>Selecione o portal para continuar suas atividades.</p>
                        </div>
                    </div>

                    <div class="ui-section__actions">
                        <span class="ui-count" data-visible-count>
                            {{ $availablePanelsCount }}
                        </span>
                    </div>
                </header>

                <div class="ui-section__body">
                    @if($availablePanelsCount > 0)
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
                        class="ui-action-list hub-action-list hub-portals-list"
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
                                    <i class="ph {{ $superVisual['icon'] }}"></i>
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
                                    <i class="ph {{ $visual['icon'] }}"></i>
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
                            <h2 id="hub-resources-title">Recursos e conta</h2>
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
                                        <i class="ph {{ $resource['icon'] }}"></i>
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
                            <h2 id="hub-news-title">Mural da organização</h2>
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
                                    <i class="ph {{ $news['icon'] }}"></i>
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
                            <h2 id="hub-contact-title">Fale com a organização</h2>
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
        <i class="ph ph-squares-four" aria-hidden="true"></i>
        <span>Portais</span>
    </button>

    <button
        class="hub-bottom-item"
        type="button"
        data-hub-screen-target="news"
    >
        <i class="ph ph-megaphone-simple" aria-hidden="true"></i>
        <span>Novidades</span>
    </button>

    <button
        class="hub-bottom-item"
        type="button"
        data-hub-screen-target="resources"
    >
        <i class="ph ph-compass-tool" aria-hidden="true"></i>
        <span>Recursos</span>
    </button>

    <button
        class="hub-bottom-item"
        type="button"
        data-hub-screen-target="contact"
    >
        <i class="ph ph-chats-circle" aria-hidden="true"></i>
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
            // Sem portais autorizados, exibimos somente o estado de ausência de acesso.
            noResults.hidden =
                matches !== 0 || portalLinks.length === 0;
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