<?php

namespace App\Filament\Resources\OrganizationResource\RelationManagers;

use App\Models\PriceTable;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ClientsRelationManager extends RelationManager
{
    protected static string $relationship = 'clients';

    protected static ?string $title = 'Clientes (Escolas, Creches, etc.)';

    protected static ?string $modelLabel = 'cliente';

    protected static ?string $pluralModelLabel = 'clientes';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nome')
                ->required()
                ->columnSpan(2),

            Forms\Components\TextInput::make('cnpj')
                ->label('CNPJ')
                ->placeholder('00.000.000/0000-00'),

            Forms\Components\Select::make('type')
                ->label('Tipo')
                ->options([
                    'escola'      => 'Escola',
                    'creche'      => 'Creche',
                    'prefeitura'  => 'Prefeitura',
                    'hospital'    => 'Hospital',
                    'restaurante' => 'Restaurante / Refeitório',
                    'mercado'     => 'Mercado',
                    'outro'       => 'Outro',
                ])
                ->required()
                ->default('escola'),

            Forms\Components\Select::make('price_table_id')
                ->label('Tabela de Preços')
                ->options(fn () => PriceTable::where('tenant_id', session('tenant_id'))
                    ->active()->pluck('name', 'id'))
                ->searchable()
                ->placeholder('— Nenhuma (usar preços do produto) —')
                ->helperText('Tabela de preços padrão para este cliente'),

            Forms\Components\TextInput::make('city')
                ->label('Cidade'),

            Forms\Components\TextInput::make('state')
                ->label('UF')
                ->maxLength(2),

            Forms\Components\Toggle::make('status')
                ->label('Ativo')
                ->default(true),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->weight('semibold'),

                Tables\Columns\BadgeColumn::make('type')
                    ->label('Tipo')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'escola'      => 'Escola',
                        'creche'      => 'Creche',
                        'prefeitura'  => 'Prefeitura',
                        'hospital'    => 'Hospital',
                        'restaurante' => 'Refeitório',
                        'mercado'     => 'Mercado',
                        default       => 'Outro',
                    }),

                Tables\Columns\TextColumn::make('priceTable.name')
                    ->label('Tabela de Preços')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('city')
                    ->label('Cidade')
                    ->placeholder('—'),

                Tables\Columns\IconColumn::make('status')
                    ->label('Ativo')
                    ->boolean(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['tenant_id'] = session('tenant_id');
                        return $data;
                    }),
                Tables\Actions\AssociateAction::make()
                    ->label('Vincular Cliente Existente')
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['name', 'trade_name'])
                    ->recordTitle(fn (Model $record): string =>
                        $record->name . ($record->trade_name && $record->trade_name !== $record->name
                            ? " ({$record->trade_name})"
                            : '')
                    ),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DissociateAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DissociateBulkAction::make(),
                ]),
            ]);
    }
}
