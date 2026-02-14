@extends('layouts.bento')

@section('title', 'Entregas do Projeto')
@section('page-title', 'Histórico de Entregas')
@section('user-role', 'Registrador')

@section('navigation')
<nav class="nav-tabs">
    <a href="{{ route('delivery.dashboard') }}" class="nav-tab">Dashboard</a>
    <a href="{{ route('delivery.register') }}" class="nav-tab">Registrar Entrega</a>
    <form action="{{ route('logout') }}" method="POST" style="display: inline;">
        @csrf
        <button type="submit" class="nav-tab" style="background: none; cursor: pointer;">Sair</button>
    </form>
</nav>
@endsection

@section('content')
<style>
    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 1rem;
    }
    
    .page-header {
        background: var(--color-surface);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        box-shadow: var(--shadow-sm);
    }
    
    .page-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--color-text);
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .page-subtitle {
        color: var(--color-text-secondary);
        font-size: 0.875rem;
    }
    
    .deliveries-grid {
        display: grid;
        gap: 1rem;
    }
    
    .delivery-card {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        box-shadow: var(--shadow-sm);
        transition: all 0.2s;
    }
    
    .delivery-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }
    
    .delivery-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 1rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid var(--color-border);
    }
    
    .delivery-main {
        flex: 1;
    }
    
    .delivery-title {
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--color-text);
        margin-bottom: 0.25rem;
    }
    
    .delivery-meta {
        font-size: 0.875rem;
        color: var(--color-text-secondary);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .delivery-body {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1rem;
    }
    
    .delivery-info {
        text-align: center;
    }
    
    .info-label {
        font-size: 0.75rem;
        color: var(--color-text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 0.25rem;
    }
    
    .info-value {
        font-size: 1rem;
        font-weight: 600;
        color: var(--color-text);
    }
    
    .info-value.success { color: var(--color-success); }
    .info-value.warning { color: var(--color-warning); }
    
    .status-badge {
        padding: 0.375rem 0.75rem;
        border-radius: var(--radius-sm);
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    
    .status-badge.pending {
        background: rgba(251, 191, 36, 0.1);
        color: var(--color-warning);
    }
    
    .status-badge.approved {
        background: rgba(16, 185, 129, 0.1);
        color: var(--color-success);
    }
    
    .status-badge.rejected {
        background: rgba(239, 68, 68, 0.1);
        color: var(--color-danger);
    }
    
    .delivery-actions {
        display: flex;
        gap: 0.5rem;
        margin-top: 1rem;
    }
    
    .btn {
        padding: 0.5rem 1rem;
        border: none;
        border-radius: var(--radius-md);
        font-size: 0.875rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
    }
    
    .btn-success {
        background: var(--color-success);
        color: white;
    }
    
    .btn-success:hover {
        background: var(--color-primary-dark);
        transform: translateY(-1px);
    }
    
    .btn-danger {
        background: var(--color-danger);
        color: white;
    }
    
    .btn-danger:hover {
        background: #dc2626;
        transform: translateY(-1px);
    }
    
    .btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }
    
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        background: var(--color-surface);
        border-radius: var(--radius-lg);
        border: 1px dashed var(--color-border);
    }
    
    .empty-icon {
        width: 64px;
        height: 64px;
        margin: 0 auto 1rem;
        color: var(--color-text-muted);
    }
    
    @media (max-width: 768px) {
        .delivery-header {
            flex-direction: column;
            gap: 0.75rem;
        }
        
        .delivery-body {
            grid-template-columns: repeat(2, 1fr);
        }
    }
</style>

<div class="container">
    <div class="page-header">
        <h1 class="page-title">
            <i data-lucide="history"></i>
            {{ $project->title }}
        </h1>
        <p class="page-subtitle">
            <i data-lucide="building"></i>
            {{ $project->customer->name ?? '-' }}
        </p>
    </div>
    
    <div id="alert-container"></div>
    
    @if($deliveries->isEmpty())
        <div class="empty-state">
            <i data-lucide="inbox" class="empty-icon"></i>
            <h3 style="font-size: 1.25rem; font-weight: 600; color: var(--color-text-secondary); margin-bottom: 0.5rem;">
                Nenhuma Entrega Registrada
            </h3>
            <p style="color: var(--color-text-muted);">
                Ainda não há entregas registradas para este projeto.
            </p>
        </div>
    @else
        <div class="deliveries-grid">
            @foreach($deliveries as $delivery)
                <div class="delivery-card">
                    <div class="delivery-header">
                        <div class="delivery-main">
                            <div class="delivery-title">{{ $delivery['product_name'] }}</div>
                            <div class="delivery-meta">
                                <i data-lucide="user" style="width:16px;height:16px;"></i>
                                {{ $delivery['associate_name'] }}
                            </div>
                        </div>
                        <span class="status-badge {{ $delivery['status_value'] }}">
                            {{ $delivery['status'] }}
                        </span>
                    </div>
                    
                    <div class="delivery-body">
                        <div class="delivery-info">
                            <div class="info-label">Data</div>
                            <div class="info-value">{{ $delivery['delivery_date'] }}</div>
                        </div>
                        <div class="delivery-info">
                            <div class="info-label">Quantidade</div>
                            <div class="info-value">{{ number_format($delivery['quantity'], 3, ',', '.') }} {{ $delivery['unit'] }}</div>
                        </div>
                        @if($delivery['quality_grade'])
                            <div class="delivery-info">
                                <div class="info-label">Qualidade</div>
                                <div class="info-value">{{ $delivery['quality_grade'] }}</div>
                            </div>
                        @endif
                        <div class="delivery-info">
                            <div class="info-label">Valor Líquido</div>
                            <div class="info-value success">R$ {{ number_format($delivery['net_value'], 2, ',', '.') }}</div>
                        </div>
                    </div>
                    
                    @if($delivery['status_value'] === 'pending')
                        <div class="delivery-actions">
                            <button type="button" class="btn btn-success approve-btn" data-id="{{ $delivery['id'] }}">
                                <i data-lucide="check"></i>
                                Aprovar
                            </button>
                            <button type="button" class="btn btn-danger reject-btn" data-id="{{ $delivery['id'] }}">
                                <i data-lucide="x"></i>
                                Rejeitar
                            </button>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Lucide
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    // Approve buttons
    document.querySelectorAll('.approve-btn').forEach(btn => {
        btn.addEventListener('click', async function() {
            if (!confirm('Deseja aprovar esta entrega?')) return;
            
            const deliveryId = this.dataset.id;
            this.disabled = true;
            this.textContent = 'Aprovando...';
            
            try {
                const res = await fetch(`/delivery/deliveries/${deliveryId}/approve`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    }
                });
                
                const data = await res.json();
                
                if (data.success) {
                    showAlert(data.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(data.message, 'error');
                    this.disabled = false;
                    this.innerHTML = '<i data-lucide="check"></i> Aprovar';
                    if (typeof lucide !== 'undefined') lucide.createIcons();
                }
            } catch (e) {
                showAlert('Erro ao aprovar entrega', 'error');
                this.disabled = false;
                this.innerHTML = '<i data-lucide="check"></i> Aprovar';
                if (typeof lucide !== 'undefined') lucide.createIcons();
            }
        });
    });
    
    // Reject buttons
    document.querySelectorAll('.reject-btn').forEach(btn => {
        btn.addEventListener('click', async function() {
            const reason = prompt('Motivo da rejeição (opcional):');
            if (reason === null) return;
            
            const deliveryId = this.dataset.id;
            this.disabled = true;
            this.textContent = 'Rejeitando...';
            
            try {
                const res = await fetch(`/delivery/deliveries/${deliveryId}/reject`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ reason })
                });
                
                const data = await res.json();
                
                if (data.success) {
                    showAlert(data.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(data.message, 'error');
                    this.disabled = false;
                    this.innerHTML = '<i data-lucide="x"></i> Rejeitar';
                    if (typeof lucide !== 'undefined') lucide.createIcons();
                }
            } catch (e) {
                showAlert('Erro ao rejeitar entrega', 'error');
                this.disabled = false;
                this.innerHTML = '<i data-lucide="x"></i> Rejeitar';
                if (typeof lucide !== 'undefined') lucide.createIcons();
            }
        });
    });
    
    function showAlert(msg, type) {
        const container = document.getElementById('alert-container');
        const alertClass = type === 'success' ? 'alert-success' : 'alert-error';
        const icon = type === 'success' ? 'check-circle' : 'alert-circle';
        
        container.innerHTML = `
            <div style="padding: 0.875rem 1rem; border-radius: var(--radius-md); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; background: ${type === 'success' ? 'rgba(16, 185, 129, 0.1)' : 'rgba(239, 68, 68, 0.1)'}; border: 1px solid ${type === 'success' ? 'rgba(16, 185, 129, 0.3)' : 'rgba(239, 68, 68, 0.3)'}; color: ${type === 'success' ? 'var(--color-success)' : 'var(--color-danger)'};">
                <i data-lucide="${icon}"></i>
                ${msg}
            </div>
        `;
        
        if (typeof lucide !== 'undefined') lucide.createIcons();
        setTimeout(() => container.innerHTML = '', 4000);
    }
});
</script>
@endpush
@endsection
