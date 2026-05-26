<?php

namespace App\Filament\Resources\SalesProjectResource\RelationManagers;

use App\Models\Associate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AssociateProductLimitsRelationManager extends RelationManager
{
    protected static string $relationship = 'associateProductLimits';

    protected static ?string $title = 'Limites por Produto/Associado';

    protected static ?string $modelLabel = 'Limite';

    protected static ?string $pluralModelLabel = 'Limites por Produto';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('associate_id')
                    ->label('Associado')
                    ->options(fn () =>
                        Associate::where('tenant_id', session('tenant_id'))
                            ->with('user')
                            ->get()
                            ->pluck('user.name', 'id')
                    )
                    ->searchable()
                    ->required(),

                Forms\Components\Select::make('product_id')
                    ->label('Produto')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),

                Forms\Components\TextInput::make('max_quantity')
                    ->label('Quantidade Máxima')
                    ->numeric()
                    ->minValue(0.001)
                    ->required()
                    ->helperText('Quantidade máxima que este associado pode entregar deste produto neste projeto.'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('associate.user.name')
                    ->label('Associado')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('product.name')
                    ->label('Produto')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('max_quantity')
                    ->label('Limite')
                    ->numeric(decimalPlaces: 3, decimalSeparator: ',', thousandsSeparator: '.')
                    ->suffix(' un')
                    ->sortable(),
            ])
            ->filters([])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['tenant_id'] = session('tenant_id');
                        return $data;
                    }),
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
}
