<?php

namespace App\Filament\Resources\EquipmentResource\RelationManagers;

use App\Models\MaintenanceSchedule;
use App\Models\MaintenanceType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class MaintenanceSchedulesRelationManager extends RelationManager
{
    protected static string $relationship = 'maintenanceSchedules';

    protected static ?string $title = 'Programação de Manutenções';

    protected static ?string $modelLabel = 'Programação';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('maintenance_type_id')
                    ->label('Tipo de Manutenção')
                    ->options(MaintenanceType::active()->pluck('name', 'id'))
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if (!$state) return;
                        
                        $type = MaintenanceType::find($state);
                        if (!$type) return;
                        
                        $equipment = $this->ownerRecord;
                        
                        if ($type->interval_type === 'hours') {
                            $set('next_hours', $equipment->current_hours + $type->interval_value);
                        } elseif ($type->interval_type === 'km') {
                            $set('next_km', $equipment->current_km + $type->interval_value);
                        } elseif ($type->interval_type === 'days') {
                            $set('next_date', now()->addDays($type->interval_value));
                        }
                    }),

                Forms\Components\TextInput::make('next_hours')
                    ->label('Próxima em (horas)')
                    ->numeric()
                    ->suffix('h'),

                Forms\Components\TextInput::make('next_km')
                    ->label('Próxima em (km)')
                    ->numeric()
                    ->suffix('km'),

                Forms\Components\DatePicker::make('next_date')
                    ->label('Próxima Data'),

                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options(MaintenanceSchedule::STATUSES)
                    ->default('pending')
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('maintenanceType.name')
            ->columns([
                Tables\Columns\TextColumn::make('maintenanceType.name')
                    ->label('Manutenção')
                    ->searchable(),

                Tables\Columns\TextColumn::make('maintenanceType.interval_display')
                    ->label('Intervalo'),

                Tables\Columns\TextColumn::make('last_hours')
                    ->label('Última (h)')
                    ->formatStateUsing(fn ($state) => $state ? number_format($state, 0, ',', '.') . 'h' : '-'),

                Tables\Columns\TextColumn::make('last_date')
                    ->label('Última Data')
                    ->date('d/m/Y')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('remaining')
                    ->label('Faltam')
                    ->badge()
                    ->color(fn ($record) => $record->needs_warning ? 'warning' : 'gray'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => MaintenanceSchedule::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => match($state) {
                        'pending' => 'gray',
                        'overdue' => 'danger',
                        'completed' => 'success',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(MaintenanceSchedule::STATUSES),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Adicionar Manutenção'),
            ])
            ->actions([
                Tables\Actions\Action::make('register_maintenance')
                    ->label('Registrar')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->form([
                        Forms\Components\DatePicker::make('performed_date')
                            ->label('Data Realização')
                            ->required()
                            ->default(now()),

                        Forms\Components\TextInput::make('hours_at_maintenance')
                            ->label('Horímetro Atual')
                            ->numeric()
                            ->default(fn ($record) => $record->equipment->current_hours),

                        Forms\Components\TextInput::make('km_at_maintenance')
                            ->label('Odômetro Atual')
                            ->numeric()
                            ->default(fn ($record) => $record->equipment->current_km),

                        Forms\Components\TextInput::make('cost')
                            ->label('Custo')
                            ->numeric()
                            ->prefix('R$'),

                        Forms\Components\TextInput::make('performed_by')
                            ->label('Realizada por'),

                        Forms\Components\Textarea::make('notes')
                            ->label('Observações')
                            ->rows(2),
                    ])
                    ->action(function ($record, array $data) {
                        // Create maintenance record
                        \App\Models\MaintenanceRecord::create([
                            'equipment_id' => $record->equipment_id,
                            'maintenance_type_id' => $record->maintenance_type_id,
                            'title' => $record->maintenanceType->name,
                            'performed_date' => $data['performed_date'],
                            'hours_at_maintenance' => $data['hours_at_maintenance'],
                            'km_at_maintenance' => $data['km_at_maintenance'],
                            'cost' => $data['cost'],
                            'performed_by' => $data['performed_by'],
                            'notes' => $data['notes'],
                            'created_by' => auth()->id(),
                        ]);
                    })
                    ->successNotificationTitle('Manutenção registrada!'),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([]);
    }
}
