<?php

namespace App\Filament\Resources;

use App\Enums\CashMovementType;
use App\Enums\PaymentMethod;
use App\Models\CashMovement;
use App\Models\BankAccount;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CashMovementResource extends Resource
{
    protected static ?string $model = CashMovement::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Financeiro';

    protected static ?string $modelLabel = 'Movimento de Caixa';

    protected static ?string $pluralModelLabel = 'Movimentos de Caixa';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dados do Movimento')
                    ->schema([
                        Forms\Components\Select::make('type')
                            ->label('Tipo')
                            ->options(CashMovementType::class)
                            ->required()
                            ->live(),

                        Forms\Components\DatePicker::make('movement_date')
                            ->label('Data do Movimento')
                            ->required()
                            ->default(now()),

                        Forms\Components\TextInput::make('amount')
                            ->label('Valor')
                            ->numeric()
                            ->required()
                            ->prefix('R$'),

                        Forms\Components\Select::make('bank_account_id')
                            ->label('Conta')
                            ->relationship('bankAccount', 'name')
                            ->options(BankAccount::where('status', true)->pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->default(fn () => BankAccount::where('is_default', true)->first()?->id),

                        Forms\Components\Select::make('transfer_to_account_id')
                            ->label('Transferir Para')
                            ->relationship('transferToAccount', 'name')
                            ->options(BankAccount::where('status', true)->pluck('name', 'id'))
                            ->visible(fn (Forms\Get $get) => $get('type') === CashMovementType::TRANSFER->value)
                            ->searchable(),

                        Forms\Components\Select::make('chart_account_id')
                            ->label('Conta Contábil')
                            ->relationship('chartAccount', 'name')
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('payment_method')
                            ->label('Forma de Pagamento')
                            ->options(PaymentMethod::class),

                        Forms\Components\TextInput::make('document_number')
                            ->label('Nº Documento')
                            ->maxLength(100),

                        Forms\Components\Textarea::make('description')
                            ->label('Descrição')
                            ->required()
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('notes')
                            ->label('Observações')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('movement_date')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (CashMovementType $state): string => $state->getLabel())
                    ->color(fn (CashMovementType $state): string => $state->getColor()),

                Tables\Columns\TextColumn::make('description')
                    ->label('Descrição')
                    ->limit(40)
                    ->searchable(),

                Tables\Columns\TextColumn::make('bankAccount.name')
                    ->label('Conta')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Valor')
                    ->money('BRL')
                    ->sortable()
                    ->color(fn (CashMovement $record): string => 
                        $record->type === CashMovementType::INCOME ? 'success' : 'danger'
                    ),

                Tables\Columns\TextColumn::make('balance_after')
                    ->label('Saldo Após')
                    ->money('BRL')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Forma Pgto')
                    ->badge()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(CashMovementType::class),

                Tables\Filters\SelectFilter::make('bank_account_id')
                    ->label('Conta')
                    ->relationship('bankAccount', 'name'),

                Tables\Filters\Filter::make('movement_date')
                    ->form([
                        Forms\Components\DatePicker::make('date_from')
                            ->label('De'),
                        Forms\Components\DatePicker::make('date_until')
                            ->label('Até'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['date_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('movement_date', '>=', $date),
                            )
                            ->when(
                                $data['date_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('movement_date', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('movement_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\CashMovementResource\Pages\ListCashMovements::route('/'),
            'create' => \App\Filament\Resources\CashMovementResource\Pages\CreateCashMovement::route('/create'),
            'view' => \App\Filament\Resources\CashMovementResource\Pages\ViewCashMovement::route('/{record}'),
            'edit' => \App\Filament\Resources\CashMovementResource\Pages\EditCashMovement::route('/{record}/edit'),
        ];
    }
}
