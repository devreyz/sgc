<dialog class="acc-dialog acc-scanner acc-qr-scanner-dialog" data-qr-batch-scanner>
    <div class="acc-dialog-head">
        <div><strong>Leitura em lote</strong><span>Aponte para o QR Code. O código é conferido no servidor e vira um lote.</span></div>
        <button class="acc-button" type="button" data-qr-close>Fechar câmera</button>
    </div>
    <video playsinline muted aria-label="Câmera para leitura de QR Code"></video>
    <div class="acc-action-feedback" data-scan-feedback aria-live="polite"></div>
</dialog>
@once
    @push('scripts')<script src="{{ asset('assets/accounting-qr-scanner.js') }}" defer></script>@endpush
@endonce
