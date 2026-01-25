<?php

namespace App\Filament\Resources\EquipmentResource\Pages;

use App\Filament\Resources\EquipmentResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;

class ViewEquipment extends ViewRecord
{
    protected static string $resource = EquipmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Components\Section::make('Informações do Equipamento')
                    ->schema([
                        Components\Grid::make(4)
                            ->schema([
                                Components\TextEntry::make('code')
                                    ->label('Código'),
                                Components\TextEntry::make('name')
                                    ->label('Nome'),
                                Components\TextEntry::make('type')
                                    ->label('Tipo')
                                    ->badge()
                                    ->formatStateUsing(fn ($state) => \App\Models\Equipment::TYPES[$state] ?? $state),
                                Components\TextEntry::make('status')
                                    ->label('Status')
                                    ->badge()
                                    ->formatStateUsing(fn ($state) => \App\Models\Equipment::STATUSES[$state] ?? $state),
                            ]),
                    ]),

                Components\Section::make('Medidores Atuais')
                    ->schema([
                        Components\Grid::make(2)
                            ->schema([
                                Components\TextEntry::make('current_hours')
                                    ->label('Horímetro')
                                    ->formatStateUsing(fn ($state) => number_format($state, 0, ',', '.') . ' horas')
                                    ->icon('heroicon-o-clock')
                                    ->size('lg')
                                    ->weight('bold'),
                                Components\TextEntry::make('current_km')
                                    ->label('Odômetro')
                                    ->formatStateUsing(fn ($state) => number_format($state, 0, ',', '.') . ' km')
                                    ->icon('heroicon-o-map')
                                    ->size('lg')
                                    ->weight('bold'),
                            ]),
                    ]),

                Components\Section::make('Manutenções')
                    ->schema([
                        Components\Grid::make(2)
                            ->schema([
                                Components\TextEntry::make('overdue_maintenance_count')
                                    ->label('Manutenções Atrasadas')
                                    ->badge()
                                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success')
                                    ->formatStateUsing(fn ($state) => $state > 0 ? $state : 'Nenhuma'),
                                Components\TextEntry::make('pending_maintenance_count')
                                    ->label('Próximas (7 dias)')
                                    ->badge()
                                    ->color(fn ($state) => $state > 0 ? 'warning' : 'success')
                                    ->formatStateUsing(fn ($state) => $state > 0 ? $state : 'Nenhuma'),
                            ]),
                    ]),
            ]);
    }
}
