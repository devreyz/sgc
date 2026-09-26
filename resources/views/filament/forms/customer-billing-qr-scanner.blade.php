<div
    x-data="{
        active: false,
        message: '',
        stream: null,
        async start() {
            if (!('BarcodeDetector' in window) || !navigator.mediaDevices?.getUserMedia) {
                this.message = 'Leitura automática indisponível neste navegador. Digite ou cole o código acima.';
                return;
            }
            try {
                this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
                this.$refs.video.srcObject = this.stream;
                await this.$refs.video.play();
                this.active = true;
                this.message = 'Aponte a câmera para o QR Code do comprovante.';
                const detector = new BarcodeDetector({ formats: ['qr_code'] });
                const read = async () => {
                    if (!this.active) return;
                    const codes = await detector.detect(this.$refs.video).catch(() => []);
                    if (codes.length) {
                        const value = codes[0].rawValue || '';
                        const current = $wire.get('data.associate_receipt_codes') || '';
                        const entries = current.split(/[\r\n]+/).map(item => item.trim()).filter(Boolean);
                        if (value && !entries.includes(value)) entries.push(value);
                        $wire.set('data.associate_receipt_codes', entries.join('\n'));
                        this.message = 'QR Code lido. Clique em “Ler comprovantes” para validar as distribuições.';
                        this.stop();
                        return;
                    }
                    requestAnimationFrame(read);
                };
                requestAnimationFrame(read);
            } catch (error) {
                this.message = 'Não foi possível acessar a câmera. Confira a permissão ou informe o código manualmente.';
                this.stop();
            }
        },
        stop() {
            this.active = false;
            this.stream?.getTracks().forEach(track => track.stop());
            this.stream = null;
        }
    }"
    x-on:remove.window="stop()"
    class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-white/5"
>
    <div class="flex flex-wrap items-center gap-3">
        <button type="button" x-show="!active" x-on:click="start()" class="fi-btn fi-btn-size-md rounded-lg bg-primary-600 px-3 py-2 text-sm font-semibold text-white shadow-sm">
            Escanear QR Code
        </button>
        <button type="button" x-show="active" x-on:click="stop()" class="fi-btn fi-btn-size-md rounded-lg bg-gray-600 px-3 py-2 text-sm font-semibold text-white shadow-sm">
            Fechar câmera
        </button>
        <span class="text-sm text-gray-600 dark:text-gray-300" x-text="message">Use o mesmo QR Code verificável do comprovante.</span>
    </div>
    <video x-ref="video" x-show="active" playsinline muted class="mt-3 max-h-72 w-full rounded-lg bg-black object-contain"></video>
</div>
