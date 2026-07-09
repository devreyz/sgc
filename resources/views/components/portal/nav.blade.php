@props([
    'portal',
    'active' => null,
    'tenant' => null,
])

@php
    $tenantParam = $tenant ?: (
        is_object(request()->route('tenant'))
            ? request()->route('tenant')->slug
            : request()->route('tenant')
    );

    $routeOrHome = function (string $name, array $params = []) use ($tenantParam) {
        return $tenantParam ? route($name, array_merge(['tenant' => $tenantParam], $params)) : url('/');
    };

    $items = match ($portal) {
        'associate' => [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'route' => 'associate.dashboard'],
            ['key' => 'projects', 'label' => 'Projetos', 'route' => 'associate.projects'],
            ['key' => 'deliveries', 'label' => 'Entregas', 'route' => 'associate.deliveries'],
            ['key' => 'ledger', 'label' => 'Extrato', 'route' => 'associate.ledger'],
        ],
        'delivery' => [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'route' => 'delivery.dashboard'],
            ['key' => 'deliveries', 'label' => 'Entregas', 'route' => 'delivery.all-deliveries'],
            ['key' => 'projects', 'label' => 'Projetos', 'route' => 'delivery.projects-list'],
            ['key' => 'register', 'label' => 'Registrar', 'route' => 'delivery.register'],
            ['key' => 'sheets', 'label' => 'Fichas', 'route' => 'delivery.sheet.index'],
        ],
        'provider' => [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'route' => 'provider.dashboard'],
            ['key' => 'orders', 'label' => 'Ordens de Servico', 'route' => 'provider.orders'],
            ['key' => 'financial', 'label' => 'Financeiro', 'route' => 'provider.financial'],
        ],
        'cashier' => [
            ['key' => 'dashboard', 'label' => 'Caixa', 'route' => 'pdv.index'],
            ['key' => 'create', 'label' => 'Nova Venda', 'route' => 'pdv.index'],
            ['key' => 'history', 'label' => 'Historico', 'route' => 'pdv.history'],
        ],
        'buyer' => [
            ['key' => 'dashboard', 'label' => 'Painel', 'route' => 'buyer.dashboard'],
            ['key' => 'projects', 'label' => 'Projetos', 'route' => 'buyer.projects'],
        ],
        default => [],
    };
@endphp

@if(! empty($items))
    <nav class="nav-tabs" data-portal-nav="{{ $portal }}" aria-label="Navegacao do portal">
        @foreach($items as $item)
            <a
                href="{{ $routeOrHome($item['route']) }}"
                class="nav-tab {{ $active === $item['key'] ? 'active' : '' }}"
                data-nav-key="{{ $item['key'] }}"
            >
                {{ $item['label'] }}
            </a>
        @endforeach
    </nav>
@endif
