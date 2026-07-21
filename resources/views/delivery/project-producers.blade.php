@extends('layouts.bento')

@section('title', 'Produtores do Projeto')
@section('page-title', 'Produtores do Projeto')
@section('user-role', 'Registrador')

@php($bentoNavigation = \App\Support\PortalNavigation::make('delivery', 'projects', $tenant->slug ?? request()->route('tenant')))

@section('content')
<style>
.pp-header{background:var(--color-surface);border:1px solid var(--color-border);border-radius:var(--radius-lg);padding:1.1rem 1.25rem;margin-bottom:1rem;display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap}
.pp-title{font-size:1.15rem;font-weight:800;margin:0 0 .25rem;display:flex;align-items:center;gap:.45rem}
.pp-meta{font-size:.82rem;color:var(--color-text-secondary)}
.pp-actions,.pp-filters,.pp-pager{display:flex;gap:.5rem;flex-wrap:wrap;align-items:center}
.btn{display:inline-flex;align-items:center;gap:.3rem;padding:.42rem .78rem;border-radius:var(--radius-md);border:1px solid transparent;cursor:pointer;font-size:.78rem;font-weight:700;text-decoration:none;transition:.15s;white-space:nowrap}
.btn:disabled{opacity:.55;cursor:not-allowed;transform:none}
.btn:hover:not(:disabled){transform:translateY(-1px)}
.btn-primary{background:var(--color-primary);color:#fff}
.btn-success{background:var(--color-success);color:#fff}
.btn-danger{background:var(--color-danger);color:#fff}
.btn-ghost{background:transparent;color:var(--color-text-secondary);border-color:var(--color-border)}
.btn-ghost:hover:not(:disabled){background:var(--color-bg);color:var(--color-text)}
.btn-sm{padding:.3rem .58rem;font-size:.72rem}
.pp-summary{display:grid;grid-template-columns:repeat(6,minmax(110px,1fr));gap:.65rem;margin-bottom:1rem}
.pp-stat{background:var(--color-surface);border:1px solid var(--color-border);border-radius:8px;padding:.75rem .85rem;text-align:left;cursor:pointer}
.pp-stat.active{border-color:var(--color-primary);box-shadow:0 0 0 2px color-mix(in srgb,var(--color-primary) 18%,transparent)}
.pp-stat-value{font-size:1.3rem;font-weight:800;line-height:1}
.pp-stat-label{font-size:.72rem;color:var(--color-text-secondary);margin-top:.25rem}
.pp-card{background:var(--color-surface);border:1px solid var(--color-border);border-radius:var(--radius-lg);overflow:hidden}
.pp-toolbar{padding:.8rem;border-bottom:1px solid var(--color-border);display:flex;justify-content:space-between;gap:.75rem;flex-wrap:wrap}
.pp-search{min-width:240px;max-width:360px;flex:1;border:1px solid var(--color-border);border-radius:var(--radius-md);background:var(--color-bg);color:var(--color-text);padding:.48rem .65rem;font-size:.84rem}
.pp-table{width:100%;border-collapse:collapse;font-size:.86rem}
.pp-table th{background:var(--color-primary);color:#fff;padding:.6rem .75rem;text-align:left;font-size:.7rem;text-transform:uppercase;font-weight:700;white-space:nowrap}
.pp-table th.r,.pp-table td.r{text-align:right}
.pp-table td{padding:.6rem .75rem;border-bottom:1px solid var(--color-border);vertical-align:middle}
.pp-table tr:hover td{background:rgba(0,0,0,.03)}
.pp-empty{padding:2.4rem;text-align:center;color:var(--color-text-secondary)}
.pp-badge{display:inline-flex;align-items:center;padding:2px 8px;border-radius:999px;font-size:.68rem;font-weight:800}
.pp-badge.gray{background:#f3f4f6;color:#374151}
.pp-badge.yellow{background:#fef9c3;color:#78350f}
.pp-badge.red{background:#fee2e2;color:#991b1b}
.pp-badge.green{background:#d1fae5;color:#065f46}
.pp-badge.blue{background:#dbeafe;color:#1e40af}
.pp-receipt-cell{display:flex;align-items:center;justify-content:flex-end;gap:.35rem;flex-wrap:wrap}
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);display:flex;align-items:center;justify-content:center;z-index:100000}
.modal-overlay.hidden,.hidden{display:none!important}
.modal-box{background:var(--color-surface);border-radius:var(--radius-lg);padding:1.35rem;width:min(640px,95vw);max-height:90vh;overflow-y:auto;box-shadow:0 8px 32px rgba(0,0,0,.22)}
.modal-title{font-size:1rem;font-weight:800;margin-bottom:.7rem;display:flex;align-items:center;gap:.4rem}
.rm-info{display:flex;align-items:flex-start;gap:.5rem;padding:.6rem .8rem;border-radius:8px;font-size:.82rem;margin-bottom:.6rem}
.rm-info-blue{background:#eff6ff;border:1px solid #93c5fd;color:#1e40af}
.rm-info-yellow{background:#fef9c3;border:1px solid #fde047;color:#78350f}
.rm-info-red{background:#fef2f2;border:1px solid #fca5a5;color:#991b1b}
.rm-info-green{background:#dcfce7;border:1px solid #86efac;color:#166534}
.rm-issues-list{display:flex;flex-direction:column;gap:.35rem;width:100%}
.rm-issue-item{border-top:1px solid rgba(153,27,27,.18);padding-top:.35rem;margin-top:.25rem}
.rm-issue-title{font-weight:800;font-size:.78rem}
.rm-issue-message{font-size:.75rem;line-height:1.35;margin-top:.1rem}
.rm-issue-action{font-size:.72rem;font-weight:700;margin-top:.18rem}
.rm-issue-actions{display:flex;gap:.35rem;flex-wrap:wrap;margin-top:.4rem}
.rm-receipt-item{display:flex;align-items:center;justify-content:space-between;gap:.65rem;padding:.55rem .7rem;background:var(--color-bg);border:1px solid var(--color-border);border-radius:8px;font-size:.82rem}
.rm-col-selector{border:1px solid var(--color-border);border-radius:8px;padding:.6rem .8rem;margin-bottom:.75rem;background:var(--color-bg)}
.rm-col-title{font-size:.7rem;font-weight:800;text-transform:uppercase;color:var(--color-text-secondary);margin-bottom:.45rem}
.rm-col-grid{display:grid;grid-template-columns:1fr 1fr;gap:.25rem .75rem}
.rm-col-lbl{display:flex;align-items:center;gap:.4rem;font-size:.8rem;cursor:pointer}
.rm-footer{display:flex;gap:.5rem;justify-content:flex-end;border-top:1px solid var(--color-border);padding-top:.75rem}
.delivery-item{display:grid;grid-template-columns:auto 1fr 92px 82px 80px 82px;gap:.45rem;align-items:center;padding:.42rem .6rem;border-bottom:1px solid var(--color-border);font-size:.8rem}
.delivery-item:last-child{border-bottom:none}
@keyframes spin{to{transform:rotate(360deg)}}
@media(max-width:900px){.pp-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.pp-table{min-width:920px}.delivery-item{grid-template-columns:auto 1fr;align-items:start}.delivery-item span:nth-child(n+3){padding-left:1.6rem}}
@media print{.no-print,nav{display:none!important}.pp-table th{background:#1a3a5c!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}}
</style>

<div class="pp-header">
    <div>
        <h1 class="pp-title">
            <i data-lucide="users" style="width:20px;height:20px;color:var(--color-primary)"></i>
            {{ $project->title }}
        </h1>
        @if($project->contract_number)
            <div class="pp-meta">Contrato: {{ $project->contract_number }}</div>
        @endif
    </div>
    <div class="pp-actions no-print">
        <a href="{{ route('delivery.projects.associates.index', ['tenant' => $tenant->slug, 'project' => $project->id]) }}" class="btn btn-primary btn-sm">
            <i data-lucide="sliders-horizontal" style="width:13px;height:13px"></i> Participacao e limites
        </a>
        <a href="{{ route('delivery.projects.deliveries', ['tenant' => $tenant->slug, 'project' => $project->id]) }}" class="btn btn-ghost btn-sm">
            <i data-lucide="arrow-left" style="width:13px;height:13px"></i> Entregas
        </a>
        <button class="btn btn-primary btn-sm" onclick="window.print()">
            <i data-lucide="printer" style="width:13px;height:13px"></i> Imprimir
        </button>
    </div>
</div>

<div class="pp-summary no-print" id="pp-summary"></div>

<div class="pp-card">
    <div class="pp-toolbar no-print">
        <input id="pp-search" class="pp-search" type="search" placeholder="Buscar produtor, CPF/CNPJ ou matricula">
        <div class="pp-filters">
            <button class="btn btn-ghost btn-sm" onclick="setProducerFilter('all')" data-filter="all">Todos</button>
            <button class="btn btn-ghost btn-sm" onclick="setProducerFilter('pending')" data-filter="pending">Pendentes</button>
            <button class="btn btn-ghost btn-sm" onclick="setProducerFilter('obsolete')" data-filter="obsolete">Obsoletos</button>
            <button class="btn btn-ghost btn-sm" onclick="setProducerFilter('billed')" data-filter="billed">Faturados</button>
        </div>
    </div>
    <div style="overflow-x:auto;">
        <table class="pp-table">
            <thead>
                <tr>
                    <th>Produtor</th>
                    <th>CPF/CNPJ</th>
                    <th class="r">Distrib.</th>
                    <th class="r">Qtd.</th>
                    <th class="r">Bruto</th>
                    <th class="r">Liquido</th>
                    <th class="r">Pend.</th>
                    <th class="r no-print">Acoes</th>
                </tr>
            </thead>
            <tbody id="pp-producers-body">
                <tr><td colspan="8" class="pp-empty">Carregando produtores...</td></tr>
            </tbody>
        </table>
    </div>
    <div class="pp-toolbar no-print" style="border-top:1px solid var(--color-border);border-bottom:0">
        <span id="pp-page-info" class="pp-meta">-</span>
        <div class="pp-pager">
            <button class="btn btn-ghost btn-sm" id="pp-prev" onclick="changeProducerPage(-1)">Anterior</button>
            <button class="btn btn-ghost btn-sm" id="pp-next" onclick="changeProducerPage(1)">Proxima</button>
        </div>
    </div>
</div>

<div class="modal-overlay hidden" id="receipt-modal" role="dialog" aria-modal="true" aria-labelledby="rm-title">
    <div class="modal-box">
        <div class="modal-title" id="rm-title">
            <i data-lucide="file-down" style="width:16px;height:16px;color:var(--color-primary)"></i>
            Comprovante de Entrega
        </div>
        <div style="font-size:.82rem;color:var(--color-text-secondary);margin-bottom:1rem;">
            Produtor: <strong id="modal-assoc-name">-</strong>
        </div>

        <div id="rm-issues" class="rm-info rm-info-blue hidden"></div>
        <div id="rm-checking" style="padding:1.5rem 0;text-align:center;font-size:.85rem;color:var(--color-text-secondary);">
            <span style="animation:spin 1s linear infinite;display:inline-block;margin-right:.4rem">◌</span>
            Verificando comprovantes...
        </div>

        <div id="rm-no-receipt" class="hidden">
            <div class="rm-info rm-info-blue">
                <i data-lucide="info" style="width:15px;height:15px;flex-shrink:0"></i>
                <span>Nenhum comprovante gerado ainda. <strong id="rm-dist-count">0</strong> distribuicao(oes) aprovada(s) serao incluidas.</span>
            </div>
            <div class="rm-col-selector">
                <div class="rm-col-title">Colunas do comprovante</div>
                <div class="rm-col-grid">
                    <label class="rm-col-lbl"><input type="checkbox" class="rm-col-chk" value="unit_price" checked> Vlr. Unitario</label>
                    <label class="rm-col-lbl"><input type="checkbox" class="rm-col-chk" value="gross" checked> Vlr. Bruto</label>
                    <label class="rm-col-lbl"><input type="checkbox" class="rm-col-chk" value="admin_fee"> Taxa Adm.</label>
                    <label class="rm-col-lbl"><input type="checkbox" class="rm-col-chk" value="net"> Vlr. Liquido</label>
                </div>
            </div>
            <div class="rm-footer">
                <button class="btn btn-ghost btn-sm" onclick="closeReceiptModal()">Cancelar</button>
                <button class="btn btn-primary btn-sm" id="rm-btn-gen-all" onclick="generateAllDeliveries()">
                    <i data-lucide="file-down" style="width:13px;height:13px"></i> Gerar Comprovante
                </button>
            </div>
        </div>

        <div id="rm-has-receipt" class="hidden">
            <div class="rm-info rm-info-yellow">
                <i data-lucide="alert-triangle" style="width:15px;height:15px;flex-shrink:0"></i>
                <span>Ja existe(m) <strong id="rm-receipt-count">0</strong> comprovante(s) para este produtor neste projeto.</span>
            </div>
            <div id="rm-receipts-list" style="margin:.6rem 0;display:flex;flex-direction:column;gap:.4rem;"></div>
            <div id="rm-uncovered-warn" class="rm-info rm-info-red hidden">
                <i data-lucide="alert-circle" style="width:15px;height:15px;flex-shrink:0"></i>
                <span><strong id="rm-uncovered-count">0</strong> distribuicao(oes) ainda nao estao cobertas por nenhum comprovante.</span>
            </div>
            <div class="rm-footer" style="margin-top:.75rem;">
                <button class="btn btn-ghost btn-sm" onclick="closeReceiptModal()">Fechar</button>
                <button class="btn btn-primary btn-sm" id="rm-create-extra" onclick="enterSelectingMode()">
                    <i data-lucide="plus-circle" style="width:13px;height:13px"></i> Criar comprovante adicional
                </button>
            </div>
        </div>

        <div id="rm-selecting" class="hidden">
            <div class="rm-info rm-info-blue" style="margin-bottom:.75rem;">
                <i data-lucide="info" style="width:15px;height:15px;flex-shrink:0"></i>
                <span>Selecione as distribuicoes que farao parte deste comprovante adicional.</span>
            </div>
            <div id="rm-sel-area" style="min-height:60px;max-height:260px;overflow-y:auto;border:1px solid var(--color-border);border-radius:8px;margin-bottom:.6rem;">
                <p style="padding:.75rem;font-size:.8rem;color:var(--color-text-secondary)">Carregando distribuicoes...</p>
            </div>
            <div style="font-size:.8rem;color:var(--color-text-secondary);margin-bottom:.6rem;">
                <strong id="rm-sel-count">0</strong> selecionada(s) &middot;
                Liquido: <strong style="color:var(--color-success)">R$ <span id="rm-sel-total">0,00</span></strong>
            </div>
            <div class="rm-col-selector">
                <div class="rm-col-title">Colunas do comprovante</div>
                <div class="rm-col-grid">
                    <label class="rm-col-lbl"><input type="checkbox" class="rm-col-chk" value="unit_price" checked> Vlr. Unitario</label>
                    <label class="rm-col-lbl"><input type="checkbox" class="rm-col-chk" value="gross" checked> Vlr. Bruto</label>
                    <label class="rm-col-lbl"><input type="checkbox" class="rm-col-chk" value="admin_fee"> Taxa Adm.</label>
                    <label class="rm-col-lbl"><input type="checkbox" class="rm-col-chk" value="net"> Vlr. Liquido</label>
                </div>
            </div>
            <div class="rm-footer">
                <button class="btn btn-ghost btn-sm" onclick="showHasReceiptState()">Voltar</button>
                <button class="btn btn-primary btn-sm" id="rm-btn-gen-sel" onclick="generateSelectedReceipt()">
                    <i data-lucide="file-down" style="width:13px;height:13px"></i> Gerar PDF
                </button>
            </div>
        </div>
    </div>
</div>

<div id="pp-toasts" style="position:fixed;bottom:1.5rem;right:1.5rem;z-index:99999;display:flex;flex-direction:column;gap:.5rem;"></div>

<script>
const PP_TENANT = '{{ $tenant->slug }}';
const PP_CSRF = '{{ csrf_token() }}';
const PP_PROJECT = {{ $project->id }};
let PP_ASSOCIATE = null;
let PP_ASSOCIATE_NAME = '';
let PP_CHECK_DATA = null;
let PP_EDIT_RECEIPT_ID = null;
let PP_FILTER = 'all';
let PP_PAGE = 1;
let PP_LAST_PAGE = 1;
let PP_SEARCH_TIMER = null;

function brMoney(value){return Number(value || 0).toLocaleString('pt-BR',{style:'currency',currency:'BRL'});}
function brQty(value){return Number(value || 0).toLocaleString('pt-BR',{minimumFractionDigits:3,maximumFractionDigits:3});}
function escapeHtml(value){return String(value ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');}
function ppToast(msg,type='success'){const c=document.getElementById('pp-toasts');const el=document.createElement('div');el.style.cssText='background:var(--color-surface);border:1px solid var(--color-border);border-radius:6px;padding:.7rem 1rem;font-size:.85rem;box-shadow:0 4px 14px rgba(0,0,0,.14);min-width:240px;max-width:360px;border-left:3px solid '+(type==='success'?'var(--color-success)':'var(--color-danger)');el.textContent=msg;c.appendChild(el);setTimeout(()=>{el.style.opacity=0;setTimeout(()=>el.remove(),250)},4500);}
function setModalState(state){['checking','no-receipt','has-receipt','selecting'].forEach(s=>document.getElementById('rm-'+s).classList.add('hidden'));document.getElementById('rm-'+state).classList.remove('hidden');}
function statusBadge(status,label,locked=false){const color=status==='obsolete'?'red':status==='paid'?'green':status==='partially_paid'?'blue':locked?'yellow':status==='pending_payment'?'yellow':'gray';return `<span class="pp-badge ${color}">${escapeHtml(label || status || 'Rascunho')}</span>`;}

async function loadProducers(){
    const body=document.getElementById('pp-producers-body');
    body.innerHTML='<tr><td colspan="8" class="pp-empty">Carregando produtores...</td></tr>';
    const search=document.getElementById('pp-search').value || '';
    try{
        const params=new URLSearchParams({page:PP_PAGE,filter:PP_FILTER,search});
        const res=await fetch(`/${PP_TENANT}/delivery/projects/${PP_PROJECT}/producers-data?${params}`,{headers:{Accept:'application/json','X-CSRF-TOKEN':PP_CSRF}});
        const data=await res.json();
        if(!data.success){ppToast(data.message || 'Erro ao carregar produtores.','error');return;}
        renderSummary(data.summary || {});
        renderProducers(data.rows || []);
        PP_LAST_PAGE=data.pagination?.last_page || 1;
        PP_PAGE=data.pagination?.current_page || 1;
        document.getElementById('pp-page-info').textContent=`Pagina ${PP_PAGE} de ${PP_LAST_PAGE} · ${data.pagination?.total || 0} produtor(es)`;
        document.getElementById('pp-prev').disabled=PP_PAGE<=1;
        document.getElementById('pp-next').disabled=PP_PAGE>=PP_LAST_PAGE;
    }catch(error){body.innerHTML='<tr><td colspan="8" class="pp-empty">Erro ao carregar produtores.</td></tr>';ppToast('Erro de comunicacao ao carregar produtores.','error');}
    if(typeof lucide!=='undefined')lucide.createIcons();
}

function renderSummary(summary){
    const cards=[
        ['all','Produtores',summary.producers || 0],
        ['pending','Distrib. pendentes',summary.pending_distributions || 0],
        ['complement','A complementar',summary.needs_complement || 0],
        ['obsolete','Obsoletos',summary.obsolete_receipts || 0],
        ['billed','Faturados',summary.billed_receipts || 0],
        ['paid','Pagos/parciais',summary.paid_receipts || 0],
    ];
    document.getElementById('pp-summary').innerHTML=cards.map(([filter,label,value])=>`
        <button type="button" class="pp-stat ${PP_FILTER===filter?'active':''}" onclick="setProducerFilter('${filter}')">
            <div class="pp-stat-value">${value}</div>
            <div class="pp-stat-label">${label}</div>
        </button>`).join('');
    document.querySelectorAll('[data-filter]').forEach(btn=>btn.classList.toggle('btn-primary',btn.dataset.filter===PP_FILTER));
}

function renderProducers(rows){
    const body=document.getElementById('pp-producers-body');
    if(!rows.length){body.innerHTML='<tr><td colspan="8" class="pp-empty">Nenhum produtor encontrado para o filtro atual.</td></tr>';return;}
    body.innerHTML=rows.map(row=>{
        const receipt=row.latest_receipt;
        const receiptInfo=receipt
            ? `<div style="display:flex;flex-direction:column;gap:.25rem;align-items:flex-end">${statusBadge(receipt.status,receipt.status_label,receipt.is_locked)}<span class="pp-meta">${escapeHtml(receipt.number)} · ${escapeHtml(receipt.issued_at)}</span></div>`
            : '<span class="pp-badge gray">Sem comprovante</span>';
        const quickAction=receipt?.status==='obsolete' && !receipt.is_locked
            ? `<button class="btn btn-danger btn-sm" onclick="regenerateReceipt(${receipt.id})">Regenerar</button>`
            : `<button class="btn btn-success btn-sm" onclick="openReceiptModal(${row.associate_id}, '${escapeHtml(row.name).replace(/&#039;/g,'\\&#039;')}')">Comprovante</button>`;
        return `<tr>
            <td><strong>${escapeHtml(row.name)}</strong><div class="pp-meta">${row.receipt_count} comprovante(s)</div></td>
            <td style="font-family:monospace">${escapeHtml(row.cpf)}</td>
            <td class="r">${row.deliveries}</td>
            <td class="r">${brQty(row.quantity)}</td>
            <td class="r">${brMoney(row.gross_value)}</td>
            <td class="r" style="color:var(--color-success);font-weight:800">${brMoney(row.net_value)}</td>
            <td class="r">${row.pending_distributions ? `<span class="pp-badge red">${row.pending_distributions}</span>` : '<span class="pp-badge gray">0</span>'}</td>
            <td class="r no-print"><div class="pp-receipt-cell"><a class="btn btn-primary btn-sm" href="/${PP_TENANT}/delivery/projects/${PP_PROJECT}/associates/${row.associate_id}"><i data-lucide="sliders-horizontal" style="width:13px;height:13px"></i> Entregas e limites</a>${receiptInfo}${quickAction}</div></td>
        </tr>`;
    }).join('');
}

function setProducerFilter(filter){PP_FILTER=filter;PP_PAGE=1;loadProducers();}
function changeProducerPage(delta){PP_PAGE=Math.min(PP_LAST_PAGE,Math.max(1,PP_PAGE+delta));loadProducers();}
document.getElementById('pp-search').addEventListener('input',()=>{clearTimeout(PP_SEARCH_TIMER);PP_SEARCH_TIMER=setTimeout(()=>{PP_PAGE=1;loadProducers();},280);});

async function openReceiptModal(associateId,name){
    PP_ASSOCIATE=associateId;PP_ASSOCIATE_NAME=name;PP_CHECK_DATA=null;PP_EDIT_RECEIPT_ID=null;
    document.getElementById('modal-assoc-name').textContent=name;
    document.getElementById('receipt-modal').classList.remove('hidden');
    setModalState('checking');
    try{
        const res=await fetch(`/${PP_TENANT}/delivery/projects/${PP_PROJECT}/associates/${associateId}/receipt-check`,{headers:{Accept:'application/json','X-CSRF-TOKEN':PP_CSRF}});
        PP_CHECK_DATA=await res.json();
        document.getElementById('rm-dist-count').textContent=PP_CHECK_DATA.total_dist ?? 0;
        document.getElementById('rm-receipt-count').textContent=PP_CHECK_DATA.receipt_count ?? 0;
        renderIssues(PP_CHECK_DATA.issues || [], PP_CHECK_DATA.critical_issues || 0);
        if(!PP_CHECK_DATA.has_receipts){setModalState('no-receipt');return;}
        renderReceiptsList(PP_CHECK_DATA.receipts || []);
        const uncovCount=PP_CHECK_DATA.uncovered_count ?? 0;
        document.getElementById('rm-uncovered-count').textContent=uncovCount;
        document.getElementById('rm-uncovered-warn').classList.toggle('hidden',uncovCount===0);
        document.getElementById('rm-create-extra').style.display=uncovCount>0?'':'none';
        setModalState('has-receipt');
    }catch(error){ppToast('Erro ao verificar comprovantes.','error');closeReceiptModal();}
    if(typeof lucide!=='undefined')lucide.createIcons();
}

function renderIssues(issues,criticalIssues){
    const issuesEl=document.getElementById('rm-issues');
    if(criticalIssues<=0 && !issues.length){issuesEl.className='rm-info rm-info-green';issuesEl.innerHTML=`<i data-lucide="check-circle" style="width:15px;height:15px;flex-shrink:0"></i><div class="rm-issues-list"><span>Sem inconsistências</span></div>`;issuesEl.classList.remove('hidden');if(typeof lucide!=='undefined')lucide.createIcons();return;}
    const hasCritical=criticalIssues>0 || issues.some(issue=>issue.severity==='critical');
    const hasWarning=issues.some(issue=>issue.severity==='warning');
    issuesEl.className='rm-info ' + (hasCritical ? 'rm-info-red' : (hasWarning ? 'rm-info-yellow' : 'rm-info-blue'));
    const issueRows=issues.slice(0,8).map(issue=>`
        <div class="rm-issue-item">
            <div class="rm-issue-title">${issue.severity==='critical'?'Critico':issue.severity==='warning'?'Atencao':'Info'} · ${escapeHtml(issue.title || '')}</div>
            <div class="rm-issue-message">${escapeHtml(issue.message || '')}</div>
            <div class="rm-issue-action">${escapeHtml(issue.action || '')}</div>
            ${issue.actionKey?`<div class="rm-issue-actions"><button type="button" class="btn btn-ghost btn-sm" onclick="ppHandleIntegrityAction('${escapeHtml(issue.actionKey)}',${Number(issue.deliveryId || 0)},${Number(issue.distributionId || 0)})">${escapeHtml(ppIntegrityActionLabel(issue.actionKey))}</button></div>`:''}
        </div>`).join('');
    issuesEl.innerHTML=`<i data-lucide="${hasCritical ? 'alert-circle' : (hasWarning ? 'alert-triangle' : 'info')}" style="width:15px;height:15px;flex-shrink:0"></i><div class="rm-issues-list"><span>${criticalIssues} inconsistencia(s) critica(s) podem bloquear o comprovante.</span>${issueRows}</div>`;
    issuesEl.classList.remove('hidden');
    if(typeof lucide!=='undefined')lucide.createIcons();
}

function renderReceiptsList(receipts){
    const listEl=document.getElementById('rm-receipts-list');
    listEl.innerHTML=receipts.map(r=>{
        const obsolete=r.status==='obsolete';
        const actions=[];
        if(r.can_regenerate) actions.push(`<button type="button" class="btn btn-danger btn-sm" onclick="regenerateReceipt(${r.id})">Regenerar</button>`);
        if(r.can_update && (PP_CHECK_DATA.uncovered_count ?? 0)>0) actions.push(`<button type="button" class="btn btn-ghost btn-sm" onclick="enterSelectingMode(${r.id})">Adicionar pendentes</button>`);
        if(!obsolete) actions.push(`<a class="btn btn-ghost btn-sm" href="${r.reprint_url}" target="_blank">Reimprimir</a>`);
        const obsoleteNote=obsolete?`<div class="pp-meta" style="color:#991b1b">${escapeHtml(r.obsolete_reason || 'Precisa ser regenerado.')}${r.obsolete_at?' · '+escapeHtml(r.obsolete_at):''}</div>`:'';
        return `<div class="rm-receipt-item">
            <div style="display:flex;flex-direction:column;gap:.25rem">
                <div style="display:flex;align-items:center;gap:.4rem;flex-wrap:wrap"><strong>Nº ${escapeHtml(r.number)}</strong>${statusBadge(r.status,r.status_label)}${r.total_net?`<span class="pp-meta" style="color:#059669;font-weight:800">R$ ${escapeHtml(r.total_net)}</span>`:''}<span class="pp-meta">${escapeHtml(r.issued_at)}</span></div>
                ${obsoleteNote}
            </div>
            <div style="display:flex;gap:.35rem;flex-wrap:wrap;justify-content:flex-end">${actions.join('')}</div>
        </div>`;
    }).join('');
}

function closeReceiptModal(){document.getElementById('receipt-modal').classList.add('hidden');PP_ASSOCIATE=null;}
document.getElementById('receipt-modal')?.addEventListener('click',e=>{if(e.target===e.currentTarget)closeReceiptModal();});
function ppIntegrityActionLabel(action){return({open_distribution:'Abrir entrega',edit_distribution:'Corrigir distribuicao',detach_missing_associate_receipt:'Desvincular comprovante',delete_orphan_distribution:'Excluir distribuicao orfa',open_producers:'Atualizar comprovantes'})[action] || 'Ver correcao';}
async function ppHandleIntegrityAction(action,deliveryId,distributionId){
    if(action==='open_distribution'||action==='edit_distribution'){window.location.href=`/${PP_TENANT}/delivery/projects/${PP_PROJECT}/deliveries`;return;}
    if(action==='open_producers'){openReceiptModal(PP_ASSOCIATE,PP_ASSOCIATE_NAME);return;}
    const question=action==='detach_missing_associate_receipt'?'Desvincular este comprovante inexistente?':'Excluir esta distribuicao orfa?';
    if(!confirm(question))return;
    const res=await fetch(`/${PP_TENANT}/delivery/projects/${PP_PROJECT}/integrity/resolve`,{method:'POST',headers:{'X-CSRF-TOKEN':PP_CSRF,'Content-Type':'application/json',Accept:'application/json'},body:JSON.stringify({action,distribution_id:distributionId})});
    const data=await res.json();
    ppToast(data.message || (data.success?'Correcao aplicada.':'Nao foi possivel corrigir.'),data.success?'success':'error');
    if(data.success)openReceiptModal(PP_ASSOCIATE,PP_ASSOCIATE_NAME);
}

async function generateAllDeliveries(){
    if(!PP_ASSOCIATE)return;
    const btn=document.getElementById('rm-btn-gen-all');btn.disabled=true;btn.textContent='Gerando...';
    try{
        const distRes=await fetch(`/${PP_TENANT}/delivery/projects/${PP_PROJECT}/associates/${PP_ASSOCIATE}/deliveries?approved_only=1`,{headers:{Accept:'application/json','X-CSRF-TOKEN':PP_CSRF}});
        const data=await distRes.json();
        if(!data.length){ppToast('Nenhuma distribuicao aprovada encontrada.','error');return;}
        const cols=Array.from(document.querySelectorAll('#rm-no-receipt .rm-col-chk:checked')).map(c=>c.value);
        await doGenerateReceipt(data.map(d=>d.id),cols,btn,'Gerar Comprovante');
    }catch(error){ppToast('Erro ao gerar comprovante.','error');}
    finally{btn.disabled=false;btn.innerHTML='<i data-lucide="file-down" style="width:13px;height:13px"></i> Gerar Comprovante';if(typeof lucide!=='undefined')lucide.createIcons();}
}

async function enterSelectingMode(receiptId=null){
    PP_EDIT_RECEIPT_ID=receiptId?Number(receiptId):null;
    setModalState('selecting');
    const updating=!!PP_EDIT_RECEIPT_ID;
    document.querySelector('#rm-selecting .rm-info span').textContent=updating?'Selecione as distribuicoes pendentes para incluir no comprovante existente.':'Selecione as distribuicoes que farao parte deste comprovante adicional.';
    document.getElementById('rm-btn-gen-sel').innerHTML=`<i data-lucide="file-down" style="width:13px;height:13px"></i> ${updating?'Atualizar comprovante':'Gerar PDF'}`;
    const area=document.getElementById('rm-sel-area');area.innerHTML='<p style="padding:.75rem;font-size:.8rem;color:var(--color-text-secondary)">Carregando distribuicoes...</p>';
    try{
        const res=await fetch(`/${PP_TENANT}/delivery/projects/${PP_PROJECT}/associates/${PP_ASSOCIATE}/deliveries?approved_only=1`,{headers:{Accept:'application/json','X-CSRF-TOKEN':PP_CSRF}});
        const data=await res.json();
        if(!data.length){area.innerHTML='<p style="padding:.75rem;font-size:.8rem;color:var(--color-text-secondary)">Nenhuma distribuicao pendente encontrada.</p>';return;}
        area.innerHTML=`<div style="display:grid;grid-template-columns:auto 1fr 92px 82px 80px 82px;gap:.45rem;padding:.35rem .6rem;background:var(--color-bg);font-size:.72rem;font-weight:800;color:var(--color-text-secondary);border-bottom:1px solid var(--color-border)">
            <input type="checkbox" id="rm-sel-all"><span>Produto</span><span>Cliente</span><span>Data</span><span>Qtd</span><span>Liquido</span></div>`+
            data.map(d=>`<label class="delivery-item"><input type="checkbox" class="rm-dist-chk" value="${d.id}" data-net="${d.net_value}"><span>${escapeHtml(d.product_name)}</span><span>${escapeHtml(d.customer_name || '')}</span><span>${escapeHtml(d.delivery_date)}</span><span>${brQty(d.quantity)} ${escapeHtml(d.unit || '')}</span><span style="color:var(--color-success)">${brMoney(d.net_value)}</span></label>`).join('');
        document.getElementById('rm-sel-all')?.addEventListener('change',function(){document.querySelectorAll('.rm-dist-chk').forEach(c=>c.checked=this.checked);updateSelSummary();});
        document.querySelectorAll('.rm-dist-chk').forEach(c=>c.addEventListener('change',updateSelSummary));
        updateSelSummary();
    }catch(error){area.innerHTML='<p style="padding:.75rem;font-size:.8rem;color:var(--color-danger)">Erro ao carregar distribuicoes.</p>';}
}

function showHasReceiptState(){PP_EDIT_RECEIPT_ID=null;setModalState('has-receipt');}
function updateSelSummary(){const checks=document.querySelectorAll('.rm-dist-chk:checked');let total=0;checks.forEach(c=>total+=Number(c.dataset.net || 0));document.getElementById('rm-sel-count').textContent=checks.length;document.getElementById('rm-sel-total').textContent=total.toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});}
async function generateSelectedReceipt(){
    const checks=document.querySelectorAll('.rm-dist-chk:checked');
    if(!checks.length){ppToast('Selecione ao menos uma distribuicao.','error');return;}
    const ids=Array.from(checks).map(c=>Number(c.value));
    const cols=Array.from(document.querySelectorAll('#rm-selecting .rm-col-chk:checked')).map(c=>c.value);
    const btn=document.getElementById('rm-btn-gen-sel');
    if(PP_EDIT_RECEIPT_ID){await updateExistingReceipt(PP_EDIT_RECEIPT_ID,ids,cols,btn);return;}
    await doGenerateReceipt(ids,cols,btn,'Gerar PDF');
}

function downloadReceiptPdf(data){const bytes=atob(data.pdf);const arr=new Uint8Array(bytes.length);for(let i=0;i<bytes.length;i++)arr[i]=bytes.charCodeAt(i);const url=URL.createObjectURL(new Blob([arr],{type:'application/pdf'}));const link=document.createElement('a');link.href=url;link.download=data.filename;link.click();URL.revokeObjectURL(url);}
async function updateExistingReceipt(receiptId,ids,visibleColumns,btn){
    btn.disabled=true;btn.textContent='Atualizando...';
    try{
        const res=await fetch(`/${PP_TENANT}/delivery/projects/${PP_PROJECT}/receipts/${receiptId}/distributions`,{method:'PUT',headers:{'X-CSRF-TOKEN':PP_CSRF,'Content-Type':'application/json',Accept:'application/json'},body:JSON.stringify({delivery_ids:ids,visible_columns:visibleColumns})});
        const data=await res.json();
        if(!data.success){ppToast(data.message || 'Nao foi possivel atualizar o comprovante.','error');return;}
        downloadReceiptPdf(data);ppToast(data.message,'success');await openReceiptModal(PP_ASSOCIATE,PP_ASSOCIATE_NAME);loadProducers();
    }catch(error){ppToast('Erro de comunicacao ao atualizar o comprovante.','error');}
    finally{btn.disabled=false;btn.innerHTML='<i data-lucide="file-down" style="width:13px;height:13px"></i> Atualizar comprovante';if(typeof lucide!=='undefined')lucide.createIcons();}
}
async function regenerateReceipt(receiptId){
    const visibleColumns=Array.from(document.querySelectorAll('.rm-col-chk:checked')).map(c=>c.value);
    try{
        const res=await fetch(`/${PP_TENANT}/delivery/projects/${PP_PROJECT}/receipts/${receiptId}/regenerate`,{method:'POST',headers:{'X-CSRF-TOKEN':PP_CSRF,'Content-Type':'application/json',Accept:'application/json'},body:JSON.stringify({visible_columns:visibleColumns})});
        const data=await res.json();
        if(!data.success){ppToast(data.message || 'Nao foi possivel regenerar.','error');return;}
        downloadReceiptPdf(data);ppToast(data.message,'success');loadProducers();if(PP_ASSOCIATE)openReceiptModal(PP_ASSOCIATE,PP_ASSOCIATE_NAME);
    }catch(error){ppToast('Erro de comunicacao ao regenerar comprovante.','error');}
}
async function doGenerateReceipt(ids,visibleColumns,btn,originalLabel){
    btn.disabled=true;btn.textContent='Gerando...';
    try{
        const res=await fetch(`/${PP_TENANT}/delivery/projects/${PP_PROJECT}/receipt-selected`,{method:'POST',headers:{'X-CSRF-TOKEN':PP_CSRF,'Content-Type':'application/json',Accept:'application/json'},body:JSON.stringify({delivery_ids:ids,visible_columns:visibleColumns})});
        const data=await res.json();
        if(data.success){downloadReceiptPdf(data);ppToast(`Comprovante nº ${data.receipt_number} gerado.`);closeReceiptModal();loadProducers();}
        else ppToast(data.message || 'Erro ao gerar comprovante.','error');
    }catch(error){ppToast('Erro de comunicacao com o servidor.','error');}
    finally{btn.disabled=false;btn.innerHTML=`<i data-lucide="file-down" style="width:13px;height:13px"></i> ${originalLabel}`;if(typeof lucide!=='undefined')lucide.createIcons();}
}

loadProducers();
</script>
@endsection
