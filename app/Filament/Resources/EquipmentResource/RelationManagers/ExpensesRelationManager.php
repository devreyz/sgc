<?php

namespace App\Filament\Resources\EquipmentResource\RelationManagers;

use App\Enums\CashMovementType;
use App\Enums\ExpenseStatus;
use App\Enums\PaymentMethod;
use App\Models\BankAccount;
use App\Models\CashMovement;
use App\Models\Expense;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class ExpensesRelationManager extends RelationManager
{
    protected static string $relationship = 'expenses';

    protected static ?string $title = 'Despesas';

    protected static ?string $modelLabel = 'Despesa';

    protected static ?string $pluralModelLabel = 'Despesas';

    public function form(Form $form): Form
    {
        return $form
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
                    ->label('Data')
                    ->required()
                    ->default(now()),

                Forms\Components\DatePicker::make('due_date')
                    ->label('Vencimento')
                    ->required(),

                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options(ExpenseStatus::class)
                    ->required()
                    ->default(ExpenseStatus::PENDING)
                    ->native(false),

                Forms\Components\Select::make('bank_account_id')
                    ->label('Conta Bancária')
                    ->relationship('bankAccount', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->default(fn () => BankAccount::where('is_default', true)->first()?->id),

                Forms\Components\TextInput::make('document_number')
                    ->label('Nº Documento')
                    ->maxLength(50),

                Forms\Components\Textarea::make('notes')
                    ->label('Observações')
                    ->rows(2)
                    ->columnSpanFull(),

                Forms\Components\Hidden::make('created_by')
                    ->default(fn () => auth()->id()),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('description')
                    ->label('Descrição')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Valor')
                    ->money('BRL')
                    ->summarize(Tables\Columns\Summarizers\Sum::make()
                        ->money('BRL')
                        ->label('Total')),

                Tables\Columns\TextColumn::make('due_date')
                    ->label('Vencimento')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('paid_date')
                    ->label('Pagamento')
                    ->date('d/m/Y'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (ExpenseStatus $state): string => $state->getLabel())
                    ->color(fn (ExpenseStatus $state): string => $state->getColor()),

                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Fornecedor')
                    ->limit(20)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('due_date', 'desc')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Adicionar Despesa')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['created_by'] = auth()->id();
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('pay')
                    ->label('Pagar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->form([
                        Forms\Components\DatePicker::make('payment_date')
                            ->label('Data de Pagamento')
                            ->required()
                            ->default(now()),
                        Forms\Components\Select::make('bank_account_id')
                            ->label('Conta Bancária')
                            ->options(BankAccount::where('status', true)->pluck('name', 'id'))
                            ->required()
                            ->default(fn () => BankAccount::where('is_default', true)->first()?->id),
                        Forms\Components\Select::make('payment_method')
                            ->label('Forma de Pagamento')
                            ->options(PaymentMethod::class)
                            ->required()
                            ->native(false),
                        Forms\Components\TextInput::make('paid_amount')
                            ->label('Valor Pago')
                            ->numeric()
                            ->prefix('R$')
                            ->default(fn (Expense $record) => $record->total_amount),
                    ])
                    ->action(function (Expense $record, array $data): void {
                        DB::transaction(function () use ($record, $data) {
                            $record->update([
                                'paid_date' => $data['payment_date'],
                                'bank_account_id' => $data['bank_account_id'],
                                'payment_method' => $data['payment_method'],
                                'paid_amount' => $data['paid_amount'],
                                'status' => ExpenseStatus::PAID,
                                'paid_by' => auth()->id(),
                            ]);

                            $bankAccount = BankAccount::find($data['bank_account_id']);
                            $newBalance = $bankAccount->current_balance - $data['paid_amount'];

                            CashMovement::create([
                                'type' => CashMovementType::EXPENSE,
                                'amount' => $data['paid_amount'],
                                'balance_after' => $newBalance,
                                'description' => 'Despesa (Equipamento): ' . $record->description,
                                'movement_date' => $data['payment_date'],
                                'bank_account_id' => $data['bank_account_id'],
                                'reference_type' => Expense::class,
                                'reference_id' => $record->id,
                                'chart_account_id' => $record->chart_account_id,
                                'payment_method' => $data['payment_method'],
                                'created_by' => auth()->id(),
                            ]);

                            $bankAccount->update(['current_balance' => $newBalance]);
                        });

                        Notification::make()
                            ->success()
                            ->title('Despesa paga!')
                            ->send();
                    })
                    ->visible(fn (Expense $record): bool => $record->status === ExpenseStatus::PENDING),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
