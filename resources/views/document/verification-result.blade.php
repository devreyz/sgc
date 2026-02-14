@extends('layouts.bento')

@section('title', 'Verificação de Documento')
@section('page-title', 'Verificação de Documento')

@section('content')
<div style="max-width: 800px; margin: 0 auto; padding: 40px;">
    <div style="text-align: center; margin-bottom: 40px;">
        <div style="font-size: 4rem; margin-bottom: 1rem;">
            @if($status === 'verified')
                ✅
            @elseif($status === 'not_found')
                ❌
            @else
                ⚠️
            @endif
        </div>

        <h1 style="font-size: 2rem; margin-bottom: 1rem; color: {{ $status === 'verified' ? 'var(--color-success)' : 'var(--color-danger)' }};">
            @if($status === 'verified')
                Documento Autêntico
            @elseif($status === 'not_found')
                Documento Não Encontrado
            @else
                Status Desconhecido
            @endif
        </h1>

        <p style="font-size: 1.125rem; color: var(--color-text-secondary); margin-bottom: 2rem;">
            {{ $message }}
        </p>
    </div>

    @if($verification)
    <div style="background: var(--color-bg); border-radius: var(--radius-lg); padding: 2rem; border: 1px solid var(--color-border);">
        <h2 style="font-size: 1.25rem; font-weight: bold; margin-bottom: 1.5rem;">Detalhes do Documento</h2>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <div>
                <div style="font-size: 0.875rem; color: var(--color-text-secondary); margin-bottom: 0.25rem;">Template</div>
                <div style="font-weight: 600;">{{ $verification->template->name ?? 'Não especificado' }}</div>
            </div>

            <div>
                <div style="font-size: 0.875rem; color: var(--color-text-secondary); margin-bottom: 0.25rem;">Data de Geração</div>
                <div style="font-weight: 600;">{{ $verification->generated_at->format('d/m/Y H:i') }}</div>
            </div>

            <div>
                <div style="font-size: 0.875rem; color: var(--color-text-secondary); margin-bottom: 0.25rem;">Gerado Por</div>
                <div style="font-weight: 600;">{{ $verification->generator->name ?? 'Sistema' }}</div>
            </div>

            <div>
                <div style="font-size: 0.875rem; color: var(--color-text-secondary); margin-bottom: 0.25rem;">Verificações</div>
                <div style="font-weight: 600;">{{ $verification->verification_count }}x</div>
            </div>

            @if($verification->verified_at)
            <div style="grid-column: 1 / -1;">
                <div style="font-size: 0.875rem; color: var(--color-text-secondary); margin-bottom: 0.25rem;">Última Verificação</div>
                <div style="font-weight: 600;">{{ $verification->verified_at->format('d/m/Y H:i') }}</div>
            </div>
            @endif
        </div>

        <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--color-border);">
            <div style="font-size: 0.875rem; color: var(--color-text-secondary); margin-bottom: 0.5rem;">Hash de Verificação</div>
            <code style="display: block; padding: 0.75rem; background: rgba(0,0,0,0.05); border-radius: var(--radius-md); font-size: 0.75rem; word-break: break-all;">
                {{ $verification->hash }}
            </code>
        </div>
    </div>

    <div style="margin-top: 2rem; text-align: center; font-size: 0.875rem; color: var(--color-text-secondary);">
        <p>Este documento foi verificado através do sistema da cooperativa.</p>
        <p>Em caso de dúvidas, entre em contato conosco.</p>
    </div>
    @else
    <div style="text-align: center; margin-top: 2rem;">
        <a href="/" class="btn btn-primary">Voltar para Home</a>
    </div>
    @endif
</div>

<style>
    .btn {
        display: inline-block;
        padding: 0.75rem 1.5rem;
        border-radius: var(--radius-md);
        text-decoration: none;
        font-weight: 600;
        transition: all 0.2s;
    }

    .btn-primary {
        background: var(--color-primary);
        color: white;
    }

    .btn-primary:hover {
        background: var(--color-primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    }
</style>
@endsection
