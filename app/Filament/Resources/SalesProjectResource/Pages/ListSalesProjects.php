<?php

namespace App\Filament\Resources\SalesProjectResource\Pages;

use App\Filament\Resources\SalesProjectResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use App\Enums\ProjectStatus;

class ListSalesProjects extends ListRecords
{
    protected static string $resource = SalesProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Todos'),
            'active' => Tab::make('Em Andamento')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ProjectStatus::ACTIVE)),
            'planning' => Tab::make('Planejamento')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ProjectStatus::PLANNING)),
            'completed' => Tab::make('Concluídos')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ProjectStatus::COMPLETED)),
        ];
    }
}
