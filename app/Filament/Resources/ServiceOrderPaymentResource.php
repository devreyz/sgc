<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceOrderPaymentResource\Pages;
use App\Models\ServiceOrderPayment;
use App\Models\ServiceOrder;
use App\Models\BankAccount;
use App\Models\CashMovement;
use App\Enums\PaymentMethod;
use App\Enums\ServiceOrderStatus;
use App\Enums\ServiceOrderPaymentStatus;
use App\Enums\CashMovementType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ServiceOrderPaymentResource extends Resource
{
    protected static ?string $model = ServiceOrderPayment::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    
    protected static ?string $navigationLabel = 'Pagamentos de Ordens';
    
    protected static ?string $modelLabel = 'Pagamento de Ordem';
    
    protected static ?string $pluralModelLabel = 'Pagamentos de Ordens';
    
    protected static ?string $navigationGroup = 'Financeiro';
    
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dados do Pagamento')
                    ->schema([
                        Forms\Components\Select::make('service_order_id')
                            ->label('Ordem de Serviço')
                            ->relationship('serviceOrder', 'number', function (Builder $query) {
                                return $query->where('status', ServiceOrderStatus::AWAITING_PAYMENT);
                            })
                            ->searchable()
                            ->preload()
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if ($state) {
                                    $order = ServiceOrder::find($state);
                                    if ($order) {
                                        $totalPaid = $order->payments()->sum('amount');
                                        $remaining = $order->final_price - $totalPaid;
                                        $set('remaining_amount', $remaining);
                                    }
                                }
                            })
                            ->helperText(fn ($state): string => 
                                $state ? self::getOrderInfo($state) : 'Selecione uma ordem para ver detalhes'
                            ),

                        Forms\Components\Placeholder::make('remaining_amount')
                            ->label('Valor Restante')
                            ->content(function ($get) {
                                $orderId = $get('service_order_id');
                                if (!$orderId) return 'R$ 0,00';
                                
                                $order = ServiceOrder::find($orderId);
                                if (!$order) return 'R$ 0,00';
                                
                                $totalPaid = $order->payments()->sum('amount');
                                $remaining = $order->final_price - $totalPaid;
                                
                                return 'R$ ' . number_format($remaining, 2, ',', '.');
                            }),

                        Forms\Components\DatePicker::make('payment_date')
                            ->label('Data do Pagamento')
                            ->default(now())
                            ->required()
                            ->maxDate(now()),

                        Forms\Components\TextInput::make('amount')
                            ->label('Valor Pago')
                            ->required()
                            ->numeric()
                            ->prefix('R$')
                            ->step(0.01)
                            ->minValue(0.01)
                            ->helperText('Informe o valor recebido neste pagamento')
                            ->rules([
                                fn ($get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                                    $orderId = $get('service_order_id');
                                    if (!$orderId) return;
                                    
                                    $order = ServiceOrder::find($orderId);
                                    if (!$order) return;
                                    
                                    $totalPaid = $order->payments()->sum('amount');
                                    $remaining = $order->final_price - $totalPaid;
                                    
                                    if ($value > $remaining) {
                                        $fail("O valor não pode ser maior que o saldo devedor de R$ " . number_format($remaining, 2, ',', '.'));
                                    }
                                },
                            ]),

                        Forms\Components\TextInput::make('discount')
                            ->label('Desconto')
                            ->numeric()
                            ->prefix('R$')
                            ->default(0)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get) {
                                $amount = floatval($get('amount') ?? 0);
                                $discount = floatval($get('discount') ?? 0);
                                $fees = floatval($get('fees') ?? 0);
                                $set('final_amount', number_format($amount - $discount + $fees, 2, '.', ''));
                            }),

                        Forms\Components\TextInput::make('fees')
                            ->label('Taxas/Encargos')
                            ->numeric()
                            ->prefix('R$')
                            ->default(0)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get) {
                                $amount = floatval($get('amount') ?? 0);
                                $discount = floatval($get('discount') ?? 0);
                                $fees = floatval($get('fees') ?? 0);
                                $set('final_amount', number_format($amount - $discount + $fees, 2, '.', ''));
                            }),

                        Forms\Components\TextInput::make('final_amount')
                            ->label('Valor Final')
                            ->numeric()
                            ->prefix('R$')
                            ->readOnly()
                            ->helperText('Valor - Desconto + Taxas'),

                        Forms\Components\Select::make('payment_method')
                            ->label('Forma de Pagamento')
                            ->options(PaymentMethod::class)
                            ->required()
                            ->native(false),

                        Forms\Components\Select::make('bank_account_id')
                            ->label('Conta Bancária')
                            ->relationship('bankAccount', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->default(fn () => \App\Models\BankAccount::where('is_default', true)->first()?->id)
                            ->helperText('Conta onde o valor foi depositado'),

                        Forms\Components\Textarea::make('notes')
                            ->label('Observações')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'client'))
            ->columns([
                Tables\Columns\TextColumn::make('serviceOrder.number')
                    ->label('Ordem')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state === 'client' ? 'Cliente' : 'Prestador')
                    ->color(fn ($state) => $state === 'client' ? 'info' : 'success'),
                
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                    
                Tables\Columns\TextColumn::make('serviceOrder.service.name')
                    ->label('Serviço')
                    ->searchable()
                    ->limit(20),
                    
                Tables\Columns\TextColumn::make('serviceOrder.associate.name')
                    ->label('Cliente')
                    ->searchable()
                    ->default('Pessoa Avulsa')
                    ->limit(20),
                    
                Tables\Columns\TextColumn::make('payment_date')
                    ->label('Data Pagamento')
                    ->date('d/m/Y')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('amount')
                    ->label('Valor')
                    ->money('BRL')
                    ->sortable(),

                Tables\Columns\TextColumn::make('discount')
                    ->label('Desconto')
                    ->money('BRL')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('fees')
                    ->label('Taxas')
                    ->money('BRL')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('final_amount')
                    ->label('Valor Final')
                    ->money('BRL')
                    ->toggleable(isToggledHiddenByDefault: true),
                    
                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Forma')
                    ->badge(),
                    
                Tables\Columns\TextColumn::make('bankAccount.name')
                    ->label('Conta')
                    ->default('-'),
                    
                Tables\Columns\TextColumn::make('registeredBy.name')
                    ->label('Registrado por')
                    ->sortable()
                    ->limit(15)
                    ->toggleable(),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(ServiceOrderPaymentStatus::class),
                
                Tables\Filters\SelectFilter::make('payment_method')
                    ->label('Forma de Pagamento')
                    ->options(PaymentMethod::class),
                    
                Tables\Filters\Filter::make('payment_date')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('De'),
                        Forms\Components\DatePicker::make('until')->label('Até'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('payment_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('payment_date', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('view_receipt')
                    ->label('Comprovante')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->url(fn (ServiceOrderPayment $record) => $record->receipt_path 
                        ? \Storage::url($record->receipt_path) 
                        : null
                    )
                    ->openUrlInNewTab()
                    ->visible(fn (ServiceOrderPayment $record) => !empty($record->receipt_path)),
                
                Tables\Actions\Action::make('bill')
                    ->label('Faturar')
                    ->icon('heroicon-o-document-check')
                    ->color('success')
                    ->modalHeading('Faturar Pagamento')
                    ->modalDescription(fn (ServiceOrderPayment $record) => sprintf(
                        'Ordem %s · Valor: R$ %s · Método: %s',
                        $record->serviceOrder->number,
                        number_format($record->amount, 2, ',', '.'),
                        $record->payment_method->getLabel()
                    ))
                    ->form([
                        Forms\Components\Placeholder::make('payment_details')
                            ->label('Detalhes do Pagamento')
                            ->content(fn (ServiceOrderPayment $record) => new \Illuminate\Support\HtmlString(
                                '<div style="margin-bottom:1rem;">' .
                                '<p><strong>Data:</strong> ' . $record->payment_date->format('d/m/Y') . '</p>' .
                                ($record->notes ? '<p><strong>Observações:</strong> ' . nl2br(e($record->notes)) . '</p>' : '') .
                                ($record->receipt_path ? '<p><a href="' . \Storage::url($record->receipt_path) . '" target="_blank" style="color:#3b82f6;text-decoration:underline;">📎 Ver comprovante enviado</a></p>' : '<p style="color:#6b7280;">Sem comprovante anexado</p>') .
                                '</div>'
                            )),
                        Forms\Components\Select::make('bank_account_id')
                            ->label('Conta Bancária')
                            ->options(fn () => BankAccount::pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->default(fn () => BankAccount::where('is_default', true)->first()?->id)
                            ->helperText('Conta onde o valor será creditado'),
                    ])
                    ->action(function (ServiceOrderPayment $record, array $data): void {
                        DB::transaction(function () use ($record, $data) {
                            // Criar movimentação de caixa
                            CashMovement::create([
                                'type' => CashMovementType::INCOME,
                                'amount' => $record->amount,
                                'description' => "Recebimento OS {$record->serviceOrder->number}".
                                    ($record->serviceOrder->associate ? ' - '.(optional($record->serviceOrder->associate->user)->name ?? '') : ' - Avulso'),
                                'movement_date' => $record->payment_date,
                                'bank_account_id' => $data['bank_account_id'],
                                'payment_method' => $record->payment_method,
                                'reference_type' => ServiceOrder::class,
                                'reference_id' => $record->service_order_id,
                                'notes' => 'Faturamento do pagamento #'.$record->id,
                                'created_by' => auth()->id(),
                            ]);

                            // Atualizar status do pagamento
                            $record->update([
                                'status' => ServiceOrderPaymentStatus::BILLED,
                                'bank_account_id' => $data['bank_account_id'],
                            ]);

                            // Verificar se ordem foi totalmente paga e faturada
                            $order = $record->serviceOrder;
                            $totalBilled = ServiceOrderPayment::where('service_order_id', $order->id)
                                ->where('type', 'client')
                                ->where('status', ServiceOrderPaymentStatus::BILLED)
                                ->sum('amount');
                            
                            if ($totalBilled >= $order->final_price) {
                                $order->update([
                                    'associate_payment_status' => ServiceOrderPaymentStatus::BILLED,
                                    'associate_paid_at' => now(),
                                ]);
                            }
                        });

                        Notification::make()
                            ->success()
                            ->title('Pagamento faturado')
                            ->body('Movimentação de caixa registrada com sucesso!')
                            ->send();
                    })
                    ->visible(fn (ServiceOrderPayment $record) => $record->status === ServiceOrderPaymentStatus::PENDING),
                
                Tables\Actions\ViewAction::make()
                    ->modalHeading('Detalhes do Pagamento')
                    ->infolist([
                        \Filament\Infolists\Components\Section::make()->schema([
                            \Filament\Infolists\Components\TextEntry::make('serviceOrder.number')
                                ->label('Ordem de Serviço'),
                            \Filament\Infolists\Components\TextEntry::make('amount')
                                ->label('Valor')
                                ->money('BRL'),
                            \Filament\Infolists\Components\TextEntry::make('payment_method')
                                ->label('Método de Pagamento')
                                ->badge(),
                            \Filament\Infolists\Components\TextEntry::make('payment_date')
                                ->label('Data do Pagamento')
                                ->date('d/m/Y'),
                            \Filament\Infolists\Components\TextEntry::make('status')
                                ->label('Status')
                                ->badge(),
                            \Filament\Infolists\Components\TextEntry::make('notes')
                                ->label('Observações')
                                ->placeholder('Sem observações')
                                ->columnSpanFull(),
                            \Filament\Infolists\Components\Actions::make([
                                \Filament\Infolists\Components\Actions\Action::make('view_receipt')
                                    ->label('Ver Comprovante')
                                    ->icon('heroicon-o-document-text')
                                    ->color('info')
                                    ->url(fn ($record) => \Storage::url($record->receipt_path))
                                    ->openUrlInNewTab()
                                    ->visible(fn ($record) => !empty($record->receipt_path)),
                            ])
                                ->columnSpanFull()
                                ->visible(fn ($record) => !empty($record->receipt_path)),
                        ])->columns(2),
                    ]),
            ])
            ->bulkActions([
                // Removido: não permitir deletar pagamentos
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServiceOrderPayments::route('/'),
            'create' => Pages\CreateServiceOrderPayment::route('/create'),
        ];
    }
    
    /**
     * Get formatted order information for helper text
     */
    protected static function getOrderInfo(int $orderId): string
    {
        $order = ServiceOrder::with(['service', 'associate', 'serviceProvider', 'payments'])->find($orderId);
        
        if (!$order) return '';
        
        $totalPaid = $order->payments->sum('amount');
        $remaining = $order->final_price - $totalPaid;
        
        $info = "Serviço: {$order->service->name} | ";
        $info .= "Valor Total: R$ " . number_format($order->final_price, 2, ',', '.') . " | ";
        $info .= "Pago: R$ " . number_format($totalPaid, 2, ',', '.') . " | ";
        $info .= "Restante: R$ " . number_format($remaining, 2, ',', '.');
        
        return $info;
    }
}
