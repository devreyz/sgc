<span class="se-inline-field" contenteditable="plaintext-only" role="textbox" aria-label="{{ \App\Services\OfficialLetterService::FIELDS[$field] }}"
    @if($field === 'recipient_address') aria-multiline="true" @endif
    data-hint="{{ $hint }}" data-inline-letter="{{ $field }}">{{ $record['letter'][$field] ?? '' }}</span>
