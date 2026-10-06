(() => {
    const codeFrom = raw => {
        const value = String(raw || '').trim();
        return value.match(/\b(CP-[A-Z0-9-]+)\b/i)?.[1]?.toUpperCase()
            || value.match(/([0-9a-f]{8}-[0-9a-f-]{27,})/i)?.[1]?.toLowerCase()
            || value;
    };

    document.querySelectorAll('[data-qr-batch-scanner]').forEach(scanner => {
        let stream = null;
        let running = false;
        let lastCode = '';
        let lastAt = 0;
        const video = scanner.querySelector('video');
        const feedback = scanner.querySelector('[data-scan-feedback]');
        const nativeScanner = () => window.Capacitor?.isNativePlatform?.()
            && window.Capacitor?.getPlatform?.() === 'android'
            && window.Capacitor?.Plugins?.NativeQrScanner;

        const stop = () => {
            running = false;
            stream?.getTracks().forEach(track => track.stop());
            stream = null;
            video.srcObject = null;
            if (scanner.open) scanner.close();
        };
        const loop = async detector => {
            if (!running) return;
            const results = await detector.detect(video).catch(() => []);
            const code = codeFrom(results[0]?.rawValue);
            if (code && (code !== lastCode || Date.now() - lastAt > 2500)) {
                lastCode = code;
                lastAt = Date.now();
                scanner.dispatchEvent(new CustomEvent('accounting:qr-code', {bubbles:true, detail:{code}}));
            }
            window.setTimeout(() => loop(detector), 180);
        };
        const start = async () => {
            const native = nativeScanner();
            if (native) {
                feedback.textContent = 'Abrindo a câmera traseira 1x...';
                feedback.classList.remove('is-error');
                try {
                    const result = await native.scan({batch:true});
                    for (const raw of (result.codes || [])) {
                        const code = codeFrom(raw);
                        if (code) scanner.dispatchEvent(new CustomEvent('accounting:qr-code', {bubbles:true, detail:{code}}));
                    }
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
            if (!('BarcodeDetector' in window) || !navigator.mediaDevices?.getUserMedia) {
                feedback.textContent = 'A leitura automática não está disponível neste navegador. Informe o código manualmente.';
                feedback.classList.add('is-error');
                if (!scanner.open) scanner.showModal();
                return;
            }
            try {
                if (!scanner.open) scanner.showModal();
                feedback.textContent = 'Câmera pronta. Aponte para o QR Code.';
                feedback.classList.remove('is-error');
                stream = await navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'environment'}}});
                video.srcObject = stream;
                await video.play();
                running = true;
                loop(new BarcodeDetector({formats:['qr_code']}));
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
    });
})();
