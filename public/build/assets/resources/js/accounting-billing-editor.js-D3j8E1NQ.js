(()=>{const d=document.querySelector("[data-billing-editor]");if(!d)return;const c=d.querySelector("[data-billing-form]"),F=document.querySelector('meta[name="csrf-token"]')?.content||"",l=new Set,g=new Map,f=new Set;let L=[],m=1,w="period",u=null,C=0,S=null,z=!1;const n=e=>d.querySelector(e),v=e=>[...d.querySelectorAll(e)],M=n("[data-project-select]"),N=n("[data-project-dialog]"),k=n("[data-freeze-dialog]"),o=e=>String(e??"").replace(/[&<>'"]/g,t=>({"&":"&amp;","<":"&lt;",">":"&gt;","'":"&#39;",'"':"&quot;"})[t]),y=e=>Number(e||0).toLocaleString("pt-BR",{style:"currency",currency:"BRL"}),p=(e,t=!1)=>{const a=n("[data-editor-message]");a.hidden=!e,a.textContent=e||"",a.classList.toggle("is-error",t),e&&a.scrollIntoView({behavior:"smooth",block:"nearest"})},h=async(e,t={})=>{const a=await fetch(e,{credentials:"same-origin",headers:{Accept:"application/json","Content-Type":"application/json","X-CSRF-TOKEN":F,...t.headers||{}},...t}),s=await a.json().catch(()=>({}));if(!a.ok){const i=Object.values(s.errors||{}).flat().join(" ");throw new Error(i||s.message||"Não foi possível concluir esta operação.")}return s},$=()=>[...M.selectedOptions].map(e=>Number(e.value)).filter(Boolean),R=()=>{const e=c.querySelector('[name="recipient_type"]:checked')?.value;return{project_ids:$(),customer_id:e==="customer"&&Number(c.customer_id.value)||null,organization_id:e==="organization"&&Number(c.organization_id.value)||null,issued_at:c.issued_at.value,from_date:c.from_date.value||null,to_date:c.to_date.value||null,notes:c.notes.value||null,distribution_ids:[...l]}},_=()=>{const e=R();if(!e.project_ids.length)throw new Error("Selecione ao menos um projeto.");if(!e.customer_id&&!e.organization_id)throw new Error("Selecione quem será cobrado.");if(!e.issued_at)throw new Error("Informe a data de emissão.");if(e.from_date&&e.to_date&&e.to_date<e.from_date)throw new Error("A data final não pode ser anterior à inicial.");return e},E=e=>{m=Math.max(1,Math.min(4,Number(e)||1)),v("[data-step]").forEach(t=>{t.hidden=Number(t.dataset.step)!==m}),v("[data-step-button]").forEach(t=>{const a=Number(t.dataset.stepButton);t.classList.toggle("is-active",a===m),t.classList.toggle("is-done",a<m),t.setAttribute("aria-current",a===m?"step":"false")}),n("[data-previous]").hidden=m===1,n("[data-next]").hidden=m===4,n("[data-save]").hidden=m<3,n("[data-freeze]")&&(n("[data-freeze]").hidden=m!==4),d.scrollIntoView({behavior:"smooth",block:"start"})},b=()=>{const e=l.size===1?"1 selecionada":`${l.size} selecionadas`;v("[data-selected-count]").forEach(t=>{t.textContent=e})},J=()=>{const e=n("[data-project-summary]"),t=new Set($()),a=L.filter(s=>t.has(Number(s.id)));if(e.classList.toggle("is-scrollable",a.length>4),!a.length){e.innerHTML='<div class="billing-project-empty">Nenhum projeto selecionado. Escolha o projeto antes de definir o destinatário.</div>';return}e.innerHTML=a.map(s=>`
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
        `).join("")},T=()=>{const e=n("[data-project-options]"),t=(n("[data-project-search]")?.value||"").trim().toLocaleLowerCase("pt-BR"),a=L.filter(r=>t?`${r.name||""} ${r.code||""}`.toLocaleLowerCase("pt-BR").includes(t):!0);e.innerHTML=a.length?a.map(r=>{const q=Number(r.id),V=f.has(q);return`
                    <button
                        class="billing-project-row${V?" is-selected":""}"
                        type="button"
                        data-project-option="${q}"
                        aria-pressed="${V?"true":"false"}"
                    >
                        <span class="billing-project-check" aria-hidden="true"><i class="ph-fill ph-check"></i></span>
                        <span>
                            <strong>${o(r.name)}</strong>
                            <small>${r.code?"Código do projeto":"Disponível para faturamento"}</small>
                        </span>
                        ${r.code?`<span class="billing-project-code">${o(r.code)}</span>`:""}
                    </button>
                `}).join(""):'<div class="billing-project-empty">Nenhum projeto encontrado para esta busca.</div>';const s=n("[data-project-dialog-count]");s&&(s.textContent=f.size===1?"1 selecionado":`${f.size} selecionados`);const i=n("[data-apply-project-picker]");i&&(i.disabled=f.size===0)},H=e=>{const t=new Set([...e].map(Number));[...M.options].forEach(a=>{a.selected=t.has(Number(a.value))}),J()},x=()=>{l.clear(),g.clear(),u=null,b(),B()},G=e=>{!e||e.open||(S=e,e.showModal(),z||(history.pushState({billingDialog:!0},""),z=!0))},j=(e,t=!0)=>{e?.open&&(e.close(),S=null,t&&z&&(z=!1,history.back()))},Q=()=>{f.clear(),$().forEach(t=>f.add(t));const e=n("[data-project-search]");e&&(e.value=""),T(),G(N)},K=async()=>{if(!f.size)return;const e=$().sort((a,s)=>a-s).join(","),t=[...f].sort((a,s)=>a-s).join(",");H(f),j(N),e!==t&&(x(),p(""),await A())},X=async e=>{const t=new Set($());t.delete(Number(e)),H(t),x(),p(""),await A()},B=()=>{const e=n("[data-scanned-batches]");e.innerHTML=g.size?[...g.values()].map(a=>`
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
            `).join(""):'<p class="billing-help">Nenhum lote escaneado.</p>';const t=n("[data-scanner-batch-preview]");if(t){const a=[...g.values()];t.innerHTML=g.size?`${a.length>3?`<small class="acc-scan-preview-total">${a.length} comprovantes selecionados · exibindo os 3 mais recentes</small>`:""}${a.slice(-3).map(s=>`
                    <div class="acc-scan-preview-item">
                        <strong>${o(s.number||s.display)}</strong>
                        <span>${o(s.associate||"")}</span>
                        <small>${s.ids.length} distribuição(ões)</small>
                    </div>
                `).join("")}`:'<p class="billing-help">Comprovantes e distribuições aparecerão aqui durante a leitura.</p>'}},D=()=>{l.clear(),g.forEach(e=>e.ids.forEach(t=>l.add(Number(t)))),b(),B()},O=()=>{const e=c.querySelector('[name="recipient_type"]:checked')?.value;v("[data-recipient-field]").forEach(t=>{t.hidden=t.dataset.recipientField!==e})},U=(e,t=!0)=>{const a=t?c.customer_id.value:"",s=t?c.organization_id.value:"";c.customer_id.innerHTML='<option value="">Selecione um cliente</option>'+(e.customers||[]).map(i=>`<option value="${i.id}">${o(i.name)}</option>`).join(""),c.organization_id.innerHTML='<option value="">Selecione uma organização</option>'+(e.organizations||[]).map(i=>`<option value="${i.id}">${o(i.name)}</option>`).join(""),[...c.customer_id.options].some(i=>i.value===a)&&(c.customer_id.value=a),[...c.organization_id.options].some(i=>i.value===s)&&(c.organization_id.value=s)},A=async()=>{const e=++C,t=new URLSearchParams;if($().forEach(a=>t.append("project_ids[]",a)),!t.has("project_ids[]")){U({customers:[],organizations:[]},!1);return}try{const a=await h(`${d.dataset.contextUrl}?${t}`,{method:"GET",headers:{"Content-Type":"application/json"}});if(e!==C)return;U(a),!(a.customers||[]).length&&!(a.organizations||[]).length&&p("Os projetos selecionados não possuem distribuições elegíveis para faturamento.",!0)}catch(a){e===C&&p(a.message,!0)}},W=()=>({..._(),mode:w,receipt_codes:n("[data-receipt-codes]").value.split(/[\n,;]+/).map(e=>e.trim()).filter(Boolean),distribution_ids:[...l]}),Y=async()=>{p("");try{const e=await h(d.dataset.selectUrl,{method:"POST",body:JSON.stringify(W())});w==="receipts"?(g.clear(),(e.batches||[]).forEach(a=>{const s=a.documents?.[0]||{};g.set(a.key,{key:a.key,display:a.label,number:s.number,associate:s.associate,distributions:s.distributions||[],ids:(a.selected_ids||[]).map(Number),excluded:Number(a.excluded_count||0)})}),D()):(l.clear(),(e.selected_ids||[]).forEach(a=>l.add(Number(a))),b());const t=e.excluded_count?` ${e.excluded_count} item(ns) incompatível(is) foram ignorados.`:"";p(`${l.size} distribuição(ões) selecionada(s).${t}`,e.excluded_count>0)}catch(e){p(e.message,!0)}},P=async(e=1)=>{try{const t=new URLSearchParams,a=_();a.project_ids.forEach(r=>t.append("project_ids[]",r)),["customer_id","organization_id","from_date","to_date"].forEach(r=>{a[r]&&t.set(r,a[r])}),t.set("page",e),t.set("search",n("[data-manual-search]").value||"");const i=(await h(`${d.dataset.manualUrl}?${t}`,{method:"GET",headers:{"Content-Type":"application/json"}})).distributions;n("[data-manual-rows]").innerHTML=(i.data||[]).map(r=>`
                <tr>
                    <td><input type="checkbox" data-pick="${r.id}" ${l.has(Number(r.id))?"checked":""} aria-label="Selecionar distribuição ${r.id}"></td>
                    <td>${o(r.date)}</td>
                    <td>${o(r.producer)}</td>
                    <td>${o(r.product)}</td>
                    <td>${o(r.quantity)} ${o(r.unit)}</td>
                    <td>${o(r.recipient)}</td>
                </tr>
            `).join("")||'<tr><td class="billing-empty-row" colspan="6">Nenhuma distribuição elegível.</td></tr>',n("[data-manual-pagination]").innerHTML=`
                <span>Página ${i.current_page} de ${i.last_page} · ${i.total} item(ns)</span>
                <div class="billing-pagination-actions">
                    <button class="billing-btn" type="button" data-manual-page="${i.current_page-1}" ${i.current_page<=1?"disabled":""}>Anterior</button>
                    <button class="billing-btn" type="button" data-manual-page="${i.current_page+1}" ${i.current_page>=i.last_page?"disabled":""}>Próxima</button>
                </div>
            `}catch(t){p(t.message,!0)}},I=async()=>{if(_(),!l.size)throw new Error("Selecione ao menos uma distribuição antes de continuar.");return u=await h(d.dataset.previewUrl,{method:"POST",body:JSON.stringify(R())}),l.clear(),(u.selected_ids||[]).forEach(e=>l.add(Number(e))),b(),Z(),u},Z=()=>{n("[data-review-count]").textContent=`${u.summary.distributions} distribuição(ões) · ${u.summary.producers} produtor(es)`,n("[data-review-rows]").innerHTML=u.distributions.map(t=>`
            <tr>
                <td>${o(t.date)}</td>
                <td>${o(t.producer)}</td>
                <td>${o(t.product)}</td>
                <td>${o(t.quantity)} ${o(t.unit)}</td>
                <td>${o(t.recipient)}</td>
                <td>
                    <button class="billing-link-btn" type="button" data-remove-id="${t.id}" aria-label="Remover distribuição ${t.id}" title="Remover">
                        <i class="ph ph-x" aria-hidden="true"></i>
                    </button>
                </td>
            </tr>
        `).join("");const e=Object.entries(u.exclusion_reasons||{});n("[data-exclusions]").innerHTML=e.length?`<div class="billing-alert is-error"><strong>Itens ignorados</strong><ul>${e.map(([t,a])=>`<li>${o(t.replaceAll("_"," "))}: ${a}</li>`).join("")}</ul></div>`:"",n("[data-preview-summary]").innerHTML=`
            <article><span>Distribuições</span><strong>${u.summary.distributions}</strong></article>
            <article><span>Produtores</span><strong>${u.summary.producers}</strong></article>
            <article><span>Documentos de origem</span><strong>${u.summary.source_receipts}</strong></article>
        `,n("[data-financial-lines]").innerHTML=u.lines.map(t=>`
            <tr>
                <td>${o(t.product)}</td>
                <td>${o(t.quantity)}</td>
                <td>${o(t.unit)}</td>
                <td>${y(t.unit_price)}</td>
                <td>${y(t.document_amount)}</td>
            </tr>
        `).join("")||'<tr><td class="billing-empty-row" colspan="5">Sem linhas.</td></tr>',n("[data-financial-fees]").innerHTML=u.fees.length?`<div class="billing-fee-list">${u.fees.map(t=>`
                <div>
                    <span>${o(t.name||"Ajuste")} · ${o(t.nature==="accrual"?"Acréscimo":"Desconto")}</span>
                    <strong>${y(t.amount)}</strong>
                </div>
            `).join("")}</div>`:'<p class="billing-help">Nenhuma taxa, desconto ou acréscimo configurado.</p>',n("[data-financial-totals]").innerHTML=`
            <div><span>Bruto</span><strong>${y(u.totals.gross)}</strong></div>
            <div><span>Ajustes líquidos</span><strong>${y(u.totals.fees)}</strong></div>
            <div class="is-total"><span>Total a cobrar</span><strong>${y(u.totals.net)}</strong></div>
        `},ee=async()=>{try{await I();const e={...R(),operation_key:crypto.randomUUID()},t=await h(d.dataset.saveUrl,{method:d.dataset.saveMethod,body:JSON.stringify(e)});p(t.message||"Rascunho salvo."),t.redirect_url&&location.assign(t.redirect_url)}catch(e){p(e.message,!0)}},te=()=>{G(k)},ae=async()=>{j(k);try{const e=await h(d.dataset.freezeUrl,{method:"POST",body:"{}"});location.assign(e.redirect_url)}catch(e){p(e.message,!0)}},se=async e=>{const t=n("[data-scan-feedback]");t&&(t.classList.remove("is-error"),t.textContent="Conferindo QR Code e selecionando as distribuições...");try{const s=(await h(d.dataset.selectUrl,{method:"POST",body:JSON.stringify({..._(),mode:"receipts",receipt_codes:[e],distribution_ids:[]})})).batches?.[0];if(!s?.receipt_found)throw new Error("Este QR Code não corresponde a um documento de origem desta organização.");const i=s.documents?.[0]||{};g.set(s.key,{key:s.key,display:s.label,number:i.number,associate:i.associate,distributions:i.distributions||[],ids:(s.selected_ids||[]).map(Number),excluded:Number(s.excluded_count||0)});const r=n("[data-receipt-codes]").value.split(/\n+/).map(q=>q.trim()).filter(Boolean);r.includes(e)||r.push(e),n("[data-receipt-codes]").value=r.join(`
`),D(),t&&(t.textContent=`Lote adicionado: ${s.selected_count} distribuição(ões). Continue apontando para outros comprovantes.`),window.document.dispatchEvent(new CustomEvent("accounting:qr-success",{detail:{number:i.number||s.label,associate:i.associate||"",count:Number(s.selected_count||0)}}))}catch(a){t?(t.textContent=a.message,t.classList.add("is-error")):p(a.message,!0)}},oe=async()=>{try{const e=await h(d.dataset.contextUrl,{method:"GET",headers:{"Content-Type":"application/json"}});if(L=e.projects||[],M.innerHTML=L.map(t=>`
                <option value="${t.id}">${o(t.name)}${t.code?` · ${o(t.code)}`:""}</option>
            `).join(""),U(e,!1),c.issued_at.value=new Date().toISOString().slice(0,10),e.draft){const t=new Set((e.draft.project_ids||[]).map(Number));H(t);const a=e.draft.organization_id?"organization":"customer";c.querySelector(`[name="recipient_type"][value="${a}"]`).checked=!0,c.organization_id.value=e.draft.organization_id||"",c.customer_id.value=e.draft.customer_id||"",["issued_at","from_date","to_date","notes"].forEach(s=>{c[s].value=e.draft[s]||""}),(e.draft.distribution_ids||[]).forEach(s=>l.add(Number(s))),O(),b(),t.size&&(await A(),c.organization_id.value=e.draft.organization_id||"",c.customer_id.value=e.draft.customer_id||"")}else J(),O(),b();B(),E(1)}catch(e){p(e.message,!0)}};d.addEventListener("click",async e=>{const t=e.target.closest("button");if(t){if(t.matches("[data-step-button]")){const a=Number(t.dataset.stepButton);a<=m&&E(a);return}if(t.matches("[data-next]")){try{_(),(m===2||m===3)&&await I(),E(m+1),p("")}catch(a){p(a.message,!0)}return}if(t.matches("[data-previous]")){E(m-1);return}if(t.matches("[data-open-project-picker]")){Q();return}if(t.matches("[data-close-project-picker], [data-cancel-project-picker]")){j(N);return}if(t.matches("[data-project-option]")){const a=Number(t.dataset.projectOption);f.has(a)?f.delete(a):f.add(a),T();return}if(t.matches("[data-clear-project-picker]")){f.clear(),T();return}if(t.matches("[data-apply-project-picker]")){await K();return}if(t.matches("[data-remove-project]")){await X(t.dataset.removeProject);return}if(t.matches("[data-mode]")){w=t.dataset.mode,v("[data-mode]").forEach(a=>a.classList.toggle("is-active",a===t)),v("[data-mode-panel]").forEach(a=>{a.hidden=a.dataset.modePanel!==w}),w==="manual"&&P();return}if(t.matches("[data-load-selection]")){Y();return}if(t.matches("[data-manual-load]")){P();return}if(t.matches("[data-manual-page]")){P(Number(t.dataset.manualPage));return}if(t.matches("[data-remove-id]")){l.delete(Number(t.dataset.removeId)),b();try{await I()}catch(a){E(2),p(a.message,!0)}return}if(t.matches("[data-remove-batch]")){g.delete(t.dataset.removeBatch),D(),n("[data-receipt-codes]").value=[...g.values()].map(a=>a.display).join(`
`);return}if(t.matches("[data-save]")){ee();return}if(t.matches("[data-freeze]")){te();return}if(t.matches("[data-cancel-freeze]")){j(k);return}t.matches("[data-confirm-freeze]")&&ae()}}),d.addEventListener("input",e=>{e.target.matches("[data-project-search]")&&T()}),d.addEventListener("keydown",e=>{e.target.matches("[data-manual-search]")&&e.key==="Enter"&&(e.preventDefault(),P())}),d.addEventListener("accounting:qr-code",async e=>{await se(e.detail.code),e.detail.onComplete?.()}),d.addEventListener("accounting:qr-native-config",e=>{try{e.detail.verificationUrl=d.dataset.selectUrl,e.detail.csrfToken=F,e.detail.selectionPayload={..._(),mode:"receipts",receipt_codes:[],distribution_ids:[]}}catch(t){e.detail.error=t}}),d.addEventListener("change",e=>{if(e.target.matches('[name="recipient_type"]')&&(O(),x()),e.target.matches('[name="organization_id"], [name="customer_id"], [name="from_date"], [name="to_date"]')&&x(),e.target.matches("[data-pick]")){const t=Number(e.target.dataset.pick);e.target.checked?l.add(t):l.delete(t),b()}}),[N,k].forEach(e=>{e&&(e.addEventListener("cancel",t=>{t.preventDefault(),j(e)}),e.addEventListener("click",t=>{t.target===e&&j(e)}))}),window.addEventListener("popstate",()=>{S?.open&&(z=!1,S.close(),S=null)}),oe()})();
