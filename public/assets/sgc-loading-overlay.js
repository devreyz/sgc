(() => {
    "use strict";

    /*
    |--------------------------------------------------------------------------
    | SGC GLOBAL REQUEST LOADER
    |--------------------------------------------------------------------------
    |
    | O loader representa somente requisições assíncronas iniciadas pelo
    | usuário. Ele NÃO é um page-loader e NÃO interfere em navegação normal.
    |
    | Regras:
    | - carregamento inicial da página: nunca mostra;
    | - links/navegação GET normal: nunca mostra;
    | - submit HTML tradicional: nunca é interceptado;
    | - fetch/XHR POST, PUT, PATCH e DELETE: mostra quando originados de uma
    |   interação recente do usuário;
    | - fetch/XHR GET: somente com opt-in explícito para busca/filtro/refresh;
    | - requisições rápidas não piscam o overlay.
    |
    */

    const CONFIG = Object.freeze({
        showDelay: 180,
        minVisibleTime: 220,
        userIntentWindow: 2200,
    });

    const MUTATION_METHODS = new Set(["POST", "PUT", "PATCH", "DELETE"]);

    const GET_LOADER_TYPES = new Set(["filter", "search", "refresh", "data"]);

    let pageReady = document.readyState === "complete";

    let pendingRequests = 0;

    let showTimer = null;
    let hideTimer = null;

    let visibleSince = 0;

    let lastUserIntentAt = 0;
    let lastUserIntentType = "";
    let lastUserIntentLabel = "";

    /*
    |--------------------------------------------------------------------------
    | Elementos
    |--------------------------------------------------------------------------
    */

    const getLoader = () => document.getElementById("global-request-loader");

    const getLoaderLabel = () =>
        document.getElementById("global-request-loader-label");

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    const normalizeMethod = (method) => String(method || "GET").toUpperCase();

    const normalizeLabel = (value, fallback = "Processando...") => {
        const label = String(value || "")
            .replace(/\s+/g, " ")
            .trim()
            .slice(0, 60);

        return label || fallback;
    };

    const normalizeLoaderType = (value) =>
        String(value || "")
            .trim()
            .toLowerCase();

    /*
    |--------------------------------------------------------------------------
    | Intenção do usuário
    |--------------------------------------------------------------------------
    |
    | Estes eventos NÃO exibem o loader.
    |
    | Eles apenas registram que houve interação real.
    |
    */

    function registerUserIntent(event) {
        if (!event.isTrusted) {
            return;
        }

        lastUserIntentAt = performance.now();

        lastUserIntentType = "";
        lastUserIntentLabel = "";

        const target = event.target instanceof Element ? event.target : null;

        const owner = target?.closest?.(
            ["[data-request-loader]", "[data-loading-label]"].join(", "),
        );

        if (!owner) {
            return;
        }

        lastUserIntentType = normalizeLoaderType(owner.dataset.requestLoader);

        lastUserIntentLabel = normalizeLabel(
            owner.dataset.loadingLabel ||
                owner.dataset.requestLoaderLabel ||
                "",
            "",
        );
    }

    ["pointerdown", "keydown", "submit", "change"].forEach((eventName) => {
        document.addEventListener(eventName, registerUserIntent, true);
    });

    function hasRecentUserIntent() {
        return (
            lastUserIntentAt > 0 &&
            performance.now() - lastUserIntentAt <= CONFIG.userIntentWindow
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Controle visual
    |--------------------------------------------------------------------------
    */

    function setLoaderVisible(visible, label = "Processando...") {
        const loader = getLoader();

        if (!loader) {
            return;
        }

        if (visible) {
            const labelElement = getLoaderLabel();

            if (labelElement) {
                labelElement.textContent = normalizeLabel(label);
            }

            loader.classList.add("active");

            loader.setAttribute("aria-hidden", "false");

            document.documentElement.classList.add("sgc-loading-lock");

            document.body.classList.add("sgc-loading-lock");

            visibleSince = performance.now();

            return;
        }

        loader.classList.remove("active");

        loader.setAttribute("aria-hidden", "true");

        document.documentElement.classList.remove("sgc-loading-lock");

        document.body.classList.remove("sgc-loading-lock");

        visibleSince = 0;
    }

    /*
    |--------------------------------------------------------------------------
    | Iniciar loading
    |--------------------------------------------------------------------------
    */

    function beginLoading(label = "Processando...") {
        pendingRequests += 1;

        /*
         * Já existe uma request controlando
         * o overlay.
         */
        if (pendingRequests !== 1) {
            return;
        }

        clearTimeout(showTimer);
        clearTimeout(hideTimer);

        /*
         * Evita aquele flash agressivo
         * em requisições muito rápidas.
         */
        showTimer = window.setTimeout(() => {
            showTimer = null;

            if (pendingRequests > 0) {
                setLoaderVisible(true, label);
            }
        }, CONFIG.showDelay);
    }

    /*
    |--------------------------------------------------------------------------
    | Finalizar loading
    |--------------------------------------------------------------------------
    */

    function endLoading() {
        pendingRequests = Math.max(0, pendingRequests - 1);

        /*
         * Ainda temos requests em andamento.
         */
        if (pendingRequests > 0) {
            return;
        }

        /*
         * Se ainda nem apareceu,
         * cancela o timer.
         */
        clearTimeout(showTimer);

        showTimer = null;

        const loader = getLoader();

        if (!loader?.classList.contains("active")) {
            setLoaderVisible(false);

            return;
        }

        /*
         * Se chegou a aparecer,
         * deixa um tempo mínimo para
         * não criar efeito de piscada.
         */
        const elapsed = performance.now() - visibleSince;

        const remaining = Math.max(0, CONFIG.minVisibleTime - elapsed);

        clearTimeout(hideTimer);

        hideTimer = window.setTimeout(() => {
            hideTimer = null;

            setLoaderVisible(false);
        }, remaining);
    }

    /*
    |--------------------------------------------------------------------------
    | Reset
    |--------------------------------------------------------------------------
    */

    function resetLoading() {
        pendingRequests = 0;

        clearTimeout(showTimer);
        clearTimeout(hideTimer);

        showTimer = null;
        hideTimer = null;

        setLoaderVisible(false);
    }

    /*
    |--------------------------------------------------------------------------
    | API pública
    |--------------------------------------------------------------------------
    |
    | Continua disponível para operações especiais:
    |
    | showGlobalLoading('Gerando relatório...');
    |
    | hideGlobalLoading();
    |
    | Não use para links/navegação normal.
    |
    */

    window.showGlobalLoading = (label = "Processando...") => {
        beginLoading(label);
    };

    window.hideGlobalLoading = () => {
        endLoading();
    };

    /*
    |--------------------------------------------------------------------------
    | Política
    |--------------------------------------------------------------------------
    */

    function shouldUseLoader(method, options = {}) {
        /*
         * Nunca mostrar enquanto
         * a página está iniciando.
         */
        if (!pageReady) {
            return false;
        }

        /*
         * Opt-out explícito.
         */
        if (options.globalLoader === false || options.requestLoader === false) {
            return false;
        }

        /*
         * Opt-in explícito.
         */
        if (options.globalLoader === true || options.requestLoader === true) {
            return true;
        }

        /*
         * GET:
         *
         * nunca automaticamente.
         */
        if (method === "GET") {
            const explicitType = normalizeLoaderType(
                options.loaderType || options.requestLoaderType,
            );

            /*
             * fetch(url, {
             *     loaderType: 'filter'
             * })
             */
            if (GET_LOADER_TYPES.has(explicitType)) {
                return true;
            }

            /*
             * Também permite:
             *
             * <button
             *     data-request-loader="filter"
             * >
             */
            return (
                hasRecentUserIntent() &&
                GET_LOADER_TYPES.has(lastUserIntentType)
            );
        }

        /*
         * POST / PUT / PATCH / DELETE
         *
         * Só automático quando houve
         * interação recente.
         */
        if (MUTATION_METHODS.has(method)) {
            return hasRecentUserIntent();
        }

        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | Mensagem
    |--------------------------------------------------------------------------
    */

    function requestLabel(method, options = {}) {
        const explicitLabel =
            options.loadingLabel ||
            options.requestLoaderLabel ||
            lastUserIntentLabel;

        if (explicitLabel) {
            return normalizeLabel(explicitLabel);
        }

        if (method === "GET") {
            return "Atualizando dados...";
        }

        if (method === "DELETE") {
            return "Excluindo...";
        }

        if (method === "PUT" || method === "PATCH") {
            return "Salvando alterações...";
        }

        if (method === "POST") {
            return "Processando solicitação...";
        }

        return "Processando...";
    }

    /*
    |--------------------------------------------------------------------------
    | FETCH
    |--------------------------------------------------------------------------
    |
    | Propriedades extras disponíveis:
    |
    | globalLoader
    | requestLoader
    | loaderType
    | requestLoaderType
    | loadingLabel
    | requestLoaderLabel
    |
    | Elas são removidas antes da chamada
    | ao fetch nativo.
    |
    */

    const nativeFetch = window.fetch?.bind(window);

    if (nativeFetch) {
        window.fetch = (input, init = {}) => {
            const method = normalizeMethod(
                init.method ||
                    (input instanceof Request ? input.method : "GET"),
            );

            const loaderOptions = {
                globalLoader: init.globalLoader,

                requestLoader: init.requestLoader,

                loaderType: init.loaderType,

                requestLoaderType: init.requestLoaderType,

                loadingLabel: init.loadingLabel,

                requestLoaderLabel: init.requestLoaderLabel,
            };

            const useLoader = shouldUseLoader(method, loaderOptions);

            /*
             * Remove as propriedades
             * exclusivas do SGC.
             */
            const {
                globalLoader,
                requestLoader,
                loaderType,
                requestLoaderType,
                loadingLabel,
                requestLoaderLabel,

                ...nativeInit
            } = init;

            if (useLoader) {
                beginLoading(requestLabel(method, loaderOptions));
            }

            let promise;

            try {
                promise = nativeFetch(input, nativeInit);
            } catch (error) {
                if (useLoader) {
                    endLoading();
                }

                throw error;
            }

            /*
             * Request sem loader.
             */
            if (!useLoader) {
                return promise;
            }

            /*
             * Loader termina junto com
             * a request.
             */
            return Promise.resolve(promise).finally(endLoading);
        };
    }

    /*
    |--------------------------------------------------------------------------
    | XMLHttpRequest / AJAX legado
    |--------------------------------------------------------------------------
    */

    if (window.XMLHttpRequest) {
        const nativeOpen = XMLHttpRequest.prototype.open;

        const nativeSend = XMLHttpRequest.prototype.send;

        XMLHttpRequest.prototype.open = function (method, url, ...args) {
            this.__sgcMethod = normalizeMethod(method);

            this.__sgcUrl = url;

            return nativeOpen.call(this, method, url, ...args);
        };

        XMLHttpRequest.prototype.send = function (...args) {
            const method = this.__sgcMethod || "GET";

            const loaderOptions = {
                globalLoader: this.__sgcGlobalLoader,

                requestLoader: this.__sgcRequestLoader,

                loaderType: this.__sgcLoaderType,

                requestLoaderType: this.__sgcRequestLoaderType,

                loadingLabel: this.__sgcLoadingLabel,

                requestLoaderLabel: this.__sgcRequestLoaderLabel,
            };

            const useLoader = shouldUseLoader(method, loaderOptions);

            if (useLoader) {
                beginLoading(requestLabel(method, loaderOptions));

                this.addEventListener("loadend", endLoading, {
                    once: true,
                });
            }

            return nativeSend.apply(this, args);
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Ciclo da página
    |--------------------------------------------------------------------------
    */

    if (!pageReady) {
        window.addEventListener(
            "load",
            () => {
                pageReady = true;

                /*
                 * Qualquer request feita
                 * durante o boot não deixa
                 * estado residual.
                 */
                resetLoading();
            },
            {
                once: true,
            },
        );
    }

    /*
     * BFCache:
     *
     * voltar/avançar pelo navegador
     * deve restaurar uma tela limpa.
     */
    window.addEventListener("pageshow", () => {
        pageReady = true;
        resetLoading();
    });

    window.addEventListener("pagehide", resetLoading);

    /*
     * Estado inicial.
     */
    resetLoading();
})();
