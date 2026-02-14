@extends('layouts.bento')

@section('title', 'Solicitar Pagamento')
@section('page-title', 'Solicitar Pagamento')
@section('user-role', 'Prestador de Serviço')

@section('navigation')
<nav class="nav-tabs">
    <a href="{{ route('provider.dashboard') }}" class="nav-tab">Dashboard</a>
    <a href="{{ route('provider.orders') }}" class="nav-tab">Ordens de Serviço</a>
    <a href="{{ route('provider.financial') }}" class="nav-tab active">Financeiro</a>
    <form action="{{ route('logout') }}" method="POST" style="display: inline;">
        @csrf
        <button type="submit" class="nav-tab" style="background: none; cursor: pointer;">Sair</button>
    </form>
</nav>
@endsection

@section('content')
<style>
    .info-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:0.75rem; }
    .info-item { display:flex; flex-direction:column; padding:0.5rem 0; border-bottom:1px solid var(--color-border); }
    .info-label { color:var(--color-text-muted); font-size:0.8125rem; }
    .info-value { font-weight:600; color:var(--color-text); }
    .form-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:1rem; }
    .preview-container { max-width:300px; margin-top:0.5rem; }
    .preview-container img { max-width:100%; height:auto; border-radius:var(--radius-md); border:2px solid var(--color-border); }
    @media(max-width:768px) { .info-grid,.form-grid { grid-template-columns:1fr; } }
</style>

<div class="bento-grid">

    <!-- Header -->
    <div class="bento-card col-span-full">
        <div class="flex justify-between items-center" style="flex-wrap:wrap;gap:1rem;">
            <div>
                <h2 class="font-bold" style="font-size:1.5rem;">Solicitar Pagamento</h2>
                <p class="text-muted text-sm">Ordem #{{ $order->number }}</p>
            </div>
            <a href="{{ route('provider.orders.show', $order->id) }}" class="btn btn-outline">← Voltar</a>
        </div>
    </div>

    @if($existingRequest)
    <!-- Alerta de solicitação pendente -->
    <div class="bento-card col-span-full" style="border-left:4px solid var(--color-warning);background:rgba(245,158,11,0.05);">
        <div style="display:flex;gap:1rem;align-items:start;">
            <i data-lucide="alert-triangle" style="min-width:1.5rem;height:1.5rem;color:#92400e;"></i>
            <div>
                <p style="color:#92400e;font-weight:600;margin-bottom:0.5rem;">Solicitação Pendente</p>
                <p style="color:#92400e;">Você já possui uma solicitação de pagamento pendente para esta ordem no valor de <strong>R$ {{ number_format($existingRequest->amount, 2, ',', '.') }}</strong>, enviada em {{ $existingRequest->request_date->format('d/m/Y H:i') }}.</p>
                @if($existingRequest->description)
                <p style="color:#92400e;margin-top:0.5rem;font-size:0.875rem;">Descrição: {{ $existingRequest->description }}</p>
                @endif
            </div>
        </div>
    </div>
    @endif

    <!-- Informações da Ordem -->
    <div class="bento-card col-span-full md:col-span-6">
        <h3 class="font-bold mb-3" style="font-size:1.125rem;">
            <i data-lucide="file-text" style="width:1rem;height:1rem;display:inline;vertical-align:text-bottom;"></i>
            Detalhes da Ordem
        </h3>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Número</span>
                <span class="info-value">#{{ $order->number }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Serviço</span>
                <span class="info-value">{{ $order->service->name }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Data Execução</span>
                <span class="info-value">{{ $order->execution_date ? $order->execution_date->format('d/m/Y') : '-' }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Status</span>
                <span class="info-value">{{ $order->status->getLabel() }}</span>
            </div>
        </div>
    </div>

    <!-- Informações Financeiras -->
    <div class="bento-card col-span-full md:col-span-6">
        <h3 class="font-bold mb-3" style="font-size:1.125rem;">
            <i data-lucide="dollar-sign" style="width:1rem;height:1rem;display:inline;vertical-align:text-bottom;"></i>
            Valores
        </h3>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Saldo Disponível</span>
                <span class="info-value" style="font-size:1.5rem;color:var(--color-success);">R$ {{ number_format($order->provider_remaining, 2, ',', '.') }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Valor Total da OS</span>
                <span class="info-value">R$ {{ number_format($order->provider_payment ?? 0, 2, ',', '.') }}</span>
            </div>
        </div>
    </div>

    <!-- Formulário de Solicitação -->
    @if($order->provider_remaining > 0 && !$existingRequest)
    <div class="bento-card col-span-full">
        <h3 class="font-bold mb-3" style="font-size:1.125rem;">
            <i data-lucide="receipt" style="width:1rem;height:1rem;display:inline;vertical-align:text-bottom;"></i>
            Nova Solicitação de Pagamento
        </h3>

        <form action="{{ route('provider.financial.store-request', $order->id) }}" method="POST">
            @csrf

            <div class="form-grid">
                <div class="form-group">
                    <label for="amount" class="form-label">Valor a Solicitar *</label>
                    <input type="number" 
                           name="amount" 
                           id="amount" 
                           class="form-input @error('amount') error @enderror" 
                           step="0.01" 
                           min="0.01" 
                           max="{{ $order->provider_remaining }}"
                           value="{{ old('amount', $order->provider_remaining) }}" 
                           required>
                    <span class="form-help">Máximo: R$ {{ number_format($order->provider_remaining, 2, ',', '.') }}</span>
                    @error('amount')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="bank_info" class="form-label">Dados Bancários *</label>
                    <textarea name="bank_info" 
                              id="bank_info" 
                              class="form-input @error('bank_info') error @enderror" 
                              rows="4"
                              placeholder="Ex: 
Banco: 001 - Banco do Brasil
Agência: 1234-5
Conta: 12345678-9
CPF: 123.456.789-00
PIX: seu@email.com"
                              required>{{ old('bank_info', $provider->pix_key ? 'PIX: ' . $provider->pix_key : '') }}</textarea>
                    @error('bank_info')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="form-group">
                <label for="description" class="form-label">Descrição / Observações</label>
                <textarea name="description" 
                          id="description" 
                          class="form-input @error('description') error @enderror" 
                          rows="3"
                          placeholder="Adicione informações relevantes sobre esta solicitação...">{{ old('description') }}</textarea>
                @error('description')
                    <span class="form-error">{{ $message }}</span>
                @enderror
            </div>

            <div style="margin-top:1.5rem;display:flex;gap:1rem;justify-content:flex-end;">
                <a href="{{ route('provider.financial') }}" class="btn btn-outline">Cancelar</a>
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="send" style="width:1rem;height:1rem;"></i>
                    Enviar Solicitação
                </button>
            </div>
        </form>
    </div>
    @elseif($order->provider_remaining <= 0 && !$existingRequest)
    <div class="bento-card col-span-full" style="border-left:4px solid var(--color-success);background:rgba(16,185,129,0.05);">
        <p style="color:var(--color-success);font-weight:500;">
            <i data-lucide="check-circle" style="width:1rem;height:1rem;display:inline;vertical-align:text-bottom;"></i>
            Não há valores disponíveis para solicitar. Você já solicitou ou recebeu o valor total desta ordem.
        </p>
    </div>
    @endif

</div>

@endsection
