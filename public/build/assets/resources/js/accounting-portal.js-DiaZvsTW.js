import"../../qr-scanner-core-CTBFOQOA.js";(function(){const w=document.querySelector("[data-accounting-page]");if(!w)return;const N=w.dataset.accountingPage,h=new Intl.NumberFormat("pt-BR",{style:"currency",currency:"BRL"}),L=new Intl.NumberFormat("pt-BR",{minimumFractionDigits:0,maximumFractionDigits:4}),t=a=>String(a??"").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;").replace(/'/g,"&#039;"),Q={"circle-check":"ph-check-circle","chevron-left":"ph-caret-left","chevron-right":"ph-caret-right","list-tree":"ph-tree-structure","file-text":"ph-file-text","alert-triangle":"ph-warning","circle-alert":"ph-warning-circle",clock:"ph-clock",wallet:"ph-wallet",receipt:"ph-receipt","receipt-text":"ph-receipt",files:"ph-files","file-clock":"ph-file-dashed",send:"ph-paper-plane-tilt",search:"ph-magnifying-glass","folder-search":"ph-folder-open",settings:"ph-gear",printer:"ph-printer","key-round":"ph-key","external-link":"ph-arrow-square-out","pencil-line":"ph-pencil-line",x:"ph-x"},V=a=>{const s=String(a||"").trim();return s?s.startsWith("ph-")?s:Q[s]||`ph-${s}`:"ph-circle"},d=a=>`
        <i
            class="ph-fill ${t(V(a))}"
            aria-hidden="true"
        ></i>
    `;(()=>{if(document.querySelector('link[href*="@phosphor-icons"][href*="fill"]'))return;const a=document.createElement("link");a.rel="stylesheet",a.href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/fill/style.css",document.head.appendChild(a)})();const q=(a,s="neutral")=>`
        <span
            class="acc-badge acc-badge-${t(s)}"
        >
            ${t(a)}
        </span>
    `,j=(a=4)=>Array.from({length:a},()=>'<div class="acc-skeleton"></div>').join("");function z(a){return a?`<div class="acc-document-actions" data-document-actions>
            <div class="acc-document-actions-buttons">
                ${a.download_url?`<a class="acc-button" href="${t(a.download_url)}" download>${d("ph-download-simple")} Baixar PDF atualizado</a>`:""}
                ${a.refresh_url?`<button type="button" class="acc-button" data-refresh-document="${t(a.refresh_url)}" data-status-url="${t(a.status_url)}">${d("ph-arrows-clockwise")} Atualizar PDF no Drive</button>`:""}
            </div>
            <p class="acc-document-feedback" data-document-feedback aria-live="polite">${t(a.drive_status_label)}${a.drive_updated_at?` · Última sincronização: ${t(a.drive_updated_at)} (Brasília)`:""}</p>
        </div>`:""}const R=new Set;document.addEventListener("click",async a=>{const s=a.target.closest?.("[data-refresh-document]");if(!s||R.has(s.dataset.refreshDocument))return;const o=s.closest("[data-document-actions]"),e=o.querySelector("[data-document-feedback]"),n=s.dataset.refreshDocument;R.add(n),s.disabled=!0,e.classList.remove("is-error"),e.textContent="Agendando atualização do PDF…";const l=()=>{s.disabled=!1,R.delete(n)};try{const v=await D(n,{});e.textContent=v.message;let r=0;const i=async()=>{if(!o.isConnected){l();return}try{const u=await x(s.dataset.statusUrl);u.drive_status==="synced"?(e.textContent=`PDF atualizado no Drive · ${u.drive_updated_at||""} (Brasília)`,l()):["failed","rejected"].includes(u.drive_status)?(e.textContent="A atualização do Drive não foi concluída. Confira a conexão ou os dados do comprovante. Você pode tentar novamente ou baixar o PDF atualizado.",e.classList.add("is-error"),l()):++r<12?setTimeout(i,5e3):(e.textContent="Atualização ainda pendente. O worker da fila documents precisa estar ativo. O download atualizado pode ser usado sem aguardar o Drive.",l())}catch{e.textContent="Não foi possível consultar a conclusão. Confira o status ao reabrir a página; a solicitação já foi enviada.",l()}};setTimeout(i,5e3)}catch(v){e.textContent=v.message,e.classList.add("is-error"),l()}});async function x(a,s){const o=await fetch(a,{headers:{Accept:"application/json","X-Requested-With":"XMLHttpRequest"},credentials:"same-origin",cache:"no-store",signal:s}),e=await o.json().catch(()=>({}));if(!o.ok)throw new Error(e.message||"Não foi possível carregar os dados.");return e}async function D(a,s){const o=document.querySelector('meta[name="csrf-token"]')?.content||"",e=await fetch(a,{method:"POST",headers:{Accept:"application/json","Content-Type":"application/json","X-Requested-With":"XMLHttpRequest","X-CSRF-TOKEN":o},credentials:"same-origin",cache:"no-store",body:JSON.stringify(s||{})}),n=await e.json().catch(()=>({}));if(!e.ok){const l=Array.isArray(n.issues)?n.issues.map(v=>v.message).join(" "):"";throw new Error(l||n.message||"Não foi possível concluir a ação.")}return n}function C(a,s){a&&(a.innerHTML=`
            <div
                class="acc-error"
                role="alert"
            >
                ${t(s.message||s)}
            </div>
        `)}function T(a,s){const o=new URL(a,window.location.origin);return Object.entries(s||{}).forEach(([e,n])=>{n!==""&&n!==null&&n!==void 0&&o.searchParams.set(e,n)}),o.toString()}const O=a=>encodeURIComponent(String(a??"")),M=(a,s="Copiar",o="")=>`
        <button
            class="acc-button acc-copy-button ${t(o)}"
            type="button"
            data-copy-text="${t(O(a))}"
        >
            ${d("ph-copy")}

            <span data-copy-label>
                ${t(s)}
            </span>
        </button>
    `;async function I(a){const s=String(a??"");if(navigator.clipboard?.writeText){await navigator.clipboard.writeText(s);return}const o=document.createElement("textarea");o.value=s,o.setAttribute("readonly",""),o.style.position="fixed",o.style.opacity="0",o.style.pointerEvents="none",document.body.appendChild(o),o.select();try{document.execCommand("copy")}finally{o.remove()}}function W(a){const s=a.querySelector("[data-copy-label]"),o=s?.textContent||"Copiar";a.classList.add("is-copied"),s&&(s.textContent="Copiado"),window.clearTimeout(a._copyTimer),a._copyTimer=window.setTimeout(()=>{a.classList.remove("is-copied"),s&&(s.textContent=o)},1400)}document.addEventListener("click",async a=>{const s=a.target.closest("[data-copy-text], [data-copy-value]");if(!s)return;a.preventDefault(),a.stopPropagation();const o=s.dataset.copyText?decodeURIComponent(s.dataset.copyText):s.dataset.copyValue||"";try{await I(o),W(s)}catch{const n=s.querySelector("[data-copy-label]");n&&(n.textContent="Falhou",window.setTimeout(()=>{n.textContent="Copiar"},1400))}});async function X(){const a=document.querySelector("[data-queue-list]"),s=document.querySelector("[data-queue-summary]"),o=document.querySelector("[data-queue-total]");if(!a||!s)return;a.innerHTML=j(4);const e=l=>{const v=String(l?.label||"").toLowerCase();if(/erro|integridade|corrigir|falha|inconsist/.test(v))return"ph-warning-circle";if(/rascunho|prepar|completar/.test(v))return"ph-receipt";if(/autoriza|aprova/.test(v))return"ph-check-circle";if(/saldo|receb|pagamento/.test(v))return"ph-clock-countdown";switch(String(l?.tone||"")){case"danger":case"warning":return"ph-warning-circle";case"success":return"ph-check-circle";case"info":case"cyan":return"ph-folder-open";case"violet":case"purple":return"ph-receipt";default:return"ph-folder"}},n=l=>{switch(String(l?.tone||"")){case"danger":return"Corrigir";case"warning":return"Preparar";case"success":return"Pronto";case"info":case"cyan":return"Acompanhar";case"violet":case"purple":return"Revisar";default:return"Pendente"}};try{const l=await x(w.dataset.queueUrl),v=w.dataset.processesUrl,r=Number(l.summary?.open_processes||0),i=Number(l.summary?.open_amount||0),u=l.summary?.workflow_label||"Fluxo contábil",b=l.queue||[],k=b.reduce((p,c)=>p+Number(c.count||0),0);if(o&&(o.innerHTML=`
                    <i
                        class="ph-fill ph-folder"
                        aria-hidden="true"
                    ></i>

                    ${t(k)}
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
                            ${h.format(i)}
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
                                ${t(r)}
                            </strong>
                        </span>

                        <span>
                            ${t(u)}
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
                                ${t(r)}
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
                                ${t(u)}
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
                                        ${t(r)}
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
                                        ${t(k)}
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
                                        ${t(u)}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `,l.empty||!b.length){a.innerHTML=`
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
                `;return}a.innerHTML=b.map(p=>{const c=p.url||T(v,p.filters),m=String(p.tone||"neutral"),g=e(p),y=n(p);return`
                                <a
                                    class="acc-queue-row acc-tone-${t(m)}"
                                    href="${t(c)}"
                                >
                                    <span class="queue-main">
                                        <span
                                            class="acc-queue-icon"
                                            aria-hidden="true"
                                        >
                                            <i
                                                class="ph-fill ${t(g)}"
                                            ></i>
                                        </span>

                                        <span class="acc-queue-copy">
                                            <strong>
                                                ${t(p.label)}
                                            </strong>

                                            <span>
                                                Abrir processos relacionados
                                            </span>
                                        </span>
                                    </span>

                                    <span class="queue-status">
                                        ${t(y)}
                                    </span>

                                    <span class="acc-queue-count">
                                        ${t(p.count)}
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
                            `}).join("")}catch(l){o&&(o.innerHTML=`
                    <i
                        class="ph-fill ph-warning-circle"
                        aria-hidden="true"
                    ></i>

                    —
                `),C(a,l)}}function J(a){const s=a.critical_issues?q(`${a.critical_issues} erro(s)`,"danger"):a.preparation_issues?q(`${a.preparation_issues} item(ns)`,"warning"):q("Pronto","success");return`
            <tr>
                <td>
                    <a
                        class="acc-link"
                        href="${t(a.url)}"
                    >
                        ${t(a.number)}
                    </a>

                    <div class="acc-muted">
                        ${t(a.issued_at||"")}
                    </div>
                </td>

                <td>
                    <strong>
                        ${t(a.project)}
                    </strong>

                    <div class="acc-muted">
                        ${t(a.project_code||"")}
                    </div>
                </td>

                <td>
                    ${t(a.recipient)}

                    <div class="acc-muted">
                        ${t(a.recipient_type)}
                    </div>
                </td>

                <td>
                    ${q(a.state.label,a.state.tone)}

                    <div class="acc-muted">
                        ${t(a.state.next_action)}
                    </div>
                </td>

                <td class="acc-money">
                    ${h.format(a.net||0)}

                    <div class="acc-muted">
                        Saldo
                        ${h.format(a.remaining||0)}
                    </div>
                </td>

                <td>
                    ${s}
                </td>

                <td class="process-action-column">
                    <a
                        class="process-open"
                        href="${t(a.url)}"
                        aria-label="Acessar ${t(a.number)}"
                    >
                        Acessar

                        <i
                            class="ph-fill ph-arrow-right"
                            aria-hidden="true"
                        ></i>
                    </a>
                </td>
            </tr>
        `}function G(a){const s=a.critical_issues?`${t(a.critical_issues)} erro(s)`:a.preparation_issues?`${t(a.preparation_issues)} item(ns) a completar`:"Pronto";return`
            <article
                class="acc-mobile-row"
                data-tone="${t(a.state.tone||"neutral")}"
            >
                <div class="acc-mobile-head">
                    <div class="mobile-process-main">
                        <a
                            class="acc-link"
                            href="${t(a.url)}"
                        >
                            ${t(a.number)}
                        </a>

                        <span class="acc-muted">
                            ${t(a.project)}
                        </span>
                    </div>

                    ${q(a.state.label,a.state.tone)}
                </div>

                <div class="acc-mobile-meta">
                    <span>
                        Destinatário

                        <strong>
                            ${t(a.recipient)}
                        </strong>
                    </span>

                    <span>
                        Valor líquido

                        <strong>
                            ${h.format(a.net||0)}
                        </strong>
                    </span>

                    <span>
                        Próxima ação

                        <strong>
                            ${t(a.state.next_action)}
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
                        href="${t(a.url)}"
                    >
                        Acessar

                        <i
                            class="ph-fill ph-arrow-right"
                            aria-hidden="true"
                        ></i>
                    </a>
                </div>
            </article>
        `}async function K(){const a=document.querySelector("[data-process-filters]"),s=document.querySelector("[data-process-table]"),o=document.querySelector("[data-process-mobile]"),e=document.querySelector("[data-process-pagination]");if(!a||!s||!o||!e)return;const n=a.elements.project,l=a.elements.organization,v=a.elements.customer;let r,i=!1;const u=new URLSearchParams(window.location.search);["search","project","organization","customer","from","until","financial_status","authorization_status","fiscal_status","accountability_status","pending"].forEach(p=>{u.has(p)&&a.elements[p]&&(a.elements[p].value=u.get(p))});async function b(p=1){r?.abort(),r=new AbortController,s.innerHTML=`
                <tr>
                    <td colspan="7">
                        ${j(5)}
                    </td>
                </tr>
            `,o.innerHTML=j(5),e.innerHTML="";const c=Object.fromEntries(new FormData(a).entries());c.page=p;try{const m=await x(T(w.dataset.processesDataUrl,c),r.signal),g=m.processes;i||(n&&(n.innerHTML='<option value="">Todos os projetos</option>'+(m.filters?.projects||[]).map(f=>`
                                        <option value="${t(f.id)}">
                                            ${t(f.label)}
                                        </option>
                                    `).join(""),n.value=c.project||""),l&&(l.innerHTML='<option value="">Todas as organizações</option>'+(m.filters?.organizations||[]).map(f=>`
                                        <option value="${t(f.id)}">
                                            ${t(f.label)}
                                        </option>
                                    `).join(""),l.value=c.organization||""),v&&(v.innerHTML='<option value="">Todos os clientes</option>'+(m.filters?.customers||[]).map(f=>`
                                        <option value="${t(f.id)}">
                                            ${t(f.label)}
                                        </option>
                                    `).join(""),v.value=c.customer||""),i=!0),g.data.length?(s.innerHTML=g.data.map(J).join(""),o.innerHTML=g.data.map(G).join("")):(s.innerHTML=`
                        <tr>
                            <td colspan="7">
                                <div class="acc-empty">
                                    Nenhum processo encontrado.
                                </div>
                            </td>
                        </tr>
                    `,o.innerHTML=`
                        <div class="acc-empty">
                            Nenhum processo encontrado.
                        </div>
                    `),e.innerHTML=`
                    <span>
                        ${t(g.from||0)}
                        –
                        ${t(g.to||0)}
                        de
                        ${t(g.total||0)}
                    </span>

                    <div class="acc-pagination-actions">
                        <button
                            class="acc-button"
                            type="button"
                            data-page="${g.current_page-1}"
                            ${g.current_page<=1?"disabled":""}
                        >
                            ${d("ph-caret-left")}

                            Anterior
                        </button>

                        <button
                            class="acc-button"
                            type="button"
                            data-page="${g.current_page+1}"
                            ${g.current_page>=g.last_page?"disabled":""}
                        >
                            Próxima

                            ${d("ph-caret-right")}
                        </button>
                    </div>
                `,e.querySelectorAll("[data-page]").forEach(f=>f.addEventListener("click",()=>b(Number(f.dataset.page))));const y=new URL(window.location.href);Object.entries(c).forEach(([f,$])=>$?y.searchParams.set(f,$):y.searchParams.delete(f)),y.searchParams.delete("page"),history.replaceState({},"",y)}catch(m){m.name!=="AbortError"&&(s.innerHTML=`
                        <tr>
                            <td colspan="7">
                                <div class="acc-error">
                                    ${t(m.message)}
                                </div>
                            </td>
                        </tr>
                    `,C(o,m))}}let k;a.addEventListener("input",p=>{clearTimeout(k),k=setTimeout(()=>b(1),p.target.name==="search"?320:0)}),a.addEventListener("change",p=>{p.target.matches('select, input[type="date"]')&&b(1)}),a.addEventListener("submit",p=>{p.preventDefault(),b(1)}),a.querySelector("[data-clear-filters]")?.addEventListener("click",()=>{a.reset(),b(1)}),b(Number(u.get("page")||1))}function F(a){const s=a.data||[];if(!s.length)return`
                <div class="acc-empty">
                    Nenhuma distribuição vinculada.
                </div>
            `;const o=`
            <div class="acc-data-table-block acc-data-table-distributions">
                <div class="acc-table-tools">
                    <div class="acc-table-tools-copy">
                        ${d("ph-arrows-left-right")}

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
                            ${s.map(e=>{const n=[`Data: ${e.parent?.date||"Sem origem"}`,`Entrega: ${e.parent?.id?`#${e.parent.id}`:"—"}`,`Produto: ${e.product||""}`,`Destino: ${e.customer||""}`,`Membro: ${e.member||""}`,`Quantidade: ${L.format(e.quantity||0)} ${e.unit||""}`.trim(),`Valor: ${h.format(e.gross_value||0)}`,`Preço unitário: ${h.format(e.unit_price||0)} / ${e.unit||""}`].join(`
`);return`
                                                <tr>
                                                    <td>
                                                        ${t(e.parent?.date||"Sem origem")}

                                                        <div class="acc-muted">
                                                            Entrega #
                                                            ${t(e.parent?.id||"—")}
                                                        </div>
                                                    </td>

                                                    <td>
                                                        <strong>
                                                            ${t(e.product)}
                                                        </strong>

                                                        <div class="acc-muted">
                                                            ${t(e.customer)}
                                                        </div>
                                                    </td>

                                                    <td>
                                                        ${t(e.member)}
                                                    </td>

                                                    <td>
                                                        ${L.format(e.quantity||0)}

                                                        ${t(e.unit)}
                                                    </td>

                                                    <td class="acc-money">
                                                        ${h.format(e.gross_value||0)}

                                                        <div class="acc-muted">
                                                            ${h.format(e.unit_price||0)}

                                                            /

                                                            ${t(e.unit)}
                                                        </div>
                                                    </td>

                                                    <td class="acc-copy-column">
                                                        ${M(n,"Copiar","acc-button-icon-copy")}
                                                    </td>
                                                </tr>
                                            `}).join("")}
                        </tbody>
                    </table>
                </div>
            </div>
        `;return(a.last_page||1)<=1?o:o+`
            <div class="acc-pagination">
                <span>
                    ${t(a.from||0)}
                    –
                    ${t(a.to||0)}
                    de
                    ${t(a.total||0)}
                </span>

                <div class="acc-pagination-actions">
                    <button
                        class="acc-button"
                        type="button"
                        data-dist-page="${a.current_page-1}"
                        ${a.current_page<=1?"disabled":""}
                    >
                        ${d("ph-caret-left")}

                        Anterior
                    </button>

                    <button
                        class="acc-button"
                        type="button"
                        data-dist-page="${a.current_page+1}"
                        ${a.current_page>=a.last_page?"disabled":""}
                    >
                        Próxima

                        ${d("ph-caret-right")}
                    </button>
                </div>
            </div>
        `}function Z(a){if(!a?.length)return`
                <div class="acc-empty">
                    Este registro legado ainda não possui
                    linhas consolidadas no snapshot.
                </div>
            `;const s=new Map;return a.forEach(o=>{const e=String(o.unit||"un").trim()||"un",n=e.toLocaleLowerCase("pt-BR"),l=s.get(n)||{unit:e,quantity:0};l.quantity+=Math.round(Number(o.quantity||0)*1e8),s.set(n,l)}),`
            <div class="acc-data-table-block acc-data-table-delivered">
                <div class="acc-table-tools">
                    <div class="acc-table-tools-copy">
                        ${d("ph-package")}

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
                            ${a.map(o=>{const e=[`Produto: ${o.product||""}`,o.project?`Projeto: ${o.project}`:"",`Quantidade: ${L.format(Number(o.quantity||0))} ${o.unit||""}`.trim(),`Preço unitário: ${h.format(Number(o.unit_price||0))}`,`Valor documental: ${h.format(Number(o.document_amount||0))}`].filter(Boolean).join(`
`);return`
                                                <tr>
                                                    <td>
                                                        <strong>
                                                            ${t(o.product)}
                                                        </strong>

                                                        <div class="acc-muted">
                                                            ${t(o.project||"")}
                                                        </div>
                                                    </td>

                                                    <td>
                                                        ${L.format(Number(o.quantity||0))}

                                                        ${t(o.unit)}
                                                    </td>

                                                    <td class="acc-money">
                                                        ${h.format(Number(o.unit_price||0))}
                                                    </td>

                                                    <td class="acc-money">
                                                        <strong>
                                                            ${h.format(Number(o.document_amount||0))}
                                                        </strong>
                                                    </td>

                                                    <td class="acc-copy-column">
                                                        ${M(e,"Copiar","acc-button-icon-copy")}
                                                    </td>
                                                </tr>
                                            `}).join("")}
                        </tbody>
                    </table>
                </div>
                <section class="acc-delivered-totals" aria-label="Total entregue por unidade">
                    <div>
                        <strong>Total entregue por unidade</strong>
                        <span>Quantidades somadas sem misturar unidades de medida.</span>
                    </div>
                    <dl>
                        ${Array.from(s.values()).map(o=>`
                            <div>
                                <dt>${t(o.unit)}</dt>
                                <dd>${L.format(o.quantity/1e8)} ${t(o.unit)}</dd>
                            </div>
                        `).join("")}
                    </dl>
                </section>
            </div>
        `}function Y(a){return a?.length?`
            <ul class="acc-simple-list">
                ${a.map(s=>`
                                <li class="acc-simple-item">
                                    <strong>
                                        Versão
                                        ${t(s.sequence)}
                                        ·
                                        ${t(s.label)}
                                    </strong>

                                    ${s.organization?`
                                                <span>
                                                    Organização:
                                                    ${t(s.organization)}
                                                </span>
                                            `:""}

                                    <span>
                                        Enviada em
                                        ${t(s.sent_at||"—")}
                                        por
                                        ${t(s.sent_by||"Membro não identificado")}
                                    </span>

                                    ${s.responded_at?`
                                                <span>
                                                    Resposta em
                                                    ${t(s.responded_at)}
                                                    por
                                                    ${t(s.responded_by||"Representante autorizado")}
                                                </span>
                                            `:""}

                                    ${s.validity?`
                                                <span>
                                                    Validade atual:
                                                    ${t(s.validity)}
                                                </span>
                                            `:""}

                                    ${s.message?`
                                                <p class="acc-auth-message">
                                                    ${t(s.message)}
                                                </p>
                                            `:""}

                                    ${s.invalidation_reason?`
                                                <p class="acc-auth-message acc-auth-warning">
                                                    ${t(s.invalidation_reason)}
                                                </p>
                                            `:""}
                                </li>
                            `).join("")}
            </ul>
        `:`
                <div class="acc-empty">
                    Processo anterior ao workflow de autorização.
                </div>
            `}function aa(a){const s=a.access||{};if(s.applicable===!1)return`
                <div class="acc-guidance">
                    <strong>
                        Destinatário individual
                    </strong>

                    <span>
                        Este processo não exige autorização
                        por representante de uma organização compradora.
                    </span>
                </div>
            `;const o=s.recipients||[];return`
            <section class="acc-auth-access">
                <h3 class="acc-section-title">
                    Quem responde pela organização
                </h3>

                ${o.length?`
                            <ul class="acc-simple-list">
                                ${o.map(e=>`
                                                <li class="acc-simple-item">
                                                    <strong>
                                                        ${t(e.name||"Representante autorizado")}
                                                        ·
                                                        ${t(e.email)}
                                                    </strong>

                                                    <span>
                                                        ${e.has_account?"Conta ativa":"Aguardando primeiro acesso"}

                                                        ${e.last_access_at?` · último acesso ${t(e.last_access_at)}`:""}
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

                ${w.dataset.canSendAuthorization==="1"?`
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
                                        value="${t(s.organization_email||"")}"
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
                                        value="${t(s.organization_contact||"")}"
                                    >
                                </label>

                                <button
                                    class="acc-button"
                                    type="submit"
                                >
                                    ${d("ph-key")}

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
                                                href="${t(s.buyer_url)}"
                                            >
                                                ${d("ph-arrow-square-out")}

                                                Abrir tela da organização
                                            </a>
                                        `:""}
                            </div>
                        `:""}
            </section>
        `}function B(a){const s=a.data||[],o=`
            <p
                class="acc-muted"
                style="padding:.72rem .72rem 0"
            >
                Documentos de origem e repasse.
                Seus totais não formam o valor deste faturamento.
            </p>

            <div class="acc-receipt-results">
                ${s.length?s.map(e=>`
                                    <article class="acc-receipt-card">
                                        <div class="acc-receipt-card-main">
                                            <div>
                                                <strong>
                                                    ${t(e.number)}
                                                    ·
                                                    ${t(e.member)}
                                                </strong>

                                                <div class="acc-muted">
                                                    ${t(e.included_distributions||0)}
                                                    distribuição(ões)
                                                    incluída(s)
                                                    neste faturamento
                                                </div>
                                            </div>

                                            ${q(e.status_label,e.status==="paid"?"success":"warning")}
                                        </div>

                                        <div class="acc-receipt-actions">
                                            ${z(e.document_actions)}
                                            ${e.detail_url?`
                                                        <button
                                                            class="acc-button"
                                                            type="button"
                                                            data-related-detail="${t(e.detail_url)}"
                                                            data-related-title="${t(e.number)}"
                                                        >
                                                            ${d("ph-list-bullets")}

                                                            Ver distribuições
                                                        </button>
                                                    `:""}

                                            ${e.reprint_url?`
                                                        <a
                                                            class="acc-button"
                                                            href="${t(e.reprint_url)}"
                                                            target="_blank"
                                                            rel="noopener"
                                                        >
                                                            ${d("ph-file-text")}

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
        `;return(a.last_page||1)<=1?o:o+`
            <div class="acc-pagination">
                <span>
                    ${t(a.from||0)}
                    –
                    ${t(a.to||0)}
                    de
                    ${t(a.total||0)}
                </span>

                <div class="acc-pagination-actions">
                    <button
                        class="acc-button"
                        type="button"
                        data-producer-page="${a.current_page-1}"
                        ${a.current_page<=1?"disabled":""}
                    >
                        ${d("ph-caret-left")}

                        Anterior
                    </button>

                    <button
                        class="acc-button"
                        type="button"
                        data-producer-page="${a.current_page+1}"
                        ${a.current_page>=a.last_page?"disabled":""}
                    >
                        Próxima

                        ${d("ph-caret-right")}
                    </button>
                </div>
            </div>
        `}async function ta(a,s){let o=document.querySelector("[data-related-receipt-dialog]");if(!o){o=document.createElement("dialog"),o.className="acc-dialog acc-dialog-wide acc-related-dialog",o.dataset.relatedReceiptDialog="",o.innerHTML=`
                <div class="acc-dialog-head">
                    <div class="acc-dialog-title">
                        <span class="acc-dialog-icon">
                            ${d("ph-arrows-left-right")}
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
                        ${d("ph-x")}
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
                        ${d("ph-x-circle")}

                        Fechar
                    </button>
                </footer>
            `,document.body.appendChild(o);const n=()=>o.close();o.querySelector("[data-related-close]").addEventListener("click",n),o.querySelector("[data-related-footer-close]").addEventListener("click",n),o.addEventListener("click",l=>{l.target===o&&n()})}o.querySelector("[data-related-receipt-title]").textContent=s||"Documento de origem";const e=o.querySelector("[data-related-receipt-content]");e.innerHTML=j(4),o.showModal();try{e.innerHTML=H(await x(a))}catch(n){C(e,n)}}function ea(a){if(!a)return"";const s={members:"Uma seção por cliente, com associados em ordem alfabética, produtos por preço, quantidades, valores e ajustes.",acceptance:"Produtos entregues, unidade de comercialização, quantidades, valores unitários e total. Textos e assinaturas são opcionais no modelo.",documents:"Folha, comprovantes dos associados e anexos relacionados, identificando o titular de cada documento."};return`<section class="acc-report-exports" data-report-exports>
            <header><h3>Relatórios do faturamento</h3><p>Gerados com a captura atual dos dados, sem emitir comprovantes, registrar aceite ou movimentar pagamentos.</p></header>
            <div class="acc-report-filters">
                <label class="acc-field">Escopo das entregas<select data-report-scope>
                    <option value="billing">Somente distribuições desta folha</option>
                    ${a.can_project_period?'<option value="project_period">Destinatário no período e projeto(s) da folha</option>':""}
                </select></label>
                <label class="acc-field">Projeto<select data-report-project><option value="">Todos os projetos desta folha</option>
                    ${a.projects.map(o=>`<option value="${t(o.id)}">${t(o.title)}</option>`).join("")}
                </select></label>
            </div>
            <p class="acc-muted">A relação de documentos sempre se refere à folha completa. Os demais relatórios respeitam o escopo e o projeto selecionados. Para aplicar um modelo de projeto específico em folhas com vários projetos, selecione o projeto acima.</p>
            <div class="acc-report-export-list">${a.documents.map(o=>`<article><h4>${t(o.title)}</h4><p>${t(s[o.type])}</p>
                <div class="acc-document-actions-buttons"><a class="acc-button" data-report-export="${t(o.type)}" data-report-base-url="${t(o.url)}" href="${t(o.url)}?download=1" download>${d("ph-download-simple")} Baixar PDF</a>
                <a class="acc-button" data-report-export="${t(o.type)}" data-report-base-url="${t(o.url)}" data-preview-report href="${t(o.url)}?download=0" target="_blank" rel="noopener">${d("ph-eye")} Visualizar</a></div>
            </article>`).join("")}</div>
            ${a.settings_url?`<a class="acc-button" href="${t(a.settings_url)}">${d("ph-gear")} Personalizar nos PDFs do Sistema</a>`:""}
        </section>`}function sa(a){const s=a.querySelector("[data-report-scope]").value,o=a.querySelector("[data-report-project]").value;a.querySelectorAll("[data-report-export]").forEach(e=>{const n=new URL(e.dataset.reportBaseUrl,location.href);n.searchParams.set("download",e.hasAttribute("data-preview-report")?"0":"1"),e.dataset.reportExport!=="documents"&&(n.searchParams.set("scope",s),o&&n.searchParams.set("project",o)),e.href=n.href})}function oa(a){if(!a)return'<div class="acc-empty">Conferência indisponível.</div>';const s=a.summary,o=n=>L.format(Number(n||0)),e=n=>n==null?"Não disponível":h.format(Number(n));return`
            <div class="acc-reconciliation">
                <header class="acc-reconciliation-head">
                    <div><h3>Conferência de valores e quantidades</h3>
                        <p>Somente orientação: estes alertas não criam bloqueios nem alteram documentos. As regras de segurança já existentes permanecem.</p>
                        <small>Conferido em ${t(a.checked_at)} · Dados de toda a folha, sem limite de paginação.</small>
                    </div>
                    <div class="acc-reconciliation-actions">
                        <button type="button" class="acc-button" data-recheck-documents>${d("ph-arrows-clockwise")} Conferir novamente</button>
                        <button type="button" class="acc-button" data-export-reconciliation>${d("ph-download-simple")} Exportar relatório CSV</button>
                    </div>
                </header>
                <div class="acc-reconciliation-cards">
                    <div><span>Distribuições na folha</span><strong>${t(s.distributions)}</strong></div>
                    <div><span>Nos comprovantes dos membros</span><strong>${t(s.covered)} / ${t(s.distributions)}</strong></div>
                    <div><span>Sem comprovante do membro</span><strong>${t(s.uncovered)}</strong></div>
                    <div><span>Comprovantes analisados</span><strong>${t(s.member_documents)}</strong></div>
                </div>
                <section class="acc-reconciliation-section">
                    <h4>${a.warning_count?`${t(a.warning_count)} alerta(s) para revisar`:"Nenhuma divergência detectada nas verificações disponíveis"} · ${t(a.info_count)} aviso(s) de contexto</h4>
                    <ul class="acc-reconciliation-alerts">
                        ${a.alerts.map(n=>`
                            <li class="is-${n.level==="info"?"info":"warning"}">
                                <strong>${d(n.level==="info"?"ph-info":"ph-warning")} ${t(n.message)}</strong>
                                <p><b>Como revisar:</b> ${t(n.solution)}</p>
                                ${n.ids.length?`<details><summary>Ver ${t(n.ids.length)} distribuição(ões)</summary><p>${n.ids.map(l=>`#${t(l)}`).join(", ")}</p></details>`:""}
                            </li>
                        `).join("")||'<li class="is-info">Os conjuntos e totais conferidos correspondem. Consulte os limites da análise abaixo.</li>'}
                    </ul>
                </section>
                <section class="acc-reconciliation-section">
                    <h4>Quantidades por unidade de medida</h4>
                    <p>“Coberta” considera cada distribuição uma única vez, pela seleção salva dos comprovantes ativos, usando sua quantidade atual.</p>
                    <div class="acc-table-wrap"><table class="acc-table"><thead><tr><th>Unidade</th><th>Folha documental</th><th>Registros atuais da folha</th><th>Coberta por comprovantes</th><th>Sem comprovante</th></tr></thead><tbody>
                    ${a.units.map(n=>`<tr><td>${t(n.unit)}</td><td>${a.sheet_quantities_available?o(n.sheet_quantity):"Não disponível"}</td><td>${o(n.current_quantity)}</td><td>${o(n.covered_quantity)}</td><td>${o(n.uncovered_quantity)}</td></tr>`).join("")||'<tr><td colspan="5">Nenhuma quantidade disponível.</td></tr>'}
                    </tbody></table></div>
                </section>
                <section class="acc-reconciliation-section">
                    <h4>Comparativo financeiro · mesmo conjunto de distribuições</h4>
                    <div class="acc-reconciliation-cards">
                        <div><span>Bruto salvo na folha</span><strong>${e(s.sheet_gross)}</strong></div>
                        <div><span>Bruto atual dos itens da folha</span><strong>${e(s.current_gross)}</strong></div>
                        <div><span>Bruto coberto por comprovantes</span><strong>${e(s.covered_gross)}</strong></div>
                        <div><span>Líquido salvo na folha</span><strong>${e(s.sheet_net)}</strong></div>
                    </div>
                    <p>O bruto atual é quantidade × preço, antes de taxas. A folha pode arredondar por linha consolidada; os líquidos usam regras de taxas próprias.</p>
                </section>
                <details class="acc-reconciliation-section"><summary>Relatório por produto e projeto</summary>
                    <div class="acc-table-wrap"><table class="acc-table"><thead><tr><th>Produto / projeto</th><th>Unidade</th><th>Qtd. documental</th><th>Qtd. atual</th><th>Qtd. coberta</th><th>Bruto documental¹</th><th>Bruto atual¹</th></tr></thead><tbody>
                    ${a.products.map(n=>`<tr><td><strong>${t(n.product)}</strong><div class="acc-muted">Projeto #${t(n.project_id)}</div></td><td>${t(n.unit)}</td><td>${a.sheet_quantities_available?o(n.sheet_quantity):"—"}</td><td>${o(n.current_quantity)}</td><td>${o(n.covered_quantity)}</td><td>${a.sheet_quantities_available?e(n.sheet_gross):"—"}</td><td>${e(n.current_gross)}</td></tr>`).join("")}
                    </tbody></table></div><p>¹ Antes do arredondamento por linha e das taxas.</p>
                </details>
                <details class="acc-reconciliation-section"><summary>Relatório dos comprovantes dos membros · documentos integrais</summary>
                    <p>Inclui itens de outras folhas/períodos. Quantidades reconstruídas dos registros atuais; valores financeiros salvos nos documentos.</p>
                    <div class="acc-table-wrap"><table class="acc-table"><thead><tr><th>Comprovante / membro</th><th>Situação</th><th>Nesta folha</th><th>Fora desta folha</th><th>Quantidades integrais</th><th>Bruto salvo</th><th>Líquido salvo</th></tr></thead><tbody>
                    ${a.members.map(n=>`<tr><td><strong>${t(n.number)}</strong><div>${t(n.member)}</div></td><td>${t(n.status)}</td><td>${t(n.in_billing)}</td><td>${t(n.outside_billing)}</td><td>${n.quantities.map(l=>`${o(l.quantity)} ${t(l.unit)}`).join(" · ")}</td><td>${e(n.gross)}</td><td>${e(n.net)}</td></tr>`).join("")||'<tr><td colspan="7">Nenhum comprovante ativo relacionado.</td></tr>'}
                    </tbody><tfoot><tr><th colspan="4">Soma integral dos comprovantes</th><td>${s.all_members_quantities.map(n=>`${o(n.quantity)} ${t(n.unit)}`).join(" · ")||"—"}</td><td>${e(s.all_members_gross)}</td><td>${e(s.all_members_net)}</td></tr></tfoot></table></div>
                </details>
                <details class="acc-reconciliation-section" open><summary>Critérios e limites desta conferência</summary><ul>${a.limitations.map(n=>`<li>${t(n)}</li>`).join("")}</ul></details>
            </div>`}function na(a,s){const o={distributions:"Distribuições na folha",covered:"Nos comprovantes dos membros",uncovered:"Sem comprovante do membro",member_documents:"Comprovantes analisados",sheet_gross:"Bruto salvo na folha",sheet_net:"Líquido salvo na folha",current_gross:"Bruto atual dos itens da folha",covered_gross:"Bruto coberto por comprovantes",all_members_gross:"Bruto salvo dos comprovantes integrais",all_members_net:"Líquido salvo dos comprovantes integrais"},n=[["Conferência não bloqueante",s,a.checked_at],["Resumo","Valor"],...Object.entries(o).map(([r,i])=>[i,a.summary[r]??"Não disponível"]),[],["Unidade","Quantidade documental","Quantidade atual","Quantidade coberta","Quantidade sem comprovante"],...a.units.map(r=>[r.unit,a.sheet_quantities_available?r.sheet_quantity:"Não disponível",r.current_quantity,r.covered_quantity,r.uncovered_quantity]),[],["Projeto","Produto","Unidade","Quantidade documental","Quantidade atual","Quantidade coberta","Bruto documental","Bruto atual"],...a.products.map(r=>[r.project_id,r.product,r.unit,a.sheet_quantities_available?r.sheet_quantity:"",r.current_quantity,r.covered_quantity,a.sheet_quantities_available?r.sheet_gross:"",r.current_gross]),[],["Comprovante","Membro","Situação","Nesta folha","Fora da folha","Quantidades integrais (atuais)","Bruto salvo","Líquido salvo"],...a.members.map(r=>[r.number,r.member,r.status,r.in_billing,r.outside_billing,r.quantities.map(i=>`${i.quantity} ${i.unit}`).join(" / "),r.gross,r.net]),[],["Tipo","Alerta","Como revisar","Distribuições"],...a.alerts.map(r=>[r.level,r.message,r.solution,r.ids.join(", ")]),[],["Limites da análise"],...a.limitations.map(r=>[r])].map(r=>r.map(i=>{let u=String(i??"");return/^-?\d+\.\d+$/.test(u)?u=u.replace(".",","):/^[\s]*[=+\-@]/.test(u)&&(u="'"+u),`"${u.replaceAll('"','""')}"`}).join(";")).join(`\r
`),l=URL.createObjectURL(new Blob(["\uFEFF",n],{type:"text/csv;charset=utf-8"})),v=document.createElement("a");v.href=l,v.download=`conferencia-${String(s).replace(/[^a-zA-Z0-9-]/g,"-")}.csv`,v.click(),setTimeout(()=>URL.revokeObjectURL(l),1e3)}async function P(a="overview"){const s=document.querySelector("[data-dossier]");if(s){s.innerHTML=j(7);try{const o=await x(w.dataset.processDataUrl),e=o.process,n=e.integrity;s.innerHTML=`
                <section class="acc-panel">
                    <div class="acc-state-strip">
                        <div class="acc-state-item">
                            <span>
                                Processo
                            </span>

                            <strong>
                                ${t(e.state.label)}
                            </strong>
                        </div>

                        <div class="acc-state-item">
                            <span>
                                Autorização
                            </span>

                            <strong>
                                ${t(e.workflow.authorization.label)}
                            </strong>
                        </div>

                        <div class="acc-state-item">
                            <span>
                                Fiscal
                            </span>

                            <strong>
                                ${t(e.workflow.fiscal.label)}
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
                            data-tab="reports"
                            data-tone="info"
                            role="tab"
                            aria-selected="false"
                        >
                            ${d("ph-files")}
                            <span>Relatórios</span>
                        </button>
                        <button class="acc-tab" type="button"
                            data-tab="reconciliation"
                            data-tone="warning"
                            role="tab"
                            aria-selected="false"
                        >
                            ${d("ph-scales")}
                            <span>Conferência ${o.reconciliation?`(${t(o.reconciliation.warning_count+o.reconciliation.info_count)})`:""}</span>
                        </button>
                        <button class="acc-tab" type="button"
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
                                    ${t(e.project?.title||"Não identificado")}
                                </strong>
                            </div>

                            <div class="acc-detail">
                                <span>
                                    Destinatário
                                </span>

                                <strong>
                                    ${t(e.recipient.name)}
                                </strong>
                            </div>

                            <div class="acc-detail">
                                <span>
                                    Período
                                </span>

                                <strong>
                                    ${t(e.period)}
                                </strong>
                            </div>

                            <div class="acc-detail">
                                <span>
                                    Valor bruto
                                </span>

                                <strong>
                                    ${h.format(e.financial.gross||0)}
                                </strong>
                            </div>

                            <div class="acc-detail">
                                <span>
                                    Taxas
                                </span>

                                <strong>
                                    ${h.format(e.financial.fees||0)}
                                </strong>
                            </div>

                            <div class="acc-detail">
                                <span>
                                    Valor líquido
                                </span>

                                <strong>
                                    ${h.format(e.financial.net||0)}
                                </strong>
                            </div>

                            <div class="acc-detail">
                                <span>
                                    Distribuições
                                </span>

                                <strong>
                                    ${t(e.summary.distributions)}
                                </strong>
                            </div>

                            <div class="acc-detail">
                                <span>
                                    Produtores
                                </span>

                                <strong>
                                    ${t(e.summary.producers)}
                                </strong>
                            </div>

                            <div class="acc-detail">
                                <span>
                                    Produtos
                                </span>

                                <strong>
                                    ${t(e.summary.products)}
                                </strong>
                            </div>

                            <div class="acc-detail">
                                <span>
                                    Unidades recebedoras
                                </span>

                                <strong>
                                    ${t(e.summary.recipient_units)}
                                </strong>
                            </div>

                            <div class="acc-detail">
                                <span>
                                    Documentos de origem
                                </span>

                                <strong>
                                    ${t(e.summary.source_receipts)}
                                </strong>
                            </div>
                        </div>
                    </div>

                    <div
                        class="acc-tab-panel"
                        data-panel="delivered"
                    >
                        ${Z(o.consolidated_lines)}
                    </div>

                    <div
                        class="acc-tab-panel"
                        data-panel="distributions"
                    >
                        <div data-distributions-panel>
                            ${F(o.distributions)}
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
                                    ${h.format(e.financial.received||0)}
                                </strong>
                            </div>

                            <div class="acc-detail">
                                <span>
                                    Saldo restante
                                </span>

                                <strong>
                                    ${h.format(e.financial.remaining||0)}
                                </strong>
                            </div>

                            <div class="acc-detail">
                                <span>
                                    Situação financeira
                                </span>

                                <strong>
                                    ${t(e.financial.status_label)}
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
                            ${o.payments.length?o.payments.map(c=>`
                                                <li class="acc-simple-item">
                                                    <strong>
                                                        ${h.format(c.amount||0)}
                                                        ·
                                                        ${t(c.date)}
                                                    </strong>

                                                    <span>
                                                        ${t(c.account||c.method||"Sem conta informada")}
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
                        ${z(e.document_actions)}
                        <div class="acc-documents-workspace">
                            <section
                                class="acc-subsection acc-subsection-fiscal"
                            >
                                <header class="acc-subsection-head">
                                    <div class="acc-subsection-title">
                                        <span
                                            class="acc-subsection-icon acc-subsection-icon-purple"
                                        >
                                            ${d("ph-file-text")}
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
                                        ${M([`Projeto: ${e.project?.title||"Não identificado"}`,`Destinatário: ${e.recipient.name||""}`,`Período: ${e.period||""}`,`Valor bruto: ${h.format(e.financial.gross||0)}`,`Taxas: ${h.format(e.financial.fees||0)}`,`Valor líquido: ${h.format(e.financial.net||0)}`,`Situação fiscal: ${e.workflow.fiscal.label||""}`,`Documento esperado: ${e.workflow.fiscal.document_type||"Não configurado"}`].join(`
`),"Copiar dados")}

                                        ${e.pdf_url||e.workflow.fiscal.billing_sheet_url?`
                                                    <a
                                                        class="acc-button acc-button-primary"
                                                        data-sgc-pdf
                                                        title="Faturamento ${t(e.number)}"
                                                        href="${t(e.pdf_url||e.workflow.fiscal.billing_sheet_url)}"
                                                    >
                                                        ${d("ph-printer")}

                                                        ${e.financial.status==="draft"?"Imprimir prévia do faturamento":"Imprimir faturamento completo"}
                                                    </a>
                                                `:e.workflow.fiscal.settings_url?`
                                                            <a
                                                                class="acc-button acc-button-primary"
                                                                href="${t(e.workflow.fiscal.settings_url)}"
                                                            >
                                                                ${d("ph-gear")}

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
                                                ${d("ph-seal-check")}
                                            </span>

                                            <div class="acc-metric-copy">
                                                <span>
                                                    Situação fiscal
                                                </span>

                                                <strong>
                                                    ${t(e.workflow.fiscal.label)}
                                                </strong>
                                            </div>
                                        </div>

                                        <div class="acc-metric-row">
                                            <span
                                                class="acc-metric-icon acc-tone-blue"
                                            >
                                                ${d("ph-file-search")}
                                            </span>

                                            <div class="acc-metric-copy">
                                                <span>
                                                    Documento esperado
                                                </span>

                                                <strong>
                                                    ${t(e.workflow.fiscal.document_type||"Não configurado")}
                                                </strong>
                                            </div>
                                        </div>

                                        <div class="acc-metric-row">
                                            <span
                                                class="acc-metric-icon acc-tone-green"
                                            >
                                                ${d("ph-currency-circle-dollar")}
                                            </span>

                                            <div class="acc-metric-copy">
                                                <span>
                                                    Valor para emissão
                                                </span>

                                                <strong>
                                                    ${e.workflow.fiscal.expected_amount==null?"Não determinado":h.format(e.workflow.fiscal.expected_amount)}
                                                </strong>
                                            </div>
                                        </div>
                                    </div>

                                    ${e.workflow.fiscal.blocks?.length?`
                                                <div class="acc-document-alerts">
                                                    ${e.workflow.fiscal.blocks.map(c=>`
                                                                    <div class="acc-document-alert">
                                                                        <span>
                                                                            ${d("ph-warning-circle")}
                                                                        </span>

                                                                        <div>
                                                                            <strong>
                                                                                Revisar antes de emitir
                                                                            </strong>

                                                                            <p>
                                                                                ${t(c.message)}
                                                                            </p>
                                                                        </div>
                                                                    </div>
                                                                `).join("")}
                                                </div>
                                            `:""}
                                </div>
                            </section>

                            ${e.workflow.authorization.access?.applicable===!1&&!o.authorizations.length?"":`
                                        <section
                                            class="acc-subsection acc-subsection-authorization"
                                        >
                                            <header class="acc-subsection-head">
                                                <div class="acc-subsection-title">
                                                    <span
                                                        class="acc-subsection-icon acc-subsection-icon-blue"
                                                    >
                                                        ${d("ph-seal-check")}
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

                                                ${w.dataset.canSendAuthorization==="1"&&["legacy_unsubmitted","cancelled"].includes(e.workflow.authorization.state)?`
                                                            <button
                                                                class="acc-button"
                                                                type="button"
                                                                data-send-authorization
                                                            >
                                                                ${d("ph-paper-plane-tilt")}

                                                                Enviar autorização
                                                            </button>
                                                        `:""}
                                            </header>

                                            <div class="acc-subsection-body">
                                                ${aa(e.workflow.authorization)}

                                                <div
                                                    class="acc-action-feedback"
                                                    data-authorization-feedback
                                                    aria-live="polite"
                                                ></div>

                                                ${Y(o.authorizations)}
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
                                            ${d("ph-receipt")}
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
                                        ${t(e.summary.source_receipts||0)}
                                    </span>
                                </header>

                                <div
                                    class="acc-subsection-body acc-subsection-body-flush"
                                >
                                    ${B(o.producer_receipts)}
                                </div>
                            </section>
                        </div>
                    </div>

                    <div
                        class="acc-tab-panel"
                        data-panel="reports"
                    >
                        ${ea(o.report_exports)}
                    </div>
                    <div class="acc-tab-panel"
                        data-panel="reconciliation"
                    >
                        ${oa(o.reconciliation)}
                    </div>
                    <div class="acc-tab-panel"
                        data-panel="timeline"
                    >
                        <ul class="acc-simple-list">
                            ${o.timeline.length?o.timeline.map(c=>`
                                                <li class="acc-simple-item">
                                                    <strong>
                                                        ${t(c.description)}
                                                    </strong>

                                                    <span>
                                                        ${t(c.date)}
                                                        ·
                                                        ${t(c.actor)}
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
                                    ${d("ph-arrow-circle-right")}
                                </span>

                                <div>
                                    <h2>
                                        Próxima ação
                                    </h2>

                                    <p>
                                        ${t(e.state.next_action)}
                                    </p>
                                </div>
                            </div>

                            ${q(e.state.label,e.state.tone)}
                        </div>

                        <div
                            class="acc-action-box"
                            data-authorization-action
                        >
                            ${e.edit_url?`
                                        <a
                                            class="acc-button acc-button-primary"
                                            href="${t(e.edit_url)}"
                                        >
                                            ${d("ph-pencil-line")}

                                            Continuar e conferir faturamento
                                        </a>

                                        <span class="acc-muted">
                                            Revise as entregas e os valores
                                            antes de fechar.
                                        </span>
                                    `:""}

                            ${e.reopen_url&&n.snapshot_changed?`
                                    <button
                                        class="acc-button acc-button-primary"
                                        type="button"
                                        data-reopen-billing
                                        data-url="${t(e.reopen_url)}"
                                    >
                                        ${d("ph-arrows-clockwise")}
                                        Atualizar e gerar nova versão
                                    </button>

                                    <span class="acc-muted">
                                        O comprovante atual será preservado no histórico e a seleção será reaberta com os vínculos atuais.
                                    </span>
                                `:""}

                            ${e.pdf_url||e.workflow.fiscal.billing_sheet_url?`
                                    <a
                                        class="acc-button ${e.edit_url?"":"acc-button-primary"}"
                                        data-sgc-pdf
                                        title="Faturamento ${t(e.number)}"
                                        href="${t(e.pdf_url||e.workflow.fiscal.billing_sheet_url)}"
                                    >
                                        ${d("ph-printer")}

                                        ${e.financial.status==="draft"?"Imprimir prévia do faturamento":"Imprimir faturamento completo"}
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
                                    ${d("ph-checks")}
                                </span>

                                <div>
                                    <h2>
                                        Conferência dos dados
                                    </h2>

                                    <p>
                                        ${n.critical_count?`${t(n.critical_count)} erro(s) de integridade`:n.preparation_count?`${t(n.preparation_count)} item(ns) a completar`:"Dados prontos para avançar"}
                                    </p>
                                </div>
                            </div>

                            ${q(n.critical_count?"Corrigir erro":n.preparation_count?"Em preparação":"Pronto",n.critical_count?"danger":n.preparation_count?"warning":"success")}
                        </div>

                        <div class="acc-side-panel-body">
                            ${n.snapshot_changed?`
                                    <div class="acc-alert is-error acc-snapshot-warning">
                                        <strong>Comprovante desatualizado</strong>
                                        <span>
                                            ${t(n.snapshot_changes?.added_ids?.length||0)} adicionada(s) e
                                            ${t(n.snapshot_changes?.removed_ids?.length||0)} removida(s).
                                            É necessário revisar o faturamento e gerar uma nova versão.
                                        </span>
                                    </div>
                                `:""}
                            <ul class="acc-integrity-list">
                                ${n.issues.length?n.issues.map(c=>`
                                                    <li class="acc-integrity-item">
                                                        ${q(c.severity==="critical"?"Erro":"Preparação",c.severity==="critical"?"danger":"warning")}

                                                        ${t(c.message)}
                                                    </li>
                                                `).join(""):`
                                            <li class="acc-simple-item">
                                                Nenhuma pendência encontrada.
                                            </li>
                                        `}
                            </ul>
                            ${n.repair_url?`
                                        <button class="acc-button" type="button" data-repair-integrity data-url="${t(n.repair_url)}">
                                            ${d("ph-wrench")}
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
                                    ${d("ph-files")}
                                </span>

                                <div>
                                    <h2>
                                        Arquivos anexados
                                    </h2>

                                    <p>
                                        ${t(o.documents.length)}
                                        arquivo(s)
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="acc-side-panel-body">
                            <ul class="acc-simple-list acc-document-list">
                                ${o.documents.length?o.documents.map(c=>`
                                                    <li
                                                        class="acc-simple-item acc-document-row"
                                                    >
                                                        <span
                                                            class="acc-document-row-icon"
                                                        >
                                                            ${d("ph-file")}
                                                        </span>

                                                        <div>
                                                            <strong>
                                                                ${t(c.name)}
                                                            </strong>

                                                            <span>
                                                                ${t(c.category)}
                                                                ·
                                                                ${t(c.date)}
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
            `,s.querySelectorAll("[data-tab]").forEach(c=>c.addEventListener("click",()=>{s.querySelectorAll("[data-tab]").forEach(m=>{const g=m===c;m.classList.toggle("is-active",g),m.setAttribute("aria-selected",g?"true":"false"),g?m.setAttribute("aria-current","page"):m.removeAttribute("aria-current")}),s.querySelectorAll("[data-panel]").forEach(m=>m.classList.toggle("is-active",m.dataset.panel===c.dataset.tab)),window.innerWidth<760&&c.scrollIntoView({behavior:"smooth",inline:"center",block:"nearest"})})),a!=="overview"&&s.querySelector(`[data-tab="${a}"]`)?.click();const l=s.querySelector("[data-report-exports]");l?.addEventListener("change",()=>sa(l)),s.querySelector("[data-export-reconciliation]")?.addEventListener("click",()=>na(o.reconciliation,e.number)),s.querySelector("[data-recheck-documents]")?.addEventListener("click",()=>P("reconciliation"));const v=s.querySelector("[data-access-form]");v?.addEventListener("submit",async c=>{c.preventDefault();const m=v.querySelector('button[type="submit"]'),g=v.querySelector("[data-access-feedback]");m.disabled=!0,g.textContent="Salvando...";try{const y=await D(w.dataset.authorizationAccessUrl,Object.fromEntries(new FormData(v).entries()));g.textContent=y.message,await P()}catch(y){g.textContent=y.message,g.classList.add("is-error"),m.disabled=!1}});const r=s.querySelector("[data-send-authorization]"),i=s.querySelector("[data-repair-integrity]"),u=s.querySelector("[data-reopen-billing]");u?.addEventListener("click",async()=>{if(window.confirm("Reabrir este faturamento com as distribuições atualmente vinculadas? A versão anterior continuará preservada no histórico.")){u.disabled=!0;try{const c=await D(u.dataset.url,{});if(c.redirect_url){window.location.assign(c.redirect_url);return}await P()}catch(c){window.alert(c.message),u.disabled=!1}}}),i?.addEventListener("click",async()=>{const c=s.querySelector("[data-integrity-feedback]");i.disabled=!0,c.textContent="Verificando e corrigindo vínculos...";try{const m=await D(i.dataset.url,{});c.textContent=m.message,await P()}catch(m){c.textContent=m.message,c.classList.add("is-error"),i.disabled=!1}}),r?.addEventListener("click",async()=>{if(!window.confirm(`Enviar esta cobrança para autorização?

Valor: ${h.format(e.financial.net||0)}
Período: ${e.period}`))return;const c=s.querySelector("[data-authorization-feedback]");r.disabled=!0,c.textContent="Enviando...";try{await D(w.dataset.authorizationSendUrl,{operation_key:crypto.randomUUID()}),c.textContent="Cobrança enviada.",await P()}catch(m){c.textContent=m.message,c.classList.add("is-error"),r.disabled=!1}});const b=()=>s.querySelectorAll("[data-dist-page]").forEach(c=>c.addEventListener("click",async()=>{const m=s.querySelector("[data-distributions-panel]");if(m){m.innerHTML=j(3);try{const g=await x(T(w.dataset.processDataUrl,{distributions_page:c.dataset.distPage}));m.innerHTML=F(g.distributions),b()}catch(g){C(m,g)}}}));b();const k=()=>s.querySelectorAll("[data-producer-page]").forEach(c=>c.addEventListener("click",async()=>{const g=s.querySelector('[data-panel="documents"]')?.querySelector(".acc-subsection-source .acc-subsection-body");if(g){g.innerHTML=j(3);try{const y=await x(T(w.dataset.processDataUrl,{producer_receipts_page:c.dataset.producerPage}));g.innerHTML=B(y.producer_receipts),k(),p()}catch(y){C(g,y)}}})),p=()=>s.querySelectorAll("[data-related-detail]").forEach(c=>c.addEventListener("click",()=>ta(c.dataset.relatedDetail,c.dataset.relatedTitle)));k(),p()}catch(o){C(s,o)}}}function H(a){const s=a.receipt,o=a.distributions||[],e=new Map((a.processes||[]).map(i=>[Number(i.id),i])),n=Number(w.dataset.receiptId||0),l=new Map;o.forEach(i=>l.set(i.unit||"un",(l.get(i.unit||"un")||0)+Math.round(Number(i.quantity||0)*1e4)));const v=i=>o.reduce((u,b)=>u+Math.round(Number(b[i]||0)*1e4),0)/1e4,r=[...l].map(([i,u])=>`${L.format(u/1e4)} ${t(i)}`).join(" · ")||"—";return`
            <section class="acc-source-detail" data-source-detail>
                ${z(s.document_actions)}
                <div class="acc-detail-grid acc-receipt-summary">
                    <div class="acc-detail"><span>Produtor</span><strong>${t(s.member)}</strong></div>
                    <div class="acc-detail"><span>Comprovante</span><strong>${t(s.number)}</strong><small>${t(s.status_label)}</small></div>
                    <div class="acc-detail"><span>Emissão / projeto</span><strong>${t(s.issued_at||"—")}</strong><small>${t(s.project)}</small></div>
                </div>
                <div class="acc-source-totals">
                    <div><span>Distribuições</span><strong>${o.length}</strong></div>
                    <div><span>Quantidade entregue</span><strong>${r}</strong></div>
                    <div><span>Bruto das distribuições</span><strong>${h.format(v("gross"))}</strong></div>
                    <div><span>Taxas das distribuições</span><strong>${h.format(v("fees"))}</strong></div>
                    <div><span>Líquido das distribuições</span><strong>${h.format(v("net"))}</strong></div>
                    <div><span>Valor do comprovante</span><strong>${h.format(s.total_net||0)}</strong></div>
                </div>
                <div class="acc-source-tools">
                    <label class="acc-field"><span>Buscar distribuição</span><input class="acc-input" type="search" data-source-search placeholder="Produto, cliente, data, ID ou observação"></label>
                    <label class="acc-field"><span>Faturamento</span><select class="acc-input" data-source-coverage>
                        <option value="all">Todas as distribuições</option>
                        ${n?'<option value="current">Neste faturamento</option>':""}
                        <option value="draft">Em faturamento rascunho</option>
                        <option value="without">Sem faturamento identificado</option>
                    </select></label>
                </div>
                <div class="acc-source-list">
                    ${o.length?o.map(i=>{const u=i.billing_references||(e.has(Number(i.billing_receipt_id))?[{...e.get(Number(i.billing_receipt_id)),source:"linked"}]:[]),b=u.some(p=>Number(p.id)===n&&p.source!=="snapshot"),k=[i.id,i.product,i.customer,i.date,i.notes,...u.map(p=>p.number)].join(" ").toLocaleLowerCase("pt-BR");return`<article class="acc-source-distribution" data-source-distribution
                            data-search="${t(k)}" data-current="${b?"1":"0"}"
                            data-draft="${u.some(p=>p.source==="draft")?"1":"0"}"
                            data-without="${!u.length&&!i.billing_restricted&&!i.billing_receipt_id?"1":"0"}"
                            data-gross="${Number(i.gross||0)}">
                            <header><div><small>Distribuição #${t(i.id)} · ${t(i.date||"Sem data")}</small><h4>${t(i.product)}</h4><p>${t(i.customer)}</p></div>
                                <div class="acc-source-badges">${q(i.status||"Não informada","neutral")}
                                ${b?q("Neste faturamento","info"):""}
                                ${i.paid?q("Paga ao membro","success"):""}</div></header>
                            <div class="acc-source-values">
                                <div><span>Quantidade</span><strong>${L.format(i.quantity||0)} ${t(i.unit)}</strong></div>
                                <div><span>Preço unitário</span><strong>${h.format(i.unit_price||0)}</strong></div>
                                <div><span>Valor bruto</span><strong>${h.format(i.gross||0)}</strong></div>
                                <div><span>Taxas</span><strong>${h.format(i.fees||0)}</strong></div>
                                <div class="is-net"><span>Valor líquido</span><strong>${h.format(i.net||0)}</strong></div>
                            </div>
                            <div class="acc-source-links">
                                <div><span>Comprovante do membro</span><strong>${t(s.number)}</strong><small>${t(s.status_label)}</small></div>
                                <div><span>Faturamento do cliente</span>
                                    ${u.map(p=>`<div class="acc-source-billing" data-source="${t(p.source)}">
                                        <a class="acc-link" href="${t(p.url)}">Folha ${t(p.number)}</a>
                                        <small>${p.source==="snapshot"?"Referência histórica · sem vínculo atual":p.source==="draft"?"Rascunho · seleção salva":t(p.status)}</small>
                                    </div>`).join("")||"<strong>"+(i.billing_restricted||i.billing_receipt_id?"Faturamento com acesso restrito":"Sem faturamento identificado")+"</strong>"}
                                </div>
                            </div>
                            ${i.parent?`<div class="acc-source-parent">Entrega de origem #${t(i.parent.id)} · ${t(i.parent.date||"Sem data")} · Quantidade recebida: ${L.format(i.parent.quantity||0)} ${t(i.unit)}</div>`:""}
                            ${i.notes?`<details class="acc-source-notes"><summary>Observações da entrega</summary><p>${t(i.notes)}</p></details>`:""}
                        </article>`}).join(""):'<div class="acc-empty">Este comprovante não possui distribuições vinculadas.</div>'}
                </div>
                <p class="acc-muted" data-source-visible-summary>${o.length} distribuição(ões) · Bruto ${h.format(v("gross"))}</p>
            </section>
        `}function U(a){const s=a.closest("[data-source-detail]");if(!s)return;const o=s.querySelector("[data-source-search]").value.trim().toLocaleLowerCase("pt-BR"),e=s.querySelector("[data-source-coverage]").value,n=[...s.querySelectorAll("[data-source-distribution]")],l=n.filter(r=>(r.hidden=!r.dataset.search.includes(o)||e!=="all"&&r.dataset[e]!=="1",!r.hidden)),v=l.reduce((r,i)=>r+Math.round(Number(i.dataset.gross||0)*1e4),0)/1e4;s.querySelector("[data-source-visible-summary]").textContent=`${l.length} de ${n.length} distribuição(ões) · Bruto ${h.format(v)}`}document.addEventListener("input",a=>{a.target.matches("[data-source-search]")&&U(a.target)}),document.addEventListener("change",a=>{a.target.matches("[data-source-coverage]")&&U(a.target)});function ia(a){return`
            <article class="acc-receipt-card">
                <div class="acc-receipt-card-main">
                    <div>
                        <button
                            class="acc-link acc-link-button"
                            type="button"
                            data-source-receipt="${t(a.id)}"
                            data-detail-url="${t(a.detail_url)}"
                        >
                            ${t(a.number)}
                        </button>

                        <div class="acc-muted">
                            ${t(a.member)}
                            ·
                            ${t(a.project)}
                        </div>
                    </div>

                    ${q(a.status_label,a.status==="paid"?"success":a.status==="obsolete"?"danger":"warning")}
                </div>

                <div class="acc-receipt-meta">
                    <span>
                        Emissão

                        <strong>
                            ${t(a.issued_at||"—")}
                        </strong>
                    </span>

                    <span>
                        Distribuições

                        <strong>
                            ${t(a.distribution_count)}
                        </strong>
                    </span>

                    <span>
                        Valor do comprovante

                        <strong>
                            ${h.format(a.total_net||0)}
                        </strong>
                    </span>

                    <span>
                        Referência QR

                        <strong>
                            ${t(a.reference_code||"Não gerada")}
                        </strong>
                    </span>
                </div>

                <div class="acc-receipt-actions">
                    ${z(a.document_actions)}
                    <button
                        class="acc-button"
                        type="button"
                        data-source-receipt="${t(a.id)}"
                        data-detail-url="${t(a.detail_url)}"
                    >
                        ${d("ph-tree-structure")}

                        Ver entregas
                    </button>

                    ${a.reprint_url?`
                                <a
                                    class="acc-button"
                                    href="${t(a.reprint_url)}"
                                    target="_blank"
                                    rel="noopener"
                                >
                                    ${d("ph-file-text")}

                                    Abrir comprovante
                                </a>
                            `:""}
                </div>
            </article>
        `}async function ca(){const a=document.querySelector("[data-source-receipt-filters]");if(!a)return;const s=document.querySelector("[data-source-receipt-results]"),o=document.querySelector("[data-source-receipt-pagination]"),e=a.elements.project,n=document.querySelector("[data-qr-dialog]"),l=document.querySelector("[data-receipt-dialog]"),v=document.querySelector("[data-receipt-detail]"),r=document.querySelector("[data-receipt-title]"),i=document.querySelector("[data-scanner-video]"),u=document.querySelector("[data-scanner-error]");let b=null,k=!1,p=null;const c=()=>{b?.stop?.(),b=null,i&&(i.srcObject=null)},m=$=>{a.elements.search.value=String($||"").trim(),c(),n?.close(),f(1)};async function g($,_="Comprovante"){if(!(!l||!v)){r&&(r.textContent=_),v.innerHTML=j(5),l.showModal();try{v.innerHTML=H(await x($))}catch(E){C(v,E)}}}const y=()=>s?.querySelectorAll("[data-source-receipt]").forEach($=>$.addEventListener("click",()=>g($.dataset.detailUrl,$.textContent.trim())));async function f($=1){p?.abort(),p=new AbortController,s.innerHTML=j(5),o.innerHTML="";const _=Object.fromEntries(new FormData(a).entries());_.page=$;try{const E=await x(T(w.dataset.sourceReceiptsUrl,_),p.signal);!k&&e&&(e.innerHTML='<option value="">Todos os projetos</option>'+(E.filters?.projects||[]).map(A=>`
                                    <option value="${t(A.id)}">
                                        ${t(A.label)}
                                    </option>
                                `).join(""),e.value=_.project||"",k=!0);const S=E.receipts;s.innerHTML=S.data.length?S.data.map(ia).join(""):`
                            <div class="acc-empty">
                                Nenhum comprovante encontrado.
                                Confira o número ou leia novamente o QR Code.
                            </div>
                        `,o.innerHTML=`
                    <span>
                        ${t(S.from||0)}
                        –
                        ${t(S.to||0)}
                        de
                        ${t(S.total||0)}
                    </span>

                    <div class="acc-pagination-actions">
                        <button
                            class="acc-button"
                            type="button"
                            data-page="${S.current_page-1}"
                            ${S.current_page<=1?"disabled":""}
                        >
                            ${d("ph-caret-left")}

                            Anterior
                        </button>

                        <button
                            class="acc-button"
                            type="button"
                            data-page="${S.current_page+1}"
                            ${S.current_page>=S.last_page?"disabled":""}
                        >
                            Próxima

                            ${d("ph-caret-right")}
                        </button>
                    </div>
                `,o.querySelectorAll("[data-page]").forEach(A=>A.addEventListener("click",()=>f(Number(A.dataset.page)))),y(),_.search&&(/\bCP-/i.test(_.search)||/[0-9a-f]{8}-[0-9a-f-]{27,}/i.test(_.search))&&S.data.length===1&&await g(S.data[0].detail_url,S.data[0].number)}catch(E){E.name!=="AbortError"&&C(s,E)}}a.addEventListener("submit",$=>{$.preventDefault(),f(1)}),a.elements.status?.addEventListener("change",()=>f(1)),a.elements.project?.addEventListener("change",()=>f(1)),document.querySelector("[data-close-receipt]")?.addEventListener("click",()=>l?.close()),document.querySelector("[data-close-scanner]")?.addEventListener("click",()=>{c(),n?.close()}),n?.addEventListener("close",c),document.querySelector("[data-use-manual]")?.addEventListener("click",()=>m(document.querySelector("[data-scanner-manual]")?.value)),document.querySelector("[data-open-scanner]")?.addEventListener("click",async()=>{if(!n)return;u&&(u.hidden=!0);const $=window.SgcQrScanner;if($?.nativePlugin()){try{const _=await $.scanNative({batch:!1});_?.code&&m(_.code)}catch(_){_?.code!=="SCAN_CANCELLED"&&u&&(n.showModal(),u.textContent=_?.message||"Não foi possível abrir o leitor nativo.",u.hidden=!1)}return}if(n.showModal(),!$?.supportsWeb()){u&&(u.textContent="A leitura automática não está disponível neste navegador. Cole o código ou link no campo abaixo.",u.hidden=!1);return}try{b=await $.openWeb({video:i,single:!0,interval:180,onCode:m})}catch{u&&(u.textContent="Não foi possível acessar a câmera. Autorize o uso ou cole o código manualmente.",u.hidden=!1),c()}}),f(1)}N==="queue"&&X(),N==="processes"&&K(),N==="dossier"&&P(),N==="source-receipts"&&ca()})();
