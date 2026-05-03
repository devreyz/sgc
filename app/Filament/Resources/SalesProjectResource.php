<?php

namespace App\Filament\Resources;

use App\Enums\CashMovementType;
use App\Enums\PaymentMethod;
use App\Enums\ProjectPaymentStatus;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Filament\Resources\SalesProjectResource\Pages;
use App\Filament\Resources\SalesProjectResource\RelationManagers;
use App\Filament\Traits\HasExportActions;
use App\Filament\Traits\TenantScoped;
use App\Models\BankAccount;
use App\Models\CashMovement;
use App\Models\ProjectPayment;
use App\Models\SalesProject;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;

class SalesProjectResource extends Resource
{
    use HasExportActions;
    use TenantScoped;

    protected static ?string $model = SalesProject::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Projetos de Venda';

    protected static ?string $modelLabel = 'Projeto de Venda';

    protected static ?string $pluralModelLabel = 'Projetos de Venda';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dados do Projeto')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Título')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\Select::make('type')
                            ->label('Tipo')
                            ->options(ProjectType::class)
                            ->required(),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(ProjectStatus::class)
                            ->required()
                            ->default(ProjectStatus::DRAFT)
                            ->disabled()
                            ->dehydrated(fn (string $context): bool => $context === 'create')
                            ->helperText('Status só pode ser alterado por ações específicas'),

                        Forms\Components\Select::make('customer_id')
                            ->label('Cliente (opcional)')
                            ->relationship('customer', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->helperText('Referência opcional. Clientes reais são definidos nas distribuições das entregas.'),

                        Forms\Components\TextInput::make('contract_number')
                            ->label('Nº Contrato')
                            ->maxLength(50),

                        Forms\Components\DatePicker::make('start_date')
                            ->label('Data Início')
                            ->required(),

                        Forms\Components\DatePicker::make('end_date')
                            ->label('Data Fim')
                            ->required()
                            ->afterOrEqual('start_date'),

                        Forms\Components\TextInput::make('reference_year')
                            ->label('Ano de Referência')
                            ->numeric()
                            ->default(date('Y'))
                            ->required(),

                        Forms\Components\TextInput::make('total_value')
                            ->label('Valor do Contrato')
                            ->numeric()
                            ->prefix('R$')
                            ->helperText('Valor total estimado ou contratado'),

                        Forms\Components\TextInput::make('admin_fee_percentage')
                            ->label('Taxa de Administração')
                            ->numeric()
                            ->suffix('%')
                            ->default(10)
                            ->minValue(0)
                            ->maxValue(100)
                            ->helperText('Percentual retido pela cooperativa (padrão 10%)'),

                        Forms\Components\Toggle::make('allow_any_product')
                            ->label('Aceitar qualquer produto')
                            ->helperText('Quando ativo, o projeto pode receber qualquer produto cadastrado sem demandas pré-definidas')
                            ->default(false)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Clientes (Referência)')
                    ->description('Associação de referência — clientes reais são definidos via distribuição de entregas.')
                    ->schema([
                        Forms\Components\Select::make('customers')
                            ->label('Clientes Adicionais')
                            ->multiple()
                            ->relationship('customers', 'name')
                            ->searchable()
                            ->preload()
                            ->getSearchResultsUsing(fn (string $search) =>
                                \App\Models\Customer::where('tenant_id', session('tenant_id'))
                                    ->where('name', 'like', "%{$search}%")
                                    ->orderBy('name')
                                    ->limit(50)
                                    ->pluck('name', 'id')
                            )
                            ->getOptionLabelUsing(fn ($value) =>
                                \App\Models\Customer::find($value)?->name ?? $value
                            )
                            ->helperText('Opcional. O vínculo financeiro real acontece nas distribuições de cada entrega.')
                            ->columnSpanFull(),
                    ])
                    ->collapsed()
                    ->collapsible(),

                Forms\Components\Section::make('Descrição')
                    ->schema([
                        Forms\Components\Textarea::make('description')
                            ->label('Descrição')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Título')
                    ->searchable()
                    ->sortable()
                    ->limit(40),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (ProjectType $state): string => $state->getLabel())
                    ->color(fn (ProjectType $state): string => $state->getColor()),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->searchable()
                    ->limit(25),

                Tables\Columns\TextColumn::make('contract_number')
                    ->label('Contrato')
                    ->searchable(),

                Tables\Columns\TextColumn::make('start_date')
                    ->label('Início')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('end_date')
                    ->label('Fim')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_value')
                    ->label('Valor')
                    ->money('BRL')
                    ->sortable(),

                Tables\Columns\TextColumn::make('progress_percentage')
                    ->label('Progresso')
                    ->formatStateUsing(fn (SalesProject $record): string => number_format($record->progress_percentage, 1, ',', '.').'%'
                    )
                    ->badge()
                    ->color(fn (SalesProject $record): string => $record->progress_percentage >= 100 ? 'success' :
                        ($record->progress_percentage >= 50 ? 'warning' : 'danger')
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (ProjectStatus $state): string => $state->getLabel())
                    ->color(fn (ProjectStatus $state): string => $state->getColor()),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(ProjectType::class),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(ProjectStatus::class),
                Tables\Filters\SelectFilter::make('customer_id')
                    ->label('Cliente')
                    ->relationship('customer', 'name'),
            ])
            ->headerActions([
                self::getExportAction(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                // Ativar/Iniciar projeto
                Tables\Actions\Action::make('activate')
                    ->label('Iniciar Projeto')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->visible(fn (SalesProject $record) => $record->status === ProjectStatus::DRAFT)
                    ->requiresConfirmation()
                    ->modalHeading('Iniciar Projeto')
                    ->modalDescription('Ao iniciar o projeto, ele será marcado como "Em Execução" e passará a aceitar registros de entrega. Deseja continuar?')
                    ->action(function (SalesProject $record) {
                        $record->update(['status' => ProjectStatus::ACTIVE]);

                        Notification::make()
                            ->success()
                            ->title('Projeto iniciado com sucesso!')
                            ->body('O projeto agora está em execução e aceita registros de entrega.')
                            ->send();
                    }),

                // Marcar como entregue
                Tables\Actions\Action::make('mark_delivered')
                    ->label('Marcar Entregue')
                    ->icon('heroicon-o-truck')
                    ->color('info')
                    ->visible(fn (SalesProject $record) => $record->canMarkAsDelivered())
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\DatePicker::make('delivered_date')
                            ->label('Data de Entrega')
                            ->required()
                            ->default(now()),
                    ])
                    ->action(function (SalesProject $record, array $data) {
                        $record->update([
                            'status' => ProjectStatus::DELIVERED,
                            'delivered_date' => $data['delivered_date'],
                        ]);

                        Notification::make()
                            ->success()
                            ->title('Projeto marcado como entregue')
                            ->send();
                    }),

                // Confirmar recebimento do cliente
                Tables\Actions\Action::make('receive_payment')
                    ->label('Receber Pagamento')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->modalWidth('3xl')
                    ->visible(fn (SalesProject $record) => $record->canReceivePayment())
                    ->form(function (SalesProject $record): array {
                        // Montar lista de todos os clientes do projeto
                        $customers = collect();
                        if ($record->customer_id && $record->customer) {
                            $customers->put($record->customer_id, $record->customer->name);
                        }
                        foreach ($record->customers as $c) {
                            $customers->put($c->id, $c->name);
                        }

                        // Total esperado por distribuições aprovadas
                        $totalByClient = \App\Models\ProductionDelivery::where('sales_project_id', $record->id)
                            ->where('status', 'approved')
                            ->whereNotNull('parent_delivery_id')
                            ->whereNotNull('customer_id')
                            ->selectRaw('customer_id, SUM(gross_value) as total_gross')
                            ->groupBy('customer_id')
                            ->pluck('total_gross', 'customer_id');

                        return [
                            Forms\Components\Placeholder::make('_info')
                                ->label('Pagamentos a Receber')
                                ->content(new \Illuminate\Support\HtmlString(
                                    '<p class="text-sm text-gray-500">Registre os pagamentos recebidos de cada cliente. Você pode receber de múltiplos clientes em uma única operação.</p>'
                                )),

                            Forms\Components\Repeater::make('client_payments')
                                ->label('Pagamentos por Cliente')
                                ->schema([
                                    Forms\Components\Select::make('customer_id')
                                        ->label('Cliente')
                                        ->options($customers->toArray())
                                        ->required()
                                        ->searchable(),

                                    Forms\Components\TextInput::make('amount')
                                        ->label('Valor Recebido (R$)')
                                        ->numeric()
                                        ->required()
                                        ->prefix('R$')
                                        ->minValue(0.01),

                                    Forms\Components\TextInput::make('document_number')
                                        ->label('Nº Documento / NF')
                                        ->maxLength(100),
                                ])
                                ->defaultItems(1)
                                ->addActionLabel('+ Adicionar cliente')
                                ->columns(3)
                                ->columnSpanFull()
                                ->minItems(1),

                            Forms\Components\DatePicker::make('payment_date')
                                ->label('Data do Recebimento')
                                ->required()
                                ->default(now()),

                            Forms\Components\Select::make('bank_account_id')
                                ->label('Conta Bancária de Destino')
                                ->options(BankAccount::where('status', true)->pluck('name', 'id'))
                                ->required()
                                ->searchable()
                                ->helperText('Conta onde o valor foi depositado'),

                            Forms\Components\Select::make('payment_method')
                                ->label('Forma de Pagamento')
                                ->options(PaymentMethod::class)
                                ->required(),

                            Forms\Components\Textarea::make('notes')
                                ->label('Observações')
                                ->rows(2),
                        ];
                    })
                    ->action(function (SalesProject $record, array $data) {
                        DB::transaction(function () use ($record, $data) {
                            $clientPayments = $data['client_payments'] ?? [];
                            $totalReceived  = array_sum(array_column($clientPayments, 'amount'));
                            $bankAccount    = BankAccount::find($data['bank_account_id']);

                            foreach ($clientPayments as $cp) {
                                $amount     = (float) $cp['amount'];
                                $customerId = (int) ($cp['customer_id'] ?? 0);
                                $customer   = \App\Models\Customer::find($customerId);
                                $docNum     = $cp['document_number'] ?? null;

                                // Registrar pagamento do cliente
                                ProjectPayment::create([
                                    'tenant_id'         => $record->tenant_id,
                                    'sales_project_id'  => $record->id,
                                    'type'              => 'client_payment',
                                    'status'            => ProjectPaymentStatus::DEPOSITED,
                                    'amount'            => $amount,
                                    'description'       => 'Pagamento recebido de ' . ($customer?->name ?? "Cliente #{$customerId}"),
                                    'payment_date'      => $data['payment_date'],
                                    'bank_account_id'   => $data['bank_account_id'],
                                    'payment_method'    => $data['payment_method'],
                                    'document_number'   => $docNum,
                                    'notes'             => $data['notes'] ?? null,
                                    'created_by'        => auth()->id(),
                                    'approved_by'       => auth()->id(),
                                    'approved_at'       => now(),
                                ]);

                                // Movimentação de caixa por cliente
                                if ($bankAccount) {
                                    $newBalance = $bankAccount->current_balance + $amount;
                                    CashMovement::create([
                                        'tenant_id'       => $record->tenant_id,
                                        'type'            => CashMovementType::INCOME,
                                        'amount'          => $amount,
                                        'balance_after'   => $newBalance,
                                        'description'     => 'Recebimento de ' . ($customer?->name ?? "Cliente #{$customerId}") . ' — Projeto: ' . $record->title,
                                        'movement_date'   => $data['payment_date'],
                                        'bank_account_id' => $data['bank_account_id'],
                                        'reference_type'  => SalesProject::class,
                                        'reference_id'    => $record->id,
                                        'payment_method'  => $data['payment_method'],
                                        'document_number' => $docNum,
                                        'notes'           => $data['notes'] ?? null,
                                        'created_by'      => auth()->id(),
                                    ]);
                                    $bankAccount->update(['current_balance' => $newBalance]);
                                    $bankAccount->refresh();
                                }
                            }

                            // Atualizar status e total recebido no projeto
                            $record->update([
                                'status'                  => ProjectStatus::PAYMENT_RECEIVED,
                                'payment_received_date'   => $data['payment_date'],
                                'received_amount'         => ($record->received_amount ?? 0) + $totalReceived,
                                'payment_bank_account_id' => $data['bank_account_id'],
                            ]);
                        });

                        Notification::make()
                            ->success()
                            ->title('Pagamento(s) registrado(s) com sucesso')
                            ->send();
                    }),

                // Finalizar projeto e coletar taxa administrativa
                Tables\Actions\Action::make('complete_project')
                    ->label('Finalizar Projeto')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (SalesProject $record) => $record->canCompleteProject())
                    ->form([
                        Forms\Components\Placeholder::make('warning')
                            ->content(fn (SalesProject $record) => new \Illuminate\Support\HtmlString(
                                '<div class="rounded-lg bg-warning-50 p-4 mb-4">'.
                                '<p class="text-warning-800 font-semibold">⚠️ Verificação Antes de Finalizar</p>'.
                                '<ul class="mt-2 text-sm text-warning-700 list-disc list-inside">'.
                                '<li>Total de entregas aprovadas: <strong>'.$record->deliveries()->where('status', 'approved')->count().'</strong></li>'.
                                '<li>Entregas pagas: <strong>'.$record->deliveries()->where('paid', true)->count().'</strong></li>'.
                                '<li>Entregas pendentes de pagamento: <strong>'.$record->deliveries()->where('status', 'approved')->where('paid', false)->count().'</strong></li>'.
                                '</ul>'.
                                '</div>'
                            )),

                        Forms\Components\Placeholder::make('info')
                            ->content(fn (SalesProject $record) => new \Illuminate\Support\HtmlString(
                                '<div class="space-y-2">'.
                                '<p><strong>Taxa administrativa disponível:</strong> R$ '.number_format($record->available_admin_fee, 2, ',', '.').'</p>'.
                                '<p><strong>Total pago aos associados:</strong> R$ '.number_format(
                                    ($record->associates_paid_amount ?? $record->deliveries()->where('paid', true)->sum('net_value')),
                                    2, ',', '.').'</p>'.
                                '</div>'
                            )),

                        // Não é necessário selecionar conta — taxa já foi recebida quando do pagamento do cliente

                        Forms\Components\DatePicker::make('completion_date')
                            ->label('Data de Conclusão')
                            ->required()
                            ->default(now()),

                        Forms\Components\Textarea::make('notes')
                            ->label('Observações')
                            ->rows(3),
                    ])
                    ->action(function (SalesProject $record, array $data) {
                        DB::transaction(function () use ($record) {
                            $adminFee = $record->available_admin_fee;

                            // A taxa administrativa já foi recebida no momento do pagamento do cliente,
                            // portanto apenas registramos que foi coletada e finalizamos o projeto.
                            $record->update([
                                'status' => ProjectStatus::COMPLETED,
                                'admin_fee_collected' => $adminFee,
                            ]);
                        });

                        Notification::make()
                            ->success()
                            ->title('Projeto finalizado com sucesso!')
                            ->body('A taxa administrativa foi coletada e adicionada ao caixa.')
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\DemandsRelationManager::class,
            RelationManagers\DeliveriesRelationManager::class,
            RelationManagers\AssociatePaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSalesProjects::route('/'),
            'create' => Pages\CreateSalesProject::route('/create'),
            'view' => Pages\ViewSalesProject::route('/{record}'),
            'edit' => Pages\EditSalesProject::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    protected static function getExportColumns(): array
    {
        return [
            'title' => 'Título',
            'type' => 'Tipo',
            'customer.name' => 'Cliente',
            'contract_number' => 'Nº Contrato',
            'start_date' => 'Data Início',
            'end_date' => 'Data Fim',
            'reference_year' => 'Ano Referência',
            'total_value' => 'Valor Total',
            'admin_fee_percentage' => 'Taxa Adm (%)',
            'status' => 'Status',
            'completed_at' => 'Finalizado Em',
        ];
    }
}
