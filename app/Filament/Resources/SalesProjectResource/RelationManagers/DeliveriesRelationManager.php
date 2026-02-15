<?php

namespace App\Filament\Resources\SalesProjectResource\RelationManagers;

use App\Enums\DeliveryStatus;
use App\Enums\PaymentMethod;
use App\Enums\ProjectPaymentStatus;
use App\Models\BankAccount;
use App\Models\ProjectPayment;
use App\Models\AssociateLedger;
use App\Models\CashMovement;
use App\Enums\CashMovementType;
use App\Enums\LedgerType;
use App\Enums\LedgerCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class DeliveriesRelationManager extends RelationManager
{
    protected static string $relationship = 'deliveries';

    protected static ?string $title = 'Entregas';

    protected static ?string $modelLabel = 'Entrega';

    protected static ?string $pluralModelLabel = 'Entregas';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('project_demand_id')
                    ->label('Demanda / Produto')
                    ->options(fn () => $this->ownerRecord->demands->mapWithKeys(fn ($d) => [
                        $d->id => $d->product->name . ' (R$ ' . number_format($d->unit_price, 2, ',', '.') . 
                                 '/' . $d->product->unit . ') — Resta: ' . 
                                 number_format($d->remaining_quantity, 2, ',', '.') . ' ' . $d->product->unit
                    ])->toArray())
                    ->searchable()
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if (! $state) return;
                        $d = $this->ownerRecord->demands->firstWhere('id', $state);
                        if (! $d) return;
                        // Auto-preencher tudo da demanda
                        $set('product_id', $d->product_id);
                        $set('unit_price', $d->unit_price);
                        $set('quantity', min(10, (float) $d->remaining_quantity)); // Sugere 10 ou o restante
                    })
                    ->helperText('Selecione o produto/demanda deste projeto. Preço e produto serão preenchidos automaticamente.')
                    ->required(),

                Forms\Components\Hidden::make('product_id'),

                Forms\Components\Select::make('associate_id')
                    ->label('Associado Produtor')
                    ->relationship('associate', 'id')
                    ->getOptionLabelFromRecordUsing(fn ($record) => optional($record->user)->name ?? $record->property_name ?? "#{$record->id}")
                    ->searchable()
                    ->preload()
                    ->helperText('Quem entregou esta produção?')
                    ->required(),

                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('quantity')
                            ->label('Quantidade Entregue')
                            ->numeric()
                            ->required()
                            ->minValue(0.01)
                            ->suffix(fn ($get) => $get('product_id') ? 
                                \App\Models\Product::find($get('product_id'))?->unit ?? '' : '')
                            ->helperText('Digite apenas a quantidade'),

                        Forms\Components\DatePicker::make('delivery_date')
                            ->label('Data da Entrega')
                            ->required()
                            ->default(now())
                            ->maxDate(now()),
                    ]),

                Forms\Components\Placeholder::make('calculated_values')
                    ->label('Valores Calculados')
                    ->content(function ($get) {
                        $qty = $get('quantity') ?? 0;
                        $price = $get('unit_price') ?? 0;
                        $gross = $qty * $price;
                        $adminFee = $gross * ($this->ownerRecord->admin_fee_percentage / 100);
                        $net = $gross - $adminFee;
                        
                        return new \Illuminate\Support\HtmlString(
                            '<div class="text-sm space-y-1">' .
                            '<div><strong>Valor Bruto:</strong> R$ ' . number_format($gross, 2, ',', '.') . '</div>' .
                            '<div><strong>Taxa Admin (' . $this->ownerRecord->admin_fee_percentage . '%):</strong> R$ ' . 
                            number_format($adminFee, 2, ',', '.') . '</div>' .
                            '<div class="text-success-600 font-semibold"><strong>Valor Líquido (Produtor):</strong> R$ ' . 
                            number_format($net, 2, ',', '.') . '</div>' .
                            '</div>'
                        );
                    })
                    ->reactive(),

                Forms\Components\Hidden::make('unit_price'),

                Forms\Components\Select::make('quality_grade')
                    ->label('Classificação de Qualidade')
                    ->options([
                        'A' => 'A - Excelente',
                        'B' => 'B - Boa',
                        'C' => 'C - Aceitável',
                    ])
                    ->default('A'),

                Forms\Components\Textarea::make('notes')
                    ->label('Observações')
                    ->rows(2)
                    ->placeholder('Observações sobre a entrega (opcional)')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->defaultSort('delivery_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('delivery_date')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('associate.user.name')
                    ->label('Associado')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('product.name')
                    ->label('Produto')
                    ->searchable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Quantidade')
                    ->formatStateUsing(fn ($state, $record): string => 
                        number_format($state, 2, ',', '.') . ' ' . $record->product->unit
                    ),

                Tables\Columns\TextColumn::make('unit_price')
                    ->label('Preço/Un')
                    ->money('BRL'),

                Tables\Columns\TextColumn::make('gross_value')
                    ->label('Bruto')
                    ->money('BRL')
                    ->tooltip('Valor bruto (quantidade × preço)'),

                Tables\Columns\TextColumn::make('admin_fee_amount')
                    ->label('Taxa Admin')
                    ->money('BRL')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('net_value')
                    ->label('Líquido')
                    ->money('BRL')
                    ->tooltip('Valor líquido para o produtor')
                    ->weight('bold')
                    ->color('success'),

                Tables\Columns\TextColumn::make('quality_grade')
                    ->label('Qualidade')
                    ->badge()
                    ->colors([
                        'success' => 'A',
                        'warning' => 'B',
                        'danger' => 'C',
                    ])
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (DeliveryStatus $state): string => $state->getLabel())
                    ->color(fn (DeliveryStatus $state): string => $state->getColor()),

                Tables\Columns\IconColumn::make('paid')
                    ->label('Pago')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('paid_date')
                    ->label('Data Pag.')
                    ->date('d/m/Y')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(DeliveryStatus::class),
                Tables\Filters\SelectFilter::make('associate_id')
                    ->label('Associado')
                    ->relationship('associate.user', 'name'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('pay_all_for_associate')
                    ->label('Pagar Tudo do Associado')
                    ->icon('heroicon-o-banknotes')
                    ->color('secondary')
                    ->modalHeading('Pagar todas entregas de um associado')
                    ->form([
                        Forms\Components\Select::make('associate_id')
                            ->label('Associado')
                            ->options(fn () => $this->ownerRecord->deliveries
                                ->where('paid', false)
                                ->mapWithKeys(fn ($d) => [$d->associate_id => $d->associate->user->name])
                                ->unique()
                                ->toArray())
                            ->required(),

                        Forms\Components\DatePicker::make('payment_date')
                            ->label('Data do Pagamento')
                            ->required()
                            ->default(now()),

                        Forms\Components\Select::make('bank_account_id')
                            ->label('Conta para Pagamento')
                            ->options(BankAccount::where('status', true)->pluck('name', 'id'))
                            ->required()
                            ->default(fn () => $this->ownerRecord->payment_bank_account_id)
                            ->helperText('Conta de onde sairá o pagamento'),

                        Forms\Components\Select::make('payment_method')
                            ->label('Forma de Pagamento')
                            ->options(PaymentMethod::class)
                            ->required()
                            ->default(PaymentMethod::TRANSFERENCIA),

                        Forms\Components\Textarea::make('notes')
                            ->label('Observações')
                            ->rows(3),
                    ])
                    ->action(function (array $data) {
                        DB::transaction(function () use ($data) {
                            $associateId = $data['associate_id'];
                            $deliveries = $this->ownerRecord->deliveries()->where('associate_id', $associateId)->where('paid', false)->get();

                            if ($deliveries->isEmpty()) {
                                Notification::make()->warning()->title('Nenhuma entrega pendente para este associado')->send();
                                return;
                            }

                            $totalForAssociate = $deliveries->sum('net_value');

                            $payment = ProjectPayment::create([
                                'sales_project_id' => $this->ownerRecord->id,
                                'type' => 'associate_payment',
                                'status' => ProjectPaymentStatus::PAID,
                                'amount' => $totalForAssociate,
                                'description' => "Pagamento consolidado de " . $deliveries->count() . " entrega(s) do projeto {$this->ownerRecord->title}",
                                'payment_date' => $data['payment_date'],
                                'bank_account_id' => $data['bank_account_id'],
                                'payment_method' => $data['payment_method'],
                                'associate_id' => $associateId,
                                'notes' => $data['notes'] ?? null,
                                'created_by' => auth()->id(),
                                'approved_by' => auth()->id(),
                                'approved_at' => now(),
                            ]);

                            foreach ($deliveries as $delivery) {
                                $delivery->update([
                                    'paid' => true,
                                    'paid_date' => $data['payment_date'],
                                    'project_payment_id' => $payment->id,
                                ]);
                            }

                            $associate = $deliveries->first()->associate;
                            $currentBalance = $associate->current_balance ?? 0;

                            AssociateLedger::create([
                                'associate_id' => $associate->id,
                                'type' => LedgerType::DEBIT,
                                'category' => LedgerCategory::PRODUCAO,
                                'amount' => $totalForAssociate,
                                'balance_after' => $currentBalance - $totalForAssociate,
                                'description' => "Pagamento consolidado recebido ({$deliveries->count()} entrega(s)) - Projeto: {$this->ownerRecord->title}",
                                'reference_type' => ProjectPayment::class,
                                'reference_id' => $payment->id,
                                'transaction_date' => $data['payment_date'],
                                'created_by' => auth()->id(),
                            ]);

                            $bankAccount = BankAccount::find($data['bank_account_id']);
                            $newBalance = $bankAccount->current_balance - $totalForAssociate;

                            CashMovement::create([
                                'type' => CashMovementType::EXPENSE,
                                'amount' => $totalForAssociate,
                                'balance_after' => $newBalance,
                                'description' => "Pagamento ao associado {$associate->user->name} - Consolidado - Projeto: {$this->ownerRecord->title}",
                                'movement_date' => $data['payment_date'],
                                'bank_account_id' => $data['bank_account_id'],
                                'reference_type' => ProjectPayment::class,
                                'reference_id' => $payment->id,
                                'payment_method' => $data['payment_method'],
                                'notes' => $data['notes'] ?? null,
                                'created_by' => auth()->id(),
                            ]);

                            $bankAccount->update(['current_balance' => $newBalance]);

                            $this->ownerRecord->increment('associates_paid_amount', $totalForAssociate);
                        });

                        Notification::make()->success()->title('Pagamento consolidado realizado')->send();
                    })
                    ->visible(fn () => $this->ownerRecord->deliveries->where('paid', false)->count() > 0),
                Tables\Actions\CreateAction::make()
                    ->label('Nova Entrega')
                    ->icon('heroicon-o-plus')
                    ->mutateFormDataUsing(function (array $data): array {
                        // Status inicial sempre pending
                        $data['status'] = DeliveryStatus::PENDING;
                        $data['received_by'] = auth()->id();
                        $data['sales_project_id'] = $this->ownerRecord->id;
                        
                        // Os valores serão calculados automaticamente pelo model boot
                        return $data;
                    })
                    ->successNotificationTitle('Entrega registrada com sucesso!')
                    ->after(function () {
                        // Recarregar para ver quantidade atualizada nas demands
                        $this->ownerRecord->refresh();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Aprovar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Aprovar Entrega')
                    ->modalDescription('Ao aprovar, o valor líquido será creditado ao produtor e a quantidade será contabilizada.')
                    ->action(function ($record) {
                        DB::transaction(function () use ($record) {
                            $record->update([
                                'status' => DeliveryStatus::APPROVED,
                                'approved_by' => auth()->id(),
                                'approved_at' => now(),
                            ]);
                            
                            // Registrar crédito no ledger (associado tem a receber)
                            $currentBalance = $record->associate->current_balance ?? 0;
                            AssociateLedger::create([
                                'associate_id' => $record->associate_id,
                                'type' => LedgerType::CREDIT,
                                'category' => LedgerCategory::PRODUCAO,
                                'amount' => $record->net_value,
                                'balance_after' => $currentBalance + $record->net_value,
                                'description' => "Entrega aprovada - Projeto: {$record->salesProject->title} - {$record->quantity} {$record->product->unit} de {$record->product->name}",
                                'reference_type' => get_class($record),
                                'reference_id' => $record->id,
                                'transaction_date' => now(),
                                'created_by' => auth()->id(),
                            ]);
                            
                            // Forçar atualização da demanda
                            if ($record->projectDemand) {
                                $record->projectDemand->updateDeliveredQuantity();
                                $record->projectDemand->refresh();
                            }
                            
                            // Forçar atualização do projeto
                            $record->salesProject->refresh();
                        });
                    })
                    ->successNotificationTitle('Entrega aprovada!')
                    ->after(fn () => $this->ownerRecord->refresh())
                    ->visible(fn ($record): bool => $record->status === DeliveryStatus::PENDING),

                Tables\Actions\Action::make('reject')
                    ->label('Rejeitar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Motivo da Rejeição')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(fn ($record, array $data) => $record->update([
                        'status' => DeliveryStatus::REJECTED,
                        'notes' => ($record->notes ? $record->notes . "\n\n" : '') . 
                                  'REJEITADO: ' . $data['rejection_reason'],
                    ]))
                    ->successNotificationTitle('Entrega rejeitada')
                    ->visible(fn ($record): bool => $record->status === DeliveryStatus::PENDING),

                Tables\Actions\Action::make('pay')
                    ->label('Pagar')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn ($record): bool => $record->canBePaid())
                    ->form([
                        Forms\Components\Placeholder::make('info')
                            ->content(fn ($record) => new \Illuminate\Support\HtmlString(
                                '<div class="space-y-2">'.
                                '<p><strong>Associado:</strong> ' . $record->associate->user->name . '</p>' .
                                '<p><strong>Produto:</strong> ' . $record->product->name . '</p>' .
                                '<p><strong>Quantidade:</strong> ' . number_format($record->quantity, 2, ',', '.') . ' ' . $record->product->unit . '</p>' .
                                '<p class="text-lg font-bold text-success-600"><strong>Valor a Pagar:</strong> R$ ' . number_format($record->net_value, 2, ',', '.') . '</p>' .
                                '</div>'
                            )),

                        Forms\Components\DatePicker::make('payment_date')
                            ->label('Data do Pagamento')
                            ->required()
                            ->default(now()),

                        Forms\Components\Select::make('bank_account_id')
                            ->label('Conta para Pagamento')
                            ->options(BankAccount::where('status', true)->pluck('name', 'id'))
                            ->required()
                            ->default(fn () => $this->ownerRecord->payment_bank_account_id)
                            ->helperText('Conta de onde sairá o pagamento'),

                        Forms\Components\Select::make('payment_method')
                            ->label('Forma de Pagamento')
                            ->options(PaymentMethod::class)
                            ->required()
                            ->default(PaymentMethod::TRANSFERENCIA),

                        Forms\Components\Textarea::make('notes')
                            ->label('Observações')
                            ->rows(3),
                    ])
                    ->action(function ($record, array $data) {
                        DB::transaction(function () use ($record, $data) {
                            // Registrar pagamento ao associado
                            $payment = ProjectPayment::create([
                                'sales_project_id' => $record->sales_project_id,
                                'type' => 'associate_payment',
                                'status' => ProjectPaymentStatus::PAID,
                                'amount' => $record->net_value,
                                'description' => "Pagamento pela entrega de {$record->quantity} {$record->product->unit} de {$record->product->name}",
                                'payment_date' => $data['payment_date'],
                                'bank_account_id' => $data['bank_account_id'],
                                'payment_method' => $data['payment_method'],
                                'associate_id' => $record->associate_id,
                                'production_delivery_id' => $record->id,
                                'notes' => $data['notes'] ?? null,
                                'created_by' => auth()->id(),
                                'approved_by' => auth()->id(),
                                'approved_at' => now(),
                            ]);

                            // Atualizar entrega
                            $record->update([
                                'paid' => true,
                                'paid_date' => $data['payment_date'],
                                'project_payment_id' => $payment->id,
                            ]);

                            // Registrar no ledger do associado (débito - associado recebeu o pagamento)
                            $currentBalance = $record->associate->current_balance ?? 0;
                            AssociateLedger::create([
                                'associate_id' => $record->associate_id,
                                'type' => LedgerType::DEBIT,
                                'category' => LedgerCategory::PRODUCAO,
                                'amount' => $record->net_value,
                                'balance_after' => $currentBalance - $record->net_value,
                                'description' => "Pagamento recebido - Projeto: {$record->salesProject->title}",
                                'reference_type' => get_class($payment),
                                'reference_id' => $payment->id,
                                'transaction_date' => $data['payment_date'],
                                'created_by' => auth()->id(),
                            ]);

                            // Registrar movimento de caixa (saída)
                            $bankAccount = BankAccount::find($data['bank_account_id']);
                            $newBalance = $bankAccount->current_balance - $record->net_value;

                            CashMovement::create([
                                'type' => CashMovementType::EXPENSE,
                                'amount' => $record->net_value,
                                'balance_after' => $newBalance,
                                'description' => "Pagamento ao associado {$record->associate->user->name} - Projeto: {$record->salesProject->title}",
                                'movement_date' => $data['payment_date'],
                                'bank_account_id' => $data['bank_account_id'],
                                'reference_type' => get_class($record),
                                'reference_id' => $record->id,
                                'payment_method' => $data['payment_method'],
                                'notes' => $data['notes'] ?? null,
                                'created_by' => auth()->id(),
                            ]);

                            // Atualizar saldo da conta
                            $bankAccount->update(['current_balance' => $newBalance]);

                            // Atualizar valor total pago no projeto
                            $this->ownerRecord->increment('associates_paid_amount', $record->net_value);
                        });

                        Notification::make()
                            ->success()
                            ->title('Pagamento realizado com sucesso')
                            ->send();
                    }),

                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn ($record): bool => $record->status === DeliveryStatus::PENDING),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn ($record): bool => $record->status === DeliveryStatus::PENDING),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('pay_all')
                        ->label('Pagar Selecionadas')
                        ->icon('heroicon-o-banknotes')
                        ->color('success')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->form([
                            Forms\Components\Placeholder::make('summary')
                                ->label('Resumo do Pagamento')
                                ->content(function ($records) {
                                    $total = $records->sum('net_value');
                                    $count = $records->count();
                                    $associates = $records->pluck('associate.user.name')->unique()->implode(', ');
                                    
                                    return new \Illuminate\Support\HtmlString(
                                        '<div class="space-y-2 text-sm">'.
                                        '<p><strong>Total de Entregas:</strong> ' . $count . '</p>' .
                                        '<p><strong>Associado(s):</strong> ' . $associates . '</p>' .
                                        '<p class="text-lg font-bold text-success-600 mt-3"><strong>Valor Total:</strong> R$ ' . number_format($total, 2, ',', '.') . '</p>' .
                                        '</div>'
                                    );
                                }),

                            Forms\Components\DatePicker::make('payment_date')
                                ->label('Data do Pagamento')
                                ->required()
                                ->default(now()),

                            Forms\Components\Select::make('bank_account_id')
                                ->label('Conta para Pagamento')
                                ->options(BankAccount::where('status', true)->pluck('name', 'id'))
                                ->required()
                                ->default(fn () => $this->ownerRecord->payment_bank_account_id)
                                ->helperText('Conta de onde sairá o pagamento'),

                            Forms\Components\Select::make('payment_method')
                                ->label('Forma de Pagamento')
                                ->options(PaymentMethod::class)
                                ->required()
                                ->default(PaymentMethod::TRANSFERENCIA),

                            Forms\Components\Textarea::make('notes')
                                ->label('Observações')
                                ->rows(3),
                        ])
                        ->action(function ($records, array $data) {
                            $paidCount = 0;
                            $totalPaid = 0;

                            DB::transaction(function () use ($records, $data, &$paidCount, &$totalPaid) {
                                $bankAccount = BankAccount::find($data['bank_account_id']);
                                
                                // Agrupar entregas por associado
                                $deliveriesByAssociate = $records->groupBy('associate_id');

                                foreach ($deliveriesByAssociate as $associateId => $deliveries) {
                                    $associate = $deliveries->first()->associate;
                                    $totalForAssociate = $deliveries->sum('net_value');
                                    
                                    // Criar um único pagamento por associado
                                    $payment = ProjectPayment::create([
                                        'sales_project_id' => $this->ownerRecord->id,
                                        'type' => 'associate_payment',
                                        'status' => ProjectPaymentStatus::PAID,
                                        'amount' => $totalForAssociate,
                                        'description' => "Pagamento de {$deliveries->count()} entrega(s) do projeto {$this->ownerRecord->title}",
                                        'payment_date' => $data['payment_date'],
                                        'bank_account_id' => $data['bank_account_id'],
                                        'payment_method' => $data['payment_method'],
                                        'associate_id' => $associateId,
                                        'notes' => $data['notes'] ?? null,
                                        'created_by' => auth()->id(),
                                        'approved_by' => auth()->id(),
                                        'approved_at' => now(),
                                    ]);

                                    // Atualizar cada entrega deste associado
                                    foreach ($deliveries as $delivery) {
                                        $delivery->update([
                                            'paid' => true,
                                            'paid_date' => $data['payment_date'],
                                            'project_payment_id' => $payment->id,
                                        ]);
                                        $paidCount++;
                                    }

                                    // Registrar no ledger do associado (débito - associado recebeu o pagamento)
                                    $currentBalance = $associate->current_balance ?? 0;
                                    AssociateLedger::create([
                                        'associate_id' => $associateId,
                                        'type' => LedgerType::DEBIT,
                                        'category' => LedgerCategory::PRODUCAO,
                                        'amount' => $totalForAssociate,
                                        'balance_after' => $currentBalance - $totalForAssociate,
                                        'description' => "Pagamento recebido - {$deliveries->count()} entrega(s) - Projeto: {$this->ownerRecord->title}",
                                        'reference_type' => ProjectPayment::class,
                                        'reference_id' => $payment->id,
                                        'transaction_date' => $data['payment_date'],
                                        'created_by' => auth()->id(),
                                    ]);

                                    // Registrar movimento de caixa (saída)
                                    $newBalance = $bankAccount->current_balance - $totalForAssociate;

                                    CashMovement::create([
                                        'type' => CashMovementType::EXPENSE,
                                        'amount' => $totalForAssociate,
                                        'balance_after' => $newBalance,
                                        'description' => "Pagamento ao associado {$associate->user->name} - {$deliveries->count()} entrega(s) - Projeto: {$this->ownerRecord->title}",
                                        'movement_date' => $data['payment_date'],
                                        'bank_account_id' => $data['bank_account_id'],
                                        'reference_type' => ProjectPayment::class,
                                        'reference_id' => $payment->id,
                                        'payment_method' => $data['payment_method'],
                                        'notes' => $data['notes'] ?? null,
                                        'created_by' => auth()->id(),
                                    ]);

                                    // Atualizar saldo da conta
                                    $bankAccount->update(['current_balance' => $newBalance]);
                                    $bankAccount->refresh();

                                    // Atualizar valor total pago no projeto
                                    $this->ownerRecord->increment('associates_paid_amount', $totalForAssociate);
                                    $totalPaid += $totalForAssociate;
                                }
                            });

                            Notification::make()
                                ->success()
                                ->title('Pagamentos realizados com sucesso')
                                ->body("{$paidCount} entrega(s) paga(s). Total: R$ " . number_format($totalPaid, 2, ',', '.'))
                                ->send();
                        })
                        ->visible(fn ($records) => $records && $records->every(fn ($r) => $r->canBePaid())),

                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()->isSuperAdmin()),
                ]),
            ])
            ->emptyStateHeading('Nenhuma entrega registrada')
            ->emptyStateDescription('Clique em "Nova Entrega" para registrar a primeira entrega de produção.')
            ->emptyStateIcon('heroicon-o-truck');
    }
}
