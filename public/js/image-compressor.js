/**
 * Image Compressor - Converte e comprime imagens para WebP usando Canvas API
 * Uso: <input type="file" data-compress-image accept="image/*">
 */

(function () {
    "use strict";

    // Configurações
    const config = {
        maxWidth: 1920,
        maxHeight: 1920,
        quality: 0.85,
        format: "image/webp",
        maxSizeKB: 500,
    };

    /**
     * Comprime uma imagem e retorna um File WebP
     */
    async function compressImage(file) {
        return new Promise((resolve, reject) => {
            // Verificar se é imagem
            if (!file.type.startsWith("image/")) {
                resolve(file);
                return;
            }

            // Se já for pequeno, não comprimir
            if (file.size / 1024 < 100) {
                resolve(file);
                return;
            }

            const reader = new FileReader();

            reader.onload = function (e) {
                const img = new Image();

                img.onload = function () {
                    // Calcular dimensões mantendo aspect ratio
                    let width = img.width;
                    let height = img.height;

                    if (width > config.maxWidth || height > config.maxHeight) {
                        const ratio = Math.min(
                            config.maxWidth / width,
                            config.maxHeight / height,
                        );
                        width = Math.floor(width * ratio);
                        height = Math.floor(height * ratio);
                    }

                    // Criar canvas
                    const canvas = document.createElement("canvas");
                    canvas.width = width;
                    canvas.height = height;

                    const ctx = canvas.getContext("2d");

                    // Preencher com branco caso tenha transparência
                    ctx.fillStyle = "#FFFFFF";
                    ctx.fillRect(0, 0, width, height);

                    // Desenhar imagem
                    ctx.drawImage(img, 0, 0, width, height);

                    // Converter para WebP
                    canvas.toBlob(
                        function (blob) {
                            if (!blob) {
                                reject(new Error("Falha ao comprimir imagem"));
                                return;
                            }

                            // Criar novo File
                            const compressedFile = new File(
                                [blob],
                                file.name.replace(/\.[^.]+$/, ".webp"),
                                {
                                    type: config.format,
                                    lastModified: Date.now(),
                                },
                            );

                            console.log(
                                `Imagem comprimida: ${Math.round(file.size / 1024)}KB → ${Math.round(compressedFile.size / 1024)}KB`,
                            );

                            // Se ainda muito grande, tentar qualidade menor
                            if (compressedFile.size / 1024 > config.maxSizeKB) {
                                canvas.toBlob(
                                    function (blob2) {
                                        const recompressed = new File(
                                            [blob2],
                                            compressedFile.name,
                                            {
                                                type: config.format,
                                                lastModified: Date.now(),
                                            },
                                        );
                                        resolve(recompressed);
                                    },
                                    config.format,
                                    0.7,
                                );
                            } else {
                                resolve(compressedFile);
                            }
                        },
                        config.format,
                        config.quality,
                    );
                };

                img.onerror = function () {
                    reject(new Error("Falha ao carregar imagem"));
                };

                img.src = e.target.result;
            };

            reader.onerror = function () {
                reject(new Error("Falha ao ler arquivo"));
            };

            reader.readAsDataURL(file);
        });
    }

    /**
     * Inicializar compressor em inputs de arquivo
     */
    function initImageCompressor() {
        document
            .querySelectorAll('input[type="file"][data-compress-image]')
            .forEach((input) => {
                // Evitar múltiplos listeners
                if (input.dataset.compressorInit) return;
                input.dataset.compressorInit = "true";

                input.addEventListener("change", async function (e) {
                    if (!this.files || !this.files[0]) return;

                    const file = this.files[0];

                    // Mostrar loading se tiver preview
                    const previewContainer = this.dataset.previewContainer;
                    if (previewContainer) {
                        const container =
                            document.querySelector(previewContainer);
                        if (container) {
                            container.innerHTML =
                                '<div class="text-xs text-muted">Comprimindo imagem...</div>';
                        }
                    }

                    try {
                        const compressed = await compressImage(file);

                        // Substituir arquivo no input
                        const dataTransfer = new DataTransfer();
                        dataTransfer.items.add(compressed);
                        this.files = dataTransfer.files;

                        // Mostrar preview se configurado
                        if (previewContainer) {
                            const container =
                                document.querySelector(previewContainer);
                            if (container) {
                                const reader = new FileReader();
                                reader.onload = function (e) {
                                    container.innerHTML = `
                                    <img src="${e.target.result}" style="max-width: 200px; max-height: 200px; border-radius: 8px; margin-top: 8px;">
                                    <div class="text-xs text-muted" style="margin-top: 4px;">
                                        ${compressed.name} (${Math.round(compressed.size / 1024)}KB)
                                    </div>
                                `;
                                };
                                reader.readAsDataURL(compressed);
                            }
                        }

                        // Disparar evento customizado
                        this.dispatchEvent(
                            new CustomEvent("imageCompressed", {
                                detail: {
                                    original: file,
                                    compressed: compressed,
                                },
                            }),
                        );
                    } catch (error) {
                        console.error("Erro ao comprimir imagem:", error);
                        alert("Erro ao processar imagem. Tente outro arquivo.");
                    }
                });
            });
    }

    // Auto-inicializar quando DOM carregar
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initImageCompressor);
    } else {
        initImageCompressor();
    }

    // Reinicializar quando conteúdo dinâmico for adicionado
    if (typeof MutationObserver !== "undefined") {
        const observer = new MutationObserver(function (mutations) {
            let shouldInit = false;
            mutations.forEach(function (mutation) {
                mutation.addedNodes.forEach(function (node) {
                    if (
                        node.nodeType === 1 &&
                        (node.matches(
                            'input[type="file"][data-compress-image]',
                        ) ||
                            node.querySelector(
                                'input[type="file"][data-compress-image]',
                            ))
                    ) {
                        shouldInit = true;
                    }
                });
            });
            if (shouldInit) {
                initImageCompressor();
            }
        });
        observer.observe(document.body, { childList: true, subtree: true });
    }

    // Expor função globalmente
    window.compressImage = compressImage;
    window.initImageCompressor = initImageCompressor;
})();
