@extends('layouts.bento')
@section('title', 'Cardápio do projeto')
@section('page-title', 'Cardápio')
@section('page-subtitle', $project->title)
@section('user-role', 'Registrador')
@section('content')
@php
    $params = ['tenant' => $currentTenant->slug, 'project' => $project->id, 'menu' => $menu->id];
    $storeMenu = fn($action) => route('delivery.projects.menus.store', $params + ['action' => $action]);
    $statusLabels = ['planned' => 'Planejado', 'calculated' => 'Calculado', 'awaiting_validation' => 'Aguardando validação', 'approved' => 'Aprovado', 'in_execution' => 'Em execução', 'finalized' => 'Finalizado'];
@endphp
<style>
.menu-plan{max-width:1280px;margin:auto;color:var(--color-text,#20332a)}.menu-plan section,.menu-plan details{padding:1rem;margin:1rem 0;background:var(--color-surface,#fff);border:1px solid var(--color-border,#dce7e0);border-radius:12px}.menu-plan h2{font-size:1.1rem;margin:1rem 0}.menu-plan .fields{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:.65rem;margin:.8rem 0}.menu-plan label{display:flex;flex-direction:column;gap:.3rem;font-size:.85rem}.menu-plan input,.menu-plan select{border:1px solid #c8d6cd;border-radius:7px;padding:.5rem;background:var(--color-surface,#fff);color:inherit;min-width:0}.menu-plan label[hidden]{display:none}.menu-plan button,.menu-plan .link{display:inline-block;padding:.6rem .9rem;border-radius:8px;background:#168a4d;color:white;border:0;cursor:pointer;margin:.2rem}.menu-plan table{width:100%;border-collapse:collapse;font-size:.8rem}.menu-plan td,.menu-plan th{padding:.5rem;border-bottom:1px solid #dce7e0;text-align:left}.menu-plan .scroll{overflow:auto}.menu-plan .muted{font-size:.85rem;color:var(--color-text-secondary,#52645a)}.menu-plan summary{cursor:pointer;font-weight:600}.menu-plan .error{color:#a32d2d}
</style>
<div class="menu-plan">
<p><a href="{{ route('delivery.projects.planning', ['tenant' => $currentTenant->slug, 'project' => $project->id]) }}#cardapios">← Planejamento do projeto</a></p>
@if(session('status'))<p role="status">{{ session('status') }}</p>@endif
@if($errors->any())<div role="alert" class="error">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<section><h2>{{ $menu->name }} · versão {{ $menu->version }}</h2><p>{{ $report['customer'] }} · {{ $report['beneficiary_group'] }} · {{ $menu->date_from->format('d/m/Y') }} a {{ $menu->date_to->format('d/m/Y') }}</p><p>Estado: <strong>{{ $statusLabels[$menu->status] }}</strong>. {{ $menu->calculation_snapshot_id ? 'Cálculo preservado #'.$menu->calculation_snapshot_id : 'Prévia dinâmica; ainda não preservada para revisão.' }}</p>
@if($menu->approved_at)<p>Aprovação registrada em {{ $menu->approved_at->format('d/m/Y H:i') }} · {{ $menu->reviewer_name }} · {{ $menu->professional_reference }} · {{ $menu->approval_reference }}</p>@endif
<form method="post" action="{{ $storeMenu('clone') }}">@csrf<button>Criar nova versão copiando este cardápio</button></form>
@php($actions = match($menu->status) { 'planned' => ['calculate' => 'Calcular e preservar para revisão'], 'calculated' => ['submit' => 'Enviar para validação', 'revise' => 'Voltar ao planejamento'], 'awaiting_validation' => ['revise' => 'Voltar ao planejamento'], 'approved' => ['start' => 'Iniciar execução'], 'in_execution' => ['finalize' => 'Finalizar cardápio'], default => [] })
@foreach($actions as $action => $label)<form method="post" action="{{ $storeMenu('transition') }}" style="display:inline-block">@csrf<input type="hidden" name="revision" value="{{ $menu->revision }}"><input type="hidden" name="action" value="{{ $action }}"><button>{{ $label }}</button></form>@endforeach
@if($menu->status === 'awaiting_validation')
@if($canApprove)<details><summary>Registrar aprovação técnica</summary><form method="post" action="{{ $storeMenu('transition') }}">@csrf<input type="hidden" name="revision" value="{{ $menu->revision }}"><input type="hidden" name="action" value="approve"><div class="fields"><label>Responsável pela análise<input name="reviewer_name" required maxlength="255"></label><label>Identificação/registro profissional informado<input name="professional_reference" required maxlength="255"></label><label>Referência do parecer ou documento de aprovação<input name="approval_reference" required maxlength="255"></label></div><p>O sistema registra a declaração do responsável sobre o cálculo preservado; não verifica sua habilitação profissional automaticamente.</p><button>Registrar aprovação</button></form></details>@else<p>A aprovação exige autorização específica na organização.</p>@endif
@endif
</section>
@if($menu->status === 'planned')
<section><h2>Adicionar refeição</h2><form method="post" action="{{ $storeMenu('meal') }}" data-menu-meal-form>@csrf<input type="hidden" name="revision" value="{{ $menu->revision }}">
<div class="fields"><label>Data<input type="date" name="date" min="{{ $menu->date_from->format('Y-m-d') }}" max="{{ $menu->date_to->format('Y-m-d') }}" required></label><label>Refeição<input name="name" required maxlength="100"></label><label>Pessoas previstas<input type="number" name="planned_people" min="1" max="100000000" required></label></div>
<div data-menu-item-rows>@include('delivery.partials.menu-item-fields', ['item' => [], 'index' => 0])</div><button type="button" data-menu-add>Adicionar item</button><button>Salvar refeição</button></form></section>
@foreach($menu->days as $day)@foreach($day->meals as $meal)
<details><summary>{{ $day->date->format('d/m/Y') }} · {{ $meal->name }} · {{ $meal->planned_people }} pessoas</summary>
<form method="post" action="{{ $storeMenu('meal') }}" data-menu-meal-form>@csrf<input type="hidden" name="revision" value="{{ $menu->revision }}"><input type="hidden" name="meal_id" value="{{ $meal->id }}"><div class="fields"><label>Data<input type="date" name="date" value="{{ $day->date->format('Y-m-d') }}" required></label><label>Refeição<input name="name" value="{{ $meal->name }}" required maxlength="100"></label><label>Pessoas previstas<input name="planned_people" type="number" min="1" max="100000000" value="{{ $meal->planned_people }}" required></label></div>
<div data-menu-item-rows>@foreach($meal->items as $index => $item)@include('delivery.partials.menu-item-fields', ['item' => $item->attributesToArray(), 'index' => $index])@endforeach</div><button type="button" data-menu-add>Adicionar item</button><button>Salvar refeição</button></form>
<form method="post" action="{{ $storeMenu('remove-meal') }}">@csrf<input type="hidden" name="revision" value="{{ $menu->revision }}"><input type="hidden" name="meal_id" value="{{ $meal->id }}"><button>Remover refeição</button></form></details>
@endforeach @endforeach
@endif
<section><h2>Análise do cardápio</h2><p>O cálculo nutricional preservado permanece igual durante revisão e execução. A comparação física é atualizada com as distribuições do período.</p>
@foreach(['pdf' => 'PDF', 'xlsx' => 'XLSX'] as $format => $label)<a class="link" href="{{ route('delivery.projects.reports.export', ['tenant' => $currentTenant->slug, 'project' => $project->id, 'analysis_mode' => 'menu', 'menu_id' => $menu->id, 'competency' => 'planning', 'type' => 'customer', 'format' => $format]) }}">Exportar {{ $label }}</a>@endforeach
<div class="scroll">@include('delivery.partials.menu-analysis')</div></section>
</div>
<script>
document.querySelectorAll('[data-menu-meal-form]').forEach(function (form) {
    const container = form.querySelector('[data-menu-item-rows]');
    const template = document.querySelector('[data-menu-item]').cloneNode(true);
    let next = container.children.length;
    function bind(row) {
        const kind = row.querySelector('[data-menu-kind]');
        function sync() {
            const recipe = kind.value === 'recipe';
            row.querySelector('[data-menu-product]').disabled = recipe;
            row.querySelector('[data-menu-recipe]').disabled = !recipe;
            row.querySelector('[data-menu-product-label]').hidden = recipe;
            row.querySelector('[data-menu-recipe-label]').hidden = !recipe;
            row.querySelector('[data-menu-unit]').value = recipe ? 'portion' : 'g';
            row.querySelector('[data-menu-quantity-label]').textContent = recipe ? 'Porções por pessoa' : 'Gramas brutas por pessoa';
        }
        kind.addEventListener('change', sync); sync();
        row.querySelector('[data-menu-remove]').addEventListener('click', () => { if (container.children.length > 1) row.remove(); });
    }
    container.querySelectorAll('[data-menu-item]').forEach(bind);
    form.querySelector('[data-menu-add]').addEventListener('click', function () {
        if (container.children.length >= 100) return;
        const row = template.cloneNode(true);
        row.querySelectorAll('[name]').forEach(field => { field.name = field.name.replace(/\[\d+\]/, '[' + next + ']'); field.value = ''; });
        row.querySelector('[data-menu-kind]').value = 'product'; next++;
        bind(row); container.appendChild(row);
    });
});
</script>
@endsection
