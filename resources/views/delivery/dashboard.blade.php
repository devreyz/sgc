@extends('layouts.bento')

@section('title', 'Painel de Entregas')
@section('page-title', 'Painel de Entregas')
@section('user-role', 'Registrador')

@section('navigation')
<nav class="nav-tabs">
    <a href="{{ route('delivery.dashboard', ['tenant' => $currentTenant->slug]) }}" class="nav-tab active">Dashboard</a>
    <a href="{{ route('delivery.register', ['tenant' => $currentTenant->slug]) }}" class="nav-tab">Registrar Entrega</a>
    <form action="{{ route('logout') }}" method="POST" style="display: inline;">
        @csrf
        <button type="submit" class="nav-tab" style="background: none; cursor: pointer;">Sair</button>
    </form>
</nav>
@endsection

@section('content')
<style>
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .anim-fade-in { animation: fadeInUp 0.4s ease both; }
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }
    .stat-card {
        background: var(--color-surface);
        border-radius: var(--radius-lg);
        padding: 1.25rem 1.5rem;
        border: 1px solid var(--color-border);
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,.08); }
    .stat-label { font-size: .8rem; color: var(--color-text-secondary); text-transform: uppercase; letter-spacing: .05em; margin-bottom: .25rem; }
    .stat-value { font-size: 1.8rem; font-weight: 700; color: var(--color-primary); line-height: 1.1; }
    .stat-sub   { font-size: .75rem; color: var(--color-text-secondary); margin-top: .15rem; }
    .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: .5rem; }
    .section-title  { display: flex; align-items: center; gap: .5rem; font-size: 1.1rem; font-weight: 700; color: var(--color-text); }
    .badge-count    { background: var(--color-primary); color: #fff; font-size: .7rem; font-weight: 700; padding: .1rem .45rem; border-radius: 99px; }
    .projects-grid { display: grid; gap: 1.25rem; }
    .project-card {
        background: var(--color-surface);
        border-radius: var(--radius-lg);
        border: 1px solid var(--color-border);
        overflow: hidden;
        transition: border-color 0.2s, box-shadow 0.2s, transform 0.2s;
    }
    .project-card.draft-card { border-color: var(--color-warning); opacity: .88; }
    .project-card:hover { border-color: var(--color-primary); box-shadow: 0 4px 16px rgba(0,0,0,.1); transform: translateY(-2px); }
    .project-header { padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--color-border); display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; }
    .project-header-info { flex: 1; min-width: 0; }
    .project-title { font-size: 1.1rem; font-weight: 700; color: var(--color-text); margin: 0 0 .25rem; overflow: hidden; text-overflow: ellipsis; display: flex; align-items: center; gap: .4rem; }
    .project-customer { font-size: .8rem; color: var(--color-text-secondary); display: flex; align-items: center; gap: .3rem; }
    .free-badge { display: inline-flex; align-items: center; gap: .2rem; background: rgba(139,92,246,.15); color: #7c3aed; font-size: .6rem; font-weight: 700; padding: .1rem .35rem; border-radius: 99px; text-transform: uppercase; letter-spacing: .04em; }
    .status-badge { display: inline-flex; align-items: center; gap: .3rem; padding: .25rem .65rem; border-radius: 99px; font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; white-space: nowrap; }
    .status-badge.draft  { background: rgba(245,158,11,.15); color: #d97706; }
    .status-badge.active { background: rgba(16,185,129,.15);  color: #059669; }
    .project-body { padding: 1.25rem 1.5rem; }
    .project-info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(80px, 1fr)); gap: .75rem; margin-bottom: 1rem; }
    .info-item { text-align: center; }
    .info-label { font-size: .68rem; color: var(--color-text-secondary); text-transform: uppercase; letter-spacing: .05em; margin-bottom: .2rem; }
    .info-value { font-size: 1.1rem; font-weight: 700; color: var(--color-text); }
    .info-value.success { color: var(--color-success); }
    .info-value.warning { color: var(--color-warning); }
    .progress-section { margin-bottom: 1rem; }
    .progress-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: .35rem; }
    .progress-label { font-size: .8rem; font-weight: 600; color: var(--color-text-secondary); }
    .progress-percentage { font-size: .8rem; font-weight: 700; color: var(--color-primary); }
    .progress-bar-container { height: 7px; background: var(--color-border); border-radius: 99px; overflow: hidden; }
    .progress-bar-fill { height: 100%; background: linear-gradient(90deg, var(--color-primary), var(--color-success)); transition: width 0.8s cubic-bezier(.4,0,.2,1); }
    .alert-inline { display: flex; align-items: center; gap: .4rem; padding: .5rem .75rem; border-radius: var(--radius-md); font-size: .8rem; font-weight: 500; margin-bottom: .5rem; }
    .alert-warning { background: rgba(245,158,11,.12); color: #d97706; }
    .draft-banner { padding: .75rem 1.5rem; background: rgba(245,158,11,.08); border-top: 1px solid rgba(245,158,11,.25); display: flex; justify-content: space-between; align-items: center; gap: .75rem; flex-wrap: wrap; }
    .draft-message { display: flex; align-items: center; gap: .4rem; font-size: .82rem; color: var(--color-text-secondary); }
    .project-footer { padding: .85rem 1.5rem; background: var(--color-bg); display: flex; justify-content: space-between; align-items: center; gap: .75rem; flex-wrap: wrap; }
    .deadline-info { display: flex; align-items: center; gap: .35rem; font-size: .8rem; color: var(--color-text-secondary); }
    .deadline-urgent { color: var(--color-danger); font-weight: 600; }
    .btn { display: inline-flex; align-items: center; gap: .3rem; padding: .5rem 1rem; border-radius: var(--radius-md); border: none; cursor: pointer; font-size: .8rem; font-weight: 600; text-decoration: none; transition: all 0.15s ease; white-space: nowrap; }
    .btn:disabled { opacity: .5; cursor: not-allowed; transform: none !important; }
    .btn-primary { background: var(--color-primary); color: #fff; }
    .btn-primary:hover:not(:disabled) { opacity: .9; transform: translateY(-1px); }
    .btn-warning { background: var(--color-warning); color: #fff; }
    .btn-warning:hover:not(:disabled) { opacity: .9; transform: translateY(-1px); }
    .btn-success { background: var(--color-success); color: #fff; }
    .btn-success:hover:not(:disabled) { opacity: .9; transform: translateY(-1px); }
    .btn-ghost { background: transparent; color: var(--color-text-secondary); border: 1px solid var(--color-border); }
    .btn-ghost:hover:not(:disabled) { background: var(--color-bg); color: var(--color-text); transform: translateY(-1px); }
    .btn-sm { padding: .375rem .75rem; font-size: .75rem; }
    .btn-actions { display: flex; gap: .5rem; flex-wrap: wrap; }
    .spinner { display: none; width: 12px; height: 12px; border: 2px solid rgba(255,255,255,.4); border-top-color: #fff; border-radius: 50%; animation: spin .6s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }
    .empty-state { text-align: center; padding: 3rem 1.5rem; color: var(--color-text-secondary); }
    .empty-icon { width: 48px; height: 48px; margin: 0 auto 1rem; opacity: .4; }
    .empty-title { font-size: 1.1rem; font-weight: 700; margin-bottom: .5rem; color: var(--color-text); }
    .empty-message { font-size: .875rem; }
    #toast-container { position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 9999; display: flex; flex-direction: column; gap: .5rem; }
    .toast { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: .75rem 1rem; display: flex; align-items: center; gap: .5rem; font-size: .875rem; box-shadow: 0 4px 12px rgba(0,0,0,.15); animation: fadeInUp .3s ease; transition: opacity .3s; min-width: 280px; max-width: 380px; }
    .toast.success { border-left: 3px solid var(--color-success); }
    .toast.error   { border-left: 3px solid var(--color-danger); }
    .toast.info    { border-left: 3px solid var(--color-info, #0ea5e9); }
    @media (max-width: 480px) {
        .project-header { flex-direction: column; align-items: flex-start; }
        .btn-actions { flex-direction: column; width: 100%; }
        .btn-actions .btn { width: 100%; justify-content: center; }
    }
</style>

<div id="toast-container"></div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card anim-fade-in" style="animation-delay:.0s">
        <div class="stat-label">Projetos Ativos</div>
        <div class="stat-value">{{ $stats['active_projects'] }}</div>
        <div class="stat-sub">em andamento</div>
    </div>
    <div class="stat-card anim-fade-in" style="animation-delay:.05s">
        <div class="stat-label">Entregas Hoje</div>
        <div class="stat-value">{{ $stats['deliveries_today'] }}</div>
        <div class="stat-sub">registros do dia</div>
    </div>
    <div class="stat-card anim-fade-in" style="animation-delay:.1s">
        <div class="stat-label">Pendentes</div>
        <div class="stat-value" style="color:var(--color-warning)">{{ $stats['pending_approvals'] }}</div>
        <div class="stat-sub">aguardando aprovação</div>
    </div>
    <div class="stat-card anim-fade-in" style="animation-delay:.15s">
        <div class="stat-label">Entregue esta semana</div>
        <div class="stat-value" style="font-size:1.4rem">{{ number_format($stats['total_delivered_this_week'], 0, ',', '.') }}</div>
        <div class="stat-sub">kg acumulados</div>
    </div>
</div>

<!-- Section Header -->
<div class="section-header anim-fade-in" style="animation-delay:.25s">
    <div class="section-title">
        <i data-lucide="folder-open" style="width:18px;height:18px;color:var(--color-primary)"></i>
        Projetos
        <span class="badge-count">{{ count($projects) }}</span>
    </div>
    <a href="{{ route('delivery.register', ['tenant' => $currentTenant->slug]) }}" class="btn btn-primary btn-sm" title="Registrar entrega sem vinculo com projeto">
        <i data-lucide="package-plus" style="width:14px;height:14px"></i>
        Entrega Avulsa
    </a>
</div>

<!-- Projects List -->
@if($projects->isEmpty())
    <div class="empty-state">
        <svg class="empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
        </svg>
        <div class="empty-title">Nenhum Projeto Disponivel</div>
        <div class="empty-message">Nao ha projetos em andamento ou em rascunho para este periodo.</div>
    </div>
@else
    <div class="projects-grid">
        @foreach($projects as $i => $project)
            <div class="project-card {{ $project['status_value'] === 'draft' ? 'draft-card' : '' }}"
                 style="animation-delay: {{ .3 + $i * .07 }}s"
                 data-project-id="{{ $project['id'] }}">

                <!-- Header -->
                <div class="project-header">
                    <div class="project-header-info">
                        <h3 class="project-title" title="{{ $project['title'] }}">
                            {{ $project['title'] }}
                            @if($project['allow_any_product'])
                                <span class="free-badge">
                                    <i data-lucide="infinity" style="width:10px;height:10px"></i>
                                    Livre
                                </span>
                            @endif
                        </h3>
                        <div class="project-customer">
                            <i data-lucide="building-2" style="width:12px;height:12px"></i>
                            {{ $project['customer_name'] }}
                        </div>
                    </div>
                    <span class="status-badge {{ $project['status_value'] }}">
                        @if($project['status_value'] === 'draft')
                            <i data-lucide="file-edit" style="width:10px;height:10px"></i>
                        @else
                            <i data-lucide="play" style="width:10px;height:10px"></i>
                        @endif
                        {{ $project['status'] }}
                    </span>
                </div>

                <!-- Body -->
                <div class="project-body">
                    <div class="project-info-grid">
                        <div class="info-item">
                            <div class="info-label">Produtos</div>
                            <div class="info-value">{{ $project['products_count'] }}</div>
                        </div>
                        @if(!$project['allow_any_product'])
                        <div class="info-item">
                            <div class="info-label">Meta</div>
                            <div class="info-value">{{ number_format($project['total_target'], 0, ',', '.') }}<small style="font-size:.65em;font-weight:400"> kg</small></div>
                        </div>
                        @endif
                        <div class="info-item">
                            <div class="info-label">Entregue</div>
                            <div class="info-value success">{{ number_format($project['total_delivered'], 0, ',', '.') }}<small style="font-size:.65em;font-weight:400"> kg</small></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Aprovadas</div>
                            <div class="info-value success">{{ $project['approved_deliveries'] ?? 0 }}</div>
                        </div>
                        @if(!$project['allow_any_product'])
                        <div class="info-item">
                            <div class="info-label">Restante</div>
                            <div class="info-value {{ ($project['remaining'] ?? 0) > 0 ? 'warning' : 'success' }}">
                                {{ number_format($project['remaining'] ?? 0, 0, ',', '.') }}<small style="font-size:.65em;font-weight:400"> kg</small>
                            </div>
                        </div>
                        @endif
                        @if(($project['pending_deliveries'] ?? 0) > 0)
                        <div class="info-item">
                            <div class="info-label">Pendentes</div>
                            <div class="info-value warning">{{ $project['pending_deliveries'] }}</div>
                        </div>
                        @endif
                    </div>

                    @if(!$project['allow_any_product'])
                    <div class="progress-section">
                        <div class="progress-header">
                            <span class="progress-label">Progresso</span>
                            <span class="progress-percentage">{{ number_format($project['progress'], 1) }}%</span>
                        </div>
                        <div class="progress-bar-container">
                            <div class="progress-bar-fill" style="width: {{ min($project['progress'], 100) }}%"></div>
                        </div>
                    </div>
                    @endif

                    @if(($project['pending_deliveries'] ?? 0) > 0 && $project['status_value'] === 'active')
                        <div class="alert-inline alert-warning">
                            <i data-lucide="clock" style="width:14px;height:14px"></i>
                            {{ $project['pending_deliveries'] }} entrega(s) aguardando aprovacao
                        </div>
                    @endif
                </div>

                @if($project['status_value'] === 'draft')
                <div class="draft-banner">
                    <div class="draft-message">
                        <i data-lucide="alert-triangle" style="width:15px;height:15px;color:#d97706"></i>
                        Projeto em rascunho - Inicie-o para liberar o registro de entregas
                    </div>
                    <button class="btn btn-warning btn-sm start-project-btn"
                            data-project-id="{{ $project['id'] }}"
                            data-project-title="{{ $project['title'] }}"
                            data-tenant="{{ $currentTenant->slug }}">
                        <span class="spinner" id="spinner-{{ $project['id'] }}"></span>
                        <i data-lucide="play" style="width:13px;height:13px"></i>
                        Iniciar Projeto
                    </button>
                </div>
                @endif

                <div class="project-footer">
                    <div class="deadline-info">
                        <i data-lucide="calendar" style="width:14px;height:14px"></i>
                        @if($project['days_remaining'] !== null)
                            @if($project['days_remaining'] < 0)
                                <span class="deadline-urgent">Prazo vencido ha {{ abs($project['days_remaining']) }}d</span>
                            @elseif($project['days_remaining'] == 0)
                                <span class="deadline-urgent">Prazo hoje!</span>
                            @elseif($project['days_remaining'] <= 7)
                                <span class="deadline-urgent">{{ $project['days_remaining'] }} dias restantes</span>
                            @else
                                {{ $project['days_remaining'] }} dias restantes
                            @endif
                        @else
                            Sem prazo definido
                        @endif
                    </div>

                    <div class="btn-actions">
                        <a href="{{ route('delivery.projects.deliveries', ['tenant' => $currentTenant->slug, 'project' => $project['id']]) }}"
                           class="btn btn-ghost btn-sm">
                            <i data-lucide="history" style="width:13px;height:13px"></i>
                            Historico
                        </a>
                        @if($project['status_value'] === 'active')
                        <a href="{{ route('delivery.register', ['tenant' => $currentTenant->slug, 'project' => $project['id']]) }}"
                           class="btn btn-primary btn-sm">
                            <i data-lucide="plus" style="width:13px;height:13px"></i>
                            Registrar
                        </a>
                        <button class="btn btn-success btn-sm finalize-project-btn"
                                data-project-id="{{ $project['id'] }}"
                                data-project-title="{{ $project['title'] }}"
                                data-pending="{{ $project['pending_deliveries'] ?? 0 }}"
                                data-tenant="{{ $currentTenant->slug }}"
                                title="Marcar projeto como entregue ao cliente">
                            <span class="spinner" id="fin-spinner-{{ $project['id'] }}"></span>
                            <i data-lucide="check-circle" style="width:13px;height:13px"></i>
                            Finalizar
                        </button>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof lucide !== 'undefined') lucide.createIcons();

    function showToast(message, type) {
        type = type || 'success';
        var icons = { success: 'check-circle', error: 'alert-circle', info: 'info' };
        var container = document.getElementById('toast-container');
        var toast = document.createElement('div');
        toast.className = 'toast ' + type;
        toast.innerHTML = '<i data-lucide="' + icons[type] + '" style="width:18px;height:18px;flex-shrink:0"></i><span>' + message + '</span>';
        container.appendChild(toast);
        if (typeof lucide !== 'undefined') lucide.createIcons();
        setTimeout(function() { toast.style.opacity = '0'; setTimeout(function() { toast.remove(); }, 300); }, 4000);
    }

    // Iniciar Projeto
    document.querySelectorAll('.start-project-btn').forEach(function(btn) {
        btn.addEventListener('click', async function () {
            var projectId = this.dataset.projectId;
            var projectTitle = this.dataset.projectTitle;
            var tenantSlug = this.dataset.tenant;
            var spinner = document.getElementById('spinner-' + projectId);

            if (!confirm('Iniciar o projeto "' + projectTitle + '"?\n\nApos iniciado, o projeto aceita registros de entrega.')) return;
            this.disabled = true;
            if (spinner) spinner.style.display = 'inline-block';

            try {
                var res = await fetch('/' + tenantSlug + '/delivery/projects/' + projectId + '/start', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).content || '{{ csrf_token() }}',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    }
                });
                var result = await res.json();
                if (result.success) {
                    showToast(result.message, 'success');
                    setTimeout(function() { window.location.reload(); }, 1200);
                } else {
                    showToast(result.message, 'error');
                    this.disabled = false;
                    if (spinner) spinner.style.display = 'none';
                }
            } catch (e) {
                showToast('Erro ao iniciar projeto. Tente novamente.', 'error');
                this.disabled = false;
                if (spinner) spinner.style.display = 'none';
            }
        });
    });

    // Finalizar Projeto
    document.querySelectorAll('.finalize-project-btn').forEach(function(btn) {
        btn.addEventListener('click', async function () {
            var projectId = this.dataset.projectId;
            var projectTitle = this.dataset.projectTitle;
            var tenantSlug = this.dataset.tenant;
            var pending = parseInt(this.dataset.pending || '0');
            var spinner = document.getElementById('fin-spinner-' + projectId);

            if (pending > 0) {
                showToast('Existem ' + pending + ' entrega(s) pendentes de aprovacao. Aprove ou rejeite-as antes de finalizar.', 'error');
                return;
            }

            if (!confirm('Finalizar o projeto "' + projectTitle + '"?\n\nO status sera alterado para "Entregue ao Cliente".')) return;
            this.disabled = true;
            if (spinner) spinner.style.display = 'inline-block';

            try {
                var res = await fetch('/' + tenantSlug + '/delivery/projects/' + projectId + '/finalize', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).content || '{{ csrf_token() }}',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    }
                });
                var result = await res.json();
                if (result.success) {
                    showToast(result.message, 'success');
                    setTimeout(function() { window.location.reload(); }, 1400);
                } else {
                    showToast(result.message, 'error');
                    this.disabled = false;
                    if (spinner) spinner.style.display = 'none';
                }
            } catch (e) {
                showToast('Erro ao finalizar projeto. Tente novamente.', 'error');
                this.disabled = false;
                if (spinner) spinner.style.display = 'none';
            }
        });
    });

    // Animar progress bars
    setTimeout(function() {
        document.querySelectorAll('.progress-bar-fill').forEach(function(bar) {
            var w = bar.style.width;
            bar.style.width = '0';
            requestAnimationFrame(function() { bar.style.width = w; });
        });
    }, 200);
});
</script>
@endpush
@endsection
