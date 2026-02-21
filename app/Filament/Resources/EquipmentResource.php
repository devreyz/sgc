<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EquipmentResource\Pages;
use App\Filament\Resources\EquipmentResource\RelationManagers;
use App\Filament\Traits\HasExportActions;
use App\Models\Equipment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Validation\Rules\Unique;
use Filament\Notifications\Notification;
use App\Filament\Traits\TenantScoped;

class EquipmentResource extends Resource
{
    use TenantScoped;
    use HasExportActions;
    
    protected static ?string $model = Equipment::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationGroup = 'Manutenção';

    protected static ?string $modelLabel = 'Equipamento';

    protected static ?string $pluralModelLabel = 'Equipamentos';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Identificação')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nome/Descrição')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ex: Trator New Holland T7'),

                        Forms\Components\TextInput::make('code')
                            ->label('Código Patrimônio')
                            ->unique(ignoreRecord: true, modifyRuleUsing: function (Unique $rule) {
                                return $rule->where('tenant_id', session('tenant_id'));
                            })
                            ->maxLength(50)
                            ->placeholder('Ex: TRAT-001'),

                        Forms\Components\Select::make('type')
                            ->label('Tipo')
                            ->options(Equipment::TYPES)
                            ->required()
                            ->searchable(),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(Equipment::STATUSES)
                            ->default('active')
                            ->required()
                            ->disabled()
                            ->dehydrated(fn (string $context): bool => $context === 'create')
                            ->helperText('Status só pode ser alterado por ações específicas'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Especificações')
                    ->schema([
                        Forms\Components\TextInput::make('brand')
                            ->label('Marca')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('model')
                            ->label('Modelo')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('year')
                            ->label('Ano')
                            ->maxLength(4),

                        Forms\Components\TextInput::make('serial_number')
                            ->label('Nº Série/Chassi')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('plate')
                            ->label('Placa')
                            ->maxLength(10)
                            ->placeholder('AAA-0000'),

                        Forms\Components\Select::make('responsible_id')
                            ->label('Responsável')
                            ->relationship('responsible', 'name')
                            ->searchable()
                            ->preload(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Medidores')
                    ->schema([
                        Forms\Components\TextInput::make('current_hours')
                            ->label('Horímetro Atual')
                            ->numeric()
                            ->suffix('horas')
                            ->default(0),

                        Forms\Components\TextInput::make('current_km')
                            ->label('Odômetro Atual')
                            ->numeric()
                            ->suffix('km')
                            ->default(0),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Aquisição')
                    ->schema([
                        Forms\Components\DatePicker::make('purchase_date')
                            ->label('Data de Aquisição'),

                        Forms\Components\TextInput::make('purchase_value')
                            ->label('Valor de Aquisição')
                            ->numeric()
                            ->prefix('R$'),
                    ])
                    ->columns(2)
                    ->collapsed(),

                Forms\Components\Section::make('Observações')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label('Observações')
                            ->rows(2)
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
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Equipment::TYPES[$state] ?? $state)
                    ->color('gray'),

                Tables\Columns\TextColumn::make('brand')
                    ->label('Marca')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('current_hours')
                    ->label('Horímetro')
                    ->formatStateUsing(fn ($state): string => number_format($state, 0, ',', '.') . 'h')
                    ->sortable(),

                Tables\Columns\TextColumn::make('current_km')
                    ->label('Odômetro')
                    ->formatStateUsing(fn ($state): string => number_format($state, 0, ',', '.') . 'km')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('overdue_maintenance_count')
                    ->label('Manutenções')
                    ->badge()
                    ->color(fn ($state): string => $state > 0 ? 'danger' : 'success')
                    ->formatStateUsing(fn ($state): string => $state > 0 ? "{$state} atrasada(s)" : 'Em dia')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Equipment::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => match($state) {
                        'active' => 'success',
                        'maintenance' => 'warning',
                        'inactive' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(Equipment::TYPES),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(Equipment::STATUSES),

                Tables\Filters\Filter::make('needs_attention')
                    ->label('Precisa Atenção')
                    ->query(fn (Builder $query): Builder => $query->needsAttention()),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->headerActions([
                self::getExportAction(),
            ])
            ->actions([
                Tables\Actions\Action::make('update_reading')
                    ->label('Atualizar Medidor')
                    ->icon('heroicon-o-clock')
                    ->color('info')
                    ->form([
                        Forms\Components\Select::make('reading_type')
                            ->label('Tipo')
                            ->options([
                                'hours' => 'Horímetro',
                                'km' => 'Odômetro',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('value')
                            ->label('Leitura Atual')
                            ->numeric()
                            ->required(),
                        Forms\Components\Textarea::make('notes')
                            ->label('Observações')
                            ->rows(2),
                    ])
                    ->action(function (Equipment $record, array $data) {
                        $record->updateReading(
                            $data['reading_type'],
                            $data['value'],
                            $data['notes'] ?? null
                        );
                        
                        Notification::make()
                            ->success()
                            ->title('Leitura atualizada!')
                            ->send();
                    }),

                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\MaintenanceSchedulesRelationManager::class,
            RelationManagers\MaintenanceRecordsRelationManager::class,
            RelationManagers\ExpensesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEquipment::route('/'),
            'create' => Pages\CreateEquipment::route('/create'),
            'view' => Pages\ViewEquipment::route('/{record}'),
            'edit' => Pages\EditEquipment::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getNavigationBadge(): ?string
    {
        // Count equipment with overdue maintenance
        $count = Equipment::whereHas('maintenanceSchedules', fn ($q) => $q->where('status', 'overdue'))->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    protected static function getExportColumns(): array
    {
        return [
            'code' => 'Código',
            'name' => 'Nome',
            'type' => 'Tipo',
            'brand' => 'Marca',
            'model' => 'Modelo',
            'year' => 'Ano',
            'serial_number' => 'Nº Série',
            'plate' => 'Placa',
            'current_hours' => 'Horímetro',
            'current_km' => 'Odômetro',
            'status' => 'Status',
            'purchase_date' => 'Data Aquisição',
            'purchase_value' => 'Valor Aquisição',
        ];
    }
}
