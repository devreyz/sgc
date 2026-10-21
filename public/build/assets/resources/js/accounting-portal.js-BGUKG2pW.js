import"../../qr-scanner-core-CTBFOQOA.js";(function(){const _=document.querySelector("[data-accounting-page]");if(!_)return;const A=_.dataset.accountingPage,m=new Intl.NumberFormat("pt-BR",{style:"currency",currency:"BRL"}),S=new Intl.NumberFormat("pt-BR",{minimumFractionDigits:0,maximumFractionDigits:4}),a=t=>String(t??"").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;").replace(/'/g,"&#039;"),F={"circle-check":"ph-check-circle","chevron-left":"ph-caret-left","chevron-right":"ph-caret-right","list-tree":"ph-tree-structure","file-text":"ph-file-text","alert-triangle":"ph-warning","circle-alert":"ph-warning-circle",clock:"ph-clock",wallet:"ph-wallet",receipt:"ph-receipt","receipt-text":"ph-receipt",files:"ph-files","file-clock":"ph-file-dashed",send:"ph-paper-plane-tilt",search:"ph-magnifying-glass","folder-search":"ph-folder-open",settings:"ph-gear",printer:"ph-printer","key-round":"ph-key","external-link":"ph-arrow-square-out","pencil-line":"ph-pencil-line",x:"ph-x"},Q=t=>{const s=String(t||"").trim();return s?s.startsWith("ph-")?s:F[s]||`ph-${s}`:"ph-circle"},d=t=>`
        <i
            class="ph-fill ${a(Q(t))}"
            aria-hidden="true"
        ></i>
    `;(()=>{if(document.querySelector('link[href*="@phosphor-icons"][href*="fill"]'))return;const t=document.createElement("link");t.rel="stylesheet",t.href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/fill/style.css",document.head.appendChild(t)})();const w=(t,s="neutral")=>`
        <span
            class="acc-badge acc-badge-${a(s)}"
        >
            ${a(t)}
        </span>
    `,L=(t=4)=>Array.from({length:t},()=>'<div class="acc-skeleton"></div>').join("");async function C(t,s){const n=await fetch(t,{headers:{Accept:"application/json","X-Requested-With":"XMLHttpRequest"},credentials:"same-origin",cache:"no-store",signal:s}),e=await n.json().catch(()=>({}));if(!n.ok)throw new Error(e.message||"Não foi possível carregar os dados.");return e}async function z(t,s){const n=document.querySelector('meta[name="csrf-token"]')?.content||"",e=await fetch(t,{method:"POST",headers:{Accept:"application/json","Content-Type":"application/json","X-Requested-With":"XMLHttpRequest","X-CSRF-TOKEN":n},credentials:"same-origin",cache:"no-store",body:JSON.stringify(s||{})}),o=await e.json().catch(()=>({}));if(!e.ok){const l=Array.isArray(o.issues)?o.issues.map(v=>v.message).join(" "):"";throw new Error(l||o.message||"Não foi possível concluir a ação.")}return o}function j(t,s){t&&(t.innerHTML=`
            <div
                class="acc-error"
                role="alert"
            >
                ${a(s.message||s)}
            </div>
        `)}function P(t,s){const n=new URL(t,window.location.origin);return Object.entries(s||{}).forEach(([e,o])=>{o!==""&&o!==null&&o!==void 0&&n.searchParams.set(e,o)}),n.toString()}const U=t=>encodeURIComponent(String(t??"")),D=(t,s="Copiar",n="")=>`
        <button
            class="acc-button acc-copy-button ${a(n)}"
            type="button"
            data-copy-text="${a(U(t))}"
        >
            ${d("ph-copy")}

            <span data-copy-label>
                ${a(s)}
            </span>
        </button>
    `;async function V(t){const s=String(t??"");if(navigator.clipboard?.writeText){await navigator.clipboard.writeText(s);return}const n=document.createElement("textarea");n.value=s,n.setAttribute("readonly",""),n.style.position="fixed",n.style.opacity="0",n.style.pointerEvents="none",document.body.appendChild(n),n.select();try{document.execCommand("copy")}finally{n.remove()}}function O(t){const s=t.querySelector("[data-copy-label]"),n=s?.textContent||"Copiar";t.classList.add("is-copied"),s&&(s.textContent="Copiado"),window.clearTimeout(t._copyTimer),t._copyTimer=window.setTimeout(()=>{t.classList.remove("is-copied"),s&&(s.textContent=n)},1400)}document.addEventListener("click",async t=>{const s=t.target.closest("[data-copy-text], [data-copy-value]");if(!s)return;t.preventDefault(),t.stopPropagation();const n=s.dataset.copyText?decodeURIComponent(s.dataset.copyText):s.dataset.copyValue||"";try{await V(n),O(s)}catch{const o=s.querySelector("[data-copy-label]");o&&(o.textContent="Falhou",window.setTimeout(()=>{o.textContent="Copiar"},1400))}});async function I(){const t=document.querySelector("[data-queue-list]"),s=document.querySelector("[data-queue-summary]"),n=document.querySelector("[data-queue-total]");if(!t||!s)return;t.innerHTML=L(4);const e=l=>{const v=String(l?.label||"").toLowerCase();if(/erro|integridade|corrigir|falha|inconsist/.test(v))return"ph-warning-circle";if(/rascunho|prepar|completar/.test(v))return"ph-receipt";if(/autoriza|aprova/.test(v))return"ph-check-circle";if(/saldo|receb|pagamento/.test(v))return"ph-clock-countdown";switch(String(l?.tone||"")){case"danger":case"warning":return"ph-warning-circle";case"success":return"ph-check-circle";case"info":case"cyan":return"ph-folder-open";case"violet":case"purple":return"ph-receipt";default:return"ph-folder"}},o=l=>{switch(String(l?.tone||"")){case"danger":return"Corrigir";case"warning":return"Preparar";case"success":return"Pronto";case"info":case"cyan":return"Acompanhar";case"violet":case"purple":return"Revisar";default:return"Pendente"}};try{const l=await C(_.dataset.queueUrl),v=_.dataset.processesUrl,r=Number(l.summary?.open_processes||0),c=Number(l.summary?.open_amount||0),u=l.summary?.workflow_label||"Fluxo contábil",b=l.queue||[],q=b.reduce((i,p)=>i+Number(p.count||0),0);if(n&&(n.innerHTML=`
                    <i
                        class="ph-fill ph-folder"
                        aria-hidden="true"
                    ></i>

                    ${a(q)}
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
                            ${m.format(c)}
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
                                ${a(r)}
                            </strong>
                        </span>

                        <span>
                            ${a(u)}
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
                                ${a(r)}
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
                                ${a(u)}
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
                                        ${a(r)}
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
                                        ${a(q)}
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
                                        ${a(u)}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `,l.empty||!b.length){t.innerHTML=`
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
                `;return}t.innerHTML=b.map(i=>{const p=i.url||P(v,i.filters),g=String(i.tone||"neutral"),h=e(i),x=o(i);return`
                                <a
                                    class="acc-queue-row acc-tone-${a(g)}"
                                    href="${a(p)}"
                                >
                                    <span class="queue-main">
                                        <span
                                            class="acc-queue-icon"
                                            aria-hidden="true"
                                        >
                                            <i
                                                class="ph-fill ${a(h)}"
                                            ></i>
                                        </span>

                                        <span class="acc-queue-copy">
                                            <strong>
                                                ${a(i.label)}
                                            </strong>

                                            <span>
                                                Abrir processos relacionados
                                            </span>
                                        </span>
                                    </span>

                                    <span class="queue-status">
                                        ${a(x)}
                                    </span>

                                    <span class="acc-queue-count">
                                        ${a(i.count)}
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
                            `}).join("")}catch(l){n&&(n.innerHTML=`
                    <i
                        class="ph-fill ph-warning-circle"
                        aria-hidden="true"
                    ></i>

                    —
                `),j(t,l)}}function W(t){const s=t.critical_issues?w(`${t.critical_issues} erro(s)`,"danger"):t.preparation_issues?w(`${t.preparation_issues} item(ns)`,"warning"):w("Pronto","success");return`
            <tr>
                <td>
                    <a
                        class="acc-link"
                        href="${a(t.url)}"
                    >
                        ${a(t.number)}
                    </a>

                    <div class="acc-muted">
                        ${a(t.issued_at||"")}
                    </div>
                </td>

                <td>
                    <strong>
                        ${a(t.project)}
                    </strong>

                    <div class="acc-muted">
                        ${a(t.project_code||"")}
                    </div>
                </td>

                <td>
                    ${a(t.recipient)}

                    <div class="acc-muted">
                        ${a(t.recipient_type)}
                    </div>
                </td>

                <td>
                    ${w(t.state.label,t.state.tone)}

                    <div class="acc-muted">
                        ${a(t.state.next_action)}
                    </div>
                </td>

                <td class="acc-money">
                    ${m.format(t.net||0)}

                    <div class="acc-muted">
                        Saldo
                        ${m.format(t.remaining||0)}
                    </div>
                </td>

                <td>
                    ${s}
                </td>

                <td class="process-action-column">
                    <a
                        class="process-open"
                        href="${a(t.url)}"
                        aria-label="Acessar ${a(t.number)}"
                    >
                        Acessar

                        <i
                            class="ph-fill ph-arrow-right"
                            aria-hidden="true"
                        ></i>
                    </a>
                </td>
            </tr>
        `}function X(t){const s=t.critical_issues?`${a(t.critical_issues)} erro(s)`:t.preparation_issues?`${a(t.preparation_issues)} item(ns) a completar`:"Pronto";return`
            <article
                class="acc-mobile-row"
                data-tone="${a(t.state.tone||"neutral")}"
            >
                <div class="acc-mobile-head">
                    <div class="mobile-process-main">
                        <a
                            class="acc-link"
                            href="${a(t.url)}"
                        >
                            ${a(t.number)}
                        </a>

                        <span class="acc-muted">
                            ${a(t.project)}
                        </span>
                    </div>

                    ${w(t.state.label,t.state.tone)}
                </div>

                <div class="acc-mobile-meta">
                    <span>
                        Destinatário

                        <strong>
                            ${a(t.recipient)}
                        </strong>
                    </span>

                    <span>
                        Valor líquido

                        <strong>
                            ${m.format(t.net||0)}
                        </strong>
                    </span>

                    <span>
                        Próxima ação

                        <strong>
                            ${a(t.state.next_action)}
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
                        href="${a(t.url)}"
                    >
                        Acessar

                        <i
                            class="ph-fill ph-arrow-right"
                            aria-hidden="true"
                        ></i>
                    </a>
                </div>
            </article>
        `}async function J(){const t=document.querySelector("[data-process-filters]"),s=document.querySelector("[data-process-table]"),n=document.querySelector("[data-process-mobile]"),e=document.querySelector("[data-process-pagination]");if(!t||!s||!n||!e)return;const o=t.elements.project,l=t.elements.organization,v=t.elements.customer;let r,c=!1;const u=new URLSearchParams(window.location.search);["search","project","organization","customer","from","until","financial_status","authorization_status","fiscal_status","accountability_status","pending"].forEach(i=>{u.has(i)&&t.elements[i]&&(t.elements[i].value=u.get(i))});async function b(i=1){r?.abort(),r=new AbortController,s.innerHTML=`
                <tr>
                    <td colspan="7">
                        ${L(5)}
                    </td>
                </tr>
            `,n.innerHTML=L(5),e.innerHTML="";const p=Object.fromEntries(new FormData(t).entries());p.page=i;try{const g=await C(P(_.dataset.processesDataUrl,p),r.signal),h=g.processes;c||(o&&(o.innerHTML='<option value="">Todos os projetos</option>'+(g.filters?.projects||[]).map(f=>`
                                        <option value="${a(f.id)}">
                                            ${a(f.label)}
                                        </option>
                                    `).join(""),o.value=p.project||""),l&&(l.innerHTML='<option value="">Todas as organizações</option>'+(g.filters?.organizations||[]).map(f=>`
                                        <option value="${a(f.id)}">
                                            ${a(f.label)}
                                        </option>
                                    `).join(""),l.value=p.organization||""),v&&(v.innerHTML='<option value="">Todos os clientes</option>'+(g.filters?.customers||[]).map(f=>`
                                        <option value="${a(f.id)}">
                                            ${a(f.label)}
                                        </option>
                                    `).join(""),v.value=p.customer||""),c=!0),h.data.length?(s.innerHTML=h.data.map(W).join(""),n.innerHTML=h.data.map(X).join("")):(s.innerHTML=`
                        <tr>
                            <td colspan="7">
                                <div class="acc-empty">
                                    Nenhum processo encontrado.
                                </div>
                            </td>
                        </tr>
                    `,n.innerHTML=`
                        <div class="acc-empty">
                            Nenhum processo encontrado.
                        </div>
                    `),e.innerHTML=`
                    <span>
                        ${a(h.from||0)}
                        –
                        ${a(h.to||0)}
                        de
                        ${a(h.total||0)}
                    </span>

                    <div class="acc-pagination-actions">
                        <button
                            class="acc-button"
                            type="button"
                            data-page="${h.current_page-1}"
                            ${h.current_page<=1?"disabled":""}
                        >
                            ${d("ph-caret-left")}

                            Anterior
                        </button>

                        <button
                            class="acc-button"
                            type="button"
                            data-page="${h.current_page+1}"
                            ${h.current_page>=h.last_page?"disabled":""}
                        >
                            Próxima

                            ${d("ph-caret-right")}
                        </button>
                    </div>
                `,e.querySelectorAll("[data-page]").forEach(f=>f.addEventListener("click",()=>b(Number(f.dataset.page))));const x=new URL(window.location.href);Object.entries(p).forEach(([f,$])=>$?x.searchParams.set(f,$):x.searchParams.delete(f)),x.searchParams.delete("page"),history.replaceState({},"",x)}catch(g){g.name!=="AbortError"&&(s.innerHTML=`
                        <tr>
                            <td colspan="7">
                                <div class="acc-error">
                                    ${a(g.message)}
                                </div>
                            </td>
                        </tr>
                    `,j(n,g))}}let q;t.addEventListener("input",i=>{clearTimeout(q),q=setTimeout(()=>b(1),i.target.name==="search"?320:0)}),t.addEventListener("change",i=>{i.target.matches('select, input[type="date"]')&&b(1)}),t.addEventListener("submit",i=>{i.preventDefault(),b(1)}),t.querySelector("[data-clear-filters]")?.addEventListener("click",()=>{t.reset(),b(1)}),b(Number(u.get("page")||1))}function M(t){const s=t.data||[];if(!s.length)return`
                <div class="acc-empty">
                    Nenhuma distribuição vinculada.
                </div>
            `;const n=`
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
                            ${s.map(e=>{const o=[`Data: ${e.parent?.date||"Sem origem"}`,`Entrega: ${e.parent?.id?`#${e.parent.id}`:"—"}`,`Produto: ${e.product||""}`,`Destino: ${e.customer||""}`,`Membro: ${e.member||""}`,`Quantidade: ${S.format(e.quantity||0)} ${e.unit||""}`.trim(),`Valor: ${m.format(e.gross_value||0)}`,`Preço unitário: ${m.format(e.unit_price||0)} / ${e.unit||""}`].join(`
`);return`
                                                <tr>
                                                    <td>
                                                        ${a(e.parent?.date||"Sem origem")}

                                                        <div class="acc-muted">
                                                            Entrega #
                                                            ${a(e.parent?.id||"—")}
                                                        </div>
                                                    </td>

                                                    <td>
                                                        <strong>
                                                            ${a(e.product)}
                                                        </strong>

                                                        <div class="acc-muted">
                                                            ${a(e.customer)}
                                                        </div>
                                                    </td>

                                                    <td>
                                                        ${a(e.member)}
                                                    </td>

                                                    <td>
                                                        ${S.format(e.quantity||0)}

                                                        ${a(e.unit)}
                                                    </td>

                                                    <td class="acc-money">
                                                        ${m.format(e.gross_value||0)}

                                                        <div class="acc-muted">
                                                            ${m.format(e.unit_price||0)}

                                                            /

                                                            ${a(e.unit)}
                                                        </div>
                                                    </td>

                                                    <td class="acc-copy-column">
                                                        ${D(o,"Copiar","acc-button-icon-copy")}
                                                    </td>
                                                </tr>
                                            `}).join("")}
                        </tbody>
                    </table>
                </div>
            </div>
        `;return(t.last_page||1)<=1?n:n+`
            <div class="acc-pagination">
                <span>
                    ${a(t.from||0)}
                    –
                    ${a(t.to||0)}
                    de
                    ${a(t.total||0)}
                </span>

                <div class="acc-pagination-actions">
                    <button
                        class="acc-button"
                        type="button"
                        data-dist-page="${t.current_page-1}"
                        ${t.current_page<=1?"disabled":""}
                    >
                        ${d("ph-caret-left")}

                        Anterior
                    </button>

                    <button
                        class="acc-button"
                        type="button"
                        data-dist-page="${t.current_page+1}"
                        ${t.current_page>=t.last_page?"disabled":""}
                    >
                        Próxima

                        ${d("ph-caret-right")}
                    </button>
                </div>
            </div>
        `}function K(t){if(!t?.length)return`
                <div class="acc-empty">
                    Este registro legado ainda não possui
                    linhas consolidadas no snapshot.
                </div>
            `;const s=new Map;return t.forEach(n=>{const e=String(n.unit||"un").trim()||"un",o=e.toLocaleLowerCase("pt-BR"),l=s.get(o)||{unit:e,quantity:0};l.quantity+=Math.round(Number(n.quantity||0)*1e8),s.set(o,l)}),`
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
                            ${t.map(n=>{const e=[`Produto: ${n.product||""}`,n.project?`Projeto: ${n.project}`:"",`Quantidade: ${S.format(Number(n.quantity||0))} ${n.unit||""}`.trim(),`Preço unitário: ${m.format(Number(n.unit_price||0))}`,`Valor documental: ${m.format(Number(n.document_amount||0))}`].filter(Boolean).join(`
`);return`
                                                <tr>
                                                    <td>
                                                        <strong>
                                                            ${a(n.product)}
                                                        </strong>

                                                        <div class="acc-muted">
                                                            ${a(n.project||"")}
                                                        </div>
                                                    </td>

                                                    <td>
                                                        ${S.format(Number(n.quantity||0))}

                                                        ${a(n.unit)}
                                                    </td>

                                                    <td class="acc-money">
                                                        ${m.format(Number(n.unit_price||0))}
                                                    </td>

                                                    <td class="acc-money">
                                                        <strong>
                                                            ${m.format(Number(n.document_amount||0))}
                                                        </strong>
                                                    </td>

                                                    <td class="acc-copy-column">
                                                        ${D(e,"Copiar","acc-button-icon-copy")}
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
                        ${Array.from(s.values()).map(n=>`
                            <div>
                                <dt>${a(n.unit)}</dt>
                                <dd>${S.format(n.quantity/1e8)} ${a(n.unit)}</dd>
                            </div>
                        `).join("")}
                    </dl>
                </section>
            </div>
        `}function Z(t){return t?.length?`
            <ul class="acc-simple-list">
                ${t.map(s=>`
                                <li class="acc-simple-item">
                                    <strong>
                                        Versão
                                        ${a(s.sequence)}
                                        ·
                                        ${a(s.label)}
                                    </strong>

                                    ${s.organization?`
                                                <span>
                                                    Organização:
                                                    ${a(s.organization)}
                                                </span>
                                            `:""}

                                    <span>
                                        Enviada em
                                        ${a(s.sent_at||"—")}
                                        por
                                        ${a(s.sent_by||"Membro não identificado")}
                                    </span>

                                    ${s.responded_at?`
                                                <span>
                                                    Resposta em
                                                    ${a(s.responded_at)}
                                                    por
                                                    ${a(s.responded_by||"Representante autorizado")}
                                                </span>
                                            `:""}

                                    ${s.validity?`
                                                <span>
                                                    Validade atual:
                                                    ${a(s.validity)}
                                                </span>
                                            `:""}

                                    ${s.message?`
                                                <p class="acc-auth-message">
                                                    ${a(s.message)}
                                                </p>
                                            `:""}

                                    ${s.invalidation_reason?`
                                                <p class="acc-auth-message acc-auth-warning">
                                                    ${a(s.invalidation_reason)}
                                                </p>
                                            `:""}
                                </li>
                            `).join("")}
            </ul>
        `:`
                <div class="acc-empty">
                    Processo anterior ao workflow de autorização.
                </div>
            `}function G(t){const s=t.access||{};if(s.applicable===!1)return`
                <div class="acc-guidance">
                    <strong>
                        Destinatário individual
                    </strong>

                    <span>
                        Este processo não exige autorização
                        por representante de uma organização compradora.
                    </span>
                </div>
            `;const n=s.recipients||[];return`
            <section class="acc-auth-access">
                <h3 class="acc-section-title">
                    Quem responde pela organização
                </h3>

                ${n.length?`
                            <ul class="acc-simple-list">
                                ${n.map(e=>`
                                                <li class="acc-simple-item">
                                                    <strong>
                                                        ${a(e.name||"Representante autorizado")}
                                                        ·
                                                        ${a(e.email)}
                                                    </strong>

                                                    <span>
                                                        ${e.has_account?"Conta ativa":"Aguardando primeiro acesso"}

                                                        ${e.last_access_at?` · último acesso ${a(e.last_access_at)}`:""}
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

                ${_.dataset.canSendAuthorization==="1"?`
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
                                        value="${a(s.organization_email||"")}"
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
                                        value="${a(s.organization_contact||"")}"
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

                ${t.state==="sent"?`
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
                                                href="${a(s.buyer_url)}"
                                            >
                                                ${d("ph-arrow-square-out")}

                                                Abrir tela da organização
                                            </a>
                                        `:""}
                            </div>
                        `:""}
            </section>
        `}function R(t){const s=t.data||[],n=`
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
                                                    ${a(e.number)}
                                                    ·
                                                    ${a(e.member)}
                                                </strong>

                                                <div class="acc-muted">
                                                    ${a(e.included_distributions||0)}
                                                    distribuição(ões)
                                                    incluída(s)
                                                    neste faturamento
                                                </div>
                                            </div>

                                            ${w(e.status_label,e.status==="paid"?"success":"warning")}
                                        </div>

                                        <div class="acc-receipt-actions">
                                            ${e.detail_url?`
                                                        <button
                                                            class="acc-button"
                                                            type="button"
                                                            data-related-detail="${a(e.detail_url)}"
                                                            data-related-title="${a(e.number)}"
                                                        >
                                                            ${d("ph-list-bullets")}

                                                            Ver distribuições
                                                        </button>
                                                    `:""}

                                            ${e.reprint_url?`
                                                        <a
                                                            class="acc-button"
                                                            href="${a(e.reprint_url)}"
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
        `;return(t.last_page||1)<=1?n:n+`
            <div class="acc-pagination">
                <span>
                    ${a(t.from||0)}
                    –
                    ${a(t.to||0)}
                    de
                    ${a(t.total||0)}
                </span>

                <div class="acc-pagination-actions">
                    <button
                        class="acc-button"
                        type="button"
                        data-producer-page="${t.current_page-1}"
                        ${t.current_page<=1?"disabled":""}
                    >
                        ${d("ph-caret-left")}

                        Anterior
                    </button>

                    <button
                        class="acc-button"
                        type="button"
                        data-producer-page="${t.current_page+1}"
                        ${t.current_page>=t.last_page?"disabled":""}
                    >
                        Próxima

                        ${d("ph-caret-right")}
                    </button>
                </div>
            </div>
        `}async function Y(t,s){let n=document.querySelector("[data-related-receipt-dialog]");if(!n){n=document.createElement("dialog"),n.className="acc-dialog acc-dialog-wide acc-related-dialog",n.dataset.relatedReceiptDialog="",n.innerHTML=`
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
            `,document.body.appendChild(n);const o=()=>n.close();n.querySelector("[data-related-close]").addEventListener("click",o),n.querySelector("[data-related-footer-close]").addEventListener("click",o),n.addEventListener("click",l=>{l.target===n&&o()})}n.querySelector("[data-related-receipt-title]").textContent=s||"Documento de origem";const e=n.querySelector("[data-related-receipt-content]");e.innerHTML=L(4),n.showModal();try{e.innerHTML=H(await C(t))}catch(o){j(e,o)}}function aa(t){if(!t)return'<div class="acc-empty">Conferência indisponível.</div>';const s=t.summary,n=o=>S.format(Number(o||0)),e=o=>o==null?"Não disponível":m.format(Number(o));return`
            <div class="acc-reconciliation">
                <header class="acc-reconciliation-head">
                    <div><h3>Conferência de valores e quantidades</h3>
                        <p>Somente orientação: estes alertas não criam bloqueios nem alteram documentos. As regras de segurança já existentes permanecem.</p>
                        <small>Conferido em ${a(t.checked_at)} · Dados de toda a folha, sem limite de paginação.</small>
                    </div>
                    <div class="acc-reconciliation-actions">
                        <button type="button" class="acc-button" data-recheck-documents>${d("ph-arrows-clockwise")} Conferir novamente</button>
                        <button type="button" class="acc-button" data-export-reconciliation>${d("ph-download-simple")} Exportar relatório CSV</button>
                    </div>
                </header>
                <div class="acc-reconciliation-cards">
                    <div><span>Distribuições na folha</span><strong>${a(s.distributions)}</strong></div>
                    <div><span>Nos comprovantes dos membros</span><strong>${a(s.covered)} / ${a(s.distributions)}</strong></div>
                    <div><span>Sem comprovante do membro</span><strong>${a(s.uncovered)}</strong></div>
                    <div><span>Comprovantes analisados</span><strong>${a(s.member_documents)}</strong></div>
                </div>
                <section class="acc-reconciliation-section">
                    <h4>${t.warning_count?`${a(t.warning_count)} alerta(s) para revisar`:"Nenhuma divergência detectada nas verificações disponíveis"} · ${a(t.info_count)} aviso(s) de contexto</h4>
                    <ul class="acc-reconciliation-alerts">
                        ${t.alerts.map(o=>`
                            <li class="is-${o.level==="info"?"info":"warning"}">
                                <strong>${d(o.level==="info"?"ph-info":"ph-warning")} ${a(o.message)}</strong>
                                <p><b>Como revisar:</b> ${a(o.solution)}</p>
                                ${o.ids.length?`<details><summary>Ver ${a(o.ids.length)} distribuição(ões)</summary><p>${o.ids.map(l=>`#${a(l)}`).join(", ")}</p></details>`:""}
                            </li>
                        `).join("")||'<li class="is-info">Os conjuntos e totais conferidos correspondem. Consulte os limites da análise abaixo.</li>'}
                    </ul>
                </section>
                <section class="acc-reconciliation-section">
                    <h4>Quantidades por unidade de medida</h4>
                    <p>“Coberta” considera cada distribuição uma única vez, pela seleção salva dos comprovantes ativos, usando sua quantidade atual.</p>
                    <div class="acc-table-wrap"><table class="acc-table"><thead><tr><th>Unidade</th><th>Folha documental</th><th>Registros atuais da folha</th><th>Coberta por comprovantes</th><th>Sem comprovante</th></tr></thead><tbody>
                    ${t.units.map(o=>`<tr><td>${a(o.unit)}</td><td>${t.sheet_quantities_available?n(o.sheet_quantity):"Não disponível"}</td><td>${n(o.current_quantity)}</td><td>${n(o.covered_quantity)}</td><td>${n(o.uncovered_quantity)}</td></tr>`).join("")||'<tr><td colspan="5">Nenhuma quantidade disponível.</td></tr>'}
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
                    ${t.products.map(o=>`<tr><td><strong>${a(o.product)}</strong><div class="acc-muted">Projeto #${a(o.project_id)}</div></td><td>${a(o.unit)}</td><td>${t.sheet_quantities_available?n(o.sheet_quantity):"—"}</td><td>${n(o.current_quantity)}</td><td>${n(o.covered_quantity)}</td><td>${t.sheet_quantities_available?e(o.sheet_gross):"—"}</td><td>${e(o.current_gross)}</td></tr>`).join("")}
                    </tbody></table></div><p>¹ Antes do arredondamento por linha e das taxas.</p>
                </details>
                <details class="acc-reconciliation-section"><summary>Relatório dos comprovantes dos membros · documentos integrais</summary>
                    <p>Inclui itens de outras folhas/períodos. Quantidades reconstruídas dos registros atuais; valores financeiros salvos nos documentos.</p>
                    <div class="acc-table-wrap"><table class="acc-table"><thead><tr><th>Comprovante / membro</th><th>Situação</th><th>Nesta folha</th><th>Fora desta folha</th><th>Quantidades integrais</th><th>Bruto salvo</th><th>Líquido salvo</th></tr></thead><tbody>
                    ${t.members.map(o=>`<tr><td><strong>${a(o.number)}</strong><div>${a(o.member)}</div></td><td>${a(o.status)}</td><td>${a(o.in_billing)}</td><td>${a(o.outside_billing)}</td><td>${o.quantities.map(l=>`${n(l.quantity)} ${a(l.unit)}`).join(" · ")}</td><td>${e(o.gross)}</td><td>${e(o.net)}</td></tr>`).join("")||'<tr><td colspan="7">Nenhum comprovante ativo relacionado.</td></tr>'}
                    </tbody><tfoot><tr><th colspan="4">Soma integral dos comprovantes</th><td>${s.all_members_quantities.map(o=>`${n(o.quantity)} ${a(o.unit)}`).join(" · ")||"—"}</td><td>${e(s.all_members_gross)}</td><td>${e(s.all_members_net)}</td></tr></tfoot></table></div>
                </details>
                <details class="acc-reconciliation-section" open><summary>Critérios e limites desta conferência</summary><ul>${t.limitations.map(o=>`<li>${a(o)}</li>`).join("")}</ul></details>
            </div>`}function ta(t,s){const n={distributions:"Distribuições na folha",covered:"Nos comprovantes dos membros",uncovered:"Sem comprovante do membro",member_documents:"Comprovantes analisados",sheet_gross:"Bruto salvo na folha",sheet_net:"Líquido salvo na folha",current_gross:"Bruto atual dos itens da folha",covered_gross:"Bruto coberto por comprovantes",all_members_gross:"Bruto salvo dos comprovantes integrais",all_members_net:"Líquido salvo dos comprovantes integrais"},o=[["Conferência não bloqueante",s,t.checked_at],["Resumo","Valor"],...Object.entries(n).map(([r,c])=>[c,t.summary[r]??"Não disponível"]),[],["Unidade","Quantidade documental","Quantidade atual","Quantidade coberta","Quantidade sem comprovante"],...t.units.map(r=>[r.unit,t.sheet_quantities_available?r.sheet_quantity:"Não disponível",r.current_quantity,r.covered_quantity,r.uncovered_quantity]),[],["Projeto","Produto","Unidade","Quantidade documental","Quantidade atual","Quantidade coberta","Bruto documental","Bruto atual"],...t.products.map(r=>[r.project_id,r.product,r.unit,t.sheet_quantities_available?r.sheet_quantity:"",r.current_quantity,r.covered_quantity,t.sheet_quantities_available?r.sheet_gross:"",r.current_gross]),[],["Comprovante","Membro","Situação","Nesta folha","Fora da folha","Quantidades integrais (atuais)","Bruto salvo","Líquido salvo"],...t.members.map(r=>[r.number,r.member,r.status,r.in_billing,r.outside_billing,r.quantities.map(c=>`${c.quantity} ${c.unit}`).join(" / "),r.gross,r.net]),[],["Tipo","Alerta","Como revisar","Distribuições"],...t.alerts.map(r=>[r.level,r.message,r.solution,r.ids.join(", ")]),[],["Limites da análise"],...t.limitations.map(r=>[r])].map(r=>r.map(c=>{let u=String(c??"");return/^-?\d+\.\d+$/.test(u)?u=u.replace(".",","):/^[\s]*[=+\-@]/.test(u)&&(u="'"+u),`"${u.replaceAll('"','""')}"`}).join(";")).join(`\r
`),l=URL.createObjectURL(new Blob(["\uFEFF",o],{type:"text/csv;charset=utf-8"})),v=document.createElement("a");v.href=l,v.download=`conferencia-${String(s).replace(/[^a-zA-Z0-9-]/g,"-")}.csv`,v.click(),setTimeout(()=>URL.revokeObjectURL(l),1e3)}async function N(t="overview"){const s=document.querySelector("[data-dossier]");if(s){s.innerHTML=L(7);try{const n=await C(_.dataset.processDataUrl),e=n.process,o=e.integrity;s.innerHTML=`
                <section class="acc-panel">
                    <div class="acc-state-strip">
                        <div class="acc-state-item">
                            <span>
                                Processo
                            </span>

                            <strong>
                                ${a(e.state.label)}
                            </strong>
                        </div>

                        <div class="acc-state-item">
                            <span>
                                Autorização
                            </span>

                            <strong>
                                ${a(e.workflow.authorization.label)}
                            </strong>
                        </div>

                        <div class="acc-state-item">
                            <span>
                                Fiscal
                            </span>

                            <strong>
                                ${a(e.workflow.fiscal.label)}
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
                            data-tab="reconciliation"
                            data-tone="warning"
                            role="tab"
                            aria-selected="false"
                        >
                            ${d("ph-scales")}
                            <span>Conferência ${n.reconciliation?`(${a(n.reconciliation.warning_count+n.reconciliation.info_count)})`:""}</span>
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
                                    ${a(e.project?.title||"Não identificado")}
                                </strong>
                            </div>

                            <div class="acc-detail">
                                <span>
                                    Destinatário
                                </span>

                                <strong>
                                    ${a(e.recipient.name)}
                                </strong>
                            </div>

                            <div class="acc-detail">
                                <span>
                                    Período
                                </span>

                                <strong>
                                    ${a(e.period)}
                                </strong>
                            </div>

                            <div class="acc-detail">
                                <span>
                                    Valor bruto
                                </span>

                                <strong>
                                    ${m.format(e.financial.gross||0)}
                                </strong>
                            </div>

                            <div class="acc-detail">
                                <span>
                                    Taxas
                                </span>

                                <strong>
                                    ${m.format(e.financial.fees||0)}
                                </strong>
                            </div>

                            <div class="acc-detail">
                                <span>
                                    Valor líquido
                                </span>

                                <strong>
                                    ${m.format(e.financial.net||0)}
                                </strong>
                            </div>

                            <div class="acc-detail">
                                <span>
                                    Distribuições
                                </span>

                                <strong>
                                    ${a(e.summary.distributions)}
                                </strong>
                            </div>

                            <div class="acc-detail">
                                <span>
                                    Produtores
                                </span>

                                <strong>
                                    ${a(e.summary.producers)}
                                </strong>
                            </div>

                            <div class="acc-detail">
                                <span>
                                    Produtos
                                </span>

                                <strong>
                                    ${a(e.summary.products)}
                                </strong>
                            </div>

                            <div class="acc-detail">
                                <span>
                                    Unidades recebedoras
                                </span>

                                <strong>
                                    ${a(e.summary.recipient_units)}
                                </strong>
                            </div>

                            <div class="acc-detail">
                                <span>
                                    Documentos de origem
                                </span>

                                <strong>
                                    ${a(e.summary.source_receipts)}
                                </strong>
                            </div>
                        </div>
                    </div>

                    <div
                        class="acc-tab-panel"
                        data-panel="delivered"
                    >
                        ${K(n.consolidated_lines)}
                    </div>

                    <div
                        class="acc-tab-panel"
                        data-panel="distributions"
                    >
                        <div data-distributions-panel>
                            ${M(n.distributions)}
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
                                    ${m.format(e.financial.received||0)}
                                </strong>
                            </div>

                            <div class="acc-detail">
                                <span>
                                    Saldo restante
                                </span>

                                <strong>
                                    ${m.format(e.financial.remaining||0)}
                                </strong>
                            </div>

                            <div class="acc-detail">
                                <span>
                                    Situação financeira
                                </span>

                                <strong>
                                    ${a(e.financial.status_label)}
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
                            ${n.payments.length?n.payments.map(i=>`
                                                <li class="acc-simple-item">
                                                    <strong>
                                                        ${m.format(i.amount||0)}
                                                        ·
                                                        ${a(i.date)}
                                                    </strong>

                                                    <span>
                                                        ${a(i.account||i.method||"Sem conta informada")}
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
                                        ${D([`Projeto: ${e.project?.title||"Não identificado"}`,`Destinatário: ${e.recipient.name||""}`,`Período: ${e.period||""}`,`Valor bruto: ${m.format(e.financial.gross||0)}`,`Taxas: ${m.format(e.financial.fees||0)}`,`Valor líquido: ${m.format(e.financial.net||0)}`,`Situação fiscal: ${e.workflow.fiscal.label||""}`,`Documento esperado: ${e.workflow.fiscal.document_type||"Não configurado"}`].join(`
`),"Copiar dados")}

                                        ${e.pdf_url||e.workflow.fiscal.billing_sheet_url?`
                                                    <a
                                                        class="acc-button acc-button-primary"
                                                        data-sgc-pdf
                                                        title="Faturamento ${a(e.number)}"
                                                        href="${a(e.pdf_url||e.workflow.fiscal.billing_sheet_url)}"
                                                    >
                                                        ${d("ph-printer")}

                                                        ${e.financial.status==="draft"?"Imprimir prévia do faturamento":"Imprimir faturamento completo"}
                                                    </a>
                                                `:e.workflow.fiscal.settings_url?`
                                                            <a
                                                                class="acc-button acc-button-primary"
                                                                href="${a(e.workflow.fiscal.settings_url)}"
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
                                                    ${a(e.workflow.fiscal.label)}
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
                                                    ${a(e.workflow.fiscal.document_type||"Não configurado")}
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
                                                    ${e.workflow.fiscal.expected_amount==null?"Não determinado":m.format(e.workflow.fiscal.expected_amount)}
                                                </strong>
                                            </div>
                                        </div>
                                    </div>

                                    ${e.workflow.fiscal.blocks?.length?`
                                                <div class="acc-document-alerts">
                                                    ${e.workflow.fiscal.blocks.map(i=>`
                                                                    <div class="acc-document-alert">
                                                                        <span>
                                                                            ${d("ph-warning-circle")}
                                                                        </span>

                                                                        <div>
                                                                            <strong>
                                                                                Revisar antes de emitir
                                                                            </strong>

                                                                            <p>
                                                                                ${a(i.message)}
                                                                            </p>
                                                                        </div>
                                                                    </div>
                                                                `).join("")}
                                                </div>
                                            `:""}
                                </div>
                            </section>

                            ${e.workflow.authorization.access?.applicable===!1&&!n.authorizations.length?"":`
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

                                                ${_.dataset.canSendAuthorization==="1"&&["legacy_unsubmitted","cancelled"].includes(e.workflow.authorization.state)?`
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
                                                ${G(e.workflow.authorization)}

                                                <div
                                                    class="acc-action-feedback"
                                                    data-authorization-feedback
                                                    aria-live="polite"
                                                ></div>

                                                ${Z(n.authorizations)}
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
                                        ${a(e.summary.source_receipts||0)}
                                    </span>
                                </header>

                                <div
                                    class="acc-subsection-body acc-subsection-body-flush"
                                >
                                    ${R(n.producer_receipts)}
                                </div>
                            </section>
                        </div>
                    </div>

                    <div
                        class="acc-tab-panel"
                        data-panel="reconciliation"
                    >
                        ${aa(n.reconciliation)}
                    </div>
                    <div class="acc-tab-panel"
                        data-panel="timeline"
                    >
                        <ul class="acc-simple-list">
                            ${n.timeline.length?n.timeline.map(i=>`
                                                <li class="acc-simple-item">
                                                    <strong>
                                                        ${a(i.description)}
                                                    </strong>

                                                    <span>
                                                        ${a(i.date)}
                                                        ·
                                                        ${a(i.actor)}
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
                                        ${a(e.state.next_action)}
                                    </p>
                                </div>
                            </div>

                            ${w(e.state.label,e.state.tone)}
                        </div>

                        <div
                            class="acc-action-box"
                            data-authorization-action
                        >
                            ${e.edit_url?`
                                        <a
                                            class="acc-button acc-button-primary"
                                            href="${a(e.edit_url)}"
                                        >
                                            ${d("ph-pencil-line")}

                                            Continuar e conferir faturamento
                                        </a>

                                        <span class="acc-muted">
                                            Revise as entregas e os valores
                                            antes de fechar.
                                        </span>
                                    `:""}

                            ${e.reopen_url&&o.snapshot_changed?`
                                    <button
                                        class="acc-button acc-button-primary"
                                        type="button"
                                        data-reopen-billing
                                        data-url="${a(e.reopen_url)}"
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
                                        title="Faturamento ${a(e.number)}"
                                        href="${a(e.pdf_url||e.workflow.fiscal.billing_sheet_url)}"
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
                                        ${o.critical_count?`${a(o.critical_count)} erro(s) de integridade`:o.preparation_count?`${a(o.preparation_count)} item(ns) a completar`:"Dados prontos para avançar"}
                                    </p>
                                </div>
                            </div>

                            ${w(o.critical_count?"Corrigir erro":o.preparation_count?"Em preparação":"Pronto",o.critical_count?"danger":o.preparation_count?"warning":"success")}
                        </div>

                        <div class="acc-side-panel-body">
                            ${o.snapshot_changed?`
                                    <div class="acc-alert is-error acc-snapshot-warning">
                                        <strong>Comprovante desatualizado</strong>
                                        <span>
                                            ${a(o.snapshot_changes?.added_ids?.length||0)} adicionada(s) e
                                            ${a(o.snapshot_changes?.removed_ids?.length||0)} removida(s).
                                            É necessário revisar o faturamento e gerar uma nova versão.
                                        </span>
                                    </div>
                                `:""}
                            <ul class="acc-integrity-list">
                                ${o.issues.length?o.issues.map(i=>`
                                                    <li class="acc-integrity-item">
                                                        ${w(i.severity==="critical"?"Erro":"Preparação",i.severity==="critical"?"danger":"warning")}

                                                        ${a(i.message)}
                                                    </li>
                                                `).join(""):`
                                            <li class="acc-simple-item">
                                                Nenhuma pendência encontrada.
                                            </li>
                                        `}
                            </ul>
                            ${o.repair_url?`
                                        <button class="acc-button" type="button" data-repair-integrity data-url="${a(o.repair_url)}">
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
                                        ${a(n.documents.length)}
                                        arquivo(s)
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="acc-side-panel-body">
                            <ul class="acc-simple-list acc-document-list">
                                ${n.documents.length?n.documents.map(i=>`
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
                                                                ${a(i.name)}
                                                            </strong>

                                                            <span>
                                                                ${a(i.category)}
                                                                ·
                                                                ${a(i.date)}
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
            `,s.querySelectorAll("[data-tab]").forEach(i=>i.addEventListener("click",()=>{s.querySelectorAll("[data-tab]").forEach(p=>{const g=p===i;p.classList.toggle("is-active",g),p.setAttribute("aria-selected",g?"true":"false"),g?p.setAttribute("aria-current","page"):p.removeAttribute("aria-current")}),s.querySelectorAll("[data-panel]").forEach(p=>p.classList.toggle("is-active",p.dataset.panel===i.dataset.tab)),window.innerWidth<760&&i.scrollIntoView({behavior:"smooth",inline:"center",block:"nearest"})})),t!=="overview"&&s.querySelector(`[data-tab="${t}"]`)?.click(),s.querySelector("[data-export-reconciliation]")?.addEventListener("click",()=>ta(n.reconciliation,e.number)),s.querySelector("[data-recheck-documents]")?.addEventListener("click",()=>N("reconciliation"));const l=s.querySelector("[data-access-form]");l?.addEventListener("submit",async i=>{i.preventDefault();const p=l.querySelector('button[type="submit"]'),g=l.querySelector("[data-access-feedback]");p.disabled=!0,g.textContent="Salvando...";try{const h=await z(_.dataset.authorizationAccessUrl,Object.fromEntries(new FormData(l).entries()));g.textContent=h.message,await N()}catch(h){g.textContent=h.message,g.classList.add("is-error"),p.disabled=!1}});const v=s.querySelector("[data-send-authorization]"),r=s.querySelector("[data-repair-integrity]"),c=s.querySelector("[data-reopen-billing]");c?.addEventListener("click",async()=>{if(window.confirm("Reabrir este faturamento com as distribuições atualmente vinculadas? A versão anterior continuará preservada no histórico.")){c.disabled=!0;try{const i=await z(c.dataset.url,{});if(i.redirect_url){window.location.assign(i.redirect_url);return}await N()}catch(i){window.alert(i.message),c.disabled=!1}}}),r?.addEventListener("click",async()=>{const i=s.querySelector("[data-integrity-feedback]");r.disabled=!0,i.textContent="Verificando e corrigindo vínculos...";try{const p=await z(r.dataset.url,{});i.textContent=p.message,await N()}catch(p){i.textContent=p.message,i.classList.add("is-error"),r.disabled=!1}}),v?.addEventListener("click",async()=>{if(!window.confirm(`Enviar esta cobrança para autorização?

Valor: ${m.format(e.financial.net||0)}
Período: ${e.period}`))return;const i=s.querySelector("[data-authorization-feedback]");v.disabled=!0,i.textContent="Enviando...";try{await z(_.dataset.authorizationSendUrl,{operation_key:crypto.randomUUID()}),i.textContent="Cobrança enviada.",await N()}catch(p){i.textContent=p.message,i.classList.add("is-error"),v.disabled=!1}});const u=()=>s.querySelectorAll("[data-dist-page]").forEach(i=>i.addEventListener("click",async()=>{const p=s.querySelector("[data-distributions-panel]");if(p){p.innerHTML=L(3);try{const g=await C(P(_.dataset.processDataUrl,{distributions_page:i.dataset.distPage}));p.innerHTML=M(g.distributions),u()}catch(g){j(p,g)}}}));u();const b=()=>s.querySelectorAll("[data-producer-page]").forEach(i=>i.addEventListener("click",async()=>{const g=s.querySelector('[data-panel="documents"]')?.querySelector(".acc-subsection-source .acc-subsection-body");if(g){g.innerHTML=L(3);try{const h=await C(P(_.dataset.processDataUrl,{producer_receipts_page:i.dataset.producerPage}));g.innerHTML=R(h.producer_receipts),b(),q()}catch(h){j(g,h)}}})),q=()=>s.querySelectorAll("[data-related-detail]").forEach(i=>i.addEventListener("click",()=>Y(i.dataset.relatedDetail,i.dataset.relatedTitle)));b(),q()}catch(n){j(s,n)}}}function H(t){const s=t.receipt,n=t.distributions||[],e=new Map((t.processes||[]).map(c=>[Number(c.id),c])),o=Number(_.dataset.receiptId||0),l=new Map;n.forEach(c=>l.set(c.unit||"un",(l.get(c.unit||"un")||0)+Math.round(Number(c.quantity||0)*1e4)));const v=c=>n.reduce((u,b)=>u+Math.round(Number(b[c]||0)*1e4),0)/1e4,r=[...l].map(([c,u])=>`${S.format(u/1e4)} ${a(c)}`).join(" · ")||"—";return`
            <section class="acc-source-detail" data-source-detail>
                <div class="acc-detail-grid acc-receipt-summary">
                    <div class="acc-detail"><span>Produtor</span><strong>${a(s.member)}</strong></div>
                    <div class="acc-detail"><span>Comprovante</span><strong>${a(s.number)}</strong><small>${a(s.status_label)}</small></div>
                    <div class="acc-detail"><span>Emissão / projeto</span><strong>${a(s.issued_at||"—")}</strong><small>${a(s.project)}</small></div>
                </div>
                <div class="acc-source-totals">
                    <div><span>Distribuições</span><strong>${n.length}</strong></div>
                    <div><span>Quantidade entregue</span><strong>${r}</strong></div>
                    <div><span>Bruto das distribuições</span><strong>${m.format(v("gross"))}</strong></div>
                    <div><span>Taxas das distribuições</span><strong>${m.format(v("fees"))}</strong></div>
                    <div><span>Líquido das distribuições</span><strong>${m.format(v("net"))}</strong></div>
                    <div><span>Valor do comprovante</span><strong>${m.format(s.total_net||0)}</strong></div>
                </div>
                <div class="acc-source-tools">
                    <label class="acc-field"><span>Buscar distribuição</span><input class="acc-input" type="search" data-source-search placeholder="Produto, cliente, data, ID ou observação"></label>
                    <label class="acc-field"><span>Faturamento</span><select class="acc-input" data-source-coverage>
                        <option value="all">Todas as distribuições</option>
                        ${o?'<option value="current">Neste faturamento</option>':""}
                        <option value="draft">Em faturamento rascunho</option>
                        <option value="without">Sem faturamento identificado</option>
                    </select></label>
                </div>
                <div class="acc-source-list">
                    ${n.length?n.map(c=>{const u=c.billing_references||(e.has(Number(c.billing_receipt_id))?[{...e.get(Number(c.billing_receipt_id)),source:"linked"}]:[]),b=u.some(i=>Number(i.id)===o&&i.source!=="snapshot"),q=[c.id,c.product,c.customer,c.date,c.notes,...u.map(i=>i.number)].join(" ").toLocaleLowerCase("pt-BR");return`<article class="acc-source-distribution" data-source-distribution
                            data-search="${a(q)}" data-current="${b?"1":"0"}"
                            data-draft="${u.some(i=>i.source==="draft")?"1":"0"}"
                            data-without="${!u.length&&!c.billing_restricted&&!c.billing_receipt_id?"1":"0"}"
                            data-gross="${Number(c.gross||0)}">
                            <header><div><small>Distribuição #${a(c.id)} · ${a(c.date||"Sem data")}</small><h4>${a(c.product)}</h4><p>${a(c.customer)}</p></div>
                                <div class="acc-source-badges">${w(c.status||"Não informada","neutral")}
                                ${b?w("Neste faturamento","info"):""}
                                ${c.paid?w("Paga ao membro","success"):""}</div></header>
                            <div class="acc-source-values">
                                <div><span>Quantidade</span><strong>${S.format(c.quantity||0)} ${a(c.unit)}</strong></div>
                                <div><span>Preço unitário</span><strong>${m.format(c.unit_price||0)}</strong></div>
                                <div><span>Valor bruto</span><strong>${m.format(c.gross||0)}</strong></div>
                                <div><span>Taxas</span><strong>${m.format(c.fees||0)}</strong></div>
                                <div class="is-net"><span>Valor líquido</span><strong>${m.format(c.net||0)}</strong></div>
                            </div>
                            <div class="acc-source-links">
                                <div><span>Comprovante do membro</span><strong>${a(s.number)}</strong><small>${a(s.status_label)}</small></div>
                                <div><span>Faturamento do cliente</span>
                                    ${u.map(i=>`<div class="acc-source-billing" data-source="${a(i.source)}">
                                        <a class="acc-link" href="${a(i.url)}">Folha ${a(i.number)}</a>
                                        <small>${i.source==="snapshot"?"Referência histórica · sem vínculo atual":i.source==="draft"?"Rascunho · seleção salva":a(i.status)}</small>
                                    </div>`).join("")||"<strong>"+(c.billing_restricted||c.billing_receipt_id?"Faturamento com acesso restrito":"Sem faturamento identificado")+"</strong>"}
                                </div>
                            </div>
                            ${c.parent?`<div class="acc-source-parent">Entrega de origem #${a(c.parent.id)} · ${a(c.parent.date||"Sem data")} · Quantidade recebida: ${S.format(c.parent.quantity||0)} ${a(c.unit)}</div>`:""}
                            ${c.notes?`<details class="acc-source-notes"><summary>Observações da entrega</summary><p>${a(c.notes)}</p></details>`:""}
                        </article>`}).join(""):'<div class="acc-empty">Este comprovante não possui distribuições vinculadas.</div>'}
                </div>
                <p class="acc-muted" data-source-visible-summary>${n.length} distribuição(ões) · Bruto ${m.format(v("gross"))}</p>
            </section>
        `}function B(t){const s=t.closest("[data-source-detail]");if(!s)return;const n=s.querySelector("[data-source-search]").value.trim().toLocaleLowerCase("pt-BR"),e=s.querySelector("[data-source-coverage]").value,o=[...s.querySelectorAll("[data-source-distribution]")],l=o.filter(r=>(r.hidden=!r.dataset.search.includes(n)||e!=="all"&&r.dataset[e]!=="1",!r.hidden)),v=l.reduce((r,c)=>r+Math.round(Number(c.dataset.gross||0)*1e4),0)/1e4;s.querySelector("[data-source-visible-summary]").textContent=`${l.length} de ${o.length} distribuição(ões) · Bruto ${m.format(v)}`}document.addEventListener("input",t=>{t.target.matches("[data-source-search]")&&B(t.target)}),document.addEventListener("change",t=>{t.target.matches("[data-source-coverage]")&&B(t.target)});function ea(t){return`
            <article class="acc-receipt-card">
                <div class="acc-receipt-card-main">
                    <div>
                        <button
                            class="acc-link acc-link-button"
                            type="button"
                            data-source-receipt="${a(t.id)}"
                            data-detail-url="${a(t.detail_url)}"
                        >
                            ${a(t.number)}
                        </button>

                        <div class="acc-muted">
                            ${a(t.member)}
                            ·
                            ${a(t.project)}
                        </div>
                    </div>

                    ${w(t.status_label,t.status==="paid"?"success":t.status==="obsolete"?"danger":"warning")}
                </div>

                <div class="acc-receipt-meta">
                    <span>
                        Emissão

                        <strong>
                            ${a(t.issued_at||"—")}
                        </strong>
                    </span>

                    <span>
                        Distribuições

                        <strong>
                            ${a(t.distribution_count)}
                        </strong>
                    </span>

                    <span>
                        Valor do comprovante

                        <strong>
                            ${m.format(t.total_net||0)}
                        </strong>
                    </span>

                    <span>
                        Referência QR

                        <strong>
                            ${a(t.reference_code||"Não gerada")}
                        </strong>
                    </span>
                </div>

                <div class="acc-receipt-actions">
                    <button
                        class="acc-button"
                        type="button"
                        data-source-receipt="${a(t.id)}"
                        data-detail-url="${a(t.detail_url)}"
                    >
                        ${d("ph-tree-structure")}

                        Ver entregas
                    </button>

                    ${t.reprint_url?`
                                <a
                                    class="acc-button"
                                    href="${a(t.reprint_url)}"
                                    target="_blank"
                                    rel="noopener"
                                >
                                    ${d("ph-file-text")}

                                    Abrir comprovante
                                </a>
                            `:""}
                </div>
            </article>
        `}async function sa(){const t=document.querySelector("[data-source-receipt-filters]");if(!t)return;const s=document.querySelector("[data-source-receipt-results]"),n=document.querySelector("[data-source-receipt-pagination]"),e=t.elements.project,o=document.querySelector("[data-qr-dialog]"),l=document.querySelector("[data-receipt-dialog]"),v=document.querySelector("[data-receipt-detail]"),r=document.querySelector("[data-receipt-title]"),c=document.querySelector("[data-scanner-video]"),u=document.querySelector("[data-scanner-error]");let b=null,q=!1,i=null;const p=()=>{b?.stop?.(),b=null,c&&(c.srcObject=null)},g=$=>{t.elements.search.value=String($||"").trim(),p(),o?.close(),f(1)};async function h($,y="Comprovante"){if(!(!l||!v)){r&&(r.textContent=y),v.innerHTML=L(5),l.showModal();try{v.innerHTML=H(await C($))}catch(E){j(v,E)}}}const x=()=>s?.querySelectorAll("[data-source-receipt]").forEach($=>$.addEventListener("click",()=>h($.dataset.detailUrl,$.textContent.trim())));async function f($=1){i?.abort(),i=new AbortController,s.innerHTML=L(5),n.innerHTML="";const y=Object.fromEntries(new FormData(t).entries());y.page=$;try{const E=await C(P(_.dataset.sourceReceiptsUrl,y),i.signal);!q&&e&&(e.innerHTML='<option value="">Todos os projetos</option>'+(E.filters?.projects||[]).map(T=>`
                                    <option value="${a(T.id)}">
                                        ${a(T.label)}
                                    </option>
                                `).join(""),e.value=y.project||"",q=!0);const k=E.receipts;s.innerHTML=k.data.length?k.data.map(ea).join(""):`
                            <div class="acc-empty">
                                Nenhum comprovante encontrado.
                                Confira o número ou leia novamente o QR Code.
                            </div>
                        `,n.innerHTML=`
                    <span>
                        ${a(k.from||0)}
                        –
                        ${a(k.to||0)}
                        de
                        ${a(k.total||0)}
                    </span>

                    <div class="acc-pagination-actions">
                        <button
                            class="acc-button"
                            type="button"
                            data-page="${k.current_page-1}"
                            ${k.current_page<=1?"disabled":""}
                        >
                            ${d("ph-caret-left")}

                            Anterior
                        </button>

                        <button
                            class="acc-button"
                            type="button"
                            data-page="${k.current_page+1}"
                            ${k.current_page>=k.last_page?"disabled":""}
                        >
                            Próxima

                            ${d("ph-caret-right")}
                        </button>
                    </div>
                `,n.querySelectorAll("[data-page]").forEach(T=>T.addEventListener("click",()=>f(Number(T.dataset.page)))),x(),y.search&&(/\bCP-/i.test(y.search)||/[0-9a-f]{8}-[0-9a-f-]{27,}/i.test(y.search))&&k.data.length===1&&await h(k.data[0].detail_url,k.data[0].number)}catch(E){E.name!=="AbortError"&&j(s,E)}}t.addEventListener("submit",$=>{$.preventDefault(),f(1)}),t.elements.status?.addEventListener("change",()=>f(1)),t.elements.project?.addEventListener("change",()=>f(1)),document.querySelector("[data-close-receipt]")?.addEventListener("click",()=>l?.close()),document.querySelector("[data-close-scanner]")?.addEventListener("click",()=>{p(),o?.close()}),o?.addEventListener("close",p),document.querySelector("[data-use-manual]")?.addEventListener("click",()=>g(document.querySelector("[data-scanner-manual]")?.value)),document.querySelector("[data-open-scanner]")?.addEventListener("click",async()=>{if(!o)return;u&&(u.hidden=!0);const $=window.SgcQrScanner;if($?.nativePlugin()){try{const y=await $.scanNative({batch:!1});y?.code&&g(y.code)}catch(y){y?.code!=="SCAN_CANCELLED"&&u&&(o.showModal(),u.textContent=y?.message||"Não foi possível abrir o leitor nativo.",u.hidden=!1)}return}if(o.showModal(),!$?.supportsWeb()){u&&(u.textContent="A leitura automática não está disponível neste navegador. Cole o código ou link no campo abaixo.",u.hidden=!1);return}try{b=await $.openWeb({video:c,single:!0,interval:180,onCode:g})}catch{u&&(u.textContent="Não foi possível acessar a câmera. Autorize o uso ou cole o código manualmente.",u.hidden=!1),p()}}),f(1)}A==="queue"&&I(),A==="processes"&&J(),A==="dossier"&&N(),A==="source-receipts"&&sa()})();
