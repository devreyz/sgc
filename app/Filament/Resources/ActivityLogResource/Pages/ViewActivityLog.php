<?php

namespace App\Filament\Resources\ActivityLogResource\Pages;

use App\Filament\Resources\ActivityLogResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewActivityLog extends ViewRecord
{
    protected static string $resource = ActivityLogResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Informações do Log')
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Data/Hora')
                            ->dateTime('d/m/Y H:i:s'),
                        Infolists\Components\TextEntry::make('log_name')
                            ->label('Nome do Log')
                            ->badge(),
                        Infolists\Components\TextEntry::make('description')
                            ->label('Ação')
                            ->badge(),
                        Infolists\Components\TextEntry::make('causer.name')
                            ->label('Usuário'),
                    ])
                    ->columns(4),

                Infolists\Components\Section::make('Sujeito')
                    ->schema([
                        Infolists\Components\TextEntry::make('subject_type')
                            ->label('Tipo')
                            ->formatStateUsing(fn ($state): string => class_basename($state)),
                        Infolists\Components\TextEntry::make('subject_id')
                            ->label('ID'),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Alterações')
                    ->schema([
                        Infolists\Components\KeyValueEntry::make('properties.old')
                            ->label('Valores Anteriores'),
                        Infolists\Components\KeyValueEntry::make('properties.attributes')
                            ->label('Novos Valores'),
                    ])
                    ->columns(2),
            ]);
    }
}
