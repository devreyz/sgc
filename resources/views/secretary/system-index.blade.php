@extends('layouts.campo')

@section('title', 'PDFs do sistema')
@section('page-title', 'PDFs do sistema')

@php
    $bentoNavigation = \App\Support\PortalNavigation::make('secretary', 'system', $tenant->slug);
    $groups = collect($definitions)->groupBy(function ($entry, $key) {
        if ($entry['type'] === 'receipt' || preg_match('/receipt|service_execution/', $key)) return 'Comprovantes e recibos';
        if ($entry['type'] === 'contract' || preg_match('/sheet|folha|term|service_order/', $key)) return 'Formulários e termos';
        return 'Relatórios e demonstrativos';
    }, preserveKeys: true);
@endphp

@section('content')
<main class="mx-auto grid max-w-5xl gap-6">
    <header class="grid gap-2">
        <h1 class="text-xl font-bold">PDFs do sistema</h1>
        <p class="text-sm text-[var(--color-text-secondary)]">Configure a apresentação dos relatórios, comprovantes e formulários gerados pelo SGC.</p>
        <a class="text-sm font-semibold sm:self-start" href="{{ route('secretary.index', ['tenant' => $tenant->slug]) }}"><i class="ph ph-arrow-left" aria-hidden="true"></i> Acervo</a>
    </header>
    <nav class="flex flex-wrap gap-2" aria-label="Categorias de PDFs">
        @foreach($groups as $label => $entries)
            <a class="rounded-full border border-[var(--color-border)] px-3 py-2 text-sm" href="#{{ \Illuminate\Support\Str::slug($label) }}">{{ $label }}</a>
        @endforeach
    </nav>
    @foreach($groups as $label => $entries)
        <section class="grid scroll-mt-24 gap-2" id="{{ \Illuminate\Support\Str::slug($label) }}">
            <h2 class="font-bold">{{ $label }}</h2>
            <div class="divide-y divide-[var(--color-border)] rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)]">
                @foreach($entries as $key => $definition)
                    @php($variants = $configured[$key] ?? collect())
                    <article class="grid gap-2 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="min-w-0 flex-1">
                                <h3 class="text-sm font-semibold">{{ $definition['label'] }}</h3>
                                <p class="mt-1 text-sm text-[var(--color-text-secondary)]">{{ $definition['description'] }}</p>
                            </div>
                            @if($canManage)
                                <a class="rounded-lg border border-[var(--color-border)] px-3 py-2 text-sm font-semibold" href="{{ $variants->count() === 1 ? route('secretary.templates.edit', ['tenant' => $tenant->slug, 'template' => $variants->first()]) : route('secretary.system.edit', ['tenant' => $tenant->slug, 'key' => $key]) }}">
                                    <i class="ph ph-sliders-horizontal" aria-hidden="true"></i> {{ $variants->count() > 1 ? 'Nova configuração' : 'Configurar' }}
                                </a>
                            @endif
                        </div>
                        @if($canManage && $variants->count() > 1)
                            <ul class="flex flex-wrap gap-2">
                                @foreach($variants as $variant)
                                    <li><a class="rounded-lg bg-[var(--color-bg)] px-3 py-2 text-sm underline" href="{{ route('secretary.templates.edit', ['tenant' => $tenant->slug, 'template' => $variant]) }}">{{ $variant->name }}{{ $variant->project_type ? ' · '.$variant->project_type : '' }}{{ $variant->is_active ? '' : ' · Inativo' }}</a></li>
                                @endforeach
                            </ul>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>
    @endforeach
</main>
@endsection
