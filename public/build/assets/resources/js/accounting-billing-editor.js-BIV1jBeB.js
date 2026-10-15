(()=>{const c=document.querySelector("[data-billing-editor]");if(!c)return;const d=c.querySelector("[data-billing-form]"),V=document.querySelector('meta[name="csrf-token"]')?.content||"",p=new Set,b=new Map,g=new Set;let T=[],m=1,x="period",l=null,f=[],O=0,N=null,z=!1;const K=crypto.randomUUID(),r=t=>c.querySelector(t),h=t=>[...c.querySelectorAll(t)],D=r("[data-project-select]"),P=r("[data-project-dialog]"),q=r("[data-freeze-dialog]"),s=t=>String(t??"").replace(/[&<>'"]/g,e=>({"&":"&amp;","<":"&lt;",">":"&gt;","'":"&#39;",'"':"&quot;"})[e]),$=t=>Number(t||0).toLocaleString("pt-BR",{style:"currency",currency:"BRL"}),u=(t,e=!1)=>{const a=r("[data-editor-message]");a.hidden=!t,a.textContent=t||"",a.classList.toggle("is-error",e),t&&a.scrollIntoView({behavior:"smooth",block:"nearest"})},v=async(t,e={})=>{const a=await fetch(t,{credentials:"same-origin",headers:{Accept:"application/json","Content-Type":"application/json","X-CSRF-TOKEN":V,...e.headers||{}},...e}),n=await a.json().catch(()=>({}));if(!a.ok){const o=Object.values(n.errors||{}).flat().join(" ");throw new Error(o||n.message||"Não foi possível concluir esta operação.")}return n},_=()=>[...D.selectedOptions].map(t=>Number(t.value)).filter(Boolean),j=()=>{const t=h("[data-report-annotation]").map(e=>({target:e.querySelector("[data-annotation-target]").value,text:e.querySelector("[data-annotation-text]").value.trim()}));return t.length&&(f=t),f},L=()=>{const t=h("[data-report-annotation]").length?j():f,e=new Map;(l?.distributions||[]).forEach(n=>{n.product_id&&e.set(Number(n.product_id),n.product)});const a=[["global","Observação geral"],...[...e].map(([n,o])=>[`product:${n}`,`Produto: ${o}`]),...(l?.distributions||[]).map(n=>[`distribution:${n.id}`,`Distribuição #${n.id} · ${n.product} · ${n.date}`]),...(l?.distributions||[]).filter(n=>n.delivery_notes).map(n=>[`delivery-note:${n.id}`,`Observação da entrega · ${n.product} · ${n.date}`])];t.forEach(n=>{n.target&&!a.some(([o])=>o===n.target)&&a.push([n.target,"Referência selecionada (fora da seleção atual)"])}),r("[data-report-annotations]").innerHTML=t.map((n,o)=>`
            <div data-report-annotation class="billing-fields-3" style="margin:.6rem 0;padding:.75rem;border:1px solid #dce7e0;border-radius:10px">
                <label class="billing-field"><span>Aplicar a</span><select class="billing-select" data-annotation-target>
                    ${a.map(([i,E])=>`<option value="${s(i)}" ${i===n.target?"selected":""}>${s(E)}</option>`).join("")}
                </select></label>
                <label class="billing-field" style="grid-column:span 2"><span>Observação</span><textarea class="billing-textarea" data-annotation-text maxlength="1000" rows="2">${s(n.text)}</textarea></label>
                <button class="billing-btn" type="button" data-remove-report-annotation="${o}">Remover</button>
            </div>
        `).join(""),f=t},C=()=>{const t=d.querySelector('[name="recipient_type"]:checked')?.value;return{project_ids:_(),customer_id:t==="customer"&&Number(d.customer_id.value)||null,organization_id:t==="organization"&&Number(d.organization_id.value)||null,issued_at:d.issued_at.value,from_date:d.from_date.value||null,to_date:d.to_date.value||null,notes:d.notes.value||null,report_annotations_position:d.report_annotations_position.value,report_annotations:j().filter(e=>e.text),distribution_ids:[...p]}},w=()=>{const t=C();if(!t.project_ids.length)throw new Error("Selecione ao menos um projeto.");if(!t.customer_id&&!t.organization_id)throw new Error("Selecione quem será cobrado.");if(!t.issued_at)throw new Error("Informe a data de emissão.");if(t.from_date&&t.to_date&&t.to_date<t.from_date)throw new Error("A data final não pode ser anterior à inicial.");return t},k=t=>{m=Math.max(1,Math.min(4,Number(t)||1)),h("[data-step]").forEach(e=>{e.hidden=Number(e.dataset.step)!==m}),h("[data-step-button]").forEach(e=>{const a=Number(e.dataset.stepButton);e.classList.toggle("is-active",a===m),e.classList.toggle("is-done",a<m),e.setAttribute("aria-current",a===m?"step":"false")}),r("[data-previous]").hidden=m===1,r("[data-next]").hidden=m===4,r("[data-save]").hidden=m<3,r("[data-freeze]")&&(r("[data-freeze]").hidden=m!==4),c.scrollIntoView({behavior:"smooth",block:"start"})},y=()=>{const t=p.size===1?"1 selecionada":`${p.size} selecionadas`;h("[data-selected-count]").forEach(e=>{e.textContent=t})},Q=()=>{const t=r("[data-project-summary]"),e=new Set(_()),a=T.filter(n=>e.has(Number(n.id)));if(t.classList.toggle("is-scrollable",a.length>4),!a.length){t.innerHTML='<div class="billing-project-empty">Nenhum projeto selecionado. Escolha o projeto antes de definir o destinatário.</div>';return}t.innerHTML=a.map(n=>`
            <div class="billing-project-selected">
                <div>
                    <strong>${s(n.name)}</strong>
                    <small>${n.code?s(n.code):"Projeto selecionado"}</small>
                </div>
                <button
                    class="billing-btn"
                    type="button"
                    data-remove-project="${Number(n.id)}"
                    aria-label="Remover ${s(n.name)}"
                    title="Remover projeto"
                >
                    <i class="ph ph-x" aria-hidden="true"></i>
                </button>
            </div>
        `).join("")},M=()=>{const t=r("[data-project-options]"),e=(r("[data-project-search]")?.value||"").trim().toLocaleLowerCase("pt-BR"),a=T.filter(i=>e?`${i.name||""} ${i.code||""}`.toLocaleLowerCase("pt-BR").includes(e):!0);t.innerHTML=a.length?a.map(i=>{const E=Number(i.id),X=g.has(E);return`
                    <button
                        class="billing-project-row${X?" is-selected":""}"
                        type="button"
                        data-project-option="${E}"
                        aria-pressed="${X?"true":"false"}"
                    >
                        <span class="billing-project-check" aria-hidden="true"><i class="ph-fill ph-check"></i></span>
                        <span>
                            <strong>${s(i.name)}</strong>
                            <small>${i.code?"Código do projeto":"Disponível para faturamento"}</small>
                        </span>
                        ${i.code?`<span class="billing-project-code">${s(i.code)}</span>`:""}
                    </button>
                `}).join(""):'<div class="billing-project-empty">Nenhum projeto encontrado para esta busca.</div>';const n=r("[data-project-dialog-count]");n&&(n.textContent=g.size===1?"1 selecionado":`${g.size} selecionados`);const o=r("[data-apply-project-picker]");o&&(o.disabled=g.size===0)},B=t=>{const e=new Set([...t].map(Number));[...D.options].forEach(a=>{a.selected=e.has(Number(a.value))}),Q()},R=()=>{p.clear(),b.clear(),l=null,y(),U()},W=t=>{!t||t.open||(N=t,t.showModal(),z||(history.pushState({billingDialog:!0},""),z=!0))},S=(t,e=!0)=>{t?.open&&(t.close(),N=null,e&&z&&(z=!1,history.back()))},Y=()=>{g.clear(),_().forEach(e=>g.add(e));const t=r("[data-project-search]");t&&(t.value=""),M(),W(P)},Z=async()=>{if(!g.size)return;const t=_().sort((a,n)=>a-n).join(","),e=[...g].sort((a,n)=>a-n).join(",");B(g),S(P),t!==e&&(R(),u(""),await G())},ee=async t=>{const e=new Set(_());e.delete(Number(t)),B(e),R(),u(""),await G()},U=()=>{const t=r("[data-scanned-batches]");t.innerHTML=b.size?[...b.values()].map(a=>`
                <article class="billing-batch-card">
                    <div class="billing-batch-main">
                        <strong>${s(a.number||a.display)}</strong>
                        <b>${s(a.associate||"Associado não identificado")}</b>
                        <span>${a.ids.length} distribuição(ões) selecionada(s)${a.excluded?` · ${a.excluded} ignorada(s)`:""}</span>
                        ${(a.distributions||[]).length?`
                            <details>
                                <summary>Ver distribuições</summary>
                                <ul>${a.distributions.map(n=>`
                                    <li>#${n.id} · ${s(n.product)} · ${s(n.quantity)} ${s(n.unit)}${n.date?` · ${s(n.date)}`:""}</li>
                                `).join("")}</ul>
                            </details>
                        `:""}
                    </div>
                    <button class="billing-btn billing-btn-danger" type="button" data-remove-batch="${s(a.key)}">
                        <i class="ph ph-trash" aria-hidden="true"></i>
                        Remover
                    </button>
                </article>
            `).join(""):'<p class="billing-help">Nenhum lote escaneado.</p>';const e=r("[data-scanner-batch-preview]");if(e){const a=[...b.values()];e.innerHTML=b.size?`${a.length>3?`<small class="acc-scan-preview-total">${a.length} comprovantes selecionados · exibindo os 3 mais recentes</small>`:""}${a.slice(-3).map(n=>`
                    <div class="acc-scan-preview-item">
                        <strong>${s(n.number||n.display)}</strong>
                        <span>${s(n.associate||"")}</span>
                        <small>${n.ids.length} distribuição(ões)</small>
                    </div>
                `).join("")}`:'<p class="billing-help">Comprovantes e distribuições aparecerão aqui durante a leitura.</p>'}},I=()=>{p.clear(),b.forEach(t=>t.ids.forEach(e=>p.add(Number(e)))),y(),U()},J=()=>{const t=d.querySelector('[name="recipient_type"]:checked')?.value;h("[data-recipient-field]").forEach(e=>{e.hidden=e.dataset.recipientField!==t})},F=(t,e=!0)=>{const a=e?d.customer_id.value:"",n=e?d.organization_id.value:"";d.customer_id.innerHTML='<option value="">Selecione um cliente</option>'+(t.customers||[]).map(o=>`<option value="${o.id}">${s(o.name)}</option>`).join(""),d.organization_id.innerHTML='<option value="">Selecione uma organização</option>'+(t.organizations||[]).map(o=>`<option value="${o.id}">${s(o.name)}</option>`).join(""),[...d.customer_id.options].some(o=>o.value===a)&&(d.customer_id.value=a),[...d.organization_id.options].some(o=>o.value===n)&&(d.organization_id.value=n)},G=async()=>{const t=++O,e=new URLSearchParams;if(_().forEach(a=>e.append("project_ids[]",a)),!e.has("project_ids[]")){F({customers:[],organizations:[]},!1);return}try{const a=await v(`${c.dataset.contextUrl}?${e}`,{method:"GET",headers:{"Content-Type":"application/json"}});if(t!==O)return;F(a),!(a.customers||[]).length&&!(a.organizations||[]).length&&u("Os projetos selecionados não possuem distribuições elegíveis para faturamento.",!0)}catch(a){t===O&&u(a.message,!0)}},te=()=>({...w(),mode:x,receipt_codes:r("[data-receipt-codes]").value.split(/[\n,;]+/).map(t=>t.trim()).filter(Boolean),distribution_ids:[...p]}),ae=async()=>{u("");try{const t=await v(c.dataset.selectUrl,{method:"POST",body:JSON.stringify(te())});x==="receipts"?(b.clear(),(t.batches||[]).forEach(a=>{const n=a.documents?.[0]||{};b.set(a.key,{key:a.key,display:a.label,number:n.number,associate:n.associate,distributions:n.distributions||[],ids:(a.selected_ids||[]).map(Number),excluded:Number(a.excluded_count||0)})}),I()):(p.clear(),(t.selected_ids||[]).forEach(a=>p.add(Number(a))),y());const e=t.excluded_count?` ${t.excluded_count} item(ns) incompatível(is) foram ignorados.`:"";u(`${p.size} distribuição(ões) selecionada(s).${e}`,t.excluded_count>0)}catch(t){u(t.message,!0)}},A=async(t=1)=>{try{const e=new URLSearchParams,a=w();a.project_ids.forEach(i=>e.append("project_ids[]",i)),["customer_id","organization_id","from_date","to_date"].forEach(i=>{a[i]&&e.set(i,a[i])}),e.set("page",t),e.set("search",r("[data-manual-search]").value||"");const o=(await v(`${c.dataset.manualUrl}?${e}`,{method:"GET",headers:{"Content-Type":"application/json"}})).distributions;r("[data-manual-rows]").innerHTML=(o.data||[]).map(i=>`
                <tr>
                    <td><input type="checkbox" data-pick="${i.id}" ${p.has(Number(i.id))?"checked":""} aria-label="Selecionar distribuição ${i.id}"></td>
                    <td>${s(i.date)}</td>
                    <td>${s(i.producer)}</td>
                    <td>${s(i.product)}</td>
                    <td>${s(i.quantity)} ${s(i.unit)}</td>
                    <td>${s(i.recipient)}</td>
                </tr>
            `).join("")||'<tr><td class="billing-empty-row" colspan="6">Nenhuma distribuição elegível.</td></tr>',r("[data-manual-pagination]").innerHTML=`
                <span>Página ${o.current_page} de ${o.last_page} · ${o.total} item(ns)</span>
                <div class="billing-pagination-actions">
                    <button class="billing-btn" type="button" data-manual-page="${o.current_page-1}" ${o.current_page<=1?"disabled":""}>Anterior</button>
                    <button class="billing-btn" type="button" data-manual-page="${o.current_page+1}" ${o.current_page>=o.last_page?"disabled":""}>Próxima</button>
                </div>
            `}catch(e){u(e.message,!0)}},H=async()=>{if(w(),!p.size)throw new Error("Selecione ao menos uma distribuição antes de continuar.");return l=await v(c.dataset.previewUrl,{method:"POST",body:JSON.stringify(C())}),p.clear(),(l.selected_ids||[]).forEach(t=>p.add(Number(t))),y(),ne(),l},ne=()=>{r("[data-review-count]").textContent=`${l.summary.distributions} distribuição(ões) · ${l.summary.producers} produtor(es)`,r("[data-review-rows]").innerHTML=l.distributions.map(e=>`
            <tr>
                <td>${s(e.date)}</td>
                <td>${s(e.producer)}</td>
                <td>${s(e.product)}</td>
                <td>${s(e.quantity)} ${s(e.unit)}</td>
                <td>${s(e.recipient)}</td>
                <td>
                    <button class="billing-link-btn" type="button" data-remove-id="${e.id}" aria-label="Remover distribuição ${e.id}" title="Remover">
                        <i class="ph ph-x" aria-hidden="true"></i>
                    </button>
                </td>
            </tr>
        `).join(""),L();const t=Object.entries(l.exclusion_reasons||{});r("[data-exclusions]").innerHTML=t.length?`<div class="billing-alert is-error"><strong>Itens ignorados</strong><ul>${t.map(([e,a])=>`<li>${s(e.replaceAll("_"," "))}: ${a}</li>`).join("")}</ul></div>`:"",r("[data-preview-summary]").innerHTML=`
            <article><span>Distribuições</span><strong>${l.summary.distributions}</strong></article>
            <article><span>Produtores</span><strong>${l.summary.producers}</strong></article>
            <article><span>Documentos de origem</span><strong>${l.summary.source_receipts}</strong></article>
        `,r("[data-financial-lines]").innerHTML=l.lines.map(e=>`
            <tr>
                <td>${s(e.product)}</td>
                <td>${s(e.quantity)}</td>
                <td>${s(e.unit)}</td>
                <td>${$(e.unit_price)}</td>
                <td>${$(e.document_amount)}</td>
            </tr>
        `).join("")||'<tr><td class="billing-empty-row" colspan="5">Sem linhas.</td></tr>',r("[data-financial-fees]").innerHTML=l.fees.length?`<div class="billing-fee-list">${l.fees.map(e=>`
                <div>
                    <span>${s(e.name||"Ajuste")} · ${s(e.nature==="accrual"?"Acréscimo":"Desconto")}</span>
                    <strong>${$(e.amount)}</strong>
                </div>
            `).join("")}</div>`:'<p class="billing-help">Nenhuma taxa, desconto ou acréscimo configurado.</p>',r("[data-financial-totals]").innerHTML=`
            <div><span>Bruto</span><strong>${$(l.totals.gross)}</strong></div>
            <div><span>Ajustes líquidos</span><strong>${$(l.totals.fees)}</strong></div>
            <div class="is-total"><span>Total a cobrar</span><strong>${$(l.totals.net)}</strong></div>
        `},oe=async()=>{const t=r("[data-save]");try{t.disabled=!0,t.setAttribute("aria-busy","true"),await H();const e={...C(),operation_key:K},a=await v(c.dataset.saveUrl,{method:c.dataset.saveMethod,body:JSON.stringify(e)});u(a.message||"Rascunho salvo."),a.redirect_url&&location.assign(a.redirect_url)}catch(e){u(e.message,!0)}finally{t.disabled=!1,t.removeAttribute("aria-busy")}},se=()=>{W(q)},re=async()=>{S(q);const t=r("[data-freeze]");try{t.disabled=!0,t.setAttribute("aria-busy","true"),u("Salvando, validando e emitindo o faturamento..."),await H();const e=await v(c.dataset.saveUrl,{method:c.dataset.saveMethod,body:JSON.stringify({...C(),operation_key:K,finalize:!0})});location.assign(e.redirect_url)}catch(e){u(e.message,!0),t.disabled=!1,t.removeAttribute("aria-busy")}},ie=async t=>{const e=r("[data-scan-feedback]");e&&(e.classList.remove("is-error"),e.textContent="Conferindo QR Code e selecionando as distribuições...");try{const n=(await v(c.dataset.selectUrl,{method:"POST",body:JSON.stringify({...w(),mode:"receipts",receipt_codes:[t],distribution_ids:[]})})).batches?.[0];if(!n?.receipt_found)throw new Error("Este QR Code não corresponde a um documento de origem desta organização.");const o=n.documents?.[0]||{};b.set(n.key,{key:n.key,display:n.label,number:o.number,associate:o.associate,distributions:o.distributions||[],ids:(n.selected_ids||[]).map(Number),excluded:Number(n.excluded_count||0)});const i=r("[data-receipt-codes]").value.split(/\n+/).map(E=>E.trim()).filter(Boolean);i.includes(t)||i.push(t),r("[data-receipt-codes]").value=i.join(`
`),I(),e&&(e.textContent=`Lote adicionado: ${n.selected_count} distribuição(ões). Continue apontando para outros comprovantes.`),window.document.dispatchEvent(new CustomEvent("accounting:qr-success",{detail:{number:o.number||n.label,associate:o.associate||"",count:Number(n.selected_count||0)}}))}catch(a){e?(e.textContent=a.message,e.classList.add("is-error")):u(a.message,!0)}},de=async()=>{try{const t=await v(c.dataset.contextUrl,{method:"GET",headers:{"Content-Type":"application/json"}});if(T=t.projects||[],D.innerHTML=T.map(e=>`
                <option value="${e.id}">${s(e.name)}${e.code?` · ${s(e.code)}`:""}</option>
            `).join(""),F(t,!1),d.issued_at.value=new Date().toISOString().slice(0,10),t.draft){const e=new Set((t.draft.project_ids||[]).map(Number));B(e);const a=t.draft.organization_id?"organization":"customer";d.querySelector(`[name="recipient_type"][value="${a}"]`).checked=!0,d.organization_id.value=t.draft.organization_id||"",d.customer_id.value=t.draft.customer_id||"",["issued_at","from_date","to_date","notes"].forEach(n=>{d[n].value=t.draft[n]||""}),f=t.draft.report_annotations||[],d.report_annotations_position.value=t.draft.report_annotations_position||"after",L(),(t.draft.distribution_ids||[]).forEach(n=>p.add(Number(n))),J(),y(),e.size&&(await G(),d.organization_id.value=t.draft.organization_id||"",d.customer_id.value=t.draft.customer_id||"")}else Q(),J(),y();U(),k(1)}catch(t){u(t.message,!0)}};c.addEventListener("click",async t=>{const e=t.target.closest("button");if(e){if(e.matches("[data-step-button]")){const a=Number(e.dataset.stepButton);a<=m&&k(a);return}if(e.matches("[data-next]")){try{w(),(m===2||m===3)&&await H(),k(m+1),u("")}catch(a){u(a.message,!0)}return}if(e.matches("[data-previous]")){k(m-1);return}if(e.matches("[data-open-project-picker]")){Y();return}if(e.matches("[data-close-project-picker], [data-cancel-project-picker]")){S(P);return}if(e.matches("[data-project-option]")){const a=Number(e.dataset.projectOption);g.has(a)?g.delete(a):g.add(a),M();return}if(e.matches("[data-clear-project-picker]")){g.clear(),M();return}if(e.matches("[data-apply-project-picker]")){await Z();return}if(e.matches("[data-remove-project]")){await ee(e.dataset.removeProject);return}if(e.matches("[data-mode]")){x=e.dataset.mode,h("[data-mode]").forEach(a=>a.classList.toggle("is-active",a===e)),h("[data-mode-panel]").forEach(a=>{a.hidden=a.dataset.modePanel!==x}),x==="manual"&&A();return}if(e.matches("[data-load-selection]")){ae();return}if(e.matches("[data-manual-load]")){A();return}if(e.matches("[data-manual-page]")){A(Number(e.dataset.manualPage));return}if(e.matches("[data-remove-id]")){p.delete(Number(e.dataset.removeId)),y();try{await H()}catch(a){k(2),u(a.message,!0)}return}if(e.matches("[data-add-report-annotation]")){if(j(),f.length>=30)return;f.push({target:"global",text:""}),L();return}if(e.matches("[data-import-delivery-notes]")){const a=new Set(j().map(o=>o.target)),n=(l?.distributions||[]).filter(o=>o.delivery_notes&&!a.has(`delivery-note:${o.id}`)).map(o=>({target:`delivery-note:${o.id}`,text:o.delivery_notes}));if(!n.length){u("Não há novas observações de entregas na seleção atual.",!0);return}f=[...j(),...n].slice(0,30),r("[data-report-annotations]").replaceChildren(),L(),u(`${n.length} observação(ões) de entrega importada(s).`);return}if(e.matches("[data-remove-report-annotation]")){j(),f.splice(Number(e.dataset.removeReportAnnotation),1),r("[data-report-annotations]").replaceChildren(),L();return}if(e.matches("[data-remove-batch]")){b.delete(e.dataset.removeBatch),I(),r("[data-receipt-codes]").value=[...b.values()].map(a=>a.display).join(`
`);return}if(e.matches("[data-save]")){oe();return}if(e.matches("[data-freeze]")){se();return}if(e.matches("[data-cancel-freeze]")){S(q);return}e.matches("[data-confirm-freeze]")&&re()}}),c.addEventListener("change",t=>{const e=t.target.closest("[data-annotation-target]");if(!e||!e.value.startsWith("delivery-note:"))return;const a=Number(e.value.split(":")[1]),n=(l?.distributions||[]).find(i=>Number(i.id)===a),o=e.closest("[data-report-annotation]")?.querySelector("[data-annotation-text]");o&&n?.delivery_notes&&(o.value=n.delivery_notes)}),c.addEventListener("input",t=>{t.target.matches("[data-project-search]")&&M()}),c.addEventListener("keydown",t=>{t.target.matches("[data-manual-search]")&&t.key==="Enter"&&(t.preventDefault(),A())}),c.addEventListener("accounting:qr-code",async t=>{await ie(t.detail.code),t.detail.onComplete?.()}),c.addEventListener("accounting:qr-native-config",t=>{try{t.detail.verificationUrl=c.dataset.selectUrl,t.detail.csrfToken=V,t.detail.selectionPayload={...w(),mode:"receipts",receipt_codes:[],distribution_ids:[]}}catch(e){t.detail.error=e}}),c.addEventListener("change",t=>{if(t.target.matches('[name="recipient_type"]')&&(J(),R()),t.target.matches('[name="organization_id"], [name="customer_id"], [name="from_date"], [name="to_date"]')&&R(),t.target.matches("[data-pick]")){const e=Number(t.target.dataset.pick);t.target.checked?p.add(e):p.delete(e),y()}}),[P,q].forEach(t=>{t&&(t.addEventListener("cancel",e=>{e.preventDefault(),S(t)}),t.addEventListener("click",e=>{e.target===t&&S(t)}))}),window.addEventListener("popstate",()=>{N?.open&&(z=!1,N.close(),N=null)}),de()})();
