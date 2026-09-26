@extends('layouts.bento')

@section('title', 'Faturamento '.$receiptNumber)
@section('page-title', 'Dossiê do faturamento')
@section('page-subtitle', $tenant->name)
@section('user-role', 'Contabilidade')

@php
    $bentoNavigation = \App\Support\PortalNavigation::make('accounting', 'processes', $tenant->slug);
@endphp

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/accounting-portal.css') }}">
@endpush

@section('content')
@once
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/fill/style.css">
@endonce

<main
    class="acc-shell accounting-dossier accounting-dossier-v3 ui-page"
    data-accounting-page="dossier"
    data-receipt-number="{{ $receiptNumber }}"
    data-process-data-url="{{ route('accounting.data.processes.show', ['tenant' => $tenant->slug, 'receipt' => $receiptId]) }}"
    data-authorization-send-url="{{ route('accounting.data.processes.authorization.send', ['tenant' => $tenant->slug, 'receipt' => $receiptId]) }}"
    data-authorization-access-url="{{ route('accounting.data.processes.authorization.access', ['tenant' => $tenant->slug, 'receipt' => $receiptId]) }}"
    data-can-send-authorization="{{ auth()->user()?->can('send_accounting_authorizations') ? '1' : '0' }}"
>
    <header
        class="acc-topbar dossier-topbar ui-section"
        aria-labelledby="accounting-dossier-title"
    >
        <div class="dossier-heading">
            <span
                class="dossier-head-icon ui-icon-box"
                data-tone="violet"
                aria-hidden="true"
            >
                <i class="ph-fill ph-receipt"></i>
            </span>

            <div class="dossier-head-copy">
                <h1 id="accounting-dossier-title">
                    Faturamento {{ $receiptNumber }}
                </h1>

                <div class="dossier-meta">
                    <span>
                        <i class="ph-fill ph-buildings" aria-hidden="true"></i>
                        <span class="dossier-meta-text">
                            {{ $tenant->name }}
                        </span>
                    </span>

                    <span class="dossier-meta-secondary">
                        <i class="ph-fill ph-file-text" aria-hidden="true"></i>
                        Dossiê contábil
                    </span>
                </div>
            </div>
        </div>

        <div class="dossier-head-actions">
            <button
                class="acc-button dossier-copy-number"
                type="button"
                data-copy-value="{{ $receiptNumber }}"
                aria-label="Copiar número do faturamento"
            >
                <i class="ph-fill ph-copy" aria-hidden="true"></i>
                <span data-copy-label>Copiar número</span>
            </button>

            <a
                class="acc-button dossier-back"
                href="{{ route('accounting.processes.index', ['tenant' => $tenant->slug]) }}"
            >
                <i class="ph-fill ph-arrow-left" aria-hidden="true"></i>
                <span>Faturamentos</span>
            </a>
        </div>
    </header>

    @if(session('warning'))
        <div class="acc-error dossier-flash" role="alert">
            <i class="ph-fill ph-warning-circle" aria-hidden="true"></i>
            <span>{{ session('warning') }}</span>
        </div>
    @endif

    @if(session('success'))
        <div class="acc-alert dossier-flash" role="status">
            <i class="ph-fill ph-check-circle" aria-hidden="true"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <div
        class="acc-dossier-grid"
        data-dossier
        aria-live="polite"
    ></div>
</main>
@endsection

@push('scripts')
    <script src="{{ asset('assets/accounting-portal.js') }}" defer></script>
@endpush
