@extends('layouts.bento')

@section('title', 'Processo '.$receiptNumber)
@section('page-title', 'Dossiê Contábil')
@section('page-subtitle', $tenant->name)
@section('user-role', 'Contabilidade')

@php
    $bentoNavigation = \App\Support\PortalNavigation::make('accounting', 'processes', $tenant->slug);
@endphp

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/accounting-portal.css') }}">
@endpush

@section('content')
<main
    class="acc-shell"
    data-accounting-page="dossier"
    data-process-data-url="{{ route('accounting.data.processes.show', ['tenant' => $tenant->slug, 'receipt' => $receiptId]) }}"
    data-authorization-send-url="{{ route('accounting.data.processes.authorization.send', ['tenant' => $tenant->slug, 'receipt' => $receiptId]) }}"
    data-can-send-authorization="{{ auth()->user()?->can('send_accounting_authorizations') ? '1' : '0' }}"
>
    <header class="acc-topbar">
        <div class="acc-heading">
            <p class="acc-eyebrow">Processo financeiro</p>
            <h1>{{ $receiptNumber }}</h1>
            <p>Origem, valores, documentos relacionados e histórico em um único dossiê.</p>
        </div>
        <a class="acc-button" href="{{ route('accounting.processes.index', ['tenant' => $tenant->slug]) }}">
            <i data-lucide="arrow-left" aria-hidden="true"></i>
            Processos
        </a>
    </header>
    <div class="acc-dossier-grid" data-dossier aria-live="polite"></div>
</main>
@endsection

@push('scripts')
    <script src="{{ asset('assets/accounting-portal.js') }}" defer></script>
@endpush
