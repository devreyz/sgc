import './qr-scanner-core';

(() => {
    document.querySelectorAll('[data-qr-batch-scanner]').forEach(scanner => {
        const core = window.SgcQrScanner;
        if (!core) return;
        let session = null;
        let historyArmed = false;
        const video = scanner.querySelector('video');
        const feedback = scanner.querySelector('[data-scan-feedback]');
        const status = scanner.querySelector('[data-scan-status]');
        let audioContext = null;
        let statusTimer = null;
        const sound = (final = false) => {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;
            audioContext ||= new AudioContext();
            audioContext.resume().then(() => {
                const play = (frequency, delay, duration) => {
                    const oscillator = audioContext.createOscillator();
                    const gain = audioContext.createGain();
                    oscillator.type = 'sine';
                    oscillator.frequency.value = frequency;
                    gain.gain.setValueAtTime(.0001, audioContext.currentTime + delay);
                    gain.gain.exponentialRampToValueAtTime(.16, audioContext.currentTime + delay + .01);
                    gain.gain.exponentialRampToValueAtTime(.0001, audioContext.currentTime + delay + duration);
                    oscillator.connect(gain).connect(audioContext.destination);
                    oscillator.start(audioContext.currentTime + delay);
                    oscillator.stop(audioContext.currentTime + delay + duration + .02);
                };
                play(880, 0, .11);
                if (final) play(1175, .14, .16);
            }).catch(() => {});
        };
        const showStatus = (text, final = false) => {
            if (!status) return;
            window.clearTimeout(statusTimer);
            status.querySelector('span').textContent = text;
            status.hidden = false;
            status.classList.toggle('is-final', final);
            status.classList.remove('is-visible');
            requestAnimationFrame(() => status.classList.add('is-visible'));
            statusTimer = window.setTimeout(() => {
                status.classList.remove('is-visible');
                if (!final) status.hidden = true;
            }, final ? 4000 : 1800);
        };
        const stop = () => {
            session?.stop?.();
            session = null;
            video.srcObject = null;
            if (scanner.open) scanner.close();
            if (historyArmed) { historyArmed = false; history.back(); }
        };
        const dispatch = (code, detail = {}) => scanner.dispatchEvent(new CustomEvent('accounting:qr-code', {bubbles:true, detail:{code, ...detail}}));
        const start = async () => {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (AudioContext) {
                audioContext ||= new AudioContext();
                audioContext.resume().catch(() => {});
            }
            feedback.classList.remove('is-success');
            if (core.nativePlugin()) {
                feedback.textContent = 'Abrindo a câmera traseira 1x...';
                feedback.classList.remove('is-error');
                try {
                    const config = {};
                    scanner.dispatchEvent(new CustomEvent('accounting:qr-native-config', {
                        bubbles: true,
                        detail: config,
                    }));
                    if (config.error) throw config.error;
                    const result = await core.scanNative({batch:true, ...config});
                    for (const code of result?.codes || []) {
                        await new Promise(resolve => dispatch(code, {onComplete: resolve}));
                    }
                    if (!scanner.open) scanner.showModal();
                    const count = (result.codes || []).length;
                    feedback.textContent = count
                        ? `Leitura concluída: ${count} documento(s) selecionado(s).`
                        : 'Leitura encerrada sem documentos selecionados.';
                    feedback.classList.toggle('is-success', count > 0);
                    if (count) { sound(true); showStatus(`Leitura concluída · ${count} documento(s)`, true); }
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
        document.addEventListener('accounting:qr-success', event => {
            const detail = event.detail || {};
            sound();
            showStatus(`✓ ${detail.number || 'Comprovante'} · ${detail.count || 0} distribuição(ões)`);
        });
    });
})();

