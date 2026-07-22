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
                $item['type'] = 'link';
                $item['url'] = $tenantSlug && Route::has($item['route'])
                    ? route($item['route'], ['tenant' => $tenantSlug])
                    : url('/');
                unset($item['route']);

                return $item;
            })->all(),
        ];
    }
}
