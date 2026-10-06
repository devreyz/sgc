(()=>{const d=document.querySelector("[data-billing-editor]");if(!d)return;const c=d.querySelector("[data-billing-form]"),V=document.querySelector('meta[name="csrf-token"]')?.content||"",l=new Set,g=new Map,f=new Set;let L=[],m=1,j="period",u=null,C=0,w=null,S=!1;const r=t=>d.querySelector(t),v=t=>[...d.querySelectorAll(t)],M=r("[data-project-select]"),N=r("[data-project-dialog]"),k=r("[data-freeze-dialog]"),o=t=>String(t??"").replace(/[&<>'"]/g,e=>({"&":"&amp;","<":"&lt;",">":"&gt;","'":"&#39;",'"':"&quot;"})[e]),y=t=>Number(t||0).toLocaleString("pt-BR",{style:"currency",currency:"BRL"}),p=(t,e=!1)=>{const a=r("[data-editor-message]");a.hidden=!t,a.textContent=t||"",a.classList.toggle("is-error",e),t&&a.scrollIntoView({behavior:"smooth",block:"nearest"})},h=async(t,e={})=>{const a=await fetch(t,{credentials:"same-origin",headers:{Accept:"application/json","Content-Type":"application/json","X-CSRF-TOKEN":V,...e.headers||{}},...e}),s=await a.json().catch(()=>({}));if(!a.ok){const n=Object.values(s.errors||{}).flat().join(" ");throw new Error(n||s.message||"Não foi possível concluir esta operação.")}return s},$=()=>[...M.selectedOptions].map(t=>Number(t.value)).filter(Boolean),R=()=>{const t=c.querySelector('[name="recipient_type"]:checked')?.value;return{project_ids:$(),customer_id:t==="customer"&&Number(c.customer_id.value)||null,organization_id:t==="organization"&&Number(c.organization_id.value)||null,issued_at:c.issued_at.value,from_date:c.from_date.value||null,to_date:c.to_date.value||null,notes:c.notes.value||null,distribution_ids:[...l]}},z=()=>{const t=R();if(!t.project_ids.length)throw new Error("Selecione ao menos um projeto.");if(!t.customer_id&&!t.organization_id)throw new Error("Selecione quem será cobrado.");if(!t.issued_at)throw new Error("Informe a data de emissão.");if(t.from_date&&t.to_date&&t.to_date<t.from_date)throw new Error("A data final não pode ser anterior à inicial.");return t},E=t=>{m=Math.max(1,Math.min(4,Number(t)||1)),v("[data-step]").forEach(e=>{e.hidden=Number(e.dataset.step)!==m}),v("[data-step-button]").forEach(e=>{const a=Number(e.dataset.stepButton);e.classList.toggle("is-active",a===m),e.classList.toggle("is-done",a<m),e.setAttribute("aria-current",a===m?"step":"false")}),r("[data-previous]").hidden=m===1,r("[data-next]").hidden=m===4,r("[data-save]").hidden=m<3,r("[data-freeze]")&&(r("[data-freeze]").hidden=m!==4),d.scrollIntoView({behavior:"smooth",block:"start"})},b=()=>{const t=l.size===1?"1 selecionada":`${l.size} selecionadas`;v("[data-selected-count]").forEach(e=>{e.textContent=t})},F=()=>{const t=r("[data-project-summary]"),e=new Set($()),a=L.filter(s=>e.has(Number(s.id)));if(t.classList.toggle("is-scrollable",a.length>4),!a.length){t.innerHTML='<div class="billing-project-empty">Nenhum projeto selecionado. Escolha o projeto antes de definir o destinatário.</div>';return}t.innerHTML=a.map(s=>`
            <div class="billing-project-selected">
                <div>
                    <strong>${o(s.name)}</strong>
                    <small>${s.code?o(s.code):"Projeto selecionado"}</small>
                </div>
                <button
                    class="billing-btn"
                    type="button"
                    data-remove-project="${Number(s.id)}"
                    aria-label="Remover ${o(s.name)}"
                    title="Remover projeto"
                >
                    <i class="ph ph-x" aria-hidden="true"></i>
                </button>
            </div>
        `).join("")},T=()=>{const t=r("[data-project-options]"),e=(r("[data-project-search]")?.value||"").trim().toLocaleLowerCase("pt-BR"),a=L.filter(i=>e?`${i.name||""} ${i.code||""}`.toLocaleLowerCase("pt-BR").includes(e):!0);t.innerHTML=a.length?a.map(i=>{const q=Number(i.id),G=f.has(q);return`
                    <button
                        class="billing-project-row${G?" is-selected":""}"
                        type="button"
                        data-project-option="${q}"
                        aria-pressed="${G?"true":"false"}"
                    >
                        <span class="billing-project-check" aria-hidden="true"><i class="ph-fill ph-check"></i></span>
                        <span>
                            <strong>${o(i.name)}</strong>
                            <small>${i.code?"Código do projeto":"Disponível para faturamento"}</small>
                        </span>
                        ${i.code?`<span class="billing-project-code">${o(i.code)}</span>`:""}
                    </button>
                `}).join(""):'<div class="billing-project-empty">Nenhum projeto encontrado para esta busca.</div>';const s=r("[data-project-dialog-count]");s&&(s.textContent=f.size===1?"1 selecionado":`${f.size} selecionados`);const n=r("[data-apply-project-picker]");n&&(n.disabled=f.size===0)},H=t=>{const e=new Set([...t].map(Number));[...M.options].forEach(a=>{a.selected=e.has(Number(a.value))}),F()},x=()=>{l.clear(),g.clear(),u=null,b(),D()},J=t=>{!t||t.open||(w=t,t.showModal(),S||(history.pushState({billingDialog:!0},""),S=!0))},_=(t,e=!0)=>{t?.open&&(t.close(),w=null,e&&S&&(S=!1,history.back()))},Q=()=>{f.clear(),$().forEach(e=>f.add(e));const t=r("[data-project-search]");t&&(t.value=""),T(),J(N)},K=async()=>{if(!f.size)return;const t=$().sort((a,s)=>a-s).join(","),e=[...f].sort((a,s)=>a-s).join(",");H(f),_(N),t!==e&&(x(),p(""),await A())},X=async t=>{const e=new Set($());e.delete(Number(t)),H(e),x(),p(""),await A()},D=()=>{const t=r("[data-scanned-batches]");t.innerHTML=g.size?[...g.values()].map(a=>`
                <article class="billing-batch-card">
                    <div class="billing-batch-main">
                        <strong>${o(a.number||a.display)}</strong>
                        <b>${o(a.associate||"Associado não identificado")}</b>
                        <span>${a.ids.length} distribuição(ões) selecionada(s)${a.excluded?` · ${a.excluded} ignorada(s)`:""}</span>
                        ${(a.distributions||[]).length?`
                            <details>
                                <summary>Ver distribuições</summary>
                                <ul>${a.distributions.map(s=>`
                                    <li>#${s.id} · ${o(s.product)} · ${o(s.quantity)} ${o(s.unit)}${s.date?` · ${o(s.date)}`:""}</li>
                                `).join("")}</ul>
                            </details>
                        `:""}
                    </div>
                    <button class="billing-btn billing-btn-danger" type="button" data-remove-batch="${o(a.key)}">
                        <i class="ph ph-trash" aria-hidden="true"></i>
                        Remover
                    </button>
                </article>
            `).join(""):'<p class="billing-help">Nenhum lote escaneado.</p>';const e=r("[data-scanner-batch-preview]");e&&(e.innerHTML=g.size?[...g.values()].map(a=>`
                    <div class="acc-scan-preview-item">
                        <strong>${o(a.number||a.display)}</strong>
                        <span>${o(a.associate||"")}</span>
                        <small>${a.ids.length} distribuição(ões)</small>
                    </div>
                `).join(""):'<p class="billing-help">Comprovantes e distribuições aparecerão aqui durante a leitura.</p>')},O=()=>{l.clear(),g.forEach(t=>t.ids.forEach(e=>l.add(Number(e)))),b(),D()},B=()=>{const t=c.querySelector('[name="recipient_type"]:checked')?.value;v("[data-recipient-field]").forEach(e=>{e.hidden=e.dataset.recipientField!==t})},U=(t,e=!0)=>{const a=e?c.customer_id.value:"",s=e?c.organization_id.value:"";c.customer_id.innerHTML='<option value="">Selecione um cliente</option>'+(t.customers||[]).map(n=>`<option value="${n.id}">${o(n.name)}</option>`).join(""),c.organization_id.innerHTML='<option value="">Selecione uma organização</option>'+(t.organizations||[]).map(n=>`<option value="${n.id}">${o(n.name)}</option>`).join(""),[...c.customer_id.options].some(n=>n.value===a)&&(c.customer_id.value=a),[...c.organization_id.options].some(n=>n.value===s)&&(c.organization_id.value=s)},A=async()=>{const t=++C,e=new URLSearchParams;if($().forEach(a=>e.append("project_ids[]",a)),!e.has("project_ids[]")){U({customers:[],organizations:[]},!1);return}try{const a=await h(`${d.dataset.contextUrl}?${e}`,{method:"GET",headers:{"Content-Type":"application/json"}});if(t!==C)return;U(a),!(a.customers||[]).length&&!(a.organizations||[]).length&&p("Os projetos selecionados não possuem distribuições elegíveis para faturamento.",!0)}catch(a){t===C&&p(a.message,!0)}},W=()=>({...z(),mode:j,receipt_codes:r("[data-receipt-codes]").value.split(/[\n,;]+/).map(t=>t.trim()).filter(Boolean),distribution_ids:[...l]}),Y=async()=>{p("");try{const t=await h(d.dataset.selectUrl,{method:"POST",body:JSON.stringify(W())});j==="receipts"?(g.clear(),(t.batches||[]).forEach(a=>{const s=a.documents?.[0]||{};g.set(a.key,{key:a.key,display:a.label,number:s.number,associate:s.associate,distributions:s.distributions||[],ids:(a.selected_ids||[]).map(Number),excluded:Number(a.excluded_count||0)})}),O()):(l.clear(),(t.selected_ids||[]).forEach(a=>l.add(Number(a))),b());const e=t.excluded_count?` ${t.excluded_count} item(ns) incompatível(is) foram ignorados.`:"";p(`${l.size} distribuição(ões) selecionada(s).${e}`,t.excluded_count>0)}catch(t){p(t.message,!0)}},P=async(t=1)=>{try{const e=new URLSearchParams,a=z();a.project_ids.forEach(i=>e.append("project_ids[]",i)),["customer_id","organization_id","from_date","to_date"].forEach(i=>{a[i]&&e.set(i,a[i])}),e.set("page",t),e.set("search",r("[data-manual-search]").value||"");const n=(await h(`${d.dataset.manualUrl}?${e}`,{method:"GET",headers:{"Content-Type":"application/json"}})).distributions;r("[data-manual-rows]").innerHTML=(n.data||[]).map(i=>`
                <tr>
                    <td><input type="checkbox" data-pick="${i.id}" ${l.has(Number(i.id))?"checked":""} aria-label="Selecionar distribuição ${i.id}"></td>
                    <td>${o(i.date)}</td>
                    <td>${o(i.producer)}</td>
                    <td>${o(i.product)}</td>
                    <td>${o(i.quantity)} ${o(i.unit)}</td>
                    <td>${o(i.recipient)}</td>
                </tr>
            `).join("")||'<tr><td class="billing-empty-row" colspan="6">Nenhuma distribuição elegível.</td></tr>',r("[data-manual-pagination]").innerHTML=`
                <span>Página ${n.current_page} de ${n.last_page} · ${n.total} item(ns)</span>
                <div class="billing-pagination-actions">
                    <button class="billing-btn" type="button" data-manual-page="${n.current_page-1}" ${n.current_page<=1?"disabled":""}>Anterior</button>
                    <button class="billing-btn" type="button" data-manual-page="${n.current_page+1}" ${n.current_page>=n.last_page?"disabled":""}>Próxima</button>
                </div>
            `}catch(e){p(e.message,!0)}},I=async()=>{if(z(),!l.size)throw new Error("Selecione ao menos uma distribuição antes de continuar.");return u=await h(d.dataset.previewUrl,{method:"POST",body:JSON.stringify(R())}),l.clear(),(u.selected_ids||[]).forEach(t=>l.add(Number(t))),b(),Z(),u},Z=()=>{r("[data-review-count]").textContent=`${u.summary.distributions} distribuição(ões) · ${u.summary.producers} produtor(es)`,r("[data-review-rows]").innerHTML=u.distributions.map(e=>`
            <tr>
                <td>${o(e.date)}</td>
                <td>${o(e.producer)}</td>
                <td>${o(e.product)}</td>
                <td>${o(e.quantity)} ${o(e.unit)}</td>
                <td>${o(e.recipient)}</td>
                <td>
                    <button class="billing-link-btn" type="button" data-remove-id="${e.id}" aria-label="Remover distribuição ${e.id}" title="Remover">
                        <i class="ph ph-x" aria-hidden="true"></i>
                    </button>
                </td>
            </tr>
        `).join("");const t=Object.entries(u.exclusion_reasons||{});r("[data-exclusions]").innerHTML=t.length?`<div class="billing-alert is-error"><strong>Itens ignorados</strong><ul>${t.map(([e,a])=>`<li>${o(e.replaceAll("_"," "))}: ${a}</li>`).join("")}</ul></div>`:"",r("[data-preview-summary]").innerHTML=`
            <article><span>Distribuições</span><strong>${u.summary.distributions}</strong></article>
            <article><span>Produtores</span><strong>${u.summary.producers}</strong></article>
            <article><span>Documentos de origem</span><strong>${u.summary.source_receipts}</strong></article>
        `,r("[data-financial-lines]").innerHTML=u.lines.map(e=>`
            <tr>
                <td>${o(e.product)}</td>
                <td>${o(e.quantity)}</td>
                <td>${o(e.unit)}</td>
                <td>${y(e.unit_price)}</td>
                <td>${y(e.document_amount)}</td>
            </tr>
        `).join("")||'<tr><td class="billing-empty-row" colspan="5">Sem linhas.</td></tr>',r("[data-financial-fees]").innerHTML=u.fees.length?`<div class="billing-fee-list">${u.fees.map(e=>`
                <div>
                    <span>${o(e.name||"Ajuste")} · ${o(e.nature==="accrual"?"Acréscimo":"Desconto")}</span>
                    <strong>${y(e.amount)}</strong>
                </div>
            `).join("")}</div>`:'<p class="billing-help">Nenhuma taxa, desconto ou acréscimo configurado.</p>',r("[data-financial-totals]").innerHTML=`
            <div><span>Bruto</span><strong>${y(u.totals.gross)}</strong></div>
            <div><span>Ajustes líquidos</span><strong>${y(u.totals.fees)}</strong></div>
            <div class="is-total"><span>Total a cobrar</span><strong>${y(u.totals.net)}</strong></div>
        `},ee=async()=>{try{await I();const t={...R(),operation_key:crypto.randomUUID()},e=await h(d.dataset.saveUrl,{method:d.dataset.saveMethod,body:JSON.stringify(t)});p(e.message||"Rascunho salvo."),e.redirect_url&&location.assign(e.redirect_url)}catch(t){p(t.message,!0)}},te=()=>{J(k)},ae=async()=>{_(k);try{const t=await h(d.dataset.freezeUrl,{method:"POST",body:"{}"});location.assign(t.redirect_url)}catch(t){p(t.message,!0)}},se=async t=>{const e=r("[data-scan-feedback]");e&&(e.classList.remove("is-error"),e.textContent="Conferindo QR Code e selecionando as distribuições...");try{const s=(await h(d.dataset.selectUrl,{method:"POST",body:JSON.stringify({...z(),mode:"receipts",receipt_codes:[t],distribution_ids:[]})})).batches?.[0];if(!s?.receipt_found)throw new Error("Este QR Code não corresponde a um documento de origem desta organização.");const n=s.documents?.[0]||{};g.set(s.key,{key:s.key,display:s.label,number:n.number,associate:n.associate,distributions:n.distributions||[],ids:(s.selected_ids||[]).map(Number),excluded:Number(s.excluded_count||0)});const i=r("[data-receipt-codes]").value.split(/\n+/).map(q=>q.trim()).filter(Boolean);i.includes(t)||i.push(t),r("[data-receipt-codes]").value=i.join(`
`),O(),e&&(e.textContent=`Lote adicionado: ${s.selected_count} distribuição(ões). Continue apontando para outros comprovantes.`)}catch(a){e?(e.textContent=a.message,e.classList.add("is-error")):p(a.message,!0)}},oe=async()=>{try{const t=await h(d.dataset.contextUrl,{method:"GET",headers:{"Content-Type":"application/json"}});if(L=t.projects||[],M.innerHTML=L.map(e=>`
                <option value="${e.id}">${o(e.name)}${e.code?` · ${o(e.code)}`:""}</option>
            `).join(""),U(t,!1),c.issued_at.value=new Date().toISOString().slice(0,10),t.draft){const e=new Set((t.draft.project_ids||[]).map(Number));H(e);const a=t.draft.organization_id?"organization":"customer";c.querySelector(`[name="recipient_type"][value="${a}"]`).checked=!0,c.organization_id.value=t.draft.organization_id||"",c.customer_id.value=t.draft.customer_id||"",["issued_at","from_date","to_date","notes"].forEach(s=>{c[s].value=t.draft[s]||""}),(t.draft.distribution_ids||[]).forEach(s=>l.add(Number(s))),B(),b(),e.size&&(await A(),c.organization_id.value=t.draft.organization_id||"",c.customer_id.value=t.draft.customer_id||"")}else F(),B(),b();D(),E(1)}catch(t){p(t.message,!0)}};d.addEventListener("click",async t=>{const e=t.target.closest("button");if(e){if(e.matches("[data-step-button]")){const a=Number(e.dataset.stepButton);a<=m&&E(a);return}if(e.matches("[data-next]")){try{z(),(m===2||m===3)&&await I(),E(m+1),p("")}catch(a){p(a.message,!0)}return}if(e.matches("[data-previous]")){E(m-1);return}if(e.matches("[data-open-project-picker]")){Q();return}if(e.matches("[data-close-project-picker], [data-cancel-project-picker]")){_(N);return}if(e.matches("[data-project-option]")){const a=Number(e.dataset.projectOption);f.has(a)?f.delete(a):f.add(a),T();return}if(e.matches("[data-clear-project-picker]")){f.clear(),T();return}if(e.matches("[data-apply-project-picker]")){await K();return}if(e.matches("[data-remove-project]")){await X(e.dataset.removeProject);return}if(e.matches("[data-mode]")){j=e.dataset.mode,v("[data-mode]").forEach(a=>a.classList.toggle("is-active",a===e)),v("[data-mode-panel]").forEach(a=>{a.hidden=a.dataset.modePanel!==j}),j==="manual"&&P();return}if(e.matches("[data-load-selection]")){Y();return}if(e.matches("[data-manual-load]")){P();return}if(e.matches("[data-manual-page]")){P(Number(e.dataset.manualPage));return}if(e.matches("[data-remove-id]")){l.delete(Number(e.dataset.removeId)),b();try{await I()}catch(a){E(2),p(a.message,!0)}return}if(e.matches("[data-remove-batch]")){g.delete(e.dataset.removeBatch),O(),r("[data-receipt-codes]").value=[...g.values()].map(a=>a.display).join(`
`);return}if(e.matches("[data-save]")){ee();return}if(e.matches("[data-freeze]")){te();return}if(e.matches("[data-cancel-freeze]")){_(k);return}e.matches("[data-confirm-freeze]")&&ae()}}),d.addEventListener("input",t=>{t.target.matches("[data-project-search]")&&T()}),d.addEventListener("keydown",t=>{t.target.matches("[data-manual-search]")&&t.key==="Enter"&&(t.preventDefault(),P())}),d.addEventListener("accounting:qr-code",t=>se(t.detail.code)),d.addEventListener("change",t=>{if(t.target.matches('[name="recipient_type"]')&&(B(),x()),t.target.matches('[name="organization_id"], [name="customer_id"], [name="from_date"], [name="to_date"]')&&x(),t.target.matches("[data-pick]")){const e=Number(t.target.dataset.pick);t.target.checked?l.add(e):l.delete(e),b()}}),[N,k].forEach(t=>{t&&(t.addEventListener("cancel",e=>{e.preventDefault(),_(t)}),t.addEventListener("click",e=>{e.target===t&&_(t)}))}),window.addEventListener("popstate",()=>{w?.open&&(S=!1,w.close(),w=null)}),oe()})();
