<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

class PortalNavigation
{
    public static function make(string $portal, ?string $active = null, mixed $tenant = null): array
    {
        $tenantSlug = is_object($tenant) ? ($tenant->slug ?? null) : $tenant;
        $items = match ($portal) {
            'associate' => [
                ['key' => 'dashboard', 'label' => 'Inicio', 'route' => 'associate.dashboard'],
                ['key' => 'projects', 'label' => 'Projetos', 'route' => 'associate.projects'],
                ['key' => 'deliveries', 'label' => 'Entregas', 'route' => 'associate.deliveries'],
                ['key' => 'ledger', 'label' => 'Extrato', 'route' => 'associate.ledger'],
            ],
            'delivery' => [
                ['key' => 'dashboard', 'label' => 'Inicio', 'route' => 'delivery.dashboard'],
                ['key' => 'projects', 'label' => 'Projetos', 'route' => 'delivery.projects-list'],
                ['key' => 'printables', 'label' => 'Imprimíveis', 'route' => 'delivery.sheet.index'],
            ],
            'delivery-viewer' => [
                ['key' => 'home', 'label' => 'Inicio', 'route' => 'home'],
                ['key' => 'projects', 'label' => 'Projetos', 'route' => 'delivery-viewer.index'],
            ],
            'provider' => [
                ['key' => 'dashboard', 'label' => 'Inicio', 'route' => 'provider.dashboard'],
                ['key' => 'orders', 'label' => 'Ordens', 'route' => 'provider.orders'],
                ['key' => 'financial', 'label' => 'Financeiro', 'route' => 'provider.financial'],
            ],
            'cashier' => [
                ['key' => 'dashboard', 'label' => 'Caixa', 'route' => 'pdv.index'],
                ['key' => 'create', 'label' => 'Nova venda', 'route' => 'pdv.index'],
                ['key' => 'history', 'label' => 'Historico', 'route' => 'pdv.history'],
            ],
            'finance' => [
                ['key' => 'dashboard', 'label' => 'Visão geral', 'route' => 'finance.index'],
                ['key' => 'receipts', 'label' => 'Recebimentos', 'route' => 'finance.receipts.index'],
                ['key' => 'management', 'label' => 'Cadastros', 'route' => 'finance.management.index', 'parameters' => ['module' => 'accounts']],
                ['key' => 'new-receipt', 'label' => 'Novo recibo', 'route' => 'finance.receipts.create'],
            ],
            'buyer' => [
                ['key' => 'dashboard', 'label' => 'Inicio', 'route' => 'buyer.dashboard'],
                ['key' => 'projects', 'label' => 'Projetos', 'route' => 'buyer.projects'],
            ],
            default => [],
        };

        return [
            'portal' => $portal,
            'active' => $active,
            'aria_label' => 'Navegacao principal do portal',
            'items' => collect($items)->map(function (array $item) use ($tenantSlug) {
                $route = Route::getRoutes()->getByName($item['route']);
                $parameters = $tenantSlug && in_array('tenant', $route?->parameterNames() ?? [], true)
                    ? ['tenant' => $tenantSlug]
                    : [];
                $parameters = array_merge($parameters, $item['parameters'] ?? []);
                $item['type'] = 'link';
                $item['url'] = Route::has($item['route'])
                    ? route($item['route'], $parameters)
                    : url('/');
                unset($item['route'], $item['parameters']);

                return $item;
            })->all(),
        ];
    }
}
