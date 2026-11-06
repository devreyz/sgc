import "../css/pre-distributions.css";

const root = document.querySelector("[data-pre-distributions]");
if (root) initialize(root);

function initialize(root) {
    const base = root.dataset.baseUrl;
    const manager = root.dataset.manager === "1";
    const $ = (selector) => root.querySelector(selector);
    const esc = (value) =>
        String(value ?? "").replace(
            /[&<>"']/g,
            (character) =>
                ({
                    "&": "&amp;",
                    "<": "&lt;",
                    ">": "&gt;",
                    '"': "&quot;",
                    "'": "&#39;",
                }[character])
        );
    const labels = {
        draft: "Rascunho",
        completed: "Concluída",
        cancelled: "Cancelada",
    };
    const quantities = new Intl.NumberFormat("pt-BR", {
        minimumFractionDigits: 4,
        maximumFractionDigits: 4,
    });
    const money = new Intl.NumberFormat("pt-BR", {
        style: "currency",
        currency: "BRL",
    });
    const unitMoney = new Intl.NumberFormat("pt-BR", {
        style: "currency",
        currency: "BRL",
        minimumFractionDigits: 4,
        maximumFractionDigits: 4,
    });
    let priceTables = [];
    let entryCustomers = [];
    let entryContext = null;
    const destinationsBySheet = new Map();
    let entrySearchTimer;
    let entrySearchVersion = 0;
    let detailSearch = "";
    const quoteRequests = new WeakMap();
    const reconciliationLabels = {
        unreconciled: "Não conciliado",
        partial: "Parcialmente conciliado",
        reconciled: "Conciliado",
        divergent: "Com divergência",
        cancelled: "Cancelado",
    };
    let consolidationFilters = {};
    const selected = new Set();
    const dirty = new Set();
    let createDirty = false;
    const operationKeys = new Map();
    const readableDate = (value) => {
        const match = String(value ?? "")
            .slice(0, 10)
            .match(/^(\d{4})-(\d{2})-(\d{2})$/);
        return match
            ? `${match[3]}/${match[2]}/${match[1]}`
            : "Data não informada";
    };
    const sheetCode = (id) => `PD-${String(id).padStart(6, "0")}`;
    const sheetBadge = (status) =>
        `<span class="pd-badge pd-badge-${esc(
            status
        )}"><span class="pd-badge-dot"></span>${esc(
            labels[status] || status
        )}</span>`;
    function updateSelection() {
        const count = $("[data-selection-count]");
        if (!count) return;
        count.textContent = selected.size
            ? `${selected.size} planilha(s) selecionada(s)`
            : "Selecione planilhas para consolidar";
        const button = $("[data-consolidate]");
        if (button && !busy) button.disabled = selected.size === 0;
    }
    async function ask({
        title,
        description = "",
        label = "",
        value = "",
        type = "confirm",
        confirm = "Confirmar",
        options = [],
        minLength = 0,
    }) {
        const dialog = $("[data-action-dialog]");
        const form = dialog.querySelector("[data-action-form]");
        const field = dialog.querySelector("[data-action-field]");
        const choiceField = dialog.querySelector("[data-action-choice-field]");
        const input = dialog.querySelector("[data-action-input]");
        const select = dialog.querySelector("[data-action-choice]");
        dialog.querySelector("[data-action-title]").textContent = title;
        dialog.querySelector("[data-action-description]").textContent =
            description;
        dialog.querySelector("[data-action-confirm]").textContent = confirm;
        field.hidden = type !== "text" && type !== "date";
        choiceField.hidden = type !== "choice";
        input.required = !field.hidden;
        input.type = type === "date" ? "date" : "text";
        input.minLength = type === "text" ? minLength : 0;
        input.value = value;
        dialog.querySelector("[data-action-label]").textContent = label;
        select.replaceChildren(
            ...options.map(([key, text]) => {
                const element = document.createElement("option");
                element.value = key;
                element.textContent = text;
                return element;
            })
        );
        return new Promise((resolve) => {
            let settled = false;
            const finish = (answer) => {
                if (settled) return;
                settled = true;
                form.removeEventListener("submit", onSubmit);
                dialog.removeEventListener("cancel", onCancel);
                dialog.removeEventListener("close", onClose);
                dialog
                    .querySelectorAll("[data-action-cancel]")
                    .forEach((button) =>
                        button.removeEventListener("click", onCancel)
                    );
                if (dialog.open) dialog.close();
                resolve(answer);
            };
            const onSubmit = (event) => {
                event.preventDefault();
                finish(
                    type === "choice"
                        ? select.value
                        : field.hidden
                        ? true
                        : input.value.trim()
                );
            };
            const onCancel = (event) => {
                event?.preventDefault?.();
                finish(null);
            };
            const onClose = () => finish(null);
            form.addEventListener("submit", onSubmit);
            dialog.addEventListener("cancel", onCancel);
            dialog.addEventListener("close", onClose);
            dialog
                .querySelectorAll("[data-action-cancel]")
                .forEach((button) =>
                    button.addEventListener("click", onCancel)
                );
            dialog.showModal();
            if (!field.hidden) input.focus();
        });
    }

    function localDateTime(value) {
        if (!value) return "";
        const date = new Date(value);
        if (!Number.isFinite(date.getTime())) return "";
        return new Date(date.getTime() - date.getTimezoneOffset() * 60000)
            .toISOString()
            .slice(0, 16);
    }
    let active = null;
    let busy = false;
    let listPage = 1;
    let detailCustomer = "";
    let detailPending = false;

    function message(text) {
        const notice =
            $("[data-entry][open] [data-entry-message]") || $("[data-message]");
        notice.textContent = text;
        notice.hidden = !text;
    }
    function panel(name) {
        for (const key of ["list", "create", "detail"])
            $(`[data-sheet-${key}]`).hidden = key !== name;
        $("[data-consolidated]").hidden = name !== "consolidated";
    }
    function canLeave() {
        return (
            (!dirty.size && !createDirty) ||
            window.confirm(
                "Existem dados não salvos. Deseja sair e descartar essas alterações?"
            )
        );
    }
    window.addEventListener("beforeunload", (event) => {
        if (dirty.size || createDirty || busy) {
            event.preventDefault();
            event.returnValue = "";
        }
    });

    async function request(path = "", method = "GET", data = null) {
        const options = {
            method,
            cache: "no-store",
            credentials: "same-origin",
            headers: {
                Accept: "application/json",
                "X-Requested-With": "XMLHttpRequest",
            },
        };
        let signature;
        if (data) {
            signature = method + path + JSON.stringify(data);
            if (!operationKeys.has(signature))
                operationKeys.set(signature, crypto.randomUUID());
            options.headers["Content-Type"] = "application/json";
            options.headers["X-CSRF-TOKEN"] =
                document.querySelector('meta[name="csrf-token"]')?.content ||
                "";
            options.headers["Idempotency-Key"] = operationKeys.get(signature);
            options.body = JSON.stringify(data);
        }
        const response = await fetch(base + path, options);
        const result = await response.json().catch(() => ({}));
        if (!response.ok)
            throw new Error(
                Object.values(result.errors || {})
                    .flat()
                    .join("\n") ||
                    result.message ||
                    "Não foi possível concluir. Os dados digitados foram preservados."
            );
        if (signature) operationKeys.delete(signature);
        return result;
    }
    async function run(callback) {
        if (busy) return;
        busy = true;
        message("");
        const controls = [...root.querySelectorAll("button")]
            .filter((button) => !button.closest("[data-action-dialog]"))
            .map((button) => [button, button.disabled]);
        controls.forEach(([button]) => {
            button.disabled = true;
        });
        try {
            await callback();
        } catch (error) {
            message(
                error.message ||
                    "Falha de conexão. Tente novamente para reenviar a mesma operação."
            );
        } finally {
            busy = false;
            controls.forEach(([button, disabled]) => {
                if (button.isConnected) button.disabled = disabled;
            });
            updateSelection();
        }
    }
    function pageButtons(container, paginator, handler) {
        if (!container) return;
        const current = Math.max(1, Number(paginator?.current_page) || 1);
        const last = Math.max(1, Number(paginator?.last_page) || 1);
        container.replaceChildren();
        const previous = document.createElement("button");
        const next = document.createElement("button");
        previous.type = next.type = "button";
        previous.className = next.className = "pd-page-arrow";
        previous.innerHTML =
            '<i class="ph ph-arrow-left" aria-hidden="true"></i><span>Anterior</span>';
        next.innerHTML =
            '<span>Próxima</span><i class="ph ph-arrow-right" aria-hidden="true"></i>';
        previous.disabled = current <= 1;
        next.disabled = current >= last;
        previous.addEventListener("click", () =>
            run(() => handler(current - 1))
        );
        next.addEventListener("click", () => run(() => handler(current + 1)));
        const middle = document.createElement("span");
        middle.className = "pd-page-count";
        middle.textContent = `Página ${current} de ${last}`;
        container.append(previous, middle, next);
    }
    async function list(page = 1) {
        const params = new URLSearchParams(new FormData($("[data-filter]")));
        params.set("page", page);
        const result = await request("?" + params);
        listPage = page;
        const rows = result.data || [];
        $("[data-list-content]").innerHTML = rows.length
            ? rows
                  .map(
                      (sheet) => `<article class="pd-sheet">
            ${
                manager
                    ? `<label class="pd-sheet-select" title="Selecionar para consolidar"><input type="checkbox" data-select-sheet="${Number(
                          sheet.id
                      )}" aria-label="Selecionar ${esc(sheet.title)}" ${
                          selected.has(Number(sheet.id)) ? "checked" : ""
                      }><span></span></label>`
                    : ""
            }
            <span class="pd-sheet-icon"><i class="ph ph-clipboard-text" aria-hidden="true"></i></span>
            <div class="pd-sheet-info"><div class="pd-sheet-title"><strong>${esc(
                sheet.title
            )}</strong>${sheetBadge(sheet.status)}</div>
            <p class="pd-sheet-meta"><span>${sheetCode(
                sheet.id
            )}</span><span><i class="ph ph-calendar-blank" aria-hidden="true"></i>${readableDate(
                          sheet.operational_date
                      )}</span></p></div>
            <button class="pd-sheet-open" type="button" data-open-sheet="${Number(
                sheet.id
            )}">Abrir <i class="ph ph-arrow-right" aria-hidden="true"></i></button>
        </article>`
                  )
                  .join("")
            : `<div class="pd-empty"><i class="ph ph-clipboard-text" aria-hidden="true"></i><h4>Nenhuma planilha encontrada</h4><p>Tente outro filtro${
                  manager ? " ou crie uma nova planilha para começar." : "."
              }</p></div>`;
        pageButtons($("[data-list-pagination]"), result, list);
        panel("list");
        dirty.clear();
        createDirty = false;
        active = null;
        updateSelection();
    }
    function options(rows, field = "name") {
        return rows
            .map(
                (row) =>
                    `<option value="${Number(row.id)}">${esc(
                        row[field]
                    )}</option>`
            )
            .join("");
    }
    async function createPanel() {
        if (!canLeave()) return;
        const form = $("[data-create-form]");
        form.reset();
        $("[data-project-choices]").replaceChildren();
        createDirty = false;
        await loadCreateCatalog();
        dirty.clear();
        const today = new Date();
        form.elements.operational_date.value = `${today.getFullYear()}-${String(
            today.getMonth() + 1
        ).padStart(2, "0")}-${String(today.getDate()).padStart(2, "0")}`;
        panel("create");
    }
    async function loadCreateCatalog() {
        const form = $("[data-create-form]");
        const result = await request(
            "/catalog?" +
                new URLSearchParams({
                    project_q: form.elements.project_q.value,
                    operator_q: form.elements.operator_q.value,
                })
        );
        const operator = form.elements.assigned_to;
        const selectedOperator = operator.value;
        const operatorRows = [...result.operators];
        if (
            selectedOperator &&
            !operatorRows.some((row) => String(row.id) === selectedOperator)
        ) {
            operatorRows.unshift({
                id: Number(selectedOperator),
                name: operator.selectedOptions[0].textContent,
            });
        }
        operator.innerHTML =
            '<option value="">Selecione o responsável</option>' +
            options(operatorRows);
        operator.value = selectedOperator;

        const selectedProjects = new Map(
            [...form.querySelectorAll('[name="project_ids[]"]:checked')].map(
                (input) => [Number(input.value), input.dataset.title]
            )
        );
        const projects = new Map(
            result.projects.map((project) => [
                Number(project.id),
                project.title,
            ])
        );
        for (const [id, title] of selectedProjects)
            if (!projects.has(id)) projects.set(id, title);
        $("[data-project-choices]").innerHTML =
            [...projects]
                .map(
                    ([id, title]) =>
                        `<label class="pd-check"><input type="checkbox" name="project_ids[]" value="${id}" data-title="${esc(
                            title
                        )}" ${selectedProjects.has(id) ? "checked" : ""}> ${esc(
                            title
                        )}</label>`
                )
                .join("") ||
            '<p class="pd-muted">Nenhum projeto encontrado.</p>';
        $("[data-catalog-hint]").hidden =
            !result.projects_more && !result.operators_more;
        $("[data-catalog-hint]").textContent =
            "Há mais resultados. Refine a busca; suas seleções foram preservadas.";
    }
    function priceFields(item = null, showPreview = true) {
        const savedTable = priceTables.find(
            (table) =>
                Number(table.id) === Number(item?.reference_price_table_id)
        );
        const currentTable = priceTables.find(
            (table) =>
                table.active &&
                !table.deleted_at &&
                Number(table.id) === Number(item?.customer?.price_table_id)
        );
        return `<label>Tabela de preços<select name="price_choice">${
            item
                ? `<option value="keep">Manter referência salva${
                      savedTable ? ` (${esc(savedTable.name)})` : ""
                  }</option>`
                : ""
        }<option value="default">${
            currentTable
                ? `Atualizar pela tabela ${esc(currentTable.name)}`
                : "Tabela vinculada ao cliente"
        }</option><option value="manual">Referência manual</option></select></label>
            <label>Preço unitário de referência<input name="reference_price" inputmode="decimal" value="${esc(
                String(item?.reference_price ?? "").replace(".", ",")
            )}" ${item ? "" : "readonly"}></label>
            ${
                showPreview
                    ? '<p data-price-preview aria-live="polite"></p>'
                    : ""
            }`;
    }
    function pricePayload(form) {
        const choice = form.elements.price_choice.value;
        if (choice === "keep" || choice === "manual") {
            return {
                reference_price:
                    form.elements.reference_price.value
                        .trim()
                        .replace(",", ".") || null,
            };
        }
        return { price_table_id: null };
    }
    function hasEntryPrice(form) {
        const value = form.elements.reference_price.value.trim().replace(",", ".");
        return /^\d{1,14}(?:\.\d{1,4})?$/.test(value) && Number(value) > 0;
    }
    function showPricePreview(form, label = form.dataset.quoteLabel || "") {
        const value = form.elements.reference_price.value
            .trim()
            .replace(",", ".");
        const quantity = form.elements.quantity.value.trim().replace(",", ".");
        const subtotal =
            value &&
            quantity &&
            Number.isFinite(Number(value) * Number(quantity))
                ? ` · Subtotal estimado ${money.format(
                      Number(value) * Number(quantity)
                  )}`
                : "";
        if (form.matches("[data-add-item]")) {
            const valid = hasEntryPrice(form);
            form.querySelectorAll('button[type="submit"]').forEach(button => { button.disabled = !valid; });
            if (!valid && !label && form.elements.product_id.value) {
                label = "Informe um preço de referência maior que zero em Preço e mais opções.";
            }
        }
        form.querySelector(
            "[data-price-preview]"
        ).textContent = `${label}${subtotal}`;
    }
    async function refreshQuote(form) {
        const choice = form.elements.price_choice.value;
        const token = {};
        quoteRequests.set(form, token);
        const input = form.elements.reference_price;
        input.readOnly = choice !== "manual" && choice !== "keep";
        form.dataset.quoteLabel = "";
        if (!input.readOnly) {
            if (choice === "keep") input.value = input.defaultValue;
            showPricePreview(form);
            return;
        }
        input.value = "";
        const project =
            form.elements.sales_project_id?.value || form.dataset.project;
        const customer =
            form.elements.customer_id?.value || form.dataset.customer;
        const product = form.elements.product_id?.value || form.dataset.product;
        if (!project || !customer || !product) {
            showPricePreview(form);
            return;
        }
        const params = new URLSearchParams({
            project_id: project,
            customer_id: customer,
            product_id: product,
        });
        showPricePreview(form, "Consultando tabela…");
        try {
            const quote = await request("/reference-price?" + params);
            if (quoteRequests.get(form) !== token) return;
            input.value = String(quote.price ?? "").replace(".", ",");
            form.dataset.quoteLabel =
                quote.price === null
                    ? "Produto sem preço na tabela do cliente. Configure o preço ou informe uma referência manual em Preço e mais opções."
                    : quote.table_name;
            showPricePreview(form);
        } catch (error) {
            if (quoteRequests.get(form) === token)
                showPricePreview(form, error.message);
        }
    }
    function updateReferenceTotals(totals) {
        if (!totals) return;
        const note = totals.unpriced
            ? ` · ${totals.unpriced} linha(s) sem preço de referência`
            : "";
        $(
            "[data-reference-totals]"
        ).textContent = `Valor estimado das entregas ${money.format(
            Number(totals.reported)
        )}${note}`;
    }
    function updateSavedReference(form, references) {
        if (
            !references ||
            !form.elements.price_choice ||
            !(form.dataset.item in references)
        )
            return;
        quoteRequests.delete(form);
        const input = form.elements.reference_price;
        const saved = references[form.dataset.item];
        input.defaultValue = String(saved.reference_price ?? "").replace(
            ".",
            ","
        );
        const table = priceTables.find(
            (option) =>
                Number(option.id) === Number(saved.reference_price_table_id)
        );
        form.elements.price_choice.options[0].textContent = table
            ? `Manter referência salva (${table.name})`
            : "Manter referência salva";
        const unit = form.dataset.unit;
        form.querySelector("[data-saved-price]").textContent =
            saved.reference_price == null
                ? "Sem preço de referência"
                : `${unitMoney.format(
                      Number(saved.reference_price)
                  )} / ${unit} · ${
                      table?.name ||
                      (saved.reference_price_table_id
                          ? "Tabela vinculada"
                          : "Referência manual")
                  }`;
        form.elements.price_choice.value = "keep";
        refreshQuote(form);
    }
    function rowHtml(item, editable) {
        const savedTable = priceTables.find(
            (table) =>
                Number(table.id) === Number(item.reference_price_table_id)
        );
        const historical =
            item.reference_price_table_id &&
            Number(item.reference_price_table_id) !==
                Number(item.customer?.price_table_id);
        const priceSummary =
            item.reference_price === null
                ? "Sem preço de referência"
                : `${unitMoney.format(Number(item.reference_price))} / ${
                      item.unit
                  } · ${
                      savedTable?.name ||
                      (item.reference_price_table_id
                          ? "Tabela histórica"
                          : "Referência manual")
                  }${historical ? " · Referência histórica" : ""}`;
        const state = item.cancelled_at
            ? "cancelled"
            : item.reported_quantity === null
            ? "pending"
            : Number(item.reported_quantity) === 0
            ? "zero"
            : "filled";
        const stateLabel = item.cancelled_at
            ? "Cancelado"
            : item.reported_quantity === null
            ? "Pendente"
            : Number(item.reported_quantity) === 0
            ? "Não entregue"
            : "Informado";
        return `<form class="pd-item" data-item="${Number(
            item.id
        )}" data-unit="${esc(item.unit)}" data-project="${Number(
            item.sales_project_id
        )}" data-customer="${Number(item.customer_id)}" data-product="${Number(
            item.product_id
        )}">
            <div class="pd-item-core">
                <div class="pd-item-product"><div class="pd-item-primary"><strong>${esc(
                    item.product?.name || "Produto indisponível"
                )}</strong><span class="pd-mini-state pd-mini-${state}" data-row-status>${esc(
            stateLabel
        )}</span></div>
                    <p><i class="ph ph-map-pin" aria-hidden="true"></i> ${esc(
                        item.customer?.name || "Cliente indisponível"
                    )} <span class="pd-dot-separator">·</span> ${esc(
            item.project?.title || "Projeto não informado"
        )}</p>
                    ${
                        item.cancelled_at
                            ? `<small class="pd-warning">Motivo: ${esc(
                                  item.cancellation_reason
                              )}</small>`
                            : ""
                    }
                </div>
                <label class="pd-item-quantity"><span>Entregue <small>(${esc(
                    item.unit
                )})</small></span><input name="quantity" inputmode="decimal" autocomplete="off" value="${esc(
            String(item.reported_quantity ?? "").replace(".", ",")
        )}" ${editable ? "" : "disabled"} placeholder="—"></label>
                ${
                    editable
                        ? `<button class="pd-save-item" type="submit" title="Salvar esta linha"><i class="ph ph-check" aria-hidden="true"></i><span>Salvar</span></button>`
                        : ""
                }
            </div>
            <details class="pd-item-details"><summary><i class="ph ph-sliders-horizontal" aria-hidden="true"></i> Detalhes e observações <i class="ph ph-caret-down pd-caret" aria-hidden="true"></i></summary>
                <div class="pd-item-details-content">
                    <div class="pd-form-grid"><label><span>Horário da entrega</span><input name="delivered_at" type="datetime-local" value="${localDateTime(
                        item.delivered_at
                    )}" ${editable ? "" : "disabled"}></label>
                        <label class="pd-field-wide"><span>Observações</span><textarea name="notes" rows="2" maxlength="5000" ${
                            editable ? "" : "disabled"
                        }>${esc(item.notes || "")}</textarea></label></div>
                    ${
                        manager && editable
                            ? `<div class="pd-item-planning"><p class="pd-subsection-title">Planejamento e referência</p><div class="pd-form-grid"><label><span>Quantidade planejada (${esc(
                                  item.unit
                              )})</span><input name="planned_quantity" inputmode="decimal" value="${esc(
                                  String(item.planned_quantity ?? "").replace(
                                      ".",
                                      ","
                                  )
                              )}"></label>${priceFields(
                                  item
                              )}<label><span>Modalidade</span><select name="logistics_mode"><option value="unspecified" ${
                                  item.logistics_mode === "unspecified"
                                      ? "selected"
                                      : ""
                              }>Não informada</option><option value="association" ${
                                  item.logistics_mode === "association"
                                      ? "selected"
                                      : ""
                              }>Via associação</option><option value="direct" ${
                                  item.logistics_mode === "direct"
                                      ? "selected"
                                      : ""
                              }>Direta ao cliente</option></select></label></div></div>`
                            : manager
                            ? `<p class="pd-muted" data-saved-price>${esc(
                                  priceSummary
                              )}</p>`
                            : ""
                    }
                    ${
                        manager && editable
                            ? `<p class="pd-muted" data-saved-price>${esc(
                                  priceSummary
                              )}</p><button type="button" class="pd-text-danger" data-cancel-item="${Number(
                                  item.id
                              )}"><i class="ph ph-trash" aria-hidden="true"></i> Cancelar este item</button>`
                            : ""
                    }
                </div>
            </details>
        </form>`;
    }
    function rowPayload(form) {
        return {
            id: Number(form.dataset.item),
            reported_quantity:
                form.elements.quantity.value.trim().replace(",", ".") || null,
            notes: form.elements.notes.value,
            delivered_at: form.elements.delivered_at.value
                ? new Date(form.elements.delivered_at.value).toISOString()
                : null,
            ...(manager && form.elements.planned_quantity
                ? {
                      planned_quantity:
                          form.elements.planned_quantity.value
                              .trim()
                              .replace(",", ".") || null,
                      ...pricePayload(form),
                      logistics_mode: form.elements.logistics_mode.value,
                  }
                : {}),
        };
    }
    async function detail(id, page = 1) {
        if (!canLeave()) return;
        if (Number(id) !== Number(active?.id)) {
            detailCustomer = "";
            detailPending = false;
        }
        const params = new URLSearchParams({
            page,
            ...(detailCustomer ? { customer_id: detailCustomer } : {}),
            ...(detailPending ? { pending: "1" } : {}),
        });
        if (Number(active?.id) !== Number(id)) {
            detailSearch = "";
            entryContext = destinationsBySheet.get(Number(id)) || null;
        }
        if (detailSearch) params.set("q", detailSearch);
        const result = await request(`/${Number(id)}?${params}`);
        active = result.sheet;
        dirty.clear();
        priceTables = result.price_tables || [];
        const editable = active.status === "draft";
        const pageItems = result.items?.data || [];
        const pagePending = pageItems.filter(
            (item) => !item.cancelled_at && item.reported_quantity === null
        ).length;
        const pageConfirmed = pageItems.filter(
            (item) => !item.cancelled_at && item.reported_quantity !== null
        ).length;
        $("[data-sheet-detail]").innerHTML = `
            <button type="button" class="pd-link-back" data-back><i class="ph ph-arrow-left" aria-hidden="true"></i> Todas as planilhas</button>
            <header class="pd-detail-head">
                <div class="pd-detail-identity"><div class="pd-detail-subtitle"><span>${sheetCode(
                    active.id
                )}</span>${sheetBadge(
            active.status
        )}<span>Versão <span data-version>${Number(
            active.version
        )}</span></span></div>
                    <h3>${esc(
                        active.title
                    )}</h3><p><i class="ph ph-calendar-blank" aria-hidden="true"></i> ${readableDate(
            active.operational_date
        )} <span class="pd-dot-separator">·</span> ${Number(
            active.projects?.length || 0
        )} projeto(s)</p></div>
                <div class="pd-actions"><a class="btn-secondary" href="${base}/${Number(
            active.id
        )}/pdf" target="_blank" rel="noopener"><i class="ph ph-printer" aria-hidden="true"></i> Imprimir PDF</a>
                    <button class="btn-secondary" type="button" data-sheet-table-toggle aria-controls="pd-sheet-table" aria-pressed="false"><i class="ph ph-table" aria-hidden="true"></i> Ver tabela</button>
                    ${
                        manager && editable
                            ? `<button class="pd-button-quiet" type="button" data-edit-details><i class="ph ph-pencil-simple" aria-hidden="true"></i> Editar dados</button>`
                            : ""
                    }</div>
            </header>
            ${
                active.notes
                    ? `<div class="pd-note-banner"><i class="ph ph-note" aria-hidden="true"></i><p>${esc(
                          active.notes
                      )}</p></div>`
                    : ""
            }
            <section id="pd-sheet-table" data-sheet-table aria-label="Relação de produtos entregues" hidden></section>
            <div data-sheet-editor>
            <div class="pd-detail-overview">
                <div class="pd-mini-metric"><span><i class="ph ph-list-bullets" aria-hidden="true"></i> Itens nesta página</span><strong>${
                    pageItems.length
                }</strong></div>
                <div class="pd-mini-metric"><span><i class="ph ph-check-circle" aria-hidden="true"></i> Quantidades informadas</span><strong>${pageConfirmed}</strong></div>
                <div class="pd-mini-metric ${
                    pagePending ? "pd-metric-alert" : ""
                }"><span><i class="ph ph-clock" aria-hidden="true"></i> Pendentes nesta página</span><strong>${pagePending}</strong></div>
            </div>
            ${
                manager
                    ? '<div class="pd-reference-summary" data-reference-totals aria-live="polite"></div>'
                    : ""
            }
            ${
                manager && editable
                    ? `<div class="pd-entry-shortcut"><div class="pd-entry-location"><span class="pd-location-icon"><i class="ph ph-map-pin-line" aria-hidden="true"></i></span><div><span class="pd-eyebrow">Destino em uso</span><p data-active-destination></p></div></div>
                <div class="pd-actions"><button class="btn-secondary" type="button" data-set-entry-destination><i class="ph ph-arrows-left-right" aria-hidden="true"></i> Alterar destino</button><button class="btn-primary" type="button" data-open-entry><i class="ph ph-plus" aria-hidden="true"></i> Adicionar produto</button></div></div>`
                    : ""
            }
            <div class="pd-detail-section-heading"><div><h4>Produtos e quantidades</h4><p>Edite a quantidade diretamente. Abra os detalhes apenas quando precisar.</p></div></div>
            <form class="pd-detail-filters" data-detail-search>
                <label class="pd-field-search"><span>Pesquisar</span><span class="pd-input-icon"><i class="ph ph-magnifying-glass" aria-hidden="true"></i><input type="search" name="q" maxlength="100" value="${esc(
                    detailSearch
                )}" placeholder="Produto ou cliente"></span></label>
                <label><span>Destino</span><select data-detail-customer><option value="">Todos os destinos</option>${options(
                    result.destinations || []
                )}</select></label>
                <label class="pd-filter-check"><input type="checkbox" data-detail-pending ${
                    detailPending ? "checked" : ""
                }><span>Somente pendentes</span></label>
                <button class="btn-secondary" type="submit">Buscar</button>
            </form>
            <div class="pd-list-legend"><span>Produto / destino</span><span>Quantidade entregue</span><span> </span></div>
            <div data-item-list class="pd-items">${
                pageItems
                    .map((item) =>
                        rowHtml(item, editable && !item.cancelled_at)
                    )
                    .join("") ||
                '<div class="pd-empty"><i class="ph ph-package" aria-hidden="true"></i><h4>Nenhum produto nesta visualização</h4><p>Altere os filtros ou adicione um produto à planilha.</p></div>'
            }</div>
            <nav class="pd-pagination" data-item-pages aria-label="Paginação dos produtos"></nav>
            <div class="pd-detail-actions">
                <div class="pd-actions">${
                    editable
                        ? '<button class="btn-primary" type="button" data-save-changed><i class="ph ph-floppy-disk" aria-hidden="true"></i> Salvar alterações <span class="pd-unsaved-count" data-unsaved-count hidden></span></button>'
                        : ""
                }
                    ${
                        editable
                            ? '<button class="btn-secondary" type="button" data-transition="complete"><i class="ph ph-check-circle" aria-hidden="true"></i> Concluir planilha</button>'
                            : active.status === "completed"
                            ? '<button class="btn-secondary" type="button" data-transition="request_correction">Solicitar correção</button>'
                            : ""
                    }</div>
                <div class="pd-actions"><button type="button" class="pd-button-quiet" data-history><i class="ph ph-clock-counter-clockwise" aria-hidden="true"></i> Histórico</button>
                    ${
                        manager && active.status === "completed"
                            ? '<button type="button" class="pd-button-quiet" data-transition="reopen">Reabrir</button>'
                            : ""
                    }
                    ${
                        manager && active.status !== "cancelled"
                            ? '<button type="button" class="pd-text-danger" data-transition="cancel">Cancelar planilha</button>'
                            : ""
                    }</div>
            </div>
            <div data-history-content class="pd-subcontent"></div>
            ${
                manager
                    ? `<div class="pd-secondary-actions"><button type="button" data-duplicate><i class="ph ph-copy" aria-hidden="true"></i> Duplicar planilha</button><button type="button" data-reconciliation><i class="ph ph-git-merge" aria-hidden="true"></i> Ver conciliação</button></div><div data-extra-content class="pd-subcontent"></div>`
                    : ""
            }
            </div>
            ${
                manager && editable
                    ? `<dialog class="pd-entry-dialog" data-entry aria-labelledby="pd-entry-title">
                <div class="pd-entry-shell"><header class="pd-entry-header"><button class="pd-icon-button" type="button" data-entry-back aria-label="Voltar"><i class="ph ph-arrow-left" aria-hidden="true"></i></button><div><span class="pd-eyebrow">Cadastro rápido</span><h3 id="pd-entry-title">Adicionar produto</h3></div><button class="pd-icon-button" type="button" data-entry-close aria-label="Fechar"><i class="ph ph-x" aria-hidden="true"></i></button></header>
                <div class="pd-entry-progress"><span data-progress-destination>Destino</span><i class="ph ph-caret-right" aria-hidden="true"></i><span data-progress-product>Produto</span><i class="ph ph-caret-right" aria-hidden="true"></i><span data-progress-quantity>Quantidade</span></div>
                <p class="pd-entry-step-description" data-entry-step-label></p><p class="pd-entry-message" data-entry-message role="alert" hidden></p>
                <form data-add-item>
                    <section data-entry-step="destination" class="pd-entry-step">
                        <div data-entry-context-fields class="pd-entry-step">
                            <label><span>Projeto</span><select name="sales_project_id" required>${options(
                                active.projects || [],
                                "title"
                            )}</select></label>
                            <label><span>Tipo de destino</span><select name="destination_type"><option value="customer">Cliente</option><option value="organization">Organização de clientes</option></select></label>
                            <label data-entry-organization hidden><span>Organização</span><select name="organization_id"><option value="">Selecione a organização</option></select></label>
                        </div>
                        <div data-entry-client-fields class="pd-entry-step"><p class="pd-muted" data-entry-organization-name hidden></p>
                            <label><span>Encontrar cliente</span><input name="customer_search" type="search" placeholder="Busque uma escola ou cliente"></label>
                            <label><span>Cliente que recebeu</span><select name="customer_id"></select></label></div>
                        <button class="btn-primary pd-entry-primary" type="button" data-entry-next>Continuar <i class="ph ph-arrow-right" aria-hidden="true"></i></button>
                    </section>
                    <section data-entry-step="product" class="pd-entry-step" hidden>
                        <div class="pd-destination-context"><span><i class="ph ph-map-pin" aria-hidden="true"></i> <span data-entry-destination></span></span><button class="pd-button-quiet" type="button" data-entry-change-customer hidden>Trocar cliente</button></div>
                        <label><span>Buscar produto</span><input name="search" type="search" maxlength="80" autocomplete="off" placeholder="Digite o nome do produto"></label>
                        <button class="pd-button-quiet" type="button" data-toggle-loose-product aria-pressed="false">Adicionar produto avulso</button>
                        <div data-product-results class="pd-product-results" aria-live="polite"></div>
                    </section>
                    <input type="hidden" name="product_id">
                    <section data-entry-step="quantity" class="pd-entry-step" hidden>
                        <button class="pd-button-quiet" type="button" data-entry-change-customer hidden>Trocar cliente</button>
                        <div class="pd-selected-product"><span class="pd-location-icon"><i class="ph ph-package" aria-hidden="true"></i></span><strong data-entry-product></strong></div>
                        <label class="pd-quantity-large"><span data-quantity-label>Quantidade entregue</span><input name="quantity" inputmode="decimal" autocomplete="off" placeholder="Ex.: 12,5"></label>
                        <p class="pd-entry-tip">Deixe vazio para pendente. Digite <strong>0</strong> quando não houve entrega.</p>
                        <p class="pd-price-preview" data-price-preview aria-live="polite"></p>
                        <div class="pd-entry-buttons"><button class="btn-primary" type="submit">Salvar produto</button><button class="btn-secondary" type="submit" data-save-add-another>Salvar e adicionar outro</button></div>
                        <button class="pd-button-quiet" type="button" data-entry-options><i class="ph ph-sliders-horizontal" aria-hidden="true"></i> Preço e mais opções</button>
                    </section>
                    <section data-entry-step="options" class="pd-entry-step" hidden>
                        ${priceFields(null, false)}
                        <label><span>Quantidade prevista</span><input name="planned_quantity" inputmode="decimal"></label>
                        <label><span>Modalidade da entrega</span><select name="logistics_mode"><option value="unspecified">Não informada</option><option value="association">Via associação</option><option value="direct">Direta ao cliente</option></select></label>
                        <button class="btn-primary pd-entry-primary" type="button" data-entry-done>Continuar para salvar <i class="ph ph-arrow-right" aria-hidden="true"></i></button>
                    </section>
                </form></div></dialog>`
                    : ""
            }`;
        pageButtons($("[data-item-pages]"), result.items, (number) =>
            detail(id, number)
        );
        panel("detail");
        $("[data-detail-customer]").value = detailCustomer;
        updateReferenceTotals(result.reference_totals);
        updateDestinationLabel();
    }

    async function toggleSheetTable(button) {
        const editor = $("[data-sheet-editor]");
        const panel = $("[data-sheet-table]");
        if (!panel.hidden) {
            panel.hidden = true;
            editor.hidden = false;
            button.setAttribute("aria-pressed", "false");
            button.innerHTML = '<i class="ph ph-table" aria-hidden="true"></i> Ver tabela';
            return;
        }
        if (dirty.size) throw new Error("Salve as alterações antes de abrir a tabela de conferência.");

        const {groups, show_prices: showPrices} = await request(`/${active.id}/table`);
        panel.innerHTML = `<div class="pd-detail-section-heading"><div><h4>Relação de produtos entregues</h4><p>Quantidades salvas, agrupadas por projeto e destino.</p></div></div>` +
            (groups.map(group => `<section class="pd-sheet-table-group">
                <header><span>${esc(group.recipient_label)} <strong>${esc(group.organization)}</strong></span><span>Projeto <strong>${esc(group.project)}</strong></span></header>
                ${group.tables.map(table => sheetReportTable(table, showPrices)).join("")}
            </section>`).join("") || '<p class="pd-empty">Nenhuma quantidade entregue para conferência nesta planilha.</p>');
        editor.hidden = true;
        panel.hidden = false;
        button.setAttribute("aria-pressed", "true");
        button.innerHTML = '<i class="ph ph-list-bullets" aria-hidden="true"></i> Voltar à lista de produtos';
    }

    function sheetReportTable(table, showPrices) {
        const rows = table.rows.map(row => `<tr>
            <th scope="row">${esc(row.product)}</th>
            ${table.customers.map(customer => `<td>${row.by_customer[customer.id] == null ? '—' : quantities.format(Number(row.by_customer[customer.id]))}</td>`).join("")}
            <td>${quantities.format(Number(row.quantity))} ${esc(row.unit)}</td>
            ${showPrices ? `<td>${row.price === null ? '—' : unitMoney.format(Number(row.price))}</td><td>${row.amount === null ? '—' : money.format(Number(row.amount))}</td>` : ""}
        </tr>`).join("");
        const footer = showPrices && table.rows.some(row => row.amount !== null)
            ? `<tfoot><tr><th scope="row" colspan="${table.customers.length + 3}">Total</th><td>${money.format(Number(table.total))}</td></tr></tfoot>` : "";
        return `<div class="pd-table-wrap pd-sheet-report-wrap" tabindex="0" role="region" aria-label="Tabela de entregas">
            <table class="pd-table pd-sheet-report"><thead><tr><th scope="col">Produto</th>
                ${table.customers.map(customer => `<th scope="col">${esc(customer.name)}</th>`).join("")}
                <th scope="col">Qtd. total</th>${showPrices ? '<th scope="col">Preço ref.</th><th scope="col">Total estimado</th>' : ""}
            </tr></thead><tbody>${rows}</tbody>${footer}</table></div>`;
    }

    function updateDestinationLabel() {
        const label = $("[data-active-destination]");
        if (!label) return;
        label.textContent = entryContext
            ? `${entryContext.name}${
                  entryContext.customerName
                      ? " / " + entryContext.customerName
                      : ""
              } · ${entryContext.projectName}`
            : "Nenhum destino escolhido. Selecione ao adicionar o primeiro produto.";
        $("[data-set-entry-destination]").textContent = entryContext
            ? "Trocar destino"
            : "Definir destino";
    }

    function configureEntryDestination(form, configuring) {
        const organization =
            form.elements.destination_type.value === "organization";
        $("[data-entry-context-fields]").hidden = !configuring;
        $("[data-entry-organization]").hidden = !organization;
        $("[data-entry-client-fields]").hidden = configuring && organization;
        const organizationName = $("[data-entry-organization-name]");
        organizationName.hidden = configuring || !organization;
        organizationName.textContent = organizationName.hidden
            ? ""
            : entryContext?.name || "";
        $("[data-entry-next]").textContent = configuring
            ? "Confirmar destino"
            : "Escolher produto";
        form.elements.organization_id.required = configuring && organization;
        form.elements.customer_id.required = !configuring || !organization;
    }

    function entryStep(step) {
        const dialog = $("[data-entry]");
        dialog.dataset.step = step;
        dialog.querySelectorAll("[data-entry-step]").forEach((section) => {
            section.hidden = section.dataset.entryStep !== step;
        });
        const form = $("[data-add-item]");
        const target = {
            destination:
                dialog.dataset.configuring === "1"
                    ? "sales_project_id"
                    : "customer_id",
            product: "search",
            quantity: "quantity",
            options: "price_choice",
        }[step];
        const descriptions = {
            destination: "Escolha o projeto e o destino da entrega.",
            product: "Selecione o produto para este destino.",
            quantity: "Informe o que foi entregue.",
            options: "Ajuste os dados opcionais do registro.",
        };
        const label = dialog.querySelector("[data-entry-step-label]");
        if (label) label.textContent = descriptions[step];
        for (const key of ["destination", "product", "quantity"]) {
            const indicator = dialog.querySelector(`[data-progress-${key}]`);
            if (indicator)
                indicator.classList.toggle(
                    "is-active",
                    key === (step === "options" ? "quantity" : step)
                );
        }
        form.elements[target].focus();
    }

    function closeEntry(restoreHistory = true) {
        const dialog = $("[data-entry]");
        if (!dialog?.open) return;
        clearTimeout(entrySearchTimer);
        entrySearchVersion++;
        dialog.close();
        if (restoreHistory && history.state?.preDistributionEntry)
            history.back();
    }

    window.addEventListener("popstate", () => {
        closeEntry(false);
    });

    async function openEntry(configuring = false, keepHistory = false) {
        if (dirty.size)
            throw new Error(
                "Salve as linhas alteradas antes de registrar outro produto."
            );
        const dialog = $("[data-entry]");
        const form = $("[data-add-item]");
        form.reset();
        delete form.dataset.loadedProject;
        delete form.dataset.looseProduct;
        const looseToggle = form.querySelector("[data-toggle-loose-product]");
        looseToggle.setAttribute("aria-pressed", "false");
        looseToggle.textContent = "Adicionar produto avulso";
        form.dataset.pricedCustomer = "";
        if (entryContext) {
            form.elements.sales_project_id.value = entryContext.project;
            form.elements.destination_type.value = entryContext.type;
        }
        const needsConfiguration = configuring || !entryContext;
        dialog.dataset.intent = configuring ? "set" : "add";
        dialog.dataset.configuring = needsConfiguration ? "1" : "0";
        configureEntryDestination(form, needsConfiguration);
        if (!keepHistory)
            history.pushState(
                { ...history.state, preDistributionEntry: true },
                ""
            );
        dialog.showModal();
        entryStep("destination");
        dialog.oncancel = (event) => {
            event.preventDefault();
            closeEntry();
        };
        await loadProducts();
        if (!dialog.open || needsConfiguration) return;
        if (
            entryContext.type === "customer" &&
            form.elements.customer_id.value === entryContext.customer
        ) {
            openProductSearch(form);
        } else if (
            entryContext.type === "organization" &&
            form.elements.organization_id.value === entryContext.organization
        ) {
            if (form.elements.customer_id.value) openProductSearch(form);
            else form.elements.customer_id.focus();
        } else {
            closeEntry();
            throw new Error(
                "O destino escolhido não está mais disponível. Defina outro destino."
            );
        }
    }

    function confirmEntryDestination(form) {
        const organization =
            form.elements.destination_type.value === "organization";
        const destination = organization
            ? form.elements.organization_id
            : form.elements.customer_id;
        if (
            !form.elements.sales_project_id.reportValidity() ||
            !destination.reportValidity()
        )
            return;
        if (form.dataset.loadedProject !== form.elements.sales_project_id.value)
            return;
        entryContext = {
            type: form.elements.destination_type.value,
            project: form.elements.sales_project_id.value,
            projectName:
                form.elements.sales_project_id.selectedOptions[0].textContent,
            name: destination.selectedOptions[0].textContent,
            organization: organization ? destination.value : "",
            customer: organization ? "" : destination.value,
        };
        destinationsBySheet.set(Number(active.id), entryContext);
        updateDestinationLabel();
        if ($("[data-entry]").dataset.intent === "set") {
            closeEntry();
            $("[data-open-entry]")?.focus();
            return;
        }
        $("[data-entry]").dataset.configuring = "0";
        configureEntryDestination(form, false);
        filterEntryCustomers(form, organization ? "" : destination.value);
        if (!organization || form.elements.customer_id.value)
            openProductSearch(form);
        else {
            entryStep("destination");
            form.elements.customer_id.focus();
        }
    }

    function openProductSearch(form) {
        if (!form.elements.customer_id.reportValidity()) return;
        if (entryContext.type === "organization") {
            entryContext.customer = form.elements.customer_id.value;
            entryContext.customerName = form.elements.customer_id.selectedOptions[0].textContent;
            destinationsBySheet.set(Number(active.id), entryContext);
            updateDestinationLabel();
        }
        form.querySelectorAll("[data-entry-change-customer]").forEach(button => {
            button.hidden = entryContext.type !== "organization";
        });
        $("[data-entry-destination]").textContent =
            form.elements.customer_id.selectedOptions[0].textContent;
        entryStep("product");
        searchEntryProducts(form);
    }

    async function searchEntryProducts(form) {
        const version = ++entrySearchVersion;
        const results = $("[data-product-results]");
        const params = new URLSearchParams({
            project: form.elements.sales_project_id.value,
            customer_id: form.elements.customer_id.value,
            q: form.elements.search.value.trim(),
            products_only: "1",
        });
        const loose = form.dataset.looseProduct === "1";
        if (loose) params.set("unpriced_only", "1");
        results.textContent = "Buscando produtos…";
        try {
            const { products } = await request("/catalog?" + params);
            if (version !== entrySearchVersion) return;
            results.innerHTML =
                products
                    .map(
                        (product) =>
                            `<button type="button" data-entry-product-id="${Number(
                                product.id
                            )}" data-name="${esc(
                                product.name
                            )}" data-unit="${esc(product.unit)}"><strong>${esc(
                                product.name
                            )}</strong><span>${esc(
                                product.unit
                            )} <i class="ph ph-caret-right" aria-hidden="true"></i></span></button>`
                    )
                    .join("") || (loose ? "<p>Nenhum produto avulso cadastrado encontrado.</p>" : "<p>Nenhum produto com preço na tabela deste cliente.</p>");
        } catch (error) {
            if (version === entrySearchVersion)
                results.textContent = error.message;
        }
    }
    function filterEntryCustomers(
        form,
        selectedCustomer = form.elements.customer_id.value
    ) {
        const organization =
            form.elements.destination_type.value === "organization"
                ? form.elements.organization_id.value
                : "";
        const search = form.elements.customer_search.value
            .trim()
            .toLocaleLowerCase("pt-BR");
        const customers = entryCustomers.filter(
            (customer) =>
                (!organization ||
                    String(customer.organization_id) === organization) &&
                customer.name.toLocaleLowerCase("pt-BR").includes(search)
        );
        form.elements.customer_id.innerHTML =
            '<option value="">Selecione o cliente</option>' +
            options(customers);
        if (
            customers.some(
                (customer) => String(customer.id) === selectedCustomer
            )
        )
            form.elements.customer_id.value = selectedCustomer;
        else if (customers.length === 1)
            form.elements.customer_id.value = String(customers[0].id);
        updateEntryPrice(form);
    }
    function updateEntryPrice(form) {
        const customer = entryCustomers.find(
            (row) => String(row.id) === form.elements.customer_id.value
        );
        form.elements.price_choice.options[0].textContent =
            customer?.price_table?.name || "Tabela vinculada ao cliente";
        if (form.dataset.pricedCustomer !== form.elements.customer_id.value) {
            form.elements.price_choice.value = "default";
            form.elements.product_id.value = "";
            form.elements.quantity.value = "";
            form.dataset.pricedCustomer = form.elements.customer_id.value;
        }
        refreshQuote(form);
    }
    async function loadProducts() {
        const form = $("[data-add-item]");
        if (!form) return;
        const projectSelect = form.elements.sales_project_id;
        const project = projectSelect.value;
        if (!project)
            throw new Error(
                "A planilha precisa de um projeto disponível para registrar entregas."
            );
        const sameProject = form.dataset.loadedProject === project;
        const previous = sameProject
            ? {
                  organization: form.elements.organization_id.value,
                  customer: form.elements.customer_id.value,
              }
            : entryContext?.project === project
            ? entryContext
            : {};
        projectSelect.disabled = true;
        let result;
        try {
            result = await request(
                "/catalog?" + new URLSearchParams({ project })
            );
        } finally {
            projectSelect.disabled = false;
        }
        form.dataset.loadedProject = project;
        entryCustomers = result.customers;
        form.elements.organization_id.innerHTML =
            '<option value="">Selecione a organização</option>' +
            options(result.organizations);
        if (
            result.organizations.some(
                (row) => String(row.id) === previous.organization
            )
        )
            form.elements.organization_id.value = previous.organization;
        form.elements.product_id.value = "";
        filterEntryCustomers(form, previous.customer || "");
    }
    async function consolidate(page = 1) {
        if (!selected.size)
            throw new Error("Selecione pelo menos uma planilha.");
        const params = new URLSearchParams();
        selected.forEach((id) => params.append("sheet_ids[]", id));
        Object.entries(consolidationFilters).forEach(([key, value]) => {
            if (value) params.set(key, value);
        });
        params.set("page", page);
        const result = await request("/consolidated?" + params);
        const catalog = await request("/catalog");
        $(
            "[data-consolidated]"
        ).innerHTML = `<button type="button" class="pd-link-back" data-back><i class="ph ph-arrow-left" aria-hidden="true"></i> Todas as planilhas</button>
            <div class="pd-consolidated-head"><div><span class="pd-eyebrow">Visão integrada</span><h3>Consolidação de planilhas</h3><p>Somatório operacional de ${
                selected.size
            } planilha(s), com origens preservadas. Nenhuma distribuição oficial será criada.</p></div>
            <button type="button" class="btn-primary" data-export-report><i class="ph ph-file-pdf" aria-hidden="true"></i> Emitir relação preliminar</button></div>
            <form data-consolidation-filter class="pd-toolbar pd-consolidation-filters"><label><span>De</span><input name="date_from" type="date" value="${esc(
                consolidationFilters.date_from
            )}"></label><label><span>Até</span><input name="date_to" type="date" value="${esc(
            consolidationFilters.date_to
        )}"></label>
                <label><span>Projeto</span><select name="sales_project_id"><option value="">Todos</option>${options(
                    catalog.projects,
                    "title"
                )}</select></label>
                <label><span>Entregador</span><select name="assigned_to"><option value="">Todos</option>${options(
                    catalog.operators
                )}</select></label>
                <label><span>Cliente</span><select name="customer_id"><option value="">Todos do projeto</option></select></label>
                <button class="btn-secondary" type="submit"><i class="ph ph-funnel" aria-hidden="true"></i> Aplicar</button>
            </form>
            <div class="pd-detail-section-heading"><div><h4>Produtos consolidados</h4><p>Toque em “Ver origens” para identificar cada lançamento e entregador.</p></div><span class="pd-count-pill">${
                result.data.length
            } grupo(s) nesta página</span></div>
            <div class="pd-table-wrap"><table class="pd-table"><thead><tr><th>Projeto / destino</th><th>Produto</th><th>Planejado</th><th>Informado</th><th>Pendentes</th><th>Origens</th></tr></thead>
                <tbody>${
                    result.data
                        .map(
                            (row, index) => `<tr>
                    <td data-label="Destino"><strong>${esc(
                        row.customer
                    )}</strong><br><span class="pd-muted">${esc(
                                row.project
                            )}</span></td>
                    <td data-label="Produto"><strong>${esc(
                        row.product
                    )}</strong><br><span class="pd-muted">${esc(
                                row.unit
                            )}</span></td>
                    <td data-label="Planejado">${
                        row.planned_quantity === null
                            ? "—"
                            : esc(
                                  quantities.format(
                                      Number(row.planned_quantity)
                                  )
                              )
                    }</td>
                    <td data-label="Informado"><strong>${
                        row.reported_quantity === null
                            ? "—"
                            : esc(
                                  quantities.format(
                                      Number(row.reported_quantity)
                                  )
                              )
                    }</strong></td>
                    <td data-label="Pendentes">${Number(row.pending_items)}</td>
                    <td data-label="Origem">${
                        row.possible_duplicate
                            ? '<p class="pd-warning">Possível duplicidade</p>'
                            : ""
                    }<button data-origin="${index}" type="button"><i class="ph ph-git-branch" aria-hidden="true"></i> Ver origens (${Number(
                                row.source_items
                            )})</button></td>
                </tr>`
                        )
                        .join("") ||
                    '<tr><td colspan="6">Nenhum registro corresponde aos filtros.</td></tr>'
                }</tbody></table></div>
            <div data-origin-content class="pd-origins"></div>`;
        $("[data-consolidated]")
            .querySelectorAll("[data-origin]")
            .forEach((button) =>
                button.addEventListener("click", () =>
                    run(async () => {
                        const row = result.data[Number(button.dataset.origin)];
                        const query = new URLSearchParams(params);
                        for (const field of [
                            "sales_project_id",
                            "customer_id",
                            "product_id",
                            "unit",
                        ])
                            query.set(field, row[field]);
                        async function showOrigins(page = 1) {
                            query.set("page", page);
                            const origins = await request("/origins?" + query);
                            $(
                                "[data-origin-content]"
                            ).innerHTML = `<div class="pd-detail-section-heading"><h4>Origens dos registros (${Number(
                                origins.total
                            )})</h4><button type="button" class="pd-button-quiet" data-hide-origins><i class="ph ph-x" aria-hidden="true"></i> Fechar</button></div>
                    ${origins.data
                        .map(
                            (item) =>
                                `<div class="pd-origin-row"><span><strong>${sheetCode(
                                    item.sheet_id
                                )}</strong> · Linha #${Number(
                                    item.id
                                )}<small>${readableDate(
                                    item.operational_date
                                )}</small></span><strong>${
                                    item.reported_quantity === null
                                        ? "Pendente"
                                        : quantities.format(
                                              Number(item.reported_quantity)
                                          ) +
                                          " " +
                                          esc(item.unit)
                                }</strong></div>`
                        )
                        .join("")}
                    <nav class="pd-pagination" data-origin-pages></nav>`;
                            $("[data-origin-content]").scrollIntoView({
                                behavior: "smooth",
                                block: "nearest",
                            });
                            pageButtons(
                                $("[data-origin-pages]"),
                                origins,
                                showOrigins
                            );
                        }
                        await showOrigins();
                    })
                )
            );
        $("[data-consolidated]").insertAdjacentHTML(
            "beforeend",
            '<nav class="pd-pagination" data-consolidated-pages></nav>'
        );
        pageButtons($("[data-consolidated-pages]"), result, consolidate);
        const filters = $("[data-consolidation-filter]");
        const organizationLabel = document.createElement("label");
        organizationLabel.textContent = "Organização";
        const organization = document.createElement("select");
        organization.name = "organization_id";
        organization.innerHTML =
            '<option value="">Todas</option>' +
            options(catalog.organizations || []);
        organization.value = consolidationFilters.organization_id || "";
        organizationLabel.append(organization);
        filters.insertBefore(organizationLabel, filters.lastElementChild);
        filters.elements.sales_project_id.value =
            consolidationFilters.sales_project_id || "";
        filters.elements.assigned_to.value =
            consolidationFilters.assigned_to || "";
        if (consolidationFilters.sales_project_id) await loadFilterCustomers();
        filters.elements.customer_id.value =
            consolidationFilters.customer_id || "";
        panel("consolidated");
    }
    async function loadFilterCustomers() {
        const form = $("[data-consolidation-filter]");
        if (!form) return;
        const project = form.elements.sales_project_id.value;
        const catalog = project
            ? await request("/catalog?project=" + encodeURIComponent(project))
            : { customers: [] };
        form.elements.customer_id.innerHTML =
            '<option value="">Todos do projeto</option>' +
            options(catalog.customers);
    }
    root.addEventListener("input", (event) => {
        if (event.target.closest("[data-create-form]")) createDirty = true;
        if (event.target.matches('[data-add-item] [name="customer_search"]'))
            filterEntryCustomers(event.target.form);
        if (event.target.matches('[data-add-item] [name="search"]')) {
            entrySearchVersion++;
            clearTimeout(entrySearchTimer);
            $("[data-product-results]").textContent = "Buscando produtos…";
            const form = event.target.form;
            entrySearchTimer = setTimeout(() => searchEntryProducts(form), 250);
        }
        if (
            event.target.matches(
                '[name="planned_quantity"], [name="quantity"], [name="reference_price"]'
            ) &&
            event.target.form?.elements.price_choice
        ) {
            showPricePreview(event.target.form);
        }
        const form = event.target.closest("[data-item]");
        if (!form) return;
        dirty.add(Number(form.dataset.item));
        form.dataset.dirty = "true";
        const badge = $("[data-unsaved-count]");
        if (badge) {
            badge.hidden = false;
            badge.textContent = `${dirty.size} não salva(s)`;
        }
    });
    root.addEventListener("change", (event) => {
        if (event.target.closest("[data-create-form]")) createDirty = true;
        if (event.target.matches('[data-add-item] [name="destination_type"]')) {
            const form = event.target.form;
            configureEntryDestination(form, true);
            filterEntryCustomers(form, "");
        }
        if (event.target.matches('[data-add-item] [name="organization_id"]'))
            filterEntryCustomers(event.target.form, "");
        if (event.target.matches('[data-add-item] [name="customer_id"]'))
            updateEntryPrice(event.target.form);
        if (
            event.target.matches(
                '[name="price_choice"], [data-add-item] [name="product_id"]'
            )
        ) {
            refreshQuote(event.target.form);
        }
        if (
            event.target.matches(
                "[data-detail-customer], [data-detail-pending]"
            )
        ) {
            if (busy) {
                $("[data-detail-customer]").value = detailCustomer;
                $("[data-detail-pending]").checked = detailPending;
                return;
            }
            if (!canLeave()) {
                $("[data-detail-customer]").value = detailCustomer;
                $("[data-detail-pending]").checked = detailPending;
                return;
            }
            detailCustomer = $("[data-detail-customer]").value;
            detailPending = $("[data-detail-pending]").checked;
            dirty.clear();
            run(() => detail(active.id));
        }
        if (event.target.matches("[data-select-sheet]")) {
            const id = Number(event.target.dataset.selectSheet);
            event.target.checked ? selected.add(id) : selected.delete(id);
            updateSelection();
        }
        if (event.target.matches('[data-add-item] [name="sales_project_id"]'))
            run(loadProducts);
        if (
            event.target.matches(
                '[data-consolidation-filter] [name="sales_project_id"]'
            )
        )
            run(loadFilterCustomers);
    });
    root.addEventListener("keydown", (event) => {
        if (event.key !== "Enter") return;
        if (
            event.target.matches(
                '[data-create-form] [name="project_q"], [data-create-form] [name="operator_q"]'
            )
        ) {
            event.preventDefault();
            run(loadCreateCatalog);
        } else if (event.target.matches('[data-add-item] [name="search"]')) {
            event.preventDefault();
            clearTimeout(entrySearchTimer);
            searchEntryProducts(event.target.form);
        } else if (
            event.target.matches('[data-add-item] [name="customer_search"]')
        ) {
            event.preventDefault();
            event.target.form.elements.customer_id.focus();
        }
    });
    root.addEventListener("submit", (event) => {
        const form = event.target;
        const submitter = event.submitter;
        event.preventDefault();
        run(async () => {
            if (form.matches("[data-filter]")) return list();
            if (form.matches("[data-detail-search]")) {
                if (!canLeave()) return;
                detailSearch = form.elements.q.value.trim();
                dirty.clear();
                return detail(active.id);
            }
            if (form.matches("[data-consolidation-filter]")) {
                consolidationFilters = Object.fromEntries(new FormData(form));
                return consolidate();
            }
            if (form.matches("[data-link-existing]")) {
                if (dirty.size)
                    throw new Error(
                        "Salve as linhas alteradas antes de conciliar."
                    );
                const rows = [...form.querySelectorAll("[data-official-id]")]
                    .filter((input) => input.value.trim())
                    .map((input) => ({
                        pre_distribution_item_id: Number(
                            form.dataset.linkExisting
                        ),
                        pre_distribution_version: active.version,
                        distribution_id: Number(input.dataset.officialId),
                        quantity: input.value.trim().replace(",", "."),
                    }));
                if (!rows.length)
                    throw new Error(
                        "Informe a quantidade que deseja vincular."
                    );
                if (
                    (await ask({
                        title: "Confirmar vínculos?",
                        description:
                            "As distribuições e os valores oficiais serão preservados. A operação ficará registrada na auditoria.",
                        confirm: "Vincular distribuições",
                    })) === null
                )
                    return;
                await request("/links", "POST", { rows });
                return detail(active.id);
            }
            if (form.matches("[data-create-form]")) {
                const projects = new FormData(form)
                    .getAll("project_ids[]")
                    .map(Number);
                if (!projects.length)
                    throw new Error(
                        "Selecione pelo menos um projeto para a planilha."
                    );
                const result = await request("", "POST", {
                    title: form.elements.title.value,
                    operational_date: form.elements.operational_date.value,
                    assigned_to: Number(form.elements.assigned_to.value),
                    project_ids: projects,
                    notes: form.elements.notes.value,
                    items: [],
                });
                createDirty = false;
                return detail(result.sheet_id);
            }
            if (form.matches("[data-details]")) {
                await request(`/${active.id}/details`, "PUT", {
                    version: active.version,
                    title: form.elements.title.value,
                    operational_date: form.elements.operational_date.value,
                    assigned_to: Number(form.elements.assigned_to.value),
                    notes: form.elements.notes.value,
                });
                return detail(active.id);
            }
            if (form.matches("[data-item]")) {
                const id = Number(form.dataset.item);
                const inputs = [
                    ...form.querySelectorAll("input,textarea,select"),
                ];
                inputs.forEach((input) => {
                    input.disabled = true;
                });
                try {
                    const result = await request(`/${active.id}`, "PUT", {
                        version: active.version,
                        items: [rowPayload(form)],
                    });
                    active.version = result.version;
                    $("[data-version]").textContent = result.version;
                    updateReferenceTotals(result.reference_totals);
                    updateSavedReference(form, result.saved_references);
                    dirty.delete(id);
                    delete form.dataset.dirty;
                    const indicator = form.querySelector("[data-row-status]");
                    indicator.textContent = "Salvo";
                    indicator.className = "pd-mini-state pd-mini-filled";
                    const badge = $("[data-unsaved-count]");
                    if (badge) {
                        badge.hidden = !dirty.size;
                        badge.textContent = `${dirty.size} não salva(s)`;
                    }
                } finally {
                    inputs.forEach((input) => {
                        input.disabled = false;
                    });
                }
                return;
            }
            if (form.matches("[data-add-item]")) {
                if (
                    !entryContext ||
                    $("[data-entry]").dataset.step !== "quantity" ||
                    !form.elements.product_id.value
                )
                    return;
                if (!hasEntryPrice(form)) throw new Error("Defina um preço maior que zero antes de registrar este produto.");
                if (dirty.size)
                    throw new Error(
                        "Salve as linhas alteradas antes de adicionar outra."
                    );
                if (
                    form.dataset.loadedProject !==
                    form.elements.sales_project_id.value
                )
                    throw new Error(
                        "Atualize os clientes e produtos do projeto antes de salvar."
                    );
                await request(`/${active.id}`, "PUT", {
                    version: active.version,
                    items: [
                        {
                            sales_project_id: Number(entryContext.project),
                            customer_id: Number(form.elements.customer_id.value),
                            product_id: Number(form.elements.product_id.value),
                            organization_id:
                                Number(entryContext.organization) || null,
                            planned_quantity:
                                form.elements.planned_quantity.value
                                    .trim()
                                    .replace(",", ".") || null,
                            reported_quantity:
                                form.elements.quantity.value
                                    .trim()
                                    .replace(",", ".") || null,
                            ...pricePayload(form),
                            logistics_mode: form.elements.logistics_mode.value,
                        },
                    ],
                });
                const another = !!submitter?.hasAttribute(
                    "data-save-add-another"
                );
                closeEntry(!another);
                await detail(active.id);
                if (another) {
                    await openEntry(false, true);
                    const notice = $("[data-entry-message]");
                    if (notice) {
                        notice.textContent =
                            "Produto salvo! Escolha o próximo.";
                        notice.classList.add("pd-entry-message-success");
                        notice.hidden = false;
                    }
                } else {
                    message("Produto salvo na planilha.");
                    $("[data-open-entry]")?.focus();
                }
                return;
            }
        });
    });
    root.addEventListener("click", (event) => {
        const button = event.target.closest("button");
        if (!button) return;
        if (button.matches("[data-new-sheet]")) run(createPanel);
        if (button.matches("[data-find-context]")) run(loadCreateCatalog);
        if (button.matches("[data-back]") && canLeave()) {
            dirty.clear();
            run(() => list(listPage));
        }
        if (button.matches("[data-open-sheet]"))
            run(() => detail(button.dataset.openSheet));
        if (button.matches("[data-sheet-table-toggle]")) run(() => toggleSheetTable(button));
        if (button.matches("[data-set-entry-destination]"))
            run(() => openEntry(true));
        if (button.matches("[data-open-entry]")) run(() => openEntry());
        if (button.matches("[data-entry-close]")) closeEntry();
        if (button.matches("[data-entry-change-customer]")) entryStep("destination");
        if (button.matches("[data-entry-back]")) {
            const step = $("[data-entry]").dataset.step;
            if (
                step === "destination" ||
                (step === "product" && entryContext?.type === "customer")
            )
                closeEntry();
            else
                entryStep(
                    {
                        product: "destination",
                        quantity: "product",
                        options: "quantity",
                    }[step]
                );
        }
        if (button.matches("[data-entry-next]")) {
            if ($("[data-entry]").dataset.configuring === "1")
                confirmEntryDestination(button.form);
            else openProductSearch(button.form);
        }
        if (button.matches("[data-entry-options]")) entryStep("options");
        if (button.matches("[data-entry-done]")) entryStep("quantity");
        if (button.matches("[data-toggle-loose-product]")) {
            const form = button.form;
            const loose = form.dataset.looseProduct !== "1";
            form.dataset.looseProduct = loose ? "1" : "0";
            button.setAttribute("aria-pressed", String(loose));
            button.textContent = loose ? "Voltar aos produtos da tabela" : "Adicionar produto avulso";
            clearTimeout(entrySearchTimer);
            searchEntryProducts(form);
            form.elements.search.focus();
        }
        if (button.matches("[data-entry-product-id]")) {
            const form = button.closest("form");
            if (
                form.elements.product_id.value !== button.dataset.entryProductId
            ) {
                form.elements.quantity.value = "";
                form.elements.reference_price.value = "";
                form.elements.price_choice.value = "default";
            }
            form.elements.product_id.value = button.dataset.entryProductId;
            $("[data-entry-product]").textContent = button.dataset.name;
            $(
                "[data-quantity-label]"
            ).textContent = `Quantidade entregue (${button.dataset.unit})`;
            const loose = form.dataset.looseProduct === "1";
            if (loose) form.elements.price_choice.value = "manual";
            entryStep(loose ? "options" : "quantity");
            refreshQuote(form);
            if (loose) form.elements.reference_price.focus();
        }
        if (button.matches("[data-hide-origins]")) {
            $("[data-origin-content]").replaceChildren();
        }
        if (button.matches("[data-consolidate]")) {
            consolidationFilters = {};
            run(() => consolidate());
        }
        if (button.matches("[data-save-changed]"))
            run(async () => {
                const forms = [...root.querySelectorAll("[data-item]")].filter(
                    (form) => dirty.has(Number(form.dataset.item))
                );
                if (!forms.length) {
                    message("Nenhuma alteração pendente nesta página.");
                    return;
                }
                const controls = forms.flatMap((form) => [
                    ...form.querySelectorAll("input,textarea,select"),
                ]);
                controls.forEach((input) => {
                    input.disabled = true;
                });
                try {
                    const result = await request(`/${active.id}`, "PUT", {
                        version: active.version,
                        items: forms.map(rowPayload),
                    });
                    active.version = result.version;
                    $("[data-version]").textContent = result.version;
                    updateReferenceTotals(result.reference_totals);
                    forms.forEach((form) =>
                        updateSavedReference(form, result.saved_references)
                    );
                    forms.forEach((form) => {
                        dirty.delete(Number(form.dataset.item));
                        delete form.dataset.dirty;
                        const indicator =
                            form.querySelector("[data-row-status]");
                        indicator.textContent = "Salvo";
                        indicator.className = "pd-mini-state pd-mini-filled";
                    });
                    const badge = $("[data-unsaved-count]");
                    if (badge) badge.hidden = true;
                    message(`${forms.length} linha(s) salva(s).`);
                } finally {
                    controls.forEach((input) => {
                        input.disabled = false;
                    });
                }
            });
        if (button.matches("[data-export-report]"))
            run(async () => {
                const choice = await ask({
                    title: "Emitir relação preliminar",
                    description:
                        "Escolha o que deve aparecer no PDF. O documento não altera o financeiro oficial.",
                    type: "choice",
                    confirm: "Gerar documento",
                    options: [
                        ["without", "Somente produtos e quantidades"],
                        ["with", "Produtos, quantidades e valores estimados"],
                    ],
                });
                if (choice === null) return;
                const includePrices = choice === "with";
                const result = await request("/reports", "POST", {
                    sheet_ids: [...selected],
                    include_prices: includePrices,
                    filters: consolidationFilters,
                });
                message(
                    `Relação preliminar ${result.code} · revisão ${result.revision} emitida.`
                );
                const link = document.createElement("a");
                link.href = `${base}/reports/${result.report_id}/pdf`;
                link.textContent = "Baixar relação preliminar";
                link.className = "pd-download";
                $("[data-message]").append(document.createElement("br"), link);
            });
        if (button.matches("[data-reports]"))
            run(async () => {
                if (!canLeave()) return;
                async function showReports(page = 1) {
                    const result = await request("/reports?page=" + page);
                    $(
                        "[data-consolidated]"
                    ).innerHTML = `<button type="button" class="pd-link-back" data-back><i class="ph ph-arrow-left" aria-hidden="true"></i> Todas as planilhas</button>
                    <div class="pd-consolidated-head"><div><span class="pd-eyebrow">Documentos preservados</span><h3>Relações preliminares emitidas</h3><p>Consulte PDFs anteriores e emita novas revisões sem alterar as versões antigas.</p></div></div>
                    <div class="pd-list-content">${
                        result.data
                            .map(
                                (
                                    row
                                ) => `<article class="pd-sheet"><span class="pd-sheet-icon"><i class="ph ph-file-pdf" aria-hidden="true"></i></span>
                        <div class="pd-sheet-info"><strong>${esc(
                            row.code
                        )}</strong><p class="pd-sheet-meta">Revisão ${Number(
                                    row.revision
                                )} · ${esc(row.created_at)}</p></div>
                        <div class="pd-actions"><a class="btn-secondary" href="${base}/reports/${Number(
                                    row.id
                                )}/pdf" target="_blank" rel="noopener"><i class="ph ph-download" aria-hidden="true"></i> PDF</a>
                        <button class="pd-button-quiet" type="button" data-revise-report="${Number(
                            row.id
                        )}"><i class="ph ph-arrows-clockwise" aria-hidden="true"></i> Nova revisão</button></div></article>`
                            )
                            .join("") ||
                        '<div class="pd-empty"><i class="ph ph-files"></i><h4>Nenhum documento emitido</h4></div>'
                    }</div>
                    <nav data-report-pages class="pd-pagination"></nav>`;
                    pageButtons($("[data-report-pages]"), result, showReports);
                    panel("consolidated");
                }
                await showReports();
            });
        if (button.matches("[data-revise-report]"))
            run(async () => {
                if (
                    (await ask({
                        title: "Emitir nova revisão?",
                        description:
                            "O PDF anterior será preservado no histórico.",
                        confirm: "Emitir revisão",
                    })) === null
                )
                    return;
                const id = Number(button.dataset.reviseReport);
                const source = await request(`/reports/${id}`);
                const result = await request("/reports", "POST", {
                    ...source,
                    previous_report_id: id,
                });
                message(`${result.code} · Revisão ${result.revision} emitida.`);
                const link = document.createElement("a");
                link.href = `${base}/reports/${result.report_id}/pdf`;
                link.textContent = "Baixar nova revisão";
                $("[data-message]").append(document.createElement("br"), link);
            });
        if (button.matches("[data-duplicate]"))
            run(async () => {
                if (dirty.size)
                    throw new Error(
                        "Salve as linhas alteradas antes de duplicar."
                    );
                const date = await ask({
                    title: "Duplicar planilha",
                    description:
                        "A cópia será criada como um novo planejamento, sem alterar a original.",
                    type: "date",
                    label: "Data da nova planilha",
                    value: active.operational_date.slice(0, 10),
                    confirm: "Criar cópia",
                });
                if (date === null) return;
                const result = await request(
                    `/${active.id}/duplicate`,
                    "POST",
                    {
                        version: active.version,
                        title: active.title + " - cópia",
                        operational_date: date,
                        assigned_to: active.assigned_to,
                    }
                );
                return detail(result.sheet_id);
            });
        if (button.matches("[data-edit-details]"))
            run(async () => {
                if (dirty.size)
                    throw new Error(
                        "Salve as linhas alteradas antes de editar os dados."
                    );
                const catalog = await request("/catalog");
                $(
                    "[data-extra-content]"
                ).innerHTML = `<form data-details class="pd-editor"><label>Nome<input name="title" required maxlength="180" value="${esc(
                    active.title
                )}"></label><label>Data<input name="operational_date" type="date" required value="${esc(
                    active.operational_date.slice(0, 10)
                )}"></label><label>Responsável<select name="assigned_to">${options(
                    catalog.operators
                )}</select></label><label>Observações<textarea name="notes" maxlength="5000">${esc(
                    active.notes
                )}</textarea></label><button>Salvar dados</button></form>`;
                $("[data-details]").elements.assigned_to.value =
                    active.assigned_to;
                $("[data-extra-content]").scrollIntoView({
                    behavior: "smooth",
                    block: "nearest",
                });
            });
        if (button.matches("[data-reconciliation]"))
            run(async () => {
                async function showProgress(page = 1) {
                    const result = await request(
                        `/${active.id}/reconciliation?page=${page}`
                    );
                    $(
                        "[data-extra-content]"
                    ).innerHTML = `<h3>Conciliação com distribuições oficiais</h3>${result.data
                        .map(
                            ({ item, progress }) =>
                                `<article class="pd-reconcile-card"><strong>Linha #${
                                    item.id
                                } · ${esc(
                                    reconciliationLabels[progress.state] ||
                                        progress.state
                                )}</strong><p>Informado: ${
                                    progress.reported === null
                                        ? "Pendente"
                                        : quantities.format(
                                              Number(progress.reported)
                                          )
                                } ${esc(
                                    item.unit
                                )} · Conciliado: ${quantities.format(
                                    Number(progress.reconciled)
                                )} · Reservado: ${quantities.format(
                                    Number(progress.reserved)
                                )} · Disponível: ${quantities.format(
                                    Number(progress.remaining)
                                )}</p>${progress.warnings
                                    .map(
                                        (warning) =>
                                            `<p class="pd-warning">${esc(
                                                warning.message
                                            )}</p>`
                                    )
                                    .join("")}${progress.links
                                    .map(
                                        (link) =>
                                            `<p>Distribuição #${
                                                link.distribution_id
                                            } · ${quantities.format(
                                                Number(link.quantity)
                                            )} ${esc(link.unit)} · ${
                                                link.reversed_at
                                                    ? "Vínculo revertido"
                                                    : `<button type="button" data-reverse-link="${link.id}">Reverter após correção oficial</button>`
                                            }</p>`
                                    )
                                    .join("")}</article>`
                        )
                        .join(
                            ""
                        )}<nav data-progress-pages class="pd-pagination"></nav>`;
                    pageButtons(
                        $("[data-progress-pages]"),
                        result,
                        showProgress
                    );
                    $("[data-extra-content]")
                        .querySelectorAll("article")
                        .forEach((article, index) => {
                            const { item, progress } = result.data[index];
                            article.querySelector(
                                "strong"
                            ).textContent = `Linha #${item.id} · ${
                                reconciliationLabels[progress.state] ||
                                progress.state
                            }`;
                            if (
                                active.status !== "completed" ||
                                Number(progress.remaining) <= 0 ||
                                progress.state === "divergent"
                            )
                                return;
                            const button = document.createElement("button");
                            button.type = "button";
                            button.dataset.existingItem = item.id;
                            button.textContent =
                                "Vincular distribuição existente";
                            const container = document.createElement("div");
                            container.dataset.officialChoices = item.id;
                            article.append(button, container);
                        });
                }
                await showProgress();
                $("[data-extra-content]").scrollIntoView({
                    behavior: "smooth",
                    block: "nearest",
                });
            });
        if (button.matches("[data-existing-item]"))
            run(async () => {
                const id = Number(button.dataset.existingItem);
                async function showCandidates(page = 1) {
                    const result = await request(
                        `/${active.id}/items/${id}/officials?page=${page}`
                    );
                    const container = $(`[data-official-choices="${id}"]`);
                    container.innerHTML = `<form data-link-existing="${id}" class="pd-editor"><p>Informe a parte de cada distribuição que corresponde a esta linha preliminar.</p>${result.data
                        .map(
                            (row) =>
                                `<label>Distribuição #${row.id} · ${esc(
                                    row.associate
                                )} · ${esc(
                                    row.delivery_date
                                )} · Saldo ${quantities.format(
                                    Number(row.available)
                                )}<input data-official-id="${
                                    row.id
                                }" inputmode="decimal" placeholder="Quantidade para vincular" ${
                                    Number(row.available) <= 0 ? "disabled" : ""
                                }></label>`
                        )
                        .join("")}<button ${
                        result.data.length ? "" : "disabled"
                    }>Confirmar vínculos</button></form><nav data-official-pages="${id}" class="pd-pagination"></nav>`;
                    pageButtons(
                        $(`[data-official-pages="${id}"]`),
                        result,
                        async (next) => {
                            if (
                                [...container.querySelectorAll("input")].some(
                                    (input) => input.value.trim()
                                ) &&
                                !window.confirm(
                                    "Trocar a página sem confirmar as quantidades digitadas?"
                                )
                            )
                                return;
                            await showCandidates(next);
                        }
                    );
                }
                await showCandidates();
            });
        if (button.matches("[data-reverse-link]"))
            run(async () => {
                if (dirty.size)
                    throw new Error(
                        "Salve suas alterações antes de reverter um vínculo."
                    );
                const reason = await ask({
                    title: "Reverter vínculo",
                    description:
                        "A reversão será registrada na auditoria. Corrija a distribuição oficial antes, quando necessário.",
                    type: "text",
                    label: "Justificativa",
                    minLength: 5,
                    confirm: "Reverter vínculo",
                });
                if (reason === null) return;
                await request(
                    `/links/${Number(button.dataset.reverseLink)}/reverse`,
                    "POST",
                    { version: active.version, reason }
                );
                return detail(active.id);
            });
        if (button.matches("[data-cancel-item]"))
            run(async () => {
                if (dirty.size)
                    throw new Error(
                        "Salve as linhas alteradas antes de cancelar."
                    );
                const reason = await ask({
                    title: "Cancelar item",
                    description:
                        "Esta ação exige justificativa e será registrada no histórico.",
                    type: "text",
                    label: "Motivo do cancelamento",
                    minLength: 5,
                    confirm: "Cancelar item",
                });
                if (reason === null) return;
                await request(
                    `/${active.id}/items/${Number(
                        button.dataset.cancelItem
                    )}/cancel`,
                    "POST",
                    { version: active.version, reason }
                );
                return detail(active.id);
            });
        if (button.matches("[data-history]"))
            run(async () => {
                const result = await request(`/${active.id}/history`);
                $("[data-history-content]").innerHTML = result.data
                    .map(
                        (event) =>
                            `<details><summary>Versão ${Number(
                                event.version
                            )} · ${esc(event.event)} · ${esc(
                                event.created_at
                            )}</summary><p>${esc(event.reason)}</p>${
                                manager
                                    ? `<pre>${esc(
                                          JSON.stringify(
                                              {
                                                  before: event.before,
                                                  after: event.after,
                                              },
                                              null,
                                              2
                                          )
                                      )}</pre>`
                                    : ""
                            }</details>`
                    )
                    .join("");
            });
        if (button.matches("[data-transition]"))
            run(async () => {
                if (dirty.size)
                    throw new Error(
                        "Salve todas as linhas alteradas antes de mudar a situação."
                    );
                const action = button.dataset.transition;
                const titles = {
                    complete: "Concluir planilha",
                    reopen: "Reabrir planilha",
                    cancel: "Cancelar planilha",
                    request_correction: "Solicitar correção",
                };
                const response = await ask({
                    title: titles[action] || "Confirmar alteração",
                    description:
                        action === "complete"
                            ? "Após concluir, correções exigirão reabertura autorizada."
                            : "Informe a justificativa. A alteração ficará registrada na auditoria.",
                    type: action === "complete" ? "confirm" : "text",
                    label: "Justificativa",
                    minLength: 5,
                    confirm:
                        action === "complete"
                            ? "Concluir planilha"
                            : "Confirmar alteração",
                });
                if (response === null) return;
                const reason = action === "complete" ? null : response;
                await request(`/${active.id}/transition`, "POST", {
                    version: active.version,
                    action,
                    reason,
                });
                return detail(active.id);
            });
    });
    run(() => list());
}
