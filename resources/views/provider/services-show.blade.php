@extends('layouts.campo')

@section('title', $order->number)
@section('page-title', $order->service->name)
@section('page-subtitle', 'Acompanhe a execução, evidências, conferência e valores desta ordem.')
@section('user-role', ($operator ?? false) ? 'Operação de serviços' : 'Prestador')

@php
    $routeTenant = request()->route('tenant');

    $tenantSlug = is_object($routeTenant)
        ? ($routeTenant->slug ?? null)
        : $routeTenant;

    $bentoNavigation = \App\Support\PortalNavigation::make(
        'provider',
        'orders',
        $tenantSlug
    );

    $execution = $order->execution;

    /*
     * Mantidos porque também ficam disponíveis para o partial
     * provider._service-fields.
     */
    $fields = collect(
        data_get(
            $execution->catalog_snapshot,
            'fields',
            []
        )
    );

    $evidenceFields = $fields->whereIn(
        'type',
        ['image', 'file', 'signature']
    );

    $uploadedKeys = $execution->evidences
        ->pluck('field_key');

    $providerOverride = $order
        ->serviceVersion
        ?->providerRates
        ?->first(
            fn ($rate) =>
                $rate->active
                && (int) $rate->service_provider_id
                    === (int) $order->service_provider_id
        );

    $snapshottedCompensation = data_get(
        $execution->catalog_snapshot,
        'provider_compensation'
    );

    $providerPricingMethod = is_array($snapshottedCompensation)
        ? data_get($snapshottedCompensation, 'method')
        : data_get($execution->catalog_snapshot, 'provider_pricing_method', $order->serviceVersion?->provider_pricing_method);

    $providerConfiguredRate = is_array($snapshottedCompensation)
        ? data_get($snapshottedCompensation, $providerPricingMethod === 'percent_of_base' ? 'percentage' : 'rate')
        : ($providerPricingMethod === 'fixed'
            ? ($providerOverride?->fixed_amount ?? $providerOverride?->rate ?? $order->serviceVersion?->default_provider_rate)
            : ($providerPricingMethod === 'percent_of_base'
                ? ($providerOverride?->percentage ?? $order->serviceVersion?->provider_percentage)
                : ($providerOverride?->rate ?? $providerOverride?->fixed_amount ?? $order->serviceVersion?->default_provider_rate)));

    $providerPricingSource = is_array($snapshottedCompensation)
        ? data_get($snapshottedCompensation, 'source')
        : ($providerOverride ? 'provider_override' : 'service_version_default');

    $statusLabels = [
        'draft' => 'Aguardando início',
        'rejected' => 'Correção solicitada',
        'in_progress' => 'Em execução',
        'submitted' => 'Enviado para conferência',
        'validated' => 'Concluído',
    ];

    $statusMeta = [
        'draft' => [
            'class' => 'is-draft',
            'icon' => 'ph-clock-countdown',
        ],
        'rejected' => [
            'class' => 'is-rejected',
            'icon' => 'ph-warning-circle',
        ],
        'in_progress' => [
            'class' => 'is-progress',
            'icon' => 'ph-play-circle',
        ],
        'submitted' => [
            'class' => 'is-submitted',
            'icon' => 'ph-paper-plane-tilt',
        ],
        'validated' => [
            'class' => 'is-validated',
            'icon' => 'ph-seal-check',
        ],
    ];

    $executionStatus =
        $execution->status
        ?? 'draft';

    $executionStatusLabel =
        $statusLabels[$executionStatus]
        ?? \Illuminate\Support\Str::headline(
            (string) $executionStatus
        );

    $executionStatusMeta =
        $statusMeta[$executionStatus]
        ?? [
            'class' => 'is-neutral',
            'icon' => 'ph-circle',
        ];

    $stepIndex = match ($executionStatus) {
        'draft', 'rejected' => 0,
        'in_progress' => 1,
        'submitted' => 2,
        'validated' => 3,
        default => 0,
    };

    $workflowSteps = [
        [
            'label' => 'Iniciar',
            'icon' => 'ph-play',
        ],
        [
            'label' => 'Executar',
            'icon' => 'ph-wrench',
        ],
        [
            'label' => 'Conferência',
            'icon' => 'ph-magnifying-glass',
        ],
        [
            'label' => 'Concluído',
            'icon' => 'ph-flag',
        ],
    ];

    $beneficiaryName =
        $order->beneficiary_snapshot['name']
        ?? 'Sem beneficiário';

    $providerName =
        $order->provider_snapshot['name']
        ?? $order->serviceProvider?->name
        ?? '—';

    $locationLabel =
        $order->location
        ?: 'Local não informado';

    $scheduledLabel =
        $order->scheduled_at
            ?->format('d/m/Y H:i')
        ?? 'Não agendado';

    $customerPricingMethod =
        $order->serviceVersion?->customer_pricing_method;

    $formatMethod = static function ($method): string {
        return match ($method) {
            'fixed' => 'Valor fixo por execução',
            'quantity_x_rate' => 'Quantidade × tarifa',
            'percent_of_base' => 'Percentual da cobrança',
            default => '—',
        };
    };

    $formatMoney = static fn ($value): string =>
        'R$ ' . number_format(
            (float) ($value ?? 0),
            2,
            ',',
            '.'
        );

    $receivablePreview =
        $financialPreview
            ? (float) (
                $financialPreview['receivable_total']
                ?? 0
            )
            : null;

    $payablePreview =
        $financialPreview
            ? (float) (
                $financialPreview['payable_total']
                ?? 0
            )
            : null;

    $evidenceTotal =
        $execution->evidences->count();

    $evidenceConfiguredTotal =
        $evidenceFields->count();

    $canApprove =
        $executionStatus === 'submitted'
        && ($operator ?? false)
        && auth()
            ->user()
            ->checkPermissionTo(
                'approve_service_execution'
            );

    /*
     * Valores escalares usados pela interface.
     * O JavaScript os lê pelos atributos data-* do <main>.
     */
    $draftUrl = route(
        $managementExecution ?? false ? 'services.management.execute.draft' : 'provider.orders.draft',
        [
            $tenantSlug,
            $order,
        ]
    );

    [$meterStartField, $meterEndField] = \App\Services\Services\ServiceCalculationRules::meterPair((array) $execution->catalog_snapshot);

    $executionUnit = (string) ($execution->unit ?? '');

    $serviceLocalKey = implode(
        ':',
        [
            'service-order-draft',
            (string) $tenantSlug,
            (string) $order->id,
        ]
    );

    $savedValues = collect(
        $execution->values ?? []
    )->filter(
        static fn ($value) =>
            $value !== null
            && $value !== ''
    );
@endphp

@section('content')
<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/fill/style.css"
>


<style>
    /*
 * SGC CAMPO · Serviço / ordem de execução · redesign v3
 * Integração com theme.css e design-system.css.
 * Escopo: somente .service-execution. Sem alterar os contratos do Laravel/JS.
 * Design: Campo Claro + Grafite & Lima. As cores vivem no tema global.
 */
.service-execution {
  --svc-canvas:var(--ui-color-canvas,#f4f4ef);
  --svc-surface:var(--ui-color-surface,#fffefb);
  --svc-soft:var(--ui-color-surface-soft,#f0f0e9);
  --svc-raised:var(--ui-color-surface-raised,var(--svc-surface));
  --svc-text:var(--ui-color-text,#1d2922);
  --svc-text-2:var(--ui-color-text-secondary,#647167);
  --svc-muted:var(--ui-color-text-muted,#879087);
  --svc-border:var(--ui-color-border,#d9e0d7);
  --svc-border-strong:var(--ui-color-border-strong,#b9c7bc);
  --svc-accent:var(--sgc-accent,#d9f172);
  --svc-on-accent:var(--sgc-on-accent,#233523);
  --svc-hero:var(--sgc-rail,#173c2e);
  --svc-hero-ink:var(--sgc-rail-text,#eff5ed);
  --svc-success:var(--ui-color-success,#207346);
  --svc-success-soft:var(--ui-color-success-soft,#e8f4e9);
  --svc-info:var(--ui-color-info,#386a8b);
  --svc-info-soft:var(--ui-color-info-soft,#e9f2f6);
  --svc-warning:var(--ui-color-warning,#926122);
  --svc-warning-soft:var(--ui-color-warning-soft,#fbf1db);
  --svc-danger:var(--ui-color-danger,#a94a3b);
  --svc-danger-soft:var(--ui-color-danger-soft,#f9e9e5);
  --svc-neutral:var(--ui-color-neutral,var(--svc-text-2));
  --svc-green:var(--svc-success);--svc-green-dark:var(--svc-success);
  --svc-green-soft:var(--svc-success-soft);--svc-green-border:var(--ui-color-success-border,var(--svc-border));
  --svc-blue:var(--svc-info);--svc-blue-soft:var(--svc-info-soft);--svc-blue-border:var(--ui-color-info-border,var(--svc-border));
  --svc-violet:var(--ui-color-violet,#8a4bd2);--svc-violet-soft:var(--ui-color-violet-soft,var(--svc-soft));--svc-violet-border:var(--ui-color-violet-border,var(--svc-border));
  --svc-cyan:var(--ui-color-cyan,var(--svc-info));--svc-cyan-soft:var(--ui-color-cyan-soft,var(--svc-info-soft));--svc-cyan-border:var(--ui-color-cyan-border,var(--svc-border));
  --svc-amber:var(--svc-warning);--svc-amber-soft:var(--svc-warning-soft);--svc-amber-border:var(--ui-color-warning-border,var(--svc-border));
  --svc-red:var(--svc-danger);--svc-red-soft:var(--svc-danger-soft);--svc-red-border:var(--ui-color-danger-border,var(--svc-border));
  --svc-slate:var(--svc-neutral);--svc-slate-soft:var(--ui-color-neutral-soft,var(--svc-soft));
  --svc-shadow:var(--ui-shadow-sm,0 5px 18px rgba(25,61,39,.045));
  display:grid;gap:20px;width:min(100%,1500px);min-width:0;max-width:100%;
  grid-column:1/-1;margin:0 auto;padding:3px 0 52px;color:var(--svc-text);
  font:400 14px/1.48 Inter,ui-sans-serif,system-ui,sans-serif;
}
.service-execution *, .service-execution *::before, .service-execution *::after {box-sizing:border-box}
.service-execution [hidden] {display:none!important}
.service-execution :focus-visible {outline:2px solid var(--ui-color-focus,var(--svc-info));outline-offset:3px}
.service-execution a {text-decoration:none}
.service-execution button {font:inherit;cursor:pointer}
.service-execution :is(button,input,select,textarea) {max-width:100%}
.service-execution :is(.svc-panel,.svc-quick-context,.svc-finance-strip) {min-width:0}

/* 01 / Cabeçalho editorial de operação, sem cards soltos e sem gradientes. */
.service-execution .svc-top {min-width:0;display:grid;gap:0;overflow:visible;border:1px solid var(--svc-border);border-radius:14px;background:var(--svc-surface);box-shadow:var(--svc-shadow)}
.service-execution .svc-header {--status-tone:var(--svc-neutral);--status-soft:var(--svc-slate-soft);position:relative;display:flex;align-items:stretch;justify-content:space-between;gap:26px;min-width:0;overflow:hidden;padding:27px 30px 29px;background:var(--svc-hero);color:var(--svc-hero-ink);border-radius:13px 13px 0 0}
.service-execution .svc-header::after {content:'SGC  /  CAMPO';position:absolute;right:28px;bottom:-9px;opacity:.055;font-size:clamp(35px,6vw,80px);font-weight:950;letter-spacing:-.075em;line-height:1;pointer-events:none;white-space:nowrap}
.service-execution .svc-header.is-rejected {--status-tone:var(--svc-red);--status-soft:var(--svc-red-soft)}
.service-execution .svc-header.is-progress {--status-tone:var(--svc-cyan);--status-soft:var(--svc-cyan-soft)}
.service-execution .svc-header.is-submitted {--status-tone:var(--svc-amber);--status-soft:var(--svc-amber-soft)}
.service-execution .svc-header.is-validated {--status-tone:var(--svc-green);--status-soft:var(--svc-green-soft)}
.service-execution .svc-header-main {min-width:0;display:flex;align-items:flex-start;gap:15px;position:relative;z-index:1;flex:1}
.service-execution .svc-back {display:grid;place-items:center;width:40px;height:40px;flex:0 0 40px;border:1px solid rgba(255,255,255,.21);border-radius:9px;color:inherit;background:rgba(255,255,255,.075);transition:background-color .15s}
.service-execution .svc-back:hover {background:rgba(255,255,255,.18)}
.service-execution .svc-back i {font-size:18px}
.service-execution .svc-header-icon {display:none}
.service-execution .svc-header-copy {min-width:0;max-width:740px}
.service-execution .svc-hero-kicker {display:flex;align-items:center;gap:10px;flex-wrap:wrap;min-height:20px;color:color-mix(in srgb,var(--svc-hero-ink) 68%,transparent);font-size:10px;font-weight:850;letter-spacing:.145em}
.service-execution .svc-kicker-rule {display:inline-block;width:22px;height:2px;background:var(--svc-accent)}
.service-execution .svc-hero-number {padding:3px 7px;background:rgba(255,255,255,.085);color:var(--svc-hero-ink);border:1px solid rgba(255,255,255,.12);border-radius:5px;font-size:10px;letter-spacing:.06em;font-variant-numeric:tabular-nums}
.service-execution .svc-header-copy h1 {margin:12px 0 8px;color:inherit;max-width:710px;font-size:clamp(26px,2.55vw,39px);line-height:1.11;font-weight:900;letter-spacing:-.048em;overflow-wrap:anywhere}
.service-execution .svc-hero-description {max-width:590px;margin:0;color:color-mix(in srgb,var(--svc-hero-ink) 73%,transparent);font-size:12px;line-height:1.55}
.service-execution .svc-meta {display:flex;gap:9px 18px;align-items:center;flex-wrap:wrap;margin-top:19px;color:var(--svc-hero-ink);font-size:12px;font-weight:620}
.service-execution .svc-meta>span {display:inline-flex;align-items:center;gap:7px;min-width:0}
.service-execution .svc-meta i {font-size:15px;color:var(--svc-accent);opacity:.92}
.service-execution .svc-meta-text {min-width:0;overflow-wrap:anywhere}
.service-execution .svc-header-side {position:relative;z-index:1;display:flex;align-items:flex-start;flex-direction:column;gap:9px;width:210px;flex:0 0 210px;border-left:1px solid rgba(255,255,255,.16);padding:3px 0 0 25px}
.service-execution .svc-status-caption {font-size:9px;font-weight:850;letter-spacing:.13em;color:color-mix(in srgb,var(--svc-hero-ink) 58%,transparent)}
.service-execution .svc-status {display:inline-flex;align-items:center;gap:7px;padding:6px 9px;min-height:31px;border:1px solid rgba(255,255,255,.17);border-radius:6px;background:rgba(255,255,255,.08);color:var(--svc-hero-ink);font-size:11px;font-weight:770;line-height:1.3}
.service-execution .svc-status i {color:var(--svc-accent);font-size:16px}
.service-execution .svc-hero-stage {font-size:11px;color:color-mix(in srgb,var(--svc-hero-ink) 73%,transparent)}
.service-execution .svc-hero-jump {display:inline-flex;align-items:center;gap:7px;justify-content:center;margin-top:auto;min-height:38px;padding:8px 10px;border-radius:7px;background:var(--svc-accent);color:var(--svc-on-accent);font-size:11px;font-weight:850;transition:background-color .15s;white-space:nowrap}
.service-execution .svc-hero-jump:hover {background:var(--sgc-accent-hover,var(--svc-accent))}
.service-execution .svc-hero-jump i {font-size:15px}

/* Etapas: régua operacional contínua em vez de cartões independentes. */
.service-execution .svc-workflow {padding:17px 25px;background:var(--svc-surface);border-radius:0 0 13px 13px;min-width:0}
.service-execution .svc-steps {display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:0;min-width:0}
.service-execution .svc-step {--step-tone:var(--svc-muted);--step-fill:var(--svc-soft);position:relative;display:flex;align-items:center;gap:9px;min-width:0;min-height:48px;color:var(--step-tone)}
.service-execution .svc-step:not(:last-child)::after {content:'';position:absolute;left:44px;right:11px;top:23px;height:1px;background:var(--svc-border);z-index:0;pointer-events:none}
.service-execution .svc-step.done {--step-tone:var(--svc-success);--step-fill:var(--svc-success-soft)}
.service-execution .svc-step.current {--step-tone:var(--svc-text);--step-fill:var(--svc-accent)}
.service-execution .svc-step.rejected.current {--step-tone:var(--svc-danger);--step-fill:var(--svc-danger-soft)}
.service-execution .svc-step.done:not(:last-child)::after {background:var(--svc-success)}
.service-execution .svc-step-icon {position:relative;z-index:1;display:grid;place-items:center;flex:0 0 39px;width:39px;height:39px;border:1px solid var(--svc-border);border-radius:50%;background:var(--svc-surface);color:var(--step-tone);font-size:16px}
.service-execution .svc-step.done .svc-step-icon {background:var(--step-fill);border-color:var(--svc-green-border);color:var(--svc-success)}
.service-execution .svc-step.current .svc-step-icon {background:var(--step-fill);color:var(--svc-on-accent);border-color:var(--svc-accent)}
.service-execution .svc-step.rejected.current .svc-step-icon {color:var(--svc-danger);border-color:var(--svc-red-border)}
.service-execution .svc-step-copy {position:relative;z-index:1;display:grid;gap:2px;min-width:0;padding:0 8px 0 3px;background:var(--svc-surface)}
.service-execution .svc-step-counter {font-size:9px;font-weight:820;letter-spacing:.1em;color:var(--svc-muted)}
.service-execution .svc-step-copy strong {font-size:12px;font-weight:800;color:var(--step-tone);line-height:1.2;overflow-wrap:anywhere}
.service-execution .svc-step.current .svc-step-copy strong {color:var(--svc-text)}

/* 02 / Área de trabalho principal + ficha lateral contextual. */
.service-execution .svc-workbench {display:grid;grid-template-columns:minmax(0,1fr) minmax(284px,325px);gap:22px;align-items:start;min-width:0;scroll-margin-top:100px}
.service-execution .svc-primary-lane {min-width:0;display:grid;gap:16px}
.service-execution .svc-context-lane {min-width:0;display:grid;align-content:start;gap:0;position:sticky;top:94px;border:1px solid var(--svc-border);border-radius:12px;background:var(--svc-surface);overflow:hidden}
.service-execution .svc-aside-overline {display:flex;align-items:center;gap:7px;padding:20px 19px 0;font-size:10px;font-weight:840;letter-spacing:.125em;color:var(--svc-text-2)}
.service-execution .svc-aside-overline>span {height:2px;width:16px;background:var(--svc-accent)}
.service-execution .svc-aside-heading {margin:5px 19px 18px;color:var(--svc-text);font-size:20px;font-weight:850;letter-spacing:-.045em;line-height:1.12}
.service-execution .svc-task-heading {--task-tone:var(--svc-info);display:flex;align-items:center;justify-content:space-between;gap:18px;padding:17px 20px 19px;border:1px solid var(--svc-border);border-left:4px solid var(--svc-accent);border-radius:10px;background:var(--svc-surface)}
.service-execution .svc-task-heading.is-rejected {border-left-color:var(--svc-danger);--task-tone:var(--svc-danger)}
.service-execution .svc-task-heading.is-validated {border-left-color:var(--svc-success);--task-tone:var(--svc-success)}
.service-execution .svc-task-heading.is-submitted {border-left-color:var(--svc-warning);--task-tone:var(--svc-warning)}
.service-execution .svc-task-label {font-size:10px;font-weight:870;letter-spacing:.125em;color:var(--task-tone)}
.service-execution .svc-task-label span {font-size:9px;margin-right:4px}
.service-execution .svc-task-heading h2 {margin:6px 0 4px;color:var(--svc-text);font-size:clamp(19px,2vw,25px);font-weight:860;letter-spacing:-.04em;line-height:1.18}
.service-execution .svc-task-heading p {margin:0;max-width:650px;color:var(--svc-text-2);font-size:12px;line-height:1.52}
.service-execution .svc-task-heading-icon {display:grid;place-items:center;width:48px;height:48px;flex:0 0 48px;border:1px solid var(--svc-border);border-radius:10px;background:var(--svc-soft);color:var(--task-tone);font-size:24px}

/* Área principal: etapas preenchidas, dados persistidos e conferência. */
.service-execution .svc-panel {min-width:0;overflow:hidden;border:1px solid var(--svc-border);border-radius:12px;background:var(--svc-surface);box-shadow:var(--svc-shadow)}
.service-execution .svc-panel-head {display:flex;align-items:center;justify-content:space-between;gap:12px;min-height:75px;padding:17px 20px;border-bottom:1px solid var(--svc-border);background:var(--svc-surface)}
.service-execution .svc-panel-title {display:flex;gap:12px;align-items:center;min-width:0}
.service-execution .svc-panel-icon {display:grid;place-items:center;flex:0 0 41px;width:41px;height:41px;border-radius:9px;border:1px solid var(--svc-border);background:var(--svc-soft);color:var(--panel-tone,var(--svc-info));font-size:19px}
.service-execution .svc-panel-copy {min-width:0}
.service-execution .svc-panel-copy h2 {margin:0;color:var(--svc-text);font-size:17px;font-weight:850;letter-spacing:-.03em;line-height:1.2}
.service-execution .svc-panel-copy p {margin:4px 0 0;color:var(--svc-text-2);font-size:12px;line-height:1.45}
.service-execution .svc-action-panel {border-top:3px solid var(--svc-accent)}
.service-execution .svc-action-panel .svc-panel-head {background:var(--svc-soft)}
.service-execution .svc-form-body,.service-execution .svc-review-body {padding:18px 20px 20px;background:var(--svc-surface)}
.service-execution .svc-form {display:grid;width:100%;min-width:0;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;align-items:start}
.service-execution .svc-form .svc-field,.service-execution .svc-form .field-group,.service-execution .svc-form .form-group {display:block;width:100%;min-width:0;max-width:100%;margin:0;padding:13px 14px 14px;border:1px solid var(--svc-border);border-radius:9px;background:var(--svc-surface);vertical-align:top}
.service-execution .svc-form .svc-field[hidden] {display:none!important}
.service-execution .svc-field>label {display:block;color:var(--svc-text);font-size:13px;font-weight:750;line-height:1.4}
.service-execution .svc-form :is(label,.form-label) {color:var(--svc-text);font-size:13px;font-weight:750}
.service-execution .svc-form .svc-field>label>span,.service-execution .svc-evidence-label {display:block;margin-bottom:6px;color:var(--svc-text);font-size:13px;font-weight:760}
.service-execution .svc-form input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]),.service-execution .svc-form select,.service-execution .svc-form textarea {display:block;width:100%;min-width:0;min-height:44px;padding:10px 12px;border:1px solid var(--svc-border-strong);border-radius:8px;outline:0;background:var(--svc-surface);color:var(--svc-text);font:inherit;font-size:14px;line-height:1.4}
.service-execution .svc-form textarea {min-height:100px;resize:vertical}
.service-execution .svc-form input::placeholder,.service-execution .svc-form textarea::placeholder {color:var(--svc-muted)}
.service-execution .svc-form :is(input,select,textarea):focus {border-color:var(--svc-info);outline:2px solid color-mix(in srgb,var(--svc-info) 25%,transparent);outline-offset:0}
.service-execution .svc-form input[type="checkbox"],.service-execution .svc-form input[type="radio"] {accent-color:var(--ui-color-primary)}
.service-execution .svc-required {color:var(--svc-danger);font-weight:850}
.service-execution .svc-field-unit,.service-execution .svc-optional,.service-execution .svc-field-help {display:block;margin-top:4px;color:var(--svc-text-2);font-size:12px;font-weight:500;line-height:1.45}
.service-execution .svc-file {width:100%;border:1px dashed var(--svc-border-strong);border-radius:9px;padding:12px;background:var(--svc-soft);color:var(--svc-text-2)}
.service-execution .svc-form-actions {display:flex;align-items:center;justify-content:flex-end;gap:9px;flex-wrap:wrap;grid-column:1/-1;border-top:1px solid var(--svc-border);padding:17px 0 0;margin:3px 0 0}
.service-execution .svc-live-totals {grid-column:1/-1;padding:1rem;border:1px solid var(--svc-border);border-radius:12px;background:var(--svc-soft)}
.svc-live-totals dl {display:flex;flex-wrap:wrap;gap:.5rem 1.5rem;margin:.5rem 0}
.svc-live-totals dt,.svc-live-totals p {font-size:.8125rem;color:var(--svc-text-2)}
.svc-live-totals dd {font-weight:750;font-variant-numeric:tabular-nums}
.service-execution .svc-async-feedback {grid-column:1/-1}
.svc-save-spinner {display:inline-block;animation:svc-spin .82s linear infinite}
@media (prefers-reduced-motion: reduce) {
    .svc-save-spinner,.svc-reload-spinner i {animation:none}
}
.service-execution .svc-btn,.service-execution .svc-table-link {display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:43px;padding:10px 15px;border:1px solid var(--svc-border-strong);border-radius:8px;background:var(--svc-surface);color:var(--svc-text);font-size:12px;font-weight:800;line-height:1.2;text-decoration:none;transition:background-color .15s,border-color .15s;white-space:normal}
.service-execution .svc-btn:hover,.service-execution .svc-table-link:hover {background:var(--svc-soft);border-color:var(--svc-muted)}
.service-execution .svc-btn.primary {background:var(--svc-accent);border-color:var(--svc-accent);color:var(--svc-on-accent);font-weight:850}
.service-execution .svc-btn.primary:hover {background:var(--sgc-accent-hover,var(--svc-accent));border-color:var(--sgc-accent-hover,var(--svc-accent))}
.service-execution .svc-btn i {font-size:16px}
.service-execution .svc-alert {display:flex;align-items:flex-start;gap:11px;padding:12px 14px;margin:0 0 15px;border:1px solid var(--svc-blue-border);border-radius:9px;background:var(--svc-blue-soft);color:var(--svc-text-2);font-size:12px;line-height:1.5}
.service-execution .svc-alert.danger {border-color:var(--svc-red-border);background:var(--svc-red-soft)}
.service-execution .svc-alert.warning {border-color:var(--svc-amber-border);background:var(--svc-amber-soft)}
.service-execution .svc-alert.success {border-color:var(--svc-green-border);background:var(--svc-green-soft)}
.service-execution .svc-alert-icon {display:grid;place-items:center;flex:0 0 32px;width:32px;height:32px;border-radius:8px;background:var(--svc-surface);color:var(--svc-info);font-size:17px}
.service-execution .svc-alert.danger .svc-alert-icon {color:var(--svc-danger)}
.service-execution .svc-alert strong {color:var(--svc-text)}
.service-execution .svc-preview {display:none;grid-template-columns:32px minmax(0,1fr) auto;grid-column:1/-1;gap:12px;align-items:center;padding:13px 15px;margin:0 0 14px;border:1px solid var(--svc-blue-border);border-radius:9px;background:var(--svc-blue-soft)}
.service-execution .svc-preview-icon {display:grid;place-items:center;width:32px;height:32px;background:var(--svc-surface);color:var(--svc-info);border-radius:7px}
.service-execution .svc-preview-copy span {color:var(--svc-text);font-size:13px;font-weight:800}
.service-execution .svc-preview-copy small {display:block;color:var(--svc-text-2);font-size:11px}
.service-execution .svc-preview-result {color:var(--svc-info);font-size:18px;font-weight:900;font-variant-numeric:tabular-nums}
.service-execution .svc-preview.is-invalid {border-color:var(--svc-red-border);background:var(--svc-red-soft)}
.service-execution .svc-preview.is-invalid .svc-preview-result {color:var(--svc-danger)}

/* 03 / Sidebar: contexto conciso, sem cartões concorrentes com o formulário. */
.service-execution .svc-quick-context {background:transparent;border-top:1px solid var(--svc-border)}
.service-execution .svc-quick-facts {display:grid;grid-template-columns:1fr}
.service-execution .svc-quick-fact {display:flex;align-items:center;gap:11px;min-width:0;padding:14px 18px;border-bottom:1px solid var(--svc-border)}
.service-execution .svc-quick-icon {display:grid;place-items:center;width:34px;height:34px;flex:0 0 34px;border-radius:8px;background:var(--svc-soft);color:var(--svc-text-2);font-size:17px}
.service-execution .svc-quick-copy {min-width:0;display:grid;gap:3px}
.service-execution .svc-quick-copy small {color:var(--svc-muted);font-size:10px;font-weight:790;letter-spacing:.045em;text-transform:uppercase}
.service-execution .svc-quick-copy strong {color:var(--svc-text);font-size:13px;font-weight:760;line-height:1.3;overflow-wrap:anywhere}
.service-execution .svc-order-details {border-bottom:1px solid var(--svc-border)}
.service-execution .svc-order-details>summary {display:flex;align-items:center;justify-content:space-between;gap:8px;min-height:46px;padding:10px 18px;cursor:pointer;color:var(--svc-text-2);font-size:12px;font-weight:800;list-style:none}
.service-execution .svc-order-details>summary::-webkit-details-marker {display:none}
.service-execution .svc-order-details>summary>span {display:inline-flex;align-items:center;gap:8px}
.service-execution .svc-order-details>summary:hover {background:var(--svc-soft)}
.service-execution .svc-details-caret {font-size:14px;transition:transform .15s}
.service-execution .svc-order-details[open] .svc-details-caret {transform:rotate(180deg)}
.service-execution .svc-order-details-body {padding:5px 15px 14px;background:var(--svc-surface)}
.service-execution .svc-details-list {display:grid;gap:0;margin:0}
.service-execution .svc-detail-item {display:flex;align-items:center;gap:10px;min-width:0;padding:10px 0;border-bottom:1px solid var(--svc-border)}
.service-execution .svc-detail-item:last-child {border-bottom:0}
.service-execution .svc-detail-icon {display:grid;place-items:center;width:27px;height:27px;flex:0 0 27px;border-radius:7px;background:var(--svc-soft);color:var(--svc-text-2);font-size:14px}
.service-execution .svc-detail-copy {min-width:0}
.service-execution .svc-detail-copy dt {margin:0 0 2px;color:var(--svc-muted);font-size:10px;font-weight:700}
.service-execution .svc-detail-copy dd {margin:0;color:var(--svc-text);font-size:12px;font-weight:760;overflow-wrap:anywhere}
.service-execution .svc-finance-strip {display:grid;grid-template-columns:1fr;background:var(--svc-soft);padding:15px 19px 19px}
.service-execution .svc-finance-strip::before {content:'REFERÊNCIA FINANCEIRA';display:block;margin-bottom:3px;font-size:10px;letter-spacing:.12em;font-weight:850;color:var(--svc-text-2)}
.service-execution .svc-finance-item {min-width:0;display:grid;gap:3px;padding:10px 0}
.service-execution .svc-finance-item>span {display:flex;gap:6px;align-items:center;color:var(--svc-text-2);font-size:11px;font-weight:760}
.service-execution .svc-finance-item strong {color:var(--svc-text);font-size:clamp(21px,2vw,26px);font-weight:880;letter-spacing:-.05em;font-variant-numeric:tabular-nums;line-height:1.15;overflow-wrap:anywhere}
.service-execution .svc-finance-item small {color:var(--svc-text-2);font-size:11px;line-height:1.4}
.service-execution .svc-finance-divider {height:1px;width:100%;background:var(--svc-border)}
.service-execution .svc-finance-item.receivable>span i {color:var(--svc-success)}
.service-execution .svc-finance-item.payable>span i {color:var(--svc-info)}

/* 04 / Dados registrados, conferência e evidências. */
.service-execution .svc-info-table,.service-execution .svc-money-table,.service-execution .svc-review-table,.service-execution .svc-evidence-table {width:100%;border-collapse:collapse;background:var(--svc-surface);font-size:13px}
.service-execution :is(.svc-info-table,.svc-money-table,.svc-review-table,.svc-evidence-table) :is(th,td) {padding:12px 15px;border-bottom:1px solid var(--svc-border);text-align:left;vertical-align:middle}
.service-execution :is(.svc-info-table,.svc-money-table,.svc-review-table,.svc-evidence-table) th {color:var(--svc-text-2);font-size:11px;font-weight:780}
.service-execution :is(.svc-info-table,.svc-money-table,.svc-review-table) th {width:41%;background:var(--svc-soft)}
.service-execution :is(.svc-info-table,.svc-money-table,.svc-review-table,.svc-evidence-table) tr:last-child :is(td,th) {border-bottom:0}
.service-execution :is(.svc-info-table,.svc-money-table,.svc-review-table) td {color:var(--svc-text);font-weight:650;overflow-wrap:anywhere}
.service-execution .svc-money-table td {text-align:right}
.service-execution .svc-money-value {display:block;font-size:18px;font-weight:860;color:var(--svc-text);font-variant-numeric:tabular-nums;letter-spacing:-.035em}
.service-execution .svc-money-value.receivable {color:var(--svc-success)}
.service-execution .svc-money-value.payable {color:var(--svc-info)}
.service-execution .svc-money-note {display:block;color:var(--svc-text-2);font-size:11px;line-height:1.4}
.service-execution .svc-evidence-wrap {min-width:0;overflow-x:auto}
.service-execution .svc-evidence-table {min-width:620px}
.service-execution .svc-evidence-table th {background:var(--svc-soft);font-size:10px;text-transform:uppercase;letter-spacing:.06em}
.service-execution .svc-evidence-table td {font-size:12px;min-width:0}
.service-execution .svc-evidence-table tbody tr:hover {background:var(--svc-soft)}
.service-execution .evidence-name {display:flex;align-items:center;gap:10px;min-width:0}
.service-execution .evidence-icon {display:grid;place-items:center;width:36px;height:36px;flex:0 0 36px;border-radius:8px;background:var(--svc-violet-soft);color:var(--svc-violet);font-size:18px}
.service-execution .evidence-name strong {color:var(--svc-text);font-size:12px;font-weight:800;overflow-wrap:anywhere}
.service-execution .evidence-copy {display:grid;gap:3px;min-width:0}
.service-execution .evidence-copy strong {color:var(--svc-text);font-size:12px}
.service-execution .evidence-copy small {color:var(--svc-muted);font-size:11px}
.service-execution .svc-evidence-reference {color:var(--svc-text-2);font-size:12px}
.service-execution .svc-evidence-preview-cell {text-align:right}
.service-execution .svc-evidence-thumb {display:block;width:80px;height:56px;overflow:hidden;border:1px solid var(--svc-border);border-radius:7px;background:var(--svc-soft)}
.service-execution .svc-evidence-thumb img {width:100%;height:100%;object-fit:cover}
.service-execution .svc-evidence-link {display:flex;align-items:center;gap:9px;min-width:0;color:var(--svc-text);text-decoration:none}
.service-execution .svc-evidence-link-icon {display:grid;place-items:center;flex:0 0 35px;width:35px;height:35px;background:var(--svc-soft);border-radius:7px;color:var(--svc-info)}
.service-execution .svc-evidence-link-copy {min-width:0;display:grid;gap:2px}
.service-execution .svc-evidence-link-copy strong {color:var(--svc-text);font-size:12px;font-weight:780;overflow-wrap:anywhere}
.service-execution .svc-evidence-link-copy small {color:var(--svc-text-2);font-size:11px}
.service-execution .svc-review-layout {display:grid;grid-template-columns:minmax(0,1fr) auto;gap:15px;align-items:end}
.service-execution .svc-review-table-wrap {min-width:0;overflow:hidden;border:1px solid var(--svc-border);border-radius:8px}
.service-execution .svc-review-result {color:var(--svc-info);font-weight:820}
.service-execution .svc-review-action {display:flex;justify-content:flex-end}
.service-execution .svc-count {display:inline-flex;align-items:center;gap:6px;padding:5px 9px;border:1px solid var(--svc-border);border-radius:7px;background:var(--svc-soft);color:var(--svc-text-2);font-size:11px;font-weight:800}

/* 05 / Acessibilidade e breakpoints: prioridade ao formulário. */
@media(max-width:1230px) {
  .service-execution .svc-workbench {grid-template-columns:minmax(0,1fr) minmax(260px,290px);gap:15px}
  .service-execution .svc-header {padding:24px}
}
@media(max-width:1050px) {
  .service-execution .svc-workbench {grid-template-columns:1fr;gap:15px}
  .service-execution .svc-context-lane {position:static}
  .service-execution .svc-quick-facts {grid-template-columns:repeat(3,minmax(0,1fr))}
  .service-execution .svc-quick-fact {border-right:1px solid var(--svc-border)}
  .service-execution .svc-quick-fact:last-child {border-right:0}
  .service-execution .svc-finance-strip {grid-template-columns:1fr 1px 1fr;gap:15px}
  .service-execution .svc-finance-strip::before {grid-column:1/-1}
  .service-execution .svc-finance-divider {height:100%;width:1px}
}
@media(max-width:767px) {
  .service-execution {gap:13px;padding-bottom:calc(90px + env(safe-area-inset-bottom,0px))}
  .service-execution .svc-top {border-radius:11px}
  .service-execution .svc-header {padding:18px 16px 19px;flex-direction:column;gap:16px;border-radius:10px 10px 0 0}
  .service-execution .svc-header-main {gap:11px}
  .service-execution .svc-header-copy h1 {font-size:clamp(23px,6vw,31px);margin-top:10px}
  .service-execution .svc-hero-description {font-size:12px}
  .service-execution .svc-header-side {width:100%;flex:unset;border-left:0;border-top:1px solid rgba(255,255,255,.16);padding:11px 0 0;display:grid;grid-template-columns:1fr auto;align-items:center;gap:6px 9px}
  .service-execution .svc-status-caption {grid-column:1/-1}
  .service-execution .svc-hero-stage {justify-self:end}
  .service-execution .svc-hero-jump {grid-column:1/-1;margin-top:5px}
  .service-execution .svc-workflow {padding:11px 9px}
  .service-execution .svc-step {justify-content:center;gap:4px;display:grid;grid-template-columns:1fr;justify-items:center;text-align:center;min-height:69px;padding:0 2px}
  .service-execution .svc-step:not(:last-child)::after {left:calc(50% + 19px);right:calc(-50% + 19px);top:19px}
  .service-execution .svc-step-icon {width:36px;height:36px;flex-basis:36px}
  .service-execution .svc-step-copy {padding:0;background:transparent}
  .service-execution .svc-step-counter {display:none}
  .service-execution .svc-step-copy strong {font-size:10px;line-height:1.2}
  .service-execution .svc-form {grid-template-columns:1fr}
  .service-execution .svc-panel-head {min-height:67px;padding:13px 14px}
  .service-execution .svc-panel-copy h2 {font-size:16px}
  .service-execution .svc-form-body,.service-execution .svc-review-body {padding:13px 13px 16px}
  .service-execution .svc-form-actions {display:grid;grid-template-columns:1fr;gap:9px}
  .service-execution .svc-form-actions .svc-btn,.service-execution .svc-review-action .svc-btn {width:100%;min-height:46px}
  .service-execution .svc-quick-facts {grid-template-columns:1fr}
  .service-execution .svc-quick-fact {border-right:0}
  .service-execution .svc-finance-strip {grid-template-columns:1fr;gap:0}
  .service-execution .svc-finance-strip::before {grid-column:auto}
  .service-execution .svc-finance-divider {height:1px;width:100%}
  .service-execution .svc-review-layout {grid-template-columns:1fr}
  .service-execution .svc-review-action {display:block}
  .service-execution .svc-evidence-wrap {overflow:visible;padding:10px}
  .service-execution .svc-evidence-table {min-width:0;display:block}
  .service-execution .svc-evidence-table thead {display:none}
  .service-execution .svc-evidence-table tbody {display:grid;gap:8px}
  .service-execution .svc-evidence-table tr {display:grid;grid-template-columns:minmax(0,1fr);gap:8px;border:1px solid var(--svc-border);border-radius:8px;padding:12px;background:var(--svc-surface)}
  .service-execution .svc-evidence-table td {display:block;border:0;padding:0;min-width:0}
  .service-execution .svc-evidence-table td::before {content:attr(data-label);display:block;margin:3px 0 5px;color:var(--svc-muted);font-size:10px;font-weight:820;text-transform:uppercase;letter-spacing:.06em}
  .service-execution .svc-evidence-preview-cell {text-align:left}
  .service-execution .svc-evidence-thumb {width:100%;height:122px}
  .service-execution .svc-task-heading {padding:14px 15px}
  .service-execution .svc-task-heading h2 {font-size:20px}
  .service-execution .svc-task-heading-icon {width:39px;height:39px;flex-basis:39px;font-size:19px}
}
@media(max-width:390px) {
  .service-execution .svc-meta {display:grid;gap:7px}
  .service-execution .svc-header-copy h1 {font-size:23px}
  .service-execution .svc-back {width:36px;height:36px;flex-basis:36px}
  .service-execution .svc-hero-kicker {font-size:9px;gap:6px}
  .service-execution .svc-step-copy strong {font-size:9px}
  .service-execution .svc-task-heading-icon {display:none}
  .service-execution .svc-panel-icon {width:37px;height:37px;flex-basis:37px}
  .service-execution .svc-panel-copy p {font-size:11px}
}
@media(prefers-reduced-motion:reduce) {
  .service-execution *, .service-execution *::before, .service-execution *::after {animation-duration:.01ms!important;transition-duration:.01ms!important;scroll-behavior:auto!important}
}
@media print {
  .service-execution {color:#111;background:#fff;padding:0}
  .service-execution .svc-top,.service-execution .svc-panel,.service-execution .svc-context-lane {box-shadow:none;break-inside:avoid}
  .service-execution .svc-header {background:#173c2e!important;color:white!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}
  .service-execution .svc-hero-jump,.service-execution .svc-form-actions,.service-execution .svc-back {display:none!important}
  .service-execution .svc-workbench {display:block}
  .service-execution .svc-context-lane {position:static;margin-top:18px}
}
/* Preservado do módulo anterior: campos de evidência, câmera, arquivos, viewer, loading */
.svc-file-preview {
    min-width: 0;
    margin-top: .35rem;
  }
.svc-file-preview small {
    color: var(--svc-muted);
    font-size: .6rem;
  }
.svc-file-preview img {
    display: block;
    max-width: 100%;
    max-height: 12rem;
    object-fit: contain;
    border: 1px solid var(--svc-border);
    border-radius: 8px;
    background: var(--svc-surface);
    cursor: zoom-in;
  }
.svc-evidence-field {
    display: grid;
    min-width: 0;
    gap: .35rem;
    padding: .5rem .52rem;
    border: 1px dashed var(--svc-border-strong);
    border-radius: 7px;
    background: var(--svc-soft);
  }
.svc-form .svc-field > label,     .svc-form .svc-evidence-field {
    min-width: 0;
    max-width: 100%;
  }
.service-execution .svc-field > label + .svc-evidence-field {
    margin-top: .875rem;
    padding: .75rem;
}
[data-theme="dark"] .service-execution .svc-form :is(input:not([type="hidden"], [type="file"], [type="checkbox"], [type="radio"]), select, textarea) {
    color-scheme: dark;
}
[data-theme="dark"] .service-execution .svc-form input:autofill {
    box-shadow: 0 0 0 100px var(--app-surface) inset !important;
    -webkit-text-fill-color: var(--ui-color-text);
}
.svc-form .svc-file-preview img {
    width: auto;
    max-width: 100%;
    height: auto;
  }
.svc-evidence-field[hidden],     .svc-field[hidden] {
    display: none !important;
  }
.svc-image-preview-button {
    position: relative;
    display: block;
    width: 100%;
    padding: 0;
    overflow: hidden;
    border: 1px solid var(--svc-border);
    border-radius: 8px;
    background: var(--svc-surface);
    cursor: zoom-in;
  }
.svc-image-preview-button img {
    display: block;
    width: 100%;
    max-height: 210px;
    object-fit: contain;
    background: var(--svc-soft);
  }
.svc-image-preview-hint {
    position: absolute;
    right: .38rem;
    bottom: .38rem;
    display: inline-flex;
    gap: .22rem;
    align-items: center;
    padding: .22rem .35rem;
    border-radius: 6px;
    background: rgba(23, 37, 28, .82);
    color: var(--svc-surface);
    font-size: .55rem;
    font-weight: 760;
    pointer-events: none;
  }
.svc-image-viewer {
    position: fixed;
    z-index: 2600;
    inset: 0;
    width: 100%;
    max-width: none;
    height: 100%;
    max-height: none;
    margin: 0;
    padding: 0;
    border: 0;
    background: rgba(8, 18, 12, .96);
  }
.svc-image-viewer:not([open]) {
    display: none;
  }
.svc-image-viewer[open] {
    display: grid;
    grid-template-rows: auto minmax(0, 1fr);
  }
.svc-image-viewer::backdrop {
    background: rgba(8, 18, 12, .96);
  }
.svc-image-viewer-toolbar {
    display: flex;
    min-height: 54px;
    gap: .5rem;
    align-items: center;
    justify-content: space-between;
    padding: max(.5rem, env(safe-area-inset-top))
            max(.65rem, env(safe-area-inset-right))
            .5rem
            max(.65rem, env(safe-area-inset-left));
    border-bottom: 1px solid rgba(255, 255, 255, .12);
    color: var(--svc-surface);
  }
.svc-image-viewer-title {
    min-width: 0;
    overflow: hidden;
    font-size: .71rem;
    font-weight: 760;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
.svc-image-viewer-close {
    display: grid;
    width: 38px;
    height: 38px;
    flex: 0 0 auto;
    place-items: center;
    border: 1px solid rgba(255, 255, 255, .18);
    border-radius: 8px;
    background: rgba(255, 255, 255, .08);
    color: var(--svc-surface);
    cursor: pointer;
  }
.svc-image-viewer-stage {
    display: grid;
    min-height: 0;
    place-items: center;
    overflow: auto;
    padding: .7rem;
  }
.svc-image-viewer-stage img {
    display: block;
    max-width: 100%;
    max-height: calc(100dvh - 80px);
    object-fit: contain;
  }
.svc-evidence-thumb {
    position: relative;
    display: block;
    padding: 0;
    overflow: hidden;
    border: 1px solid var(--svc-border);
    border-radius: 7px;
    background: var(--svc-soft);
    cursor: zoom-in;
    width: 76px;
    height: 52px;
    flex: 0 0 auto;
  }
.svc-evidence-thumb img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
  }
.svc-evidence-thumb > span {
    position: absolute;
    right: 3px;
    bottom: 3px;
    display: grid;
    width: 18px;
    height: 18px;
    place-items: center;
    border-radius: 5px;
    background: rgba(23, 37, 28, .82);
    color: var(--svc-surface);
    font-size: .52rem;
  }
.svc-file-viewer {
    position: fixed;
    z-index: 2700;
    inset: 0;
    width: 100%;
    max-width: none;
    height: 100%;
    max-height: none;
    margin: 0;
    padding: 0;
    border: 0;
    background: rgba(8, 18, 12, .97);
  }
.svc-file-viewer:not([open]) {
    display: none;
  }
.svc-file-viewer[open] {
    display: grid;
    grid-template-rows: auto minmax(0, 1fr);
  }
.svc-file-viewer::backdrop {
    background: rgba(8, 18, 12, .97);
  }
.svc-file-viewer-toolbar {
    display: flex;
    min-height: 54px;
    gap: .6rem;
    align-items: center;
    justify-content: space-between;
    padding: max(.48rem, env(safe-area-inset-top))
            max(.65rem, env(safe-area-inset-right))
            .48rem
            max(.65rem, env(safe-area-inset-left));
    border-bottom: 1px solid rgba(255, 255, 255, .12);
    color: var(--svc-surface);
  }
.svc-file-viewer-title {
    min-width: 0;
    overflow: hidden;
    font-size: .7rem;
    font-weight: 780;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
.svc-file-viewer-close {
    display: grid;
    width: 38px;
    height: 38px;
    flex: 0 0 auto;
    place-items: center;
    border: 1px solid rgba(255, 255, 255, .16);
    border-radius: 8px;
    background: rgba(255, 255, 255, .08);
    color: var(--svc-surface);
    cursor: pointer;
    font-size: 1rem;
  }
.svc-file-viewer-stage {
    display: grid;
    min-height: 0;
    place-items: center;
    overflow: hidden;
    padding: .7rem;
  }
.svc-file-viewer-image {
    display: block;
    max-width: 100%;
    max-height: calc(100dvh - 80px);
    object-fit: contain;
  }
.svc-file-viewer-frame {
    width: min(100%, 1080px);
    height: 100%;
    min-height: 0;
    border: 0;
    border-radius: 8px;
    background: var(--svc-surface);
  }
.svc-file-viewer-fallback {
    display: grid;
    width: min(100%, 360px);
    gap: .55rem;
    justify-items: center;
    padding: 1rem;
    border: 1px solid rgba(255, 255, 255, .14);
    border-radius: 10px;
    color: var(--svc-surface);
    text-align: center;
  }
.svc-file-viewer-fallback[hidden],     .svc-file-viewer-image[hidden],     .svc-file-viewer-frame[hidden] {
    display: none !important;
  }
.svc-file-viewer-fallback-icon {
    display: grid;
    width: 48px;
    height: 48px;
    place-items: center;
    border-radius: 10px;
    background: rgba(255, 255, 255, .08);
    font-size: 1.2rem;
  }
.svc-evidence-link {
    display: grid;
    min-width: 0;
    grid-template-columns: 30px minmax(0, 1fr);
    gap: .4rem;
    align-items: center;
  }
.svc-evidence-link-icon {
    display: grid;
    width: 30px;
    height: 30px;
    place-items: center;
    border-radius: 7px;
    background: var(--svc-cyan-soft);
    color: var(--svc-cyan);
    font-size: .75rem;
  }
.svc-evidence-link-copy {
    min-width: 0;
  }
.svc-evidence-link-copy strong,     .svc-evidence-link-copy small {
    display: block;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
.svc-evidence-link-copy strong {
    color: var(--svc-text);
    font-size: .64rem;
    font-weight: 800;
  }
.svc-evidence-link-copy small {
    margin-top: .05rem;
    color: var(--svc-blue);
    font-size: .6rem;
    font-weight: 780;
    font-variant-numeric: tabular-nums;
  }
.svc-evidence-preview-cell {
    display: inline-flex;
    gap: .38rem;
    align-items: center;
    justify-content: flex-end;
  }
.svc-file-preview-button {
    --preview-tone: var(--svc-slate);
    --preview-soft: var(--svc-slate-soft);
    display: grid;
    width: 52px;
    height: 52px;
    flex: 0 0 auto;
    place-items: center;
    border: 1px solid var(--svc-border);
    border-radius: 8px;
    background: var(--preview-soft);
    color: var(--preview-tone);
    cursor: pointer;
    font-size: 1.05rem;
  }
.svc-file-preview-button.pdf {
    --preview-tone: var(--svc-red);
    --preview-soft: var(--svc-red-soft);
  }
.svc-file-preview-button.signature {
    --preview-tone: var(--svc-blue);
    --preview-soft: var(--svc-blue-soft);
  }
.svc-evidence-preview-cell:has(.svc-evidence-thumb) {
    align-items: center;
  }
.svc-evidence-picker {
    display: grid;
    min-width: 0;
    gap: .34rem;
  }
.svc-evidence-native-input,     .svc-evidence-camera-input {
    position: absolute !important;
    width: 1px !important;
    height: 1px !important;
    padding: 0 !important;
    margin: -1px !important;
    overflow: hidden !important;
    clip: rect(0, 0, 0, 0) !important;
    white-space: nowrap !important;
    border: 0 !important;
    opacity: 0 !important;
  }
.svc-evidence-picker-actions {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: .38rem;
  }
.svc-evidence-pick-btn {
    --picker-tone: var(--svc-blue);
    --picker-soft: var(--svc-blue-soft);
    --picker-border: var(--svc-blue-border);
    display: inline-flex;
    min-width: 0;
    min-height: 39px;
    gap: .32rem;
    align-items: center;
    justify-content: center;
    padding: .4rem .52rem;
    border: 1px solid var(--picker-border);
    border-radius: 8px;
    background: var(--picker-soft);
    color: var(--picker-tone);
    cursor: pointer;
    font: inherit;
    font-size: .65rem;
    font-weight: 790;
  }
.svc-evidence-pick-btn.camera {
    --picker-tone: var(--svc-green);
    --picker-soft: var(--svc-green-soft);
    --picker-border: var(--svc-green-border);
  }
.svc-evidence-pick-btn i {
    flex: 0 0 auto;
    font-size: .84rem;
  }
.svc-evidence-pick-btn:focus-visible {
    outline: 2px solid var(--picker-tone);
    outline-offset: 2px;
  }
.svc-evidence-selection {
    display: inline-flex;
    min-width: 0;
    gap: .24rem;
    align-items: center;
    color: var(--svc-muted);
    font-size: .56rem;
    font-weight: 650;
    line-height: 1.35;
    overflow-wrap: anywhere;
  }
.svc-evidence-selection.is-selected,     .svc-evidence-selection.is-saved {
    color: var(--svc-green);
    font-weight: 740;
  }
.svc-evidence-field.has-evidence-error {
    border-color: var(--svc-red);
    background: var(--svc-red-soft);
  }
.svc-reload-overlay {
    position: fixed;
    z-index: 3100;
    inset: 0;
    display: grid;
    place-items: center;
    padding: 1rem;
    background: rgba(14, 25, 18, .72);
  }
.svc-reload-overlay[hidden] {
    display: none !important;
  }
.svc-reload-card {
    display: grid;
    width: min(100%, 310px);
    gap: .42rem;
    justify-items: center;
    padding: 1rem;
    border: 1px solid var(--svc-border);
    border-radius: 12px;
    background: var(--svc-surface);
    color: var(--svc-text);
    box-shadow: 0 18px 50px rgba(12, 28, 18, .2);
    text-align: center;
  }
.svc-reload-spinner {
    display: grid;
    width: 42px;
    height: 42px;
    place-items: center;
    border-radius: 10px;
    background: var(--svc-green-soft);
    color: var(--svc-green);
    font-size: 1.2rem;
  }
.svc-reload-spinner i {
    animation: svc-spin .82s linear infinite;
  }
.svc-reload-card strong {
    font-size: .78rem;
    font-weight: 840;
  }
.svc-reload-card small {
    color: var(--svc-muted);
    font-size: .61rem;
    line-height: 1.4;
  }
.svc-evidence-file-error {
    display: inline-flex;
    gap: .25rem;
    align-items: flex-start;
    color: var(--svc-red);
    font-size: .58rem;
    font-weight: 740;
    line-height: 1.4;
  }
.svc-evidence-file-error i {
    flex: 0 0 auto;
    margin-top: .04rem;
    font-size: .72rem;
  }
@media (max-width: 820px){
.svc-evidence-preview-cell {
    display: grid;
    justify-items: start;
  }
.svc-evidence-thumb {
    width: 82px;
    height: 58px;
  }
}
@media (max-width: 520px){
.svc-evidence-preview-cell .svc-table-link {
    width: 100%;
    min-height: 32px;
    padding-inline: .3rem;
    font-size: 0;
  }
.svc-evidence-preview-cell .svc-table-link i {
    font-size: .75rem;
  }
.svc-evidence-thumb,         .svc-file-preview-button {
    width: 72px;
  }
.svc-evidence-thumb {
    height: 58px;
  }
}
@media (max-width: 470px){
.svc-evidence-thumb {
    grid-column: 1 / -1;
    width: 100%;
    height: 92px;
  }
}
.service-execution .svc-evidence-table .svc-evidence-thumb {
    width: 112px;
    height: 76px;
}
.service-execution .svc-evidence-table .svc-evidence-thumb img {
    object-fit: contain;
}
@media (max-width: 820px) {
    .service-execution .svc-evidence-table tr {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .75rem 1rem;
    }
    .service-execution .svc-evidence-table td:nth-child(n+3) { grid-column: 1 / -1; }
    .service-execution .svc-evidence-table .svc-evidence-thumb {
        width: 100%;
        height: clamp(120px, 42vw, 240px);
    }
}
@media (max-width: 420px){
.svc-evidence-picker-actions {
    grid-template-columns: 1fr;
  }
}
@media (prefers-reduced-motion: reduce){
.svc-reload-spinner i {
    animation-duration: 1.8s;
  }
}
@keyframes svc-spin {
        to {
            transform: rotate(360deg);
        }
    }
.service-execution .svc-file-viewer { background: #0b0d11; }
.service-execution .svc-reload-overlay { background: rgb(10 12 16 / .77); }
</style>

<main
    class="service-execution ui-scope"
    data-draft-url="{{ $draftUrl }}"
    data-local-key="{{ $serviceLocalKey }}"
    data-meter-start="{{ $meterStartField }}"
    data-meter-end="{{ $meterEndField }}"
    data-execution-unit="{{ $executionUnit }}"
    data-execution-status="{{ $executionStatus }}"
>
    @if($errors->any())
        <div class="svc-alert danger" role="alert" tabindex="-1" id="form-errors">
            <span class="svc-alert-icon" aria-hidden="true"><i class="ph-fill ph-warning-circle"></i></span>
            <div><strong>Não foi possível concluir a ordem.</strong><ul style="margin:.35rem 0 0;padding-left:1.15rem">@foreach($errors->all() as $message)<li>{{$message}}</li>@endforeach</ul><small>Os dados preenchidos permanecem na tela. Corrija somente os itens indicados e envie novamente.</small></div>
        </div>
    @endif
    {{-- =========================================================
         CABEÇALHO + FLUXO
         ========================================================= --}}
    <section class="svc-panel svc-top">
        <header
            class="
                svc-header
                {{ $executionStatusMeta['class'] }}
            "
        >
            <div class="svc-header-main">
                <a
                    class="svc-back"
                    href="{{ route(($managementExecution ?? false) ? 'services.management.show' : 'provider.orders', ($managementExecution ?? false) ? [$tenantSlug, $order] : ['tenant' => $tenantSlug]) }}"
                    aria-label="Voltar às ordens"
                    title="Voltar às ordens"
                >
                    <i class="ph-fill ph-arrow-left"></i>
                </a>

                <span
                    class="svc-header-icon"
                    aria-hidden="true"
                >
                    <i
                        class="
                            ph-fill
                            {{ $executionStatusMeta['icon'] }}
                        "
                    ></i>
                </span>

                <div class="svc-header-copy">
                    <div class="svc-hero-kicker">
                        <span class="svc-kicker-rule" aria-hidden="true"></span>
                        ORDEM DE SERVIÇO
                        <span class="svc-hero-number">{{ $order->number }}</span>
                    </div>
                    <h1>{{ $order->service->name }}</h1>
                    <div class="svc-meta">
                        <span>
                            <i class="ph ph-user-circle" aria-hidden="true"></i>
                            <span class="svc-meta-text">{{ $beneficiaryName }}</span>
                        </span>
                        <span>
                            <i class="ph ph-map-pin" aria-hidden="true"></i>
                            <span class="svc-meta-text">{{ $locationLabel }}</span>
                        </span>
                    </div>
                </div>
            </div>

            <div class="svc-header-side">
                <span class="svc-status-caption">SITUAÇÃO ATUAL</span>
                <span class="svc-status">
                    <i class="ph-fill {{ $executionStatusMeta['icon'] }}" aria-hidden="true"></i>
                    {{ $executionStatusLabel }}
                </span>
                <span class="svc-hero-stage">Etapa {{ $stepIndex + 1 }} de 4</span>
                @if(in_array($executionStatus, ['draft', 'rejected', 'in_progress'], true))
                    <a class="svc-hero-jump" href="#svc-work-area">
                        Continuar operação
                        <i class="ph ph-arrow-down-right" aria-hidden="true"></i>
                    </a>
                @endif
            </div>
        </header>

        <div class="svc-workflow">
            <nav
                class="svc-steps"
                aria-label="Etapas da execução"
            >
                @foreach($workflowSteps as $index => $step)
                    @php
                        $stepDone =
                            $index < $stepIndex;

                        $stepCurrent =
                            $index === $stepIndex;

                        $stepRejected =
                            $executionStatus === 'rejected'
                            && $stepCurrent;
                    @endphp

                    <div
                        class="
                            svc-step
                            {{ $stepDone ? 'done' : '' }}
                            {{ $stepCurrent ? 'current' : '' }}
                            {{ $stepRejected ? 'rejected' : '' }}
                            {{
                                $executionStatus === 'validated'
                                && $index === 3
                                    ? 'validated-step'
                                    : ''
                            }}
                        "
                        data-step="{{ $index + 1 }}"
                        @if($stepCurrent)
                            aria-current="step"
                        @endif
                    >
                        <span
                            class="svc-step-icon"
                            aria-hidden="true"
                        >
                            <i
                                class="
                                    ph-fill
                                    {{
                                        $executionStatus === 'validated'
                                        && $index === 3
                                            ? 'ph-check-circle'
                                            : $step['icon']
                                    }}
                                "
                            ></i>
                        </span>

                        <span class="svc-step-copy">
                            <span class="svc-step-counter">ETAPA 0{{ $index + 1 }}</span>
                            <strong>
                                {{ $executionStatus === 'rejected' && $index === 0
                                    ? 'Corrigir'
                                    : $step['label'] }}
                            </strong>
                        </span>
                    </div>
                @endforeach
            </nav>
        </div>
    </section>

    {{-- Área operacional: ação primeiro, ficha da ordem em segundo plano. --}}
    <div class="svc-workbench" id="svc-work-area">
        <div class="svc-primary-lane">
            <div class="svc-task-heading {{ $executionStatusMeta['class'] }}">
                <div class="svc-task-heading-copy">
                    <span class="svc-task-label"><span aria-hidden="true">●</span> ETAPA ATUAL</span>
                    <h2>{{ match($executionStatus) {
                        'draft' => 'Tudo pronto para começar',
                        'rejected' => 'Uma correção precisa de você',
                        'in_progress' => 'Registre o trabalho realizado',
                        'submitted' => 'Serviço enviado para conferência',
                        'validated' => 'Execução concluída',
                        default => 'Acompanhe esta ordem',
                    } }}</h2>
                    <p>{{ match($executionStatus) {
                        'draft' => 'Preencha as informações iniciais para colocar esta ordem em execução.',
                        'rejected' => 'Confira a observação da revisão e reenvie os dados corrigidos.',
                        'in_progress' => 'Complete os dados da operação, anexe evidências e envie para conferência.',
                        'submitted' => 'Os dados foram enviados. A conclusão depende da conferência autorizada.',
                        'validated' => 'Consulte os dados registrados e as evidências desta operação.',
                        default => 'Consulte os dados desta ordem.',
                    } }}</p>
                </div>
                <span class="svc-task-heading-icon" aria-hidden="true"><i class="ph-fill {{ $executionStatusMeta['icon'] }}"></i></span>
            </div>

    {{-- =========================================================
         INÍCIO / CORREÇÃO
         ========================================================= --}}
    @if(
        in_array(
            $executionStatus,
            ['draft', 'rejected'],
            true
        )
    )
        <section class="svc-panel svc-action-panel">
            <header
                class="svc-panel-head"
                style="
                    --panel-tone:
                        {{ $executionStatus === 'rejected'
                            ? 'var(--svc-red)'
                            : 'var(--svc-blue)' }};
                    --panel-soft:
                        {{ $executionStatus === 'rejected'
                            ? 'var(--svc-red-soft)'
                            : 'var(--svc-blue-soft)' }};
                "
            >
                <div class="svc-panel-title">
                    <span
                        class="svc-panel-icon"
                        aria-hidden="true"
                    >
                        <i
                            class="
                                ph-fill
                                {{ $executionStatus === 'rejected'
                                    ? 'ph-arrow-counter-clockwise'
                                    : 'ph-play-circle' }}
                            "
                        ></i>
                    </span>

                    <div class="svc-panel-copy">
                        <h2>
                            {{ $executionStatus === 'rejected'
                                ? 'Corrigir e reiniciar'
                                : 'Iniciar serviço' }}
                        </h2>

                    </div>
                </div>
            </header>

            <div class="svc-form-body">
                @if($execution->review_reason)
                    <div class="svc-alert danger">
                        <span
                            class="svc-alert-icon"
                            aria-hidden="true"
                        >
                            <i class="ph-fill ph-warning-circle"></i>
                        </span>

                        <div>
                            <strong>
                                Correção solicitada:
                            </strong>

                            {{ $execution->review_reason }}
                        </div>
                    </div>
                @endif

                <form
                    data-async-service-form
                    method="post"
                    enctype="multipart/form-data"
                    action="{{ route(
                        ($managementExecution ?? false) ? 'services.management.execute.start' : 'provider.orders.start',
                        [
                            $tenantSlug,
                            $order,
                        ]
                    ) }}"
                    class="svc-form"
                >
                    @csrf

                    <input
                        type="hidden"
                        name="operation_key"
                        value="{{ \Illuminate\Support\Str::uuid() }}"
                    >

                    @include(
                        'provider._service-fields',
                        [
                            'execution' => $execution,
                            'phase' => 'start',
                            'operator' => $operator ?? false,
                            'tenantSlug' => $tenantSlug,
                        ]
                    )

                    <div class="svc-form-actions">
                        <button
                            type="submit"
                            class="svc-btn primary"
                        >
                            <i class="ph-fill ph-play-circle"></i>

                            {{ $executionStatus === 'rejected'
                                ? 'Salvar correções e reiniciar'
                                : 'Salvar dados e iniciar' }}
                        </button>
                    </div>
                </form>
            </div>
        </section>
    @endif

    {{-- =========================================================
         EXECUÇÃO
         ========================================================= --}}
    @if($executionStatus === 'in_progress')
        <section class="svc-panel svc-action-panel">
            <header
                class="svc-panel-head"
                style="
                    --panel-tone:var(--svc-cyan);
                    --panel-soft:var(--svc-cyan-soft);
                "
            >
                <div class="svc-panel-title">
                    <span
                        class="svc-panel-icon"
                        aria-hidden="true"
                    >
                        <i class="ph-fill ph-wrench"></i>
                    </span>

                    <div class="svc-panel-copy">
                        <h2>Registrar execução</h2>

                    </div>
                </div>
            </header>

            <div class="svc-form-body">
                <form
                    id="execution-form"
                    data-async-service-form
                    data-preview-url="{{ route(($managementExecution ?? false) ? 'services.management.execute.preview' : 'provider.orders.preview', [$tenantSlug, $order]) }}"
                    method="post"
                    enctype="multipart/form-data"
                    action="{{ route(
                        ($managementExecution ?? false) ? 'services.management.execute.submit' : 'provider.orders.submit',
                        [
                            $tenantSlug,
                            $order,
                        ]
                    ) }}"
                    class="svc-form"
                >
                    @csrf

                    <input
                        type="hidden"
                        name="operation_key"
                        value="{{ \Illuminate\Support\Str::uuid() }}"
                    >

                    @include(
                        'provider._service-fields',
                        [
                            'execution' => $execution,
                            'phase' => 'execution',
                            'operator' => $operator ?? false,
                            'tenantSlug' => $tenantSlug,
                        ]
                    )

                    @include(
                        'provider._service-fields',
                        [
                            'execution' => $execution,
                            'phase' => 'finish',
                            'operator' => $operator ?? false,
                            'tenantSlug' => $tenantSlug,
                        ]
                    )

                    @if(
                        data_get(
                            $execution->catalog_snapshot,
                            'execution_config.quantity_mode'
                        ) === 'meter_difference'
                    )
                        <div
                            class="svc-preview"
                            id="meter-preview"
                            aria-live="polite"
                        >
                            <span
                                class="svc-preview-icon"
                                aria-hidden="true"
                            >
                                <i class="ph-fill ph-gauge"></i>
                            </span>

                            <span class="svc-preview-copy">
                                <span>
                                    Quantidade calculada
                                    pela diferença
                                </span>

                                <small>
                                    Medição final − medição inicial
                                </small>
                            </span>

                            <strong
                                class="svc-preview-result"
                                id="meter-result"
                            >
                                —
                            </strong>
                        </div>
                    @endif

                    <section class="svc-live-totals" data-service-totals hidden aria-labelledby="svc-live-totals-title">
                        <h3 id="svc-live-totals-title"><strong>Valores estimados</strong></h3>
                        <dl>
                            <div><dt>Organização recebe</dt><dd data-total="receivable">—</dd></div>
                            <div><dt>Prestador recebe</dt><dd data-total="payable">—</dd></div>
                        </dl>
                        <p role="status" data-total-status></p>
                    </section>

                    <div class="svc-form-actions">
                        <button
                            type="button"
                            class="svc-btn"
                            data-save-draft
                        >
                            <i class="ph-fill ph-floppy-disk"></i>
                            Salvar rascunho
                        </button>

                        <button
                            type="submit"
                            class="svc-btn primary"
                        >
                            <i class="ph-fill ph-paper-plane-tilt"></i>
                            Finalizar e enviar
                        </button>
                    </div>
                </form>
            </div>
        </section>
    @endif

    @if($savedValues->isNotEmpty())
        <section class="svc-panel svc-recorded-panel">
            <header
                class="svc-panel-head"
                style="
                    --panel-tone:var(--svc-blue);
                    --panel-soft:var(--svc-blue-soft);
                "
            >
                <div class="svc-panel-title">
                    <span
                        class="svc-panel-icon"
                        aria-hidden="true"
                    >
                        <i class="ph-fill ph-clock-counter-clockwise"></i>
                    </span>

                    <div class="svc-panel-copy">
                        <h2>Dados já registrados</h2>
                        <p>Valores salvos anteriormente nesta execução.</p>
                    </div>
                </div>
            </header>

            <table
                class="svc-info-table"
                aria-label="Dados já registrados"
            >
                <tbody>
                    @foreach($savedValues as $key => $value)
                        @php
                            $savedField = $fields->firstWhere(
                                'key',
                                $key
                            );

                            $savedLabel =
                                $savedField['label']
                                ?? \Illuminate\Support\Str::headline(
                                    (string) $key
                                );

                            $savedUnit = data_get(
                                $savedField,
                                'unit'
                            );

                            $savedDisplayValue = is_bool($value)
                                ? ($value ? 'Sim' : 'Não')
                                : (
                                    is_array($value)
                                        ? implode(', ', $value)
                                        : $value
                                );
                        @endphp

                        <tr>
                            <th>{{ $savedLabel }}</th>

                            <td>
                                {{ $savedDisplayValue }}

                                @if($savedUnit)
                                    {{ $savedUnit }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endif


    {{-- =========================================================
         EVIDÊNCIAS JÁ ENVIADAS
         ========================================================= --}}
    @if($execution->evidences->isNotEmpty())
        <section class="svc-panel svc-files-panel">
            <header
                class="svc-panel-head"
                style="
                    --panel-tone:var(--svc-violet);
                    --panel-soft:var(--svc-violet-soft);
                "
            >
                <div class="svc-panel-title">
                    <span class="svc-panel-icon" aria-hidden="true">
                        <i class="ph-fill ph-files"></i>
                    </span>

                    <div class="svc-panel-copy">
                        <h2>Arquivos enviados</h2>
                        <p>Evidências vinculadas a esta execução.</p>
                    </div>
                </div>

                <span class="svc-count">
                    <i class="ph-fill ph-paperclip"></i>
                    {{ $evidenceTotal }}
                </span>
            </header>

            <div class="svc-evidence-wrap">
                <table class="svc-evidence-table" aria-label="Arquivos enviados">
                    <thead>
                        <tr>
                            <th>Arquivo</th>
                            <th>Vinculado a</th>
                            <th>Prévia</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($execution->evidences as $evidence)
                            @php
                                $evidenceMime = (string) (
                                    $evidence->document->mime_type
                                    ?? ''
                                );

                                $evidenceUrl = route(
                                    'provider.evidences.download',
                                    [
                                        $tenantSlug,
                                        $evidence,
                                    ]
                                );

                                $evidenceIsImage = str_starts_with(
                                    $evidenceMime,
                                    'image/'
                                );

                                $evidenceIsPdf =
                                    $evidenceMime === 'application/pdf'
                                    || str_ends_with(
                                        strtolower(
                                            (string) $evidence
                                                ->document
                                                ->name
                                        ),
                                        '.pdf'
                                    );

                                $evidenceField = $fields->firstWhere(
                                    'key',
                                    $evidence->field_key
                                );

                                $evidenceFieldType = data_get(
                                    $evidenceField,
                                    'type'
                                );

                                $evidenceFieldLabel =
                                    data_get(
                                        $evidenceField,
                                        'label'
                                    )
                                    ?? (
                                        $evidence->field_key
                                            ? \Illuminate\Support\Str::headline(
                                                (string) $evidence->field_key
                                            )
                                            : 'Arquivo da execução'
                                    );

                                /*
                                 * Campo de dado ao qual esta evidência pertence.
                                 * Primeiro usa evidence_for_field do catálogo.
                                 * Se não existir, tenta a mesma inferência
                                 * inicial/final usada no partial de campos.
                                 */
                                $representedFieldKey = data_get(
                                    $evidenceField,
                                    'evidence_for_field'
                                );

                                if (!$representedFieldKey && $evidenceField) {
                                    $normalizedEvidenceLabel = str(
                                        (string) $evidenceFieldLabel
                                    )
                                        ->ascii()
                                        ->lower()
                                        ->toString();

                                    $evidenceKeyword = collect(
                                        [
                                            'inicial',
                                            'inicio',
                                            'final',
                                            'fim',
                                        ]
                                    )->first(
                                        static fn (string $word): bool =>
                                            str_contains(
                                                $normalizedEvidenceLabel,
                                                $word
                                            )
                                    );

                                    if ($evidenceKeyword) {
                                        $representedField = $fields->first(
                                            static function ($candidate) use ($evidenceKeyword): bool {
                                                $candidateType = data_get(
                                                    $candidate,
                                                    'type'
                                                );

                                                if (
                                                    in_array(
                                                        $candidateType,
                                                        [
                                                            'image',
                                                            'file',
                                                            'signature',
                                                        ],
                                                        true
                                                    )
                                                ) {
                                                    return false;
                                                }

                                                $candidateLabel = str(
                                                    (string) data_get(
                                                        $candidate,
                                                        'label',
                                                        ''
                                                    )
                                                )
                                                    ->ascii()
                                                    ->lower()
                                                    ->toString();

                                                return str_contains(
                                                    $candidateLabel,
                                                    $evidenceKeyword
                                                );
                                            }
                                        );

                                        $representedFieldKey = data_get(
                                            $representedField,
                                            'key'
                                        );
                                    }
                                }

                                $representedField = $representedFieldKey
                                    ? $fields->firstWhere(
                                        'key',
                                        $representedFieldKey
                                    )
                                    : null;

                                $representedLabel = $representedField
                                    ? (
                                        data_get(
                                            $representedField,
                                            'label'
                                        )
                                        ?? \Illuminate\Support\Str::headline(
                                            (string) $representedFieldKey
                                        )
                                    )
                                    : $evidenceFieldLabel;

                                $representedUnit = data_get(
                                    $representedField,
                                    'unit'
                                );

                                $representedValue = $representedFieldKey
                                    ? data_get(
                                        $execution->values,
                                        $representedFieldKey
                                    )
                                    : null;

                                if (is_bool($representedValue)) {
                                    $representedDisplayValue = $representedValue
                                        ? 'Sim'
                                        : 'Não';
                                } elseif (is_array($representedValue)) {
                                    $representedDisplayValue = implode(
                                        ', ',
                                        $representedValue
                                    );
                                } else {
                                    $representedDisplayValue =
                                        $representedValue;
                                }

                                if ($evidenceFieldType === 'signature') {
                                    $evidenceIcon = 'ph-signature';
                                    $evidenceKind = 'signature';
                                    $previewType = $evidenceIsImage
                                        ? 'image'
                                        : 'file';
                                } elseif ($evidenceIsImage) {
                                    $evidenceIcon = 'ph-image-square';
                                    $evidenceKind = 'image';
                                    $previewType = 'image';
                                } elseif ($evidenceIsPdf) {
                                    $evidenceIcon = 'ph-file-pdf';
                                    $evidenceKind = 'pdf';
                                    $previewType = 'pdf';
                                } else {
                                    $evidenceIcon = 'ph-file';
                                    $evidenceKind = 'file';
                                    $previewType = 'file';
                                }
                            @endphp

                            <tr class="svc-evidence-row">
                                <td data-label="Arquivo">
                                    <div class="evidence-name">
                                        <span
                                            class="evidence-icon {{ $evidenceKind }}"
                                            aria-hidden="true"
                                        >
                                            <i class="ph-fill {{ $evidenceIcon }}"></i>
                                        </span>

                                        <span class="evidence-copy">
                                            <strong>
                                                {{ $evidence->document->name }}
                                            </strong>

                                            <small>
                                                {{ $evidenceIsImage
                                                    ? 'Imagem'
                                                    : (
                                                        $evidenceIsPdf
                                                            ? 'Documento PDF'
                                                            : (
                                                                $evidenceFieldType === 'signature'
                                                                    ? 'Assinatura'
                                                                    : 'Arquivo'
                                                            )
                                                    ) }}
                                            </small>
                                        </span>
                                    </div>
                                </td>

                                <td data-label="Vinculado a">
                                    <div class="svc-evidence-link">
                                        <span class="svc-evidence-link-icon">
                                            <i class="ph-fill ph-link-simple"></i>
                                        </span>

                                        <span class="svc-evidence-link-copy">
                                            <strong>
                                                {{ $representedLabel }}
                                            </strong>

                                            @if(
                                                $representedDisplayValue !== null
                                                && $representedDisplayValue !== ''
                                            )
                                                <small>
                                                    {{ $representedDisplayValue }}
                                                    @if($representedUnit)
                                                        {{ $representedUnit }}
                                                    @endif
                                                </small>
                                            @else
                                                <small>
                                                    {{ $representedFieldKey
                                                        ? 'Valor não informado'
                                                        : 'Evidência independente' }}
                                                </small>
                                            @endif
                                        </span>
                                    </div>
                                </td>

                                <td data-label="Prévia">
                                    <div class="svc-evidence-preview-cell">
                                        @if($evidenceIsImage)
                                            <button
                                                type="button"
                                                class="
                                                    svc-evidence-thumb
                                                    svc-preview-trigger
                                                "
                                                data-preview-url="{{ $evidenceUrl }}"
                                                data-preview-type="image"
                                                data-preview-title="{{ $evidence->document->name }}"
                                                aria-label="Ampliar {{ $evidence->document->name }}"
                                            >
                                                <img
                                                    src="{{ $evidenceUrl }}"
                                                    alt=""
                                                    loading="lazy"
                                                >

                                                <span aria-hidden="true">
                                                    <i class="ph-fill ph-arrows-out"></i>
                                                </span>
                                            </button>
                                        @else
                                            <button
                                                type="button"
                                                class="
                                                    svc-file-preview-button
                                                    {{ $evidenceKind }}
                                                    svc-preview-trigger
                                                "
                                                data-preview-url="{{ $evidenceUrl }}"
                                                data-preview-type="{{ $previewType }}"
                                                data-preview-title="{{ $evidence->document->name }}"
                                            >
                                                <i
                                                    class="
                                                        ph-fill
                                                        {{ $evidenceIcon }}
                                                    "
                                                ></i>
                                            </button>
                                        @endif

                                        @unless($evidenceIsImage)
                                            <button
                                                type="button"
                                                class="
                                                    svc-table-link
                                                    svc-preview-trigger
                                                "
                                                data-preview-url="{{ $evidenceUrl }}"
                                                data-preview-type="{{ $previewType }}"
                                                data-preview-title="{{ $evidence->document->name }}"
                                            >
                                                <i class="ph-fill ph-eye"></i>
                                                Visualizar
                                            </button>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    {{-- =========================================================
         CONFERÊNCIA / CONCLUSÃO
         ========================================================= --}}
    @if(
        in_array(
            $executionStatus,
            ['submitted', 'validated'],
            true
        )
    )
        <section class="svc-panel svc-review-panel">
            <header
                class="svc-panel-head"
                style="
                    --panel-tone:
                        {{ $executionStatus === 'validated'
                            ? 'var(--svc-green)'
                            : 'var(--svc-amber)' }};
                    --panel-soft:
                        {{ $executionStatus === 'validated'
                            ? 'var(--svc-green-soft)'
                            : 'var(--svc-amber-soft)' }};
                "
            >
                <div class="svc-panel-title">
                    <span
                        class="svc-panel-icon"
                        aria-hidden="true"
                    >
                        <i
                            class="
                                ph-fill
                                {{ $executionStatus === 'validated'
                                    ? 'ph-seal-check'
                                    : 'ph-magnifying-glass' }}
                            "
                        ></i>
                    </span>

                    <div class="svc-panel-copy">
                        <h2>
                            {{ $executionStatus === 'validated'
                                ? 'Execução concluída'
                                : 'Aguardando conferência' }}
                        </h2>

                        <p>
                            Dados e cálculos registrados
                            para conferência.
                        </p>
                    </div>
                </div>
            </header>

            <div class="svc-review-body">
                <div class="svc-review-layout">
                    <div class="svc-review-table-wrap">
                        <table
                            class="svc-review-table"
                            aria-label="Cálculos da execução"
                        >
                            <tbody>
                                @foreach(
                                    [
                                        'customer' => 'Cobrança',
                                        'provider' => 'Remuneração',
                                    ]
                                    as $direction => $label
                                )
                                    @php
                                        $calculation = data_get(
                                            $execution->derived_values,
                                            "calculation.$direction",
                                            []
                                        );
                                    @endphp

                                    @if(
                                        data_get($calculation, 'mode') ===
                                        'meter_difference'
                                    )
                                        <tr>
                                            <th>
                                                {{ $label }}
                                            </th>

                                            <td>
                                                <span
                                                    class="svc-review-result"
                                                >
                                                    {{ data_get(
                                                        $calculation,
                                                        'meter_end_value'
                                                    ) }}
                                                    −
                                                    {{ data_get(
                                                        $calculation,
                                                        'meter_start_value'
                                                    ) }}
                                                    @foreach(json_decode(data_get($execution->values, 'meter_pauses_'.data_get($calculation, 'meter_start_field'), '[]'), true) ?? [] as $pause)
                                                        − ({{ $pause['resume'] }} − {{ $pause['stop'] }})
                                                    @endforeach
                                                    =
                                                    {{ data_get(
                                                        $calculation,
                                                        'result'
                                                    ) }}
                                                </span>
                                            </td>
                                        </tr>
                                    @elseif(
                                        data_get($calculation, 'mode') ===
                                        'field'
                                    )
                                        <tr>
                                            <th>
                                                {{ $label }}
                                            </th>

                                            <td>
                                                <span
                                                    class="svc-review-result"
                                                >
                                                    {{ data_get(
                                                        $calculation,
                                                        'result'
                                                    ) }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach

                                @if(
                                    !data_get(
                                        $execution->derived_values,
                                        'calculation.customer'
                                    )
                                    && !data_get(
                                        $execution->derived_values,
                                        'calculation.provider'
                                    )
                                )
                                    <tr>
                                        <th>Cálculos</th>

                                        <td>
                                            Nenhum cálculo derivado
                                            disponível.
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>

                    @if($canApprove)
                        <div class="svc-review-action">
                            <form
                                data-async-service-form
                                method="post"
                                action="{{ route(
                                    ($managementExecution ?? false) ? 'services.management.execute.approve' : 'provider.orders.approve',
                                    [
                                        $tenantSlug,
                                        $order,
                                    ]
                                ) }}"
                            >
                                @csrf
                                <input type="hidden" name="lock_version" value="{{ $execution->lock_version }}">

                                <input
                                    type="hidden"
                                    name="operation_key"
                                    value="{{ \Illuminate\Support\Str::uuid() }}"
                                >

                                <button
                                    type="submit"
                                    class="svc-btn primary"
                                >
                                    <i class="ph-fill ph-seal-check"></i>
                                    Conferir e concluir ordem
                                </button>
                            </form>
                        </div>
                    @endif
                </div>
            </div>
        </section>
    @endif

        </div> {{-- /svc-primary-lane --}}
        <aside class="svc-context-lane" aria-label="Ficha da ordem">
            <div class="svc-aside-overline"><span aria-hidden="true"></span> FICHA DA ORDEM</div>
            <h2 class="svc-aside-heading">Informações essenciais</h2>
    {{-- =========================================================
         CONTEXTO RÁPIDO DA ORDEM
         ========================================================= --}}
    <section class="svc-quick-context">
        <div class="svc-quick-facts">
            <div class="svc-quick-fact schedule">
                <span class="svc-quick-icon" aria-hidden="true">
                    <i class="ph-fill ph-calendar-check"></i>
                </span>
                <span class="svc-quick-copy">
                    <small>Agendamento</small>
                    <strong>{{ $scheduledLabel }}</strong>
                </span>
            </div>

            <div class="svc-quick-fact provider">
                <span class="svc-quick-icon" aria-hidden="true">
                    <i class="ph-fill ph-user-gear"></i>
                </span>
                <span class="svc-quick-copy">
                    <small>Prestador</small>
                    <strong>{{ $providerName }}</strong>
                </span>
            </div>

            <div class="svc-quick-fact evidence">
                <span class="svc-quick-icon" aria-hidden="true">
                    <i class="ph-fill ph-images-square"></i>
                </span>
                <span class="svc-quick-copy">
                    <small>Evidências</small>
                    <strong>
                        {{ $evidenceTotal }}
                        @if($evidenceConfiguredTotal > 0)
                            de {{ $evidenceConfiguredTotal }}
                        @else
                            enviada(s)
                        @endif
                    </strong>
                </span>
            </div>
        </div>

        <details class="svc-order-details">
            <summary>
                <span>
                    <i class="ph-fill ph-info"></i>
                    Detalhes da ordem
                </span>

                <i
                    class="ph-fill ph-caret-down svc-details-caret"
                    aria-hidden="true"
                ></i>
            </summary>

            <div class="svc-order-details-body">
                <dl class="svc-details-list">
                    <div class="svc-detail-item order">
                        <span class="svc-detail-icon" aria-hidden="true">
                            <i class="ph-fill ph-receipt"></i>
                        </span>

                        <span class="svc-detail-copy">
                            <dt>Ordem</dt>
                            <dd>{{ $order->number }}</dd>
                        </span>
                    </div>

                    <div class="svc-detail-item service">
                        <span class="svc-detail-icon" aria-hidden="true">
                            <i class="ph-fill ph-wrench"></i>
                        </span>

                        <span class="svc-detail-copy">
                            <dt>Serviço</dt>
                            <dd>{{ $order->service->name }}</dd>
                        </span>
                    </div>

                    <div class="svc-detail-item beneficiary">
                        <span class="svc-detail-icon" aria-hidden="true">
                            <i class="ph-fill ph-user-circle"></i>
                        </span>

                        <span class="svc-detail-copy">
                            <dt>Beneficiário</dt>
                            <dd>{{ $beneficiaryName }}</dd>
                        </span>
                    </div>

                    <div class="svc-detail-item provider">
                        <span class="svc-detail-icon" aria-hidden="true">
                            <i class="ph-fill ph-user-gear"></i>
                        </span>

                        <span class="svc-detail-copy">
                            <dt>Prestador</dt>
                            <dd>{{ $providerName }}</dd>
                        </span>
                    </div>

                    <div class="svc-detail-item schedule">
                        <span class="svc-detail-icon" aria-hidden="true">
                            <i class="ph-fill ph-calendar-check"></i>
                        </span>

                        <span class="svc-detail-copy">
                            <dt>Agendamento</dt>
                            <dd>{{ $scheduledLabel }}</dd>
                        </span>
                    </div>

                    <div class="svc-detail-item location">
                        <span class="svc-detail-icon" aria-hidden="true">
                            <i class="ph-fill ph-map-pin"></i>
                        </span>

                        <span class="svc-detail-copy">
                            <dt>Local</dt>
                            <dd>{{ $locationLabel }}</dd>
                        </span>
                    </div>
                </dl>
            </div>
        </details>
    </section>

    @if($executionStatus !== 'in_progress')
    <section class="svc-finance-strip">
        <div class="svc-finance-item receivable">
            <span>
                <i class="ph-fill ph-bank"></i>
                Organização recebe
            </span>

            <strong>
                {{ $receivablePreview !== null
                    ? $formatMoney($receivablePreview)
                    : 'A calcular' }}
            </strong>

            <small>
                {{ $formatMethod($customerPricingMethod) }}
                @if($order->serviceVersion?->customer_rate)
                    · {{ $formatMoney($order->serviceVersion->customer_rate) }}
                @endif
            </small>
        </div>

        <div class="svc-finance-divider" aria-hidden="true"></div>

        <div class="svc-finance-item payable">
            <span>
                <i class="ph-fill ph-hand-coins"></i>
                Prestador recebe
            </span>

            <strong>
                {{ $payablePreview !== null
                    ? $formatMoney($payablePreview)
                    : 'A calcular' }}
            </strong>

            <small>
                {{ $formatMethod($providerPricingMethod) }}
                @if($providerConfiguredRate)
                    · {{ $formatMoney($providerConfiguredRate) }}
                @endif
            </small>
        </div>
    </section>
    @endif

        </aside>
    </div> {{-- /svc-workbench --}}

    <dialog
        class="svc-file-viewer"
        id="svc-file-viewer"
        aria-label="Visualização do arquivo"
    >
        <div class="svc-file-viewer-toolbar">
            <span class="svc-file-viewer-title" id="svc-file-viewer-title">
                Visualização
            </span>

            <button
                type="button"
                class="svc-file-viewer-close"
                data-file-viewer-close
                aria-label="Fechar visualização"
                title="Fechar"
            >
                <i class="ph-fill ph-x-circle"></i>
            </button>
        </div>

        <div class="svc-file-viewer-stage">
            <img
                id="svc-file-viewer-image"
                class="svc-file-viewer-image"
                src=""
                alt="Imagem ampliada"
                hidden
            >

            <iframe
                id="svc-file-viewer-frame"
                class="svc-file-viewer-frame"
                src="about:blank"
                title="Prévia do documento"
                hidden
            ></iframe>

            <div
                id="svc-file-viewer-fallback"
                class="svc-file-viewer-fallback"
                hidden
            >
                <span class="svc-file-viewer-fallback-icon">
                    <i class="ph-fill ph-file"></i>
                </span>

                <strong>Este arquivo não possui prévia incorporada.</strong>

                <a
                    id="svc-file-viewer-open"
                    class="svc-btn"
                    href="#"
                    target="_blank"
                    rel="noopener"
                >
                    <i class="ph-fill ph-arrow-square-out"></i>
                    Abrir arquivo
                </a>
            </div>
        </div>
    </dialog>


    <div
        class="svc-reload-overlay"
        id="svc-reload-overlay"
        hidden
        role="status"
        aria-live="assertive"
        aria-busy="true"
    >
        <div class="svc-reload-card">
            <span class="svc-reload-spinner" aria-hidden="true">
                <i class="ph-fill ph-spinner-gap"></i>
            </span>

            <strong id="svc-reload-title">
                Atualizando ordem…
            </strong>

            <small id="svc-reload-copy">
                Salvando o novo estado e recarregando a tela.
            </small>
        </div>
    </div>

</main>

<script>
    const serviceWorkspace =
        document.querySelector('.service-execution');

    const serviceDraftUrl =
        serviceWorkspace?.dataset.draftUrl || '';

    const serviceLocalKey =
        serviceWorkspace?.dataset.localKey || '';

    const serviceMeterStartField =
        serviceWorkspace?.dataset.meterStart || '';

    const serviceMeterEndField =
        serviceWorkspace?.dataset.meterEnd || '';

    const serviceExecutionUnit =
        serviceWorkspace?.dataset.executionUnit || '';

    const serviceExecutionStatus =
        serviceWorkspace?.dataset.executionStatus || '';

    const previewForm = document.getElementById('execution-form');
    const totals = previewForm?.querySelector('[data-service-totals]');

    if (totals) {
        const status = totals.querySelector('[data-total-status]');
        const amounts = [...totals.querySelectorAll('[data-total]')];
        const money = new Intl.NumberFormat('pt-BR', {style: 'currency', currency: 'BRL'});
        let timer;
        let pending;

        const updatePreview = () => {
            clearTimeout(timer);
            pending?.abort();
            const request = pending = new AbortController();
            amounts.forEach(amount => amount.textContent = '—');
            status.textContent = 'Calculando valores…';
            timer = setTimeout(async () => {
                const data = new URLSearchParams();
                for (const [key, value] of new FormData(previewForm)) {
                    if (key === '_token' || (key.startsWith('values[') && typeof value === 'string')) data.append(key, value);
                }

                try {
                    const response = await fetch(previewForm.dataset.previewUrl, {
                        method: 'POST', body: data, signal: request.signal, globalLoader: false,
                        headers: {'Accept': 'application/json'},
                    });
                    if (request.signal.aborted) return;
                    if (response.status === 422) {
                        status.textContent = 'Confira os dados do serviço para calcular os valores.';
                        return;
                    }
                    if (!response.ok) throw new Error('Preview unavailable');
                    const result = await response.json();
                    if (request.signal.aborted) return;
                    amounts.forEach(amount => amount.textContent = money.format(result[amount.dataset.total]));
                    status.textContent = 'Estimativa com os dados desta tela.';
                } catch {
                    if (!request.signal.aborted) status.textContent = 'Prévia indisponível. Altere um campo para tentar novamente.';
                }
            }, 450);
        };

        totals.hidden = false;
        ['input', 'change'].forEach(event => previewForm.addEventListener(event, ({target}) => {
            if (target.name?.startsWith('values[')) updatePreview();
        }));
        window.addEventListener('pageshow', updatePreview);
    }

    const serviceReloadOverlay =
        document.getElementById('svc-reload-overlay');

    const serviceReloadTitle =
        document.getElementById('svc-reload-title');

    const serviceReloadCopy =
        document.getElementById('svc-reload-copy');

    const serviceFileViewer =
        document.getElementById('svc-file-viewer');

    const serviceFileViewerImage =
        document.getElementById('svc-file-viewer-image');

    const serviceFileViewerFrame =
        document.getElementById('svc-file-viewer-frame');

    const serviceFileViewerFallback =
        document.getElementById('svc-file-viewer-fallback');

    const serviceFileViewerOpen =
        document.getElementById('svc-file-viewer-open');

    const serviceFileViewerTitle =
        document.getElementById('svc-file-viewer-title');

    let serviceViewerPushedHistory = false;

    function resetServiceFileViewer() {
        if (serviceFileViewerImage) {
            serviceFileViewerImage.hidden = true;
            serviceFileViewerImage.src = '';
        }

        if (serviceFileViewerFrame) {
            serviceFileViewerFrame.hidden = true;
            serviceFileViewerFrame.src = 'about:blank';
        }

        if (serviceFileViewerFallback) {
            serviceFileViewerFallback.hidden = true;
        }

        if (serviceFileViewerOpen) {
            serviceFileViewerOpen.href = '#';
        }
    }

    function openServiceFileViewer(
        source,
        type = 'file',
        title = 'Visualização'
    ) {
        if (!serviceFileViewer || !source) {
            return;
        }

        resetServiceFileViewer();

        if (serviceFileViewerTitle) {
            serviceFileViewerTitle.textContent =
                title || 'Visualização';
        }

        if (type === 'image' && serviceFileViewerImage) {
            serviceFileViewerImage.src = source;
            serviceFileViewerImage.hidden = false;
        } else if (type === 'pdf' && serviceFileViewerFrame) {
            serviceFileViewerFrame.src = source;
            serviceFileViewerFrame.hidden = false;
        } else if (serviceFileViewerFallback) {
            serviceFileViewerFallback.hidden = false;

            if (serviceFileViewerOpen) {
                serviceFileViewerOpen.href = source;
            }
        }

        if (!serviceFileViewer.hasAttribute('open')) {
            if (
                typeof serviceFileViewer.showModal
                === 'function'
            ) {
                serviceFileViewer.showModal();
            } else {
                serviceFileViewer.setAttribute('open', '');
            }
        }

        if (!history.state?.svcFileViewer) {
            history.pushState(
                {
                    ...(history.state || {}),
                    svcFileViewer: true,
                },
                '',
                window.location.href
            );

            serviceViewerPushedHistory = true;
        }
    }

    function closeServiceFileViewerDirect() {
        if (!serviceFileViewer) {
            return;
        }

        if (
            typeof serviceFileViewer.close === 'function'
            && serviceFileViewer.open
        ) {
            serviceFileViewer.close();
        } else {
            serviceFileViewer.removeAttribute('open');
        }

        resetServiceFileViewer();
        serviceViewerPushedHistory = false;
    }

    function requestCloseServiceFileViewer() {
        if (
            serviceViewerPushedHistory
            && history.state?.svcFileViewer
        ) {
            history.back();
            return;
        }

        closeServiceFileViewerDirect();
    }

    function bindServicePreviewTriggers(root = document) {
        root
            .querySelectorAll(
                '.svc-preview-trigger:not([data-preview-bound])'
            )
            .forEach(trigger => {
                trigger.dataset.previewBound = '1';

                trigger.addEventListener(
                    'click',
                    event => {
                        event.preventDefault();

                        openServiceFileViewer(
                            trigger.dataset.previewUrl,
                            trigger.dataset.previewType || 'file',
                            trigger.dataset.previewTitle || 'Visualização'
                        );
                    }
                );
            });
    }

    function bindServiceImagePreview(
        image,
        title = 'Prévia da imagem',
        sourceOverride = null
    ) {
        if (!image) {
            return;
        }

        image.setAttribute('tabindex', '0');
        image.setAttribute('role', 'button');
        image.setAttribute('aria-label', 'Ampliar imagem');

        const open = () => {
            openServiceFileViewer(
                sourceOverride || image.currentSrc || image.src,
                'image',
                title
            );
        };

        image.addEventListener('click', event => {
            event.preventDefault();
            open();
        });

        image.addEventListener('keydown', event => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                open();
            }
        });
    }

    bindServicePreviewTriggers();

    document
        .querySelectorAll(
            '.svc-file-preview img'
        )
        .forEach(image => {
            if (!image.closest('.svc-preview-trigger')) {
                bindServiceImagePreview(
                    image,
                    image.alt || 'Prévia da imagem'
                );
            }
        });

    document
        .querySelector('[data-file-viewer-close]')
        ?.addEventListener(
            'click',
            requestCloseServiceFileViewer
        );

    serviceFileViewer?.addEventListener(
        'cancel',
        event => {
            event.preventDefault();
            requestCloseServiceFileViewer();
        }
    );

    serviceFileViewer?.addEventListener(
        'click',
        event => {
            if (event.target === serviceFileViewer) {
                requestCloseServiceFileViewer();
            }
        }
    );

    window.addEventListener(
        'popstate',
        () => {
            if (serviceFileViewer?.hasAttribute('open')) {
                closeServiceFileViewerDirect();
            }
        }
    );

    const SERVICE_DRAFT_DB_NAME =
        'sgc-service-drafts';

    const SERVICE_DRAFT_DB_VERSION =
        1;

    const SERVICE_DRAFT_EVIDENCE_STORE =
        'evidenceFiles';

    function serviceDraftEvidenceId(input) {
        if (!serviceLocalKey || !input?.name) {
            return '';
        }

        return `${serviceLocalKey}:${input.name}`;
    }

    function openServiceDraftDb() {
        return new Promise((resolve, reject) => {
            if (!('indexedDB' in window)) {
                reject(
                    new Error(
                        'IndexedDB indisponível'
                    )
                );
                return;
            }

            const request =
                indexedDB.open(
                    SERVICE_DRAFT_DB_NAME,
                    SERVICE_DRAFT_DB_VERSION
                );

            request.onupgradeneeded = () => {
                const db = request.result;

                if (
                    !db.objectStoreNames.contains(
                        SERVICE_DRAFT_EVIDENCE_STORE
                    )
                ) {
                    const store =
                        db.createObjectStore(
                            SERVICE_DRAFT_EVIDENCE_STORE,
                            {
                                keyPath: 'id',
                            }
                        );

                    store.createIndex(
                        'draftKey',
                        'draftKey',
                        {
                            unique: false,
                        }
                    );
                }
            };

            request.onsuccess = () => {
                resolve(request.result);
            };

            request.onerror = () => {
                reject(
                    request.error
                    || new Error(
                        'Falha ao abrir cache local'
                    )
                );
            };
        });
    }

    async function saveServiceDraftEvidenceFile(
        input,
        file
    ) {
        const id =
            serviceDraftEvidenceId(input);

        if (!id || !file) {
            return;
        }

        try {
            const db =
                await openServiceDraftDb();

            await new Promise(
                (resolve, reject) => {
                    const transaction =
                        db.transaction(
                            SERVICE_DRAFT_EVIDENCE_STORE,
                            'readwrite'
                        );

                    transaction
                        .objectStore(
                            SERVICE_DRAFT_EVIDENCE_STORE
                        )
                        .put({
                            id,
                            draftKey:
                                serviceLocalKey,
                            inputName:
                                input.name,
                            fileName:
                                file.name,
                            type:
                                file.type,
                            lastModified:
                                file.lastModified
                                || Date.now(),
                            blob:
                                file,
                            savedAt:
                                Date.now(),
                        });

                    transaction.oncomplete =
                        () => resolve();

                    transaction.onerror =
                        () => reject(
                            transaction.error
                        );
                }
            );

            db.close();
        } catch (error) {
            /*
             * Cache local é uma melhoria de resiliência.
             * Falha nele não deve impedir o envio normal.
             */
        }
    }

    async function removeServiceDraftEvidenceFile(
        input
    ) {
        const id =
            serviceDraftEvidenceId(input);

        if (!id) {
            return;
        }

        try {
            const db =
                await openServiceDraftDb();

            await new Promise(
                (resolve, reject) => {
                    const transaction =
                        db.transaction(
                            SERVICE_DRAFT_EVIDENCE_STORE,
                            'readwrite'
                        );

                    transaction
                        .objectStore(
                            SERVICE_DRAFT_EVIDENCE_STORE
                        )
                        .delete(id);

                    transaction.oncomplete =
                        () => resolve();

                    transaction.onerror =
                        () => reject(
                            transaction.error
                        );
                }
            );

            db.close();
        } catch (error) {}
    }

    async function clearServiceDraftEvidenceFiles() {
        if (!serviceLocalKey) {
            return;
        }

        try {
            const db =
                await openServiceDraftDb();

            await new Promise(
                (resolve, reject) => {
                    const transaction =
                        db.transaction(
                            SERVICE_DRAFT_EVIDENCE_STORE,
                            'readwrite'
                        );

                    const store =
                        transaction.objectStore(
                            SERVICE_DRAFT_EVIDENCE_STORE
                        );

                    const index =
                        store.index('draftKey');

                    const request =
                        index.openCursor(
                            IDBKeyRange.only(
                                serviceLocalKey
                            )
                        );

                    request.onsuccess = () => {
                        const cursor =
                            request.result;

                        if (!cursor) {
                            return;
                        }

                        cursor.delete();
                        cursor.continue();
                    };

                    transaction.oncomplete =
                        () => resolve();

                    transaction.onerror =
                        () => reject(
                            transaction.error
                        );
                }
            );

            db.close();
        } catch (error) {}
    }

    const SERVICE_ALLOWED_IMAGE_EXTENSIONS =
        new Set([
            'jpg',
            'jpeg',
            'png',
            'webp',
        ]);

    const SERVICE_ALLOWED_IMAGE_TYPES =
        new Set([
            'image/jpeg',
            'image/png',
            'image/webp',
        ]);

    function serviceFileExtension(file) {
        const name =
            String(
                file?.name
                || ''
            );

        const dotIndex =
            name.lastIndexOf('.');

        return dotIndex >= 0
            ? name
                .slice(dotIndex + 1)
                .toLowerCase()
            : '';
    }

    function isServiceEvidenceImage(file) {
        if (!file) {
            return false;
        }

        return (
            SERVICE_ALLOWED_IMAGE_EXTENSIONS
                .has(
                    serviceFileExtension(file)
                )
            || SERVICE_ALLOWED_IMAGE_TYPES
                .has(
                    String(
                        file.type
                        || ''
                    ).toLowerCase()
                )
        );
    }

    function isServiceEvidencePdf(file) {
        if (!file) {
            return false;
        }

        return (
            serviceFileExtension(file) === 'pdf'
            || String(
                file.type
                || ''
            ).toLowerCase()
                === 'application/pdf'
        );
    }

    function validateServiceEvidenceFile(
        input,
        file
    ) {
        if (!file) {
            return {
                valid: false,
                message: 'Nenhum arquivo selecionado.',
            };
        }

        const extension =
            serviceFileExtension(file);

        const type =
            String(
                file.type
                || ''
            ).toLowerCase();

        const isVideo =
            type.startsWith('video/')
            || [
                'mp4',
                'mov',
                'm4v',
                'avi',
                'mkv',
                'webm',
                '3gp',
                '3gpp',
            ].includes(extension);

        if (isVideo) {
            return {
                valid: false,
                message:
                    'Vídeos não são permitidos. Escolha uma foto ou imagem.',
            };
        }

        const allowedKind =
            input?.dataset.allowedKind
            || 'image';

        const isImage =
            isServiceEvidenceImage(file);

        const isPdf =
            isServiceEvidencePdf(file);

        const selectedMime = isPdf
            ? 'application/pdf'
            : (file.type === 'image/jpg' ? 'image/jpeg' : file.type);
        const allowedMimes = (input?.dataset.allowedMimes || '').split(',').filter(Boolean);
        if (allowedMimes.length && !allowedMimes.includes(selectedMime)) {
            return { valid: false, message: 'Formato não permitido para este comprovante. Escolha um dos formatos indicados no campo.' };
        }

        if (
            allowedKind === 'image'
            && !isImage
        ) {
            return {
                valid: false,
                message:
                    'Este campo aceita somente JPG, JPEG, PNG ou WebP.',
            };
        }

        if (
            allowedKind === 'image_or_pdf'
            && !isImage
            && !isPdf
        ) {
            return {
                valid: false,
                message:
                    'Escolha JPG, JPEG, PNG, WebP ou PDF.',
            };
        }

        return {
            valid: true,
            message: '',
        };
    }

    function clearEvidenceFileError(input) {
        const wrapper =
            input?.closest(
                '.svc-evidence-field'
            );

        wrapper?.classList.remove(
            'has-evidence-error'
        );

        wrapper
            ?.querySelector(
                '.svc-evidence-file-error'
            )
            ?.remove();
    }

    function showEvidenceFileError(
        input,
        message
    ) {
        const wrapper =
            input?.closest(
                '.svc-evidence-field'
            );

        if (!wrapper) {
            return;
        }

        wrapper.classList.add(
            'has-evidence-error'
        );

        let error =
            wrapper.querySelector(
                '.svc-evidence-file-error'
            );

        if (!error) {
            error =
                document.createElement(
                    'small'
                );

            error.className =
                'svc-evidence-file-error';

            const picker =
                wrapper.querySelector(
                    '.svc-evidence-picker'
                );

            if (picker) {
                picker.insertAdjacentElement(
                    'afterend',
                    error
                );
            } else {
                wrapper.appendChild(error);
            }
        }

        error.innerHTML = `
            <i class="ph-fill ph-warning-circle"></i>
            ${escapeHtml(message)}
        `;
    }

    function setEvidenceInputFile(
        input,
        file
    ) {
        if (!input || !file) {
            return false;
        }

        try {
            const transfer =
                new DataTransfer();

            transfer.items.add(file);
            input.files = transfer.files;

            return true;
        } catch (error) {
            return false;
        }
    }

    function evidenceSelectionFor(input) {
        const id =
            input?.dataset.selection;

        return id
            ? document.getElementById(id)
            : null;
    }

    function updateEvidenceSelection(
        input,
        {
            saved = false,
            restored = false,
        } = {}
    ) {
        const selection =
            evidenceSelectionFor(input);

        if (!selection) {
            return;
        }

        const file =
            input.files?.[0];

        selection.classList.toggle(
            'is-selected',
            Boolean(file)
        );

        selection.classList.toggle(
            'is-saved',
            saved
            || input.dataset.evidenceSaved === '1'
        );

        if (file) {
            selection.innerHTML = `
                <i class="ph-fill ph-check-circle"></i>
                ${escapeHtml(file.name)}
                ${restored ? ' · recuperado do rascunho' : ''}
            `;
            return;
        }

        if (
            saved
            || input.dataset.evidenceSaved === '1'
        ) {
            selection.innerHTML = `
                <i class="ph-fill ph-check-circle"></i>
                Arquivo já salvo
            `;
            return;
        }

        selection.textContent =
            'Nenhum novo arquivo selecionado';
    }

    async function restoreServiceDraftEvidenceFiles(
        root = document
    ) {
        if (!serviceLocalKey) {
            return;
        }

        let db;

        try {
            db =
                await openServiceDraftDb();
        } catch (error) {
            return;
        }

        const inputs = [
            ...root.querySelectorAll(
                '.svc-evidence-input'
            ),
        ];

        for (const input of inputs) {
            if (input.files?.length) {
                updateEvidenceSelection(input);
                continue;
            }

            const id =
                serviceDraftEvidenceId(input);

            if (!id) {
                continue;
            }

            const record =
                await new Promise(resolve => {
                    const transaction =
                        db.transaction(
                            SERVICE_DRAFT_EVIDENCE_STORE,
                            'readonly'
                        );

                    const request =
                        transaction
                            .objectStore(
                                SERVICE_DRAFT_EVIDENCE_STORE
                            )
                            .get(id);

                    request.onsuccess =
                        () => resolve(
                            request.result
                            || null
                        );

                    request.onerror =
                        () => resolve(null);
                });

            if (!record?.blob) {
                updateEvidenceSelection(
                    input,
                    {
                        saved:
                            input.dataset.evidenceSaved
                            === '1',
                    }
                );
                continue;
            }

            const restoredFile =
                new File(
                    [record.blob],
                    record.fileName
                    || 'evidencia',
                    {
                        type:
                            record.type
                            || record.blob.type
                            || 'application/octet-stream',
                        lastModified:
                            record.lastModified
                            || Date.now(),
                    }
                );

            const restoredValidation =
                validateServiceEvidenceFile(
                    input,
                    restoredFile
                );

            if (!restoredValidation.valid) {
                await removeServiceDraftEvidenceFile(
                    input
                );
                continue;
            }

            if (
                !setEvidenceInputFile(
                    input,
                    restoredFile
                )
            ) {
                continue;
            }

            const box =
                document.getElementById(
                    input.dataset.preview
                );

            if (box) {
                renderOriginalFile(
                    box,
                    restoredFile
                );
            }

            updateEvidenceSelection(
                input,
                {
                    restored: true,
                }
            );
        }

        db.close();
    }

    function validateServiceEvidenceInputs(
        form
    ) {
        let firstInvalid = null;

        form
            .querySelectorAll(
                '.svc-evidence-input'
            )
            .forEach(input => {
                const wrapper =
                    input.closest(
                        '.svc-evidence-field'
                    );

                wrapper?.classList.remove(
                    'has-evidence-error'
                );

                if (
                    input.disabled
                    || wrapper?.hidden
                    || input.dataset.required !== '1'
                ) {
                    return;
                }

                const hasNewFile =
                    Boolean(
                        input.files?.length
                    );

                const hasSavedFile =
                    input.dataset.evidenceSaved
                    === '1';

                if (
                    !hasNewFile
                    && !hasSavedFile
                ) {
                    wrapper?.classList.add(
                        'has-evidence-error'
                    );

                    firstInvalid ??=
                        input;
                }
            });

        if (!firstInvalid) {
            return true;
        }

        const wrapper =
            firstInvalid.closest(
                '.svc-evidence-field'
            );

        wrapper?.scrollIntoView({
            behavior: 'smooth',
            block: 'center',
        });

        const fileButton =
            wrapper?.querySelector(
                '[data-open-file-picker]'
            );

        fileButton?.focus({
            preventScroll: true,
        });

        return false;
    }

    function showServiceReloadOverlay(
        title = 'Atualizando ordem…',
        copy = 'Salvando o novo estado e recarregando a tela.'
    ) {
        if (!serviceReloadOverlay) {
            return;
        }

        if (serviceReloadTitle) {
            serviceReloadTitle.textContent =
                title;
        }

        if (serviceReloadCopy) {
            serviceReloadCopy.textContent =
                copy;
        }

        serviceReloadOverlay.hidden = false;
    }

    document.addEventListener(
        'click',
        async event => {
            const fileButton =
                event.target.closest(
                    '[data-open-file-picker]'
                );

            if (fileButton) {
                const target =
                    document.getElementById(
                        fileButton.dataset
                            .openFilePicker
                    );

                target?.click();
                return;
            }

            const cameraButton =
                event.target.closest(
                    '[data-open-camera]'
                );

            if (cameraButton) {
                const cameraInput =
                    document.getElementById(
                        cameraButton.dataset
                            .openCamera
                    );

                const evidenceInput =
                    document.getElementById(
                        cameraInput?.dataset
                            .targetInput
                    );

                const nativeCamera =
                    window.Capacitor?.Plugins
                        ?.NativeCamera;

                if (
                    window.Capacitor
                        ?.isNativePlatform?.()
                    && nativeCamera?.takePhoto
                    && evidenceInput
                ) {
                    cameraButton.disabled = true;

                    try {
                        const capture =
                            await nativeCamera
                                .takePhoto();

                        const blob =
                            await fetch(
                                capture.dataUrl
                            ).then(
                                response => response.blob()
                            );

                        const file = new File(
                            [blob],
                            capture.fileName
                                || 'evidencia.jpg',
                            {
                                type:
                                    capture.mimeType
                                    || 'image/jpeg',
                            }
                        );

                        if (
                            setEvidenceInputFile(
                                evidenceInput,
                                file
                            )
                        ) {
                            evidenceInput.dispatchEvent(
                                new Event(
                                    'change',
                                    {bubbles: true}
                                )
                            );
                        }
                    } catch (error) {
                        if (
                            error?.code
                            !== 'CAMERA_CANCELLED'
                        ) {
                            window.appToast?.(
                                'A câmera nativa não respondeu. Abrindo a câmera compatível do aparelho.',
                                'warning'
                            );
                            cameraInput?.click();
                        }
                    } finally {
                        cameraButton.disabled = false;
                    }

                    return;
                }

                cameraInput?.click();
            }
        }
    );

    document.addEventListener(
        'change',
        event => {
            const cameraInput =
                event.target.closest(
                    '.svc-evidence-camera-input'
                );

            if (!cameraInput) {
                return;
            }

            const file =
                cameraInput.files?.[0];

            const target =
                document.getElementById(
                    cameraInput.dataset
                        .targetInput
                );

            if (file && target) {
                const validation =
                    validateServiceEvidenceFile(
                        target,
                        file
                    );

                if (!validation.valid) {
                    showEvidenceFileError(
                        target,
                        validation.message
                    );

                    cameraInput.value = '';
                    return;
                }

                clearEvidenceFileError(target);

                if (
                    setEvidenceInputFile(
                        target,
                        file
                    )
                ) {
                    target.dispatchEvent(
                        new Event(
                            'change',
                            {
                                bubbles: true,
                            }
                        )
                    );
                }
            }

            cameraInput.value = '';
        }
    );

    async function submitServiceForm(form, {draft = false} = {}) {
        if (
            !draft
            && !validateServiceEvidenceInputs(form)
        ) {
            const feedback = feedbackFor(form);

            feedback.className =
                'svc-alert danger svc-async-feedback';

            feedback.innerHTML = `
                <span class="svc-alert-icon">
                    <i class="ph-fill ph-warning-circle"></i>
                </span>
                <div>
                    <strong>Falta uma evidência obrigatória.</strong>
                    <br>
                    <small>
                        Escolha um arquivo ou tire uma foto no campo destacado.
                    </small>
                </div>
            `;

            feedback.hidden = false;
            return;
        }

        if (!draft && !form.reportValidity()) {
            return;
        }

        let navigating = false;
        const buttons = [...form.querySelectorAll('button:enabled')];
        const activeButton = draft ? form.querySelector('[data-save-draft]') : form.querySelector('[type="submit"]');
        const originalLabel = activeButton?.innerHTML;
        const loadingLabel = draft ? 'Salvando rascunho…' : 'Enviando dados…';
        if (activeButton) activeButton.innerHTML = '<i class="ph ph-spinner-gap svc-save-spinner" aria-hidden="true"></i> ' + loadingLabel;
        const feedback = feedbackFor(form);
        buttons.forEach(button => button.disabled = true);
        feedback.className = 'svc-alert svc-async-feedback';
        feedback.textContent = loadingLabel;
        feedback.hidden = false;

        try {
            const data = new FormData(form);
            if (draft) data.set('_method', 'PUT');
            const response = await fetch(draft ? serviceDraftUrl : form.action, {
                method: 'POST', body: data, credentials: 'same-origin', globalLoader: false,
                headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}
            });
            const payload = await response.json().catch(() => ({message: 'O servidor retornou uma resposta inválida.'}));
            if (!response.ok) {
                const messages = payload.errors ? Object.values(payload.errors).flat() : [payload.message || 'Não foi possível salvar.'];
                showAsyncErrors(form, feedback, messages, payload.errors || {});
                return;
            }

            feedback.className =
                'svc-alert svc-async-feedback';

            feedback.innerHTML = `
                <span class="svc-alert-icon">
                    <i class="ph-fill ph-check-circle"></i>
                </span>
                <div>
                    <strong>
                        ${escapeHtml(payload.message || 'Dados salvos.')}
                    </strong>
                    <br>
                    <small>
                        ${draft
                            ? 'Rascunho salvo com segurança.'
                            : 'Atualizando o estado da ordem…'}
                    </small>
                </div>
            `;

            await updateEvidencePreviews(
                form,
                payload.evidences || []
            );

            /*
             * Se o servidor persistiu a evidência, a referência
             * deixa de depender do FileList local.
             */
            form
                .querySelectorAll(
                    '.svc-evidence-input'
                )
                .forEach(input => {
                    if (
                        input.dataset.evidenceSaved
                        === '1'
                    ) {
                        input.value = '';
                        updateEvidenceSelection(
                            input,
                            {
                                saved: true,
                            }
                        );
                    }
                });

            if (serviceLocalKey) {
                localStorage.removeItem(
                    serviceLocalKey
                );
            }

            /*
             * Toda ação não-rascunho é uma transição de estado
             * nesta tela. Recarrega por padrão, salvo se o backend
             * explicitamente responder reload:false.
             */
            const shouldReload =
                draft
                    ? Boolean(payload.reload)
                    : payload.reload !== false;

            if (!draft) {
                await clearServiceDraftEvidenceFiles();
            }

            if (shouldReload) {
                navigating = true;

                showServiceReloadOverlay(
                    payload.message
                    || 'Atualizando ordem…',
                    'Abrindo a ordem atualizada…'
                );

                window.location.assign(payload.url || window.location.href);
            }
        } catch (error) {
            showAsyncErrors(form, feedback, ['Falha de conexão. Os dados continuam nesta tela; tente novamente.'], {});
        } finally {
            if (!navigating) {
                if (activeButton) activeButton.innerHTML = originalLabel;
                buttons.forEach(
                    button => {
                        button.disabled = false;
                    }
                );
            }
        }
    }

    function feedbackFor(form) {
        let feedback = form.querySelector('.svc-async-feedback');
        if (!feedback) {
            feedback = document.createElement('div'); feedback.className = 'svc-alert svc-async-feedback'; feedback.hidden = true;
            feedback.setAttribute('role', 'status');
            form.insertBefore(feedback, form.querySelector('.svc-form-actions'));
        }
        return feedback;
    }

    function showAsyncErrors(form, feedback, messages, errors) {
        feedback.className = 'svc-alert danger svc-async-feedback';
        feedback.innerHTML = `<span class="svc-alert-icon"><i class="ph-fill ph-warning-circle"></i></span><div><strong>Não foi possível concluir.</strong><ul style="margin:.35rem 0 0;padding-left:1.15rem">${messages.map(message => `<li>${escapeHtml(message)}</li>`).join('')}</ul><small>Corrija somente os itens indicados. Tudo o que foi preenchido continua na tela.</small></div>`;
        form.querySelectorAll('[aria-invalid="true"]').forEach(field => field.removeAttribute('aria-invalid'));
        let first = null;
        Object.keys(errors).forEach(key => {
            const simple = key.replace(/^values\./, '').replace(/^evidences\./, '');
            const field = form.querySelector(`[name="values[${CSS.escape(simple)}]"], [name="evidences[${CSS.escape(simple)}]"]`);
            if (field) { field.setAttribute('aria-invalid', 'true'); first ||= field; }
        });
        (first || feedback).scrollIntoView({behavior: 'smooth', block: 'center'});
        first?.focus({preventScroll: true});
    }

    async function updateEvidencePreviews(form, evidences) {
        for (const item of evidences) {
            const input = form.querySelector(
                `[name="evidences[${CSS.escape(item.field_key)}]"]`
            );

            const box = input
                ? document.getElementById(input.dataset.preview)
                : null;

            if (!box) {
                return;
            }

            const name =
                escapeHtml(item.name || 'Evidência enviada');

            const url =
                escapeHtml(item.url || '');

            const mime =
                String(item.mime_type || '');

            const previewType =
                mime.startsWith('image/')
                    ? 'image'
                    : (
                        mime === 'application/pdf'
                            ? 'pdf'
                            : 'file'
                    );

            if (previewType === 'image') {
                box.innerHTML = `
                    <button
                        type="button"
                        class="svc-image-preview-button svc-preview-trigger"
                        data-preview-url="${url}"
                        data-preview-type="image"
                        data-preview-title="${name}"
                    >
                        <img
                            src="${url}"
                            alt="Prévia de ${name}"
                        >
                        <span class="svc-image-preview-hint">
                            <i class="ph-fill ph-arrows-out"></i>
                            Ampliar
                        </span>
                    </button>
                `;
            } else {
                box.innerHTML = `
                    <button
                        type="button"
                        class="svc-table-link svc-preview-trigger"
                        data-preview-url="${url}"
                        data-preview-type="${previewType}"
                        data-preview-title="${name}"
                    >
                        <i class="ph-fill ph-eye"></i>
                        Visualizar arquivo
                    </button>
                `;
            }

            input.dataset.evidenceSaved = '1';

            input
                .closest('.svc-evidence-field')
                ?.classList.remove(
                    'has-evidence-error'
                );

            updateEvidenceSelection(
                input,
                {
                    saved: true,
                }
            );

            await removeServiceDraftEvidenceFile(
                input
            );

            bindServicePreviewTriggers(box);
        }
    }

    document.addEventListener(
        'DOMContentLoaded',
        async () => {
            bindServicePreviewTriggers();

            /*
             * Se a ordem já chegou ao estado final, não há motivo
             * para manter rascunho ou arquivos temporários locais.
             */
            if (
                serviceExecutionStatus === 'validated'
            ) {
                if (serviceLocalKey) {
                    localStorage.removeItem(
                        serviceLocalKey
                    );
                }

                await clearServiceDraftEvidenceFiles();
            }

            document.querySelectorAll('[data-async-service-form]').forEach(form => {
                form.addEventListener('submit', event => { event.preventDefault(); submitServiceForm(form); });
                form.querySelector('[data-save-draft]')?.addEventListener('click', () => submitServiceForm(form, {draft: true}));
            });

            const executionForm = document.getElementById('execution-form');
            if (executionForm) {
                try {
                    const saved = serviceLocalKey
                        ? JSON.parse(
                            localStorage.getItem(serviceLocalKey)
                            || '{}'
                        )
                        : {};
                    Object.entries(saved).forEach(([name, value]) => {
                        const field = executionForm.elements.namedItem(name);
                        if (field && field.type !== 'file') {
                            field.value = value;
                            if (field.closest('[data-meter-pauses]')) field.dispatchEvent(new Event('restore-pauses'));
                        }
                    });
                } catch (error) {
                    if (serviceLocalKey) {
                        localStorage.removeItem(serviceLocalKey);
                    }
                }
                const remember = () => {
                    const values = {};
                    executionForm.querySelectorAll('[name^="values["]').forEach(field => { if (!field.disabled) values[field.name] = field.value; });
                    if (serviceLocalKey) {
                        localStorage.setItem(
                            serviceLocalKey,
                            JSON.stringify(values)
                        );
                    }
                };
                executionForm.addEventListener('input', remember);
                executionForm.addEventListener('change', remember);
            }

            const syncConditions = () => {
                document.querySelectorAll('[data-condition]').forEach(wrapper => {
                    let rule = null; try { rule = JSON.parse(wrapper.dataset.condition || 'null'); } catch (error) {}
                    if (!rule?.field) { wrapper.hidden = false; return; }
                    const source = document.querySelector(`[name="values[${CSS.escape(rule.field)}]"]`);
                    const actual = source?.value; const expected = rule.value;
                    const visible = ({equals:actual == expected, not_equals:actual != expected, is_true:['1','true',1,true].includes(actual), is_false:!['1','true',1,true].includes(actual), greater_than:Number(actual)>Number(expected), less_than:Number(actual)<Number(expected)})[rule.operator || 'equals'] ?? false;
                    wrapper.hidden = !visible;
                    wrapper
                        .querySelectorAll(
                            'input,select,textarea,button'
                        )
                        .forEach(
                            field => {
                                field.disabled =
                                    !visible;
                            }
                        );
                });
            };
            document.addEventListener('input', syncConditions); document.addEventListener('change', syncConditions); syncConditions();

            const startKey =
                serviceMeterStartField;

            const endKey =
                serviceMeterEndField;

            const preview =
                document.getElementById(
                    'meter-preview'
                );

            const result =
                document.getElementById(
                    'meter-result'
                );

            /*
             * Mantém a lógica original da diferença de medidores,
             * mas evita tratar campo vazio como zero.
             */
            if (
                startKey
                && endKey
                && preview
                && result
            ) {
                const start =
                    document.querySelector(
                        `[name="values[${startKey}]"]`
                    );

                const end =
                    document.querySelector(
                        `[name="values[${endKey}]"]`
                    );

                const syncMeterPreview = () => {
                    const startRaw =
                        String(
                            start?.value ?? ''
                        ).trim();

                    const endRaw =
                        String(
                            end?.value ?? ''
                        ).trim();

                    if (!startRaw || !endRaw) {
                        preview.style.display =
                            'none';

                        preview.classList.remove(
                            'is-invalid'
                        );

                        result.textContent = '—';

                        return;
                    }

                    const startValue =
                        Number(startRaw);

                    const endValue =
                        Number(endRaw);

                    const validNumbers =
                        Number.isFinite(startValue)
                        && Number.isFinite(endValue);

                    preview.style.display =
                        validNumbers
                            ? 'grid'
                            : 'none';

                    if (!validNumbers) {
                        return;
                    }

                    const pauses = JSON.parse(document.querySelector('[data-meter-pauses] input[type=hidden]')?.value || '[]');
                    let previous = startValue;
                    let idle = 0;
                    const validDifference = endValue >= startValue && pauses.every(pause => {
                        const stop = Number(pause.stop), resume = Number(pause.resume);
                        const valid = pause.stop !== '' && pause.resume !== '' && stop >= previous && resume >= stop && resume <= endValue;
                        previous = resume;
                        idle += resume - stop;
                        return valid;
                    });

                    preview.classList.toggle(
                        'is-invalid',
                        !validDifference
                    );

                    result.textContent =
                        validDifference
                            ? (
                                endValue
                                - startValue - idle
                            ).toLocaleString(
                                'pt-BR',
                                {
                                    maximumFractionDigits: 4,
                                }
                            )
                            + ' '
                            + serviceExecutionUnit
                            : 'Medição final inválida';
                };

                document.querySelector('[data-meter-pauses]')?.addEventListener('input', syncMeterPreview);
                start?.addEventListener(
                    'input',
                    syncMeterPreview
                );

                end?.addEventListener(
                    'input',
                    syncMeterPreview
                );

                syncMeterPreview();
            }

            /*
             * Mantém a otimização local das imagens antes do
             * upload. Em caso de incompatibilidade do navegador,
             * o arquivo original permanece selecionado.
             */
            document
                .querySelectorAll(
                    '.svc-evidence-input'
                )
                .forEach(input => {
                    input.addEventListener(
                        'change',
                        async () => {
                            const file =
                                input.files?.[0];

                            if (file) {
                                const validation =
                                    validateServiceEvidenceFile(
                                        input,
                                        file
                                    );

                                if (!validation.valid) {
                                    input.value = '';

                                    showEvidenceFileError(
                                        input,
                                        validation.message
                                    );

                                    updateEvidenceSelection(
                                        input
                                    );

                                    return;
                                }

                                clearEvidenceFileError(
                                    input
                                );
                            }

                            const box =
                                document.getElementById(
                                    input.dataset.preview
                                );

                            if (!file || !box) {
                                return;
                            }

                            if (
                                !isServiceEvidenceImage(
                                    file
                                )
                            ) {
                                if (box.dataset.objectUrl) {
                                    URL.revokeObjectURL(
                                        box.dataset.objectUrl
                                    );
                                }

                                const objectUrl =
                                    URL.createObjectURL(file);

                                box.dataset.objectUrl =
                                    objectUrl;

                                const previewType =
                                    isServiceEvidencePdf(file)
                                        ? 'pdf'
                                        : 'file';

                                box.innerHTML = `
                                    <button
                                        type="button"
                                        class="svc-table-link svc-preview-trigger"
                                        data-preview-url="${escapeHtml(objectUrl)}"
                                        data-preview-type="${previewType}"
                                        data-preview-title="${escapeHtml(file.name)}"
                                    >
                                        <i class="ph-fill ph-eye"></i>
                                        Visualizar ${previewType === 'pdf' ? 'PDF' : 'arquivo'}
                                    </button>
                                `;

                                bindServicePreviewTriggers(box);

                                await saveServiceDraftEvidenceFile(
                                    input,
                                    file
                                );

                                updateEvidenceSelection(
                                    input
                                );

                                input.dataset.evidenceSaved =
                                    '0';

                                return;
                            }

                            try {
                                const bitmap =
                                    await createImageBitmap(
                                        file
                                    );

                                const scale =
                                    Math.min(
                                        1,
                                        2048
                                        / Math.max(
                                            bitmap.width,
                                            bitmap.height
                                        )
                                    );

                                const canvas =
                                    document.createElement(
                                        'canvas'
                                    );

                                canvas.width =
                                    Math.round(
                                        bitmap.width
                                        * scale
                                    );

                                canvas.height =
                                    Math.round(
                                        bitmap.height
                                        * scale
                                    );

                                const context =
                                    canvas.getContext('2d');

                                if (!context) {
                                    throw new Error(
                                        'Canvas indisponível'
                                    );
                                }

                                context.drawImage(
                                    bitmap,
                                    0,
                                    0,
                                    canvas.width,
                                    canvas.height
                                );

                                canvas.toBlob(
                                    blob => {
                                        if (!blob) {
                                            renderOriginalFile(
                                                box,
                                                file
                                            );

                                            return;
                                        }

                                        try {
                                            if (!(input.dataset.allowedMimes || '').split(',').includes('image/webp')) {
                                                renderOriginalFile(box, file);
                                                return;
                                            }
                                            const optimized =
                                                new File(
                                                    [blob],
                                                    file.name.replace(
                                                        /\.[^.]+$/,
                                                        ''
                                                    )
                                                    + '.webp',
                                                    {
                                                        type:
                                                            'image/webp',
                                                    }
                                                );

                                            const transfer =
                                                new DataTransfer();

                                            transfer.items.add(
                                                optimized
                                            );

                                            input.files =
                                                transfer.files;
                                        } catch (error) {
                                            /*
                                             * Se DataTransfer/File
                                             * não permitirem troca,
                                             * mantém o original.
                                             */
                                        }

                                        box.innerHTML = '';

                                        const image =
                                            document.createElement(
                                                'img'
                                            );

                                        if (box.dataset.objectUrl) {
                                            URL.revokeObjectURL(
                                                box.dataset.objectUrl
                                            );
                                        }

                                        const objectUrl =
                                            URL.createObjectURL(blob);

                                        box.dataset.objectUrl =
                                            objectUrl;

                                        image.src = objectUrl;
                                        image.alt =
                                            'Prévia da evidência';

                                        box.appendChild(image);

                                        bindServiceImagePreview(
                                            image,
                                            file.name,
                                            objectUrl
                                        );

                                        input.dataset.evidenceSaved =
                                            '0';

                                        const cachedFile =
                                            input.files?.[0]
                                            || file;

                                        saveServiceDraftEvidenceFile(
                                            input,
                                            cachedFile
                                        );

                                        updateEvidenceSelection(
                                            input
                                        );
                                    },
                                    'image/webp',
                                    .82
                                );
                            } catch (error) {
                                renderOriginalFile(
                                    box,
                                    file
                                );

                                input.dataset.evidenceSaved =
                                    '0';

                                await saveServiceDraftEvidenceFile(
                                    input,
                                    file
                                );

                                updateEvidenceSelection(
                                    input
                                );
                            }
                        }
                    );
                });

            await restoreServiceDraftEvidenceFiles(
                document
            );
        }
    );

    function renderOriginalFile(
        box,
        file
    ) {
        if (!isServiceEvidenceImage(file)) {
            if (box.dataset.objectUrl) {
                URL.revokeObjectURL(box.dataset.objectUrl);
            }

            const objectUrl =
                URL.createObjectURL(file);

            box.dataset.objectUrl =
                objectUrl;

            const previewType =
                isServiceEvidencePdf(file)
                    ? 'pdf'
                    : 'file';

            box.innerHTML = `
                <button
                    type="button"
                    class="svc-table-link svc-preview-trigger"
                    data-preview-url="${escapeHtml(objectUrl)}"
                    data-preview-type="${previewType}"
                    data-preview-title="${escapeHtml(file.name)}"
                >
                    <i class="ph-fill ph-eye"></i>
                    Visualizar arquivo
                </button>
            `;

            bindServicePreviewTriggers(box);
            return;
        }

        const reader = new FileReader();

        reader.addEventListener(
            'load',
            () => {
                box.innerHTML = '';

                const image =
                    document.createElement('img');

                image.src =
                    String(reader.result || '');

                image.alt =
                    `Prévia de ${file.name}`;

                box.appendChild(image);

                bindServiceImagePreview(
                    image,
                    file.name,
                    image.src
                );
            },
            {once: true}
        );

        reader.readAsDataURL(file);
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(
                /[&<>"']/g,
                character => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;',
                })[character]
            );
    }
</script>
@endsection
