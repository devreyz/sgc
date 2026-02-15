<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceProviderServiceResource\Pages;
use App\Filament\Resources\ServiceProviderServiceResource\RelationManagers;
use App\Models\ServiceProviderService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Traits\TenantScoped;

class ServiceProviderServiceResource extends Resource
{
    use TenantScoped;
    protected static ?string $model = ServiceProviderService::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationGroup = 'Serviços';

    protected static ?string $modelLabel = 'Serviço de Prestador';

    protected static ?string $pluralModelLabel = 'Serviços de Prestadores';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Relacionamentos')
                    ->schema([
                        Forms\Components\Select::make('service_provider_id')
                            ->label('Prestador')
                            ->relationship('serviceProvider', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('service_id')
                            ->label('Serviço')
                            ->relationship('service', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                    ])
                    ->columns(2),
                
                Forms\Components\Section::make('Valores do Prestador')
                    ->description('Valores que o prestador receberá por unidade de trabalho')
                    ->schema([
                        Forms\Components\TextInput::make('provider_hourly_rate')
                            ->label('Valor por Hora')
                            ->helperText('Quanto o prestador recebe por hora trabalhada')
                            ->numeric()
                            ->prefix('R$')
                            ->nullable(),
                        Forms\Components\TextInput::make('provider_daily_rate')
                            ->label('Valor por Diária')
                            ->helperText('Quanto o prestador recebe por dia trabalhado')
                            ->numeric()
                            ->prefix('R$')
                            ->nullable(),
                        Forms\Components\TextInput::make('provider_unit_rate')
                            ->label('Valor por Unidade')
                            ->helperText('Quanto o prestador recebe por unidade (km, kg, etc)')
                            ->numeric()
                            ->prefix('R$')
                            ->nullable(),
                    ])
                    ->columns(3),
                
                Forms\Components\Section::make('Status e Observações')
                    ->schema([
                        Forms\Components\Toggle::make('status')
                            ->label('Ativo')
                            ->helperText('Prestador ainda oferece este serviço?')
                            ->default(true)
                            ->required(),
                        Forms\Components\Textarea::make('notes')
                            ->label('Observações')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('serviceProvider.name')
                    ->label('Prestador')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('service.name')
                    ->label('Serviço')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('provider_hourly_rate')
                    ->label('Valor/Hora')
                    ->money('BRL')
                    ->sortable(),
                Tables\Columns\TextColumn::make('provider_daily_rate')
                    ->label('Valor/Diária')
                    ->money('BRL')
                    ->sortable(),
                Tables\Columns\TextColumn::make('provider_unit_rate')
                    ->label('Valor/Unidade')
                    ->money('BRL')
                    ->sortable(),
                Tables\Columns\IconColumn::make('status')
                    ->label('Ativo')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Atualizado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('status')
                    ->label('Status')
                    ->placeholder('Todos')
                    ->trueLabel('Apenas Ativos')
                    ->falseLabel('Apenas Inativos'),
                Tables\Filters\SelectFilter::make('service_provider_id')
                    ->label('Prestador')
                    ->relationship('serviceProvider', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('service_id')
                    ->label('Serviço')
                    ->relationship('service', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListServiceProviderServices::route('/'),
            'create' => Pages\CreateServiceProviderService::route('/create'),
            'edit' => Pages\EditServiceProviderService::route('/{record}/edit'),
        ];
    }
}
