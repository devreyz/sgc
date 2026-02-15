<?php

namespace App\Filament\Resources\AssociateResource\Pages;

use App\Filament\Resources\AssociateResource;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewAssociate extends ViewRecord
{
    protected static string $resource = AssociateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Informações do Associado')
                    ->schema([
                        Infolists\Components\TextEntry::make('user.display_name')
                            ->label('Nome'),
                        Infolists\Components\TextEntry::make('cpf_cnpj')
                            ->label('CPF/CNPJ'),
                        Infolists\Components\TextEntry::make('dap_caf')
                            ->label('DAP/CAF'),
                        Infolists\Components\TextEntry::make('dap_caf_expiry')
                            ->label('Validade DAP/CAF')
                            ->date('d/m/Y'),
                        Infolists\Components\TextEntry::make('city')
                            ->label('Cidade'),
                        Infolists\Components\TextEntry::make('state')
                            ->label('Estado')
                            ->badge(),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Saldo')
                    ->schema([
                        Infolists\Components\TextEntry::make('current_balance')
                            ->label('Saldo Atual')
                            ->money('BRL')
                            ->size('lg')
                            ->color(fn ($state): string => $state >= 0 ? 'success' : 'danger'),
                    ]),
            ]);
    }
}
