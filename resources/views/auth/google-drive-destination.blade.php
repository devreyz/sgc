@extends('layouts.security-public')

@section('title', 'Google Drive')
@section('referrer-policy', 'strict-origin-when-cross-origin')

@section('heading')
    <h1>{{ $existingRoot || $folderToReauthorize ? 'Confirmar reconexão' : 'Onde guardar os documentos?' }}</h1>
    <p>{{ $tenant->name }}</p>
@endsection

@section('content')
    <p>Conta Google que receberá os arquivos <strong>{{ $accountEmail }}</strong></p>
    <p>Você pode usar uma conta Google diferente da que usa para entrar no SGC.</p>
    @if($existingRoot)
        <p>Os novos documentos vão para a pasta da organização que já pertence a esta conta.</p>
    @elseif($folderToReauthorize)
        <p>Autorize novamente o acesso à pasta já vinculada à organização para continuar usando o mesmo destino.</p>
        <p><a target="_blank" rel="noopener noreferrer" href="https://drive.google.com/drive/folders/{{ rawurlencode($folderToReauthorize) }}">Abrir pasta atual no Drive</a></p>
        @unless($picker)
            <p>Confira se a pasta está disponível no Meu Drive antes de reconectar.</p>
        @endunless
    @else
        <p>O SGC vai organizar os documentos em uma pasta própria no seu Drive.</p>
    @endif

    <form method="post" action="{{ route('settings.google-drive.destination.save', ['tenant' => $tenant->slug]) }}">
        @csrf
        @if($picker)
            <input type="hidden" name="folder_id" id="drive-folder-id">
            <button type="button" class="btn secondary" id="drive-picker-open">{{ $folderToReauthorize ? 'Autorizar pasta atual' : 'Escolher pasta do Meu Drive' }}</button>
            <p id="drive-picker-selection" role="status" hidden></p>
            <button type="submit" name="destination" value="selected" class="btn" id="drive-picker-confirm" hidden>Usar esta pasta</button>
        @endif

        @if($errors->any())
            <p class="status show error" role="alert">{{ $errors->first() }}</p>
        @endif

        @unless($folderToReauthorize)
            <button type="submit" name="destination" value="automatic" @class(['btn', 'secondary' => $picker])>
                {{ $existingRoot ? 'Reconectar na pasta atual' : 'Criar pasta automaticamente' }}
            </button>
        @endunless
    </form>

    <a class="btn outline" href="{{ route('filament.admin.pages.organization-settings-page', ['tab' => 'organization-settings-google-drive-tab']) }}">Voltar às configurações</a>
@endsection

@if($picker)
    @push('scripts')
        <script>
            (() => {
                const config = {{ \Illuminate\Support\Js::from($picker) }};
                const open = document.getElementById('drive-picker-open');
                const field = document.getElementById('drive-folder-id');
                const selection = document.getElementById('drive-picker-selection');
                const confirm = document.getElementById('drive-picker-confirm');
                let loading;

                function loadPicker() {
                    return new Promise((resolve, reject) => {
                        const script = document.createElement('script');
                        script.src = 'https://apis.google.com/js/api.js';
                        script.onload = () => gapi.load('picker', {
                            callback: resolve,
                            onerror: reject,
                            ontimeout: reject,
                            timeout: 10000,
                        });
                        script.onerror = reject;
                        document.head.append(script);
                    });
                }

                open.addEventListener('click', async () => {
                    open.disabled = true;
                    try {
                        loading ??= loadPicker();
                        await loading;
                        const picker = google.picker;
                        const folders = new picker.DocsView(picker.ViewId.FOLDERS)
                            .setIncludeFolders(true)
                            .setSelectFolderEnabled(true)
                            .setOwnedByMe(true)
                            .setMimeTypes('application/vnd.google-apps.folder');

                        new picker.PickerBuilder()
                            .addView(folders)
                            .setTitle('Escolher pasta do Meu Drive')
                            .setLocale('pt-BR')
                            .setAppId(config.appId)
                            .setDeveloperKey(config.apiKey)
                            .setOAuthToken(config.accessToken)
                            .setOrigin(window.location.origin)
                            .setCallback(result => {
                                if (result.action !== picker.Action.PICKED) return;
                                const folder = result.docs?.[0];
                                if (!folder?.id) return;
                                field.value = folder.id;
                                selection.textContent = 'Pasta escolhida: ' + folder.name;
                                selection.hidden = false;
                                confirm.hidden = false;
                            })
                            .build()
                            .setVisible(true);
                    } catch {
                        loading = null;
                        selection.textContent = 'O seletor do Google está indisponível. Tente novamente.';
                        selection.hidden = false;
                    } finally {
                        open.disabled = false;
                    }
                });
            })();
        </script>
    @endpush
@endif
