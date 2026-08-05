@extends('layouts.bento')

@section('title', 'Detalhes - '.$config['label'])
@section('page-title', $config['label'])
@section('page-subtitle', $tenant->name)
@section('user-role', 'Financeiro')
@php $bentoNavigation = \App\Support\PortalNavigation::make('finance', 'management', $tenant->slug); @endphp

@section('content')
@include('finance.partials.styles')
<style>
    .fmd-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1px;background:var(--color-border);border:1px solid var(--color-border);border-radius:7px;overflow:hidden}.fmd-item{padding:.85rem;background:var(--color-surface);min-width:0}.fmd-item span{display:block;color:var(--color-text-secondary);font-size:.76rem;margin-bottom:.25rem}.fmd-item strong{display:block;overflow-wrap:anywhere;font-size:.94rem}.fmd-wide{grid-column:1/-1}@media(max-width:640px){.fmd-grid{grid-template-columns:1fr}.fmd-wide{grid-column:auto}}
</style>
<main class="fin-shell">
    <header class="fin-head"><div><h1>Detalhes</h1><p>Registro #{{ $record['id'] }}</p></div><div class="fin-actions">
        <a class="fin-btn" href="{{ route('finance.management.index', ['tenant'=>$tenant->slug,'module'=>$module]) }}"><i data-lucide="arrow-left"></i> Voltar</a>
        @if($config['printable'])<a class="fin-btn fin-btn-primary" target="_blank" rel="noopener" href="{{ route('finance.management.print', ['tenant'=>$tenant->slug,'module'=>$module,'record'=>$record['id']]) }}"><i data-lucide="printer"></i> Imprimir</a>@endif
    </div></header>
    <section class="fin-card"><div class="fmd-grid">
        @foreach($record as $key => $value)
            @php $field = $config['fields'][$key] ?? null; $label = $field['label'] ?? \Illuminate\Support\Str::headline($key); @endphp
            <div class="fmd-item {{ in_array($key, ['description','notes'], true) ? 'fmd-wide' : '' }}"><span>{{ $label }}</span><strong>{{ is_bool($value) ? ($value ? 'Sim' : 'Nao') : ($value === null || $value === '' ? '-' : $value) }}</strong></div>
        @endforeach
    </div></section>
</main>
@endsection
