(function () {
    "use strict";

    const root = document.querySelector("[data-accounting-page]");
    if (!root) return;

    const page = root.dataset.accountingPage;

    const money = new Intl.NumberFormat("pt-BR", {
        style: "currency",
        currency: "BRL",
    });

    const quantity = new Intl.NumberFormat("pt-BR", {
        minimumFractionDigits: 0,
        maximumFractionDigits: 4,
    });

    const esc = (value) =>
        String(value ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");

    /* =========================================================
       PHOSPHOR
       ========================================================= */

    const phosphorIconMap = {
        "circle-check": "ph-check-circle",
        "chevron-left": "ph-caret-left",
        "chevron-right": "ph-caret-right",
        "list-tree": "ph-tree-structure",
        "file-text": "ph-file-text",
        "alert-triangle": "ph-warning",
        "circle-alert": "ph-warning-circle",
        clock: "ph-clock",
        wallet: "ph-wallet",
        receipt: "ph-receipt",
        "receipt-text": "ph-receipt",
        files: "ph-files",
        "file-clock": "ph-file-dashed",
        send: "ph-paper-plane-tilt",
        search: "ph-magnifying-glass",
        "folder-search": "ph-folder-open",
        settings: "ph-gear",
        printer: "ph-printer",
        "key-round": "ph-key",
        "external-link": "ph-arrow-square-out",
        "pencil-line": "ph-pencil-line",
        x: "ph-x",
    };

    const normalizePhosphorIcon = (name) => {
        const raw = String(name || "").trim();

        if (!raw) {
            return "ph-circle";
        }

        if (raw.startsWith("ph-")) {
            return raw;
        }

        return phosphorIconMap[raw] || `ph-${raw}`;
    };

    const icon = (name, className = "") => `
        <i
            class="ph-fill ${esc(normalizePhosphorIcon(name))} ${esc(
        className
    )}"
            aria-hidden="true"
        ></i>
    `;

    const phIcon = (name) => `
        <i
            class="ph-fill ${esc(normalizePhosphorIcon(name))}"
            aria-hidden="true"
        ></i>
    `;

    const refreshIcons = () => {};

    const ensurePhosphorFillStylesheet = () => {
        if (
            document.querySelector(
                'link[href*="@phosphor-icons"][href*="fill"]'
            )
        ) {
            return;
        }

        const link = document.createElement("link");

        link.rel = "stylesheet";
        link.href =
            "https://unpkg.com/@phosphor-icons/web@2.1.1/src/fill/style.css";

        document.head.appendChild(link);
    };

    ensurePhosphorFillStylesheet();

    /* =========================================================
       HELPERS
       ========================================================= */

    const badge = (label, tone = "neutral") => `
        <span
            class="acc-badge acc-badge-${esc(tone)}"
        >
            ${esc(label)}
        </span>
    `;

    const skeletons = (count = 4) =>
        Array.from(
            { length: count },
            () => '<div class="acc-skeleton"></div>'
        ).join("");

    async function getJson(url, signal) {
        const response = await fetch(url, {
            headers: {
                Accept: "application/json",

                "X-Requested-With": "XMLHttpRequest",
            },

            credentials: "same-origin",

            cache: "no-store",

            signal,
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(
                payload.message || "Não foi possível carregar os dados."
            );
        }

        return payload;
    }

    async function postJson(url, body) {
        const token =
            document.querySelector('meta[name="csrf-token"]')?.content || "";

        const response = await fetch(url, {
            method: "POST",

            headers: {
                Accept: "application/json",

                "Content-Type": "application/json",

                "X-Requested-With": "XMLHttpRequest",

                "X-CSRF-TOKEN": token,
            },

            credentials: "same-origin",

            cache: "no-store",

            body: JSON.stringify(body || {}),
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            const details = Array.isArray(payload.issues)
                ? payload.issues.map((issue) => issue.message).join(" ")
                : "";

            throw new Error(
                details ||
                    payload.message ||
                    "Não foi possível concluir a ação."
            );
        }

        return payload;
    }

    function showError(container, error) {
        if (!container) {
            return;
        }

        container.innerHTML = `
            <div
                class="acc-error"
                role="alert"
            >
                ${esc(error.message || error)}
            </div>
        `;
    }

    function filtersUrl(base, filters) {
        const url = new URL(base, window.location.origin);

        Object.entries(filters || {}).forEach(([key, value]) => {
            if (value !== "" && value !== null && value !== undefined) {
                url.searchParams.set(key, value);
            }
        });

        return url.toString();
    }

    /* =========================================================
       COPY
       ========================================================= */

    const encodeCopy = (value) => encodeURIComponent(String(value ?? ""));

    const copyButton = (value, label = "Copiar", className = "") => `
        <button
            class="acc-button acc-copy-button ${esc(className)}"
            type="button"
            data-copy-text="${esc(encodeCopy(value))}"
        >
            ${phIcon("ph-copy")}

            <span data-copy-label>
                ${esc(label)}
            </span>
        </button>
    `;

    async function copyText(value) {
        const text = String(value ?? "");

        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(text);

            return;
        }

        const textarea = document.createElement("textarea");

        textarea.value = text;

        textarea.setAttribute("readonly", "");

        textarea.style.position = "fixed";

        textarea.style.opacity = "0";

        textarea.style.pointerEvents = "none";

        document.body.appendChild(textarea);

        textarea.select();

        try {
            document.execCommand("copy");
        } finally {
            textarea.remove();
        }
    }

    function showCopyFeedback(button) {
        const label = button.querySelector("[data-copy-label]");

        const original = label?.textContent || "Copiar";

        button.classList.add("is-copied");

        if (label) {
            label.textContent = "Copiado";
        }

        window.clearTimeout(button._copyTimer);

        button._copyTimer = window.setTimeout(() => {
            button.classList.remove("is-copied");

            if (label) {
                label.textContent = original;
            }
        }, 1400);
    }

    document.addEventListener("click", async (event) => {
        const button = event.target.closest(
            "[data-copy-text], [data-copy-value]"
        );

        if (!button) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        const value = button.dataset.copyText
            ? decodeURIComponent(button.dataset.copyText)
            : button.dataset.copyValue || "";

        try {
            await copyText(value);

            showCopyFeedback(button);
        } catch (_) {
            const label = button.querySelector("[data-copy-label]");

            if (label) {
                label.textContent = "Falhou";

                window.setTimeout(() => {
                    label.textContent = "Copiar";
                }, 1400);
            }
        }
    });

    /* =========================================================
       QUEUE
       ========================================================= */

    async function loadQueue() {
        const target = document.querySelector("[data-queue-list]");

        const summary = document.querySelector("[data-queue-summary]");

        const totalElement = document.querySelector("[data-queue-total]");

        if (!target || !summary) {
            return;
        }

        target.innerHTML = skeletons(4);

        const queueIcon = (item) => {
            const label = String(item?.label || "").toLowerCase();

            if (/erro|integridade|corrigir|falha|inconsist/.test(label)) {
                return "ph-warning-circle";
            }

            if (/rascunho|prepar|completar/.test(label)) {
                return "ph-receipt";
            }

            if (/autoriza|aprova/.test(label)) {
                return "ph-check-circle";
            }

            if (/saldo|receb|pagamento/.test(label)) {
                return "ph-clock-countdown";
            }

            switch (String(item?.tone || "")) {
                case "danger":
                case "warning":
                    return "ph-warning-circle";

                case "success":
                    return "ph-check-circle";

                case "info":
                case "cyan":
                    return "ph-folder-open";

                case "violet":
                case "purple":
                    return "ph-receipt";

                default:
                    return "ph-folder";
            }
        };

        const queueStatus = (item) => {
            switch (String(item?.tone || "")) {
                case "danger":
                    return "Corrigir";

                case "warning":
                    return "Preparar";

                case "success":
                    return "Pronto";

                case "info":
                case "cyan":
                    return "Acompanhar";

                case "violet":
                case "purple":
                    return "Revisar";

                default:
                    return "Pendente";
            }
        };

        try {
            const payload = await getJson(root.dataset.queueUrl);

            const baseProcesses = root.dataset.processesUrl;

            const openProcesses = Number(payload.summary?.open_processes || 0);

            const openAmount = Number(payload.summary?.open_amount || 0);

            const workflowLabel =
                payload.summary?.workflow_label || "Fluxo contábil";

            const queueItems = payload.queue || [];

            const totalPending = queueItems.reduce(
                (total, item) => total + Number(item.count || 0),
                0
            );

            if (totalElement) {
                totalElement.innerHTML = `
                    <i
                        class="ph-fill ph-folder"
                        aria-hidden="true"
                    ></i>

                    ${esc(totalPending)}
                `;
            }

            summary.innerHTML = `
                <article class="accounting-primary">
                    <div>
                        <div class="accounting-primary-label">
                            <i
                                class="ph-fill ph-clock-countdown"
                                aria-hidden="true"
                            ></i>

                            Saldo a receber
                        </div>

                        <div class="accounting-primary-value">
                            ${money.format(openAmount)}
                        </div>

                        <div class="accounting-primary-helper">
                            Valor que permanece pendente
                            nos faturamentos em andamento.
                        </div>
                    </div>

                    <div class="accounting-primary-foot">
                        <span>
                            Processos em andamento:

                            <strong>
                                ${esc(openProcesses)}
                            </strong>
                        </span>

                        <span>
                            ${esc(workflowLabel)}
                        </span>
                    </div>
                </article>

                <div class="accounting-secondary">
                    <div class="accounting-kpis">
                        <article class="accounting-kpi">
                            <div class="accounting-kpi-head">
                                <span
                                    class="accounting-kpi-icon"
                                    aria-hidden="true"
                                >
                                    <i
                                        class="ph-fill ph-receipt"
                                    ></i>
                                </span>

                                <span class="accounting-kpi-label">
                                    Faturamentos em andamento
                                </span>
                            </div>

                            <strong>
                                ${esc(openProcesses)}
                            </strong>
                        </article>

                        <article
                            class="accounting-kpi workflow"
                        >
                            <div class="accounting-kpi-head">
                                <span
                                    class="accounting-kpi-icon"
                                    aria-hidden="true"
                                >
                                    <i
                                        class="ph-fill ph-check-circle"
                                    ></i>
                                </span>

                                <span class="accounting-kpi-label">
                                    Fluxo contábil
                                </span>
                            </div>

                            <strong>
                                5 etapas
                            </strong>
                        </article>
                    </div>

                    <div class="accounting-workflow">
                        <div class="workflow-head">
                            <strong>
                                Situação operacional
                            </strong>

                            <span>
                                ${esc(workflowLabel)}
                            </span>
                        </div>

                        <div class="workflow-steps">
                            <div class="workflow-step">
                                <span
                                    class="workflow-step-icon"
                                    aria-hidden="true"
                                >
                                    <i
                                        class="ph-fill ph-receipt"
                                    ></i>
                                </span>

                                <div class="workflow-step-copy">
                                    <strong>
                                        Processos abertos
                                    </strong>

                                    <span>
                                        ${esc(openProcesses)}
                                        faturamento(s)
                                        em acompanhamento
                                    </span>
                                </div>
                            </div>

                            <div class="workflow-step">
                                <span
                                    class="workflow-step-icon"
                                    aria-hidden="true"
                                >
                                    <i
                                        class="ph-fill ph-warning-circle"
                                    ></i>
                                </span>

                                <div class="workflow-step-copy">
                                    <strong>
                                        Pendências na fila
                                    </strong>

                                    <span>
                                        ${esc(totalPending)}
                                        processo(s)
                                        exigindo atenção
                                    </span>
                                </div>
                            </div>

                            <div class="workflow-step">
                                <span
                                    class="workflow-step-icon"
                                    aria-hidden="true"
                                >
                                    <i
                                        class="ph-fill ph-check-circle"
                                    ></i>
                                </span>

                                <div class="workflow-step-copy">
                                    <strong>
                                        Fluxo
                                    </strong>

                                    <span>
                                        ${esc(workflowLabel)}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            if (payload.empty || !queueItems.length) {
                target.innerHTML = `
                    <div class="acc-empty">
                        <div>
                            <span
                                class="empty-icon"
                                aria-hidden="true"
                            >
                                <i
                                    class="ph-fill ph-check-circle"
                                ></i>
                            </span>

                            <strong>
                                Nenhuma ação pendente
                            </strong>

                            <span>
                                A fila contábil está limpa
                                neste momento.
                            </span>
                        </div>
                    </div>
                `;

                return;
            }

            target.innerHTML = queueItems
                .map((item) => {
                    const url = filtersUrl(baseProcesses, item.filters);

                    const tone = String(item.tone || "neutral");

                    const itemIcon = queueIcon(item);

                    const status = queueStatus(item);

                    return `
                                <a
                                    class="acc-queue-row acc-tone-${esc(tone)}"
                                    href="${esc(url)}"
                                >
                                    <span class="queue-main">
                                        <span
                                            class="acc-queue-icon"
                                            aria-hidden="true"
                                        >
                                            <i
                                                class="ph-fill ${esc(itemIcon)}"
                                            ></i>
                                        </span>

                                        <span class="acc-queue-copy">
                                            <strong>
                                                ${esc(item.label)}
                                            </strong>

                                            <span>
                                                Abrir processos relacionados
                                            </span>
                                        </span>
                                    </span>

                                    <span class="queue-status">
                                        ${esc(status)}
                                    </span>

                                    <span class="acc-queue-count">
                                        ${esc(item.count)}
                                    </span>

                                    <span
                                        class="acc-queue-arrow"
                                        aria-hidden="true"
                                    >
                                        <i
                                            class="ph-fill ph-arrow-right"
                                        ></i>
                                    </span>
                                </a>
                            `;
                })
                .join("");
        } catch (error) {
            if (totalElement) {
                totalElement.innerHTML = `
                    <i
                        class="ph-fill ph-warning-circle"
                        aria-hidden="true"
                    ></i>

                    —
                `;
            }

            showError(target, error);
        }
    }

    /* =========================================================
       PROCESS INDEX
       ========================================================= */

    function processDesktopRow(process) {
        const conference = process.critical_issues
            ? badge(`${process.critical_issues} erro(s)`, "danger")
            : process.preparation_issues
            ? badge(`${process.preparation_issues} item(ns)`, "warning")
            : badge("Pronto", "success");

        return `
            <tr>
                <td>
                    <a
                        class="acc-link"
                        href="${esc(process.url)}"
                    >
                        ${esc(process.number)}
                    </a>

                    <div class="acc-muted">
                        ${esc(process.issued_at || "")}
                    </div>
                </td>

                <td>
                    <strong>
                        ${esc(process.project)}
                    </strong>

                    <div class="acc-muted">
                        ${esc(process.project_code || "")}
                    </div>
                </td>

                <td>
                    ${esc(process.recipient)}

                    <div class="acc-muted">
                        ${esc(process.recipient_type)}
                    </div>
                </td>

                <td>
                    ${badge(process.state.label, process.state.tone)}

                    <div class="acc-muted">
                        ${esc(process.state.next_action)}
                    </div>
                </td>

                <td class="acc-money">
                    ${money.format(process.net || 0)}

                    <div class="acc-muted">
                        Saldo
                        ${money.format(process.remaining || 0)}
                    </div>
                </td>

                <td>
                    ${conference}
                </td>

                <td class="process-action-column">
                    <a
                        class="process-open"
                        href="${esc(process.url)}"
                        aria-label="Acessar ${esc(process.number)}"
                    >
                        Acessar

                        <i
                            class="ph-fill ph-arrow-right"
                            aria-hidden="true"
                        ></i>
                    </a>
                </td>
            </tr>
        `;
    }

    function processMobileRow(process) {
        const conference = process.critical_issues
            ? `${esc(process.critical_issues)} erro(s)`
            : process.preparation_issues
            ? `${esc(process.preparation_issues)} item(ns) a completar`
            : "Pronto";

        return `
            <article
                class="acc-mobile-row"
                data-tone="${esc(process.state.tone || "neutral")}"
            >
                <div class="acc-mobile-head">
                    <div class="mobile-process-main">
                        <a
                            class="acc-link"
                            href="${esc(process.url)}"
                        >
                            ${esc(process.number)}
                        </a>

                        <span class="acc-muted">
                            ${esc(process.project)}
                        </span>
                    </div>

                    ${badge(process.state.label, process.state.tone)}
                </div>

                <div class="acc-mobile-meta">
                    <span>
                        Destinatário

                        <strong>
                            ${esc(process.recipient)}
                        </strong>
                    </span>

                    <span>
                        Valor líquido

                        <strong>
                            ${money.format(process.net || 0)}
                        </strong>
                    </span>

                    <span>
                        Próxima ação

                        <strong>
                            ${esc(process.state.next_action)}
                        </strong>
                    </span>

                    <span>
                        Conferência

                        <strong>
                            ${conference}
                        </strong>
                    </span>
                </div>

                <div class="mobile-process-actions">
                    <a
                        class="process-open"
                        href="${esc(process.url)}"
                    >
                        Acessar

                        <i
                            class="ph-fill ph-arrow-right"
                            aria-hidden="true"
                        ></i>
                    </a>
                </div>
            </article>
        `;
    }

    async function initProcesses() {
        const form = document.querySelector("[data-process-filters]");

        const tableBody = document.querySelector("[data-process-table]");

        const mobile = document.querySelector("[data-process-mobile]");

        const pagination = document.querySelector("[data-process-pagination]");

        if (!form || !tableBody || !mobile || !pagination) {
            return;
        }

        const projectSelect = form.elements.project;

        const organizationSelect = form.elements.organization;

        const customerSelect = form.elements.customer;

        let controller;
        let filterOptionsLoaded = false;

        const current = new URLSearchParams(window.location.search);

        [
            "search",
            "project",
            "organization",
            "customer",
            "from",
            "until",
            "financial_status",
            "authorization_status",
            "fiscal_status",
            "accountability_status",
            "pending",
        ].forEach((name) => {
            if (current.has(name) && form.elements[name]) {
                form.elements[name].value = current.get(name);
            }
        });

        async function load(pageNumber = 1) {
            controller?.abort();

            controller = new AbortController();

            tableBody.innerHTML = `
                <tr>
                    <td colspan="7">
                        ${skeletons(5)}
                    </td>
                </tr>
            `;

            mobile.innerHTML = skeletons(5);

            pagination.innerHTML = "";

            const params = Object.fromEntries(new FormData(form).entries());

            params.page = pageNumber;

            try {
                const payload = await getJson(
                    filtersUrl(root.dataset.processesDataUrl, params),
                    controller.signal
                );

                const paginator = payload.processes;

                if (!filterOptionsLoaded) {
                    if (projectSelect) {
                        projectSelect.innerHTML =
                            '<option value="">Todos os projetos</option>' +
                            (payload.filters?.projects || [])
                                .map(
                                    (option) => `
                                        <option value="${esc(option.id)}">
                                            ${esc(option.label)}
                                        </option>
                                    `
                                )
                                .join("");

                        projectSelect.value = params.project || "";
                    }

                    if (organizationSelect) {
                        organizationSelect.innerHTML =
                            '<option value="">Todas as organizações</option>' +
                            (payload.filters?.organizations || [])
                                .map(
                                    (option) => `
                                        <option value="${esc(option.id)}">
                                            ${esc(option.label)}
                                        </option>
                                    `
                                )
                                .join("");

                        organizationSelect.value = params.organization || "";
                    }

                    if (customerSelect) {
                        customerSelect.innerHTML =
                            '<option value="">Todos os clientes</option>' +
                            (payload.filters?.customers || [])
                                .map(
                                    (option) => `
                                        <option value="${esc(option.id)}">
                                            ${esc(option.label)}
                                        </option>
                                    `
                                )
                                .join("");

                        customerSelect.value = params.customer || "";
                    }

                    filterOptionsLoaded = true;
                }

                if (!paginator.data.length) {
                    tableBody.innerHTML = `
                        <tr>
                            <td colspan="7">
                                <div class="acc-empty">
                                    Nenhum processo encontrado.
                                </div>
                            </td>
                        </tr>
                    `;

                    mobile.innerHTML = `
                        <div class="acc-empty">
                            Nenhum processo encontrado.
                        </div>
                    `;
                } else {
                    tableBody.innerHTML = paginator.data
                        .map(processDesktopRow)
                        .join("");

                    mobile.innerHTML = paginator.data
                        .map(processMobileRow)
                        .join("");
                }

                pagination.innerHTML = `
                    <span>
                        ${esc(paginator.from || 0)}
                        –
                        ${esc(paginator.to || 0)}
                        de
                        ${esc(paginator.total || 0)}
                    </span>

                    <div class="acc-pagination-actions">
                        <button
                            class="acc-button"
                            type="button"
                            data-page="${paginator.current_page - 1}"
                            ${paginator.current_page <= 1 ? "disabled" : ""}
                        >
                            ${phIcon("ph-caret-left")}

                            Anterior
                        </button>

                        <button
                            class="acc-button"
                            type="button"
                            data-page="${paginator.current_page + 1}"
                            ${
                                paginator.current_page >= paginator.last_page
                                    ? "disabled"
                                    : ""
                            }
                        >
                            Próxima

                            ${phIcon("ph-caret-right")}
                        </button>
                    </div>
                `;

                pagination
                    .querySelectorAll("[data-page]")
                    .forEach((button) =>
                        button.addEventListener("click", () =>
                            load(Number(button.dataset.page))
                        )
                    );

                const browserUrl = new URL(window.location.href);

                Object.entries(params).forEach(([key, value]) =>
                    value
                        ? browserUrl.searchParams.set(key, value)
                        : browserUrl.searchParams.delete(key)
                );

                browserUrl.searchParams.delete("page");

                history.replaceState({}, "", browserUrl);
            } catch (error) {
                if (error.name !== "AbortError") {
                    tableBody.innerHTML = `
                        <tr>
                            <td colspan="7">
                                <div class="acc-error">
                                    ${esc(error.message)}
                                </div>
                            </td>
                        </tr>
                    `;

                    showError(mobile, error);
                }
            }
        }

        let timer;

        form.addEventListener("input", (event) => {
            clearTimeout(timer);

            timer = setTimeout(
                () => load(1),

                event.target.name === "search" ? 320 : 0
            );
        });

        form.addEventListener("change", (event) => {
            if (event.target.matches('select, input[type="date"]')) {
                load(1);
            }
        });

        form.addEventListener("submit", (event) => {
            event.preventDefault();

            load(1);
        });

        form.querySelector("[data-clear-filters]")?.addEventListener(
            "click",
            () => {
                form.reset();

                load(1);
            }
        );

        load(Number(current.get("page") || 1));
    }

    /* =========================================================
       DISTRIBUTIONS
       ========================================================= */

    function renderDistributions(distributions) {
        const rows = distributions.data || [];

        if (!rows.length) {
            return `
                <div class="acc-empty">
                    Nenhuma distribuição vinculada.
                </div>
            `;
        }

        const table = `
            <div class="acc-data-table-block acc-data-table-distributions">
                <div class="acc-table-tools">
                    <div class="acc-table-tools-copy">
                        ${phIcon("ph-arrows-left-right")}

                        <div>
                            <strong>
                                Distribuições
                            </strong>

                            <span>
                                Destinos e valores distribuídos
                                a partir das entregas recebidas.
                            </span>
                        </div>
                    </div>
                </div>

                <div class="acc-table-wrap">
                    <table class="acc-table acc-workspace-table">
                        <thead>
                            <tr>
                                <th>
                                    Origem
                                </th>

                                <th>
                                    Produto e destino
                                </th>

                                <th>
                                    Membro
                                </th>

                                <th>
                                    Quantidade
                                </th>

                                <th>
                                    Valor
                                </th>

                                <th class="acc-copy-column">
                                    Copiar
                                </th>
                            </tr>
                        </thead>

                        <tbody>
                            ${rows
                                .map((row) => {
                                    const rowText = [
                                        `Data: ${
                                            row.parent?.date || "Sem origem"
                                        }`,

                                        `Entrega: ${
                                            row.parent?.id
                                                ? `#${row.parent.id}`
                                                : "—"
                                        }`,

                                        `Produto: ${row.product || ""}`,

                                        `Destino: ${row.customer || ""}`,

                                        `Membro: ${row.member || ""}`,

                                        `Quantidade: ${quantity.format(
                                            row.quantity || 0
                                        )} ${row.unit || ""}`.trim(),

                                        `Valor: ${money.format(
                                            row.gross_value || 0
                                        )}`,

                                        `Preço unitário: ${money.format(
                                            row.unit_price || 0
                                        )} / ${row.unit || ""}`,
                                    ].join("\n");

                                    return `
                                                <tr>
                                                    <td>
                                                        ${esc(
                                                            row.parent?.date ||
                                                                "Sem origem"
                                                        )}

                                                        <div class="acc-muted">
                                                            Entrega #
                                                            ${esc(
                                                                row.parent
                                                                    ?.id || "—"
                                                            )}
                                                        </div>
                                                    </td>

                                                    <td>
                                                        <strong>
                                                            ${esc(row.product)}
                                                        </strong>

                                                        <div class="acc-muted">
                                                            ${esc(row.customer)}
                                                        </div>
                                                    </td>

                                                    <td>
                                                        ${esc(row.member)}
                                                    </td>

                                                    <td>
                                                        ${quantity.format(
                                                            row.quantity || 0
                                                        )}

                                                        ${esc(row.unit)}
                                                    </td>

                                                    <td class="acc-money">
                                                        ${money.format(
                                                            row.gross_value || 0
                                                        )}

                                                        <div class="acc-muted">
                                                            ${money.format(
                                                                row.unit_price ||
                                                                    0
                                                            )}

                                                            /

                                                            ${esc(row.unit)}
                                                        </div>
                                                    </td>

                                                    <td class="acc-copy-column">
                                                        ${copyButton(
                                                            rowText,
                                                            "Copiar",
                                                            "acc-button-icon-copy"
                                                        )}
                                                    </td>
                                                </tr>
                                            `;
                                })
                                .join("")}
                        </tbody>
                    </table>
                </div>
            </div>
        `;

        if ((distributions.last_page || 1) <= 1) {
            return table;
        }

        return (
            table +
            `
            <div class="acc-pagination">
                <span>
                    ${esc(distributions.from || 0)}
                    –
                    ${esc(distributions.to || 0)}
                    de
                    ${esc(distributions.total || 0)}
                </span>

                <div class="acc-pagination-actions">
                    <button
                        class="acc-button"
                        type="button"
                        data-dist-page="${distributions.current_page - 1}"
                        ${distributions.current_page <= 1 ? "disabled" : ""}
                    >
                        ${phIcon("ph-caret-left")}

                        Anterior
                    </button>

                    <button
                        class="acc-button"
                        type="button"
                        data-dist-page="${distributions.current_page + 1}"
                        ${
                            distributions.current_page >=
                            distributions.last_page
                                ? "disabled"
                                : ""
                        }
                    >
                        Próxima

                        ${phIcon("ph-caret-right")}
                    </button>
                </div>
            </div>
        `
        );
    }

    /* =========================================================
       CONSOLIDATED LINES
       ========================================================= */

    function renderConsolidatedLines(lines) {
        if (!lines?.length) {
            return `
                <div class="acc-empty">
                    Este registro legado ainda não possui
                    linhas consolidadas no snapshot.
                </div>
            `;
        }

        return `
            <div class="acc-data-table-block acc-data-table-delivered">
                <div class="acc-table-tools">
                    <div class="acc-table-tools-copy">
                        ${phIcon("ph-package")}

                        <div>
                            <strong>
                                Total entregue
                            </strong>

                            <span>
                                Produtos e quantidades
                                consolidados no faturamento.
                            </span>
                        </div>
                    </div>
                </div>

                <div class="acc-table-wrap">
                    <table class="acc-table acc-workspace-table">
                        <thead>
                            <tr>
                                <th>
                                    Produto
                                </th>

                                <th>
                                    Quantidade
                                </th>

                                <th>
                                    Preço unitário
                                </th>

                                <th>
                                    Valor documental
                                </th>

                                <th class="acc-copy-column">
                                    Copiar
                                </th>
                            </tr>
                        </thead>

                        <tbody>
                            ${lines
                                .map((line) => {
                                    const rowText = [
                                        `Produto: ${line.product || ""}`,

                                        line.project
                                            ? `Projeto: ${line.project}`
                                            : "",

                                        `Quantidade: ${quantity.format(
                                            Number(line.quantity || 0)
                                        )} ${line.unit || ""}`.trim(),

                                        `Preço unitário: ${money.format(
                                            Number(line.unit_price || 0)
                                        )}`,

                                        `Valor documental: ${money.format(
                                            Number(line.document_amount || 0)
                                        )}`,
                                    ]
                                        .filter(Boolean)
                                        .join("\n");

                                    return `
                                                <tr>
                                                    <td>
                                                        <strong>
                                                            ${esc(line.product)}
                                                        </strong>

                                                        <div class="acc-muted">
                                                            ${esc(
                                                                line.project ||
                                                                    ""
                                                            )}
                                                        </div>
                                                    </td>

                                                    <td>
                                                        ${quantity.format(
                                                            Number(
                                                                line.quantity ||
                                                                    0
                                                            )
                                                        )}

                                                        ${esc(line.unit)}
                                                    </td>

                                                    <td class="acc-money">
                                                        ${money.format(
                                                            Number(
                                                                line.unit_price ||
                                                                    0
                                                            )
                                                        )}
                                                    </td>

                                                    <td class="acc-money">
                                                        <strong>
                                                            ${money.format(
                                                                Number(
                                                                    line.document_amount ||
                                                                        0
                                                                )
                                                            )}
                                                        </strong>
                                                    </td>

                                                    <td class="acc-copy-column">
                                                        ${copyButton(
                                                            rowText,
                                                            "Copiar",
                                                            "acc-button-icon-copy"
                                                        )}
                                                    </td>
                                                </tr>
                                            `;
                                })
                                .join("")}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }

    /* =========================================================
       AUTHORIZATIONS
       ========================================================= */

    function renderAuthorizations(rounds) {
        if (!rounds?.length) {
            return `
                <div class="acc-empty">
                    Processo anterior ao workflow de autorização.
                </div>
            `;
        }

        return `
            <ul class="acc-simple-list">
                ${rounds
                    .map(
                        (round) => `
                                <li class="acc-simple-item">
                                    <strong>
                                        Versão
                                        ${esc(round.sequence)}
                                        ·
                                        ${esc(round.label)}
                                    </strong>

                                    ${
                                        round.organization
                                            ? `
                                                <span>
                                                    Organização:
                                                    ${esc(round.organization)}
                                                </span>
                                            `
                                            : ""
                                    }

                                    <span>
                                        Enviada em
                                        ${esc(round.sent_at || "—")}
                                        por
                                        ${esc(
                                            round.sent_by ||
                                                "Membro não identificado"
                                        )}
                                    </span>

                                    ${
                                        round.responded_at
                                            ? `
                                                <span>
                                                    Resposta em
                                                    ${esc(round.responded_at)}
                                                    por
                                                    ${esc(
                                                        round.responded_by ||
                                                            "Representante autorizado"
                                                    )}
                                                </span>
                                            `
                                            : ""
                                    }

                                    ${
                                        round.validity
                                            ? `
                                                <span>
                                                    Validade atual:
                                                    ${esc(round.validity)}
                                                </span>
                                            `
                                            : ""
                                    }

                                    ${
                                        round.message
                                            ? `
                                                <p class="acc-auth-message">
                                                    ${esc(round.message)}
                                                </p>
                                            `
                                            : ""
                                    }

                                    ${
                                        round.invalidation_reason
                                            ? `
                                                <p class="acc-auth-message acc-auth-warning">
                                                    ${esc(
                                                        round.invalidation_reason
                                                    )}
                                                </p>
                                            `
                                            : ""
                                    }
                                </li>
                            `
                    )
                    .join("")}
            </ul>
        `;
    }

    function renderAuthorizationAccess(authorization) {
        const access = authorization.access || {};

        if (access.applicable === false) {
            return `
                <div class="acc-guidance">
                    <strong>
                        Destinatário individual
                    </strong>

                    <span>
                        Este processo não exige autorização
                        por representante de uma organização compradora.
                    </span>
                </div>
            `;
        }

        const recipients = access.recipients || [];

        return `
            <section class="acc-auth-access">
                <h3 class="acc-section-title">
                    Quem responde pela organização
                </h3>

                ${
                    recipients.length
                        ? `
                            <ul class="acc-simple-list">
                                ${recipients
                                    .map(
                                        (recipient) => `
                                                <li class="acc-simple-item">
                                                    <strong>
                                                        ${esc(
                                                            recipient.name ||
                                                                "Representante autorizado"
                                                        )}
                                                        ·
                                                        ${esc(recipient.email)}
                                                    </strong>

                                                    <span>
                                                        ${
                                                            recipient.has_account
                                                                ? "Conta ativa"
                                                                : "Aguardando primeiro acesso"
                                                        }

                                                        ${
                                                            recipient.last_access_at
                                                                ? ` · último acesso ${esc(
                                                                      recipient.last_access_at
                                                                  )}`
                                                                : ""
                                                        }
                                                    </span>
                                                </li>
                                            `
                                    )
                                    .join("")}
                            </ul>
                        `
                        : `
                            <div class="acc-error">
                                Nenhum e-mail foi autorizado para responder
                                por esta organização.

                                O e-mail do cadastro geral, sozinho,
                                não concede poder de aprovação.
                            </div>
                        `
                }

                ${
                    root.dataset.canSendAuthorization === "1"
                        ? `
                            <form
                                class="acc-inline-form"
                                data-access-form
                            >
                                <label class="acc-field">
                                    <span>
                                        E-mail autorizado
                                    </span>

                                    <input
                                        class="acc-input"
                                        type="email"
                                        name="email"
                                        required
                                        maxlength="255"
                                        value="${esc(
                                            access.organization_email || ""
                                        )}"
                                        placeholder="representante@organizacao.gov.br"
                                    >
                                </label>

                                <label class="acc-field">
                                    <span>
                                        Nome do responsável
                                    </span>

                                    <input
                                        class="acc-input"
                                        name="name"
                                        maxlength="255"
                                        value="${esc(
                                            access.organization_contact || ""
                                        )}"
                                    >
                                </label>

                                <button
                                    class="acc-button"
                                    type="submit"
                                >
                                    ${phIcon("ph-key")}

                                    Autorizar e-mail
                                </button>

                                <div
                                    class="acc-action-feedback"
                                    data-access-feedback
                                ></div>
                            </form>
                        `
                        : ""
                }

                ${
                    authorization.state === "sent"
                        ? `
                            <div class="acc-guidance">
                                <strong>
                                    Solicitação enviada.
                                </strong>

                                <span>
                                    A resposta é feita em
                                    Portal do Comprador → Autorizações.

                                    O processo volta automaticamente
                                    para esta fila quando for autorizado
                                    ou quando houver pedido de correção.
                                </span>

                                ${
                                    access.buyer_url
                                        ? `
                                            <a
                                                class="acc-button"
                                                href="${esc(access.buyer_url)}"
                                            >
                                                ${phIcon("ph-arrow-square-out")}

                                                Abrir tela da organização
                                            </a>
                                        `
                                        : ""
                                }
                            </div>
                        `
                        : ""
                }
            </section>
        `;
    }

    /* =========================================================
       PRODUCER RECEIPTS
       ========================================================= */

    function renderProducerReceipts(receipts) {
        const rows = receipts.data || [];

        const list = `
            <p
                class="acc-muted"
                style="padding:.72rem .72rem 0"
            >
                Documentos de origem e repasse.
                Seus totais não formam o valor deste faturamento.
            </p>

            <div class="acc-receipt-results">
                ${
                    rows.length
                        ? rows
                              .map(
                                  (receipt) => `
                                    <article class="acc-receipt-card">
                                        <div class="acc-receipt-card-main">
                                            <div>
                                                <strong>
                                                    ${esc(receipt.number)}
                                                    ·
                                                    ${esc(receipt.member)}
                                                </strong>

                                                <div class="acc-muted">
                                                    ${esc(
                                                        receipt.included_distributions ||
                                                            0
                                                    )}
                                                    distribuição(ões)
                                                    incluída(s)
                                                    neste faturamento
                                                </div>
                                            </div>

                                            ${badge(
                                                receipt.status_label,

                                                receipt.status === "paid"
                                                    ? "success"
                                                    : "warning"
                                            )}
                                        </div>

                                        <div class="acc-receipt-actions">
                                            ${
                                                receipt.detail_url
                                                    ? `
                                                        <button
                                                            class="acc-button"
                                                            type="button"
                                                            data-related-detail="${esc(
                                                                receipt.detail_url
                                                            )}"
                                                            data-related-title="${esc(
                                                                receipt.number
                                                            )}"
                                                        >
                                                            ${phIcon(
                                                                "ph-list-bullets"
                                                            )}

                                                            Ver distribuições
                                                        </button>
                                                    `
                                                    : ""
                                            }

                                            ${
                                                receipt.reprint_url
                                                    ? `
                                                        <a
                                                            class="acc-button"
                                                            href="${esc(
                                                                receipt.reprint_url
                                                            )}"
                                                            target="_blank"
                                                            rel="noopener"
                                                        >
                                                            ${phIcon(
                                                                "ph-file-text"
                                                            )}

                                                            Abrir documento
                                                        </a>
                                                    `
                                                    : ""
                                            }
                                        </div>
                                    </article>
                                `
                              )
                              .join("")
                        : `
                            <div class="acc-empty">
                                Nenhum documento de origem relacionado
                                às distribuições deste faturamento.
                            </div>
                        `
                }
            </div>
        `;

        if ((receipts.last_page || 1) <= 1) {
            return list;
        }

        return (
            list +
            `
            <div class="acc-pagination">
                <span>
                    ${esc(receipts.from || 0)}
                    –
                    ${esc(receipts.to || 0)}
                    de
                    ${esc(receipts.total || 0)}
                </span>

                <div class="acc-pagination-actions">
                    <button
                        class="acc-button"
                        type="button"
                        data-producer-page="${receipts.current_page - 1}"
                        ${receipts.current_page <= 1 ? "disabled" : ""}
                    >
                        ${phIcon("ph-caret-left")}

                        Anterior
                    </button>

                    <button
                        class="acc-button"
                        type="button"
                        data-producer-page="${receipts.current_page + 1}"
                        ${
                            receipts.current_page >= receipts.last_page
                                ? "disabled"
                                : ""
                        }
                    >
                        Próxima

                        ${phIcon("ph-caret-right")}
                    </button>
                </div>
            </div>
        `
        );
    }

    /* =========================================================
       RELATED RECEIPT MODAL
       ========================================================= */

    async function openRelatedReceipt(url, receiptNumber) {
        let dialog = document.querySelector("[data-related-receipt-dialog]");

        if (!dialog) {
            dialog = document.createElement("dialog");

            dialog.className = "acc-dialog acc-dialog-wide acc-related-dialog";

            dialog.dataset.relatedReceiptDialog = "";

            dialog.innerHTML = `
                <div class="acc-dialog-head">
                    <div class="acc-dialog-title">
                        <span class="acc-dialog-icon">
                            ${phIcon("ph-arrows-left-right")}
                        </span>

                        <div>
                            <strong
                                data-related-receipt-title
                            ></strong>

                            <span>
                                Distribuições deste documento
                                e faturamentos relacionados
                            </span>
                        </div>
                    </div>

                    <button
                        class="acc-button acc-dialog-close"
                        type="button"
                        data-related-close
                        aria-label="Fechar"
                    >
                        ${phIcon("ph-x")}
                    </button>
                </div>

                <div
                    class="acc-dialog-body"
                    data-related-receipt-content
                ></div>

                <footer class="acc-dialog-footer">
                    <button
                        class="acc-button"
                        type="button"
                        data-related-footer-close
                    >
                        ${phIcon("ph-x-circle")}

                        Fechar
                    </button>
                </footer>
            `;

            document.body.appendChild(dialog);

            const closeDialog = () => dialog.close();

            dialog
                .querySelector("[data-related-close]")
                .addEventListener("click", closeDialog);

            dialog
                .querySelector("[data-related-footer-close]")
                .addEventListener("click", closeDialog);

            dialog.addEventListener("click", (event) => {
                if (event.target === dialog) {
                    closeDialog();
                }
            });
        }

        dialog.querySelector("[data-related-receipt-title]").textContent =
            receiptNumber || "Documento de origem";

        const content = dialog.querySelector("[data-related-receipt-content]");

        content.innerHTML = skeletons(4);

        dialog.showModal();

        try {
            content.innerHTML = renderSourceReceiptDetail(await getJson(url));
        } catch (error) {
            showError(content, error);
        }
    }

    /* =========================================================
       DOSSIER
       ========================================================= */

    async function initDossier() {
        const target = document.querySelector("[data-dossier]");

        if (!target) {
            return;
        }

        target.innerHTML = skeletons(7);

        try {
            const payload = await getJson(root.dataset.processDataUrl);

            const process = payload.process;

            const integrity = process.integrity;

            target.innerHTML = `
                <section class="acc-panel">
                    <div class="acc-state-strip">
                        <div class="acc-state-item">
                            <span>
                                Processo
                            </span>

                            <strong>
                                ${esc(process.state.label)}
                            </strong>
                        </div>

                        <div class="acc-state-item">
                            <span>
                                Autorização
                            </span>

                            <strong>
                                ${esc(process.workflow.authorization.label)}
                            </strong>
                        </div>

                        <div class="acc-state-item">
                            <span>
                                Fiscal
                            </span>

                            <strong>
                                ${esc(process.workflow.fiscal.label)}
                            </strong>
                        </div>
                    </div>

                    <div
                        class="acc-tabs"
                        role="tablist"
                        aria-label="Seções do faturamento"
                    >
                        <button
                            class="acc-tab is-active"
                            type="button"
                            data-tab="overview"
                            data-tone="info"
                            role="tab"
                            aria-selected="true"
                        >
                            <i
                                class="ph-fill ph-chart-donut"
                                aria-hidden="true"
                            ></i>

                            <span>
                                Visão geral
                            </span>
                        </button>

                        <button
                            class="acc-tab"
                            type="button"
                            data-tab="delivered"
                            data-tone="warning"
                            role="tab"
                            aria-selected="false"
                        >
                            <i
                                class="ph-fill ph-package"
                                aria-hidden="true"
                            ></i>

                            <span>
                                Total entregue
                            </span>
                        </button>

                        <button
                            class="acc-tab"
                            type="button"
                            data-tab="distributions"
                            data-tone="cyan"
                            role="tab"
                            aria-selected="false"
                        >
                            <i
                                class="ph-fill ph-arrows-left-right"
                                aria-hidden="true"
                            ></i>

                            <span>
                                Distribuições
                            </span>
                        </button>

                        <button
                            class="acc-tab"
                            type="button"
                            data-tab="finance"
                            data-tone="success"
                            role="tab"
                            aria-selected="false"
                        >
                            <i
                                class="ph-fill ph-wallet"
                                aria-hidden="true"
                            ></i>

                            <span>
                                Financeiro
                            </span>
                        </button>

                        <button
                            class="acc-tab"
                            type="button"
                            data-tab="documents"
                            data-tone="violet"
                            role="tab"
                            aria-selected="false"
                        >
                            <i
                                class="ph-fill ph-files"
                                aria-hidden="true"
                            ></i>

                            <span>
                                Documentos
                            </span>
                        </button>

                        <button
                            class="acc-tab"
                            type="button"
                            data-tab="timeline"
                            data-tone="neutral"
                            role="tab"
                            aria-selected="false"
                        >
                            <i
                                class="ph-fill ph-clock-counter-clockwise"
                                aria-hidden="true"
                            ></i>

                            <span>
                                Histórico
                            </span>
                        </button>
                    </div>

                    <div
                        class="acc-tab-panel is-active"
                        data-panel="overview"
                    >
                        <div class="acc-detail-grid">
                            <div class="acc-detail">
                                <span>
                                    Projeto
                                </span>

                                <strong>
                                    ${esc(
                                        process.project?.title ||
                                            "Não identificado"
                                    )}
                                </strong>
                            </div>

                            <div class="acc-detail">
                                <span>
                                    Destinatário
                                </span>

                                <strong>
                                    ${esc(process.recipient.name)}
                                </strong>
                            </div>

                            <div class="acc-detail">
                                <span>
                                    Período
                                </span>

                                <strong>
                                    ${esc(process.period)}
                                </strong>
                            </div>

                            <div class="acc-detail">
                                <span>
                                    Valor bruto
                                </span>

                                <strong>
                                    ${money.format(
                                        process.financial.gross || 0
                                    )}
                                </strong>
                            </div>

                            <div class="acc-detail">
                                <span>
                                    Taxas
                                </span>

                                <strong>
                                    ${money.format(process.financial.fees || 0)}
                                </strong>
                            </div>

                            <div class="acc-detail">
                                <span>
                                    Valor líquido
                                </span>

                                <strong>
                                    ${money.format(process.financial.net || 0)}
                                </strong>
                            </div>

                            <div class="acc-detail">
                                <span>
                                    Distribuições
                                </span>

                                <strong>
                                    ${esc(process.summary.distributions)}
                                </strong>
                            </div>

                            <div class="acc-detail">
                                <span>
                                    Produtores
                                </span>

                                <strong>
                                    ${esc(process.summary.producers)}
                                </strong>
                            </div>

                            <div class="acc-detail">
                                <span>
                                    Produtos
                                </span>

                                <strong>
                                    ${esc(process.summary.products)}
                                </strong>
                            </div>

                            <div class="acc-detail">
                                <span>
                                    Unidades recebedoras
                                </span>

                                <strong>
                                    ${esc(process.summary.recipient_units)}
                                </strong>
                            </div>

                            <div class="acc-detail">
                                <span>
                                    Documentos de origem
                                </span>

                                <strong>
                                    ${esc(process.summary.source_receipts)}
                                </strong>
                            </div>
                        </div>
                    </div>

                    <div
                        class="acc-tab-panel"
                        data-panel="delivered"
                    >
                        ${renderConsolidatedLines(payload.consolidated_lines)}
                    </div>

                    <div
                        class="acc-tab-panel"
                        data-panel="distributions"
                    >
                        <div data-distributions-panel>
                            ${renderDistributions(payload.distributions)}
                        </div>
                    </div>

                    <div
                        class="acc-tab-panel"
                        data-panel="finance"
                    >
                        <div class="acc-detail-grid">
                            <div class="acc-detail">
                                <span>
                                    Total recebido
                                </span>

                                <strong>
                                    ${money.format(
                                        process.financial.received || 0
                                    )}
                                </strong>
                            </div>

                            <div class="acc-detail">
                                <span>
                                    Saldo restante
                                </span>

                                <strong>
                                    ${money.format(
                                        process.financial.remaining || 0
                                    )}
                                </strong>
                            </div>

                            <div class="acc-detail">
                                <span>
                                    Situação financeira
                                </span>

                                <strong>
                                    ${esc(process.financial.status_label)}
                                </strong>
                            </div>
                        </div>

                        <h3
                            class="acc-section-title"
                            style="margin-top:.8rem"
                        >
                            Recebimentos
                        </h3>

                        <ul class="acc-simple-list">
                            ${
                                payload.payments.length
                                    ? payload.payments
                                          .map(
                                              (payment) => `
                                                <li class="acc-simple-item">
                                                    <strong>
                                                        ${money.format(
                                                            payment.amount || 0
                                                        )}
                                                        ·
                                                        ${esc(payment.date)}
                                                    </strong>

                                                    <span>
                                                        ${esc(
                                                            payment.account ||
                                                                payment.method ||
                                                                "Sem conta informada"
                                                        )}
                                                    </span>
                                                </li>
                                            `
                                          )
                                          .join("")
                                    : `
                                        <li class="acc-simple-item">
                                            Nenhum recebimento registrado.
                                        </li>
                                    `
                            }
                        </ul>
                    </div>

                    <div
                        class="acc-tab-panel"
                        data-panel="documents"
                    >
                        <div class="acc-documents-workspace">
                            <section
                                class="acc-subsection acc-subsection-fiscal"
                            >
                                <header class="acc-subsection-head">
                                    <div class="acc-subsection-title">
                                        <span
                                            class="acc-subsection-icon acc-subsection-icon-purple"
                                        >
                                            ${phIcon("ph-file-text")}
                                        </span>

                                        <div>
                                            <h3>
                                                Emissão fiscal
                                            </h3>

                                            <p>
                                                Dados preparados para emissão externa da nota.
                                            </p>
                                        </div>
                                    </div>

                                    <div class="acc-subsection-actions">
                                        ${copyButton(
                                            [
                                                `Projeto: ${
                                                    process.project?.title ||
                                                    "Não identificado"
                                                }`,

                                                `Destinatário: ${
                                                    process.recipient.name || ""
                                                }`,

                                                `Período: ${
                                                    process.period || ""
                                                }`,

                                                `Valor bruto: ${money.format(
                                                    process.financial.gross || 0
                                                )}`,

                                                `Taxas: ${money.format(
                                                    process.financial.fees || 0
                                                )}`,

                                                `Valor líquido: ${money.format(
                                                    process.financial.net || 0
                                                )}`,

                                                `Situação fiscal: ${
                                                    process.workflow.fiscal
                                                        .label || ""
                                                }`,

                                                `Documento esperado: ${
                                                    process.workflow.fiscal
                                                        .document_type ||
                                                    "Não configurado"
                                                }`,
                                            ].join("\n"),

                                            "Copiar dados"
                                        )}

                                        ${
                                            process.pdf_url || process.workflow.fiscal.billing_sheet_url
                                                ? `
                                                    <a
                                                        class="acc-button acc-button-primary"
                                                        href="${esc(
                                                            process.pdf_url || process.workflow
                                                                .fiscal
                                                                .billing_sheet_url
                                                        )}"
                                                    >
                                                        ${phIcon("ph-printer")}

                                                        Imprimir faturamento completo
                                                    </a>
                                                `
                                                : process.workflow.fiscal
                                                      .settings_url
                                                ? `
                                                            <a
                                                                class="acc-button acc-button-primary"
                                                                href="${esc(
                                                                    process
                                                                        .workflow
                                                                        .fiscal
                                                                        .settings_url
                                                                )}"
                                                            >
                                                                ${phIcon(
                                                                    "ph-gear"
                                                                )}

                                                                Configurar folha
                                                            </a>
                                                        `
                                                : ""
                                        }
                                    </div>
                                </header>

                                <div class="acc-subsection-body">
                                    <div class="acc-metric-table">
                                        <div class="acc-metric-row">
                                            <span
                                                class="acc-metric-icon acc-tone-purple"
                                            >
                                                ${phIcon("ph-seal-check")}
                                            </span>

                                            <div class="acc-metric-copy">
                                                <span>
                                                    Situação fiscal
                                                </span>

                                                <strong>
                                                    ${esc(
                                                        process.workflow.fiscal
                                                            .label
                                                    )}
                                                </strong>
                                            </div>
                                        </div>

                                        <div class="acc-metric-row">
                                            <span
                                                class="acc-metric-icon acc-tone-blue"
                                            >
                                                ${phIcon("ph-file-search")}
                                            </span>

                                            <div class="acc-metric-copy">
                                                <span>
                                                    Documento esperado
                                                </span>

                                                <strong>
                                                    ${esc(
                                                        process.workflow.fiscal
                                                            .document_type ||
                                                            "Não configurado"
                                                    )}
                                                </strong>
                                            </div>
                                        </div>

                                        <div class="acc-metric-row">
                                            <span
                                                class="acc-metric-icon acc-tone-green"
                                            >
                                                ${phIcon(
                                                    "ph-currency-circle-dollar"
                                                )}
                                            </span>

                                            <div class="acc-metric-copy">
                                                <span>
                                                    Valor para emissão
                                                </span>

                                                <strong>
                                                    ${
                                                        process.workflow.fiscal
                                                            .expected_amount ==
                                                        null
                                                            ? "Não determinado"
                                                            : money.format(
                                                                  process
                                                                      .workflow
                                                                      .fiscal
                                                                      .expected_amount
                                                              )
                                                    }
                                                </strong>
                                            </div>
                                        </div>
                                    </div>

                                    ${
                                        process.workflow.fiscal.blocks?.length
                                            ? `
                                                <div class="acc-document-alerts">
                                                    ${process.workflow.fiscal.blocks
                                                        .map(
                                                            (block) => `
                                                                    <div class="acc-document-alert">
                                                                        <span>
                                                                            ${phIcon(
                                                                                "ph-warning-circle"
                                                                            )}
                                                                        </span>

                                                                        <div>
                                                                            <strong>
                                                                                Revisar antes de emitir
                                                                            </strong>

                                                                            <p>
                                                                                ${esc(
                                                                                    block.message
                                                                                )}
                                                                            </p>
                                                                        </div>
                                                                    </div>
                                                                `
                                                        )
                                                        .join("")}
                                                </div>
                                            `
                                            : ""
                                    }
                                </div>
                            </section>

                            ${
                                process.workflow.authorization.access
                                    ?.applicable === false &&
                                !payload.authorizations.length
                                    ? ""
                                    : `
                                        <section
                                            class="acc-subsection acc-subsection-authorization"
                                        >
                                            <header class="acc-subsection-head">
                                                <div class="acc-subsection-title">
                                                    <span
                                                        class="acc-subsection-icon acc-subsection-icon-blue"
                                                    >
                                                        ${phIcon(
                                                            "ph-seal-check"
                                                        )}
                                                    </span>

                                                    <div>
                                                        <h3>
                                                            Autorização da compradora
                                                        </h3>

                                                        <p>
                                                            Controle opcional de ciência
                                                            e validação pela organização.
                                                        </p>
                                                    </div>
                                                </div>

                                                ${
                                                    root.dataset
                                                        .canSendAuthorization ===
                                                        "1" &&
                                                    [
                                                        "legacy_unsubmitted",
                                                        "cancelled",
                                                    ].includes(
                                                        process.workflow
                                                            .authorization.state
                                                    )
                                                        ? `
                                                            <button
                                                                class="acc-button"
                                                                type="button"
                                                                data-send-authorization
                                                            >
                                                                ${phIcon(
                                                                    "ph-paper-plane-tilt"
                                                                )}

                                                                Enviar autorização
                                                            </button>
                                                        `
                                                        : ""
                                                }
                                            </header>

                                            <div class="acc-subsection-body">
                                                ${renderAuthorizationAccess(
                                                    process.workflow
                                                        .authorization
                                                )}

                                                <div
                                                    class="acc-action-feedback"
                                                    data-authorization-feedback
                                                    aria-live="polite"
                                                ></div>

                                                ${renderAuthorizations(
                                                    payload.authorizations
                                                )}
                                            </div>
                                        </section>
                                    `
                            }

                            <section
                                class="acc-subsection acc-subsection-source"
                            >
                                <header class="acc-subsection-head">
                                    <div class="acc-subsection-title">
                                        <span
                                            class="acc-subsection-icon acc-subsection-icon-violet"
                                        >
                                            ${phIcon("ph-receipt")}
                                        </span>

                                        <div>
                                            <h3>
                                                Documentos de origem
                                            </h3>

                                            <p>
                                                Comprovantes dos produtores
                                                vinculados às distribuições.
                                            </p>
                                        </div>
                                    </div>

                                    <span class="acc-section-counter">
                                        ${esc(
                                            process.summary.source_receipts || 0
                                        )}
                                    </span>
                                </header>

                                <div
                                    class="acc-subsection-body acc-subsection-body-flush"
                                >
                                    ${renderProducerReceipts(
                                        payload.producer_receipts
                                    )}
                                </div>
                            </section>
                        </div>
                    </div>

                    <div
                        class="acc-tab-panel"
                        data-panel="timeline"
                    >
                        <ul class="acc-simple-list">
                            ${
                                payload.timeline.length
                                    ? payload.timeline
                                          .map(
                                              (event) => `
                                                <li class="acc-simple-item">
                                                    <strong>
                                                        ${esc(
                                                            event.description
                                                        )}
                                                    </strong>

                                                    <span>
                                                        ${esc(event.date)}
                                                        ·
                                                        ${esc(event.actor)}
                                                    </span>
                                                </li>
                                            `
                                          )
                                          .join("")
                                    : `
                                        <li class="acc-simple-item">
                                            Nenhum evento registrado.
                                        </li>
                                    `
                            }
                        </ul>
                    </div>
                </section>

                <aside class="acc-side-stack">
                    <section class="acc-panel acc-side-panel">
                        <div class="acc-panel-head acc-panel-head-workspace">
                            <div class="acc-panel-title-workspace">
                                <span
                                    class="acc-panel-icon acc-panel-icon-green"
                                >
                                    ${phIcon("ph-arrow-circle-right")}
                                </span>

                                <div>
                                    <h2>
                                        Próxima ação
                                    </h2>

                                    <p>
                                        ${esc(process.state.next_action)}
                                    </p>
                                </div>
                            </div>

                            ${badge(process.state.label, process.state.tone)}
                        </div>

                        <div
                            class="acc-action-box"
                            data-authorization-action
                        >
                            ${
                                process.edit_url
                                    ? `
                                        <a
                                            class="acc-button acc-button-primary"
                                            href="${esc(process.edit_url)}"
                                        >
                                            ${phIcon("ph-pencil-line")}

                                            Continuar e conferir faturamento
                                        </a>

                                        <span class="acc-muted">
                                            Revise as entregas e os valores
                                            antes de fechar.
                                        </span>
                                    `
                                    : process.pdf_url || process.workflow.fiscal.billing_sheet_url
                                    ? `
                                                <a
                                                    class="acc-button acc-button-primary"
                                                    href="${esc(
                                                        process.pdf_url || process.workflow.fiscal
                                                            .billing_sheet_url
                                                    )}"
                                                >
                                                    ${phIcon("ph-printer")}

                                                    Imprimir faturamento completo
                                                </a>
                                            `
                                    : ""
                            }
                        </div>
                    </section>

                    <section class="acc-panel acc-side-panel">
                        <div class="acc-panel-head acc-panel-head-workspace">
                            <div class="acc-panel-title-workspace">
                                <span
                                    class="acc-panel-icon acc-panel-icon-amber"
                                >
                                    ${phIcon("ph-checks")}
                                </span>

                                <div>
                                    <h2>
                                        Conferência dos dados
                                    </h2>

                                    <p>
                                        ${
                                            integrity.critical_count
                                                ? `${esc(
                                                      integrity.critical_count
                                                  )} erro(s) de integridade`
                                                : integrity.preparation_count
                                                ? `${esc(
                                                      integrity.preparation_count
                                                  )} item(ns) a completar`
                                                : "Dados prontos para avançar"
                                        }
                                    </p>
                                </div>
                            </div>

                            ${badge(
                                integrity.critical_count
                                    ? "Corrigir erro"
                                    : integrity.preparation_count
                                    ? "Em preparação"
                                    : "Pronto",

                                integrity.critical_count
                                    ? "danger"
                                    : integrity.preparation_count
                                    ? "warning"
                                    : "success"
                            )}
                        </div>

                        <div class="acc-side-panel-body">
                            <ul class="acc-integrity-list">
                                ${
                                    integrity.issues.length
                                        ? integrity.issues
                                              .map(
                                                  (issue) => `
                                                    <li class="acc-integrity-item">
                                                        ${badge(
                                                            issue.severity ===
                                                                "critical"
                                                                ? "Erro"
                                                                : "Preparação",

                                                            issue.severity ===
                                                                "critical"
                                                                ? "danger"
                                                                : "warning"
                                                        )}

                                                        ${esc(issue.message)}
                                                    </li>
                                                `
                                              )
                                              .join("")
                                        : `
                                            <li class="acc-simple-item">
                                                Nenhuma pendência encontrada.
                                            </li>
                                        `
                                }
                            </ul>
                            ${
                                integrity.repair_url
                                    ? `
                                        <button class="acc-button" type="button" data-repair-integrity data-url="${esc(
                                            integrity.repair_url
                                        )}">
                                            ${phIcon("ph-wrench")}
                                            Corrigir vínculos automaticamente
                                        </button>
                                        <div class="acc-action-feedback" data-integrity-feedback aria-live="polite"></div>
                                    `
                                    : ""
                            }
                        </div>
                    </section>

                    <section class="acc-panel acc-side-panel">
                        <div class="acc-panel-head acc-panel-head-workspace">
                            <div class="acc-panel-title-workspace">
                                <span
                                    class="acc-panel-icon acc-panel-icon-purple"
                                >
                                    ${phIcon("ph-files")}
                                </span>

                                <div>
                                    <h2>
                                        Arquivos anexados
                                    </h2>

                                    <p>
                                        ${esc(payload.documents.length)}
                                        arquivo(s)
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="acc-side-panel-body">
                            <ul class="acc-simple-list acc-document-list">
                                ${
                                    payload.documents.length
                                        ? payload.documents
                                              .map(
                                                  (document) => `
                                                    <li
                                                        class="acc-simple-item acc-document-row"
                                                    >
                                                        <span
                                                            class="acc-document-row-icon"
                                                        >
                                                            ${phIcon("ph-file")}
                                                        </span>

                                                        <div>
                                                            <strong>
                                                                ${esc(
                                                                    document.name
                                                                )}
                                                            </strong>

                                                            <span>
                                                                ${esc(
                                                                    document.category
                                                                )}
                                                                ·
                                                                ${esc(
                                                                    document.date
                                                                )}
                                                            </span>
                                                        </div>
                                                    </li>
                                                `
                                              )
                                              .join("")
                                        : `
                                            <li class="acc-simple-item">
                                                Nenhum documento anexado.
                                            </li>
                                        `
                                }
                            </ul>
                        </div>
                    </section>
                </aside>
            `;

            target.querySelectorAll("[data-tab]").forEach((button) =>
                button.addEventListener("click", () => {
                    target.querySelectorAll("[data-tab]").forEach((tab) => {
                        const active = tab === button;

                        tab.classList.toggle("is-active", active);

                        tab.setAttribute(
                            "aria-selected",
                            active ? "true" : "false"
                        );

                        if (active) {
                            tab.setAttribute("aria-current", "page");
                        } else {
                            tab.removeAttribute("aria-current");
                        }
                    });

                    target.querySelectorAll("[data-panel]").forEach((panel) =>
                        panel.classList.toggle(
                            "is-active",

                            panel.dataset.panel === button.dataset.tab
                        )
                    );

                    if (window.innerWidth < 760) {
                        button.scrollIntoView({
                            behavior: "smooth",

                            inline: "center",

                            block: "nearest",
                        });
                    }
                })
            );

            const accessForm = target.querySelector("[data-access-form]");

            accessForm?.addEventListener("submit", async (event) => {
                event.preventDefault();

                const button = accessForm.querySelector(
                    'button[type="submit"]'
                );

                const feedback = accessForm.querySelector(
                    "[data-access-feedback]"
                );

                button.disabled = true;

                feedback.textContent = "Salvando...";

                try {
                    const result = await postJson(
                        root.dataset.authorizationAccessUrl,

                        Object.fromEntries(new FormData(accessForm).entries())
                    );

                    feedback.textContent = result.message;

                    await initDossier();
                } catch (error) {
                    feedback.textContent = error.message;

                    feedback.classList.add("is-error");

                    button.disabled = false;
                }
            });

            const sendButton = target.querySelector(
                "[data-send-authorization]"
            );

            const repairButton = target.querySelector("[data-repair-integrity]");

            repairButton?.addEventListener("click", async () => {
                const feedback = target.querySelector("[data-integrity-feedback]");

                repairButton.disabled = true;
                feedback.textContent = "Verificando e corrigindo vínculos...";

                try {
                    const result = await postJson(repairButton.dataset.url, {});

                    feedback.textContent = result.message;
                    await initDossier();
                } catch (error) {
                    feedback.textContent = error.message;
                    feedback.classList.add("is-error");
                    repairButton.disabled = false;
                }
            });

            sendButton?.addEventListener("click", async () => {
                if (
                    !window.confirm(
                        `Enviar esta cobrança para autorização?\n\nValor: ${money.format(
                            process.financial.net || 0
                        )}\nPeríodo: ${process.period}`
                    )
                ) {
                    return;
                }

                const feedback = target.querySelector(
                    "[data-authorization-feedback]"
                );

                sendButton.disabled = true;

                feedback.textContent = "Enviando...";

                try {
                    await postJson(
                        root.dataset.authorizationSendUrl,

                        {
                            operation_key: crypto.randomUUID(),
                        }
                    );

                    feedback.textContent = "Cobrança enviada.";

                    await initDossier();
                } catch (error) {
                    feedback.textContent = error.message;

                    feedback.classList.add("is-error");

                    sendButton.disabled = false;
                }
            });

            const bindDistributionPagination = () =>
                target.querySelectorAll("[data-dist-page]").forEach((button) =>
                    button.addEventListener("click", async () => {
                        const panel = target.querySelector(
                            "[data-distributions-panel]"
                        );

                        if (!panel) {
                            return;
                        }

                        panel.innerHTML = skeletons(3);

                        try {
                            const pagePayload = await getJson(
                                filtersUrl(
                                    root.dataset.processDataUrl,

                                    {
                                        distributions_page:
                                            button.dataset.distPage,
                                    }
                                )
                            );

                            panel.innerHTML = renderDistributions(
                                pagePayload.distributions
                            );

                            bindDistributionPagination();
                        } catch (error) {
                            showError(panel, error);
                        }
                    })
                );

            bindDistributionPagination();

            const bindProducerPagination = () =>
                target
                    .querySelectorAll("[data-producer-page]")
                    .forEach((button) =>
                        button.addEventListener("click", async () => {
                            const panel = target.querySelector(
                                '[data-panel="documents"]'
                            );

                            const sourceBody = panel?.querySelector(
                                ".acc-subsection-source .acc-subsection-body"
                            );

                            if (!sourceBody) {
                                return;
                            }

                            sourceBody.innerHTML = skeletons(3);

                            try {
                                const pagePayload = await getJson(
                                    filtersUrl(
                                        root.dataset.processDataUrl,

                                        {
                                            producer_receipts_page:
                                                button.dataset.producerPage,
                                        }
                                    )
                                );

                                sourceBody.innerHTML = renderProducerReceipts(
                                    pagePayload.producer_receipts
                                );

                                bindProducerPagination();
                                bindRelatedReceipts();
                            } catch (error) {
                                showError(sourceBody, error);
                            }
                        })
                    );

            const bindRelatedReceipts = () =>
                target
                    .querySelectorAll("[data-related-detail]")
                    .forEach((button) =>
                        button.addEventListener("click", () =>
                            openRelatedReceipt(
                                button.dataset.relatedDetail,

                                button.dataset.relatedTitle
                            )
                        )
                    );

            bindProducerPagination();
            bindRelatedReceipts();
        } catch (error) {
            showError(target, error);
        }
    }

    /* =========================================================
       SOURCE RECEIPT DETAIL
       ========================================================= */

    function renderSourceReceiptDetail(payload) {
        const receipt = payload.receipt;

        const rows = payload.distributions || [];

        const processes = payload.processes || [];

        return `
            <div class="acc-detail-grid acc-receipt-summary">
                <div class="acc-detail">
                    <span>
                        Produtor
                    </span>

                    <strong>
                        ${esc(receipt.member)}
                    </strong>
                </div>

                <div class="acc-detail">
                    <span>
                        Projeto
                    </span>

                    <strong>
                        ${esc(receipt.project)}
                    </strong>
                </div>

                <div class="acc-detail">
                    <span>
                        Situação
                    </span>

                    <strong>
                        ${esc(receipt.status_label)}
                    </strong>
                </div>
            </div>

            ${
                rows.length
                    ? `
                        <div class="acc-table-wrap acc-detail-table">
                            <table class="acc-table">
                                <thead>
                                    <tr>
                                        <th>
                                            Data
                                        </th>

                                        <th>
                                            Produto e destino
                                        </th>

                                        <th>
                                            Quantidade
                                        </th>

                                        <th>
                                            Preço
                                        </th>

                                        <th>
                                            Valor
                                        </th>

                                        <th>
                                            Faturamento
                                        </th>
                                    </tr>
                                </thead>

                                <tbody>
                                    ${rows
                                        .map(
                                            (row) => `
                                                    <tr>
                                                        <td>
                                                            ${esc(
                                                                row.date || "—"
                                                            )}
                                                        </td>

                                                        <td>
                                                            <strong>
                                                                ${esc(
                                                                    row.product
                                                                )}
                                                            </strong>

                                                            <div class="acc-muted">
                                                                ${esc(
                                                                    row.customer
                                                                )}
                                                            </div>
                                                        </td>

                                                        <td>
                                                            ${quantity.format(
                                                                row.quantity ||
                                                                    0
                                                            )}

                                                            ${esc(row.unit)}
                                                        </td>

                                                        <td>
                                                            ${money.format(
                                                                row.unit_price ||
                                                                    0
                                                            )}
                                                        </td>

                                                        <td class="acc-money">
                                                            ${money.format(
                                                                row.gross || 0
                                                            )}
                                                        </td>

                                                        <td>
                                                            ${
                                                                row.billing_receipt_id
                                                                    ? `Processo #${esc(
                                                                          row.billing_receipt_id
                                                                      )}`
                                                                    : "Ainda não faturada"
                                                            }
                                                        </td>
                                                    </tr>
                                                `
                                        )
                                        .join("")}
                                </tbody>
                            </table>
                        </div>
                    `
                    : `
                        <div class="acc-empty">
                            Este comprovante não possui distribuições vinculadas.
                        </div>
                    `
            }

            <section class="acc-related-processes">
                <h3>
                    Processos contábeis relacionados
                </h3>

                ${
                    processes.length
                        ? `
                            <ul class="acc-simple-list">
                                ${processes
                                    .map(
                                        (process) => `
                                                <li class="acc-simple-item">
                                                    <a
                                                        class="acc-link"
                                                        href="${esc(
                                                            process.url
                                                        )}"
                                                    >
                                                        ${esc(process.number)}
                                                    </a>

                                                    <span>
                                                        ${esc(process.status)}
                                                    </span>
                                                </li>
                                            `
                                    )
                                    .join("")}
                            </ul>
                        `
                        : `
                            <p class="acc-muted">
                                Nenhuma distribuição deste comprovante
                                entrou em um processo contábil.
                            </p>
                        `
                }
            </section>
        `;
    }

    /* =========================================================
       SOURCE RECEIPTS PAGE
       ========================================================= */

    function sourceReceiptCard(receipt) {
        return `
            <article class="acc-receipt-card">
                <div class="acc-receipt-card-main">
                    <div>
                        <button
                            class="acc-link acc-link-button"
                            type="button"
                            data-source-receipt="${esc(receipt.id)}"
                            data-detail-url="${esc(receipt.detail_url)}"
                        >
                            ${esc(receipt.number)}
                        </button>

                        <div class="acc-muted">
                            ${esc(receipt.member)}
                            ·
                            ${esc(receipt.project)}
                        </div>
                    </div>

                    ${badge(
                        receipt.status_label,

                        receipt.status === "paid"
                            ? "success"
                            : receipt.status === "obsolete"
                            ? "danger"
                            : "warning"
                    )}
                </div>

                <div class="acc-receipt-meta">
                    <span>
                        Emissão

                        <strong>
                            ${esc(receipt.issued_at || "—")}
                        </strong>
                    </span>

                    <span>
                        Distribuições

                        <strong>
                            ${esc(receipt.distribution_count)}
                        </strong>
                    </span>

                    <span>
                        Valor do comprovante

                        <strong>
                            ${money.format(receipt.total_net || 0)}
                        </strong>
                    </span>

                    <span>
                        Referência QR

                        <strong>
                            ${esc(receipt.reference_code || "Não gerada")}
                        </strong>
                    </span>
                </div>

                <div class="acc-receipt-actions">
                    <button
                        class="acc-button"
                        type="button"
                        data-source-receipt="${esc(receipt.id)}"
                        data-detail-url="${esc(receipt.detail_url)}"
                    >
                        ${phIcon("ph-tree-structure")}

                        Ver entregas
                    </button>

                    ${
                        receipt.reprint_url
                            ? `
                                <a
                                    class="acc-button"
                                    href="${esc(receipt.reprint_url)}"
                                    target="_blank"
                                    rel="noopener"
                                >
                                    ${phIcon("ph-file-text")}

                                    Abrir comprovante
                                </a>
                            `
                            : ""
                    }
                </div>
            </article>
        `;
    }

    async function initSourceReceipts() {
        const form = document.querySelector("[data-source-receipt-filters]");

        if (!form) {
            return;
        }

        const results = document.querySelector("[data-source-receipt-results]");

        const pagination = document.querySelector(
            "[data-source-receipt-pagination]"
        );

        const projectSelect = form.elements.project;

        const qrDialog = document.querySelector("[data-qr-dialog]");

        const receiptDialog = document.querySelector("[data-receipt-dialog]");

        const detail = document.querySelector("[data-receipt-detail]");

        const title = document.querySelector("[data-receipt-title]");

        const video = document.querySelector("[data-scanner-video]");

        const scannerError = document.querySelector("[data-scanner-error]");

        let stream = null;

        let scanning = false;

        let detector = null;

        let optionsLoaded = false;

        let controller = null;

        const stopScanner = () => {
            scanning = false;

            stream?.getTracks().forEach((track) => track.stop());

            stream = null;

            if (video) {
                video.srcObject = null;
            }
        };

        const searchCode = (code) => {
            form.elements.search.value = String(code || "").trim();

            stopScanner();

            qrDialog?.close();

            load(1);
        };

        const scanFrame = async () => {
            if (!scanning || !detector || !video) {
                return;
            }

            try {
                const codes = await detector.detect(video);

                if (codes[0]?.rawValue) {
                    return searchCode(codes[0].rawValue);
                }
            } catch (_) {}

            if (scanning) {
                window.setTimeout(scanFrame, 180);
            }
        };

        async function openDetail(url, receiptNumber = "Comprovante") {
            if (!receiptDialog || !detail) {
                return;
            }

            if (title) {
                title.textContent = receiptNumber;
            }

            detail.innerHTML = skeletons(5);

            receiptDialog.showModal();

            try {
                detail.innerHTML = renderSourceReceiptDetail(
                    await getJson(url)
                );
            } catch (error) {
                showError(detail, error);
            }
        }

        const bindCards = () =>
            results
                ?.querySelectorAll("[data-source-receipt]")
                .forEach((button) =>
                    button.addEventListener("click", () =>
                        openDetail(
                            button.dataset.detailUrl,

                            button.textContent.trim()
                        )
                    )
                );

        async function load(pageNumber = 1) {
            controller?.abort();

            controller = new AbortController();

            results.innerHTML = skeletons(5);

            pagination.innerHTML = "";

            const params = Object.fromEntries(new FormData(form).entries());

            params.page = pageNumber;

            try {
                const payload = await getJson(
                    filtersUrl(
                        root.dataset.sourceReceiptsUrl,

                        params
                    ),

                    controller.signal
                );

                if (!optionsLoaded && projectSelect) {
                    projectSelect.innerHTML =
                        '<option value="">Todos os projetos</option>' +
                        (payload.filters?.projects || [])
                            .map(
                                (option) => `
                                    <option value="${esc(option.id)}">
                                        ${esc(option.label)}
                                    </option>
                                `
                            )
                            .join("");

                    projectSelect.value = params.project || "";

                    optionsLoaded = true;
                }

                const pageData = payload.receipts;

                results.innerHTML = pageData.data.length
                    ? pageData.data.map(sourceReceiptCard).join("")
                    : `
                            <div class="acc-empty">
                                Nenhum comprovante encontrado.
                                Confira o número ou leia novamente o QR Code.
                            </div>
                        `;

                pagination.innerHTML = `
                    <span>
                        ${esc(pageData.from || 0)}
                        –
                        ${esc(pageData.to || 0)}
                        de
                        ${esc(pageData.total || 0)}
                    </span>

                    <div class="acc-pagination-actions">
                        <button
                            class="acc-button"
                            type="button"
                            data-page="${pageData.current_page - 1}"
                            ${pageData.current_page <= 1 ? "disabled" : ""}
                        >
                            ${phIcon("ph-caret-left")}

                            Anterior
                        </button>

                        <button
                            class="acc-button"
                            type="button"
                            data-page="${pageData.current_page + 1}"
                            ${
                                pageData.current_page >= pageData.last_page
                                    ? "disabled"
                                    : ""
                            }
                        >
                            Próxima

                            ${phIcon("ph-caret-right")}
                        </button>
                    </div>
                `;

                pagination
                    .querySelectorAll("[data-page]")
                    .forEach((button) =>
                        button.addEventListener("click", () =>
                            load(Number(button.dataset.page))
                        )
                    );

                bindCards();

                const exactQrLookup =
                    params.search &&
                    (/\bCP-/i.test(params.search) ||
                        /[0-9a-f]{8}-[0-9a-f-]{27,}/i.test(params.search));

                if (exactQrLookup && pageData.data.length === 1) {
                    await openDetail(
                        pageData.data[0].detail_url,

                        pageData.data[0].number
                    );
                }
            } catch (error) {
                if (error.name !== "AbortError") {
                    showError(results, error);
                }
            }
        }

        form.addEventListener("submit", (event) => {
            event.preventDefault();

            load(1);
        });

        form.elements.status?.addEventListener("change", () => load(1));

        form.elements.project?.addEventListener("change", () => load(1));

        document
            .querySelector("[data-close-receipt]")
            ?.addEventListener("click", () => receiptDialog?.close());

        document
            .querySelector("[data-close-scanner]")
            ?.addEventListener("click", () => {
                stopScanner();

                qrDialog?.close();
            });

        qrDialog?.addEventListener("close", stopScanner);

        document
            .querySelector("[data-use-manual]")
            ?.addEventListener("click", () =>
                searchCode(
                    document.querySelector("[data-scanner-manual]")?.value
                )
            );

        document
            .querySelector("[data-open-scanner]")
            ?.addEventListener("click", async () => {
                if (!qrDialog) {
                    return;
                }

                if (scannerError) {
                    scannerError.hidden = true;
                }

                qrDialog.showModal();

                if (
                    !("BarcodeDetector" in window) ||
                    !navigator.mediaDevices?.getUserMedia
                ) {
                    if (scannerError) {
                        scannerError.textContent =
                            "A leitura automática não está disponível neste navegador. Cole o código ou link no campo abaixo.";

                        scannerError.hidden = false;
                    }

                    return;
                }

                try {
                    detector = new BarcodeDetector({
                        formats: ["qr_code"],
                    });

                    stream = await navigator.mediaDevices.getUserMedia({
                        video: {
                            facingMode: {
                                ideal: "environment",
                            },
                        },

                        audio: false,
                    });

                    video.srcObject = stream;

                    await video.play();

                    scanning = true;

                    scanFrame();
                } catch (_) {
                    if (scannerError) {
                        scannerError.textContent =
                            "Não foi possível acessar a câmera. Autorize o uso ou cole o código manualmente.";

                        scannerError.hidden = false;
                    }

                    stopScanner();
                }
            });

        load(1);
    }

    /* =========================================================
       BOOT
       ========================================================= */

    if (page === "queue") {
        loadQueue();
    }

    if (page === "processes") {
        initProcesses();
    }

    if (page === "dossier") {
        initDossier();
    }

    if (page === "source-receipts") {
        initSourceReceipts();
    }
})();
