<div class="expense-files" data-expense-files data-limit="{{ $attachmentLimit ?? 5 }}">
    <div class="expense-attachment-actions detail-actions">
        <button class="expense-select-trigger detail-button" type="button" data-pick="camera"><i class="ph ph-camera" aria-hidden="true"></i> Tirar foto</button>
        <button class="expense-select-trigger detail-button" type="button" data-pick="files"><i class="ph ph-images" aria-hidden="true"></i> Escolher arquivos</button>
    </div>
    <input data-source="camera" type="file" accept="image/*" capture="environment" hidden>
    <input data-source="files" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" multiple hidden>
    <small class="expense-attachment-help detail-meta" data-attachment-count role="status">0 de {{ $attachmentLimit ?? 5 }} novos anexos</small>
    <div class="expense-attachment-previews detail-actions" data-attachment-previews></div>
</div>

@once
    @push('scripts')
        <script defer src="{{ asset('assets/service-expense-attachments.js') }}?v={{ filemtime(public_path('assets/service-expense-attachments.js')) }}"></script>
    @endpush
@endonce
