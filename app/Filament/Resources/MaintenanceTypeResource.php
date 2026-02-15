<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MaintenanceTypeResource\Pages;
use App\Models\MaintenanceType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Filament\Traits\TenantScoped;

class MaintenanceTypeResource extends Resource
{
    use TenantScoped;
    protected static ?string $model = MaintenanceType::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Manutenção';

    protected static ?string $modelLabel = 'Tipo de Manutenção';

    protected static ?string $pluralModelLabel = 'Tipos de Manutenção';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ex: Troca de Óleo'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Ativo')
                            ->default(true),

                        Forms\Components\Textarea::make('description')
                            ->label('Descrição')
                            ->rows(2)
                            ->placeholder('Descrição detalhada da manutenção')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Intervalo')
                    ->schema([
                        Forms\Components\Select::make('interval_type')
                            ->label('Tipo de Intervalo')
                            ->options(MaintenanceType::INTERVAL_TYPES)
                            ->required()
                            ->reactive(),

                        Forms\Components\TextInput::make('interval_value')
                            ->label('Valor do Intervalo')
                            ->numeric()
                            ->required()
                            ->suffix(fn (callable $get) => match($get('interval_type')) {
                                'hours' => 'horas',
                                'km' => 'km',
                                'days' => 'dias',
                                default => '',
                            })
                            ->helperText('A cada quantas horas/km/dias'),

                        Forms\Components\TextInput::make('warning_before')
                            ->label('Alertar Antes')
                            ->numeric()
                            ->default(50)
                            ->suffix(fn (callable $get) => match($get('interval_type')) {
                                'hours' => 'horas',
                                'km' => 'km',
                                'days' => 'dias',
                                default => '',
                            })
                            ->helperText('Alertar X antes do vencimento'),

                        Forms\Components\TextInput::make('estimated_cost')
                            ->label('Custo Estimado')
                            ->numeric()
                            ->prefix('R$'),
                    ])
                    ->columns(2),
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

                Tables\Columns\TextColumn::make('interval_type')
                    ->label('Tipo')
                    ->formatStateUsing(fn (string $state): string => MaintenanceType::INTERVAL_TYPES[$state] ?? $state)
                    ->badge(),

                Tables\Columns\TextColumn::make('interval_value')
                    ->label('Intervalo')
                    ->formatStateUsing(fn ($state, $record): string => 
                        number_format($state, 0, ',', '.') . match($record->interval_type) {
                            'hours' => 'h',
                            'km' => 'km',
                            'days' => ' dias',
                            default => '',
                        }
                    ),

                Tables\Columns\TextColumn::make('warning_before')
                    ->label('Alertar Antes')
                    ->formatStateUsing(fn ($state, $record): string => 
                        number_format($state, 0, ',', '.') . match($record->interval_type) {
                            'hours' => 'h',
                            'km' => 'km',
                            'days' => ' dias',
                            default => '',
                        }
                    )
                    ->toggleable(),

                Tables\Columns\TextColumn::make('estimated_cost')
                    ->label('Custo Est.')
                    ->money('BRL')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Ativo')
                    ->boolean(),

                Tables\Columns\TextColumn::make('schedules_count')
                    ->label('Equipamentos')
                    ->counts('schedules')
                    ->badge()
                    ->color('gray'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Ativo'),

                Tables\Filters\SelectFilter::make('interval_type')
                    ->label('Tipo')
                    ->options(MaintenanceType::INTERVAL_TYPES),
            ])
            ->actions([
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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMaintenanceTypes::route('/'),
            'create' => Pages\CreateMaintenanceType::route('/create'),
            'edit' => Pages\EditMaintenanceType::route('/{record}/edit'),
        ];
    }
}
