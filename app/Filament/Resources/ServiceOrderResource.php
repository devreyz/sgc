<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceOrderResource\Pages;
use App\Enums\ServiceOrderStatus;
use App\Enums\ServiceOrderPaymentStatus;
use App\Enums\ServiceType;
use App\Enums\LedgerType;
use App\Enums\LedgerCategory;
use App\Models\ServiceOrder;
use App\Models\ServiceProvider;
use App\Models\AssociateLedger;
use App\Models\CashMovement;
use App\Enums\CashMovementType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;

class ServiceOrderResource extends Resource
{
    protected static ?string $model = ServiceOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Serviços';

    protected static ?string $modelLabel = 'Ordem de Serviço';

    protected static ?string $pluralModelLabel = 'Ordens de Serviço';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dados da Ordem de Serviço')
                    ->schema([
                        Forms\Components\TextInput::make('number')
                            ->label('Número')
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn('edit'),

                        Forms\Components\Select::make('associate_id')
                            ->label('Associado')
                            ->relationship('associate', 'id')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->user->name)
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\Select::make('service_id')
                            ->label('Serviço')
                            ->relationship('service', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $service = \App\Models\Service::find($state);
                                    if ($service) {
                                        $set('unit', $service->unit);
                                        $set('unit_price', $service->price);
                                    }
                                }
                            }),

                        Forms\Components\Select::make('asset_id')
                            ->label('Equipamento')
                            ->relationship('asset', 'name')
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(ServiceOrderStatus::class)
                            ->required()
                            ->default(ServiceOrderStatus::SCHEDULED),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Execução')
                    ->schema([
                        Forms\Components\DatePicker::make('scheduled_date')
                            ->label('Data Agendada')
                            ->required(),

                        Forms\Components\DatePicker::make('execution_date')
                            ->label('Data de Execução'),

                        Forms\Components\TextInput::make('quantity')
                            ->label('Quantidade')
                            ->numeric()
                            ->required()
                            ->minValue(0.01)
                            ->reactive()
                            ->afterStateUpdated(fn ($state, callable $set, callable $get) => 
                                $set('final_price', $state * $get('unit_price'))
                            ),

                        Forms\Components\TextInput::make('unit')
                            ->label('Unidade')
                            ->disabled()
                            ->dehydrated(true),

                        Forms\Components\TextInput::make('unit_price')
                            ->label('Preço Unitário')
                            ->numeric()
                            ->prefix('R$')
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(fn ($state, callable $set, callable $get) => 
                                $set('final_price', $state * $get('quantity'))
                            ),

                        Forms\Components\TextInput::make('final_price')
                            ->label('Valor Final')
                            ->numeric()
                            ->prefix('R$')
                            ->disabled()
                            ->dehydrated(true),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Medidores')
                    ->schema([
                        Forms\Components\TextInput::make('horimeter_start')
                            ->label('Horímetro Inicial')
                            ->numeric(),

                        Forms\Components\TextInput::make('horimeter_end')
                            ->label('Horímetro Final')
                            ->numeric(),

                        Forms\Components\TextInput::make('odometer_start')
                            ->label('Odômetro Inicial')
                            ->numeric(),

                        Forms\Components\TextInput::make('odometer_end')
                            ->label('Odômetro Final')
                            ->numeric(),
                    ])
                    ->columns(4)
                    ->collapsed(),

                Forms\Components\Section::make('Localização e Execução')
                    ->schema([
                        Forms\Components\TextInput::make('location')
                            ->label('Local de Execução')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('distance_km')
                            ->label('Distância (km)')
                            ->numeric()
                            ->suffix('km'),

                        Forms\Components\Select::make('operator_id')
                            ->label('Operador (Usuário)')
                            ->relationship('operator', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Selecione o usuário do sistema que operou'),

                        Forms\Components\Select::make('service_provider_id')
                            ->label('Prestador de Serviço')
                            ->options(function () {
                                return ServiceProvider::where('status', true)
                                    ->pluck('name', 'id');
                            })
                            ->searchable()
                            ->preload()
                            ->helperText('Prestador externo vinculado (opcional)'),

                        Forms\Components\TextInput::make('fuel_used')
                            ->label('Combustível Utilizado')
                            ->numeric()
                            ->suffix('L'),

                        Forms\Components\Textarea::make('work_description')
                            ->label('Descrição do Serviço')
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('notes')
                            ->label('Observações')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label('Número')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('scheduled_date')
                    ->label('Agendamento')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('associate.user.name')
                    ->label('Associado')
                    ->searchable()
                    ->limit(20),

                Tables\Columns\TextColumn::make('service.name')
                    ->label('Serviço')
                    ->searchable(),

                Tables\Columns\TextColumn::make('asset.name')
                    ->label('Equipamento')
                    ->limit(15),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Quantidade')
                    ->formatStateUsing(fn ($state, $record): string => 
                        number_format($state, 2, ',', '.') . ' ' . $record->unit
                    ),

                Tables\Columns\TextColumn::make('final_price')
                    ->label('Valor Associado')
                    ->money('BRL')
                    ->tooltip('Valor que o associado deve pagar'),

                Tables\Columns\TextColumn::make('provider_payment')
                    ->label('Pagto Prestador')
                    ->money('BRL')
                    ->tooltip('Valor a pagar ao prestador')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('associate_payment_status')
                    ->label('Pgto Associado')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state?->getLabel() ?? 'N/A')
                    ->color(fn ($state): string => $state?->getColor() ?? 'gray')
                    ->tooltip(fn ($record): string => $record->associate_paid_at ? 'Pago em ' . $record->associate_paid_at->format('d/m/Y') : 'Pendente'),

                Tables\Columns\TextColumn::make('provider_payment_status')
                    ->label('Pgto Prestador')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state?->getLabel() ?? 'N/A')
                    ->color(fn ($state): string => $state?->getColor() ?? 'gray')
                    ->tooltip(fn ($record): string => $record->provider_paid_at ? 'Pago em ' . $record->provider_paid_at->format('d/m/Y') : 'Pendente'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (ServiceOrderStatus $state): string => $state->getLabel())
                    ->color(fn (ServiceOrderStatus $state): string => $state->getColor()),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(ServiceOrderStatus::class),
                Tables\Filters\SelectFilter::make('associate_payment_status')
                    ->label('Pgto Associado')
                    ->options([
                        'pending' => 'Pendente',
                        'paid' => 'Pago',
                        'cancelled' => 'Cancelado',
                    ]),
                Tables\Filters\SelectFilter::make('provider_payment_status')
                    ->label('Pgto Prestador')
                    ->options([
                        'pending' => 'Pendente',
                        'paid' => 'Pago',
                        'cancelled' => 'Cancelado',
                    ]),
                Tables\Filters\SelectFilter::make('service_id')
                    ->label('Serviço')
                    ->relationship('service', 'name'),
                Tables\Filters\SelectFilter::make('asset_id')
                    ->label('Equipamento')
                    ->relationship('asset', 'name'),
            ])
            ->actions([
                Tables\Actions\Action::make('execute')
                    ->label('Executar')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\DatePicker::make('execution_date')
                            ->label('Data de Execução')
                            ->required()
                            ->default(now()),
                        Forms\Components\TextInput::make('horimeter_end')
                            ->label('Horímetro Final')
                            ->numeric(),
                        Forms\Components\TextInput::make('odometer_end')
                            ->label('Odômetro Final')
                            ->numeric(),
                    ])
                    ->action(function (ServiceOrder $record, array $data): void {
                        $record->update([
                            'execution_date' => $data['execution_date'],
                            'horimeter_end' => $data['horimeter_end'] ?? null,
                            'odometer_end' => $data['odometer_end'] ?? null,
                            'status' => ServiceOrderStatus::COMPLETED,
                        ]);
                    })
                    ->visible(fn (ServiceOrder $record): bool => 
                        $record->status === ServiceOrderStatus::SCHEDULED
                    ),

                Tables\Actions\Action::make('markAssociatePaid')
                    ->label('Marcar Pago')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Confirmar Pagamento do Associado')
                    ->modalDescription(fn (ServiceOrder $record): string => 
                        "Confirmar que o associado {$record->associate->user->name} pagou R$ " . 
                        number_format($record->final_price, 2, ',', '.') . "?"
                    )
                    ->form([
                        Forms\Components\DateTimePicker::make('payment_date')
                            ->label('Data do Pagamento')
                            ->required()
                            ->default(now())
                            ->seconds(false),
                        Forms\Components\TextInput::make('payment_reference')
                            ->label('Referência do Pagamento')
                            ->helperText('Ex: ID da transação, número do comprovante')
                            ->maxLength(100),
                        Forms\Components\Textarea::make('notes')
                            ->label('Observações')
                            ->rows(2),
                    ])
                    ->action(function (ServiceOrder $record, array $data): void {
                        DB::transaction(function () use ($record, $data) {
                            // Atualizar status de pagamento do associado
                            $record->update([
                                'associate_payment_status' => 'paid',
                                'associate_paid_at' => $data['payment_date'],
                                'associate_payment_id' => $data['payment_reference'] ?? null,
                            ]);

                            // Atualizar lançamento no ledger para PAGO
                            $ledger = AssociateLedger::where('reference_type', get_class($record))
                                ->where('reference_id', $record->id)
                                ->where('type', LedgerType::DEBIT)
                                ->where('category', LedgerCategory::SERVICO)
                                ->first();

                            if ($ledger) {
                                $ledger->update([
                                    'paid' => true,
                                    'paid_date' => $data['payment_date'],
                                ]);
                            }

                            // Registrar entrada de caixa
                            CashMovement::create([
                                'type' => CashMovementType::INCOME,
                                'amount' => $record->final_price,
                                'description' => "Recebimento OS {$record->number} - {$record->associate->user->name}",
                                'movement_date' => $data['payment_date'],
                                'reference_type' => get_class($record),
                                'reference_id' => $record->id,
                                'notes' => $data['notes'] ?? null,
                                'created_by' => auth()->id(),
                            ]);
                        });

                        Notification::make()
                            ->success()
                            ->title('Pagamento registrado')
                            ->body('O pagamento do associado foi registrado com sucesso.')
                            ->send();
                    })
                    ->visible(fn (ServiceOrder $record): bool => 
                        $record->status === ServiceOrderStatus::COMPLETED &&
                        $record->associate_payment_status === 'pending'
                    ),

                Tables\Actions\Action::make('payProvider')
                    ->label('Pagar Prestador')
                    ->icon('heroicon-o-banknotes')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Pagar Prestador')
                    ->modalDescription(function (ServiceOrder $record): string {
                        $providerName = $record->works->first()?->serviceProvider?->name ?? 'Prestador';
                        $amount = number_format($record->provider_payment, 2, ',', '.');
                        return "Confirmar pagamento de R$ {$amount} para {$providerName}?";
                    })
                    ->form([
                        Forms\Components\DateTimePicker::make('payment_date')
                            ->label('Data do Pagamento')
                            ->required()
                            ->default(now())
                            ->seconds(false),
                        Forms\Components\Select::make('payment_method')
                            ->label('Método de Pagamento')
                            ->options([
                                'pix' => 'PIX',
                                'transfer' => 'Transferência',
                                'cash' => 'Dinheiro',
                                'check' => 'Cheque',
                            ])
                            ->required()
                            ->default('pix'),
                        Forms\Components\TextInput::make('payment_reference')
                            ->label('Referência do Pagamento')
                            ->helperText('Ex: ID da transação, número do comprovante')
                            ->maxLength(100),
                        Forms\Components\Textarea::make('notes')
                            ->label('Observações')
                            ->rows(2),
                    ])
                    ->action(function (ServiceOrder $record, array $data): void {
                        DB::transaction(function () use ($record, $data) {
                            // Atualizar status de pagamento do prestador
                            $record->update([
                                'provider_payment_status' => 'paid',
                                'provider_paid_at' => $data['payment_date'],
                                'provider_payment_id' => $data['payment_reference'] ?? null,
                            ]);

                            // Atualizar o trabalho do prestador
                            $work = $record->works->first();
                            if ($work) {
                                $work->update([
                                    'payment_status' => 'pago',
                                    'paid_date' => $data['payment_date'],
                                ]);
                            }

                            // Registrar saída de caixa
                            $providerName = $work?->serviceProvider?->name ?? 'Prestador';
                            CashMovement::create([
                                'type' => CashMovementType::EXPENSE,
                                'amount' => $record->provider_payment,
                                'description' => "Pagamento OS {$record->number} - {$providerName}",
                                'movement_date' => $data['payment_date'],
                                'payment_method' => $data['payment_method'],
                                'reference_type' => get_class($record),
                                'reference_id' => $record->id,
                                'notes' => $data['notes'] ?? null,
                                'created_by' => auth()->id(),
                            ]);
                        });

                        Notification::make()
                            ->success()
                            ->title('Pagamento efetuado')
                            ->body('O pagamento ao prestador foi registrado com sucesso.')
                            ->send();
                    })
                    ->visible(fn (ServiceOrder $record): bool => 
                        $record->status === ServiceOrderStatus::COMPLETED &&
                        $record->associate_payment_status === 'paid' &&
                        $record->provider_payment_status === 'pending' &&
                        $record->provider_payment > 0
                    ),

                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulkPayProviders')
                        ->label('Pagar Prestadores em Lote')
                        ->icon('heroicon-o-banknotes')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Pagar múltiplos prestadores')
                        ->modalDescription(function ($records): string {
                            $total = number_format($records->sum('provider_payment'), 2, ',', '.');
                            $count = $records->count();
                            return "Confirmar pagamento de R$ {$total} para {$count} prestadores?";
                        })
                        ->form([
                            Forms\Components\DateTimePicker::make('payment_date')
                                ->label('Data do Pagamento')
                                ->required()
                                ->default(now())
                                ->seconds(false),
                            Forms\Components\Select::make('payment_method')
                                ->label('Método de Pagamento')
                                ->options([
                                    'pix' => 'PIX',
                                    'transfer' => 'Transferência',
                                    'cash' => 'Dinheiro',
                                    'check' => 'Cheque',
                                ])
                                ->required()
                                ->default('pix'),
                            Forms\Components\Textarea::make('notes')
                                ->label('Observações')
                                ->helperText('Será aplicado a todos os pagamentos')
                                ->rows(2),
                        ])
                        ->action(function ($records, array $data): void {
                            $successCount = 0;
                            $totalPaid = 0;

                            DB::transaction(function () use ($records, $data, &$successCount, &$totalPaid) {
                                foreach ($records as $record) {
                                    // Atualizar status de pagamento do prestador
                                    $record->update([
                                        'provider_payment_status' => 'paid',
                                        'provider_paid_at' => $data['payment_date'],
                                    ]);

                                    // Atualizar o trabalho do prestador
                                    $work = $record->works->first();
                                    if ($work) {
                                        $work->update([
                                            'payment_status' => 'pago',
                                            'paid_date' => $data['payment_date'],
                                        ]);
                                    }

                                    // Registrar saída de caixa
                                    $providerName = $work?->serviceProvider?->name ?? 'Prestador';
                                    CashMovement::create([
                                        'type' => CashMovementType::EXPENSE,
                                        'amount' => $record->provider_payment,
                                        'description' => "Pagamento Lote OS {$record->number} - {$providerName}",
                                        'movement_date' => $data['payment_date'],
                                        'payment_method' => $data['payment_method'],
                                        'reference_type' => get_class($record),
                                        'reference_id' => $record->id,
                                        'notes' => $data['notes'] ?? null,
                                        'created_by' => auth()->id(),
                                    ]);

                                    $successCount++;
                                    $totalPaid += $record->provider_payment;
                                }
                            });

                            Notification::make()
                                ->success()
                                ->title('Pagamentos efetuados em lote')
                                ->body("{$successCount} prestadores pagos. Total: R$ " . 
                                    number_format($totalPaid, 2, ',', '.'))
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion()
                        ->visible(function ($records = null): bool {
                            if (is_null($records)) return false;
                            $collection = $records instanceof \Illuminate\Support\Collection ? $records : collect($records);
                            return $collection->every(function ($record) {
                                return $record->status === ServiceOrderStatus::COMPLETED &&
                                    $record->associate_payment_status === 'paid' &&
                                    $record->provider_payment_status === 'pending' &&
                                    $record->provider_payment > 0;
                            });
                        }),

                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListServiceOrders::route('/'),
            'create' => Pages\CreateServiceOrder::route('/create'),
            'view' => Pages\ViewServiceOrder::route('/{record}'),
            'edit' => Pages\EditServiceOrder::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
