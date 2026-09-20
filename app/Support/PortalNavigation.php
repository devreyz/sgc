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
                ['key' => 'dashboard', 'label' => 'Inicio', 'route' => 'associate.dashboard', 'icon' => 'house'],
                ['key' => 'projects', 'label' => 'Projetos', 'route' => 'associate.projects', 'icon' => 'folder-open'],
                ['key' => 'deliveries', 'label' => 'Entregas', 'route' => 'associate.deliveries', 'icon' => 'package'],
                ['key' => 'ledger', 'label' => 'Extrato', 'route' => 'associate.ledger', 'icon' => 'wallet'],
            ],
            'delivery' => [
                ['key' => 'dashboard', 'label' => 'Inicio', 'route' => 'delivery.dashboard', 'icon' => 'house'],
                ['key' => 'projects', 'label' => 'Projetos', 'route' => 'delivery.projects-list', 'icon' => 'folder-open'],
                ['key' => 'conference', 'label' => 'Conferência', 'route' => 'delivery.conference-sheets.index', 'icon' => 'checks'],
                ['key' => 'printables', 'label' => 'Imprimíveis', 'route' => 'delivery.sheet.index', 'icon' => 'printer'],
            ],
            'delivery-viewer' => [
                ['key' => 'home', 'label' => 'Inicio', 'route' => 'home', 'icon' => 'house'],
                ['key' => 'projects', 'label' => 'Projetos', 'route' => 'delivery-viewer.index', 'icon' => 'folder-open'],
            ],
            'provider' => [
                ['key' => 'dashboard', 'label' => 'Inicio', 'route' => 'provider.dashboard', 'icon' => 'house'],
                ['key' => 'orders', 'label' => 'Ordens', 'route' => 'provider.orders', 'icon' => 'clipboard-text'],
                ['key' => 'financial', 'label' => 'Financeiro', 'route' => 'provider.financial', 'icon' => 'wallet'],
                ...((auth()->user()?->checkPermissionTo('manage_service_expenses') ?? false) ? [['key' => 'expenses', 'label' => 'Despesas', 'route' => 'provider.expenses', 'icon' => 'receipt']] : []),
            ],
            'services' => [
                ...((auth()->user()?->checkPermissionTo('view_service_management') ?? false) ? [['key' => 'queue', 'label' => 'Fila', 'route' => 'services.management.index', 'icon' => 'list-checks']] : []),
                ...((auth()->user()?->checkPermissionTo('create_service_order') ?? false) ? [['key' => 'new', 'label' => 'Nova ordem', 'route' => 'services.management.create', 'icon' => 'plus-circle']] : []),
                ...((auth()->user()?->checkPermissionTo('manage_service_catalog') ?? false) ? [['key' => 'catalog', 'label' => 'Catálogo', 'route' => 'services.catalog.index', 'icon' => 'wrench']] : []),
                ...((auth()->user()?->checkPermissionTo('manage_service_providers') ?? false) ? [['key' => 'providers', 'label' => 'Prestadores', 'route' => 'services.providers.index', 'icon' => 'users-three']] : []),
                ...((auth()->user()?->checkPermissionTo('manage_service_agreements') ?? false) ? [['key' => 'agreements', 'label' => 'Acordos', 'route' => 'services.management.agreements', 'icon' => 'handshake']] : []),
                ...((auth()->user()?->checkPermissionTo('view_service_reports') ?? false) ? [['key' => 'reports', 'label' => 'Prestação', 'route' => 'services.management.reports', 'icon' => 'chart-bar']] : []),
            ],
            'cashier' => [
                ['key' => 'dashboard', 'label' => 'Caixa', 'route' => 'pdv.index', 'icon' => 'cash-register'],
                ['key' => 'create', 'label' => 'Nova venda', 'route' => 'pdv.index', 'icon' => 'plus-circle'],
                ['key' => 'history', 'label' => 'Historico', 'route' => 'pdv.history', 'icon' => 'clock-counter-clockwise'],
            ],
            'finance' => [
                ['key' => 'dashboard', 'label' => 'Visão geral', 'route' => 'finance.index', 'icon' => 'chart-pie-slice'],
                ['key' => 'receipts', 'label' => 'Recebimentos', 'route' => 'finance.receipts.index', 'icon' => 'receipt'],
                ['key' => 'management', 'label' => 'Cadastros', 'route' => 'finance.management.index', 'parameters' => ['module' => 'accounts'], 'icon' => 'sliders-horizontal'],
                ['key' => 'new-receipt', 'label' => 'Novo recibo', 'route' => 'finance.receipts.create', 'icon' => 'plus-circle'],
            ],
            'accounting' => [
                ['key' => 'queue', 'label' => 'Fila', 'route' => 'accounting.index', 'icon' => 'list-checks'],
                ['key' => 'processes', 'label' => 'Processos', 'route' => 'accounting.processes.index', 'icon' => 'flow-arrow'],
                ['key' => 'fiscal', 'label' => 'Fiscal', 'route' => 'accounting.fiscal.index', 'icon' => 'file-text'],
                ['key' => 'settings', 'label' => 'Configuração', 'route' => 'accounting.fiscal.settings', 'icon' => 'gear'],
                ['key' => 'home', 'label' => 'Painéis', 'route' => 'home', 'icon' => 'squares-four'],
            ],
            'secretary' => [
                ['key' => 'documents', 'label' => 'Documentos', 'route' => 'secretary.index', 'icon' => 'files'],
                ['key' => 'new-document', 'label' => 'Novo documento', 'route' => 'secretary.documents.create', 'icon' => 'file-plus'],
                ['key' => 'new-template', 'label' => 'Novo modelo', 'route' => 'secretary.templates.create', 'icon' => 'note-pencil'],
                ['key' => 'new-layout', 'label' => 'Cabeçalho ou rodapé', 'route' => 'secretary.layouts.create', 'icon' => 'layout'],
                ['key' => 'home', 'label' => 'Painéis', 'route' => 'home', 'icon' => 'squares-four'],
            ],
            'buyer' => [
                ['key' => 'dashboard', 'label' => 'Inicio', 'route' => 'buyer.dashboard', 'icon' => 'house'],
                ['key' => 'projects', 'label' => 'Projetos', 'route' => 'buyer.projects', 'icon' => 'folder-open'],
                ['key' => 'authorizations', 'label' => 'Autorizações', 'route' => 'buyer.authorizations.index', 'icon' => 'seal-check'],
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
