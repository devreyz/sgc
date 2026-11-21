document.querySelectorAll('[data-expense-files]').forEach(picker => {
    const form = picker.closest('form');
    const previews = picker.querySelector('[data-attachment-previews]');
    const count = picker.querySelector('[data-attachment-count]');
    const limit = Number(picker.dataset.limit);
    const entries = [];
    let processing = 0;

    function render(extra = '') {
        count.textContent = entries.length + ' de ' + limit + ' novos anexos' +
            (processing ? ' · preparando fotos…' : '') + extra;
        previews.replaceChildren();
        for (const entry of entries) {
            const card = document.createElement('span');
            card.className = 'expense-attachment-preview';
            const name = document.createElement('span');
            name.textContent = entry.file.name;
            if (entry.file.type.startsWith('image/')) {
                const image = document.createElement('img');
                image.alt = '';
                image.width = image.height = 48;
                image.style.objectFit = 'cover';
                entry.previewUrl ??= URL.createObjectURL(entry.file);
                image.src = entry.previewUrl;
                card.append(image);
            }
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.textContent = 'Remover';
            remove.setAttribute('aria-label', 'Remover ' + entry.file.name);
            remove.onclick = () => {
                if (entry.previewUrl) URL.revokeObjectURL(entry.previewUrl);
                entries.splice(entries.indexOf(entry), 1);
                render();
            };
            card.append(name, remove);
            previews.append(card);
        }
    }

    async function compress(file) {
        const targetBytes = 230 * 1024;
        if (!file.type.startsWith('image/') || file.size <= targetBytes) return file;
        const url = URL.createObjectURL(file);
        try {
            const image = new Image();
            image.src = url;
            await image.decode();
            const canvas = document.createElement('canvas');
            for (const edge of [1728, 1360, 1060, 800]) {
                const scale = Math.min(1, edge / Math.max(image.naturalWidth, image.naturalHeight));
                canvas.width = Math.max(1, Math.round(image.naturalWidth * scale));
                canvas.height = Math.max(1, Math.round(image.naturalHeight * scale));
                canvas.getContext('2d').drawImage(image, 0, 0, canvas.width, canvas.height);
                for (const quality of [.84, .71, .57]) {
                    const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/webp', quality));
                    if (blob?.type === 'image/webp' && blob.size <= targetBytes) {
                        return new File([blob], file.name.replace(/\.[^.]+$/, '') + '.webp', {type: blob.type});
                    }
                }
            }
            // Keep the original when the server will need to optimize it again.
            return file;
        } catch {
            return file;
        } finally {
            URL.revokeObjectURL(url);
        }
    }

    function addFiles(files) {
        const remaining = limit - entries.length;
        const selected = files.slice(0, remaining).map(file => ({file}));
        entries.push(...selected);
        processing += selected.length;
        render(files.length > remaining ? ' · limite atingido' : '');
        for (const entry of selected) {
            compress(entry.file).then(file => {
                if (file !== entry.file && entry.previewUrl) {
                    URL.revokeObjectURL(entry.previewUrl);
                    entry.previewUrl = null;
                }
                entry.file = file;
            }).finally(() => {
                processing--;
                render();
            });
        }
    }

    picker.querySelectorAll('[data-pick]').forEach(button => {
        button.addEventListener('click', async () => {
            const source = button.dataset.pick;
            if (entries.length >= limit) {
                render(' · limite atingido');
                return;
            }
            const native = window.Capacitor?.Plugins?.NativeCamera;
            if (source !== 'camera' || !window.Capacitor?.isNativePlatform?.() || !native?.takePhoto) {
                picker.querySelector('[data-source="' + source + '"]').click();
                return;
            }
            button.disabled = true;
            try {
                const photo = await native.takePhoto();
                const blob = await fetch(photo.dataUrl).then(response => response.blob());
                addFiles([new File([blob], photo.fileName || 'comprovante.jpg', {type: photo.mimeType || 'image/jpeg'})]);
            } catch (error) {
                if (error?.code !== 'CAMERA_CANCELLED') render(' · câmera indisponível');
            } finally {
                button.disabled = false;
            }
        });
    });
    picker.querySelectorAll('[data-source]').forEach(input => {
        input.addEventListener('change', () => {
            const files = Array.from(input.files || []);
            input.value = '';
            addFiles(files);
        });
    });
    form.addEventListener('submit', event => {
        if (processing) {
            event.preventDefault();
            event.stopImmediatePropagation();
            count.textContent = 'Aguarde a preparação das fotos antes de salvar.';
        }
    }, true);
    form.addEventListener('formdata', event => {
        entries.forEach(entry => event.formData.append('attachments[]', entry.file));
    });
});
