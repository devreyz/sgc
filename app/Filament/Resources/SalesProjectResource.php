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
                            ->default(ProjectStatus::DRAFT),

                        Forms\Components\Select::make('customer_id')
                            ->label('Cliente')
                            ->relationship('customer', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\TextInput::make('contract_number')
                            ->label('Nº Contrato')
                            ->maxLength(50),

                        Forms\Components\DatePicker::make('start_date')
                            ->label('Data Início')
                            ->required(),

                        Forms\Components\DatePicker::make('end_date')
                            ->label('Data Fim')
                            ->required()
                            ->after('start_date'),

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
                    ])
                    ->columns(2),

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
                    ->visible(fn (SalesProject $record) => $record->canReceivePayment())
                    ->form([
                        Forms\Components\DatePicker::make('payment_date')
                            ->label('Data do Pagamento')
                            ->required()
                            ->default(now()),

                        Forms\Components\TextInput::make('received_amount')
                            ->label('Valor Recebido')
                            ->numeric()
                            ->required()
                            ->prefix('R$')
                            ->default(fn (SalesProject $record) => $record->total_delivered_value),

                        Forms\Components\Select::make('bank_account_id')
                            ->label('Conta Bancária')
                            ->relationship('paymentBankAccount', 'name')
                            ->options(BankAccount::where('status', true)->pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->helperText('Conta onde o valor foi depositado'),

                        Forms\Components\Select::make('payment_method')
                            ->label('Forma de Pagamento')
                            ->options(PaymentMethod::class)
                            ->required(),

                        Forms\Components\TextInput::make('document_number')
                            ->label('Número do Documento')
                            ->maxLength(100),

                        Forms\Components\Textarea::make('notes')
                            ->label('Observações')
                            ->rows(3),
                    ])
                    ->action(function (SalesProject $record, array $data) {
                        DB::transaction(function () use ($record, $data) {
                            // Atualizar projeto
                            $record->update([
                                'status' => ProjectStatus::PAYMENT_RECEIVED,
                                'payment_received_date' => $data['payment_date'],
                                'received_amount' => $data['received_amount'],
                                'payment_bank_account_id' => $data['bank_account_id'],
                            ]);

                            // Registrar pagamento do cliente
                            ProjectPayment::create([
                                'sales_project_id' => $record->id,
                                'type' => 'client_payment',
                                'status' => ProjectPaymentStatus::DEPOSITED,
                                'amount' => $data['received_amount'],
                                'description' => 'Pagamento recebido do cliente '.$record->customer->name,
                                'payment_date' => $data['payment_date'],
                                'bank_account_id' => $data['bank_account_id'],
                                'payment_method' => $data['payment_method'],
                                'document_number' => $data['document_number'] ?? null,
                                'notes' => $data['notes'] ?? null,
                                'created_by' => auth()->id(),
                                'approved_by' => auth()->id(),
                                'approved_at' => now(),
                            ]);

                            // Registrar movimento de caixa (entrada)
                            $bankAccount = BankAccount::find($data['bank_account_id']);
                            $newBalance = $bankAccount->current_balance + $data['received_amount'];

                            CashMovement::create([
                                'type' => CashMovementType::INCOME,
                                'amount' => $data['received_amount'],
                                'balance_after' => $newBalance,
                                'description' => 'Recebimento do projeto: '.$record->title,
                                'movement_date' => $data['payment_date'],
                                'bank_account_id' => $data['bank_account_id'],
                                'reference_type' => SalesProject::class,
                                'reference_id' => $record->id,
                                'payment_method' => $data['payment_method'],
                                'document_number' => $data['document_number'] ?? null,
                                'notes' => $data['notes'] ?? null,
                                'created_by' => auth()->id(),
                            ]);

                            // Atualizar saldo da conta
                            $bankAccount->update(['current_balance' => $newBalance]);
                        });

                        Notification::make()
                            ->success()
                            ->title('Pagamento recebido com sucesso')
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
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\DemandsRelationManager::class,
            RelationManagers\DeliveriesRelationManager::class,
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
