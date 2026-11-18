// HTTP headers may expose UTF-8 bytes as Latin-1 in fetch/WebView.
export function documentText(value) {
    let text = String(value || '');
    try { text = decodeURIComponent(text); } catch (_) { /* Literal percent sign. */ }
    for (let attempt = 0; attempt < 2 && /[ÃÂâÅ]/.test(text); attempt++) {
        try {
            const windows1252 = '€\u0081‚ƒ„…†‡ˆ‰Š‹Œ\u008dŽ\u008f\u0090‘’“”•–—˜™š›œ\u009džŸ';
            const bytes = [...text].map(character => {
                const code = character.charCodeAt(0);
                return code <= 255 ? code : windows1252.includes(character) ? 128 + windows1252.indexOf(character) : -1;
            });
            if (bytes.includes(-1)) break;
            const decoded = new TextDecoder('utf-8', { fatal: true }).decode(
                Uint8Array.from(bytes),
            );
            if (decoded === text) break;
            text = decoded;
        } catch (_) { break; }
    }
    return text;
}

export function documentFilename(disposition) {
    const extended = disposition.match(/filename\*\s*=\s*UTF-8''([^;]+)/i);
    const basic = disposition.match(/filename\s*=\s*(?:"([^"]+)"|([^;]+))/i);
    return documentText((extended?.[1] || basic?.[1] || basic?.[2] || 'documento-sgc.pdf').trim());
}
