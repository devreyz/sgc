<?php

namespace App\Filament\Resources\ProductionDeliveryResource\Pages;

use App\Filament\Resources\ProductionDeliveryResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;
use App\Enums\DeliveryStatus;

class ViewProductionDelivery extends ViewRecord
{
    protected static string $resource = ProductionDeliveryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('approve')
                ->label('Aprovar')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update([
                        'status' => DeliveryStatus::APPROVED,
                        'approved_by' => auth()->id(),
                        'approved_at' => now(),
                    ]);
                    
                    if ($this->record->projectDemand) {
                        $this->record->projectDemand->updateDeliveredQuantity();
                    }
                    
                    $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                })
                ->visible(fn (): bool => $this->record->status === DeliveryStatus::PENDING),

            Actions\EditAction::make()
                ->visible(fn (): bool => $this->record->status === DeliveryStatus::PENDING),
            Actions\DeleteAction::make()
                ->visible(fn (): bool => $this->record->status === DeliveryStatus::PENDING),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Components\Section::make('Informações da Entrega')
                    ->icon('heroicon-o-truck')
                    ->schema([
                        Components\Grid::make(3)
                            ->schema([
                                Components\TextEntry::make('status')
                                    ->label('Status')
                                    ->badge()
                                    ->formatStateUsing(fn (DeliveryStatus $state): string => $state->getLabel())
                                    ->color(fn (DeliveryStatus $state): string => $state->getColor()),

                                Components\TextEntry::make('delivery_date')
                                    ->label('Data da Entrega')
                                    ->date('d/m/Y'),

                                Components\TextEntry::make('quality_grade')
                                    ->label('Qualidade')
                                    ->badge()
                                    ->colors([
                                        'success' => 'A',
                                        'warning' => 'B',
                                        'danger' => 'C',
                                    ]),
                            ]),
                    ]),

                Components\Section::make('Projeto e Produtor')
                    ->icon('heroicon-o-building-office')
                    ->columns(2)
                    ->schema([
                        Components\TextEntry::make('salesProject.title')
                            ->label('Projeto de Venda'),

                        Components\TextEntry::make('associate.user.name')
                            ->label('Produtor'),

                        Components\TextEntry::make('product.name')
                            ->label('Produto'),

                        Components\TextEntry::make('quantity')
                            ->label('Quantidade')
                            ->formatStateUsing(fn ($state, $record): string => 
                                number_format($state, 2, ',', '.') . ' ' . ($record->product->unit ?? '')
                            ),
                    ]),

                Components\Section::make('Valores Financeiros')
                    ->icon('heroicon-o-banknotes')
                    ->schema([
                        Components\Grid::make(4)
                            ->schema([
                                Components\TextEntry::make('unit_price')
                                    ->label('Preço Unitário')
                                    ->money('BRL'),

                                Components\TextEntry::make('gross_value')
                                    ->label('Valor Bruto')
                                    ->money('BRL')
                                    ->weight('bold'),

                                Components\TextEntry::make('admin_fee_amount')
                                    ->label('Taxa Administrativa')
                                    ->money('BRL')
                                    ->color('danger')
                                    ->weight('bold'),

                                Components\TextEntry::make('net_value')
                                    ->label('Valor Líquido')
                                    ->money('BRL')
                                    ->color('success')
                                    ->weight('bold')
                                    ->size('lg'),
                            ]),
                    ]),

                Components\Section::make('Aprovação')
                    ->icon('heroicon-o-shield-check')
                    ->visible(fn ($record) => $record->status !== DeliveryStatus::PENDING)
                    ->columns(2)
                    ->schema([
                        Components\TextEntry::make('approver.name')
                            ->label('Aprovado por'),

                        Components\TextEntry::make('approved_at')
                            ->label('Data/Hora Aprovação')
                            ->dateTime('d/m/Y H:i'),
                    ]),

                Components\Section::make('Observações')
                    ->icon('heroicon-o-chat-bubble-bottom-center-text')
                    ->visible(fn ($record) => !empty($record->notes))
                    ->schema([
                        Components\TextEntry::make('notes')
                            ->label('')
                            ->prose(),
                    ]),
            ]);
    }
}
