<?php

namespace App\Filament\Resources\DistributionBillingResource\Pages;

use App\Filament\Resources\DistributionBillingResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewDistributionBilling extends ViewRecord
{
    protected static string $resource = DistributionBillingResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Lote de Faturamento')->schema([
                Infolists\Components\TextEntry::make('id')->label('Lote #'),
                Infolists\Components\TextEntry::make('billing_date')->label('Data')->date('d/m/Y'),
                Infolists\Components\TextEntry::make('salesProject.title')->label('Projeto'),
                Infolists\Components\TextEntry::make('associate.display_name')->label('Associado')->placeholder('Todos'),
                Infolists\Components\TextEntry::make('reference')->label('Referência')->placeholder('—'),
                Infolists\Components\TextEntry::make('period_start')->label('Período Início')->date('d/m/Y')->placeholder('—'),
                Infolists\Components\TextEntry::make('period_end')->label('Período Fim')->date('d/m/Y')->placeholder('—'),
            ])->columns(3),

            Infolists\Components\Section::make('Totais')->schema([
                Infolists\Components\TextEntry::make('total_distributions')->label('Distribuições'),
                Infolists\Components\TextEntry::make('total_gross')->label('Bruto')->money('BRL'),
                Infolists\Components\TextEntry::make('total_admin_fee')->label('Taxa Admin')->money('BRL'),
                Infolists\Components\TextEntry::make('total_net')->label('Líquido')->money('BRL')->color('success'),
            ])->columns(4),

            Infolists\Components\Section::make('Distribuições Faturadas')->schema([
                Infolists\Components\RepeatableEntry::make('distributions')
                    ->label('')
                    ->schema([
                        Infolists\Components\TextEntry::make('delivery_date')->label('Data')->date('d/m/Y'),
                        Infolists\Components\TextEntry::make('associate.display_name')->label('Associado'),
                        Infolists\Components\TextEntry::make('customer.name')->label('Cliente')->default('—'),
                        Infolists\Components\TextEntry::make('product.name')->label('Produto'),
                        Infolists\Components\TextEntry::make('quantity')->label('Qtd'),
                        Infolists\Components\TextEntry::make('unit_price')->label('Preço Un.')->money('BRL'),
                        Infolists\Components\TextEntry::make('net_value')->label('Líquido')->money('BRL')->color('success'),
                    ])->columns(7),
            ]),

            Infolists\Components\Section::make('Observações')->schema([
                Infolists\Components\TextEntry::make('notes')->label('')->default('—')->columnSpanFull(),
            ])->collapsed(),
        ]);
    }
}
