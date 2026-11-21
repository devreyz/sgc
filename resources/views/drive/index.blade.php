@extends('layouts.campo')

@section('title', 'Drive')
@section('page-title', 'Drive')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/drive-portal.css') }}?v={{ filemtime(public_path('assets/drive-portal.css')) }}">
@endpush

@section('content')
    <section class="drive-portal" aria-label="Drive da organização"
        data-drive-portal
        data-files-url="{{ route('drive.files', ['tenant' => $tenant->slug]) }}"
        data-manage="{{ $canManage ? '1' : '0' }}"
        data-connected="{{ $connected ? '1' : '0' }}"
    >
        <header class="drive-heading">
            <div>
                <a class="drive-home" href="{{ route('home') }}"><i class="ph ph-arrow-left" aria-hidden="true"></i> Portais</a>
                <h1>Drive</h1>
                <p>{{ $tenant->name }}@if($account) <span>· {{ $account }}</span>@endif</p>
            </div>
            @if($connected && $canManage)
                <div class="drive-tools">
                    <button class="drive-button" type="button" data-drive-create>
                        <i class="ph ph-folder-plus" aria-hidden="true"></i> Nova pasta
                    </button>
                    <button class="drive-button drive-primary" type="button" data-drive-upload>
                        <i class="ph ph-upload-simple" aria-hidden="true"></i> Enviar arquivos
                    </button>
                    <input type="file" multiple hidden data-drive-file-input>
                </div>
            @endif
        </header>

        @unless($connected)
            <div class="drive-empty">
                <i class="ph-duotone ph-cloud-slash" aria-hidden="true"></i>
                <h2>Conecte o Drive da organização</h2>
                @if($canConnect)
                    <a class="drive-button drive-primary" href="{{ route('settings.google-drive.connect', ['tenant' => $tenant->slug]) }}">Conectar com Google</a>
                @else
                    <p>A conexão pode ser feita pelo administrador em Minha Organização.</p>
                @endif
            </div>
        @else
            <div class="drive-panel">
                <div class="drive-toolbar">
                    <nav aria-label="Localização da pasta" data-drive-path class="drive-path">
                        <span><i class="ph ph-house" aria-hidden="true"></i> Arquivos</span>
                    </nav>
                    <button class="drive-button drive-trash-switch" type="button" data-drive-trash aria-pressed="false" aria-label="Lixeira da pasta" title="Lixeira da pasta">
                        <i class="ph ph-trash" aria-hidden="true"></i><span>Lixeira da pasta</span>
                    </button>
                </div>
                <div class="drive-searchbar">
                    <label class="drive-search">
                        <i class="ph ph-magnifying-glass" aria-hidden="true"></i>
                        <input type="search" data-drive-search aria-label="Buscar nesta pasta" placeholder="Buscar nesta pasta" autocomplete="off">
                    </label>
                    <button class="drive-icon-button" data-drive-refresh type="button" aria-label="Atualizar pasta" title="Atualizar pasta">
                        <i class="ph ph-arrow-clockwise" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="drive-status" role="status" data-drive-status>Carregando arquivos…</div>
                <div data-drive-list aria-busy="true">
                    <table class="drive-table" hidden>
                        <thead>
                            <tr><th scope="col">Nome</th><th scope="col" class="drive-secondary-col">Modificado</th><th scope="col" class="drive-secondary-col">Tamanho</th><th scope="col"><span class="sr-only">Ações</span></th></tr>
                        </thead>
                        <tbody data-drive-rows></tbody>
                    </table>
                    <div class="drive-empty" data-drive-empty hidden>
                        <i class="ph-duotone ph-folder-open" aria-hidden="true"></i>
                        <h2 data-drive-empty-title>Pasta vazia</h2>
                    </div>
                </div>
                <div class="drive-list-footer" data-drive-footer hidden>
                    <button type="button" class="drive-button" data-drive-more>Carregar mais</button>
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
