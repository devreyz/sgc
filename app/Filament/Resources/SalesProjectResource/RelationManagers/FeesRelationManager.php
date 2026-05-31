<?php

namespace App\Filament\Resources\SalesProjectResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class FeesRelationManager extends RelationManager
{
    protected static string $relationship = 'fees';

    protected static ?string $title = 'Taxas do Projeto';

    protected static ?string $modelLabel = 'taxa';

    protected static ?string $pluralModelLabel = 'taxas';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nome da Taxa')
                ->required()
                ->placeholder('Ex: Taxa administrativa, Frete, Bônus de Produção')
                ->columnSpan(2),

            Forms\Components\Select::make('type')
                ->label('Tipo')
                ->options([
                    'percentage' => 'Percentual (%)',
                    'fixed'      => 'Valor Fixo (R$)',
                ])
                ->required()
                ->default('percentage')
                ->live(),

            Forms\Components\Select::make('nature')
                ->label('Natureza')
                ->options([
                    'discount' => 'Desconto (reduz o líquido)',
                    'accrual'  => 'Acréscimo (aumenta o líquido)',
                ])
                ->required()
                ->default('discount')
                ->helperText('Descontos: Taxa adm., frete. Acréscimos: bônus, incentivo.'),

            Forms\Components\TextInput::make('value')
                ->label(fn (Forms\Get $get) => $get('type') === 'fixed' ? 'Valor (R$)' : 'Percentual (%)')
                ->required()
                ->numeric()
                ->minValue(0)
                ->suffix(fn (Forms\Get $get) => $get('type') === 'fixed' ? 'R$' : '%'),

            Forms\Components\TextInput::make('sort_order')
                ->label('Ordem de Aplicação')
                ->integer()
                ->default(0)
                ->helperText('Menor número = aplicada primeiro.'),

            Forms\Components\Toggle::make('active')
                ->label('Ativa')
                ->default(true)
                ->helperText('Taxas inativas não são aplicadas no faturamento.'),

            Forms\Components\Textarea::make('notes')
                ->label('Observações')
                ->columnSpanFull(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Taxa')
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('type')
                    ->label('Tipo')
                    ->formatStateUsing(fn ($state) => $state === 'percentage' ? 'Percentual' : 'Valor Fixo')
                    ->color(fn ($state) => $state === 'percentage' ? 'info' : 'warning'),

                Tables\Columns\BadgeColumn::make('nature')
                    ->label('Natureza')
                    ->formatStateUsing(fn ($state) => $state === 'accrual' ? 'Acréscimo' : 'Desconto')
                    ->color(fn ($state) => $state === 'accrual' ? 'success' : 'danger'),

                Tables\Columns\TextColumn::make('value')
                    ->label('Valor')
                    ->formatStateUsing(fn ($state, $record) => $record->getTypeLabel()),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Ordem')
                    ->sortable(),

                Tables\Columns\IconColumn::make('active')
                    ->label('Ativa')
                    ->boolean(),

                Tables\Columns\TextColumn::make('notes')
                    ->label('Observações')
                    ->limit(40)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['tenant_id']  = session('tenant_id');
                        $data['created_by'] = auth()->id();
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
