<?php

namespace App\Filament\Widgets;

use App\Models\ProviderPaymentRequest;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class PendingPaymentRequestsWidget extends BaseWidget
{
    protected static ?int $sort = 1;
    
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Pedidos de Saque Pendentes')
            ->description('Prestadores aguardando pagamento')
            ->query(
                ProviderPaymentRequest::query()
                    ->where('status', 'pending')
                    ->with(['serviceProvider', 'serviceOrder.service'])
                    ->latest('request_date')
            )
            ->columns([
                Tables\Columns\TextColumn::make('request_date')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('serviceProvider.name')
                    ->label('Prestador')
                    ->searchable()
                    ->limit(25),
                
                Tables\Columns\TextColumn::make('serviceOrder.number')
                    ->label('Ordem')
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('serviceOrder.service.name')
                    ->label('Serviço')
                    ->limit(20),
                
                Tables\Columns\TextColumn::make('amount')
                    ->label('Valor')
                    ->money('BRL')
                    ->sortable()
                    ->weight('bold')
                    ->color('warning'),
                
                Tables\Columns\TextColumn::make('description')
                    ->label('Descrição')
                    ->limit(30)
                    ->toggleable(),
            ])
            ->actions([
                Tables\Actions\Action::make('view_order')
                    ->label('Ver Ordem')
                    ->icon('heroicon-o-eye')
                    ->url(fn (ProviderPaymentRequest $record): string => 
                        route('filament.admin.resources.service-orders.view', ['record' => $record->service_order_id])
                    )
                    ->openUrlInNewTab(),
            ])
            ->emptyStateHeading('Nenhum pedido de saque pendente')
            ->emptyStateDescription('Quando prestadores solicitarem saques, aparecerão aqui.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->defaultPaginationPageOption(5)
            ->poll('30s');
    }
}
