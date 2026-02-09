@extends('layouts.bento')

@section('title', 'Perfil Não Encontrado')
@section('page-title', 'Portal do Associado')
@section('user-role', 'Associado')

@section('content')
<div class="bento-grid">
    <div class="bento-card col-span-full">
        <div style="text-align: center; padding: 3rem;">
            <div style="font-size: 3rem; margin-bottom: 1rem;">⚠️</div>
            <h2 style="font-size: 1.5rem; font-weight: 600; margin-bottom: 1rem; color: var(--color-text);">
                Perfil de Associado Não Encontrado
            </h2>
            <p style="color: var(--color-text-muted); margin-bottom: 2rem;">
                Seu usuário tem a função de <strong>Associado</strong>, 
                mas não possui um cadastro de associado vinculado.
            </p>
            <p style="color: var(--color-text-muted); margin-bottom: 2rem;">
                Entre em contato com o administrador do sistema para criar seu perfil de associado.
            </p>
            
            @if(Auth::user()->hasAnyRole(['super_admin', 'admin', 'financeiro']))
            <div style="margin-top: 2rem;">
                <a href="/admin" class="btn btn-primary">
                    Ir para o Painel Administrativo
                </a>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
