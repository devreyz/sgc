@extends('layouts.campo')

@section('title', 'Drive')
@section('page-title', 'Drive')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/drive-portal.css') }}?v={{ filemtime(public_path('assets/drive-portal.css')) }}">
@endpush

@section('content')
<section class="drive-portal" aria-label="Arquivos da organização"
    data-drive-portal
    data-files-url="{{ route('drive.files', ['tenant' => $tenant->slug]) }}"
    data-manage="{{ $canManage ? '1' : '0' }}"
    data-connected="{{ $connected ? '1' : '0' }}"
>
    <header class="drive-heading">
        <div class="drive-heading-main">
            <a class="drive-home" href="{{ route('home') }}" aria-label="Voltar aos portais">
                <i class="ph ph-arrow-left" aria-hidden="true"></i>
                <span>Portais</span>
            </a>
            <div class="drive-brand">
                <span class="drive-brand-symbol" aria-hidden="true"><i class="ph-duotone ph-hard-drives"></i></span>
                <div class="drive-brand-copy">
                    <span class="drive-eyebrow">Arquivos compartilhados</span>
                    <h1>Drive</h1>
                    <p>{{ $tenant->name }}@if($account) <span class="drive-account">· {{ $account }}</span>@endif</p>
                </div>
            </div>
        </div>
        @if($connected && $canManage)
            <div class="drive-tools" aria-label="Criar e enviar arquivos">
                <button class="drive-button drive-create-button" type="button" data-drive-create>
                    <i class="ph ph-folder-plus" aria-hidden="true"></i><span>Nova pasta</span>
                </button>
                <button class="drive-button drive-primary" type="button" data-drive-upload>
                    <i class="ph ph-upload-simple" aria-hidden="true"></i><span>Enviar arquivos</span>
                </button>
                <input type="file" multiple hidden data-drive-file-input>
            </div>
        @endif
    </header>

    @unless($connected)
        <div class="drive-unconnected">
            <span class="drive-unconnected-icon" aria-hidden="true"><i class="ph-duotone ph-cloud-slash"></i></span>
            <h2>Conecte o Drive da organização</h2>
            <p>Os arquivos compartilhados aparecerão aqui assim que a conexão for ativada.</p>
            @if($canConnect)
                <a class="drive-button drive-primary" href="{{ route('settings.google-drive.connect', ['tenant' => $tenant->slug]) }}">
                    <i class="ph ph-google-logo" aria-hidden="true"></i> Conectar com Google
                </a>
            @else
                <p>Peça ao administrador que conecte o Google Drive em Minha Organização.</p>
            @endif
        </div>
    @else
        <div class="drive-app">
            <aside class="drive-sidebar" aria-label="Navegação do Drive">
                <div class="drive-sidebar-label">BIBLIOTECA</div>
                <nav class="drive-sidebar-nav" aria-label="Pastas e lixeira">
                    <button class="drive-side-link is-active" type="button" data-drive-root aria-current="page" title="Ir para a pasta principal">
                        <span class="drive-side-icon"><i class="ph ph-folder-open" aria-hidden="true"></i></span>
                        <span class="drive-side-copy"><strong>Arquivos</strong><small>Pasta principal</small></span>
                    </button>
                    <button class="drive-side-link" type="button" data-drive-side-trash aria-pressed="false" title="Abrir lixeira da pasta atual">
                        <span class="drive-side-icon"><i class="ph ph-trash" aria-hidden="true"></i></span>
                        <span class="drive-side-copy"><strong>Lixeira</strong><small>Da pasta atual</small></span>
                    </button>
                </nav>
                <div class="drive-side-divider"></div>
                <div class="drive-sidebar-label">LOCALIZAÇÃO</div>
                <div class="drive-side-location" data-drive-side-location title="Pasta atual">
                    <i class="ph ph-folder-simple" aria-hidden="true"></i>
                    <span data-drive-side-folder>Arquivos</span>
                </div>
                @if($canManage)
                    <div class="drive-side-hint"><i class="ph ph-info" aria-hidden="true"></i><span>Você também pode arrastar arquivos para esta janela para enviá-los.</span></div>
                @endif
            </aside>

            <div class="drive-panel" data-drive-view="list">
                <div class="drive-toolbar">
                    <nav class="drive-path" aria-label="Localização da pasta" data-drive-path>
                        <span><i class="ph ph-house" aria-hidden="true"></i> Arquivos</span>
                    </nav>
                    <button class="drive-button drive-trash-switch" type="button" data-drive-trash aria-pressed="false" aria-label="Lixeira da pasta" title="Lixeira da pasta">
                        <i class="ph ph-trash" aria-hidden="true"></i><span>Lixeira da pasta</span>
                    </button>
                </div>

                <div class="drive-searchbar">
                    <div class="drive-search" role="search">
                        <i class="ph ph-magnifying-glass" aria-hidden="true"></i>
                        <input type="search" data-drive-search aria-label="Buscar nesta pasta" placeholder="Buscar arquivos e pastas" autocomplete="off" enterkeyhint="search">
                        <button class="drive-search-clear" type="button" data-drive-clear-search aria-label="Limpar busca" title="Limpar busca" hidden>
                            <i class="ph ph-x" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="drive-display-options" role="group" aria-label="Visualização dos arquivos">
                        <button type="button" class="drive-icon-button is-selected" data-drive-view-button="list" aria-label="Visualizar em lista" title="Lista" aria-pressed="true"><i class="ph ph-list-bullets" aria-hidden="true"></i></button>
                        <button type="button" class="drive-icon-button" data-drive-view-button="grid" aria-label="Visualizar em grade" title="Grade" aria-pressed="false"><i class="ph ph-squares-four" aria-hidden="true"></i></button>
                    </div>
                    <button class="drive-icon-button drive-refresh" type="button" data-drive-refresh aria-label="Atualizar arquivos" title="Atualizar"><i class="ph ph-arrows-clockwise" aria-hidden="true"></i></button>
                </div>

                <div class="drive-list-heading">
                    <div class="drive-list-heading-title">
                        <h2 data-drive-current-title>Arquivos</h2>
                        <span data-drive-count>Carregando…</span>
                    </div>
                    <span class="drive-list-hint">Pastas primeiro</span>
                </div>
                <div class="drive-transfer" data-drive-transfer hidden role="status" aria-live="polite">
                    <div class="drive-transfer-info"><span data-drive-transfer-title>Enviando arquivos</span><strong data-drive-transfer-count></strong></div>
                    <div class="drive-transfer-track"><span data-drive-transfer-bar></span></div>
                </div>
                <div class="drive-status" role="status" aria-live="polite" data-drive-status>Carregando arquivos…</div>

                <div class="drive-drop-target" data-drive-drop-target>
                    <div class="drive-drop-overlay" data-drive-drop-overlay hidden aria-hidden="true">
                        <i class="ph ph-upload-simple" aria-hidden="true"></i><strong>Solte os arquivos para enviar</strong>
                        <span>Os arquivos serão adicionados à pasta atual</span>
                    </div>
                    <div data-drive-list aria-busy="true">
                        <div class="drive-loading" data-drive-loading aria-hidden="true"><span></span><span></span><span></span><span></span></div>
                        <table class="drive-table" hidden>
                            <thead>
                                <tr><th scope="col">Nome</th><th scope="col" class="drive-secondary-col">Modificado</th><th scope="col" class="drive-secondary-col">Tamanho</th><th scope="col"><span class="sr-only">Ações</span></th></tr>
                            </thead>
                            <tbody data-drive-rows></tbody>
                        </table>
                        <div class="drive-empty" data-drive-empty hidden>
                            <span class="drive-empty-symbol" aria-hidden="true"><i class="ph-duotone ph-folder-open"></i></span>
                            <h2 data-drive-empty-title>Pasta vazia</h2>
                            <p data-drive-empty-description>Nenhum arquivo encontrado nesta pasta.</p>
                        </div>
                    </div>
                </div>
                <div class="drive-list-footer" data-drive-footer hidden>
                    <button type="button" class="drive-button" data-drive-more><i class="ph ph-arrow-down" aria-hidden="true"></i> Carregar mais arquivos</button>
                </div>
            </div>
        </div>

        <dialog class="drive-dialog" data-drive-dialog aria-labelledby="drive-dialog-title">
            <header><h2 id="drive-dialog-title"></h2><button class="drive-icon-button" type="button" data-drive-close aria-label="Fechar"><i class="ph ph-x" aria-hidden="true"></i></button></header>
            <div class="drive-dialog-body" data-drive-dialog-body></div>
            <p class="drive-dialog-feedback" role="status" data-drive-dialog-feedback hidden></p>
        </dialog>
    @endunless
</section>
@endsection

@if($connected)
    @push('scripts')
        <script defer src="{{ asset('assets/drive-portal.js') }}?v={{ filemtime(public_path('assets/drive-portal.js')) }}"></script>
    @endpush
@endif
