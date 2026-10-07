import"../../qr-scanner-core-CTBFOQOA.js";(function(){const y=document.querySelector("[data-accounting-page]");if(!y)return;const A=y.dataset.accountingPage,p=new Intl.NumberFormat("pt-BR",{style:"currency",currency:"BRL"}),P=new Intl.NumberFormat("pt-BR",{minimumFractionDigits:0,maximumFractionDigits:4}),e=a=>String(a??"").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;").replace(/'/g,"&#039;"),F={"circle-check":"ph-check-circle","chevron-left":"ph-caret-left","chevron-right":"ph-caret-right","list-tree":"ph-tree-structure","file-text":"ph-file-text","alert-triangle":"ph-warning","circle-alert":"ph-warning-circle",clock:"ph-clock",wallet:"ph-wallet",receipt:"ph-receipt","receipt-text":"ph-receipt",files:"ph-files","file-clock":"ph-file-dashed",send:"ph-paper-plane-tilt",search:"ph-magnifying-glass","folder-search":"ph-folder-open",settings:"ph-gear",printer:"ph-printer","key-round":"ph-key","external-link":"ph-arrow-square-out","pencil-line":"ph-pencil-line",x:"ph-x"},V=a=>{const s=String(a||"").trim();return s?s.startsWith("ph-")?s:F[s]||`ph-${s}`:"ph-circle"},r=a=>`
        <i
            class="ph-fill ${e(V(a))}"
            aria-hidden="true"
        ></i>
    `;(()=>{if(document.querySelector('link[href*="@phosphor-icons"][href*="fill"]'))return;const a=document.createElement("link");a.rel="stylesheet",a.href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/fill/style.css",document.head.appendChild(a)})();const S=(a,s="neutral")=>`
        <span
            class="acc-badge acc-badge-${e(s)}"
        >
            ${e(a)}
        </span>
    `,q=(a=4)=>Array.from({length:a},()=>'<div class="acc-skeleton"></div>').join("");async function L(a,s){const t=await fetch(a,{headers:{Accept:"application/json","X-Requested-With":"XMLHttpRequest"},credentials:"same-origin",cache:"no-store",signal:s}),c=await t.json().catch(()=>({}));if(!t.ok)throw new Error(c.message||"Não foi possível carregar os dados.");return c}async function D(a,s){const t=document.querySelector('meta[name="csrf-token"]')?.content||"",c=await fetch(a,{method:"POST",headers:{Accept:"application/json","Content-Type":"application/json","X-Requested-With":"XMLHttpRequest","X-CSRF-TOKEN":t},credentials:"same-origin",cache:"no-store",body:JSON.stringify(s||{})}),o=await c.json().catch(()=>({}));if(!c.ok){const d=Array.isArray(o.issues)?o.issues.map(m=>m.message).join(" "):"";throw new Error(d||o.message||"Não foi possível concluir a ação.")}return o}function x(a,s){a&&(a.innerHTML=`
            <div
                class="acc-error"
                role="alert"
            >
                ${e(s.message||s)}
            </div>
        `)}function j(a,s){const t=new URL(a,window.location.origin);return Object.entries(s||{}).forEach(([c,o])=>{o!==""&&o!==null&&o!==void 0&&t.searchParams.set(c,o)}),t.toString()}const U=a=>encodeURIComponent(String(a??"")),M=(a,s="Copiar",t="")=>`
        <button
            class="acc-button acc-copy-button ${e(t)}"
            type="button"
            data-copy-text="${e(U(a))}"
        >
            ${r("ph-copy")}

            <span data-copy-label>
                ${e(s)}
            </span>
        </button>
    `;async function I(a){const s=String(a??"");if(navigator.clipboard?.writeText){await navigator.clipboard.writeText(s);return}const t=document.createElement("textarea");t.value=s,t.setAttribute("readonly",""),t.style.position="fixed",t.style.opacity="0",t.style.pointerEvents="none",document.body.appendChild(t),t.select();try{document.execCommand("copy")}finally{t.remove()}}function O(a){const s=a.querySelector("[data-copy-label]"),t=s?.textContent||"Copiar";a.classList.add("is-copied"),s&&(s.textContent="Copiado"),window.clearTimeout(a._copyTimer),a._copyTimer=window.setTimeout(()=>{a.classList.remove("is-copied"),s&&(s.textContent=t)},1400)}document.addEventListener("click",async a=>{const s=a.target.closest("[data-copy-text], [data-copy-value]");if(!s)return;a.preventDefault(),a.stopPropagation();const t=s.dataset.copyText?decodeURIComponent(s.dataset.copyText):s.dataset.copyValue||"";try{await I(t),O(s)}catch{const o=s.querySelector("[data-copy-label]");o&&(o.textContent="Falhou",window.setTimeout(()=>{o.textContent="Copiar"},1400))}});async function Q(){const a=document.querySelector("[data-queue-list]"),s=document.querySelector("[data-queue-summary]"),t=document.querySelector("[data-queue-total]");if(!a||!s)return;a.innerHTML=q(4);const c=d=>{const m=String(d?.label||"").toLowerCase();if(/erro|integridade|corrigir|falha|inconsist/.test(m))return"ph-warning-circle";if(/rascunho|prepar|completar/.test(m))return"ph-receipt";if(/autoriza|aprova/.test(m))return"ph-check-circle";if(/saldo|receb|pagamento/.test(m))return"ph-clock-countdown";switch(String(d?.tone||"")){case"danger":case"warning":return"ph-warning-circle";case"success":return"ph-check-circle";case"info":case"cyan":return"ph-folder-open";case"violet":case"purple":return"ph-receipt";default:return"ph-folder"}},o=d=>{switch(String(d?.tone||"")){case"danger":return"Corrigir";case"warning":return"Preparar";case"success":return"Pronto";case"info":case"cyan":return"Acompanhar";case"violet":case"purple":return"Revisar";default:return"Pendente"}};try{const d=await L(y.dataset.queueUrl),m=y.dataset.processesUrl,w=Number(d.summary?.open_processes||0),k=Number(d.summary?.open_amount||0),g=d.summary?.workflow_label||"Fluxo contábil",n=d.queue||[],l=n.reduce((i,u)=>i+Number(u.count||0),0);if(t&&(t.innerHTML=`
                    <i
                        class="ph-fill ph-folder"
                        aria-hidden="true"
                    ></i>

                    ${e(l)}
                `),s.innerHTML=`
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
                            ${p.format(k)}
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
                                ${e(w)}
                            </strong>
                        </span>

                        <span>
                            ${e(g)}
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
                                ${e(w)}
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
                                ${e(g)}
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
                                        ${e(w)}
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
                                        ${e(l)}
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
                                        ${e(g)}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `,d.empty||!n.length){a.innerHTML=`
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
                `;return}a.innerHTML=n.map(i=>{const u=j(m,i.filters),$=String(i.tone||"neutral"),f=c(i),E=o(i);return`
                                <a
                                    class="acc-queue-row acc-tone-${e($)}"
                                    href="${e(u)}"
                                >
                                    <span class="queue-main">
                                        <span
                                            class="acc-queue-icon"
                                            aria-hidden="true"
                                        >
                                            <i
                                                class="ph-fill ${e(f)}"
                                            ></i>
                                        </span>

                                        <span class="acc-queue-copy">
                                            <strong>
                                                ${e(i.label)}
                                            </strong>

                                            <span>
                                                Abrir processos relacionados
                                            </span>
                                        </span>
                                    </span>

                                    <span class="queue-status">
                                        ${e(E)}
                                    </span>

                                    <span class="acc-queue-count">
                                        ${e(i.count)}
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
                            `}).join("")}catch(d){t&&(t.innerHTML=`
                    <i
                        class="ph-fill ph-warning-circle"
                        aria-hidden="true"
                    ></i>

                    —
                `),x(a,d)}}function B(a){const s=a.critical_issues?S(`${a.critical_issues} erro(s)`,"danger"):a.preparation_issues?S(`${a.preparation_issues} item(ns)`,"warning"):S("Pronto","success");return`
            <tr>
                <td>
                    <a
                        class="acc-link"
                        href="${e(a.url)}"
                    >
                        ${e(a.number)}
                    </a>

                    <div class="acc-muted">
                        ${e(a.issued_at||"")}
                    </div>
                </td>

                <td>
                    <strong>
                        ${e(a.project)}
                    </strong>

                    <div class="acc-muted">
                        ${e(a.project_code||"")}
                    </div>
                </td>

                <td>
                    ${e(a.recipient)}

                    <div class="acc-muted">
                        ${e(a.recipient_type)}
                    </div>
                </td>

                <td>
                    ${S(a.state.label,a.state.tone)}

                    <div class="acc-muted">
                        ${e(a.state.next_action)}
                    </div>
                </td>

                <td class="acc-money">
                    ${p.format(a.net||0)}

                    <div class="acc-muted">
                        Saldo
                        ${p.format(a.remaining||0)}
                    </div>
                </td>

                <td>
                    ${s}
                </td>

                <td class="process-action-column">
                    <a
                        class="process-open"
                        href="${e(a.url)}"
                        aria-label="Acessar ${e(a.number)}"
                    >
                        Acessar

                        <i
                            class="ph-fill ph-arrow-right"
                            aria-hidden="true"
                        ></i>
                    </a>
                </td>
            </tr>
        `}function W(a){const s=a.critical_issues?`${e(a.critical_issues)} erro(s)`:a.preparation_issues?`${e(a.preparation_issues)} item(ns) a completar`:"Pronto";return`
            <article
                class="acc-mobile-row"
                data-tone="${e(a.state.tone||"neutral")}"
            >
                <div class="acc-mobile-head">
                    <div class="mobile-process-main">
                        <a
                            class="acc-link"
                            href="${e(a.url)}"
                        >
                            ${e(a.number)}
                        </a>

                        <span class="acc-muted">
                            ${e(a.project)}
                        </span>
                    </div>

                    ${S(a.state.label,a.state.tone)}
                </div>

                <div class="acc-mobile-meta">
                    <span>
                        Destinatário

                        <strong>
                            ${e(a.recipient)}
                        </strong>
                    </span>

                    <span>
                        Valor líquido

                        <strong>
                            ${p.format(a.net||0)}
                        </strong>
                    </span>

                    <span>
                        Próxima ação

                        <strong>
                            ${e(a.state.next_action)}
                        </strong>
                    </span>

                    <span>
                        Conferência

                        <strong>
                            ${s}
                        </strong>
                    </span>
                </div>

                <div class="mobile-process-actions">
                    <a
                        class="process-open"
                        href="${e(a.url)}"
                    >
                        Acessar

                        <i
                            class="ph-fill ph-arrow-right"
                            aria-hidden="true"
                        ></i>
                    </a>
                </div>
            </article>
        `}async function X(){const a=document.querySelector("[data-process-filters]"),s=document.querySelector("[data-process-table]"),t=document.querySelector("[data-process-mobile]"),c=document.querySelector("[data-process-pagination]");if(!a||!s||!t||!c)return;const o=a.elements.project,d=a.elements.organization,m=a.elements.customer;let w,k=!1;const g=new URLSearchParams(window.location.search);["search","project","organization","customer","from","until","financial_status","authorization_status","fiscal_status","accountability_status","pending"].forEach(i=>{g.has(i)&&a.elements[i]&&(a.elements[i].value=g.get(i))});async function n(i=1){w?.abort(),w=new AbortController,s.innerHTML=`
                <tr>
                    <td colspan="7">
                        ${q(5)}
                    </td>
                </tr>
            `,t.innerHTML=q(5),c.innerHTML="";const u=Object.fromEntries(new FormData(a).entries());u.page=i;try{const $=await L(j(y.dataset.processesDataUrl,u),w.signal),f=$.processes;k||(o&&(o.innerHTML='<option value="">Todos os projetos</option>'+($.filters?.projects||[]).map(h=>`
                                        <option value="${e(h.id)}">
                                            ${e(h.label)}
                                        </option>
                                    `).join(""),o.value=u.project||""),d&&(d.innerHTML='<option value="">Todas as organizações</option>'+($.filters?.organizations||[]).map(h=>`
                                        <option value="${e(h.id)}">
                                            ${e(h.label)}
                                        </option>
                                    `).join(""),d.value=u.organization||""),m&&(m.innerHTML='<option value="">Todos os clientes</option>'+($.filters?.customers||[]).map(h=>`
                                        <option value="${e(h.id)}">
                                            ${e(h.label)}
                                        </option>
                                    `).join(""),m.value=u.customer||""),k=!0),f.data.length?(s.innerHTML=f.data.map(B).join(""),t.innerHTML=f.data.map(W).join("")):(s.innerHTML=`
                        <tr>
                            <td colspan="7">
                                <div class="acc-empty">
                                    Nenhum processo encontrado.
                                </div>
                            </td>
                        </tr>
                    `,t.innerHTML=`
                        <div class="acc-empty">
                            Nenhum processo encontrado.
                        </div>
                    `),c.innerHTML=`
                    <span>
                        ${e(f.from||0)}
                        –
                        ${e(f.to||0)}
                        de
                        ${e(f.total||0)}
                    </span>

                    <div class="acc-pagination-actions">
                        <button
                            class="acc-button"
                            type="button"
                            data-page="${f.current_page-1}"
                            ${f.current_page<=1?"disabled":""}
                        >
                            ${r("ph-caret-left")}

                            Anterior
                        </button>

                        <button
                            class="acc-button"
                            type="button"
                            data-page="${f.current_page+1}"
                            ${f.current_page>=f.last_page?"disabled":""}
                        >
                            Próxima

                            ${r("ph-caret-right")}
                        </button>
                    </div>
                `,c.querySelectorAll("[data-page]").forEach(h=>h.addEventListener("click",()=>n(Number(h.dataset.page))));const E=new URL(window.location.href);Object.entries(u).forEach(([h,v])=>v?E.searchParams.set(h,v):E.searchParams.delete(h)),E.searchParams.delete("page"),history.replaceState({},"",E)}catch($){$.name!=="AbortError"&&(s.innerHTML=`
                        <tr>
                            <td colspan="7">
                                <div class="acc-error">
                                    ${e($.message)}
                                </div>
                            </td>
                        </tr>
                    `,x(t,$))}}let l;a.addEventListener("input",i=>{clearTimeout(l),l=setTimeout(()=>n(1),i.target.name==="search"?320:0)}),a.addEventListener("change",i=>{i.target.matches('select, input[type="date"]')&&n(1)}),a.addEventListener("submit",i=>{i.preventDefault(),n(1)}),a.querySelector("[data-clear-filters]")?.addEventListener("click",()=>{a.reset(),n(1)}),n(Number(g.get("page")||1))}function N(a){const s=a.data||[];if(!s.length)return`
                <div class="acc-empty">
                    Nenhuma distribuição vinculada.
                </div>
            `;const t=`
            <div class="acc-data-table-block acc-data-table-distributions">
                <div class="acc-table-tools">
                    <div class="acc-table-tools-copy">
                        ${r("ph-arrows-left-right")}

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
                            ${s.map(c=>{const o=[`Data: ${c.parent?.date||"Sem origem"}`,`Entrega: ${c.parent?.id?`#${c.parent.id}`:"—"}`,`Produto: ${c.product||""}`,`Destino: ${c.customer||""}`,`Membro: ${c.member||""}`,`Quantidade: ${P.format(c.quantity||0)} ${c.unit||""}`.trim(),`Valor: ${p.format(c.gross_value||0)}`,`Preço unitário: ${p.format(c.unit_price||0)} / ${c.unit||""}`].join(`
`);return`
                                                <tr>
                                                    <td>
                                                        ${e(c.parent?.date||"Sem origem")}

                                                        <div class="acc-muted">
                                                            Entrega #
                                                            ${e(c.parent?.id||"—")}
                                                        </div>
                                                    </td>

                                                    <td>
                                                        <strong>
                                                            ${e(c.product)}
                                                        </strong>

                                                        <div class="acc-muted">
                                                            ${e(c.customer)}
                                                        </div>
                                                    </td>

                                                    <td>
                                                        ${e(c.member)}
                                                    </td>

                                                    <td>
                                                        ${P.format(c.quantity||0)}

                                                        ${e(c.unit)}
                                                    </td>

                                                    <td class="acc-money">
                                                        ${p.format(c.gross_value||0)}

                                                        <div class="acc-muted">
                                                            ${p.format(c.unit_price||0)}

                                                            /

                                                            ${e(c.unit)}
                                                        </div>
                                                    </td>

                                                    <td class="acc-copy-column">
                                                        ${M(o,"Copiar","acc-button-icon-copy")}
                                                    </td>
                                                </tr>
                                            `}).join("")}
                        </tbody>
                    </table>
                </div>
            </div>
        `;return(a.last_page||1)<=1?t:t+`
            <div class="acc-pagination">
                <span>
                    ${e(a.from||0)}
                    –
                    ${e(a.to||0)}
                    de
                    ${e(a.total||0)}
                </span>

                <div class="acc-pagination-actions">
                    <button
                        class="acc-button"
                        type="button"
                        data-dist-page="${a.current_page-1}"
                        ${a.current_page<=1?"disabled":""}
                    >
                        ${r("ph-caret-left")}

                        Anterior
                    </button>

                    <button
                        class="acc-button"
                        type="button"
                        data-dist-page="${a.current_page+1}"
                        ${a.current_page>=a.last_page?"disabled":""}
                    >
                        Próxima

                        ${r("ph-caret-right")}
                    </button>
                </div>
            </div>
        `}function J(a){return a?.length?`
            <div class="acc-data-table-block acc-data-table-delivered">
                <div class="acc-table-tools">
                    <div class="acc-table-tools-copy">
                        ${r("ph-package")}

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
                            ${a.map(s=>{const t=[`Produto: ${s.product||""}`,s.project?`Projeto: ${s.project}`:"",`Quantidade: ${P.format(Number(s.quantity||0))} ${s.unit||""}`.trim(),`Preço unitário: ${p.format(Number(s.unit_price||0))}`,`Valor documental: ${p.format(Number(s.document_amount||0))}`].filter(Boolean).join(`
`);return`
                                                <tr>
                                                    <td>
                                                        <strong>
                                                            ${e(s.product)}
                                                        </strong>

                                                        <div class="acc-muted">
                                                            ${e(s.project||"")}
                                                        </div>
                                                    </td>

                                                    <td>
                                                        ${P.format(Number(s.quantity||0))}

                                                        ${e(s.unit)}
                                                    </td>

                                                    <td class="acc-money">
                                                        ${p.format(Number(s.unit_price||0))}
                                                    </td>

                                                    <td class="acc-money">
                                                        <strong>
                                                            ${p.format(Number(s.document_amount||0))}
                                                        </strong>
                                                    </td>

                                                    <td class="acc-copy-column">
                                                        ${M(t,"Copiar","acc-button-icon-copy")}
                                                    </td>
                                                </tr>
                                            `}).join("")}
                        </tbody>
                    </table>
                </div>
            </div>
        `:`
                <div class="acc-empty">
                    Este registro legado ainda não possui
                    linhas consolidadas no snapshot.
                </div>
            `}function K(a){return a?.length?`
            <ul class="acc-simple-list">
                ${a.map(s=>`
                                <li class="acc-simple-item">
                                    <strong>
                                        Versão
                                        ${e(s.sequence)}
                                        ·
                                        ${e(s.label)}
                                    </strong>

                                    ${s.organization?`
                                                <span>
                                                    Organização:
                                                    ${e(s.organization)}
                                                </span>
                                            `:""}

                                    <span>
                                        Enviada em
                                        ${e(s.sent_at||"—")}
                                        por
                                        ${e(s.sent_by||"Membro não identificado")}
                                    </span>

                                    ${s.responded_at?`
                                                <span>
                                                    Resposta em
                                                    ${e(s.responded_at)}
                                                    por
                                                    ${e(s.responded_by||"Representante autorizado")}
                                                </span>
                                            `:""}

                                    ${s.validity?`
                                                <span>
                                                    Validade atual:
                                                    ${e(s.validity)}
                                                </span>
                                            `:""}

                                    ${s.message?`
                                                <p class="acc-auth-message">
                                                    ${e(s.message)}
                                                </p>
                                            `:""}

                                    ${s.invalidation_reason?`
                                                <p class="acc-auth-message acc-auth-warning">
                                                    ${e(s.invalidation_reason)}
                                                </p>
                                            `:""}
                                </li>
                            `).join("")}
            </ul>
        `:`
                <div class="acc-empty">
                    Processo anterior ao workflow de autorização.
                </div>
            `}function G(a){const s=a.access||{};if(s.applicable===!1)return`
                <div class="acc-guidance">
                    <strong>
                        Destinatário individual
                    </strong>

                    <span>
                        Este processo não exige autorização
                        por representante de uma organização compradora.
                    </span>
                </div>
            `;const t=s.recipients||[];return`
            <section class="acc-auth-access">
                <h3 class="acc-section-title">
                    Quem responde pela organização
                </h3>

                ${t.length?`
                            <ul class="acc-simple-list">
                                ${t.map(c=>`
                                                <li class="acc-simple-item">
                                                    <strong>
                                                        ${e(c.name||"Representante autorizado")}
                                                        ·
                                                        ${e(c.email)}
                                                    </strong>

                                                    <span>
                                                        ${c.has_account?"Conta ativa":"Aguardando primeiro acesso"}

                                                        ${c.last_access_at?` · último acesso ${e(c.last_access_at)}`:""}
                                                    </span>
                                                </li>
                                            `).join("")}
                            </ul>
                        `:`
                            <div class="acc-error">
                                Nenhum e-mail foi autorizado para responder
                                por esta organização.

                                O e-mail do cadastro geral, sozinho,
                                não concede poder de aprovação.
                            </div>
                        `}

                ${y.dataset.canSendAuthorization==="1"?`
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
                                        value="${e(s.organization_email||"")}"
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
                                        value="${e(s.organization_contact||"")}"
                                    >
                                </label>

                                <button
                                    class="acc-button"
                                    type="submit"
                                >
                                    ${r("ph-key")}

                                    Autorizar e-mail
                                </button>

                                <div
                                    class="acc-action-feedback"
                                    data-access-feedback
                                ></div>
                            </form>
                        `:""}

                ${a.state==="sent"?`
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

                                ${s.buyer_url?`
                                            <a
                                                class="acc-button"
                                                href="${e(s.buyer_url)}"
                                            >
                                                ${r("ph-arrow-square-out")}

                                                Abrir tela da organização
                                            </a>
                                        `:""}
                            </div>
                        `:""}
            </section>
        `}function H(a){const s=a.data||[],t=`
            <p
                class="acc-muted"
                style="padding:.72rem .72rem 0"
            >
                Documentos de origem e repasse.
                Seus totais não formam o valor deste faturamento.
            </p>

            <div class="acc-receipt-results">
                ${s.length?s.map(c=>`
                                    <article class="acc-receipt-card">
                                        <div class="acc-receipt-card-main">
                                            <div>
                                                <strong>
                                                    ${e(c.number)}
                                                    ·
                                                    ${e(c.member)}
                                                </strong>

                                                <div class="acc-muted">
                                                    ${e(c.included_distributions||0)}
                                                    distribuição(ões)
                                                    incluída(s)
                                                    neste faturamento
                                                </div>
                                            </div>

                                            ${S(c.status_label,c.status==="paid"?"success":"warning")}
                                        </div>

                                        <div class="acc-receipt-actions">
                                            ${c.detail_url?`
                                                        <button
                                                            class="acc-button"
                                                            type="button"
                                                            data-related-detail="${e(c.detail_url)}"
                                                            data-related-title="${e(c.number)}"
                                                        >
                                                            ${r("ph-list-bullets")}

                                                            Ver distribuições
                                                        </button>
                                                    `:""}

                                            ${c.reprint_url?`
                                                        <a
                                                            class="acc-button"
                                                            href="${e(c.reprint_url)}"
                                                            target="_blank"
                                                            rel="noopener"
                                                        >
                                                            ${r("ph-file-text")}

                                                            Abrir documento
                                                        </a>
                                                    `:""}
                                        </div>
                                    </article>
                                `).join(""):`
                            <div class="acc-empty">
                                Nenhum documento de origem relacionado
                                às distribuições deste faturamento.
                            </div>
                        `}
            </div>
        `;return(a.last_page||1)<=1?t:t+`
            <div class="acc-pagination">
                <span>
                    ${e(a.from||0)}
                    –
                    ${e(a.to||0)}
                    de
                    ${e(a.total||0)}
                </span>

                <div class="acc-pagination-actions">
                    <button
                        class="acc-button"
                        type="button"
                        data-producer-page="${a.current_page-1}"
                        ${a.current_page<=1?"disabled":""}
                    >
                        ${r("ph-caret-left")}

                        Anterior
                    </button>

                    <button
                        class="acc-button"
                        type="button"
                        data-producer-page="${a.current_page+1}"
                        ${a.current_page>=a.last_page?"disabled":""}
                    >
                        Próxima

                        ${r("ph-caret-right")}
                    </button>
                </div>
            </div>
        `}async function Y(a,s){let t=document.querySelector("[data-related-receipt-dialog]");if(!t){t=document.createElement("dialog"),t.className="acc-dialog acc-dialog-wide acc-related-dialog",t.dataset.relatedReceiptDialog="",t.innerHTML=`
                <div class="acc-dialog-head">
                    <div class="acc-dialog-title">
                        <span class="acc-dialog-icon">
                            ${r("ph-arrows-left-right")}
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
                        ${r("ph-x")}
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
                        ${r("ph-x-circle")}

                        Fechar
                    </button>
                </footer>
            `,document.body.appendChild(t);const o=()=>t.close();t.querySelector("[data-related-close]").addEventListener("click",o),t.querySelector("[data-related-footer-close]").addEventListener("click",o),t.addEventListener("click",d=>{d.target===t&&o()})}t.querySelector("[data-related-receipt-title]").textContent=s||"Documento de origem";const c=t.querySelector("[data-related-receipt-content]");c.innerHTML=q(4),t.showModal();try{c.innerHTML=R(await L(a))}catch(o){x(c,o)}}async function z(){const a=document.querySelector("[data-dossier]");if(a){a.innerHTML=q(7);try{const s=await L(y.dataset.processDataUrl),t=s.process,c=t.integrity;a.innerHTML=`
                <section class="acc-panel">
                    <div class="acc-state-strip">
                        <div class="acc-state-item">
                            <span>
                                Processo
                            </span>

                            <strong>
                                ${e(t.state.label)}
                            </strong>
                        </div>

                        <div class="acc-state-item">
                            <span>
                                Autorização
                            </span>

                            <strong>
                                ${e(t.workflow.authorization.label)}
                            </strong>
                        </div>

                        <div class="acc-state-item">
                            <span>
                                Fiscal
                            </span>

                            <strong>
                                ${e(t.workflow.fiscal.label)}
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
                                    ${e(t.project?.title||"Não identificado")}
                                </strong>
                            </div>

                            <div class="acc-detail">
                                <span>
                                    Destinatário
                                </span>

                                <strong>
                                    ${e(t.recipient.name)}
                                </strong>
                            </div>

                            <div class="acc-detail">
                                <span>
                                    Período
                                </span>

                                <strong>
                                    ${e(t.period)}
                                </strong>
                            </div>

                            <div class="acc-detail">
                                <span>
                                    Valor bruto
                                </span>

                                <strong>
                                    ${p.format(t.financial.gross||0)}
                                </strong>
                            </div>

                            <div class="acc-detail">
                                <span>
                                    Taxas
                                </span>

                                <strong>
                                    ${p.format(t.financial.fees||0)}
                                </strong>
                            </div>

                            <div class="acc-detail">
                                <span>
                                    Valor líquido
                                </span>

                                <strong>
                                    ${p.format(t.financial.net||0)}
                                </strong>
                            </div>

                            <div class="acc-detail">
                                <span>
                                    Distribuições
                                </span>

                                <strong>
                                    ${e(t.summary.distributions)}
                                </strong>
                            </div>

                            <div class="acc-detail">
                                <span>
                                    Produtores
                                </span>

                                <strong>
                                    ${e(t.summary.producers)}
                                </strong>
                            </div>

                            <div class="acc-detail">
                                <span>
                                    Produtos
                                </span>

                                <strong>
                                    ${e(t.summary.products)}
                                </strong>
                            </div>

                            <div class="acc-detail">
                                <span>
                                    Unidades recebedoras
                                </span>

                                <strong>
                                    ${e(t.summary.recipient_units)}
                                </strong>
                            </div>

                            <div class="acc-detail">
                                <span>
                                    Documentos de origem
                                </span>

                                <strong>
                                    ${e(t.summary.source_receipts)}
                                </strong>
                            </div>
                        </div>
                    </div>

                    <div
                        class="acc-tab-panel"
                        data-panel="delivered"
                    >
                        ${J(s.consolidated_lines)}
                    </div>

                    <div
                        class="acc-tab-panel"
                        data-panel="distributions"
                    >
                        <div data-distributions-panel>
                            ${N(s.distributions)}
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
                                    ${p.format(t.financial.received||0)}
                                </strong>
                            </div>

                            <div class="acc-detail">
                                <span>
                                    Saldo restante
                                </span>

                                <strong>
                                    ${p.format(t.financial.remaining||0)}
                                </strong>
                            </div>

                            <div class="acc-detail">
                                <span>
                                    Situação financeira
                                </span>

                                <strong>
                                    ${e(t.financial.status_label)}
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
                            ${s.payments.length?s.payments.map(n=>`
                                                <li class="acc-simple-item">
                                                    <strong>
                                                        ${p.format(n.amount||0)}
                                                        ·
                                                        ${e(n.date)}
                                                    </strong>

                                                    <span>
                                                        ${e(n.account||n.method||"Sem conta informada")}
                                                    </span>
                                                </li>
                                            `).join(""):`
                                        <li class="acc-simple-item">
                                            Nenhum recebimento registrado.
                                        </li>
                                    `}
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
                                            ${r("ph-file-text")}
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
                                        ${M([`Projeto: ${t.project?.title||"Não identificado"}`,`Destinatário: ${t.recipient.name||""}`,`Período: ${t.period||""}`,`Valor bruto: ${p.format(t.financial.gross||0)}`,`Taxas: ${p.format(t.financial.fees||0)}`,`Valor líquido: ${p.format(t.financial.net||0)}`,`Situação fiscal: ${t.workflow.fiscal.label||""}`,`Documento esperado: ${t.workflow.fiscal.document_type||"Não configurado"}`].join(`
`),"Copiar dados")}

                                        ${t.pdf_url||t.workflow.fiscal.billing_sheet_url?`
                                                    <a
                                                        class="acc-button acc-button-primary"
                                                        href="${e(t.pdf_url||t.workflow.fiscal.billing_sheet_url)}"
                                                    >
                                                        ${r("ph-printer")}

                                                        ${t.financial.status==="draft"?"Imprimir prévia do faturamento":"Imprimir faturamento completo"}
                                                    </a>
                                                `:t.workflow.fiscal.settings_url?`
                                                            <a
                                                                class="acc-button acc-button-primary"
                                                                href="${e(t.workflow.fiscal.settings_url)}"
                                                            >
                                                                ${r("ph-gear")}

                                                                Configurar folha
                                                            </a>
                                                        `:""}
                                    </div>
                                </header>

                                <div class="acc-subsection-body">
                                    <div class="acc-metric-table">
                                        <div class="acc-metric-row">
                                            <span
                                                class="acc-metric-icon acc-tone-purple"
                                            >
                                                ${r("ph-seal-check")}
                                            </span>

                                            <div class="acc-metric-copy">
                                                <span>
                                                    Situação fiscal
                                                </span>

                                                <strong>
                                                    ${e(t.workflow.fiscal.label)}
                                                </strong>
                                            </div>
                                        </div>

                                        <div class="acc-metric-row">
                                            <span
                                                class="acc-metric-icon acc-tone-blue"
                                            >
                                                ${r("ph-file-search")}
                                            </span>

                                            <div class="acc-metric-copy">
                                                <span>
                                                    Documento esperado
                                                </span>

                                                <strong>
                                                    ${e(t.workflow.fiscal.document_type||"Não configurado")}
                                                </strong>
                                            </div>
                                        </div>

                                        <div class="acc-metric-row">
                                            <span
                                                class="acc-metric-icon acc-tone-green"
                                            >
                                                ${r("ph-currency-circle-dollar")}
                                            </span>

                                            <div class="acc-metric-copy">
                                                <span>
                                                    Valor para emissão
                                                </span>

                                                <strong>
                                                    ${t.workflow.fiscal.expected_amount==null?"Não determinado":p.format(t.workflow.fiscal.expected_amount)}
                                                </strong>
                                            </div>
                                        </div>
                                    </div>

                                    ${t.workflow.fiscal.blocks?.length?`
                                                <div class="acc-document-alerts">
                                                    ${t.workflow.fiscal.blocks.map(n=>`
                                                                    <div class="acc-document-alert">
                                                                        <span>
                                                                            ${r("ph-warning-circle")}
                                                                        </span>

                                                                        <div>
                                                                            <strong>
                                                                                Revisar antes de emitir
                                                                            </strong>

                                                                            <p>
                                                                                ${e(n.message)}
                                                                            </p>
                                                                        </div>
                                                                    </div>
                                                                `).join("")}
                                                </div>
                                            `:""}
                                </div>
                            </section>

                            ${t.workflow.authorization.access?.applicable===!1&&!s.authorizations.length?"":`
                                        <section
                                            class="acc-subsection acc-subsection-authorization"
                                        >
                                            <header class="acc-subsection-head">
                                                <div class="acc-subsection-title">
                                                    <span
                                                        class="acc-subsection-icon acc-subsection-icon-blue"
                                                    >
                                                        ${r("ph-seal-check")}
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

                                                ${y.dataset.canSendAuthorization==="1"&&["legacy_unsubmitted","cancelled"].includes(t.workflow.authorization.state)?`
                                                            <button
                                                                class="acc-button"
                                                                type="button"
                                                                data-send-authorization
                                                            >
                                                                ${r("ph-paper-plane-tilt")}

                                                                Enviar autorização
                                                            </button>
                                                        `:""}
                                            </header>

                                            <div class="acc-subsection-body">
                                                ${G(t.workflow.authorization)}

                                                <div
                                                    class="acc-action-feedback"
                                                    data-authorization-feedback
                                                    aria-live="polite"
                                                ></div>

                                                ${K(s.authorizations)}
                                            </div>
                                        </section>
                                    `}

                            <section
                                class="acc-subsection acc-subsection-source"
                            >
                                <header class="acc-subsection-head">
                                    <div class="acc-subsection-title">
                                        <span
                                            class="acc-subsection-icon acc-subsection-icon-violet"
                                        >
                                            ${r("ph-receipt")}
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
                                        ${e(t.summary.source_receipts||0)}
                                    </span>
                                </header>

                                <div
                                    class="acc-subsection-body acc-subsection-body-flush"
                                >
                                    ${H(s.producer_receipts)}
                                </div>
                            </section>
                        </div>
                    </div>

                    <div
                        class="acc-tab-panel"
                        data-panel="timeline"
                    >
                        <ul class="acc-simple-list">
                            ${s.timeline.length?s.timeline.map(n=>`
                                                <li class="acc-simple-item">
                                                    <strong>
                                                        ${e(n.description)}
                                                    </strong>

                                                    <span>
                                                        ${e(n.date)}
                                                        ·
                                                        ${e(n.actor)}
                                                    </span>
                                                </li>
                                            `).join(""):`
                                        <li class="acc-simple-item">
                                            Nenhum evento registrado.
                                        </li>
                                    `}
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
                                    ${r("ph-arrow-circle-right")}
                                </span>

                                <div>
                                    <h2>
                                        Próxima ação
                                    </h2>

                                    <p>
                                        ${e(t.state.next_action)}
                                    </p>
                                </div>
                            </div>

                            ${S(t.state.label,t.state.tone)}
                        </div>

                        <div
                            class="acc-action-box"
                            data-authorization-action
                        >
                            ${t.edit_url?`
                                        <a
                                            class="acc-button acc-button-primary"
                                            href="${e(t.edit_url)}"
                                        >
                                            ${r("ph-pencil-line")}

                                            Continuar e conferir faturamento
                                        </a>

                                        <span class="acc-muted">
                                            Revise as entregas e os valores
                                            antes de fechar.
                                        </span>
                                    `:""}

                            ${t.pdf_url||t.workflow.fiscal.billing_sheet_url?`
                                    <a
                                        class="acc-button ${t.edit_url?"":"acc-button-primary"}"
                                        href="${e(t.pdf_url||t.workflow.fiscal.billing_sheet_url)}"
                                    >
                                        ${r("ph-printer")}

                                        ${t.financial.status==="draft"?"Imprimir prévia do faturamento":"Imprimir faturamento completo"}
                                    </a>
                                `:""}
                        </div>
                    </section>

                    <section class="acc-panel acc-side-panel">
                        <div class="acc-panel-head acc-panel-head-workspace">
                            <div class="acc-panel-title-workspace">
                                <span
                                    class="acc-panel-icon acc-panel-icon-amber"
                                >
                                    ${r("ph-checks")}
                                </span>

                                <div>
                                    <h2>
                                        Conferência dos dados
                                    </h2>

                                    <p>
                                        ${c.critical_count?`${e(c.critical_count)} erro(s) de integridade`:c.preparation_count?`${e(c.preparation_count)} item(ns) a completar`:"Dados prontos para avançar"}
                                    </p>
                                </div>
                            </div>

                            ${S(c.critical_count?"Corrigir erro":c.preparation_count?"Em preparação":"Pronto",c.critical_count?"danger":c.preparation_count?"warning":"success")}
                        </div>

                        <div class="acc-side-panel-body">
                            <ul class="acc-integrity-list">
                                ${c.issues.length?c.issues.map(n=>`
                                                    <li class="acc-integrity-item">
                                                        ${S(n.severity==="critical"?"Erro":"Preparação",n.severity==="critical"?"danger":"warning")}

                                                        ${e(n.message)}
                                                    </li>
                                                `).join(""):`
                                            <li class="acc-simple-item">
                                                Nenhuma pendência encontrada.
                                            </li>
                                        `}
                            </ul>
                            ${c.repair_url?`
                                        <button class="acc-button" type="button" data-repair-integrity data-url="${e(c.repair_url)}">
                                            ${r("ph-wrench")}
                                            Corrigir vínculos automaticamente
                                        </button>
                                        <div class="acc-action-feedback" data-integrity-feedback aria-live="polite"></div>
                                    `:""}
                        </div>
                    </section>

                    <section class="acc-panel acc-side-panel">
                        <div class="acc-panel-head acc-panel-head-workspace">
                            <div class="acc-panel-title-workspace">
                                <span
                                    class="acc-panel-icon acc-panel-icon-purple"
                                >
                                    ${r("ph-files")}
                                </span>

                                <div>
                                    <h2>
                                        Arquivos anexados
                                    </h2>

                                    <p>
                                        ${e(s.documents.length)}
                                        arquivo(s)
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="acc-side-panel-body">
                            <ul class="acc-simple-list acc-document-list">
                                ${s.documents.length?s.documents.map(n=>`
                                                    <li
                                                        class="acc-simple-item acc-document-row"
                                                    >
                                                        <span
                                                            class="acc-document-row-icon"
                                                        >
                                                            ${r("ph-file")}
                                                        </span>

                                                        <div>
                                                            <strong>
                                                                ${e(n.name)}
                                                            </strong>

                                                            <span>
                                                                ${e(n.category)}
                                                                ·
                                                                ${e(n.date)}
                                                            </span>
                                                        </div>
                                                    </li>
                                                `).join(""):`
                                            <li class="acc-simple-item">
                                                Nenhum documento anexado.
                                            </li>
                                        `}
                            </ul>
                        </div>
                    </section>
                </aside>
            `,a.querySelectorAll("[data-tab]").forEach(n=>n.addEventListener("click",()=>{a.querySelectorAll("[data-tab]").forEach(l=>{const i=l===n;l.classList.toggle("is-active",i),l.setAttribute("aria-selected",i?"true":"false"),i?l.setAttribute("aria-current","page"):l.removeAttribute("aria-current")}),a.querySelectorAll("[data-panel]").forEach(l=>l.classList.toggle("is-active",l.dataset.panel===n.dataset.tab)),window.innerWidth<760&&n.scrollIntoView({behavior:"smooth",inline:"center",block:"nearest"})}));const o=a.querySelector("[data-access-form]");o?.addEventListener("submit",async n=>{n.preventDefault();const l=o.querySelector('button[type="submit"]'),i=o.querySelector("[data-access-feedback]");l.disabled=!0,i.textContent="Salvando...";try{const u=await D(y.dataset.authorizationAccessUrl,Object.fromEntries(new FormData(o).entries()));i.textContent=u.message,await z()}catch(u){i.textContent=u.message,i.classList.add("is-error"),l.disabled=!1}});const d=a.querySelector("[data-send-authorization]"),m=a.querySelector("[data-repair-integrity]");m?.addEventListener("click",async()=>{const n=a.querySelector("[data-integrity-feedback]");m.disabled=!0,n.textContent="Verificando e corrigindo vínculos...";try{const l=await D(m.dataset.url,{});n.textContent=l.message,await z()}catch(l){n.textContent=l.message,n.classList.add("is-error"),m.disabled=!1}}),d?.addEventListener("click",async()=>{if(!window.confirm(`Enviar esta cobrança para autorização?

Valor: ${p.format(t.financial.net||0)}
Período: ${t.period}`))return;const n=a.querySelector("[data-authorization-feedback]");d.disabled=!0,n.textContent="Enviando...";try{await D(y.dataset.authorizationSendUrl,{operation_key:crypto.randomUUID()}),n.textContent="Cobrança enviada.",await z()}catch(l){n.textContent=l.message,n.classList.add("is-error"),d.disabled=!1}});const w=()=>a.querySelectorAll("[data-dist-page]").forEach(n=>n.addEventListener("click",async()=>{const l=a.querySelector("[data-distributions-panel]");if(l){l.innerHTML=q(3);try{const i=await L(j(y.dataset.processDataUrl,{distributions_page:n.dataset.distPage}));l.innerHTML=N(i.distributions),w()}catch(i){x(l,i)}}}));w();const k=()=>a.querySelectorAll("[data-producer-page]").forEach(n=>n.addEventListener("click",async()=>{const i=a.querySelector('[data-panel="documents"]')?.querySelector(".acc-subsection-source .acc-subsection-body");if(i){i.innerHTML=q(3);try{const u=await L(j(y.dataset.processDataUrl,{producer_receipts_page:n.dataset.producerPage}));i.innerHTML=H(u.producer_receipts),k(),g()}catch(u){x(i,u)}}})),g=()=>a.querySelectorAll("[data-related-detail]").forEach(n=>n.addEventListener("click",()=>Y(n.dataset.relatedDetail,n.dataset.relatedTitle)));k(),g()}catch(s){x(a,s)}}}function R(a){const s=a.receipt,t=a.distributions||[],c=a.processes||[];return`
            <div class="acc-detail-grid acc-receipt-summary">
                <div class="acc-detail">
                    <span>
                        Produtor
                    </span>

                    <strong>
                        ${e(s.member)}
                    </strong>
                </div>

                <div class="acc-detail">
                    <span>
                        Projeto
                    </span>

                    <strong>
                        ${e(s.project)}
                    </strong>
                </div>

                <div class="acc-detail">
                    <span>
                        Situação
                    </span>

                    <strong>
                        ${e(s.status_label)}
                    </strong>
                </div>
            </div>

            ${t.length?`
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
                                    ${t.map(o=>`
                                                    <tr>
                                                        <td>
                                                            ${e(o.date||"—")}
                                                        </td>

                                                        <td>
                                                            <strong>
                                                                ${e(o.product)}
                                                            </strong>

                                                            <div class="acc-muted">
                                                                ${e(o.customer)}
                                                            </div>
                                                        </td>

                                                        <td>
                                                            ${P.format(o.quantity||0)}

                                                            ${e(o.unit)}
                                                        </td>

                                                        <td>
                                                            ${p.format(o.unit_price||0)}
                                                        </td>

                                                        <td class="acc-money">
                                                            ${p.format(o.gross||0)}
                                                        </td>

                                                        <td>
                                                            ${o.billing_receipt_id?`Processo #${e(o.billing_receipt_id)}`:"Ainda não faturada"}
                                                        </td>
                                                    </tr>
                                                `).join("")}
                                </tbody>
                            </table>
                        </div>
                    `:`
                        <div class="acc-empty">
                            Este comprovante não possui distribuições vinculadas.
                        </div>
                    `}

            <section class="acc-related-processes">
                <h3>
                    Processos contábeis relacionados
                </h3>

                ${c.length?`
                            <ul class="acc-simple-list">
                                ${c.map(o=>`
                                                <li class="acc-simple-item">
                                                    <a
                                                        class="acc-link"
                                                        href="${e(o.url)}"
                                                    >
                                                        ${e(o.number)}
                                                    </a>

                                                    <span>
                                                        ${e(o.status)}
                                                    </span>
                                                </li>
                                            `).join("")}
                            </ul>
                        `:`
                            <p class="acc-muted">
                                Nenhuma distribuição deste comprovante
                                entrou em um processo contábil.
                            </p>
                        `}
            </section>
        `}function Z(a){return`
            <article class="acc-receipt-card">
                <div class="acc-receipt-card-main">
                    <div>
                        <button
                            class="acc-link acc-link-button"
                            type="button"
                            data-source-receipt="${e(a.id)}"
                            data-detail-url="${e(a.detail_url)}"
                        >
                            ${e(a.number)}
                        </button>

                        <div class="acc-muted">
                            ${e(a.member)}
                            ·
                            ${e(a.project)}
                        </div>
                    </div>

                    ${S(a.status_label,a.status==="paid"?"success":a.status==="obsolete"?"danger":"warning")}
                </div>

                <div class="acc-receipt-meta">
                    <span>
                        Emissão

                        <strong>
                            ${e(a.issued_at||"—")}
                        </strong>
                    </span>

                    <span>
                        Distribuições

                        <strong>
                            ${e(a.distribution_count)}
                        </strong>
                    </span>

                    <span>
                        Valor do comprovante

                        <strong>
                            ${p.format(a.total_net||0)}
                        </strong>
                    </span>

                    <span>
                        Referência QR

                        <strong>
                            ${e(a.reference_code||"Não gerada")}
                        </strong>
                    </span>
                </div>

                <div class="acc-receipt-actions">
                    <button
                        class="acc-button"
                        type="button"
                        data-source-receipt="${e(a.id)}"
                        data-detail-url="${e(a.detail_url)}"
                    >
                        ${r("ph-tree-structure")}

                        Ver entregas
                    </button>

                    ${a.reprint_url?`
                                <a
                                    class="acc-button"
                                    href="${e(a.reprint_url)}"
                                    target="_blank"
                                    rel="noopener"
                                >
                                    ${r("ph-file-text")}

                                    Abrir comprovante
                                </a>
                            `:""}
                </div>
            </article>
        `}async function aa(){const a=document.querySelector("[data-source-receipt-filters]");if(!a)return;const s=document.querySelector("[data-source-receipt-results]"),t=document.querySelector("[data-source-receipt-pagination]"),c=a.elements.project,o=document.querySelector("[data-qr-dialog]"),d=document.querySelector("[data-receipt-dialog]"),m=document.querySelector("[data-receipt-detail]"),w=document.querySelector("[data-receipt-title]"),k=document.querySelector("[data-scanner-video]"),g=document.querySelector("[data-scanner-error]");let n=null,l=!1,i=null;const u=()=>{n?.stop?.(),n=null,k&&(k.srcObject=null)},$=v=>{a.elements.search.value=String(v||"").trim(),u(),o?.close(),h(1)};async function f(v,b="Comprovante"){if(!(!d||!m)){w&&(w.textContent=b),m.innerHTML=q(5),d.showModal();try{m.innerHTML=R(await L(v))}catch(C){x(m,C)}}}const E=()=>s?.querySelectorAll("[data-source-receipt]").forEach(v=>v.addEventListener("click",()=>f(v.dataset.detailUrl,v.textContent.trim())));async function h(v=1){i?.abort(),i=new AbortController,s.innerHTML=q(5),t.innerHTML="";const b=Object.fromEntries(new FormData(a).entries());b.page=v;try{const C=await L(j(y.dataset.sourceReceiptsUrl,b),i.signal);!l&&c&&(c.innerHTML='<option value="">Todos os projetos</option>'+(C.filters?.projects||[]).map(T=>`
                                    <option value="${e(T.id)}">
                                        ${e(T.label)}
                                    </option>
                                `).join(""),c.value=b.project||"",l=!0);const _=C.receipts;s.innerHTML=_.data.length?_.data.map(Z).join(""):`
                            <div class="acc-empty">
                                Nenhum comprovante encontrado.
                                Confira o número ou leia novamente o QR Code.
                            </div>
                        `,t.innerHTML=`
                    <span>
                        ${e(_.from||0)}
                        –
                        ${e(_.to||0)}
                        de
                        ${e(_.total||0)}
                    </span>

                    <div class="acc-pagination-actions">
                        <button
                            class="acc-button"
                            type="button"
                            data-page="${_.current_page-1}"
                            ${_.current_page<=1?"disabled":""}
                        >
                            ${r("ph-caret-left")}

                            Anterior
                        </button>

                        <button
                            class="acc-button"
                            type="button"
                            data-page="${_.current_page+1}"
                            ${_.current_page>=_.last_page?"disabled":""}
                        >
                            Próxima

                            ${r("ph-caret-right")}
                        </button>
                    </div>
                `,t.querySelectorAll("[data-page]").forEach(T=>T.addEventListener("click",()=>h(Number(T.dataset.page)))),E(),b.search&&(/\bCP-/i.test(b.search)||/[0-9a-f]{8}-[0-9a-f-]{27,}/i.test(b.search))&&_.data.length===1&&await f(_.data[0].detail_url,_.data[0].number)}catch(C){C.name!=="AbortError"&&x(s,C)}}a.addEventListener("submit",v=>{v.preventDefault(),h(1)}),a.elements.status?.addEventListener("change",()=>h(1)),a.elements.project?.addEventListener("change",()=>h(1)),document.querySelector("[data-close-receipt]")?.addEventListener("click",()=>d?.close()),document.querySelector("[data-close-scanner]")?.addEventListener("click",()=>{u(),o?.close()}),o?.addEventListener("close",u),document.querySelector("[data-use-manual]")?.addEventListener("click",()=>$(document.querySelector("[data-scanner-manual]")?.value)),document.querySelector("[data-open-scanner]")?.addEventListener("click",async()=>{if(!o)return;g&&(g.hidden=!0);const v=window.SgcQrScanner;if(v?.nativePlugin()){try{const b=await v.scanNative({batch:!1});b?.code&&$(b.code)}catch(b){b?.code!=="SCAN_CANCELLED"&&g&&(o.showModal(),g.textContent=b?.message||"Não foi possível abrir o leitor nativo.",g.hidden=!1)}return}if(o.showModal(),!v?.supportsWeb()){g&&(g.textContent="A leitura automática não está disponível neste navegador. Cole o código ou link no campo abaixo.",g.hidden=!1);return}try{n=await v.openWeb({video:k,single:!0,interval:180,onCode:$})}catch{g&&(g.textContent="Não foi possível acessar a câmera. Autorize o uso ou cole o código manualmente.",g.hidden=!1),u()}}),h(1)}A==="queue"&&Q(),A==="processes"&&X(),A==="dossier"&&z(),A==="source-receipts"&&aa()})();
