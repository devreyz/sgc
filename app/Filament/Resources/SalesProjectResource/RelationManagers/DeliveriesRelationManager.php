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

    public function isReadOnly(): bool
    {
        return false;
    }

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
                    ->required(fn () => !($this->ownerRecord->allow_any_product ?? false))
                    ->visible(fn () => !($this->ownerRecord->allow_any_product ?? false)),

                // Seletor de produto para projetos livres (allow_any_product)
                Forms\Components\Select::make('product_id')
                    ->label('Produto (Projeto Livre)')
                    ->options(fn () => \App\Models\Product::active()
                        ->where('tenant_id', $this->ownerRecord->tenant_id)
                        ->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if ($state) {
                            $product = \App\Models\Product::find($state);
                            $set('unit_price', $product?->cost_price ?? 0);
                        }
                    })
                    ->required(fn () => (bool) ($this->ownerRecord->allow_any_product ?? false))
                    ->visible(fn () => (bool) ($this->ownerRecord->allow_any_product ?? false))
                    ->helperText('Produto entregue (projeto aceita qualquer produto)'),

                Forms\Components\TextInput::make('unit_price')
                    ->label('Preço Unitário')
                    ->numeric()
                    ->prefix('R$')
                    ->required(fn () => (bool) ($this->ownerRecord->allow_any_product ?? false))
                    ->visible(fn () => (bool) ($this->ownerRecord->allow_any_product ?? false))
                    ->helperText('Preço por unidade do produto'),

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

                Forms\Components\Select::make('customer_id')
                    ->label('Cliente Destino (opcional)')
                    ->options(function () {
                        $project = $this->ownerRecord;
                        $customers = $project->customers()
                            ->orderBy('name')
                            ->pluck('name', 'customers.id');
                        if ($project->customer_id) {
                            $customers = $customers->prepend(
                                $project->customer->name,
                                $project->customer_id
                            );
                        }
                        return $customers->unique();
                    })
                    ->searchable()
                    ->nullable()
                    ->placeholder('Cliente padrão do projeto')
                    ->helperText('Preencha somente quando esta entrega for para um cliente específico do projeto')
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

                // ── Tipo: Recepção / Distribuição / Direto ──
                Tables\Columns\BadgeColumn::make('tipo')
                    ->label('Tipo')
                    ->getStateUsing(function ($record): string {
                        if (!is_null($record->parent_delivery_id)) return 'Distribuição';
                        if (is_null($record->customer_id))         return 'Recepção';
                        return 'Direto';
                    })
                    ->colors([
                        'warning' => 'Recepção',
                        'info'    => 'Distribuição',
                        'success' => 'Direto',
                    ]),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->placeholder('—')
                    ->limit(20)
                    ->sortable(),

                Tables\Columns\TextColumn::make('associate.user.display_name')
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

                // Coluna de progresso de distribuição (só visível em recepções)
                Tables\Columns\TextColumn::make('distribuicao')
                    ->label('Distribuído')
                    ->getStateUsing(function ($record): string {
                        if (!is_null($record->parent_delivery_id) || !is_null($record->customer_id)) {
                            return '—';
                        }
                        $distributed = $record->distributed_quantity;
                        $total       = (float) $record->quantity;
                        $remaining   = $record->remaining_quantity;
                        $pct         = $total > 0 ? round($distributed / $total * 100) : 0;
                        return number_format($distributed, 2, ',', '.') . '/' . number_format($total, 2, ',', '.') . ' (' . $pct . '%)';
                    })
                    ->color(function ($record): string {
                        if (!is_null($record->parent_delivery_id) || !is_null($record->customer_id)) return 'gray';
                        return $record->remaining_quantity <= 0.001 ? 'success' : 'warning';
                    })
                    ->toggleable(),

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
                Tables\Filters\SelectFilter::make('tipo')
                    ->label('Tipo de Entrega')
                    ->options([
                        'receptions'    => 'Recepções (entradas de campo)',
                        'distributions' => 'Distribuições (entregas a clientes)',
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data) {
                        if ($data['value'] === 'receptions') {
                            $query->whereNull('parent_delivery_id');
                        } elseif ($data['value'] === 'distributions') {
                            $query->whereNotNull('parent_delivery_id');
                        }
                    })
                    ->default('distributions'),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(DeliveryStatus::class),

                Tables\Filters\SelectFilter::make('associate_id')
                    ->label('Associado')
                    ->relationship('associate.user', 'name'),

                Tables\Filters\Filter::make('unpaid')
                    ->label('Não Pagas')
                    ->query(fn (\Illuminate\Database\Eloquent\Builder $q) => $q->where('paid', false)
                        ->whereNotNull('parent_delivery_id')),
            ])
            ->headerActions([
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
                    ->visible(fn ($record): bool => false) // DESATIVADO — centralize em "Pagamentos a Associados"
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

                // ── Ação: Distribuir entre clientes ──────────────────────────
                Tables\Actions\Action::make('distribute')
                    ->label('Distribuir')
                    ->icon('heroicon-o-arrows-pointing-out')
                    ->color('info')
                    ->modalHeading(fn ($record) => 'Distribuir entrega: ' . $record->product->name)
                    ->modalDescription(fn ($record) => new \Illuminate\Support\HtmlString(
                        '<div class="space-y-1 text-sm">'
                        . '<p><strong>Associado:</strong> ' . ($record->associate?->user?->name ?? '—') . '</p>'
                        . '<p><strong>Total recebido:</strong> ' . number_format($record->quantity, 3, ',', '.') . ' ' . $record->product->unit . '</p>'
                        . '<p><strong>Já distribuído:</strong> ' . number_format($record->distributed_quantity, 3, ',', '.') . ' ' . $record->product->unit . '</p>'
                        . '<p class="font-semibold text-warning-600"><strong>Disponível:</strong> ' . number_format($record->remaining_quantity, 3, ',', '.') . ' ' . $record->product->unit . '</p>'
                        . '</div>'
                    ))
                    ->form(function ($record) {
                        $project = $this->ownerRecord;
                        $customerOptions = collect();

                        // Clientes disponíveis: primário + pivot
                        if ($project->customer_id) {
                            $customerOptions->put($project->customer_id, $project->customer->name ?? '—');
                        }
                        foreach ($project->customers as $c) {
                            $customerOptions->put($c->id, $c->name);
                        }

                        return [
                            Forms\Components\Repeater::make('distributions')
                                ->label('Distribuição por cliente')
                                ->schema([
                                    Forms\Components\Select::make('customer_id')
                                        ->label('Cliente')
                                        ->options($customerOptions->toArray())
                                        ->required()
                                        ->searchable(),

                                    Forms\Components\TextInput::make('quantity')
                                        ->label('Quantidade')
                                        ->numeric()
                                        ->required()
                                        ->minValue(0.001)
                                        ->suffix($record->product->unit)
                                        ->helperText('Quantidade destinada a este cliente'),

                                    Forms\Components\TextInput::make('unit_price')
                                        ->label('Preço unitário (R$)')
                                        ->numeric()
                                        ->prefix('R$')
                                        ->required()
                                        ->minValue(0.01)
                                        ->default(fn ($get) => $record->unit_price)
                                        ->helperText('Preço específico para este cliente'),
                                ])
                                ->minItems(1)
                                ->addActionLabel('+ Adicionar cliente')
                                ->columns(3)
                                ->columnSpanFull()
                                ->helperText('A soma das quantidades não pode ultrapassar ' . number_format($record->remaining_quantity, 3, ',', '.') . ' ' . $record->product->unit),
                        ];
                    })
                    ->action(function ($record, array $data) {
                        $distributions = $data['distributions'] ?? [];

                        // Validar soma das quantidades
                        $totalDistributing = array_sum(array_column($distributions, 'quantity'));
                        if ($totalDistributing > $record->remaining_quantity + 0.0001) {
                            Notification::make()
                                ->danger()
                                ->title('Quantidade inválida')
                                ->body('A soma (' . number_format($totalDistributing, 3, ',', '.') . ') ultrapassa o disponível (' . number_format($record->remaining_quantity, 3, ',', '.') . ').')
                                ->send();
                            return;
                        }

                        $project = $this->ownerRecord;

                        DB::transaction(function () use ($record, $distributions, $project) {
                            foreach ($distributions as $dist) {
                                \App\Models\ProductionDelivery::create([
                                    'parent_delivery_id'  => $record->id,
                                    'tenant_id'           => $record->tenant_id,
                                    'sales_project_id'    => $record->sales_project_id,
                                    'project_demand_id'   => $record->project_demand_id,
                                    'associate_id'        => $record->associate_id,
                                    'customer_id'         => $dist['customer_id'],
                                    'product_id'          => $record->product_id,
                                    'delivery_date'       => $record->delivery_date,
                                    'quantity'            => $dist['quantity'],
                                    'unit_price'          => $dist['unit_price'],
                                    'admin_fee_percentage'=> $project->admin_fee_percentage,
                                    'status'              => DeliveryStatus::PENDING,
                                    'quality_grade'       => $record->quality_grade,
                                    'notes'               => 'Distribuição da recepção #' . $record->id,
                                    'received_by'         => auth()->id(),
                                ]);
                            }
                        });

                        Notification::make()
                            ->success()
                            ->title('Distribuição criada')
                            ->body(count($distributions) . ' registro(s) de distribuição criados.')
                            ->send();

                        $this->ownerRecord->refresh();
                    })
                    ->visible(fn ($record): bool =>
                        is_null($record->parent_delivery_id)
                        && is_null($record->customer_id)
                        && $record->remaining_quantity > 0.001
                    ),

                Tables\Actions\EditAction::make()
                    ->visible(fn ($record): bool =>
                        $record->status === DeliveryStatus::PENDING
                        && !$record->paid
                        && is_null($record->project_payment_id)
                    ),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn ($record): bool =>
                        $record->status === DeliveryStatus::PENDING
                        && !$record->paid
                        && is_null($record->project_payment_id)
                    ),
                Tables\Actions\Action::make('locked')
                    ->label('Faturado')
                    ->icon('heroicon-o-lock-closed')
                    ->color('warning')
                    ->disabled()
                    ->tooltip('Este registro já foi faturado e não pode ser alterado.')
                    ->visible(fn ($record): bool => !is_null($record->project_payment_id) || $record->paid),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()->isSuperAdmin()),
                ]),
            ])
            ->emptyStateHeading('Nenhuma entrega registrada')
            ->emptyStateDescription('Clique em "Nova Entrega" para registrar a primeira entrega de produção.')
            ->emptyStateIcon('heroicon-o-truck');
    }
}

