<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceResource\Pages;
use App\Enums\ServiceType;
use App\Models\Service;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Traits\TenantScoped;
use Illuminate\Validation\Rules\Unique;

class ServiceResource extends Resource
{
    use TenantScoped;
    protected static ?string $model = Service::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench';

    protected static ?string $navigationGroup = 'Serviços';

    protected static ?string $modelLabel = 'Serviço';

    protected static ?string $pluralModelLabel = 'Serviços';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dados do Serviço')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Código')
                            ->unique(ignoreRecord: true, modifyRuleUsing: function (Unique $rule) {
                                return $rule->where('tenant_id', session('tenant_id'));
                            })
                            ->maxLength(20),

                        Forms\Components\TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('type')
                            ->label('Tipo')
                            ->options(ServiceType::class)
                            ->required(),

                        Forms\Components\TextInput::make('unit')
                            ->label('Unidade')
                            ->required()
                            ->default('hora')
                            ->maxLength(20),

                        Forms\Components\TextInput::make('base_price')
                            ->label('Preço Base')
                            ->helperText('Preço padrão do serviço')
                            ->numeric()
                            ->prefix('R$')
                            ->required(),
                        
                        Forms\Components\TextInput::make('associate_price')
                            ->label('Preço Associado')
                            ->helperText('Preço especial para associados')
                            ->numeric()
                            ->prefix('R$')
                            ->nullable(),
                        
                        Forms\Components\TextInput::make('non_associate_price')
                            ->label('Preço Não-Associado')
                            ->helperText('Preço para pessoas avulsas')
                            ->numeric()
                            ->prefix('R$')
                            ->nullable(),

                        Forms\Components\Toggle::make('status')
                            ->label('Ativo')
                            ->default(true),

                        Forms\Components\Textarea::make('description')
                            ->label('Descrição')
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
                    ->formatStateUsing(fn (ServiceType $state): string => $state->getLabel())
                    ->color(fn (ServiceType $state): string => $state->getColor()),

                Tables\Columns\TextColumn::make('unit')
                    ->label('Unidade')
                    ->badge(),

                Tables\Columns\TextColumn::make('base_price')
                    ->label('Preço Base')
                    ->money('BRL')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('associate_price')
                    ->label('Associado')
                    ->money('BRL')
                    ->sortable()
                    ->toggleable(),
                
                Tables\Columns\TextColumn::make('non_associate_price')
                    ->label('Avulso')
                    ->money('BRL')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('status')
                    ->label('Ativo')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(ServiceType::class),
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
            'index' => Pages\ListServices::route('/'),
            'create' => Pages\CreateService::route('/create'),
            'edit' => Pages\EditService::route('/{record}/edit'),
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
