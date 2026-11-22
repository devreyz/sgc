<div class="se-paper-controls">
    <span id="se-paper-dimensions"></span>
    <div class="se-paper-options">
    @if(!$closed)
        <select id="se-paper-font" class="se-select" aria-label="Fonte do documento">
            @foreach(\App\Services\OfficialLetterService::FONTS as $key => [$name])
                <option value="{{ $key }}" @selected(($record['layout']['font'] ?? 'sans') === $key)>{{ $name }}</option>
            @endforeach
        </select>
    @endif
    <select id="se-zoom" class="se-select" aria-label="Zoom da folha">
        <option value="fit">Ajustar folha</option>
        <option value=".75">75%</option>
        <option value="1">100%</option>
        <option value="1.25">125%</option>
    </select>
    </div>
</div>
<div class="se-page-wrap se-document-stage">
    <article class="se-document-paper" id="se-paper-preview"
        data-enabled="{{ $isOfficialDocument || ($record['type'] ?? null) === 'official_letter' ? '1' : '0' }}"
        data-orientation="{{ $record['paper_orientation'] ?? 'portrait' }}" data-format="{{ $record['paper_size'] ?? 'a4' }}">
        <div class="se-letter-chrome">
            <header class="se-paper-header" id="se-paper-header"></header>
            <p class="se-paper-number" id="se-paper-number"></p>
            @if($isOfficialDocument && !$closed)
                <div class="se-paper-date se-inline-date">
                    @include('secretary.letter-inline-field', ['field' => 'city', 'hint' => 'Local'])
                    <label class="se-date-picker">
                        <span id="se-paper-date-label">Data do ofício</span>
                        <input type="date" data-inline-letter="date" aria-label="Data do ofício" value="{{ $record['letter']['date'] ?? '' }}">
                    </label>
                </div>
                <div class="se-paper-recipient">
                    @foreach (['recipient' => 'Destinatário', 'recipient_position' => 'Cargo do destinatário', 'institution' => 'Instituição', 'recipient_address' => 'Endereço do destinatário'] as $field => $hint)
                        <div>@include('secretary.letter-inline-field', compact('field', 'hint'))</div>
                    @endforeach
                </div>
                <p class="se-paper-subject">Assunto: @include('secretary.letter-inline-field', ['field' => 'subject', 'hint' => 'Informe o assunto'])</p>
                <p class="se-paper-paragraph">@include('secretary.letter-inline-field', ['field' => 'salutation', 'hint' => 'Vocativo'])</p>
            @else
                <p class="se-paper-date" id="se-paper-date" hidden></p>
                <div class="se-paper-recipient" id="se-paper-recipient"></div>
                <p class="se-paper-subject" id="se-paper-subject" hidden></p>
                <p class="se-paper-paragraph" id="se-paper-salutation" hidden></p>
            @endif
        </div>
        <div id="se-editor" class="se-paper-body" contenteditable="{{ $closed ? 'false' : 'true' }}" spellcheck="true"
            autocapitalize="sentences" role="textbox" aria-label="Corpo do documento" aria-multiline="true"
            aria-placeholder="{{ $isOfficialDocument ? 'Escreva o corpo do ofício…' : 'Escreva o conteúdo do documento…' }}"
            data-placeholder="{{ $isOfficialDocument ? 'Escreva o corpo do ofício…' : 'Escreva o conteúdo do documento…' }}">{!! $record['content'] ?? '<p><br></p>' !!}</div>
        <div class="se-letter-chrome se-paper-end">
            @if($isOfficialDocument && !$closed)
                <p class="se-paper-paragraph">@include('secretary.letter-inline-field', ['field' => 'closing', 'hint' => 'Fecho'])</p>
                <div class="se-paper-signature">
                    <strong>@include('secretary.letter-inline-field', ['field' => 'signatory', 'hint' => 'Nome do signatário'])</strong><br>
                    @include('secretary.letter-inline-field', ['field' => 'signatory_position', 'hint' => 'Cargo do signatário'])
                </div>
            @else
                <p class="se-paper-paragraph" id="se-paper-closing" hidden></p>
                <div class="se-paper-signature" id="se-paper-signature" hidden></div>
            @endif
            <footer class="se-paper-footer" id="se-paper-footer" hidden></footer>
        </div>
    </article>
</div>
