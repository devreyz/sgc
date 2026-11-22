<div class="se-paper-controls">
    <span id="se-paper-dimensions"></span>
    <select id="se-zoom" class="se-select" aria-label="Zoom da folha">
        <option value="fit">Ajustar folha</option>
        <option value=".75">75%</option>
        <option value="1">100%</option>
        <option value="1.25">125%</option>
    </select>
</div>
<div class="se-page-wrap se-document-stage">
    <article class="se-document-paper" id="se-paper-preview"
        data-enabled="{{ $isOfficialDocument || ($record['type'] ?? null) === 'official_letter' ? '1' : '0' }}"
        data-orientation="{{ $record['paper_orientation'] ?? 'portrait' }}" data-format="{{ $record['paper_size'] ?? 'a4' }}">
        <div class="se-letter-chrome">
            <header class="se-paper-header" id="se-paper-header"></header>
            <p class="se-paper-number" id="se-paper-number"></p>
            <p class="se-paper-date" id="se-paper-date" hidden></p>
            <div class="se-paper-recipient" id="se-paper-recipient"></div>
            <p class="se-paper-subject" id="se-paper-subject" hidden></p>
            <p class="se-paper-paragraph" id="se-paper-salutation" hidden></p>
        </div>
        <div id="se-editor" class="se-paper-body" contenteditable="{{ $closed ? 'false' : 'true' }}" spellcheck="true"
            autocapitalize="sentences" role="textbox" aria-label="Corpo do documento" aria-multiline="true">{!! $record['content'] ?? '<p><br></p>' !!}</div>
        <div class="se-letter-chrome se-paper-end">
            <p class="se-paper-paragraph" id="se-paper-closing" hidden></p>
            <div class="se-paper-signature" id="se-paper-signature" hidden></div>
            <footer class="se-paper-footer" id="se-paper-footer" hidden></footer>
        </div>
    </article>
</div>
