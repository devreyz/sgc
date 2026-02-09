@extends('layouts.bento')

@section('title', 'Registrar Serviço')
@section('page-title', 'Registrar Serviço')
@section('user-role', 'Prestador de Serviço')

@section('navigation')
<nav class="nav-tabs">
    <a href="{{ route('provider.dashboard') }}" class="nav-tab">Dashboard</a>
    <a href="{{ route('provider.orders') }}" class="nav-tab active">Ordens de Serviço</a>
    <a href="{{ route('provider.works') }}" class="nav-tab">Meus Serviços</a>
    <form action="{{ route('logout') }}" method="POST" style="display: inline;">
        @csrf
        <button type="submit" class="nav-tab" style="background: none; cursor: pointer;">Sair</button>
    </form>
</nav>
@endsection

@section('content')
<div class="bento-grid">
    <!-- Order Info -->
    <div class="bento-card col-span-4">
        <h2 class="font-bold mb-4" style="font-size: 1.25rem;">Informações da Ordem</h2>
        
        <div style="display: flex; flex-direction: column; gap: 1rem;">
            <div>
                <div class="text-xs text-muted">Projeto</div>
                <div class="font-semibold">{{ $order->project->title }}</div>
            </div>

            <div>
                <div class="text-xs text-muted">Cliente</div>
                <div class="font-semibold">{{ $order->project->customer->name ?? '-' }}</div>
            </div>

            <div>
                <div class="text-xs text-muted">Associado</div>
                <div class="font-semibold">{{ $order->associate->name ?? '-' }}</div>
            </div>

            <div>
                <div class="text-xs text-muted">Equipamento</div>
                <div class="font-semibold">{{ $order->equipment->name ?? '-' }}</div>
            </div>

            <div>
                <div class="text-xs text-muted">Data Agendada</div>
                <div class="font-semibold">{{ $order->scheduled_date ? $order->scheduled_date->format('d/m/Y') : '-' }}</div>
            </div>

            <div>
                <div class="text-xs text-muted">Valor Estimado</div>
                <div class="font-semibold text-primary">R$ {{ number_format($order->estimated_value ?? 0, 2, ',', '.') }}</div>
            </div>

            @if($order->description)
            <div>
                <div class="text-xs text-muted">Observações</div>
                <div class="text-sm">{{ $order->description }}</div>
            </div>
            @endif
        </div>
    </div>

    <!-- Work Form -->
    <div class="bento-card col-span-8">
        <h2 class="font-bold mb-4" style="font-size: 1.25rem;">Registrar Serviço Prestado</h2>

        @if ($errors->any())
            <div class="mb-4" style="padding: 1rem; background: rgba(239, 68, 68, 0.1); border-radius: var(--radius-md); border: 1px solid var(--color-danger);">
                <p class="text-danger font-semibold mb-2">Erros encontrados:</p>
                <ul style="list-style: disc; margin-left: 1.5rem;">
                    @foreach ($errors->all() as $error)
                        <li class="text-danger text-sm">{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('provider.work.store', $order->id) }}" enctype="multipart/form-data">
            @csrf

            <div class="form-group">
                <label class="form-label">Data do Serviço *</label>
                <input type="date" name="work_date" class="form-input" value="{{ old('work_date', date('Y-m-d')) }}" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label">Horas Trabalhadas *</label>
                    <input type="number" name="hours_worked" class="form-input" step="0.5" min="0" value="{{ old('hours_worked') }}" placeholder="Ex: 8.5" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Valor (R$) *</label>
                    <input type="number" name="value" class="form-input" step="0.01" min="0" value="{{ old('value', $order->estimated_value) }}" placeholder="0.00" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Descrição do Serviço *</label>
                <textarea name="description" class="form-textarea" required placeholder="Descreva detalhadamente o serviço realizado...">{{ old('description') }}</textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Comprovante (PDF, JPG, PNG - máx 5MB)</label>
                <input type="file" name="receipt_path" class="form-input" accept=".pdf,.jpg,.jpeg,.png">
                <p class="text-xs text-muted mt-2">Envie fotos ou documentos que comprovem o serviço realizado.</p>
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                <button type="submit" class="btn btn-primary" style="flex: 1;">
                    ✅ Registrar Serviço
                </button>
                <a href="{{ route('provider.orders') }}" class="btn btn-outline" style="flex: 1;">
                    ❌ Cancelar
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
