(() => {
    if (window.SgcQrScanner) return;

    const normalize = raw => {
        const value = String(raw || '').trim();
        return value.match(/\b(CP-[A-Z0-9-]+)\b/i)?.[1]?.toUpperCase()
            || value.match(/([0-9a-f]{8}-[0-9a-f-]{27,})/i)?.[1]?.toLowerCase()
            || value;
    };
    const nativePlugin = () => window.Capacitor?.isNativePlatform?.()
        && window.Capacitor?.getPlatform?.() === 'android'
        ? window.Capacitor?.Plugins?.NativeQrScanner
        : null;
    const supportsWeb = () => 'BarcodeDetector' in window && Boolean(navigator.mediaDevices?.getUserMedia);

    const scanNative = async options => {
        const plugin = nativePlugin();
        if (!plugin) return null;
        const result = await plugin.scan({
            batch: Boolean(options?.batch),
            verificationUrl: options?.verificationUrl || '',
            csrfToken: options?.csrfToken || '',
        });
        const codes = (result?.codes || []).map(normalize).filter(Boolean);
        return {code: normalize(result?.code), codes, native: true};
    };

    const openWeb = async options => {
        if (!supportsWeb()) throw Object.assign(new Error('A leitura automática não está disponível neste navegador.'), {code:'WEB_SCANNER_UNAVAILABLE'});
        const video = options.video;
        const previousHtmlOverflow = document.documentElement.style.overflow;
        const previousBodyOverflow = document.body.style.overflow;
        document.documentElement.style.overflow = 'hidden';
        document.body.style.overflow = 'hidden';
        const stream = await navigator.mediaDevices.getUserMedia({
            video: {
                facingMode: {ideal:'environment'},
                width: {ideal: options.width || 1920},
                height: {ideal: options.height || 1080},
            },
            audio: false,
        });
        video.srcObject = stream;
        await video.play();
        const detector = new BarcodeDetector({formats:['qr_code']});
        let active = true;
        let lastCode = '';
        let lastAt = 0;
        const stop = () => {
            active = false;
            stream.getTracks().forEach(track => track.stop());
            if (video.srcObject === stream) video.srcObject = null;
            document.documentElement.style.overflow = previousHtmlOverflow;
            document.body.style.overflow = previousBodyOverflow;
        };
        const loop = async () => {
            if (!active) return;
            const found = await detector.detect(video).catch(() => []);
            const code = normalize(found[0]?.rawValue);
            if (code && (code !== lastCode || Date.now() - lastAt > (options.repeatDelay || 2500))) {
                lastCode = code; lastAt = Date.now();
                await options.onCode?.(code, stop);
                if (options.single) return stop();
            }
            if (active) window.setTimeout(loop, options.interval || 160);
        };
        loop();
        return {stream, stop, native:false};
    };

    window.SgcQrScanner = {normalize, nativePlugin, supportsWeb, scanNative, openWeb};
})();
