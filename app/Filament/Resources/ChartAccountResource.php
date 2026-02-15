<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ChartAccountResource\Pages;
use App\Models\ChartAccount;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ChartAccountResource extends Resource
{
    protected static ?string $model = ChartAccount::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Financeiro';

    protected static ?string $modelLabel = 'Plano de Contas';

    protected static ?string $pluralModelLabel = 'Plano de Contas';

    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dados da Conta')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Código')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(20),

                        Forms\Components\TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('type')
                            ->label('Tipo')
                            ->options([
                                'receita' => 'Receita',
                                'despesa' => 'Despesa',
                                'ativo' => 'Ativo',
                                'passivo' => 'Passivo',
                                'patrimonio' => 'Patrimônio Líquido',
                            ])
                            ->required(),

                        Forms\Components\Select::make('parent_id')
                            ->label('Conta Pai')
                            ->relationship('parent', 'name')
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('nature')
                            ->label('Natureza')
                            ->options([
                                'devedora' => 'Devedora',
                                'credora' => 'Credora',
                            ])
                            ->helperText('Devedora: aumenta com débito. Credora: aumenta com crédito.'),

                        Forms\Components\Toggle::make('allows_entries')
                            ->label('Permite Lançamentos')
                            ->default(true)
                            ->helperText('Contas sintéticas (pai) geralmente não permitem.'),

                        Forms\Components\Toggle::make('status')
                            ->label('Ativo')
                            ->default(true),

                        Forms\Components\Textarea::make('description')
                            ->label('Descrição')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Código')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => match ($state) {
                        'receita' => 'Receita',
                        'despesa' => 'Despesa',
                        'ativo' => 'Ativo',
                        'passivo' => 'Passivo',
                        'patrimonio' => 'Patrimônio Líquido',
                        default => ucfirst($state),
                    })
                    ->color(fn ($state): string => match ($state) {
                        'receita' => 'success',
                        'despesa' => 'danger',
                        'ativo' => 'info',
                        'passivo' => 'warning',
                        'patrimonio' => 'primary',
                        default => 'gray',
                    }),

                Tables\Columns\IconColumn::make('allows_entries')
                    ->label('Lanç.')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Conta Pai'),

                Tables\Columns\IconColumn::make('status')
                    ->label('Ativo')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'receita' => 'Receita',
                        'despesa' => 'Despesa',
                        'ativo' => 'Ativo',
                        'passivo' => 'Passivo',
                        'patrimonio' => 'Patrimônio Líquido',
                    ]),
            ])
            ->actions([
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
            'index' => Pages\ListChartAccounts::route('/'),
            'create' => Pages\CreateChartAccount::route('/create'),
            'edit' => Pages\EditChartAccount::route('/{record}/edit'),
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
