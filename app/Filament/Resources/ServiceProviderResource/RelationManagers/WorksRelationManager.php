<?php

namespace App\Filament\Resources\ServiceProviderResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class WorksRelationManager extends RelationManager
{
    protected static string $relationship = 'works';

    protected static ?string $title = 'Serviços Realizados';

    protected static ?string $modelLabel = 'Serviço';

    protected static ?string $pluralModelLabel = 'Serviços';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DatePicker::make('work_date')
                    ->label('Data do Serviço')
                    ->required()
                    ->default(now()),

                Forms\Components\TextInput::make('description')
                    ->label('Descrição do Serviço')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                Forms\Components\Select::make('service_order_id')
                    ->label('Ordem de Serviço (opcional)')
                    ->relationship('serviceOrder', 'number')
                    ->searchable()
                    ->preload()
                    ->helperText('Vincule a uma OS existente se aplicável'),

                Forms\Components\Select::make('associate_id')
                    ->label('Associado Atendido (opcional)')
                    ->relationship('associate', 'id')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->user->name ?? $record->id)
                    ->searchable()
                    ->preload()
                    ->helperText('Para quem o serviço foi prestado'),

                Forms\Components\TextInput::make('hours_worked')
                    ->label('Horas Trabalhadas')
                    ->numeric()
                    ->minValue(0.01)
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                        $provider = $this->ownerRecord;
                        if ($state && $provider->hourly_rate) {
                            $set('unit_price', $provider->hourly_rate);
                            $set('total_value', round((float)$state * (float)$provider->hourly_rate, 2));
                        }
                    }),

                Forms\Components\TextInput::make('unit_price')
                    ->label('Valor por Hora/Unidade')
                    ->numeric()
                    ->prefix('R$')
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                        $hours = (float) ($get('hours_worked') ?? 0);
                        if ($hours > 0 && $state) {
                            $set('total_value', round($hours * (float)$state, 2));
                        }
                    }),

                Forms\Components\TextInput::make('total_value')
                    ->label('Valor Total')
                    ->numeric()
                    ->prefix('R$')
                    ->required(),

                Forms\Components\TextInput::make('location')
                    ->label('Local')
                    ->maxLength(191),

                Forms\Components\Select::make('payment_status')
                    ->label('Status Pagamento')
                    ->options([
                        'pendente' => 'Pendente',
                        'pago' => 'Pago',
                        'cancelado' => 'Cancelado',
                    ])
                    ->default('pendente')
                    ->required(),

                Forms\Components\DatePicker::make('paid_date')
                    ->label('Data do Pagamento')
                    ->visible(fn (callable $get) => $get('payment_status') === 'pago'),

                Forms\Components\Textarea::make('notes')
                    ->label('Observações')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->defaultSort('work_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('work_date')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Descrição')
                    ->searchable()
                    ->limit(35),

                Tables\Columns\TextColumn::make('associate.user.name')
                    ->label('Associado')
                    ->placeholder('Interno')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('hours_worked')
                    ->label('Horas')
                    ->formatStateUsing(fn ($state) => $state ? number_format($state, 1, ',', '.') . 'h' : '-')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('total_value')
                    ->label('Valor')
                    ->money('BRL')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Pagamento')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => match($state) {
                        'pendente' => 'Pendente',
                        'pago' => 'Pago',
                        'cancelado' => 'Cancelado',
                        default => $state,
                    })
                    ->color(fn ($state): string => match($state) {
                        'pendente' => 'warning',
                        'pago' => 'success',
                        'cancelado' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('paid_date')
                    ->label('Pago em')
                    ->date('d/m/Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('payment_status')
                    ->label('Status Pagamento')
                    ->options([
                        'pendente' => 'Pendente',
                        'pago' => 'Pago',
                        'cancelado' => 'Cancelado',
                    ]),
                Tables\Filters\Filter::make('work_period')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('De'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Até'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('work_date', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('work_date', '<=', $date));
                    }),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Registrar Serviço')
                    ->icon('heroicon-o-plus'),
            ])
            ->actions([
                Tables\Actions\Action::make('mark_paid')
                    ->label('Marcar Pago')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Registrar Pagamento')
                    ->modalDescription('Ao confirmar, o valor será debitado do saldo a receber do prestador.')
                    ->form([
                        Forms\Components\DatePicker::make('paid_date')
                            ->label('Data do Pagamento')
                            ->required()
                            ->default(now()),
                        Forms\Components\Textarea::make('notes')
                            ->label('Observações')
                            ->rows(2),
                    ])
                    ->action(function ($record, array $data) {
                        \DB::transaction(function () use ($record, $data) {
                            $record->update([
                                'payment_status' => 'pago',
                                'paid_date' => $data['paid_date'],
                                'notes' => $data['notes'] ?? $record->notes,
                            ]);

                            // Registrar no ledger do prestador (débito - recebeu pagamento)
                            $currentBalance = $record->serviceProvider->current_balance ?? 0;
                            \App\Models\ServiceProviderLedger::create([
                                'service_provider_id' => $record->service_provider_id,
                                'type' => \App\Enums\LedgerType::DEBIT,
                                'category' => \App\Enums\ProviderLedgerCategory::PAGAMENTO_RECEBIDO,
                                'amount' => $record->total_value,
                                'balance_after' => $currentBalance - $record->total_value,
                                'description' => "Pagamento recebido - {$record->description}",
                                'reference_type' => get_class($record),
                                'reference_id' => $record->id,
                                'transaction_date' => $data['paid_date'],
                                'created_by' => auth()->id(),
                            ]);
                        });
                        
                        Notification::make()->success()->title('Pagamento registrado!')->send();
                    })
                    ->visible(fn ($record) => $record->payment_status === 'pendente'),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('mark_all_paid')
                        ->label('Marcar Todos como Pago')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Registrar Pagamentos em Lote')
                        ->modalDescription('Todos os serviços selecionados serão marcados como pagos e o saldo do prestador será atualizado.')
                        ->form([
                            Forms\Components\DatePicker::make('paid_date')
                                ->label('Data do Pagamento')
                                ->required()
                                ->default(now()),
                            Forms\Components\Textarea::make('notes')
                                ->label('Observações')
                                ->rows(2),
                        ])
                        ->action(function ($records, array $data) {
                            \DB::transaction(function () use ($records, $data) {
                                $updatedCount = 0;
                                foreach ($records as $record) {
                                    if ($record->payment_status === 'pendente') {
                                        $record->update([
                                            'payment_status' => 'pago',
                                            'paid_date' => $data['paid_date'],
                                            'notes' => $data['notes'] ?? $record->notes,
                                        ]);

                                        // Registrar no ledger do prestador
                                        $currentBalance = $record->serviceProvider->current_balance ?? 0;
                                        \App\Models\ServiceProviderLedger::create([
                                            'service_provider_id' => $record->service_provider_id,
                                            'type' => \App\Enums\LedgerType::DEBIT,
                                            'category' => \App\Enums\ProviderLedgerCategory::PAGAMENTO_RECEBIDO,
                                            'amount' => $record->total_value,
                                            'balance_after' => $currentBalance - $record->total_value,
                                            'description' => "Pagamento recebido - {$record->description}",
                                            'reference_type' => get_class($record),
                                            'reference_id' => $record->id,
                                            'transaction_date' => $data['paid_date'],
                                            'created_by' => auth()->id(),
                                        ]);
                                        
                                        $updatedCount++;
                                    }
                                }
                                
                                Notification::make()
                                    ->success()
                                    ->title("$updatedCount pagamento(s) registrado(s)!")
                                    ->send();
                            });
                        })
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
