@extends('layouts.campo')
@section('title', 'Editar detalhes da OS')
@section('page-title', 'Editar ordem de serviço')
@php($bentoNavigation = \App\Support\PortalNavigation::make('services', 'queue', $tenant->slug))
@section('content')
    @include('services._workspace-style')
    <div class="service-grid">
        @include('services._workspace-messages')
        <section class="sg-panel">@include('services._order-details-form')</section>
    </div>
    <script>
    document.querySelector('.order-details-form').addEventListener('submit', event => {
        const form=event.currentTarget;
        if(form.dataset.submitting){event.preventDefault();return;}
        form.dataset.submitting='true';form.setAttribute('aria-busy','true');
        const button=form.querySelector('button[type="submit"]');button.disabled=true;button.textContent='Salvando…';
    });
    </script>
@endsection
