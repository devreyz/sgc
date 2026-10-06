@extends('layouts.bento')

@section('title', 'Verificação rápida de documentos')
@section('page-title', 'Verificação rápida')
@section('page-subtitle', $tenant->name)
@section('user-role', $portal === 'finance' ? 'Financeiro' : 'Contabilidade')

@php
    $bentoNavigation = \App\Support\PortalNavigation::make($portal, 'document-verification', $tenant->slug);
@endphp

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/document-quick-verification.css') }}">
@endpush

@section('content')
<main class="qv-shell" data-quick-verification data-verify-url="{{ route($portal.'.documents.verify.run', ['tenant' => $tenant->slug]) }}">
    <header class="qv-head">
        <div><span>Conferência instantânea</span><h1>Valide um comprovante em segundos</h1><p>Leia o QR Code e receba imediatamente o resumo, os vínculos e qualquer falha de integridade conhecida.</p></div>
        <button class="qv-button qv-primary" type="button" data-start-scan><i data-lucide="scan-line"></i> Escanear documento</button>
    </header>

    <section class="qv-stage" data-stage="ready">
        <div class="qv-ready" data-ready>
            <div class="qv-orbit"><i data-lucide="scan-qr-code"></i></div>
            <h2>Aponte, leia e confira</h2>
            <p>A verificação não altera o documento. Ela cruza a identidade do QR Code com o comprovante, entregas, distribuições, valores congelados e pendências.</p>
            <button class="qv-button qv-primary qv-large" type="button" data-start-scan><i data-lucide="camera"></i> Abrir câmera</button>
            <details class="qv-manual"><summary>Digitar ou colar código</summary><div><input type="text" maxlength="500" data-manual-code placeholder="CP-…, UUID ou link do comprovante"><button class="qv-button" type="button" data-verify-manual>Verificar</button></div></details>
        </div>

        <div class="qv-loading" data-loading hidden>
            <div class="qv-pulse"><i data-lucide="sparkles"></i></div><h2>Conferindo o documento…</h2><p>Validando identidade, versão e vínculos financeiros.</p>
        </div>

        <article class="qv-report" data-report hidden aria-live="polite">
            <header class="qv-verdict" data-verdict><span class="qv-verdict-icon"><i data-verdict-icon data-lucide="shield-check"></i></span><div><small>Resultado da conferência</small><h2 data-headline></h2><p data-message></p></div></header>
            <div class="qv-summary" data-summary></div>
            <section class="qv-section" data-issues-section><div class="qv-section-head"><h3>Pontos encontrados</h3><span data-issue-count></span></div><div class="qv-issues" data-issues></div></section>
            <section class="qv-section" data-distributions-section><div class="qv-section-head"><h3>Distribuições vinculadas</h3><button class="qv-link" type="button" data-toggle-distributions>Mostrar detalhes</button></div><div class="qv-distributions" data-distributions hidden></div></section>
            <footer class="qv-actions"><a class="qv-button" href="#" data-print-current data-sgc-pdf hidden><i data-lucide="printer"></i> Imprimir versão vigente</a><button class="qv-button qv-primary qv-large" type="button" data-start-scan><i data-lucide="scan-line"></i> Escanear outro</button><button class="qv-button" type="button" data-reset>Encerrar conferência</button></footer>
        </article>
    </section>

    <dialog class="qv-camera" data-camera-dialog>
        <header><div><strong>Centralize o QR Code</strong><span>A leitura acontece automaticamente</span></div><button type="button" data-close-camera aria-label="Fechar câmera"><i data-lucide="x"></i></button></header>
        <div class="qv-camera-feed"><video playsinline muted data-camera-video></video><div class="qv-reticle"></div></div>
        <p data-camera-status>Câmera traseira 1x</p>
    </dialog>
</main>
@endsection

@push('scripts')
    <script src="{{ asset('assets/qr-scanner-core.js') }}" defer></script>
    <script src="{{ asset('assets/document-quick-verification.js') }}" defer></script>
@endpush
