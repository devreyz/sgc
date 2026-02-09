<?php

namespace App\Filament\Resources;

use App\Enums\CashMovementType;
use App\Enums\ExpenseStatus;
use App\Enums\PaymentMethod;
use App\Filament\Resources\ExpenseResource\Pages;
use App\Models\Asset;
use App\Models\BankAccount;
use App\Models\CashMovement;
use App\Models\Expense;
use App\Models\SalesProject;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-trending-down';

    protected static ?string $navigationGroup = 'Financeiro';

    protected static ?string $modelLabel = 'Despesa';

    protected static ?string $pluralModelLabel = 'Despesas';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dados da Despesa')
                    ->schema([
                        Forms\Components\TextInput::make('description')
                            ->label('Descrição')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\Select::make('chart_account_id')
                            ->label('Plano de Contas')
                            ->relationship('chartAccount', 'name', fn ($query) => $query->where('type', 'despesa'))
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\Select::make('supplier_id')
                            ->label('Fornecedor')
                            ->relationship('supplier', 'name')
                            ->searchable()
                            ->preload(),

                        Forms\Components\TextInput::make('amount')
                            ->label('Valor')
                            ->numeric()
                            ->prefix('R$')
                            ->required(),

                        Forms\Components\DatePicker::make('date')
                            ->label('Data do Documento')
                            ->required()
                            ->default(now()),

                        Forms\Components\DatePicker::make('due_date')
                            ->label('Data de Vencimento')
                            ->required(),

                        Forms\Components\DatePicker::make('paid_date')
                            ->label('Data de Pagamento'),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(ExpenseStatus::class)
                            ->required()
                            ->default(ExpenseStatus::PENDING),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Pagamento')
                    ->schema([
                        Forms\Components\Select::make('bank_account_id')
                            ->label('Conta Bancária')
                            ->relationship('bankAccount', 'name')
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('payment_method')
                            ->label('Forma de Pagamento')
                            ->options(PaymentMethod::class),

                        Forms\Components\Select::make('paid_by')
                            ->label('Pago por')
                            ->relationship('payer', 'name')
                            ->searchable()
                            ->preload(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Vinculação')
                    ->schema([
                        Forms\Components\MorphToSelect::make('expenseable')
                            ->label('Vincular a')
                            ->types([
                                Forms\Components\MorphToSelect\Type::make(Asset::class)
                                    ->titleAttribute('name')
                                    ->label('Patrimônio'),
                                Forms\Components\MorphToSelect\Type::make(SalesProject::class)
                                    ->titleAttribute('title')
                                    ->label('Projeto de Venda'),
                                Forms\Components\MorphToSelect\Type::make(User::class)
                                    ->titleAttribute('name')
                                    ->label('Usuário'),
                            ])
                            ->searchable()
                            ->preload(),
                    ])
                    ->collapsed(),

                Forms\Components\Section::make('Documentos')
                    ->schema([
                        Forms\Components\TextInput::make('document_number')
                            ->label('Nº Documento')
                            ->maxLength(50),

                        Forms\Components\FileUpload::make('document_path')
                            ->label('Comprovante')
                            ->disk('google')
                            ->directory('despesas')
                            ->visibility('private'),
                    ])
                    ->columns(2)
                    ->collapsed(),

                Forms\Components\Section::make('Observações')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label('Observações')
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
                Tables\Columns\TextColumn::make('description')
                    ->label('Descrição')
                    ->searchable()
                    ->limit(40),

                Tables\Columns\TextColumn::make('chartAccount.name')
                    ->label('Categoria')
                    ->searchable(),

                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Fornecedor')
                    ->searchable()
                    ->limit(20),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Valor')
                    ->money('BRL')
                    ->sortable(),

                Tables\Columns\TextColumn::make('due_date')
                    ->label('Vencimento')
                    ->date('d/m/Y')
                    ->sortable()
                    ->color(fn (Expense $record): string => $record->status === ExpenseStatus::PENDING && $record->due_date?->isPast() ? 'danger' :
                        ($record->status === ExpenseStatus::PENDING && $record->due_date?->diffInDays(now()) <= 7 ? 'warning' : 'gray')
                    ),

                Tables\Columns\TextColumn::make('paid_date')
                    ->label('Pagamento')
                    ->date('d/m/Y'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (ExpenseStatus $state): string => $state->getLabel())
                    ->color(fn (ExpenseStatus $state): string => $state->getColor()),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(ExpenseStatus::class),
                Tables\Filters\SelectFilter::make('chart_account_id')
                    ->label('Categoria')
                    ->relationship('chartAccount', 'name'),
                Tables\Filters\Filter::make('due_date')
                    ->form([
                        Forms\Components\DatePicker::make('due_from')
                            ->label('Vencimento de'),
                        Forms\Components\DatePicker::make('due_until')
                            ->label('Vencimento até'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['due_from'], fn ($q, $date) => $q->whereDate('due_date', '>=', $date))
                            ->when($data['due_until'], fn ($q, $date) => $q->whereDate('due_date', '<=', $date));
                    }),
                Tables\Filters\Filter::make('overdue')
                    ->label('Vencidas')
                    ->query(fn (Builder $query): Builder => $query->where('due_date', '<', now())->where('status', ExpenseStatus::PENDING)
                    ),
            ])
            ->actions([
                Tables\Actions\Action::make('pay')
                    ->label('Pagar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\DatePicker::make('payment_date')
                            ->label('Data de Pagamento')
                            ->required()
                            ->default(now()),
                        Forms\Components\Select::make('bank_account_id')
                            ->label('Conta Bancária')
                            ->options(BankAccount::where('status', true)->pluck('name', 'id'))
                            ->required(),
                        Forms\Components\Select::make('payment_method')
                            ->label('Forma de Pagamento')
                            ->options(PaymentMethod::class)
                            ->required(),
                        Forms\Components\TextInput::make('paid_amount')
                            ->label('Valor Pago')
                            ->numeric()
                            ->prefix('R$')
                            ->default(fn (Expense $record) => $record->amount)
                            ->helperText('Informe o valor efetivamente pago'),
                    ])
                    ->action(function (Expense $record, array $data): void {
                        DB::transaction(function () use ($record, $data) {
                            // Atualizar despesa
                            $record->update([
                                'paid_date' => $data['payment_date'],
                                'bank_account_id' => $data['bank_account_id'],
                                'payment_method' => $data['payment_method'],
                                'paid_amount' => $data['paid_amount'],
                                'status' => ExpenseStatus::PAID,
                                'paid_by' => auth()->id(),
                            ]);

                            // Registrar movimento de caixa (saída)
                            $bankAccount = BankAccount::find($data['bank_account_id']);
                            $newBalance = $bankAccount->current_balance - $data['paid_amount'];

                            CashMovement::create([
                                'type' => CashMovementType::EXPENSE,
                                'amount' => $data['paid_amount'],
                                'balance_after' => $newBalance,
                                'description' => 'Despesa: '.$record->description,
                                'movement_date' => $data['payment_date'],
                                'bank_account_id' => $data['bank_account_id'],
                                'reference_type' => Expense::class,
                                'reference_id' => $record->id,
                                'chart_account_id' => $record->chart_account_id,
                                'payment_method' => $data['payment_method'],
                                'document_number' => $record->document_number,
                                'created_by' => auth()->id(),
                            ]);

                            // Atualizar saldo da conta
                            $bankAccount->update(['current_balance' => $newBalance]);
                        });

                        Notification::make()
                            ->success()
                            ->title('Despesa paga com sucesso')
                            ->send();
                    })
                    ->visible(fn (Expense $record): bool => $record->status === ExpenseStatus::PENDING),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExpenses::route('/'),
            'create' => Pages\CreateExpense::route('/create'),
            'view' => Pages\ViewExpense::route('/{record}'),
            'edit' => Pages\EditExpense::route('/{record}/edit'),
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
