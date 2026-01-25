<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceOrderResource\Pages;
use App\Enums\ServiceOrderStatus;
use App\Enums\ServiceType;
use App\Models\ServiceOrder;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ServiceOrderResource extends Resource
{
    protected static ?string $model = ServiceOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Serviços';

    protected static ?string $modelLabel = 'Ordem de Serviço';

    protected static ?string $pluralModelLabel = 'Ordens de Serviço';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dados da Ordem de Serviço')
                    ->schema([
                        Forms\Components\TextInput::make('number')
                            ->label('Número')
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn('edit'),

                        Forms\Components\Select::make('associate_id')
                            ->label('Associado')
                            ->relationship('associate', 'id')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->user->name)
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\Select::make('service_id')
                            ->label('Serviço')
                            ->relationship('service', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $service = \App\Models\Service::find($state);
                                    if ($service) {
                                        $set('unit', $service->unit);
                                        $set('unit_price', $service->price);
                                    }
                                }
                            }),

                        Forms\Components\Select::make('asset_id')
                            ->label('Equipamento')
                            ->relationship('asset', 'name')
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(ServiceOrderStatus::class)
                            ->required()
                            ->default(ServiceOrderStatus::SCHEDULED),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Execução')
                    ->schema([
                        Forms\Components\DatePicker::make('scheduled_date')
                            ->label('Data Agendada')
                            ->required(),

                        Forms\Components\DatePicker::make('execution_date')
                            ->label('Data de Execução'),

                        Forms\Components\TextInput::make('quantity')
                            ->label('Quantidade')
                            ->numeric()
                            ->required()
                            ->minValue(0.01)
                            ->reactive()
                            ->afterStateUpdated(fn ($state, callable $set, callable $get) => 
                                $set('final_price', $state * $get('unit_price'))
                            ),

                        Forms\Components\TextInput::make('unit')
                            ->label('Unidade')
                            ->disabled()
                            ->dehydrated(true),

                        Forms\Components\TextInput::make('unit_price')
                            ->label('Preço Unitário')
                            ->numeric()
                            ->prefix('R$')
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(fn ($state, callable $set, callable $get) => 
                                $set('final_price', $state * $get('quantity'))
                            ),

                        Forms\Components\TextInput::make('final_price')
                            ->label('Valor Final')
                            ->numeric()
                            ->prefix('R$')
                            ->disabled()
                            ->dehydrated(true),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Medidores')
                    ->schema([
                        Forms\Components\TextInput::make('start_hourimeter')
                            ->label('Horímetro Inicial')
                            ->numeric(),

                        Forms\Components\TextInput::make('end_hourimeter')
                            ->label('Horímetro Final')
                            ->numeric(),

                        Forms\Components\TextInput::make('start_odometer')
                            ->label('Odômetro Inicial')
                            ->numeric(),

                        Forms\Components\TextInput::make('end_odometer')
                            ->label('Odômetro Final')
                            ->numeric(),
                    ])
                    ->columns(4)
                    ->collapsed(),

                Forms\Components\Section::make('Localização e Observações')
                    ->schema([
                        Forms\Components\TextInput::make('location')
                            ->label('Local de Execução')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('operator_name')
                            ->label('Nome do Operador')
                            ->maxLength(255),

                        Forms\Components\Textarea::make('description')
                            ->label('Descrição do Serviço')
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('notes')
                            ->label('Observações')
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
                Tables\Columns\TextColumn::make('number')
                    ->label('Número')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('scheduled_date')
                    ->label('Agendamento')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('associate.user.name')
                    ->label('Associado')
                    ->searchable()
                    ->limit(20),

                Tables\Columns\TextColumn::make('service.name')
                    ->label('Serviço')
                    ->searchable(),

                Tables\Columns\TextColumn::make('asset.name')
                    ->label('Equipamento')
                    ->limit(15),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Quantidade')
                    ->formatStateUsing(fn ($state, $record): string => 
                        number_format($state, 2, ',', '.') . ' ' . $record->unit
                    ),

                Tables\Columns\TextColumn::make('final_price')
                    ->label('Valor')
                    ->money('BRL'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (ServiceOrderStatus $state): string => $state->label())
                    ->color(fn (ServiceOrderStatus $state): string => $state->color()),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(ServiceOrderStatus::class),
                Tables\Filters\SelectFilter::make('service_id')
                    ->label('Serviço')
                    ->relationship('service', 'name'),
                Tables\Filters\SelectFilter::make('asset_id')
                    ->label('Equipamento')
                    ->relationship('asset', 'name'),
            ])
            ->actions([
                Tables\Actions\Action::make('execute')
                    ->label('Executar')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\DatePicker::make('execution_date')
                            ->label('Data de Execução')
                            ->required()
                            ->default(now()),
                        Forms\Components\TextInput::make('end_hourimeter')
                            ->label('Horímetro Final')
                            ->numeric(),
                        Forms\Components\TextInput::make('end_odometer')
                            ->label('Odômetro Final')
                            ->numeric(),
                    ])
                    ->action(function (ServiceOrder $record, array $data): void {
                        $record->update([
                            'execution_date' => $data['execution_date'],
                            'end_hourimeter' => $data['end_hourimeter'] ?? null,
                            'end_odometer' => $data['end_odometer'] ?? null,
                            'status' => ServiceOrderStatus::COMPLETED,
                        ]);
                    })
                    ->visible(fn (ServiceOrder $record): bool => 
                        $record->status === ServiceOrderStatus::SCHEDULED
                    ),

                Tables\Actions\Action::make('bill')
                    ->label('Faturar')
                    ->icon('heroicon-o-currency-dollar')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(fn (ServiceOrder $record) => $record->update(['status' => ServiceOrderStatus::BILLED]))
                    ->visible(fn (ServiceOrder $record): bool => 
                        $record->status === ServiceOrderStatus::COMPLETED
                    ),

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
            'index' => Pages\ListServiceOrders::route('/'),
            'create' => Pages\CreateServiceOrder::route('/create'),
            'view' => Pages\ViewServiceOrder::route('/{record}'),
            'edit' => Pages\EditServiceOrder::route('/{record}/edit'),
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
