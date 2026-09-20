@php
    $fieldKey = (string) (
        $field['key']
        ?? ''
    );

    $fieldLabel = (string) (
        $field['label']
        ?? $fieldKey
    );

    $fieldType = (string) (
        $field['type']
        ?? 'file'
    );

    $fieldRequired = (bool) (
        $field['required']
        ?? false
    );

    $existing = $execution
        ->evidences
        ->where(
            'field_key',
            $fieldKey
        )
        ->last();

    $previewId =
        'preview-'
        . preg_replace(
            '/[^A-Za-z0-9_-]/',
            '-',
            $fieldKey
        );

    $conditionJson = json_encode(
        $field['conditional_rule']
        ?? null,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
    );

    if ($conditionJson === false) {
        $conditionJson = 'null';
    }

    /*
     * Android:
     * o seletor "Arquivos" fica propositalmente sem accept.
     *
     * Quando o accept informa MIME de imagem, Chrome/WebView pode
     * tratar o input como mídia e oferecer câmera/filmadora.
     * Sem accept, o fluxo tende a ser o seletor de documentos,
     * onde Samsung Meus Arquivos / Files podem ser escolhidos.
     *
     * Os formatos permitidos são validados imediatamente em JS.
     */
    $allowedEvidenceKind = $fieldType === 'image'
        ? 'image'
        : 'image_or_pdf';

    /*
     * A câmera fica em outro input, isolado do seletor Arquivos.
     * Aqui image/* é intencional porque existe capture=environment.
     */
    $cameraAccept = 'image/*';

    $inputId =
        'evidence-input-'
        . preg_replace(
            '/[^A-Za-z0-9_-]/',
            '-',
            $fieldKey
        );

    $cameraInputId =
        'evidence-camera-'
        . preg_replace(
            '/[^A-Za-z0-9_-]/',
            '-',
            $fieldKey
        );

    $selectionId =
        'evidence-selection-'
        . preg_replace(
            '/[^A-Za-z0-9_-]/',
            '-',
            $fieldKey
        );

    $routeTenant = request()->route(
        'tenant'
    );

    $resolvedTenantSlug =
        $tenantSlug
        ?? (
            is_object($routeTenant)
                ? ($routeTenant->slug ?? null)
                : $routeTenant
        );

    $downloadUrl = $existing
        ? route(
            'provider.evidences.download',
            [
                $resolvedTenantSlug,
                $existing,
            ]
        )
        : null;

    $existingIsImage = $existing
        && str_starts_with(
            (string) $existing
                ->document
                ->mime_type,
            'image/'
        );
@endphp

<label
    class="svc-evidence-field"
    data-field-key="{{ $fieldKey }}"
    data-condition="{{ $conditionJson }}"
>
    <span class="svc-evidence-label">
        {{ $fieldLabel }}

        @if($fieldRequired)
            <b class="svc-required">*</b>
        @else
            <small class="svc-optional">
                (opcional)
            </small>
        @endif
    </span>

    <div class="svc-evidence-picker">
        <input
            id="{{ $inputId }}"
            class="svc-evidence-native-input svc-evidence-input"
            type="file"
            name="evidences[{{ $fieldKey }}]"
            data-preview="{{ $previewId }}"
            data-selection="{{ $selectionId }}"
            data-required="{{ $fieldRequired ? '1' : '0' }}"
            data-evidence-saved="{{ $existing ? '1' : '0' }}"
            data-field-label="{{ $fieldLabel }}"
            data-allowed-kind="{{ $allowedEvidenceKind }}"
        >

        <input
            id="{{ $cameraInputId }}"
            class="svc-evidence-camera-input"
            type="file"
            accept="{{ $cameraAccept }}"
            capture="environment"
            data-target-input="{{ $inputId }}"
            tabindex="-1"
            aria-hidden="true"
        >

        <div class="svc-evidence-picker-actions">
            <button
                type="button"
                class="svc-evidence-pick-btn files"
                data-open-file-picker="{{ $inputId }}"
            >
                <i class="ph-fill ph-folder-open"></i>
                Arquivos
            </button>

            <button
                type="button"
                class="svc-evidence-pick-btn camera"
                data-open-camera="{{ $cameraInputId }}"
            >
                <i class="ph-fill ph-camera"></i>
                Câmera
            </button>
        </div>

        <span
            id="{{ $selectionId }}"
            class="svc-evidence-selection"
            aria-live="polite"
        >
            @if($existing)
                <i class="ph-fill ph-check-circle"></i>
                Arquivo já salvo
            @else
                Nenhum novo arquivo selecionado
            @endif
        </span>
    </div>

    <small class="svc-field-help">
        <strong>Arquivos</strong> abre o seletor de documentos do aparelho.
        Depois da escolha, o sistema aceita apenas JPG, JPEG, PNG e WebP
        @if($fieldType !== 'image')
            ou PDF
        @endif
        . Vídeos são recusados.
        A câmera só é acionada por este botão. A foto é usada como evidência
        da execução, otimizada antes do envio e armazenada no repositório
        privado configurado pela organização.
    </small>

    <div
        id="{{ $previewId }}"
        class="svc-file-preview"
    >
        @if($existing)
            @if($existingIsImage)
                <button
                    type="button"
                    class="
                        svc-image-preview-button
                        svc-preview-trigger
                    "
                    data-preview-url="{{ $downloadUrl }}"
                    data-preview-type="image"
                    data-preview-title="{{ $existing->document->name }}"
                    aria-label="Ampliar {{ $fieldLabel }}"
                >
                    <img
                        src="{{ $downloadUrl }}"
                        alt="Prévia de {{ $fieldLabel }}"
                    >

                    <span class="svc-image-preview-hint">
                        <i class="ph-fill ph-arrows-out"></i>
                        Ampliar
                    </span>
                </button>
            @else
                <button
                    type="button"
                    class="
                        svc-table-link
                        svc-preview-trigger
                    "
                    data-preview-url="{{ $downloadUrl }}"
                    data-preview-type="{{ $existing->document->mime_type === 'application/pdf' ? 'pdf' : 'file' }}"
                    data-preview-title="{{ $existing->document->name }}"
                >
                    <i class="ph-fill ph-eye"></i>
                    Visualizar arquivo
                </button>
            @endif
        @endif
    </div>
</label>
