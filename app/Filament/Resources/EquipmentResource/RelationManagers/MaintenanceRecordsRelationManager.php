<?php

namespace App\Filament\Resources\EquipmentResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\MaintenanceType;

class MaintenanceRecordsRelationManager extends RelationManager
{
    protected static string $relationship = 'maintenanceRecords';

    protected static ?string $title = 'Histórico de Manutenções';

    protected static ?string $modelLabel = 'Registro';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Título/Descrição')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\Select::make('maintenance_type_id')
                            ->label('Tipo de Manutenção')
                            ->options(MaintenanceType::active()->pluck('name', 'id'))
                            ->searchable()
                            ->preload(),

                        Forms\Components\DatePicker::make('performed_date')
                            ->label('Data Realização')
                            ->required()
                            ->default(now()),

                        Forms\Components\TextInput::make('hours_at_maintenance')
                            ->label('Horímetro')
                            ->numeric()
                            ->suffix('h')
                            ->default(fn () => $this->ownerRecord->current_hours),

                        Forms\Components\TextInput::make('km_at_maintenance')
                            ->label('Odômetro')
                            ->numeric()
                            ->suffix('km')
                            ->default(fn () => $this->ownerRecord->current_km),

                        Forms\Components\TextInput::make('cost')
                            ->label('Custo')
                            ->numeric()
                            ->prefix('R$'),

                        Forms\Components\TextInput::make('performed_by')
                            ->label('Realizada por'),

                        Forms\Components\TextInput::make('invoice_number')
                            ->label('Nº Nota Fiscal'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Detalhes')
                    ->schema([
                        Forms\Components\Textarea::make('description')
                            ->label('Descrição Detalhada')
                            ->rows(2)
                            ->columnSpanFull(),

                        Forms\Components\TagsInput::make('parts_replaced')
                            ->label('Peças Trocadas')
                            ->placeholder('Adicione peças...')
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('notes')
                            ->label('Observações')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->collapsed(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->defaultSort('performed_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('performed_date')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('title')
                    ->label('Descrição')
                    ->limit(40)
                    ->searchable(),

                Tables\Columns\TextColumn::make('maintenanceType.name')
                    ->label('Tipo')
                    ->badge()
                    ->placeholder('Avulso'),

                Tables\Columns\TextColumn::make('hours_at_maintenance')
                    ->label('Horímetro')
                    ->formatStateUsing(fn ($state) => $state ? number_format($state, 0, ',', '.') . 'h' : '-'),

                Tables\Columns\TextColumn::make('cost')
                    ->label('Custo')
                    ->money('BRL')
                    ->color('danger'),

                Tables\Columns\TextColumn::make('performed_by')
                    ->label('Por')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('maintenance_type_id')
                    ->label('Tipo')
                    ->options(MaintenanceType::pluck('name', 'id')),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Registrar Manutenção')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['created_by'] = auth()->id();
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([]);
    }
}
