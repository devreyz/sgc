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
use App\Filament\Traits\TenantScoped;

class BankAccountResource extends Resource
{
    use TenantScoped;
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
                Forms\Components\Section::make('Identificação')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nome da Conta')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ex: Caixa Interno, Banco do Brasil, Sicredi')
                            ->columnSpan(2),

                        Forms\Components\Select::make('type')
                            ->label('Tipo de Conta')
                            ->options([
                                'caixa' => 'Caixa',
                                'corrente' => 'Conta Corrente',
                                'poupanca' => 'Poupança',
                                'investimento' => 'Investimento',
                                'aplicacao' => 'Aplicação',
                            ])
                            ->required()
                            ->reactive()
                            ->default('corrente'),

                        Forms\Components\Toggle::make('is_default')
                            ->label('Conta Padrão')
                            ->helperText('Conta padrão para novas operações')
                            ->default(false),

                        Forms\Components\Toggle::make('status')
                            ->label('Ativa')
                            ->default(true),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Dados Bancários')
                    ->schema([
                        Forms\Components\TextInput::make('bank_code')
                            ->label('Código do Banco')
                            ->maxLength(3)
                            ->placeholder('Ex: 001'),

                        Forms\Components\TextInput::make('bank_name')
                            ->label('Nome do Banco')
                            ->maxLength(255)
                            ->placeholder('Ex: Banco do Brasil'),

                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('agency')
                                ->label('Agência')
                                ->maxLength(10)
                                ->placeholder('0001'),

                            Forms\Components\TextInput::make('agency_digit')
                                ->label('Dígito Ag.')
                                ->maxLength(1),
                        ]),

                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('account_number')
                                ->label('Número da Conta')
                                ->maxLength(20)
                                ->placeholder('12345'),

                            Forms\Components\TextInput::make('account_digit')
                                ->label('Dígito Conta')
                                ->maxLength(2),
                        ]),
                    ])
                    ->columns(2)
                    ->visible(fn (callable $get) => $get('type') !== 'caixa'),

                Forms\Components\Section::make('Saldos')
                    ->schema([
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
                            ->dehydrated(false)
                            ->visibleOn('edit'),

                        Forms\Components\DatePicker::make('balance_date')
                            ->label('Data de Referência do Saldo'),
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
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => match ($state) {
                        'caixa' => 'Caixa',
                        'corrente' => 'Corrente',
                        'poupanca' => 'Poupança',
                        'investimento' => 'Investimento',
                        'aplicacao' => 'Aplicação',
                        default => ucfirst($state),
                    })
                    ->color(fn ($state): string => match ($state) {
                        'caixa' => 'warning',
                        'corrente' => 'info',
                        'poupanca' => 'success',
                        'investimento' => 'primary',
                        'aplicacao' => 'primary',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('bank_name')
                    ->label('Banco')
                    ->searchable()
                    ->placeholder('Caixa Interno'),

                Tables\Columns\TextColumn::make('full_identification')
                    ->label('Agência / Conta')
                    ->getStateUsing(function (BankAccount $record): string {
                        if ($record->type === 'caixa') return '-';
                        $ag = $record->agency . ($record->agency_digit ? "-{$record->agency_digit}" : '');
                        $cc = $record->account_number . ($record->account_digit ? "-{$record->account_digit}" : '');
                        return "Ag: {$ag} | Cc: {$cc}";
                    }),

                Tables\Columns\IconColumn::make('is_default')
                    ->label('Padrão')
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->falseIcon('heroicon-o-minus'),

                Tables\Columns\TextColumn::make('current_balance')
                    ->label('Saldo Atual')
                    ->money('BRL')
                    ->color(fn ($state): string => $state >= 0 ? 'success' : 'danger')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\IconColumn::make('status')
                    ->label('Ativa')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\TernaryFilter::make('status')
                    ->label('Ativa'),
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'caixa' => 'Caixa',
                        'corrente' => 'Corrente',
                        'poupanca' => 'Poupança',
                        'investimento' => 'Investimento',
                        'aplicacao' => 'Aplicação',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
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
