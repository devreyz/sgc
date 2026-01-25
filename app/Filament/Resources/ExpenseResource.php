<?php

namespace App\Filament\Resources;

use App\Enums\ExpenseStatus;
use App\Enums\PaymentMethod;
use App\Filament\Resources\ExpenseResource\Pages;
use App\Models\Asset;
use App\Models\Expense;
use App\Models\SalesProject;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

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
                            ->relationship('chartAccount', 'name', fn ($query) => $query->where('type', 'expense'))
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
                    ->formatStateUsing(fn (ExpenseStatus $state): string => $state->label())
                    ->color(fn (ExpenseStatus $state): string => $state->color()),
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
                            ->relationship('bankAccount', 'name')
                            ->required(),
                        Forms\Components\Select::make('payment_method')
                            ->label('Forma de Pagamento')
                            ->options(PaymentMethod::class)
                            ->required(),
                    ])
                    ->action(function (Expense $record, array $data): void {
                        $record->update([
                            'payment_date' => $data['payment_date'],
                            'bank_account_id' => $data['bank_account_id'],
                            'payment_method' => $data['payment_method'],
                            'status' => ExpenseStatus::PAID,
                            'paid_by' => auth()->id(),
                        ]);
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
