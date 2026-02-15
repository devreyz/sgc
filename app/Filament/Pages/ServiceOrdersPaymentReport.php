<?php

namespace App\Filament\Pages;

use App\Models\ServiceOrder;
use App\Enums\ServiceOrderStatus;
use Filament\Pages\Page;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\DatePicker;
use Illuminate\Support\Facades\DB;

class ServiceOrdersPaymentReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static string $view = 'filament.pages.service-orders-payment-report';

    protected static ?string $navigationGroup = 'Serviços';

    protected static ?string $navigationLabel = 'Relatório de Pagamentos';

    protected static ?string $title = 'Relatório de Pagamentos de Ordens de Serviço';

    protected static ?int $navigationSort = 10;

    public function table(Table $table): Table
    {
        $tenantId = session('tenant_id');
        
        return $table
            ->query(
                ServiceOrder::query()
                    ->where('tenant_id', $tenantId)
                    ->where('status', ServiceOrderStatus::COMPLETED)
                    ->with(['associate.user', 'service', 'works.serviceProvider'])
                    ->latest('execution_date')
            )
            ->columns([
                TextColumn::make('number')
                    ->label('Número')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('execution_date')
                    ->label('Data Execução')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('associate.user.display_name')
                    ->label('Associado')
                    ->searchable()
                    ->limit(20),

                TextColumn::make('service.name')
                    ->label('Serviço')
                    ->searchable()
                    ->limit(20),

                TextColumn::make('final_price')
                    ->label('Valor Associado')
                    ->money('BRL')
                    ->sortable()
                    ->summarize([
                        \Filament\Tables\Columns\Summarizers\Sum::make()
                            ->money('BRL')
                            ->label('Total'),
                    ]),

                TextColumn::make('provider_payment')
                    ->label('Pagto Prestador')
                    ->money('BRL')
                    ->sortable()
                    ->summarize([
                        \Filament\Tables\Columns\Summarizers\Sum::make()
                            ->money('BRL')
                            ->label('Total'),
                    ]),

                TextColumn::make('cooperative_profit')
                    ->label('Lucro Cooperativa')
                    ->money('BRL')
                    ->state(function (ServiceOrder $record): float {
                        return $record->final_price - $record->provider_payment;
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw("(final_price - provider_payment) {$direction}");
                    })
                    ->summarize([
                        \Filament\Tables\Columns\Summarizers\Sum::make()
                            ->money('BRL')
                            ->using(fn ($query) => $query->sum(DB::raw('final_price - provider_payment')))
                            ->label('Total'),
                    ]),

                TextColumn::make('associate_payment_status')
                    ->label('Status Pgto Associado')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => match($state) {
                        'paid' => 'Pago',
                        'pending' => 'Pendente',
                        'cancelled' => 'Cancelado',
                        default => 'N/A'
                    })
                    ->color(fn ($state): string => match($state) {
                        'paid' => 'success',
                        'pending' => 'warning',
                        'cancelled' => 'danger',
                        default => 'gray'
                    }),

                TextColumn::make('provider_payment_status')
                    ->label('Status Pgto Prestador')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => match($state) {
                        'paid' => 'Pago',
                        'pending' => 'Pendente',
                        'cancelled' => 'Cancelado',
                        default => 'N/A'
                    })
                    ->color(fn ($state): string => match($state) {
                        'paid' => 'success',
                        'pending' => 'warning',
                        'cancelled' => 'danger',
                        default => 'gray'
                    }),

                TextColumn::make('associate_paid_at')
                    ->label('Pago em (Assoc.)')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('provider_paid_at')
                    ->label('Pago em (Prest.)')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('associate_payment_status')
                    ->label('Status Pgto Associado')
                    ->options([
                        'pending' => 'Pendente',
                        'paid' => 'Pago',
                        'cancelled' => 'Cancelado',
                    ]),

                SelectFilter::make('provider_payment_status')
                    ->label('Status Pgto Prestador')
                    ->options([
                        'pending' => 'Pendente',
                        'paid' => 'Pago',
                        'cancelled' => 'Cancelado',
                    ]),

                Filter::make('execution_date')
                    ->form([
                        DatePicker::make('executed_from')
                            ->label('Executado de'),
                        DatePicker::make('executed_until')
                            ->label('Executado até'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['executed_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('execution_date', '>=', $date),
                            )
                            ->when(
                                $data['executed_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('execution_date', '<=', $date),
                            );
                    }),

                SelectFilter::make('associate_id')
                    ->label('Associado')
                    ->relationship('associate.user', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('service_id')
                    ->label('Serviço')
                    ->relationship('service', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('execution_date', 'desc')
            ->striped()
            ->poll('30s');
    }

    public function getTableRecordKey($record): string
    {
        return (string) $record->id;
    }
}
