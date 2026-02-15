<?php

namespace App\Filament\Resources;

use App\Enums\CashMovementType;
use App\Enums\LedgerType;
use App\Enums\PaymentMethod;
use App\Enums\ProviderLedgerCategory;
use App\Enums\ServiceOrderPaymentStatus;
use App\Enums\ServiceOrderStatus;
use App\Filament\Resources\ServiceOrderResource\Pages;
use App\Models\BankAccount;
use App\Models\CashMovement;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderPayment;
use App\Models\ServiceProvider;
use App\Models\ServiceProviderLedger;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;
use App\Filament\Traits\TenantScoped;

class ServiceOrderResource extends Resource
{
    use TenantScoped;
    protected static ?string $model = ServiceOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Serviços';

    protected static ?string $modelLabel = 'Ordem de Serviço';

    protected static ?string $pluralModelLabel = 'Ordens de Serviço';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\Section::make('Dados da Ordem')
                ->schema([
                    Forms\Components\TextInput::make('number')
                        ->label('Número')
                        ->disabled()
                        ->dehydrated(false)
                        ->visibleOn('edit'),

                    Forms\Components\Select::make('service_id')
                        ->label('Serviço')
                        ->relationship('service', 'name')
                        ->searchable()->preload()->required()
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $set) {
                            if ($state) {
                                $service = \App\Models\Service::find($state);
                                if ($service) {
                                    $set('unit', $service->unit);
                                    $set('unit_price', $service->associate_price ?? $service->base_price);
                                }
                            }
                        }),

                    Forms\Components\Select::make('associate_id')
                        ->label('Associado (cliente)')
                        ->relationship('associate', 'id')
                        ->getOptionLabelFromRecordUsing(fn ($record) => optional($record->user)->name ?? $record->property_name ?? "#{$record->id}")
                        ->searchable()->preload()
                        ->helperText('Deixe vazio para pessoa avulsa'),

                    Forms\Components\Select::make('service_provider_id')
                        ->label('Prestador de Serviço')
                        ->reactive()
                        ->options(fn (callable $get) => ($get('service_id'))
                                ? \App\Models\ServiceProvider::whereHas('services', fn ($q) => $q->where('services.id', $get('service_id')))->where('status', true)->pluck('name', 'id')
                                : \App\Models\ServiceProvider::where('status', true)->pluck('name', 'id')
                        )
                        ->searchable()->preload()->required(),

                    Forms\Components\Select::make('asset_id')
                        ->label('Equipamento')
                        ->relationship('asset', 'name')
                        ->searchable()->preload(),

                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options(ServiceOrderStatus::class)
                        ->required()
                        ->default(ServiceOrderStatus::SCHEDULED),
                ])->columns(3),

            Forms\Components\Section::make('Agendamento e Local')
                ->schema([
                    Forms\Components\DatePicker::make('scheduled_date')
                        ->label('Data Agendada')->required(),
                    Forms\Components\DatePicker::make('execution_date')
                        ->label('Data de Execução'),
                    Forms\Components\TextInput::make('location')
                        ->label('Local')->maxLength(255),
                    Forms\Components\TextInput::make('distance_km')
                        ->label('Distância (km)')->numeric()->suffix('km'),
                ])->columns(4),

            Forms\Components\Section::make('Valores')
                ->schema([
                    Forms\Components\TextInput::make('quantity')
                        ->label('Qtd. Estimada')->numeric(),
                    Forms\Components\TextInput::make('actual_quantity')
                        ->label('Qtd. Executada')->numeric(),
                    Forms\Components\TextInput::make('unit')
                        ->label('Unidade')->disabled()->dehydrated(true),
                    Forms\Components\TextInput::make('unit_price')
                        ->label('Preço Unitário (Cliente)')->numeric()->prefix('R$'),
                    Forms\Components\TextInput::make('final_price')
                        ->label('Total Cliente')->numeric()->prefix('R$'),
                    Forms\Components\TextInput::make('provider_payment')
                        ->label('Total Prestador')->numeric()->prefix('R$')
                        ->helperText('Qtd × Taxa do prestador'),
                ])->columns(3),

            Forms\Components\Section::make('Medidores')
                ->schema([
                    Forms\Components\TextInput::make('horimeter_start')->label('Horímetro Ini')->numeric(),
                    Forms\Components\TextInput::make('horimeter_end')->label('Horímetro Fim')->numeric(),
                    Forms\Components\TextInput::make('odometer_start')->label('Odômetro Ini')->numeric(),
                    Forms\Components\TextInput::make('odometer_end')->label('Odômetro Fim')->numeric(),
                    Forms\Components\TextInput::make('fuel_used')->label('Combustível (L)')->numeric()->suffix('L'),
                ])->columns(5)->collapsed(),

            Forms\Components\Section::make('Descrição')
                ->schema([
                    Forms\Components\Textarea::make('work_description')
                        ->label('Descrição do Serviço')->rows(3)->columnSpanFull(),
                    Forms\Components\Textarea::make('notes')
                        ->label('Observações')->rows(2)->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label('Número')->searchable()->sortable(),

                Tables\Columns\TextColumn::make('scheduled_date')
                    ->label('Data')->date('d/m/Y')->sortable(),

                Tables\Columns\TextColumn::make('service.name')
                    ->label('Serviço')->searchable()->limit(20),

                Tables\Columns\TextColumn::make('associate.user.display_name')
                    ->label('Cliente')->searchable()->limit(20)
                    ->default('Avulso'),

                Tables\Columns\TextColumn::make('serviceProvider.name')
                    ->label('Prestador')->searchable()->limit(15)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('actual_quantity')
                    ->label('Qtd.')
                    ->formatStateUsing(fn ($state, $record) => $state ? number_format($state, 1, ',', '.').' '.$record->unit : '-'),

                Tables\Columns\TextColumn::make('final_price')
                    ->label('Valor Cliente')->money('BRL')
                    ->tooltip('Valor cobrado do cliente'),

                Tables\Columns\TextColumn::make('provider_payment')
                    ->label('Valor Prestador')->money('BRL')
                    ->tooltip('Valor a pagar ao prestador')
                    ->color('success'),

                Tables\Columns\TextColumn::make('associate_payment_status')
                    ->label('Pgto Cliente')->badge()
                    ->formatStateUsing(fn ($state) => $state?->getLabel() ?? 'N/A')
                    ->color(fn ($state) => $state?->getColor() ?? 'gray'),

                Tables\Columns\TextColumn::make('provider_payment_status')
                    ->label('Pgto Prestador')->badge()
                    ->formatStateUsing(fn ($state) => $state?->getLabel() ?? 'N/A')
                    ->color(fn ($state) => $state?->getColor() ?? 'gray'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')->badge()
                    ->formatStateUsing(fn (ServiceOrderStatus $state) => $state->getLabel())
                    ->color(fn (ServiceOrderStatus $state) => $state->getColor()),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')->options(ServiceOrderStatus::class),
                Tables\Filters\SelectFilter::make('associate_payment_status')
                    ->label('Pgto Cliente')
                    ->options(ServiceOrderPaymentStatus::class),
                Tables\Filters\SelectFilter::make('provider_payment_status')
                    ->label('Pgto Prestador')
                    ->options(ServiceOrderPaymentStatus::class),
                Tables\Filters\SelectFilter::make('service_provider_id')
                    ->label('Prestador')
                    ->options(fn () => ServiceProvider::where('status', true)->pluck('name', 'id')),
            ])
            ->actions([

                // ── INICIAR EXECUÇÃO ──
                Tables\Actions\Action::make('startExecution')
                    ->label('Iniciar Execução')
                    ->icon('heroicon-o-play')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Iniciar Execução')
                    ->modalDescription('Alterar status para Em Execução?')
                    ->action(function (ServiceOrder $record): void {
                        $record->update(['status' => ServiceOrderStatus::IN_PROGRESS]);
                        Notification::make()->success()->title('Execução iniciada')->send();
                    })
                    ->visible(fn (ServiceOrder $record): bool => $record->status === ServiceOrderStatus::SCHEDULED),

                // ── FINALIZAR EXECUÇÃO ──
                Tables\Actions\Action::make('finishExecution')
                    ->label('Finalizar Execução')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->modalHeading('Finalizar Execução')
                    ->form([
                        Forms\Components\DatePicker::make('execution_date')
                            ->label('Data de Execução')->required()->default(now()),
                        Forms\Components\TextInput::make('actual_quantity')
                            ->label('Quantidade Executada')->numeric()->required()->minValue(0.1),
                        Forms\Components\TextInput::make('horimeter_start')
                            ->label('Horímetro Inicial')->numeric(),
                        Forms\Components\TextInput::make('horimeter_end')
                            ->label('Horímetro Final')->numeric(),
                        Forms\Components\TextInput::make('fuel_used')
                            ->label('Combustível (L)')->numeric(),
                        Forms\Components\Textarea::make('work_description')
                            ->label('Descrição do Trabalho')->required()->rows(3),
                    ])
                    ->action(function (ServiceOrder $record, array $data): void {
                        DB::transaction(function () use ($record, $data) {
                            $providerService = \App\Models\ServiceProviderService::where('service_provider_id', $record->service_provider_id)
                                ->where('service_id', $record->service_id)->first();

                            if (! $providerService) {
                                Notification::make()->danger()
                                    ->title('Erro')->body('Taxa do prestador não configurada.')
                                    ->send();

                                return;
                            }

                            $providerRate = match ($record->unit) {
                                'hora' => (float) ($providerService->provider_hourly_rate ?? 0),
                                'diaria', 'dia' => (float) ($providerService->provider_daily_rate ?? 0),
                                default => (float) ($providerService->provider_unit_rate ?? 0),
                            };

                            $service = $record->service;
                            $clientRate = $record->associate_id
                                ? ($service->associate_price ?? $service->base_price ?? 0)
                                : ($service->non_associate_price ?? $service->base_price ?? 0);

                            $qty = (float) $data['actual_quantity'];
                            $totalClient = round($qty * $clientRate, 2);
                            $totalProvider = round($qty * $providerRate, 2);

                            $record->update([
                                'status' => ServiceOrderStatus::AWAITING_PAYMENT,
                                'execution_date' => $data['execution_date'],
                                'actual_quantity' => $qty,
                                'unit_price' => $clientRate,
                                'total_price' => $totalClient,
                                'final_price' => $totalClient,
                                'provider_payment' => $totalProvider,
                                'work_description' => $data['work_description'],
                                'horimeter_start' => $data['horimeter_start'] ?? null,
                                'horimeter_end' => $data['horimeter_end'] ?? null,
                                'fuel_used' => $data['fuel_used'] ?? null,
                                'associate_payment_status' => ServiceOrderPaymentStatus::PENDING,
                                'provider_payment_status' => ServiceOrderPaymentStatus::PENDING,
                            ]);
                        });
                        Notification::make()->success()->title('Execução finalizada')->body('Aguardando pagamento do cliente')->send();
                    })
                    ->visible(fn (ServiceOrder $record): bool => $record->status === ServiceOrderStatus::IN_PROGRESS),

                // ── REGISTRAR E FATURAR PAGAMENTO DO CLIENTE (SIMPLIFICADO) ──
                Tables\Actions\Action::make('registerClientPayment')
                    ->label('Receber do Cliente')
                    ->icon('heroicon-o-banknotes')
                    ->color('warning')
                    ->modalHeading('Receber Pagamento do Cliente')
                    ->modalDescription(fn (ServiceOrder $record) => sprintf(
                        'Ordem %s · Total: R$ %s · Já pago: R$ %s · Restante: R$ %s',
                        $record->number,
                        number_format($record->final_price, 2, ',', '.'),
                        number_format($record->total_client_paid, 2, ',', '.'),
                        number_format($record->client_remaining, 2, ',', '.')
                    ))
                    ->form([
                        Forms\Components\DatePicker::make('payment_date')
                            ->label('Data do Pagamento')->required()->default(now()),
                        Forms\Components\TextInput::make('amount')
                            ->label('Valor (R$)')->numeric()->required()->minValue(0.01)
                            ->default(fn (ServiceOrder $record) => $record->client_remaining),
                        Forms\Components\Select::make('payment_method')
                            ->label('Método')->options(PaymentMethod::class)->required()->default('dinheiro'),
                        Forms\Components\Select::make('bank_account_id')
                            ->label('Conta Bancária')
                            ->options(fn () => BankAccount::pluck('name', 'id'))
                            ->searchable()->preload()
                            ->required()
                            ->default(fn () => BankAccount::where('is_default', true)->first()?->id)
                            ->helperText('Conta onde o valor será creditado'),
                        Forms\Components\FileUpload::make('receipt_path')
                            ->label('Comprovante')
                            ->disk('public')->directory('receipts/payments')
                            ->acceptedFileTypes(['application/pdf', 'image/*'])
                            ->maxSize(5120),
                        Forms\Components\Textarea::make('notes')
                            ->label('Observações')->rows(2),
                    ])
                    ->action(function (ServiceOrder $record, array $data): void {
                        DB::transaction(function () use ($record, $data) {
                            // Criar pagamento já BILLED (faturado automaticamente)
                            $payment = ServiceOrderPayment::create([
                                'service_order_id' => $record->id,
                                'type' => 'client',
                                'status' => ServiceOrderPaymentStatus::BILLED,
                                'payment_date' => $data['payment_date'],
                                'amount' => $data['amount'],
                                'payment_method' => $data['payment_method'],
                                'bank_account_id' => $data['bank_account_id'],
                                'receipt_path' => $data['receipt_path'] ?? null,
                                'notes' => $data['notes'] ?? null,
                                'registered_by' => auth()->id(),
                            ]);

                            // Criar movimentação de caixa
                            CashMovement::create([
                                'type' => CashMovementType::INCOME,
                                'amount' => $data['amount'],
                                'description' => "Recebimento OS {$record->number}".
                                    ($record->associate ? ' - '.(optional($record->associate->user)->name ?? '') : ' - Avulso'),
                                'movement_date' => $data['payment_date'],
                                'bank_account_id' => $data['bank_account_id'],
                                'payment_method' => $data['payment_method'],
                                'reference_type' => ServiceOrder::class,
                                'reference_id' => $record->id,
                                'notes' => $data['notes'] ?? 'Recebimento e faturamento automático',
                                'created_by' => auth()->id(),
                            ]);

                            // Verificar se cliente pagou tudo
                            $record->refresh();
                            $totalBilled = ServiceOrderPayment::where('service_order_id', $record->id)
                                ->where('type', 'client')
                                ->where('status', ServiceOrderPaymentStatus::BILLED)
                                ->sum('amount');

                            if ($totalBilled >= $record->final_price) {
                                $record->update([
                                    'associate_payment_status' => ServiceOrderPaymentStatus::BILLED,
                                    'associate_paid_at' => now(),
                                ]);
                            }
                        });

                        Notification::make()->success()
                            ->title('Pagamento recebido e faturado')
                            ->body('Movimentação de caixa registrada automaticamente!')
                            ->send();
                    })
                    ->visible(fn (ServiceOrder $record): bool => in_array($record->status, [ServiceOrderStatus::AWAITING_PAYMENT, ServiceOrderStatus::COMPLETED]) &&
                        $record->client_remaining > 0
                    ),

                // ── FATURAR PAGAMENTO DO CLIENTE ──
                Tables\Actions\Action::make('billClientPayment')
                    ->label('Faturar Pagamento')
                    ->icon('heroicon-o-document-check')
                    ->color('info')
                    ->modalHeading('Faturar Pagamento Pendente')
                    ->modalDescription(fn (ServiceOrder $record) => sprintf(
                        'Ordem %s · Selecione o pagamento para faturar',
                        $record->number
                    ))
                    ->form(function (ServiceOrder $record) {
                        $pendingPayments = ServiceOrderPayment::where('service_order_id', $record->id)
                            ->where('type', 'client')
                            ->where('status', ServiceOrderPaymentStatus::PENDING)
                            ->get();

                        return [
                            Forms\Components\Select::make('payment_id')
                                ->label('Pagamento')
                                ->options($pendingPayments->mapWithKeys(function ($payment) {
                                    return [$payment->id => sprintf(
                                        'R$ %s - %s - %s',
                                        number_format($payment->amount, 2, ',', '.'),
                                        $payment->payment_method->getLabel(),
                                        $payment->payment_date->format('d/m/Y')
                                    )];
                                }))
                                ->required()
                                ->reactive(),

                            Forms\Components\Select::make('bank_account_id')
                                ->label('Conta Bancária')
                                ->options(fn () => BankAccount::pluck('name', 'id'))
                                ->searchable()->preload()
                                ->required()
                                ->default(fn () => BankAccount::where('is_default', true)->first()?->id)
                                ->helperText('Conta onde o valor será creditado'),
                        ];
                    })
                    ->action(function (ServiceOrder $record, array $data): void {
                        DB::transaction(function () use ($record, $data) {
                            $payment = ServiceOrderPayment::findOrFail($data['payment_id']);

                            // Criar movimentação de caixa
                            CashMovement::create([
                                'type' => CashMovementType::INCOME,
                                'amount' => $payment->amount,
                                'description' => "Recebimento OS {$record->number}".
                                    ($record->associate ? ' - '.(optional($record->associate->user)->name ?? '') : ' - Avulso'),
                                'movement_date' => $payment->payment_date,
                                'bank_account_id' => $data['bank_account_id'],
                                'payment_method' => $payment->payment_method,
                                'reference_type' => ServiceOrder::class,
                                'reference_id' => $record->id,
                                'notes' => 'Faturamento do pagamento #'.$payment->id,
                                'created_by' => auth()->id(),
                            ]);

                            // Atualizar status do pagamento para faturado
                            $payment->update([
                                'status' => ServiceOrderPaymentStatus::BILLED,
                                'bank_account_id' => $data['bank_account_id'],
                            ]);

                            // Verificar se cliente pagou tudo e está tudo faturado
                            $record->refresh();
                            $totalBilled = ServiceOrderPayment::where('service_order_id', $record->id)
                                ->where('type', 'client')
                                ->where('status', ServiceOrderPaymentStatus::BILLED)
                                ->sum('amount');

                            if ($totalBilled >= $record->final_price) {
                                $record->update([
                                    'associate_payment_status' => ServiceOrderPaymentStatus::BILLED,
                                    'associate_paid_at' => now(),
                                ]);
                            }
                        });

                        Notification::make()->success()
                            ->title('Pagamento faturado')
                            ->body('Movimentação de caixa registrada!')
                            ->send();
                    })
                    ->visible(fn (ServiceOrder $record): bool => ServiceOrderPayment::where('service_order_id', $record->id)
                        ->where('type', 'client')
                        ->where('status', ServiceOrderPaymentStatus::PENDING)
                        ->exists()
                    ),

                // ── CONCLUIR ORDEM ──
                Tables\Actions\Action::make('completeOrder')
                    ->label('Concluir Ordem')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Concluir Ordem')
                    ->modalDescription('Cliente pagou tudo e está tudo faturado. Marcar ordem como concluída?')
                    ->action(function (ServiceOrder $record): void {
                        $record->update(['status' => ServiceOrderStatus::COMPLETED]);
                        Notification::make()->success()
                            ->title('Ordem concluída')
                            ->body('Ordem de serviço concluída com sucesso.')
                            ->send();
                    })
                    ->visible(function (ServiceOrder $record): bool {
                        if ($record->status !== ServiceOrderStatus::AWAITING_PAYMENT) {
                            return false;
                        }

                        // Verificar se cliente pagou tudo
                        $totalBilled = ServiceOrderPayment::where('service_order_id', $record->id)
                            ->where('type', 'client')
                            ->where('status', ServiceOrderPaymentStatus::BILLED)
                            ->sum('amount');

                        // Verificar se não tem pagamentos pendentes
                        $hasPending = ServiceOrderPayment::where('service_order_id', $record->id)
                            ->where('type', 'client')
                            ->where('status', ServiceOrderPaymentStatus::PENDING)
                            ->exists();

                        return $totalBilled >= $record->final_price && ! $hasPending;
                    }),

                // ── REGISTRAR PAGAMENTO AO PRESTADOR ──
                Tables\Actions\Action::make('registerProviderPayment')
                    ->label('Pagar Prestador')
                    ->icon('heroicon-o-credit-card')
                    ->color('success')
                    ->modalHeading('Registrar Pagamento ao Prestador')
                    ->modalDescription(fn (ServiceOrder $record) => sprintf(
                        'Ordem %s · Prestador: %s · Total: R$ %s · Já pago: R$ %s · Restante: R$ %s',
                        $record->number,
                        optional($record->serviceProvider)->name ?? '-',
                        number_format($record->provider_payment ?? 0, 2, ',', '.'),
                        number_format($record->total_provider_paid, 2, ',', '.'),
                        number_format($record->provider_remaining, 2, ',', '.')
                    ))
                    ->form([
                        Forms\Components\DatePicker::make('payment_date')
                            ->label('Data do Pagamento')->required()->default(now()),
                        Forms\Components\TextInput::make('amount')
                            ->label('Valor (R$)')->numeric()->required()->minValue(0.01)
                            ->default(fn (ServiceOrder $record) => $record->provider_remaining),
                        Forms\Components\Select::make('payment_method')
                            ->label('Método')->options(PaymentMethod::class)->required()->default('pix'),
                        Forms\Components\Select::make('bank_account_id')
                            ->label('Conta Bancária')
                            ->options(fn () => BankAccount::pluck('name', 'id'))
                            ->searchable()->preload()
                            ->required()
                            ->default(fn () => BankAccount::where('is_default', true)->first()?->id)
                            ->helperText('Conta de onde sai o dinheiro'),
                        Forms\Components\FileUpload::make('receipt_path')
                            ->label('Comprovante')
                            ->disk('public')->directory('receipts/payments')
                            ->acceptedFileTypes(['application/pdf', 'image/*'])
                            ->maxSize(5120),
                        Forms\Components\Textarea::make('notes')
                            ->label('Observações')->rows(2),
                    ])
                    ->action(function (ServiceOrder $record, array $data): void {
                        DB::transaction(function () use ($record, $data) {
                            // Criar registro de pagamento com status BILLED (pago diretamente)
                            ServiceOrderPayment::create([
                                'service_order_id' => $record->id,
                                'type' => 'provider',
                                'status' => ServiceOrderPaymentStatus::BILLED,
                                'payment_date' => $data['payment_date'],
                                'amount' => $data['amount'],
                                'payment_method' => $data['payment_method'],
                                'bank_account_id' => $data['bank_account_id'] ?? null,
                                'receipt_path' => $data['receipt_path'] ?? null,
                                'notes' => $data['notes'] ?? null,
                                'registered_by' => auth()->id(),
                            ]);

                            // Registrar saída de caixa
                            $providerName = optional($record->serviceProvider)->name ?? 'Prestador';
                            CashMovement::create([
                                'type' => CashMovementType::EXPENSE,
                                'amount' => $data['amount'],
                                'description' => "Pagamento OS {$record->number} - {$providerName}",
                                'movement_date' => $data['payment_date'],
                                'bank_account_id' => $data['bank_account_id'] ?? null,
                                'payment_method' => $data['payment_method'],
                                'reference_type' => ServiceOrder::class,
                                'reference_id' => $record->id,
                                'notes' => $data['notes'] ?? null,
                                'created_by' => auth()->id(),
                            ]);

                            // Registrar no ledger do prestador
                            if ($record->service_provider_id) {
                                ServiceProviderLedger::create([
                                    'service_provider_id' => $record->service_provider_id,
                                    'type' => LedgerType::CREDIT,
                                    'category' => ProviderLedgerCategory::PAGAMENTO_RECEBIDO,
                                    'amount' => $data['amount'],
                                    'description' => "Pagamento OS {$record->number}",
                                    'transaction_date' => $data['payment_date'],
                                    'reference_type' => ServiceOrder::class,
                                    'reference_id' => $record->id,
                                    'notes' => $data['notes'] ?? null,
                                    'created_by' => auth()->id(),
                                ]);
                            }

                            // Marcar pedidos de saque relacionados como aprovados
                            \App\Models\ProviderPaymentRequest::where('service_order_id', $record->id)
                                ->where('service_provider_id', $record->service_provider_id)
                                ->where('status', 'pending')
                                ->update([
                                    'status' => 'approved',
                                    'approved_by' => auth()->id(),
                                    'approved_at' => now(),
                                ]);

                            // Verificar se prestador já recebeu tudo
                            $record->refresh();
                            if ($record->isProviderFullyPaid()) {
                                $record->update([
                                    'provider_payment_status' => ServiceOrderPaymentStatus::BILLED,
                                    'provider_paid_at' => now(),
                                ]);
                            }
                        });

                        Notification::make()->success()
                            ->title('Pagamento registrado')
                            ->body('Pagamento ao prestador registrado com sucesso.')
                            ->send();
                    })
                    ->visible(fn (ServiceOrder $record): bool => in_array($record->status, [ServiceOrderStatus::AWAITING_PAYMENT, ServiceOrderStatus::COMPLETED]) &&
                        $record->provider_remaining > 0
                    ),

                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // PAGAR MÚLTIPLOS PRESTADORES
                    Tables\Actions\BulkAction::make('payProviders')
                        ->label('Pagar Prestadores')
                        ->icon('heroicon-o-banknotes')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Pagar Prestadores')
                        ->modalDescription(fn ($records) => sprintf(
                            'Pagar %d ordens selecionadas. Total: R$ %s',
                            $records->count(),
                            number_format($records->sum('provider_remaining'), 2, ',', '.')
                        ))
                        ->form([
                            Forms\Components\DatePicker::make('payment_date')
                                ->label('Data do Pagamento')->required()->default(now()),
                            Forms\Components\Select::make('payment_method')
                                ->label('Método')->options(PaymentMethod::class)->required()->default('pix'),
                            Forms\Components\Select::make('bank_account_id')
                                ->label('Conta Bancária')
                                ->options(fn () => BankAccount::pluck('name', 'id'))
                                ->searchable()->preload()
                                ->required()
                                ->default(fn () => BankAccount::where('is_default', true)->first()?->id)
                                ->helperText('Conta de onde sai o dinheiro'),
                            Forms\Components\Textarea::make('notes')
                                ->label('Observações')->rows(2),
                        ])
                        ->action(function ($records, array $data): void {
                            DB::transaction(function () use ($records, $data) {
                                foreach ($records as $order) {
                                    if ($order->provider_remaining <= 0) {
                                        continue;
                                    }

                                    $amount = $order->provider_remaining;

                                    ServiceOrderPayment::create([
                                        'service_order_id' => $order->id,
                                        'type' => 'provider',
                                        'status' => ServiceOrderPaymentStatus::BILLED,
                                        'payment_date' => $data['payment_date'],
                                        'amount' => $amount,
                                        'payment_method' => $data['payment_method'],
                                        'bank_account_id' => $data['bank_account_id'] ?? null,
                                        'notes' => $data['notes'] ?? null,
                                        'registered_by' => auth()->id(),
                                    ]);

                                    $providerName = optional($order->serviceProvider)->name ?? 'Prestador';
                                    CashMovement::create([
                                        'type' => CashMovementType::EXPENSE,
                                        'amount' => $amount,
                                        'description' => "Pagamento OS {$order->number} - {$providerName}",
                                        'movement_date' => $data['payment_date'],
                                        'bank_account_id' => $data['bank_account_id'] ?? null,
                                        'payment_method' => $data['payment_method'],
                                        'reference_type' => ServiceOrder::class,
                                        'reference_id' => $order->id,
                                        'notes' => $data['notes'] ?? null,
                                        'created_by' => auth()->id(),
                                    ]);

                                    // Registrar no ledger do prestador
                                    if ($order->service_provider_id) {
                                        ServiceProviderLedger::create([
                                            'service_provider_id' => $order->service_provider_id,
                                            'type' => LedgerType::CREDIT,
                                            'category' => ProviderLedgerCategory::PAGAMENTO_RECEBIDO,
                                            'amount' => $amount,
                                            'description' => "Pagamento OS {$order->number}",
                                            'transaction_date' => $data['payment_date'],
                                            'reference_type' => ServiceOrder::class,
                                            'reference_id' => $order->id,
                                            'notes' => $data['notes'] ?? null,
                                            'created_by' => auth()->id(),
                                        ]);
                                    }

                                    // Marcar pedidos de saque relacionados como aprovados
                                    \App\Models\ProviderPaymentRequest::where('service_order_id', $order->id)
                                        ->where('service_provider_id', $order->service_provider_id)
                                        ->where('status', 'pending')
                                        ->update([
                                            'status' => 'approved',
                                            'approved_by' => auth()->id(),
                                            'approved_at' => now(),
                                        ]);

                                    $order->refresh();
                                    if ($order->isProviderFullyPaid()) {
                                        $order->update([
                                            'provider_payment_status' => ServiceOrderPaymentStatus::BILLED,
                                            'provider_paid_at' => now(),
                                        ]);
                                    }
                                }
                            });

                            Notification::make()->success()
                                ->title('Pagamentos registrados')
                                ->body(sprintf('%d prestadores pagos com sucesso.', $records->count()))
                                ->send();
                        }),

                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
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
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
