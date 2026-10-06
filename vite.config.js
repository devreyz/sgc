import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        laravel({
            input: [
                "resources/css/app.css",
                "resources/css/accounting-portal.css",
                "resources/css/document-quick-verification.css",
                "resources/js/app.js",
                "resources/js/accounting-portal.js",
                "resources/js/accounting-billing-editor.js",
                "resources/js/accounting-fiscal.js",
                "resources/js/accounting-qr-scanner.js",
                "resources/js/document-quick-verification.js",
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            usePolling: false, // Tenta reduzir watchers
            interval: 1000, // Ajusta a frequência de verificação
            ignored: ["**/storage/framework/views/**"],
        },

        fs: {
            strict: true, // Restringe o monitoramento a arquivos importantes
            // Permite servir arquivos também de node_modules durante o dev (evita 403 ao importar pacotes)
            allow: [process.cwd(), "resources", "public"], // inclui a raiz do projeto
        },
        host: "127.0.0.1",
        port: 5173,
    },
    build: {
        sourcemap: false, // Desabilita mapas de fonte no ambiente de produção
        rollupOptions: {
            // Evita chaves D:/... no manifest gerado pelo Vite no Windows.
            input: {
                "resources/css/app.css": "resources/css/app.css",
                "resources/css/accounting-portal.css": "resources/css/accounting-portal.css",
                "resources/css/document-quick-verification.css": "resources/css/document-quick-verification.css",
                "resources/js/app.js": "resources/js/app.js",
                "resources/js/accounting-portal.js": "resources/js/accounting-portal.js",
                "resources/js/accounting-billing-editor.js": "resources/js/accounting-billing-editor.js",
                "resources/js/accounting-fiscal.js": "resources/js/accounting-fiscal.js",
                "resources/js/accounting-qr-scanner.js": "resources/js/accounting-qr-scanner.js",
                "resources/js/document-quick-verification.js": "resources/js/document-quick-verification.js",
            },
            output: {
                manualChunks: (id) => {
                    if (id.includes("node_modules/pdfjs-dist")) {
                        return "pdf-viewer";
                    }
                    if (id.includes("node_modules")) {
                        return "vendor"; // Agrupa pacotes em "vendor.js"
                    }
                },
            },
        },
    },
});
