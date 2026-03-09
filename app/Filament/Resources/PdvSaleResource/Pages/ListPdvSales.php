<?php

namespace App\Filament\Resources\PdvSaleResource\Pages;

use App\Filament\Resources\PdvSaleResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPdvSales extends ListRecords
{
    protected static string $resource = PdvSaleResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        $tenantId = session('tenant_id');

        return [
            'all' => Tab::make('Todas')
                ->icon('heroicon-o-list-bullet'),
            'today' => Tab::make('Hoje')
                ->icon('heroicon-o-calendar-days')
                ->modifyQueryUsing(fn ($query) => $query->whereDate('created_at', today())),
            'fiado' => Tab::make('Fiado Pendente')
                ->icon('heroicon-o-clock')
                ->badge(fn () => \App\Models\PdvSale::where('tenant_id', $tenantId)
                    ->where('status', 'completed')
                    ->where('is_fiado', true)
                    ->whereRaw('total > amount_paid')
                    ->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn ($query) => $query
                    ->where('status', 'completed')
                    ->where('is_fiado', true)
                    ->whereRaw('total > amount_paid')),
            'cancelled' => Tab::make('Canceladas')
                ->icon('heroicon-o-x-circle')
                ->modifyQueryUsing(fn ($query) => $query->where('status', 'cancelled')),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [];
    }
}
