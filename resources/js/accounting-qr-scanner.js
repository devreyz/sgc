import './qr-scanner-core';

(() => {
    document.querySelectorAll('[data-qr-batch-scanner]').forEach(scanner => {
        const core = window.SgcQrScanner;
        if (!core) return;
        let session = null;
        let historyArmed = false;
        const video = scanner.querySelector('video');
        const feedback = scanner.querySelector('[data-scan-feedback]');
        const stop = () => {
            session?.stop?.();
            session = null;
            video.srcObject = null;
            if (scanner.open) scanner.close();
            if (historyArmed) { historyArmed = false; history.back(); }
        };
        const dispatch = code => scanner.dispatchEvent(new CustomEvent('accounting:qr-code', {bubbles:true, detail:{code}}));
        const start = async () => {
            if (core.nativePlugin()) {
                feedback.textContent = 'Abrindo a câmera traseira 1x...';
                feedback.classList.remove('is-error');
                try {
                    const result = await core.scanNative({batch:true});
                    (result?.codes || []).forEach(dispatch);
                    if (!scanner.open) scanner.showModal();
                    feedback.textContent = `${(result.codes || []).length} documento(s) enviados para conferência.`;
                } catch (error) {
                    if (error?.code !== 'SCAN_CANCELLED') {
                        feedback.textContent = error?.message || 'Não foi possível abrir o leitor nativo.';
                        feedback.classList.add('is-error');
                        if (!scanner.open) scanner.showModal();
                    }
                }
                return;
            }
            if (!core.supportsWeb()) {
                feedback.textContent = 'A leitura automática não está disponível neste navegador. Informe o código manualmente.';
                feedback.classList.add('is-error');
                if (!scanner.open) scanner.showModal();
                if (!historyArmed) { history.pushState({accountingScanner:true}, ''); historyArmed = true; }
                return;
            }
            try {
                if (!scanner.open) scanner.showModal();
                if (!historyArmed) { history.pushState({accountingScanner:true}, ''); historyArmed = true; }
                feedback.textContent = 'Câmera pronta. Aponte para o QR Code.';
                feedback.classList.remove('is-error');
                session = await core.openWeb({video, onCode:dispatch, interval:180, repeatDelay:2500});
                scanner.scrollIntoView({behavior:'smooth', block:'start'});
            } catch {
                feedback.textContent = 'Não foi possível acessar a câmera. Confira a permissão ou informe o código manualmente.';
                feedback.classList.add('is-error');
                if (!scanner.open) scanner.showModal();
            }
        };

        scanner.addEventListener('click', event => { if (event.target.closest('[data-qr-close]')) stop(); });
        scanner.addEventListener('close', stop);
        document.addEventListener('click', event => { if (event.target.closest('[data-open-qr-scanner]')) start(); });
        window.addEventListener('pagehide', stop);
        window.addEventListener('popstate', () => {
            if (!historyArmed) return;
            historyArmed = false;
            session?.stop?.();
            session = null;
            video.srcObject = null;
            if (scanner.open) scanner.close();
        });
    });
})();

