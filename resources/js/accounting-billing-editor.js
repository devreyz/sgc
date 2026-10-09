(() => {
    const root = document.querySelector("[data-billing-editor]");
    if (!root) return;

    const form = root.querySelector("[data-billing-form]");
    const token =
        document.querySelector('meta[name="csrf-token"]')?.content || "";
    const selected = new Set();
    const batches = new Map();
    const projectDraft = new Set();

    let projects = [];
    let step = 1;
    let mode = "period";
    let preview = null;
    let reportAnnotations = [];
    let recipientRequest = 0;
    let activeDialog = null;
    let dialogHistoryPushed = false;
    const operationKey = crypto.randomUUID();

    const q = (selector) => root.querySelector(selector);
    const qa = (selector) => [...root.querySelectorAll(selector)];
    const projectSelect = q("[data-project-select]");
    const projectDialog = q("[data-project-dialog]");
    const freezeDialog = q("[data-freeze-dialog]");

    const escapeHtml = (value) =>
        String(value ?? "").replace(
            /[&<>'"]/g,
            (char) =>
                ({
                    "&": "&amp;",
                    "<": "&lt;",
                    ">": "&gt;",
                    "'": "&#39;",
                    '"': "&quot;",
                }[char])
        );

    const money = (value) =>
        Number(value || 0).toLocaleString("pt-BR", {
            style: "currency",
            currency: "BRL",
        });

    const message = (text, error = false) => {
        const box = q("[data-editor-message]");
        box.hidden = !text;
        box.textContent = text || "";
        box.classList.toggle("is-error", error);
        if (text) box.scrollIntoView({ behavior: "smooth", block: "nearest" });
    };

    const request = async (url, options = {}) => {
        const response = await fetch(url, {
            credentials: "same-origin",
            headers: {
                Accept: "application/json",
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": token,
                ...(options.headers || {}),
            },
            ...options,
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            const errors = Object.values(data.errors || {})
                .flat()
                .join(" ");
            throw new Error(
                errors ||
                    data.message ||
                    "Não foi possível concluir esta operação."
            );
        }

        return data;
    };

    const selectedProjectIds = () =>
        [...projectSelect.selectedOptions]
            .map((option) => Number(option.value))
            .filter(Boolean);

    const readReportAnnotations = () => {
        const entries = qa('[data-report-annotation]').map((row) => ({
            target: row.querySelector('[data-annotation-target]').value,
            text: row.querySelector('[data-annotation-text]').value.trim(),
        }));
        if (entries.length) reportAnnotations = entries;
        return reportAnnotations;
    };

    const renderReportAnnotations = () => {
        const current = qa('[data-report-annotation]').length
            ? readReportAnnotations()
            : reportAnnotations;
        const products = new Map();
        (preview?.distributions || []).forEach((row) => {
            if (row.product_id) products.set(Number(row.product_id), row.product);
        });
        const options = [
            ['global', 'Observação geral'],
            ...[...products].map(([id, name]) => [`product:${id}`, `Produto: ${name}`]),
            ...(preview?.distributions || []).map((row) => [`distribution:${row.id}`, `Distribuição #${row.id} · ${row.product} · ${row.date}`]),
        ];
        current.forEach((entry) => {
            if (entry.target && !options.some(([value]) => value === entry.target)) {
                options.push([entry.target, 'Referência selecionada (fora da seleção atual)']);
            }
        });
        q('[data-report-annotations]').innerHTML = current.map((entry, index) => `
            <div data-report-annotation class="billing-fields-3" style="margin:.6rem 0;padding:.75rem;border:1px solid #dce7e0;border-radius:10px">
                <label class="billing-field"><span>Aplicar a</span><select class="billing-select" data-annotation-target>
                    ${options.map(([value, label]) => `<option value="${escapeHtml(value)}" ${value === entry.target ? 'selected' : ''}>${escapeHtml(label)}</option>`).join('')}
                </select></label>
                <label class="billing-field" style="grid-column:span 2"><span>Observação</span><textarea class="billing-textarea" data-annotation-text maxlength="1000" rows="2">${escapeHtml(entry.text)}</textarea></label>
                <button class="billing-btn" type="button" data-remove-report-annotation="${index}">Remover</button>
            </div>
        `).join('');
        reportAnnotations = current;
    };

    const values = () => {
        const recipientType = form.querySelector(
            '[name="recipient_type"]:checked'
        )?.value;

        return {
            project_ids: selectedProjectIds(),
            customer_id:
                recipientType === "customer"
                    ? Number(form.customer_id.value) || null
                    : null,
            organization_id:
                recipientType === "organization"
                    ? Number(form.organization_id.value) || null
                    : null,
            issued_at: form.issued_at.value,
            from_date: form.from_date.value || null,
            to_date: form.to_date.value || null,
            notes: form.notes.value || null,
            report_annotations_position: form.report_annotations_position.value,
            report_annotations: readReportAnnotations().filter((entry) => entry.text),
            distribution_ids: [...selected],
        };
    };

    const validateContext = () => {
        const data = values();

        if (!data.project_ids.length)
            throw new Error("Selecione ao menos um projeto.");
        if (!data.customer_id && !data.organization_id)
            throw new Error("Selecione quem será cobrado.");
        if (!data.issued_at) throw new Error("Informe a data de emissão.");
        if (data.from_date && data.to_date && data.to_date < data.from_date) {
            throw new Error("A data final não pode ser anterior à inicial.");
        }

        return data;
    };

    const setStep = (next) => {
        step = Math.max(1, Math.min(4, Number(next) || 1));

        qa("[data-step]").forEach((element) => {
            element.hidden = Number(element.dataset.step) !== step;
        });

        qa("[data-step-button]").forEach((button) => {
            const buttonStep = Number(button.dataset.stepButton);
            button.classList.toggle("is-active", buttonStep === step);
            button.classList.toggle("is-done", buttonStep < step);
            button.setAttribute(
                "aria-current",
                buttonStep === step ? "step" : "false"
            );
        });

        q("[data-previous]").hidden = step === 1;
        q("[data-next]").hidden = step === 4;
        q("[data-save]").hidden = step < 3;

        if (q("[data-freeze]")) {
            q("[data-freeze]").hidden = step !== 4;
        }

        root.scrollIntoView({ behavior: "smooth", block: "start" });
    };

    const updateCount = () => {
        const label =
            selected.size === 1
                ? "1 selecionada"
                : `${selected.size} selecionadas`;
        qa("[data-selected-count]").forEach((element) => {
            element.textContent = label;
        });
    };

    const renderProjectSummary = () => {
        const target = q("[data-project-summary]");
        const ids = new Set(selectedProjectIds());
        const chosen = projects.filter((project) =>
            ids.has(Number(project.id))
        );

        target.classList.toggle("is-scrollable", chosen.length > 4);

        if (!chosen.length) {
            target.innerHTML =
                '<div class="billing-project-empty">Nenhum projeto selecionado. Escolha o projeto antes de definir o destinatário.</div>';
            return;
        }

        target.innerHTML = chosen
            .map(
                (project) => `
            <div class="billing-project-selected">
                <div>
                    <strong>${escapeHtml(project.name)}</strong>
                    <small>${
                        project.code
                            ? escapeHtml(project.code)
                            : "Projeto selecionado"
                    }</small>
                </div>
                <button
                    class="billing-btn"
                    type="button"
                    data-remove-project="${Number(project.id)}"
                    aria-label="Remover ${escapeHtml(project.name)}"
                    title="Remover projeto"
                >
                    <i class="ph ph-x" aria-hidden="true"></i>
                </button>
            </div>
        `
            )
            .join("");
    };

    const renderProjectOptions = () => {
        const target = q("[data-project-options]");
        const search = (q("[data-project-search]")?.value || "")
            .trim()
            .toLocaleLowerCase("pt-BR");
        const filtered = projects.filter((project) => {
            if (!search) return true;
            return `${project.name || ""} ${project.code || ""}`
                .toLocaleLowerCase("pt-BR")
                .includes(search);
        });

        target.innerHTML = filtered.length
            ? filtered
                  .map((project) => {
                      const id = Number(project.id);
                      const checked = projectDraft.has(id);
                      return `
                    <button
                        class="billing-project-row${
                            checked ? " is-selected" : ""
                        }"
                        type="button"
                        data-project-option="${id}"
                        aria-pressed="${checked ? "true" : "false"}"
                    >
                        <span class="billing-project-check" aria-hidden="true"><i class="ph-fill ph-check"></i></span>
                        <span>
                            <strong>${escapeHtml(project.name)}</strong>
                            <small>${
                                project.code
                                    ? "Código do projeto"
                                    : "Disponível para faturamento"
                            }</small>
                        </span>
                        ${
                            project.code
                                ? `<span class="billing-project-code">${escapeHtml(
                                      project.code
                                  )}</span>`
                                : ""
                        }
                    </button>
                `;
                  })
                  .join("")
            : '<div class="billing-project-empty">Nenhum projeto encontrado para esta busca.</div>';

        const count = q("[data-project-dialog-count]");
        if (count)
            count.textContent =
                projectDraft.size === 1
                    ? "1 selecionado"
                    : `${projectDraft.size} selecionados`;

        const applyButton = q("[data-apply-project-picker]");
        if (applyButton) applyButton.disabled = projectDraft.size === 0;
    };

    const syncProjectSelect = (ids) => {
        const normalized = new Set([...ids].map(Number));
        [...projectSelect.options].forEach((option) => {
            option.selected = normalized.has(Number(option.value));
        });
        renderProjectSummary();
    };

    const resetDistributionSelection = () => {
        selected.clear();
        batches.clear();
        preview = null;
        updateCount();
        renderBatches();
    };

    const openDialog = (dialog) => {
        if (!dialog || dialog.open) return;
        activeDialog = dialog;
        dialog.showModal();

        if (!dialogHistoryPushed) {
            history.pushState({ billingDialog: true }, "");
            dialogHistoryPushed = true;
        }
    };

    const closeDialog = (dialog, useHistory = true) => {
        if (!dialog?.open) return;
        dialog.close();
        activeDialog = null;

        if (useHistory && dialogHistoryPushed) {
            dialogHistoryPushed = false;
            history.back();
        }
    };

    const openProjectPicker = () => {
        projectDraft.clear();
        selectedProjectIds().forEach((id) => projectDraft.add(id));
        const search = q("[data-project-search]");
        if (search) search.value = "";
        renderProjectOptions();
        openDialog(projectDialog);
    };

    const applyProjectPicker = async () => {
        if (!projectDraft.size) return;

        const before = selectedProjectIds()
            .sort((a, b) => a - b)
            .join(",");
        const after = [...projectDraft].sort((a, b) => a - b).join(",");

        syncProjectSelect(projectDraft);
        closeDialog(projectDialog);

        if (before === after) return;

        resetDistributionSelection();
        message("");
        await loadRecipientsForProjects();
    };

    const removeProject = async (id) => {
        const ids = new Set(selectedProjectIds());
        ids.delete(Number(id));
        syncProjectSelect(ids);
        resetDistributionSelection();
        message("");
        await loadRecipientsForProjects();
    };

    const renderBatches = () => {
        const target = q("[data-scanned-batches]");

        target.innerHTML = batches.size
            ? [...batches.values()]
                  .map(
                      (batch) => `
                <article class="billing-batch-card">
                    <div class="billing-batch-main">
                        <strong>${escapeHtml(
                            batch.number || batch.display
                        )}</strong>
                        <b>${escapeHtml(
                            batch.associate || "Associado não identificado"
                        )}</b>
                        <span>${
                            batch.ids.length
                        } distribuição(ões) selecionada(s)${
                          batch.excluded
                              ? ` · ${batch.excluded} ignorada(s)`
                              : ""
                      }</span>
                        ${
                            (batch.distributions || []).length
                                ? `
                            <details>
                                <summary>Ver distribuições</summary>
                                <ul>${batch.distributions
                                    .map(
                                        (item) => `
                                    <li>#${item.id} · ${escapeHtml(
                                            item.product
                                        )} · ${escapeHtml(
                                            item.quantity
                                        )} ${escapeHtml(item.unit)}${
                                            item.date
                                                ? ` · ${escapeHtml(item.date)}`
                                                : ""
                                        }</li>
                                `
                                    )
                                    .join("")}</ul>
                            </details>
                        `
                                : ""
                        }
                    </div>
                    <button class="billing-btn billing-btn-danger" type="button" data-remove-batch="${escapeHtml(
                        batch.key
                    )}">
                        <i class="ph ph-trash" aria-hidden="true"></i>
                        Remover
                    </button>
                </article>
            `
                  )
                  .join("")
            : '<p class="billing-help">Nenhum lote escaneado.</p>';

        const scannerPreview = q("[data-scanner-batch-preview]");
        if (scannerPreview) {
            const scannedBatches = [...batches.values()];
            scannerPreview.innerHTML = batches.size
                ? `${scannedBatches.length > 3 ? `<small class="acc-scan-preview-total">${scannedBatches.length} comprovantes selecionados · exibindo os 3 mais recentes</small>` : ""}${scannedBatches
                      .slice(-3)
                      .map(
                          (batch) => `
                    <div class="acc-scan-preview-item">
                        <strong>${escapeHtml(
                            batch.number || batch.display
                        )}</strong>
                        <span>${escapeHtml(batch.associate || "")}</span>
                        <small>${batch.ids.length} distribuição(ões)</small>
                    </div>
                `
                      )
                      .join("")}`
                : '<p class="billing-help">Comprovantes e distribuições aparecerão aqui durante a leitura.</p>';
        }
    };

    const rebuildBatchSelection = () => {
        selected.clear();
        batches.forEach((batch) =>
            batch.ids.forEach((id) => selected.add(Number(id)))
        );
        updateCount();
        renderBatches();
    };

    const recipientToggle = () => {
        const type = form.querySelector(
            '[name="recipient_type"]:checked'
        )?.value;
        qa("[data-recipient-field]").forEach((element) => {
            element.hidden = element.dataset.recipientField !== type;
        });
    };

    const populateRecipients = (data, preserve = true) => {
        const customer = preserve ? form.customer_id.value : "";
        const organization = preserve ? form.organization_id.value : "";

        form.customer_id.innerHTML =
            '<option value="">Selecione um cliente</option>' +
            (data.customers || [])
                .map(
                    (item) =>
                        `<option value="${item.id}">${escapeHtml(
                            item.name
                        )}</option>`
                )
                .join("");

        form.organization_id.innerHTML =
            '<option value="">Selecione uma organização</option>' +
            (data.organizations || [])
                .map(
                    (item) =>
                        `<option value="${item.id}">${escapeHtml(
                            item.name
                        )}</option>`
                )
                .join("");

        if (
            [...form.customer_id.options].some(
                (option) => option.value === customer
            )
        ) {
            form.customer_id.value = customer;
        }

        if (
            [...form.organization_id.options].some(
                (option) => option.value === organization
            )
        ) {
            form.organization_id.value = organization;
        }
    };

    const loadRecipientsForProjects = async () => {
        const requestId = ++recipientRequest;
        const params = new URLSearchParams();
        selectedProjectIds().forEach((id) =>
            params.append("project_ids[]", id)
        );

        if (!params.has("project_ids[]")) {
            populateRecipients({ customers: [], organizations: [] }, false);
            return;
        }

        try {
            const data = await request(`${root.dataset.contextUrl}?${params}`, {
                method: "GET",
                headers: { "Content-Type": "application/json" },
            });

            if (requestId !== recipientRequest) return;

            populateRecipients(data);

            if (
                !(data.customers || []).length &&
                !(data.organizations || []).length
            ) {
                message(
                    "Os projetos selecionados não possuem distribuições elegíveis para faturamento.",
                    true
                );
            }
        } catch (error) {
            if (requestId === recipientRequest) message(error.message, true);
        }
    };

    const selectionPayload = () => ({
        ...validateContext(),
        mode,
        receipt_codes: q("[data-receipt-codes]")
            .value.split(/[\n,;]+/)
            .map((value) => value.trim())
            .filter(Boolean),
        distribution_ids: [...selected],
    });

    const loadSelection = async () => {
        message("");

        try {
            const result = await request(root.dataset.selectUrl, {
                method: "POST",
                body: JSON.stringify(selectionPayload()),
            });

            if (mode === "receipts") {
                batches.clear();
                (result.batches || []).forEach((batch) => {
                    const document = batch.documents?.[0] || {};
                    batches.set(batch.key, {
                        key: batch.key,
                        display: batch.label,
                        number: document.number,
                        associate: document.associate,
                        distributions: document.distributions || [],
                        ids: (batch.selected_ids || []).map(Number),
                        excluded: Number(batch.excluded_count || 0),
                    });
                });
                rebuildBatchSelection();
            } else {
                selected.clear();
                (result.selected_ids || []).forEach((id) =>
                    selected.add(Number(id))
                );
                updateCount();
            }

            const excluded = result.excluded_count
                ? ` ${result.excluded_count} item(ns) incompatível(is) foram ignorados.`
                : "";

            message(
                `${selected.size} distribuição(ões) selecionada(s).${excluded}`,
                result.excluded_count > 0
            );
        } catch (error) {
            message(error.message, true);
        }
    };

    const loadManual = async (page = 1) => {
        try {
            const params = new URLSearchParams();
            const data = validateContext();

            data.project_ids.forEach((id) =>
                params.append("project_ids[]", id)
            );
            ["customer_id", "organization_id", "from_date", "to_date"].forEach(
                (key) => {
                    if (data[key]) params.set(key, data[key]);
                }
            );

            params.set("page", page);
            params.set("search", q("[data-manual-search]").value || "");

            const result = await request(
                `${root.dataset.manualUrl}?${params}`,
                {
                    method: "GET",
                    headers: { "Content-Type": "application/json" },
                }
            );

            const pager = result.distributions;

            q("[data-manual-rows]").innerHTML =
                (pager.data || [])
                    .map(
                        (row) => `
                <tr>
                    <td><input type="checkbox" data-pick="${row.id}" ${
                            selected.has(Number(row.id)) ? "checked" : ""
                        } aria-label="Selecionar distribuição ${row.id}"></td>
                    <td>${escapeHtml(row.date)}</td>
                    <td>${escapeHtml(row.producer)}</td>
                    <td>${escapeHtml(row.product)}</td>
                    <td>${escapeHtml(row.quantity)} ${escapeHtml(row.unit)}</td>
                    <td>${escapeHtml(row.recipient)}</td>
                </tr>
            `
                    )
                    .join("") ||
                '<tr><td class="billing-empty-row" colspan="6">Nenhuma distribuição elegível.</td></tr>';

            q("[data-manual-pagination]").innerHTML = `
                <span>Página ${pager.current_page} de ${pager.last_page} · ${
                pager.total
            } item(ns)</span>
                <div class="billing-pagination-actions">
                    <button class="billing-btn" type="button" data-manual-page="${
                        pager.current_page - 1
                    }" ${
                pager.current_page <= 1 ? "disabled" : ""
            }>Anterior</button>
                    <button class="billing-btn" type="button" data-manual-page="${
                        pager.current_page + 1
                    }" ${
                pager.current_page >= pager.last_page ? "disabled" : ""
            }>Próxima</button>
                </div>
            `;
        } catch (error) {
            message(error.message, true);
        }
    };

    const loadPreview = async () => {
        validateContext();
        if (!selected.size)
            throw new Error(
                "Selecione ao menos uma distribuição antes de continuar."
            );

        preview = await request(root.dataset.previewUrl, {
            method: "POST",
            body: JSON.stringify(values()),
        });

        selected.clear();
        (preview.selected_ids || []).forEach((id) => selected.add(Number(id)));
        updateCount();
        renderPreview();
        return preview;
    };

    const renderPreview = () => {
        q(
            "[data-review-count]"
        ).textContent = `${preview.summary.distributions} distribuição(ões) · ${preview.summary.producers} produtor(es)`;

        q("[data-review-rows]").innerHTML = preview.distributions
            .map(
                (row) => `
            <tr>
                <td>${escapeHtml(row.date)}</td>
                <td>${escapeHtml(row.producer)}</td>
                <td>${escapeHtml(row.product)}</td>
                <td>${escapeHtml(row.quantity)} ${escapeHtml(row.unit)}</td>
                <td>${escapeHtml(row.recipient)}</td>
                <td>
                    <button class="billing-link-btn" type="button" data-remove-id="${
                        row.id
                    }" aria-label="Remover distribuição ${
                    row.id
                }" title="Remover">
                        <i class="ph ph-x" aria-hidden="true"></i>
                    </button>
                </td>
            </tr>
        `
            )
            .join("");
        renderReportAnnotations();

        const reasons = Object.entries(preview.exclusion_reasons || {});
        q("[data-exclusions]").innerHTML = reasons.length
            ? `<div class="billing-alert is-error"><strong>Itens ignorados</strong><ul>${reasons
                  .map(
                      ([reason, count]) =>
                          `<li>${escapeHtml(
                              reason.replaceAll("_", " ")
                          )}: ${count}</li>`
                  )
                  .join("")}</ul></div>`
            : "";

        q("[data-preview-summary]").innerHTML = `
            <article><span>Distribuições</span><strong>${preview.summary.distributions}</strong></article>
            <article><span>Produtores</span><strong>${preview.summary.producers}</strong></article>
            <article><span>Documentos de origem</span><strong>${preview.summary.source_receipts}</strong></article>
        `;

        q("[data-financial-lines]").innerHTML =
            preview.lines
                .map(
                    (line) => `
            <tr>
                <td>${escapeHtml(line.product)}</td>
                <td>${escapeHtml(line.quantity)}</td>
                <td>${escapeHtml(line.unit)}</td>
                <td>${money(line.unit_price)}</td>
                <td>${money(line.document_amount)}</td>
            </tr>
        `
                )
                .join("") ||
            '<tr><td class="billing-empty-row" colspan="5">Sem linhas.</td></tr>';

        q("[data-financial-fees]").innerHTML = preview.fees.length
            ? `<div class="billing-fee-list">${preview.fees
                  .map(
                      (fee) => `
                <div>
                    <span>${escapeHtml(fee.name || "Ajuste")} · ${escapeHtml(
                          fee.nature === "accrual" ? "Acréscimo" : "Desconto"
                      )}</span>
                    <strong>${money(fee.amount)}</strong>
                </div>
            `
                  )
                  .join("")}</div>`
            : '<p class="billing-help">Nenhuma taxa, desconto ou acréscimo configurado.</p>';

        q("[data-financial-totals]").innerHTML = `
            <div><span>Bruto</span><strong>${money(
                preview.totals.gross
            )}</strong></div>
            <div><span>Ajustes líquidos</span><strong>${money(
                preview.totals.fees
            )}</strong></div>
            <div class="is-total"><span>Total a cobrar</span><strong>${money(
                preview.totals.net
            )}</strong></div>
        `;
    };

    const save = async () => {
        const button = q("[data-save]");
        try {
            button.disabled = true;
            button.setAttribute("aria-busy", "true");
            await loadPreview();
            const payload = {
                ...values(),
                operation_key: operationKey,
            };

            const result = await request(root.dataset.saveUrl, {
                method: root.dataset.saveMethod,
                body: JSON.stringify(payload),
            });

            message(result.message || "Rascunho salvo.");
            if (result.redirect_url) location.assign(result.redirect_url);
        } catch (error) {
            message(error.message, true);
        } finally {
            button.disabled = false;
            button.removeAttribute("aria-busy");
        }
    };

    const freeze = () => {
        openDialog(freezeDialog);
    };

    const confirmFreeze = async () => {
        closeDialog(freezeDialog);
        const button = q("[data-freeze]");

        try {
            button.disabled = true;
            button.setAttribute("aria-busy", "true");
            message("Salvando, validando e emitindo o faturamento...");
            await loadPreview();

            const result = await request(root.dataset.saveUrl, {
                method: root.dataset.saveMethod,
                body: JSON.stringify({
                    ...values(),
                    operation_key: operationKey,
                    finalize: true,
                }),
            });
            location.assign(result.redirect_url);
        } catch (error) {
            message(error.message, true);
            button.disabled = false;
            button.removeAttribute("aria-busy");
        }
    };

    const addReceiptBatch = async (code) => {
        const feedback = q("[data-scan-feedback]");
        if (feedback) {
            feedback.classList.remove("is-error");
            feedback.textContent =
                "Conferindo QR Code e selecionando as distribuições...";
        }

        try {
            const result = await request(root.dataset.selectUrl, {
                method: "POST",
                body: JSON.stringify({
                    ...validateContext(),
                    mode: "receipts",
                    receipt_codes: [code],
                    distribution_ids: [],
                }),
            });

            const batch = result.batches?.[0];
            if (!batch?.receipt_found) {
                throw new Error(
                    "Este QR Code não corresponde a um documento de origem desta organização."
                );
            }

            const document = batch.documents?.[0] || {};
            batches.set(batch.key, {
                key: batch.key,
                display: batch.label,
                number: document.number,
                associate: document.associate,
                distributions: document.distributions || [],
                ids: (batch.selected_ids || []).map(Number),
                excluded: Number(batch.excluded_count || 0),
            });

            const lines = q("[data-receipt-codes]")
                .value.split(/\n+/)
                .map((value) => value.trim())
                .filter(Boolean);

            if (!lines.includes(code)) lines.push(code);
            q("[data-receipt-codes]").value = lines.join("\n");

            rebuildBatchSelection();

            if (feedback) {
                feedback.textContent = `Lote adicionado: ${batch.selected_count} distribuição(ões). Continue apontando para outros comprovantes.`;
            }
            window.document.dispatchEvent(new CustomEvent("accounting:qr-success", {
                detail: {
                    number: document.number || batch.label,
                    associate: document.associate || "",
                    count: Number(batch.selected_count || 0),
                },
            }));
        } catch (error) {
            if (feedback) {
                feedback.textContent = error.message;
                feedback.classList.add("is-error");
            } else {
                message(error.message, true);
            }
        }
    };

    const init = async () => {
        try {
            const data = await request(root.dataset.contextUrl, {
                method: "GET",
                headers: { "Content-Type": "application/json" },
            });

            projects = data.projects || [];
            projectSelect.innerHTML = projects
                .map(
                    (project) => `
                <option value="${project.id}">${escapeHtml(project.name)}${
                        project.code ? ` · ${escapeHtml(project.code)}` : ""
                    }</option>
            `
                )
                .join("");

            populateRecipients(data, false);
            form.issued_at.value = new Date().toISOString().slice(0, 10);

            if (data.draft) {
                const draftProjects = new Set(
                    (data.draft.project_ids || []).map(Number)
                );
                syncProjectSelect(draftProjects);

                const type = data.draft.organization_id
                    ? "organization"
                    : "customer";
                form.querySelector(
                    `[name="recipient_type"][value="${type}"]`
                ).checked = true;

                form.organization_id.value = data.draft.organization_id || "";
                form.customer_id.value = data.draft.customer_id || "";

                ["issued_at", "from_date", "to_date", "notes"].forEach(
                    (key) => {
                        form[key].value = data.draft[key] || "";
                    }
                );
                reportAnnotations = data.draft.report_annotations || [];
                form.report_annotations_position.value = data.draft.report_annotations_position || 'after';
                renderReportAnnotations();

                (data.draft.distribution_ids || []).forEach((id) =>
                    selected.add(Number(id))
                );
                recipientToggle();
                updateCount();

                if (draftProjects.size) {
                    await loadRecipientsForProjects();
                    form.organization_id.value =
                        data.draft.organization_id || "";
                    form.customer_id.value = data.draft.customer_id || "";
                }
            } else {
                renderProjectSummary();
                recipientToggle();
                updateCount();
            }

            renderBatches();
            setStep(1);
        } catch (error) {
            message(error.message, true);
        }
    };

    root.addEventListener("click", async (event) => {
        const button = event.target.closest("button");
        if (!button) return;

        if (button.matches("[data-step-button]")) {
            const wanted = Number(button.dataset.stepButton);
            if (wanted <= step) setStep(wanted);
            return;
        }

        if (button.matches("[data-next]")) {
            try {
                validateContext();
                if (step === 2 || step === 3) await loadPreview();
                setStep(step + 1);
                message("");
            } catch (error) {
                message(error.message, true);
            }
            return;
        }

        if (button.matches("[data-previous]")) {
            setStep(step - 1);
            return;
        }

        if (button.matches("[data-open-project-picker]")) {
            openProjectPicker();
            return;
        }

        if (
            button.matches(
                "[data-close-project-picker], [data-cancel-project-picker]"
            )
        ) {
            closeDialog(projectDialog);
            return;
        }

        if (button.matches("[data-project-option]")) {
            const id = Number(button.dataset.projectOption);
            projectDraft.has(id)
                ? projectDraft.delete(id)
                : projectDraft.add(id);
            renderProjectOptions();
            return;
        }

        if (button.matches("[data-clear-project-picker]")) {
            projectDraft.clear();
            renderProjectOptions();
            return;
        }

        if (button.matches("[data-apply-project-picker]")) {
            await applyProjectPicker();
            return;
        }

        if (button.matches("[data-remove-project]")) {
            await removeProject(button.dataset.removeProject);
            return;
        }

        if (button.matches("[data-mode]")) {
            mode = button.dataset.mode;
            qa("[data-mode]").forEach((item) =>
                item.classList.toggle("is-active", item === button)
            );
            qa("[data-mode-panel]").forEach((panel) => {
                panel.hidden = panel.dataset.modePanel !== mode;
            });
            if (mode === "manual") loadManual();
            return;
        }

        if (button.matches("[data-load-selection]")) {
            loadSelection();
            return;
        }

        if (button.matches("[data-manual-load]")) {
            loadManual();
            return;
        }

        if (button.matches("[data-manual-page]")) {
            loadManual(Number(button.dataset.manualPage));
            return;
        }

        if (button.matches("[data-remove-id]")) {
            selected.delete(Number(button.dataset.removeId));
            updateCount();
            try {
                await loadPreview();
            } catch (error) {
                setStep(2);
                message(error.message, true);
            }
            return;
        }

        if (button.matches('[data-add-report-annotation]')) {
            readReportAnnotations();
            if (reportAnnotations.length >= 30) return;
            reportAnnotations.push({target: 'global', text: ''});
            renderReportAnnotations();
            return;
        }

        if (button.matches('[data-remove-report-annotation]')) {
            readReportAnnotations();
            reportAnnotations.splice(Number(button.dataset.removeReportAnnotation), 1);
            q('[data-report-annotations]').replaceChildren();
            renderReportAnnotations();
            return;
        }

        if (button.matches("[data-remove-batch]")) {
            batches.delete(button.dataset.removeBatch);
            rebuildBatchSelection();
            q("[data-receipt-codes]").value = [...batches.values()]
                .map((batch) => batch.display)
                .join("\n");
            return;
        }

        if (button.matches("[data-save]")) {
            save();
            return;
        }

        if (button.matches("[data-freeze]")) {
            freeze();
            return;
        }

        if (button.matches("[data-cancel-freeze]")) {
            closeDialog(freezeDialog);
            return;
        }

        if (button.matches("[data-confirm-freeze]")) {
            confirmFreeze();
        }
    });

    root.addEventListener("input", (event) => {
        if (event.target.matches("[data-project-search]")) {
            renderProjectOptions();
        }
    });

    root.addEventListener("keydown", (event) => {
        if (
            event.target.matches("[data-manual-search]") &&
            event.key === "Enter"
        ) {
            event.preventDefault();
            loadManual();
        }
    });

    root.addEventListener("accounting:qr-code", async (event) => {
        await addReceiptBatch(event.detail.code);
        event.detail.onComplete?.();
    });

    root.addEventListener("accounting:qr-native-config", (event) => {
        try {
            event.detail.verificationUrl = root.dataset.selectUrl;
            event.detail.csrfToken = token;
            event.detail.selectionPayload = {
                ...validateContext(),
                mode: "receipts",
                receipt_codes: [],
                distribution_ids: [],
            };
        } catch (error) {
            event.detail.error = error;
        }
    });

    root.addEventListener("change", (event) => {
        if (event.target.matches('[name="recipient_type"]')) {
            recipientToggle();
            resetDistributionSelection();
        }

        if (
            event.target.matches(
                '[name="organization_id"], [name="customer_id"], [name="from_date"], [name="to_date"]'
            )
        ) {
            resetDistributionSelection();
        }

        if (event.target.matches("[data-pick]")) {
            const id = Number(event.target.dataset.pick);
            event.target.checked ? selected.add(id) : selected.delete(id);
            updateCount();
        }
    });

    [projectDialog, freezeDialog].forEach((dialog) => {
        if (!dialog) return;

        dialog.addEventListener("cancel", (event) => {
            event.preventDefault();
            closeDialog(dialog);
        });

        dialog.addEventListener("click", (event) => {
            if (event.target === dialog) closeDialog(dialog);
        });
    });

    window.addEventListener("popstate", () => {
        if (activeDialog?.open) {
            dialogHistoryPushed = false;
            activeDialog.close();
            activeDialog = null;
        }
    });

    init();
})();
