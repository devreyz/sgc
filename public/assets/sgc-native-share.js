(() => {
    "use strict";

    const isNativeAndroid = () => Boolean(
        window.Capacitor?.isNativePlatform?.()
        && window.Capacitor?.getPlatform?.() === "android"
    );

    const fileAsDataUrl = (file) => new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(String(reader.result || ""));
        reader.onerror = () => reject(new Error("Não foi possível ler a imagem gerada."));
        reader.readAsDataURL(file);
    });

    async function shareImage({ file, title = "Imagem do SGC", text = "" }) {
        if (!(file instanceof Blob)) {
            throw new TypeError("Uma imagem válida é obrigatória para compartilhar.");
        }

        const nativeShare = window.Capacitor?.Plugins?.NativeShare;
        if (isNativeAndroid() && nativeShare?.shareImage) {
            const base64 = await fileAsDataUrl(file);
            return nativeShare.shareImage({
                base64,
                fileName: file.name || "sgc-imagem.png",
                title,
                text,
            });
        }

        const shareData = { title, text, files: [file] };
        const supported = typeof navigator.share === "function"
            && (typeof navigator.canShare !== "function" || navigator.canShare(shareData));

        if (!supported) {
            const error = new Error("O compartilhamento de imagem não está disponível.");
            error.code = "IMAGE_SHARE_UNAVAILABLE";
            throw error;
        }

        await navigator.share(shareData);
        return { shared: true };
    }

    window.SgcShare = Object.freeze({ image: shareImage });
})();
