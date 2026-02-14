@extends('layouts.bento')

@section('content')
<style>
    .step {
        animation: fadeIn 0.3s ease-out;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .project-badge {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        background: rgba(16, 185, 129, 0.1);
        color: var(--color-success);
        padding: 0.75rem 1rem;
        border-radius: var(--radius-md);
        font-weight: 600;
        margin-bottom: 1.5rem;
        border: 1px solid rgba(16, 185, 129, 0.2);
    }

    .project-badge-icon {
        width: 1.25rem;
        height: 1.25rem;
    }

    .quality-badge {
        cursor: pointer;
        transition: all 0.2s;
        border: 2px solid transparent;
        padding: 0.5rem 1rem;
        font-weight: bold;
    }

    .quality-badge.badge-primary {
        border-color: var(--color-primary);
        transform: scale(1.05);
    }

    .form-card {
        background: var(--color-surface);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        box-shadow: var(--shadow-md);
    }
    
    .form-card-header {
        margin-bottom: 2rem;
        border-bottom: 1px solid var(--color-border);
        padding-bottom: 1rem;
    }
    
    .form-card-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--color-text);
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .form-card-subtitle {
        color: var(--color-text-secondary);
        font-size: 0.875rem;
    }
    
    .form-group-mobile {
        margin-bottom: 1.5rem;
    }
    
    .form-group-mobile label {
        display: block;
        font-weight: 600;
        font-size: 0.875rem;
        color: var(--color-text);
        margin-bottom: 0.5rem;
    }
    
    .form-group-mobile select,
    .form-group-mobile input,
    .form-group-mobile textarea {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        font-size: 1rem;
        background: var(--color-bg);
        transition: border-color 0.2s, box-shadow 0.2s;
    }
    
    .form-group-mobile select:focus,
    .form-group-mobile input:focus,
    .form-group-mobile textarea:focus {
        outline: none;
        border-color: var(--color-primary);
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
    }
    
    .form-hint {
        display: block;
        margin-top: 0.375rem;
        font-size: 0.75rem;
        color: var(--color-text-secondary);
    }
    
    .required {
        color: var(--color-danger);
    }
    
    .submit-btn {
        width: 100%;
        background: var(--color-primary);
        color: white;
        padding: 1rem;
        border: none;
        border-radius: var(--radius-md);
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }
    
    .submit-btn:hover {
        background: var(--color-primary-dark);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }

    .submit-btn.secondary {
        background: var(--color-info);
    }
    .submit-btn.secondary:hover {
        background: var(--color-info);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    }
    
    .submit-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }
    
    .info-card {
        padding: 1.25rem;
        background: var(--color-bg);
        border-radius: var(--radius-md);
        border: 1px solid var(--color-border);
        margin-bottom: 1.5rem;
    }
    
    .info-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.5rem 0;
    }
    
    .info-row:not(:last-child) {
        border-bottom: 1px solid var(--color-border);
    }
    
    .info-label {
        font-size: 0.875rem;
        color: var(--color-text-secondary);
        font-weight: 500;
    }
    
    .info-value {
        font-weight: 600;
        font-size: 0.875rem;
        color: var(--color-text);
    }
    
    .info-value.text-success { color: var(--color-success); }
    .info-value.text-warning { color: var(--color-warning); }
    .info-value.text-primary { color: var(--color-primary); }
    .info-value.text-danger { color: var(--color-danger); }
    
    .recent-deliveries {
        margin-top: 2rem;
    }
    
    .delivery-item {
        padding: 0.75rem;
        background: var(--color-bg);
        border-radius: var(--radius-md);
        border: 1px solid var(--color-border);
        margin-bottom: 0.5rem;
    }
    
    .alert {
        padding: 0.75rem 1rem;
        border-radius: var(--radius-md);
        margin-bottom: 1rem;
    }
    
    .alert-success {
        background: rgba(16, 185, 129, 0.1);
        border: 1px solid rgba(16, 185, 129, 0.3);
        color: #059669;
    }
    
    .alert-error {
        background: rgba(239, 68, 68, 0.1);
        border: 1px solid rgba(239, 68, 68, 0.3);
        color: #dc2626;
    }
</style>

<div class="form-card">
    <div class="form-card-header">
        <h2 class="form-card-title">��� Registrar Entrega</h2>
        <p class="form-card-subtitle">
            Preencha os dados da entrega rápida.
        </p>
    </div>

    <!-- Success/Error Messages -->
    <div id="alert-container"></div>

    @if($projects->count() === 1)
        <!-- Project Badge quando vem da URL -->
        <div class="project-badge">
            <svg class="project-badge-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            <span>{{ $projects->first()->title }} - {{ $projects->first()->customer->name ?? '' }}</span>
        </div>
    @endif

    <!-- Form (Multi-step) -->
    <form id="delivery-form">
        @csrf

        <div id="step-1" class="step">
            <div class="form-card-header">
                <h3 class="form-card-title" style="font-size: 1.1rem;">Passo 1 — Projeto e Produto</h3>
                <p class="form-card-subtitle">Escolha o projeto e o produto.</p>
            </div>

            @if($projects->count() > 1)
                <div class="form-group-mobile">
                    <label for="sales_project_id">Projeto <span class="required">*</span></label>
                    <select id="sales_project_id" name="sales_project_id" required>
                        <option value="">Selecione um projeto...</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}" {{ old('sales_project_id', request()->route('project')) == $project->id ? 'selected' : '' }}>
                                {{ $project->title }} - {{ $project->customer->name ?? '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @else
                <input type="hidden" id="sales_project_id" name="sales_project_id" value="{{ $projects->first()->id }}">
            @endif

            <div class="form-group-mobile">
                <label for="project_demand_id">Produto <span class="required">*</span></label>
                <select id="project_demand_id" name="project_demand_id" required disabled>
                    <option value="">{{ $projects->count() === 1 ? 'Carregando produtos...' : 'Selecione primeiro um projeto...' }}</option>
                </select>
            </div>

            <!-- Demand Info Card -->
            <div id="demand-info" class="info-card" style="display: none;">
                <div class="info-row"><span class="info-label">Já Entregue:</span><span class="info-value text-success" id="demand-delivered">-</span></div>
                <div class="info-row"><span class="info-label">Ainda Falta:</span><span class="info-value text-warning" id="demand-remaining">-</span></div>
            </div>

            <div class="form-actions" style="display:flex; gap:8px; justify-content:flex-end; margin-top:1rem;">
                <button type="button" class="submit-btn" id="to-step-2">Próximo →</button>
            </div>
        </div>

        <div id="step-2" class="step" style="display:none;">
            <div class="form-card-header">
                <h3 class="form-card-title" style="font-size: 1.1rem;">Passo 2 — Associado</h3>
                <p class="form-card-subtitle">Selecione o associado na tabela.</p>
            </div>

            <div class="form-group-mobile">
                <label>Associado <span class="required">*</span></label>
                <div style="display:flex; gap:8px; align-items:center;">
                    <input type="hidden" id="associate_id" name="associate_id">
                    <div id="associate-selected" class="info-card" style="flex:1; margin-bottom:0; padding: 0.75rem;">Nenhum associado selecionado</div>
                    <button type="button" class="submit-btn secondary" id="open-associate-modal" style="width: auto; padding: 0.75rem 1rem;">Buscar</button>
                </div>
            </div>

            <div class="form-actions" style="display:flex; gap:8px; justify-content:space-between; margin-top:1rem;">
                <button type="button" class="submit-btn secondary" id="back-to-step-1">← Voltar</button>
                <button type="button" class="submit-btn" id="to-step-3">Próximo →</button>
            </div>
        </div>

        <div id="step-3" class="step" style="display:none;">
            <div class="form-card-header">
                <h3 class="form-card-title" style="font-size: 1.1rem;">Passo 3 — Detalhes</h3>
                <p class="form-card-subtitle">Informações da entrega e qualidade.</p>
            </div>

            <div class="form-group-mobile">
                <label for="delivery_date">Data da Entrega <span class="required">*</span></label>
                <input type="date" id="delivery_date" name="delivery_date" value="{{ date('Y-m-d') }}" required>
            </div>

            <div class="form-group-mobile">
                <label for="quantity">Quantidade <span class="required">*</span></label>
                <input type="number" id="quantity" name="quantity" step="0.001" min="0.001" placeholder="0.000" required>
                <small id="quantity-help" class="form-hint">Insira a quantidade entregue</small>
            </div>

            <div class="form-group-mobile">
                <label>Qualidade</label>
                <div id="quality-badges" style="display:flex; gap:0.5rem; margin-top:0.5rem;">
                    <button type="button" class="badge badge-info quality-badge" data-grade="A">A</button>
                    <button type="button" class="badge badge-success quality-badge" data-grade="B">B</button>
                    <button type="button" class="badge badge-warning quality-badge" data-grade="C">C</button>
                </div>
                <input type="hidden" id="quality_grade" name="quality_grade" value="A">
            </div>

            <!-- Value Summary (calculated) -->
            <div id="value-summary" class="info-card" style="display: none; margin-top:1rem;">
                <div class="info-row">
                    <span class="info-label"><strong>Valor Líquido:</strong></span>
                    <span class="info-value text-success" style="font-size: 1rem;"><strong id="net-value">R$ 0,00</strong></span>
                </div>
            </div>

            <div class="form-actions" style="display:flex; gap:8px; justify-content:space-between; margin-top:1rem;">
                <button type="button" class="submit-btn secondary" id="back-to-step-2">← Voltar</button>
                <button type="submit" class="submit-btn" id="submit-btn">✓ Finalizar Registro</button>
            </div>
        </div>
    </form>

    <!-- Recent Deliveries -->
    <div id="recent-deliveries" class="recent-deliveries" style="display: none;">
        <h3 style="font-size: 1rem; font-weight: 600; color: var(--color-text); margin-bottom: 1rem;">Histórico Recente</h3>
        <div id="deliveries-list"></div>
    </div>
</div>

<!-- Associate Selection Modal -->
<div id="associate-modal" style="display:none; position:fixed; inset: 0; background:rgba(0,0,0,0.5); align-items:start; justify-content:center; z-index:1000; padding:1rem;">
    <div style="width:100%; max-width:600px; background:var(--color-surface); border-radius:var(--radius-lg); padding:1.5rem; box-shadow:var(--shadow-lg);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
            <h4 style="margin:0;">Buscar Associado</h4>
            <button id="close-associate-modal" style="background:none; border:none; font-size:1.5rem; cursor:pointer;">&times;</button>
        </div>
        <input type="text" id="associate-search" placeholder="Digitar nome ou documento..." style="width:100%; padding:0.75rem; margin-bottom:1rem; border:1px solid var(--color-border); border-radius:var(--radius-md);">
        <div style="max-height:300px; overflow-y:auto; border:1px solid var(--color-border); border-radius:var(--radius-sm);">
            <table class="table" id="associate-table" style="width:100%; border-collapse:collapse;">
                <thead style="position:sticky; top:0; background:var(--color-bg);">
                    <tr>
                        <th style="padding:0.75rem; text-align:left; border-bottom:1px solid var(--color-border);">Nome</th>
                        <th style="padding:0.75rem; text-align:right; border-bottom:1px solid var(--color-border);">Ação</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($associates as $associate)
                    <tr data-name="{{ strtolower($associate->user->name ?? '') }}" data-doc="{{ $associate->cpf_cnpj ?? '' }}" style="border-bottom:1px solid var(--color-border);">
                        <td style="padding:0.75rem;">
                            <div style="font-weight:600;">{{ $associate->user->name ?? 'Associado #' . $associate->id }}</div>
                            <div style="font-size:0.75rem; color:var(--color-text-secondary);">{{ $associate->cpf_cnpj ?? '-' }}</div>
                        </td>
                        <td style="padding:0.75rem; text-align:right;">
                            <button type="button" class="submit-btn select-associate" data-id="{{ $associate->id }}" style="padding:0.4rem 0.8rem; font-size:0.875rem;">Selecionar</button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('delivery-form');
    const projectSelect = document.getElementById('sales_project_id');
    const demandSelect = document.getElementById('project_demand_id');
    const associateHidden = document.getElementById('associate_id');
    const associateSelected = document.getElementById('associate-selected');
    const openAssociateModalBtn = document.getElementById('open-associate-modal');
    const associateModal = document.getElementById('associate-modal');
    const closeAssociateModalBtn = document.getElementById('close-associate-modal');
    const associateSearch = document.getElementById('associate-search');
    const quantityInput = document.getElementById('quantity');
    const submitBtn = document.getElementById('submit-btn');
    const toStep2 = document.getElementById('to-step-2');
    const toStep3 = document.getElementById('to-step-3');
    const backToStep1 = document.getElementById('back-to-step-1');
    const backToStep2 = document.getElementById('back-to-step-2');
    const stepElements = document.querySelectorAll('.step');
    const qualityBadges = document.querySelectorAll('.quality-badge');

    let currentDemand = null;

    function showStep(n) {
        stepElements.forEach((el, idx) => {
            el.style.display = (idx === n - 1) ? 'block' : 'none';
        });
    }

    // Step navigation
    toStep2?.addEventListener('click', () => {
        if (!projectSelect.value || !demandSelect.value) {
            showAlert('Selecione o projeto e o produto.', 'error');
            return;
        }
        showStep(2);
    });

    toStep3?.addEventListener('click', () => {
        if (!associateHidden.value) {
            showAlert('Selecione um associado.', 'error');
            return;
        }
        showStep(3);
    });

    backToStep1?.addEventListener('click', () => showStep(1));
    backToStep2?.addEventListener('click', () => showStep(2));

    // Project -> Demands AJAX
    projectSelect?.addEventListener('change', async function() {
        const projectId = this.value;
        demandSelect.disabled = true;
        demandSelect.innerHTML = '<option value="">Carregando...</option>';
        document.getElementById('demand-info').style.display = 'none';

        if (!projectId) return;

        try {
            const response = await fetch('/delivery/projects/' + projectId + '/demands');
            const demands = await response.json();
            demandSelect.innerHTML = '<option value="">Selecione um produto...</option>';
            demands.forEach(d => {
                const opt = document.createElement('option');
                opt.value = d.id;
                opt.textContent = d.product_name + ' (' + d.product_unit + ')';
                opt.dataset.demand = JSON.stringify(d);
                demandSelect.appendChild(opt);
            });
            demandSelect.disabled = false;
        } catch (e) {
            showAlert('Erro ao carregar produtos.', 'error');
        }
    });

    // Demand Info
    demandSelect?.addEventListener('change', function() {
        const opt = this.options[this.selectedIndex];
        if (!opt || !opt.dataset.demand) {
            currentDemand = null;
            document.getElementById('demand-info').style.display = 'none';
            return;
        }
        currentDemand = JSON.parse(opt.dataset.demand);
        document.getElementById('demand-delivered').textContent = formatNumber(currentDemand.delivered_quantity) + ' ' + currentDemand.product_unit;
        document.getElementById('demand-remaining').textContent = formatNumber(currentDemand.remaining_quantity) + ' ' + currentDemand.product_unit;
        document.getElementById('quantity-help').textContent = 'Unidade: ' + currentDemand.product_unit;
        document.getElementById('demand-info').style.display = 'block';
        calcValues();
    });

    // Modal Associate
    openAssociateModalBtn.addEventListener('click', () => {
        associateModal.style.display = 'flex';
        associateSearch.focus();
    });
    closeAssociateModalBtn.addEventListener('click', () => associateModal.style.display = 'none');
    
    associateSearch.addEventListener('input', function() {
        const q = this.value.toLowerCase();
        document.querySelectorAll('#associate-table tbody tr').forEach(tr => {
            const name = tr.dataset.name;
            const doc = tr.dataset.doc;
            tr.style.display = (name.includes(q) || doc.includes(q)) ? '' : 'none';
        });
    });

    document.querySelectorAll('.select-associate').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const name = this.closest('tr').querySelector('div').textContent;
            associateHidden.value = id;
            associateSelected.innerHTML = '<strong>' + name + '</strong>';
            associateModal.style.display = 'none';
            loadHistory();
        });
    });

    // Quality Badges
    qualityBadges.forEach(b => {
        b.addEventListener('click', function() {
            qualityBadges.forEach(x => x.classList.remove('badge-primary'));
            this.classList.add('badge-primary');
            document.getElementById('quality_grade').value = this.dataset.grade;
        });
    });
    if (qualityBadges[0]) qualityBadges[0].classList.add('badge-primary');

    // Calc Values
    quantityInput.addEventListener('input', calcValues);
    function calcValues() {
        if (!currentDemand || !quantityInput.value) {
            document.getElementById('value-summary').style.display = 'none';
            return;
        }
        const qty = parseFloat(quantityInput.value) || 0;
        const gross = qty * currentDemand.unit_price;
        const net = gross * 0.9; // 10% fee
        document.getElementById('net-value').textContent = 'R$ ' + formatMoney(net);
        document.getElementById('value-summary').style.display = 'block';
    }

    // Submit
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        submitBtn.disabled = true;
        submitBtn.textContent = 'Gravando...';

        const data = Object.fromEntries(new FormData(form));
        try {
            const resp = await fetch('/delivery/register', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(data)
            });
            const res = await resp.json();
            if (res.success) {
                showAlert(res.message, 'success');
                const project = projectSelect.value;
                form.reset();
                projectSelect.value = project;
                projectSelect.dispatchEvent(new Event('change'));
                associateHidden.value = '';
                associateSelected.textContent = 'Nenhum associado selecionado';
                showStep(1);
            } else {
                showAlert(res.message, 'error');
            }
        } catch (e) {
            showAlert('Erro na conexão.', 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = '✓ Finalizar Registro';
        }
    });

    function loadHistory() {
        const aid = associateHidden.value;
        const pid = projectSelect.value;
        if (!aid || !pid) return;
        fetch('/delivery/projects/' + pid + '/associates/' + aid + '/deliveries')
            .then(r => r.json())
            .then(list => {
                const container = document.getElementById('deliveries-list');
                if (list.length === 0) {
                    container.innerHTML = '<p style="font-size:0.875rem; color:var(--color-text-secondary);">Sem entregas recentes.</p>';
                } else {
                    container.innerHTML = list.map(d => ' \
                        <div class="delivery-item"> \
                            <div style="display:flex; justify-content:space-between; font-size:0.875rem;"> \
                                <strong>' + d.product_name + '</strong> \
                                <span>' + formatNumber(d.quantity) + ' ' + d.unit + '</span> \
                            </div> \
                            <div style="display:flex; justify-content:space-between; font-size:0.75rem; margin-top:0.25rem;"> \
                                <span>' + d.delivery_date + '</span> \
                                <span class="text-success">R$ ' + formatMoney(d.net_value) + '</span> \
                            </div> \
                        </div>').join('');
                }
                document.getElementById('recent-deliveries').style.display = 'block';
            });
    }

    function formatNumber(n) { return parseFloat(n).toLocaleString('pt-BR', { maximumFractionDigits: 3 }); }
    function formatMoney(n) { return parseFloat(n).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

    function showAlert(msg, type) {
        const container = document.getElementById('alert-container');
        container.innerHTML = '<div class="alert alert-' + type + '">' + msg + '</div>';
        setTimeout(() => container.innerHTML = '', 4000);
    }

    if (projectSelect.value) projectSelect.dispatchEvent(new Event('change'));
});
</script>
@endsection
