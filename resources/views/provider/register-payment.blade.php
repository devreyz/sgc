@extends('layouts.bento')

@section('title', 'Registrar Pagamento')
@section('page-title', 'Registrar Pagamento')
@section('user-role', 'Prestador de Serviço')

@section('navigation')
<nav class="nav-tabs">
    <a href="{{ route('provider.dashboard') }}" class="nav-tab">Dashboard</a>
    <a href="{{ route('provider.orders') }}" class="nav-tab active">Ordens de Serviço</a>
    <a href="{{ route('provider.financial') }}" class="nav-tab">Financeiro</a>
    <a href="{{ route('provider.financial') }}" class="nav-tab">Carteira</a>
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
                <h2 class="font-bold" style="font-size:1.5rem;">Registrar Pagamento do Cliente</h2>
                <p class="text-muted text-sm">Ordem #{{ $order->number }}</p>
            </div>
            <a href="{{ route('provider.orders.show', $order->id) }}" class="btn btn-outline">← Voltar</a>
        </div>
    </div>

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
                <span class="info-label">Cliente</span>
                <span class="info-value">
                    @if($order->associate)
                        {{ $order->associate->name }}
                    @else
                        @php
                            preg_match('/\[PESSOA AVULSA\]\nNome:\s*(.+)/m', $order->notes ?? '', $matches);
                        @endphp
                        {{ $matches[1] ?? 'Avulso' }}
                    @endif
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Data Execução</span>
                <span class="info-value">{{ $order->execution_date ? $order->execution_date->format('d/m/Y') : '-' }}</span>
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
                <span class="info-label">Valor Total da Ordem</span>
                <span class="info-value" style="font-size:1.25rem;color:var(--color-primary);">R$ {{ number_format($order->final_price, 2, ',', '.') }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Já Recebido do Cliente</span>
                <span class="info-value">R$ {{ number_format($totalPaid, 2, ',', '.') }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Falta Receber</span>
                <span class="info-value" style="font-size:1.25rem;color:var(--color-warning);">R$ {{ number_format($clientRemaining, 2, ',', '.') }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Seu Valor (Prestador)</span>
                <span class="info-value" style="color:var(--color-success);">R$ {{ number_format($order->provider_payment, 2, ',', '.') }}</span>
            </div>
        </div>
    </div>

    <!-- Alerta Informativo -->
    <div class="bento-card col-span-full" style="border-left:4px solid var(--color-info);background:rgba(59,130,246,0.05);">
        <div style="display:flex;gap:1rem;align-items:start;">
            <i data-lucide="info" style="min-width:1.5rem;height:1.5rem;color:var(--color-info);"></i>
            <div>
                <p style="color:var(--color-info);font-weight:600;margin-bottom:0.5rem;">Como funciona?</p>
                <p style="color:var(--color-info);font-size:0.875rem;">
                    Registre aqui os pagamentos que o cliente fizer a você. Se for PIX/transferência, anexe o comprovante. 
                    Se for dinheiro, você pode registrar agora e depois enviar o comprovante de depósito quando entregar/depositar o valor na cooperativa.
                    A administração confirmará o recebimento.
                </p>
            </div>
        </div>
    </div>

    <!-- Formulário de Registro de Pagamento -->
    @if($clientRemaining > 0)
    <div class="bento-card col-span-full">
        <h3 class="font-bold mb-3" style="font-size:1.125rem;">
            <i data-lucide="credit-card" style="width:1rem;height:1rem;display:inline;vertical-align:text-bottom;"></i>
            Registrar Novo Pagamento
        </h3>

        <form action="{{ route('provider.orders.store-payment', $order->id) }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="form-grid">
                <div class="form-group">
                    <label for="amount" class="form-label">Valor Recebido *</label>
                    <input type="number" 
                           name="amount" 
                           id="amount" 
                           class="form-input @error('amount') error @enderror" 
                           step="0.01" 
                           min="0.01" 
                           max="{{ $clientRemaining }}"
                           value="{{ old('amount', $clientRemaining) }}" 
                           required>
                    <span class="form-help">Máximo: R$ {{ number_format($clientRemaining, 2, ',', '.') }}</span>
                    @error('amount')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="payment_date" class="form-label">Data do Pagamento *</label>
                    <input type="date" 
                           name="payment_date" 
                           id="payment_date" 
                           class="form-input @error('payment_date') error @enderror" 
                           value="{{ old('payment_date', date('Y-m-d')) }}" 
                           required>
                    @error('payment_date')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="payment_method" class="form-label">Forma de Pagamento *</label>
                    <select name="payment_method" 
                            id="payment_method" 
                            class="form-input @error('payment_method') error @enderror" 
                            required>
                        <option value="">Selecione...</option>
                        <option value="dinheiro" {{ old('payment_method') == 'dinheiro' ? 'selected' : '' }}>Dinheiro</option>
                        <option value="pix" {{ old('payment_method') == 'pix' ? 'selected' : '' }}>PIX</option>
                        <option value="transferencia" {{ old('payment_method') == 'transferencia' ? 'selected' : '' }}>Transferência</option>
                        <option value="cartao" {{ old('payment_method') == 'cartao' ? 'selected' : '' }}>Cartão</option>
                        <option value="cheque" {{ old('payment_method') == 'cheque' ? 'selected' : '' }}>Cheque</option>
                        <option value="boleto" {{ old('payment_method') == 'boleto' ? 'selected' : '' }}>Boleto</option>
                    </select>
                    @error('payment_method')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="receipt" class="form-label">Comprovante</label>
                    <input type="file" 
                           name="receipt" 
                           id="receipt" 
                           class="form-input @error('receipt') error @enderror" 
                           accept="image/*,application/pdf"
                           data-compress-image
                           data-preview-container="#receipt-preview">
                    <span class="form-help">Opcional. Pode ser enviado depois.</span>
                    @error('receipt')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                    <div id="receipt-preview" class="preview-container"></div>
                </div>
            </div>

            <div class="form-group">
                <label for="notes" class="form-label">Observações</label>
                <textarea name="notes" 
                          id="notes" 
                          class="form-input @error('notes') error @enderror" 
                          rows="3"
                          placeholder="Ex: Cliente pagou em espécie, vou depositar amanhã...">{{ old('notes') }}</textarea>
                @error('notes')
                    <span class="form-error">{{ $message }}</span>
                @enderror
            </div>

            <div style="margin-top:1.5rem;display:flex;gap:1rem;justify-content:flex-end;">
                <a href="{{ route('provider.orders.show', $order->id) }}" class="btn btn-outline">Cancelar</a>
                <button type="submit" class="btn btn-primary" id="paymentSubmitBtn">
                    <i data-lucide="check" style="width:1rem;height:1rem;"></i>
                    <span class="btn-text">Registrar Pagamento</span>
                    <span class="btn-spinner" style="display:none;margin-left:0.5rem;">Enviando...</span>
                </button>
            </div>
        </form>
    </div>
    @else
    <div class="bento-card col-span-full" style="border-left:4px solid var(--color-success);background:rgba(16,185,129,0.05);">
        <p style="color:var(--color-success);font-weight:500;">
            <i data-lucide="check-circle" style="width:1rem;height:1rem;display:inline;vertical-align:text-bottom;"></i>
            O cliente já pagou o valor total desta ordem!
        </p>
    </div>
    @endif

</div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form[action^="/provider/orders/"]');
    const submitBtn = document.getElementById('paymentSubmitBtn');
    if (form && submitBtn) {
        form.addEventListener('submit', function() {
            submitBtn.disabled = true;
            const text = submitBtn.querySelector('.btn-text');
            const spinner = submitBtn.querySelector('.btn-spinner');
            if (text) text.style.display = 'none';
            if (spinner) spinner.style.display = 'inline-block';
        });
    }
});
</script>
@endpush
