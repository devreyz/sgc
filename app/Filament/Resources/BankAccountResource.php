<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BankAccountResource\Pages;
use App\Models\BankAccount;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BankAccountResource extends Resource
{
    protected static ?string $model = BankAccount::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $navigationGroup = 'Financeiro';

    protected static ?string $modelLabel = 'Conta Bancária';

    protected static ?string $pluralModelLabel = 'Contas Bancárias';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dados da Conta')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nome da Conta')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('bank_name')
                            ->label('Banco')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('bank_code')
                            ->label('Código do Banco')
                            ->maxLength(10),

                        Forms\Components\TextInput::make('agency')
                            ->label('Agência')
                            ->required()
                            ->maxLength(10),

                        Forms\Components\TextInput::make('account_number')
                            ->label('Número da Conta')
                            ->required()
                            ->maxLength(20),

                        Forms\Components\Select::make('account_type')
                            ->label('Tipo de Conta')
                            ->options([
                                'corrente' => 'Corrente',
                                'poupanca' => 'Poupança',
                                'caixa' => 'Caixa',
                            ])
                            ->required(),

                        Forms\Components\TextInput::make('initial_balance')
                            ->label('Saldo Inicial')
                            ->numeric()
                            ->prefix('R$')
                            ->default(0),

                        Forms\Components\TextInput::make('current_balance')
                            ->label('Saldo Atual')
                            ->numeric()
                            ->prefix('R$')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\Toggle::make('status')
                            ->label('Ativo')
                            ->default(true),
                    ])
                    ->columns(3),

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
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('bank_name')
                    ->label('Banco')
                    ->searchable(),

                Tables\Columns\TextColumn::make('agency')
                    ->label('Agência'),

                Tables\Columns\TextColumn::make('account_number')
                    ->label('Conta'),

                Tables\Columns\TextColumn::make('account_type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => ucfirst($state)),

                Tables\Columns\TextColumn::make('current_balance')
                    ->label('Saldo Atual')
                    ->money('BRL')
                    ->color(fn ($state): string => $state >= 0 ? 'success' : 'danger')
                    ->sortable(),

                Tables\Columns\IconColumn::make('status')
                    ->label('Ativo')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\TernaryFilter::make('status')
                    ->label('Ativo'),
            ])
            ->actions([
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
            'index' => Pages\ListBankAccounts::route('/'),
            'create' => Pages\CreateBankAccount::route('/create'),
            'view' => Pages\ViewBankAccount::route('/{record}'),
            'edit' => Pages\EditBankAccount::route('/{record}/edit'),
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
