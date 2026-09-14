<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceProviderServiceResource\Pages;
use App\Filament\Traits\TenantScoped;
use App\Models\ServiceProviderService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ServiceProviderServiceResource extends Resource
{
    public static function canViewAny(): bool
    {
        return auth()->user()?->checkPermissionTo('manage_service_providers') ?? false;
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit($record): bool
    {
        return static::canViewAny() && (int) $record->tenant_id === (int) session('tenant_id');
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    use TenantScoped;

    protected static ?string $model = ServiceProviderService::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationGroup = 'Serviços';

    protected static ?string $modelLabel = 'Habilitação de Prestador';

    protected static ?string $pluralModelLabel = 'Habilitações de Serviços';

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
                            ->required()
                            ->unique('service_provider_services', 'service_id', ignoreRecord: true, modifyRuleUsing: fn ($rule, $get) => $rule->where('tenant_id', session('tenant_id'))->where('service_provider_id', $get('service_provider_id'))),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Status e Observações')
                    ->schema([
                        Forms\Components\Toggle::make('status')
                            ->label('Ativo')
                            ->helperText('Somente habilitações ativas permitem atribuir ou criar ordens deste serviço.')
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
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
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
