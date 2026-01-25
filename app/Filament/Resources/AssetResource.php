<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AssetResource\Pages;
use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Models\Asset;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AssetResource extends Resource
{
    protected static ?string $model = Asset::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationGroup = 'Cadastros';

    protected static ?string $modelLabel = 'Patrimônio';

    protected static ?string $pluralModelLabel = 'Patrimônios';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dados do Patrimônio')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Código')
                            ->unique(ignoreRecord: true)
                            ->maxLength(50),

                        Forms\Components\TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('type')
                            ->label('Tipo')
                            ->options(AssetType::class)
                            ->required(),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(AssetStatus::class)
                            ->required()
                            ->default(AssetStatus::DISPONIVEL),

                        Forms\Components\TextInput::make('brand')
                            ->label('Marca')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('model')
                            ->label('Modelo')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('year')
                            ->label('Ano')
                            ->numeric()
                            ->minValue(1900)
                            ->maxValue(date('Y') + 1),

                        Forms\Components\TextInput::make('plate')
                            ->label('Placa')
                            ->maxLength(10),

                        Forms\Components\TextInput::make('serial_number')
                            ->label('Nº Série')
                            ->maxLength(100),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Valores e Medidores')
                    ->schema([
                        Forms\Components\TextInput::make('purchase_value')
                            ->label('Valor de Aquisição')
                            ->numeric()
                            ->prefix('R$'),

                        Forms\Components\DatePicker::make('purchase_date')
                            ->label('Data de Aquisição'),

                        Forms\Components\TextInput::make('current_value')
                            ->label('Valor Atual')
                            ->numeric()
                            ->prefix('R$'),

                        Forms\Components\TextInput::make('hourimeter')
                            ->label('Horímetro')
                            ->numeric()
                            ->suffix('h'),

                        Forms\Components\TextInput::make('odometer')
                            ->label('Odômetro')
                            ->numeric()
                            ->suffix('km'),

                        Forms\Components\DatePicker::make('next_maintenance_date')
                            ->label('Próxima Manutenção'),
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
                Tables\Columns\TextColumn::make('code')
                    ->label('Código')
                    ->searchable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (AssetType $state): string => $state->label())
                    ->color(fn (AssetType $state): string => $state->color()),

                Tables\Columns\TextColumn::make('brand')
                    ->label('Marca')
                    ->searchable(),

                Tables\Columns\TextColumn::make('model')
                    ->label('Modelo'),

                Tables\Columns\TextColumn::make('plate')
                    ->label('Placa')
                    ->searchable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (AssetStatus $state): string => $state->label())
                    ->color(fn (AssetStatus $state): string => $state->color()),

                Tables\Columns\TextColumn::make('hourimeter')
                    ->label('Horímetro')
                    ->formatStateUsing(fn ($state): string => $state ? number_format($state, 1, ',', '.') . 'h' : '-'),

                Tables\Columns\TextColumn::make('next_maintenance_date')
                    ->label('Próx. Manutenção')
                    ->date('d/m/Y')
                    ->color(fn ($state): string => 
                        $state && $state < now() ? 'danger' : 
                        ($state && $state < now()->addDays(15) ? 'warning' : 'success')
                    ),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(AssetType::class),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(AssetStatus::class),
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
            'index' => Pages\ListAssets::route('/'),
            'create' => Pages\CreateAsset::route('/create'),
            'view' => Pages\ViewAsset::route('/{record}'),
            'edit' => Pages\EditAsset::route('/{record}/edit'),
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
