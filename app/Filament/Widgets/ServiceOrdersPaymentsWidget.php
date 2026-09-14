<?php

namespace App\Filament\Widgets;

use App\Models\ServiceObligation;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ServiceOrdersPaymentsWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected static ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return (bool) session('tenant_id') && (auth()->user()?->checkPermissionTo('view_service_financials') ?? false);
    }

    protected function getStats(): array
    {
        $obligations = ServiceObligation::query()->where('tenant_id', session('tenant_id'))->where('status', '!=', 'cancelled')->get();

        return [
            Stat::make('Serviços: a receber', 'R$ '.number_format($obligations->where('direction', 'receivable')->sum('balance'), 2, ',', '.')),
            Stat::make('Serviços: a pagar aos prestadores', 'R$ '.number_format($obligations->where('direction', 'payable')->sum('balance'), 2, ',', '.')),
        ];
    }
}
