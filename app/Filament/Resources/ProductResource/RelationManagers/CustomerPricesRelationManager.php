<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Models\Customer;
use App\Models\SalesProject;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class CustomerPricesRelationManager extends RelationManager
{
    protected static string $relationship = 'customerPrices';

    protected static ?string $title = 'Preços por Cliente';

    protected static ?string $modelLabel = 'Preço';

    protected static ?string $pluralModelLabel = 'Preços por Cliente';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('customer_id')
                    ->label('Cliente')
                    ->options(
                        Customer::where('tenant_id', session('tenant_id'))
                            ->where('status', true)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                    )
                    ->searchable()
                    ->preload()
                    ->required()
                    ->helperText('Cliente para o qual este preço se aplica'),

                Forms\Components\Select::make('project_id')
                    ->label('Projeto (opcional)')
                    ->options(
                        SalesProject::where('tenant_id', session('tenant_id'))
                            ->whereNotIn('status', ['cancelled', 'completed'])
                            ->orderBy('title')
                            ->pluck('title', 'id')
                    )
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->helperText('Deixe vazio para preço geral deste cliente'),

                Forms\Components\TextInput::make('sale_price')
                    ->label('Preço de Venda')
                    ->numeric()
                    ->prefix('R$')
                    ->required()
                    ->minValue(0.01)
                    ->helperText('Preço cobrado deste cliente por este produto'),

                Forms\Components\TextInput::make('cost_price')
                    ->label('Preço de Compra/Repasse')
                    ->numeric()
                    ->prefix('R$')
                    ->nullable()
                    ->helperText('Opcional. Se vazio, será calculado via taxa do projeto'),

                Forms\Components\DatePicker::make('start_date')
                    ->label('Vigência Início')
                    ->nullable()
                    ->helperText('Deixe vazio para vigência imediata'),

                Forms\Components\DatePicker::make('end_date')
                    ->label('Vigência Fim')
                    ->nullable()
                    ->afterOrEqual('start_date')
                    ->helperText('Deixe vazio para sem expiração'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('project.title')
                    ->label('Projeto')
                    ->placeholder('Geral')
                    ->limit(25)
                    ->sortable(),

                Tables\Columns\TextColumn::make('sale_price')
                    ->label('Preço Venda')
                    ->money('BRL')
                    ->sortable(),

                Tables\Columns\TextColumn::make('cost_price')
                    ->label('Preço Compra')
                    ->money('BRL')
                    ->placeholder('Via taxa')
                    ->sortable(),

                Tables\Columns\TextColumn::make('start_date')
                    ->label('Início')
                    ->date('d/m/Y')
                    ->placeholder('Imediato'),

                Tables\Columns\TextColumn::make('end_date')
                    ->label('Fim')
                    ->date('d/m/Y')
                    ->placeholder('Sem expiração'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('customer_id')
                    ->label('Cliente')
                    ->options(
                        Customer::where('tenant_id', session('tenant_id'))
                            ->where('status', true)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                    )
                    ->searchable(),

                Tables\Filters\SelectFilter::make('project_id')
                    ->label('Projeto')
                    ->relationship('project', 'title')
                    ->searchable()
                    ->preload(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Adicionar Preço por Cliente'),
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
