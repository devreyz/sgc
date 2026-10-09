(()=>{const d=document.querySelector("[data-billing-editor]");if(!d)return;const c=d.querySelector("[data-billing-form]"),V=document.querySelector('meta[name="csrf-token"]')?.content||"",u=new Set,f=new Map,g=new Set;let L=[],m=1,E="period",l=null,b=[],O=0,z=null,N=!1;const K=crypto.randomUUID(),s=t=>d.querySelector(t),h=t=>[...d.querySelectorAll(t)],D=s("[data-project-select]"),k=s("[data-project-dialog]"),T=s("[data-freeze-dialog]"),n=t=>String(t??"").replace(/[&<>'"]/g,e=>({"&":"&amp;","<":"&lt;",">":"&gt;","'":"&#39;",'"':"&quot;"})[e]),$=t=>Number(t||0).toLocaleString("pt-BR",{style:"currency",currency:"BRL"}),p=(t,e=!1)=>{const a=s("[data-editor-message]");a.hidden=!t,a.textContent=t||"",a.classList.toggle("is-error",e),t&&a.scrollIntoView({behavior:"smooth",block:"nearest"})},v=async(t,e={})=>{const a=await fetch(t,{credentials:"same-origin",headers:{Accept:"application/json","Content-Type":"application/json","X-CSRF-TOKEN":V,...e.headers||{}},...e}),o=await a.json().catch(()=>({}));if(!a.ok){const r=Object.values(o.errors||{}).flat().join(" ");throw new Error(r||o.message||"Não foi possível concluir esta operação.")}return o},_=()=>[...D.selectedOptions].map(t=>Number(t.value)).filter(Boolean),P=()=>{const t=h("[data-report-annotation]").map(e=>({target:e.querySelector("[data-annotation-target]").value,text:e.querySelector("[data-annotation-text]").value.trim()}));return t.length&&(b=t),b},q=()=>{const t=h("[data-report-annotation]").length?P():b,e=new Map;(l?.distributions||[]).forEach(o=>{o.product_id&&e.set(Number(o.product_id),o.product)});const a=[["global","Observação geral"],...[...e].map(([o,r])=>[`product:${o}`,`Produto: ${r}`]),...(l?.distributions||[]).map(o=>[`distribution:${o.id}`,`Distribuição #${o.id} · ${o.product} · ${o.date}`])];t.forEach(o=>{o.target&&!a.some(([r])=>r===o.target)&&a.push([o.target,"Referência selecionada (fora da seleção atual)"])}),s("[data-report-annotations]").innerHTML=t.map((o,r)=>`
            <div data-report-annotation class="billing-fields-3" style="margin:.6rem 0;padding:.75rem;border:1px solid #dce7e0;border-radius:10px">
                <label class="billing-field"><span>Aplicar a</span><select class="billing-select" data-annotation-target>
                    ${a.map(([i,S])=>`<option value="${n(i)}" ${i===o.target?"selected":""}>${n(S)}</option>`).join("")}
                </select></label>
                <label class="billing-field" style="grid-column:span 2"><span>Observação</span><textarea class="billing-textarea" data-annotation-text maxlength="1000" rows="2">${n(o.text)}</textarea></label>
                <button class="billing-btn" type="button" data-remove-report-annotation="${r}">Remover</button>
            </div>
        `).join(""),b=t},C=()=>{const t=c.querySelector('[name="recipient_type"]:checked')?.value;return{project_ids:_(),customer_id:t==="customer"&&Number(c.customer_id.value)||null,organization_id:t==="organization"&&Number(c.organization_id.value)||null,issued_at:c.issued_at.value,from_date:c.from_date.value||null,to_date:c.to_date.value||null,notes:c.notes.value||null,report_annotations_position:c.report_annotations_position.value,report_annotations:P().filter(e=>e.text),distribution_ids:[...u]}},j=()=>{const t=C();if(!t.project_ids.length)throw new Error("Selecione ao menos um projeto.");if(!t.customer_id&&!t.organization_id)throw new Error("Selecione quem será cobrado.");if(!t.issued_at)throw new Error("Informe a data de emissão.");if(t.from_date&&t.to_date&&t.to_date<t.from_date)throw new Error("A data final não pode ser anterior à inicial.");return t},x=t=>{m=Math.max(1,Math.min(4,Number(t)||1)),h("[data-step]").forEach(e=>{e.hidden=Number(e.dataset.step)!==m}),h("[data-step-button]").forEach(e=>{const a=Number(e.dataset.stepButton);e.classList.toggle("is-active",a===m),e.classList.toggle("is-done",a<m),e.setAttribute("aria-current",a===m?"step":"false")}),s("[data-previous]").hidden=m===1,s("[data-next]").hidden=m===4,s("[data-save]").hidden=m<3,s("[data-freeze]")&&(s("[data-freeze]").hidden=m!==4),d.scrollIntoView({behavior:"smooth",block:"start"})},y=()=>{const t=u.size===1?"1 selecionada":`${u.size} selecionadas`;h("[data-selected-count]").forEach(e=>{e.textContent=t})},Q=()=>{const t=s("[data-project-summary]"),e=new Set(_()),a=L.filter(o=>e.has(Number(o.id)));if(t.classList.toggle("is-scrollable",a.length>4),!a.length){t.innerHTML='<div class="billing-project-empty">Nenhum projeto selecionado. Escolha o projeto antes de definir o destinatário.</div>';return}t.innerHTML=a.map(o=>`
            <div class="billing-project-selected">
                <div>
                    <strong>${n(o.name)}</strong>
                    <small>${o.code?n(o.code):"Projeto selecionado"}</small>
                </div>
                <button
                    class="billing-btn"
                    type="button"
                    data-remove-project="${Number(o.id)}"
                    aria-label="Remover ${n(o.name)}"
                    title="Remover projeto"
                >
                    <i class="ph ph-x" aria-hidden="true"></i>
                </button>
            </div>
        `).join("")},M=()=>{const t=s("[data-project-options]"),e=(s("[data-project-search]")?.value||"").trim().toLocaleLowerCase("pt-BR"),a=L.filter(i=>e?`${i.name||""} ${i.code||""}`.toLocaleLowerCase("pt-BR").includes(e):!0);t.innerHTML=a.length?a.map(i=>{const S=Number(i.id),W=g.has(S);return`
                    <button
                        class="billing-project-row${W?" is-selected":""}"
                        type="button"
                        data-project-option="${S}"
                        aria-pressed="${W?"true":"false"}"
                    >
                        <span class="billing-project-check" aria-hidden="true"><i class="ph-fill ph-check"></i></span>
                        <span>
                            <strong>${n(i.name)}</strong>
                            <small>${i.code?"Código do projeto":"Disponível para faturamento"}</small>
                        </span>
                        ${i.code?`<span class="billing-project-code">${n(i.code)}</span>`:""}
                    </button>
                `}).join(""):'<div class="billing-project-empty">Nenhum projeto encontrado para esta busca.</div>';const o=s("[data-project-dialog-count]");o&&(o.textContent=g.size===1?"1 selecionado":`${g.size} selecionados`);const r=s("[data-apply-project-picker]");r&&(r.disabled=g.size===0)},B=t=>{const e=new Set([...t].map(Number));[...D.options].forEach(a=>{a.selected=e.has(Number(a.value))}),Q()},R=()=>{u.clear(),f.clear(),l=null,y(),U()},X=t=>{!t||t.open||(z=t,t.showModal(),N||(history.pushState({billingDialog:!0},""),N=!0))},w=(t,e=!0)=>{t?.open&&(t.close(),z=null,e&&N&&(N=!1,history.back()))},Y=()=>{g.clear(),_().forEach(e=>g.add(e));const t=s("[data-project-search]");t&&(t.value=""),M(),X(k)},Z=async()=>{if(!g.size)return;const t=_().sort((a,o)=>a-o).join(","),e=[...g].sort((a,o)=>a-o).join(",");B(g),w(k),t!==e&&(R(),p(""),await G())},ee=async t=>{const e=new Set(_());e.delete(Number(t)),B(e),R(),p(""),await G()},U=()=>{const t=s("[data-scanned-batches]");t.innerHTML=f.size?[...f.values()].map(a=>`
                <article class="billing-batch-card">
                    <div class="billing-batch-main">
                        <strong>${n(a.number||a.display)}</strong>
                        <b>${n(a.associate||"Associado não identificado")}</b>
                        <span>${a.ids.length} distribuição(ões) selecionada(s)${a.excluded?` · ${a.excluded} ignorada(s)`:""}</span>
                        ${(a.distributions||[]).length?`
                            <details>
                                <summary>Ver distribuições</summary>
                                <ul>${a.distributions.map(o=>`
                                    <li>#${o.id} · ${n(o.product)} · ${n(o.quantity)} ${n(o.unit)}${o.date?` · ${n(o.date)}`:""}</li>
                                `).join("")}</ul>
                            </details>
                        `:""}
                    </div>
                    <button class="billing-btn billing-btn-danger" type="button" data-remove-batch="${n(a.key)}">
                        <i class="ph ph-trash" aria-hidden="true"></i>
                        Remover
                    </button>
                </article>
            `).join(""):'<p class="billing-help">Nenhum lote escaneado.</p>';const e=s("[data-scanner-batch-preview]");if(e){const a=[...f.values()];e.innerHTML=f.size?`${a.length>3?`<small class="acc-scan-preview-total">${a.length} comprovantes selecionados · exibindo os 3 mais recentes</small>`:""}${a.slice(-3).map(o=>`
                    <div class="acc-scan-preview-item">
                        <strong>${n(o.number||o.display)}</strong>
                        <span>${n(o.associate||"")}</span>
                        <small>${o.ids.length} distribuição(ões)</small>
                    </div>
                `).join("")}`:'<p class="billing-help">Comprovantes e distribuições aparecerão aqui durante a leitura.</p>'}},I=()=>{u.clear(),f.forEach(t=>t.ids.forEach(e=>u.add(Number(e)))),y(),U()},J=()=>{const t=c.querySelector('[name="recipient_type"]:checked')?.value;h("[data-recipient-field]").forEach(e=>{e.hidden=e.dataset.recipientField!==t})},F=(t,e=!0)=>{const a=e?c.customer_id.value:"",o=e?c.organization_id.value:"";c.customer_id.innerHTML='<option value="">Selecione um cliente</option>'+(t.customers||[]).map(r=>`<option value="${r.id}">${n(r.name)}</option>`).join(""),c.organization_id.innerHTML='<option value="">Selecione uma organização</option>'+(t.organizations||[]).map(r=>`<option value="${r.id}">${n(r.name)}</option>`).join(""),[...c.customer_id.options].some(r=>r.value===a)&&(c.customer_id.value=a),[...c.organization_id.options].some(r=>r.value===o)&&(c.organization_id.value=o)},G=async()=>{const t=++O,e=new URLSearchParams;if(_().forEach(a=>e.append("project_ids[]",a)),!e.has("project_ids[]")){F({customers:[],organizations:[]},!1);return}try{const a=await v(`${d.dataset.contextUrl}?${e}`,{method:"GET",headers:{"Content-Type":"application/json"}});if(t!==O)return;F(a),!(a.customers||[]).length&&!(a.organizations||[]).length&&p("Os projetos selecionados não possuem distribuições elegíveis para faturamento.",!0)}catch(a){t===O&&p(a.message,!0)}},te=()=>({...j(),mode:E,receipt_codes:s("[data-receipt-codes]").value.split(/[\n,;]+/).map(t=>t.trim()).filter(Boolean),distribution_ids:[...u]}),ae=async()=>{p("");try{const t=await v(d.dataset.selectUrl,{method:"POST",body:JSON.stringify(te())});E==="receipts"?(f.clear(),(t.batches||[]).forEach(a=>{const o=a.documents?.[0]||{};f.set(a.key,{key:a.key,display:a.label,number:o.number,associate:o.associate,distributions:o.distributions||[],ids:(a.selected_ids||[]).map(Number),excluded:Number(a.excluded_count||0)})}),I()):(u.clear(),(t.selected_ids||[]).forEach(a=>u.add(Number(a))),y());const e=t.excluded_count?` ${t.excluded_count} item(ns) incompatível(is) foram ignorados.`:"";p(`${u.size} distribuição(ões) selecionada(s).${e}`,t.excluded_count>0)}catch(t){p(t.message,!0)}},A=async(t=1)=>{try{const e=new URLSearchParams,a=j();a.project_ids.forEach(i=>e.append("project_ids[]",i)),["customer_id","organization_id","from_date","to_date"].forEach(i=>{a[i]&&e.set(i,a[i])}),e.set("page",t),e.set("search",s("[data-manual-search]").value||"");const r=(await v(`${d.dataset.manualUrl}?${e}`,{method:"GET",headers:{"Content-Type":"application/json"}})).distributions;s("[data-manual-rows]").innerHTML=(r.data||[]).map(i=>`
                <tr>
                    <td><input type="checkbox" data-pick="${i.id}" ${u.has(Number(i.id))?"checked":""} aria-label="Selecionar distribuição ${i.id}"></td>
                    <td>${n(i.date)}</td>
                    <td>${n(i.producer)}</td>
                    <td>${n(i.product)}</td>
                    <td>${n(i.quantity)} ${n(i.unit)}</td>
                    <td>${n(i.recipient)}</td>
                </tr>
            `).join("")||'<tr><td class="billing-empty-row" colspan="6">Nenhuma distribuição elegível.</td></tr>',s("[data-manual-pagination]").innerHTML=`
                <span>Página ${r.current_page} de ${r.last_page} · ${r.total} item(ns)</span>
                <div class="billing-pagination-actions">
                    <button class="billing-btn" type="button" data-manual-page="${r.current_page-1}" ${r.current_page<=1?"disabled":""}>Anterior</button>
                    <button class="billing-btn" type="button" data-manual-page="${r.current_page+1}" ${r.current_page>=r.last_page?"disabled":""}>Próxima</button>
                </div>
            `}catch(e){p(e.message,!0)}},H=async()=>{if(j(),!u.size)throw new Error("Selecione ao menos uma distribuição antes de continuar.");return l=await v(d.dataset.previewUrl,{method:"POST",body:JSON.stringify(C())}),u.clear(),(l.selected_ids||[]).forEach(t=>u.add(Number(t))),y(),oe(),l},oe=()=>{s("[data-review-count]").textContent=`${l.summary.distributions} distribuição(ões) · ${l.summary.producers} produtor(es)`,s("[data-review-rows]").innerHTML=l.distributions.map(e=>`
            <tr>
                <td>${n(e.date)}</td>
                <td>${n(e.producer)}</td>
                <td>${n(e.product)}</td>
                <td>${n(e.quantity)} ${n(e.unit)}</td>
                <td>${n(e.recipient)}</td>
                <td>
                    <button class="billing-link-btn" type="button" data-remove-id="${e.id}" aria-label="Remover distribuição ${e.id}" title="Remover">
                        <i class="ph ph-x" aria-hidden="true"></i>
                    </button>
                </td>
            </tr>
        `).join(""),q();const t=Object.entries(l.exclusion_reasons||{});s("[data-exclusions]").innerHTML=t.length?`<div class="billing-alert is-error"><strong>Itens ignorados</strong><ul>${t.map(([e,a])=>`<li>${n(e.replaceAll("_"," "))}: ${a}</li>`).join("")}</ul></div>`:"",s("[data-preview-summary]").innerHTML=`
            <article><span>Distribuições</span><strong>${l.summary.distributions}</strong></article>
            <article><span>Produtores</span><strong>${l.summary.producers}</strong></article>
            <article><span>Documentos de origem</span><strong>${l.summary.source_receipts}</strong></article>
        `,s("[data-financial-lines]").innerHTML=l.lines.map(e=>`
            <tr>
                <td>${n(e.product)}</td>
                <td>${n(e.quantity)}</td>
                <td>${n(e.unit)}</td>
                <td>${$(e.unit_price)}</td>
                <td>${$(e.document_amount)}</td>
            </tr>
        `).join("")||'<tr><td class="billing-empty-row" colspan="5">Sem linhas.</td></tr>',s("[data-financial-fees]").innerHTML=l.fees.length?`<div class="billing-fee-list">${l.fees.map(e=>`
                <div>
                    <span>${n(e.name||"Ajuste")} · ${n(e.nature==="accrual"?"Acréscimo":"Desconto")}</span>
                    <strong>${$(e.amount)}</strong>
                </div>
            `).join("")}</div>`:'<p class="billing-help">Nenhuma taxa, desconto ou acréscimo configurado.</p>',s("[data-financial-totals]").innerHTML=`
            <div><span>Bruto</span><strong>${$(l.totals.gross)}</strong></div>
            <div><span>Ajustes líquidos</span><strong>${$(l.totals.fees)}</strong></div>
            <div class="is-total"><span>Total a cobrar</span><strong>${$(l.totals.net)}</strong></div>
        `},ne=async()=>{const t=s("[data-save]");try{t.disabled=!0,t.setAttribute("aria-busy","true"),await H();const e={...C(),operation_key:K},a=await v(d.dataset.saveUrl,{method:d.dataset.saveMethod,body:JSON.stringify(e)});p(a.message||"Rascunho salvo."),a.redirect_url&&location.assign(a.redirect_url)}catch(e){p(e.message,!0)}finally{t.disabled=!1,t.removeAttribute("aria-busy")}},se=()=>{X(T)},re=async()=>{w(T);const t=s("[data-freeze]");try{t.disabled=!0,t.setAttribute("aria-busy","true"),p("Salvando, validando e emitindo o faturamento..."),await H();const e=await v(d.dataset.saveUrl,{method:d.dataset.saveMethod,body:JSON.stringify({...C(),operation_key:K,finalize:!0})});location.assign(e.redirect_url)}catch(e){p(e.message,!0),t.disabled=!1,t.removeAttribute("aria-busy")}},ie=async t=>{const e=s("[data-scan-feedback]");e&&(e.classList.remove("is-error"),e.textContent="Conferindo QR Code e selecionando as distribuições...");try{const o=(await v(d.dataset.selectUrl,{method:"POST",body:JSON.stringify({...j(),mode:"receipts",receipt_codes:[t],distribution_ids:[]})})).batches?.[0];if(!o?.receipt_found)throw new Error("Este QR Code não corresponde a um documento de origem desta organização.");const r=o.documents?.[0]||{};f.set(o.key,{key:o.key,display:o.label,number:r.number,associate:r.associate,distributions:r.distributions||[],ids:(o.selected_ids||[]).map(Number),excluded:Number(o.excluded_count||0)});const i=s("[data-receipt-codes]").value.split(/\n+/).map(S=>S.trim()).filter(Boolean);i.includes(t)||i.push(t),s("[data-receipt-codes]").value=i.join(`
`),I(),e&&(e.textContent=`Lote adicionado: ${o.selected_count} distribuição(ões). Continue apontando para outros comprovantes.`),window.document.dispatchEvent(new CustomEvent("accounting:qr-success",{detail:{number:r.number||o.label,associate:r.associate||"",count:Number(o.selected_count||0)}}))}catch(a){e?(e.textContent=a.message,e.classList.add("is-error")):p(a.message,!0)}},ce=async()=>{try{const t=await v(d.dataset.contextUrl,{method:"GET",headers:{"Content-Type":"application/json"}});if(L=t.projects||[],D.innerHTML=L.map(e=>`
                <option value="${e.id}">${n(e.name)}${e.code?` · ${n(e.code)}`:""}</option>
            `).join(""),F(t,!1),c.issued_at.value=new Date().toISOString().slice(0,10),t.draft){const e=new Set((t.draft.project_ids||[]).map(Number));B(e);const a=t.draft.organization_id?"organization":"customer";c.querySelector(`[name="recipient_type"][value="${a}"]`).checked=!0,c.organization_id.value=t.draft.organization_id||"",c.customer_id.value=t.draft.customer_id||"",["issued_at","from_date","to_date","notes"].forEach(o=>{c[o].value=t.draft[o]||""}),b=t.draft.report_annotations||[],c.report_annotations_position.value=t.draft.report_annotations_position||"after",q(),(t.draft.distribution_ids||[]).forEach(o=>u.add(Number(o))),J(),y(),e.size&&(await G(),c.organization_id.value=t.draft.organization_id||"",c.customer_id.value=t.draft.customer_id||"")}else Q(),J(),y();U(),x(1)}catch(t){p(t.message,!0)}};d.addEventListener("click",async t=>{const e=t.target.closest("button");if(e){if(e.matches("[data-step-button]")){const a=Number(e.dataset.stepButton);a<=m&&x(a);return}if(e.matches("[data-next]")){try{j(),(m===2||m===3)&&await H(),x(m+1),p("")}catch(a){p(a.message,!0)}return}if(e.matches("[data-previous]")){x(m-1);return}if(e.matches("[data-open-project-picker]")){Y();return}if(e.matches("[data-close-project-picker], [data-cancel-project-picker]")){w(k);return}if(e.matches("[data-project-option]")){const a=Number(e.dataset.projectOption);g.has(a)?g.delete(a):g.add(a),M();return}if(e.matches("[data-clear-project-picker]")){g.clear(),M();return}if(e.matches("[data-apply-project-picker]")){await Z();return}if(e.matches("[data-remove-project]")){await ee(e.dataset.removeProject);return}if(e.matches("[data-mode]")){E=e.dataset.mode,h("[data-mode]").forEach(a=>a.classList.toggle("is-active",a===e)),h("[data-mode-panel]").forEach(a=>{a.hidden=a.dataset.modePanel!==E}),E==="manual"&&A();return}if(e.matches("[data-load-selection]")){ae();return}if(e.matches("[data-manual-load]")){A();return}if(e.matches("[data-manual-page]")){A(Number(e.dataset.manualPage));return}if(e.matches("[data-remove-id]")){u.delete(Number(e.dataset.removeId)),y();try{await H()}catch(a){x(2),p(a.message,!0)}return}if(e.matches("[data-add-report-annotation]")){if(P(),b.length>=30)return;b.push({target:"global",text:""}),q();return}if(e.matches("[data-remove-report-annotation]")){P(),b.splice(Number(e.dataset.removeReportAnnotation),1),s("[data-report-annotations]").replaceChildren(),q();return}if(e.matches("[data-remove-batch]")){f.delete(e.dataset.removeBatch),I(),s("[data-receipt-codes]").value=[...f.values()].map(a=>a.display).join(`
`);return}if(e.matches("[data-save]")){ne();return}if(e.matches("[data-freeze]")){se();return}if(e.matches("[data-cancel-freeze]")){w(T);return}e.matches("[data-confirm-freeze]")&&re()}}),d.addEventListener("input",t=>{t.target.matches("[data-project-search]")&&M()}),d.addEventListener("keydown",t=>{t.target.matches("[data-manual-search]")&&t.key==="Enter"&&(t.preventDefault(),A())}),d.addEventListener("accounting:qr-code",async t=>{await ie(t.detail.code),t.detail.onComplete?.()}),d.addEventListener("accounting:qr-native-config",t=>{try{t.detail.verificationUrl=d.dataset.selectUrl,t.detail.csrfToken=V,t.detail.selectionPayload={...j(),mode:"receipts",receipt_codes:[],distribution_ids:[]}}catch(e){t.detail.error=e}}),d.addEventListener("change",t=>{if(t.target.matches('[name="recipient_type"]')&&(J(),R()),t.target.matches('[name="organization_id"], [name="customer_id"], [name="from_date"], [name="to_date"]')&&R(),t.target.matches("[data-pick]")){const e=Number(t.target.dataset.pick);t.target.checked?u.add(e):u.delete(e),y()}}),[k,T].forEach(t=>{t&&(t.addEventListener("cancel",e=>{e.preventDefault(),w(t)}),t.addEventListener("click",e=>{e.target===t&&w(t)}))}),window.addEventListener("popstate",()=>{z?.open&&(N=!1,z.close(),z=null)}),ce()})();
