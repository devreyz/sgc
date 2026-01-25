<?php

namespace App\Filament\Resources\SalesProjectResource\RelationManagers;

use App\Enums\DeliveryStatus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class DeliveriesRelationManager extends RelationManager
{
    protected static string $relationship = 'deliveries';

    protected static ?string $title = 'Entregas';

    protected static ?string $modelLabel = 'Entrega';

    protected static ?string $pluralModelLabel = 'Entregas';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('project_demand_id')
                    ->label('Demanda / Produto')
                    ->options(fn () => $this->ownerRecord->demands->mapWithKeys(fn ($d) => [
                        $d->id => $d->product->name . ' (R$ ' . number_format($d->unit_price, 2, ',', '.') . 
                                 '/' . $d->product->unit . ') — Resta: ' . 
                                 number_format($d->remaining_quantity, 2, ',', '.') . ' ' . $d->product->unit
                    ])->toArray())
                    ->searchable()
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if (! $state) return;
                        $d = $this->ownerRecord->demands->firstWhere('id', $state);
                        if (! $d) return;
                        // Auto-preencher tudo da demanda
                        $set('product_id', $d->product_id);
                        $set('unit_price', $d->unit_price);
                        $set('quantity', min(10, (float) $d->remaining_quantity)); // Sugere 10 ou o restante
                    })
                    ->helperText('Selecione o produto/demanda deste projeto. Preço e produto serão preenchidos automaticamente.')
                    ->required(),

                Forms\Components\Hidden::make('product_id'),

                Forms\Components\Select::make('associate_id')
                    ->label('Associado Produtor')
                    ->relationship('associate', 'id')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->user->name)
                    ->searchable()
                    ->preload()
                    ->helperText('Quem entregou esta produção?')
                    ->required(),

                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('quantity')
                            ->label('Quantidade Entregue')
                            ->numeric()
                            ->required()
                            ->minValue(0.01)
                            ->suffix(fn ($get) => $get('product_id') ? 
                                \App\Models\Product::find($get('product_id'))?->unit ?? '' : '')
                            ->helperText('Digite apenas a quantidade'),

                        Forms\Components\DatePicker::make('delivery_date')
                            ->label('Data da Entrega')
                            ->required()
                            ->default(now())
                            ->maxDate(now()),
                    ]),

                Forms\Components\Placeholder::make('calculated_values')
                    ->label('Valores Calculados')
                    ->content(function ($get) {
                        $qty = $get('quantity') ?? 0;
                        $price = $get('unit_price') ?? 0;
                        $gross = $qty * $price;
                        $adminFee = $gross * ($this->ownerRecord->admin_fee_percentage / 100);
                        $net = $gross - $adminFee;
                        
                        return new \Illuminate\Support\HtmlString(
                            '<div class="text-sm space-y-1">' .
                            '<div><strong>Valor Bruto:</strong> R$ ' . number_format($gross, 2, ',', '.') . '</div>' .
                            '<div><strong>Taxa Admin (' . $this->ownerRecord->admin_fee_percentage . '%):</strong> R$ ' . 
                            number_format($adminFee, 2, ',', '.') . '</div>' .
                            '<div class="text-success-600 font-semibold"><strong>Valor Líquido (Produtor):</strong> R$ ' . 
                            number_format($net, 2, ',', '.') . '</div>' .
                            '</div>'
                        );
                    })
                    ->reactive(),

                Forms\Components\Hidden::make('unit_price'),

                Forms\Components\Select::make('quality_grade')
                    ->label('Classificação de Qualidade')
                    ->options([
                        'A' => 'A - Excelente',
                        'B' => 'B - Boa',
                        'C' => 'C - Aceitável',
                    ])
                    ->default('A'),

                Forms\Components\Textarea::make('notes')
                    ->label('Observações')
                    ->rows(2)
                    ->placeholder('Observações sobre a entrega (opcional)')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->defaultSort('delivery_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('delivery_date')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('associate.user.name')
                    ->label('Associado')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('product.name')
                    ->label('Produto')
                    ->searchable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Quantidade')
                    ->formatStateUsing(fn ($state, $record): string => 
                        number_format($state, 2, ',', '.') . ' ' . $record->product->unit
                    ),

                Tables\Columns\TextColumn::make('unit_price')
                    ->label('Preço/Un')
                    ->money('BRL'),

                Tables\Columns\TextColumn::make('gross_value')
                    ->label('Bruto')
                    ->money('BRL')
                    ->tooltip('Valor bruto (quantidade × preço)'),

                Tables\Columns\TextColumn::make('admin_fee_amount')
                    ->label('Taxa Admin')
                    ->money('BRL')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('net_value')
                    ->label('Líquido')
                    ->money('BRL')
                    ->tooltip('Valor líquido para o produtor')
                    ->weight('bold')
                    ->color('success'),

                Tables\Columns\TextColumn::make('quality_grade')
                    ->label('Qualidade')
                    ->badge()
                    ->colors([
                        'success' => 'A',
                        'warning' => 'B',
                        'danger' => 'C',
                    ])
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (DeliveryStatus $state): string => $state->label())
                    ->color(fn (DeliveryStatus $state): string => $state->color()),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(DeliveryStatus::class),
                Tables\Filters\SelectFilter::make('associate_id')
                    ->label('Associado')
                    ->relationship('associate.user', 'name'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Nova Entrega')
                    ->icon('heroicon-o-plus')
                    ->mutateFormDataUsing(function (array $data): array {
                        // Status inicial sempre pending
                        $data['status'] = DeliveryStatus::PENDING;
                        $data['received_by'] = auth()->id();
                        $data['sales_project_id'] = $this->ownerRecord->id;
                        
                        // Os valores serão calculados automaticamente pelo model boot
                        return $data;
                    })
                    ->successNotificationTitle('Entrega registrada com sucesso!')
                    ->after(function () {
                        // Recarregar para ver quantidade atualizada nas demands
                        $this->ownerRecord->refresh();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Aprovar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Aprovar Entrega')
                    ->modalDescription('Ao aprovar, o valor líquido será creditado ao produtor e a quantidade será contabilizada.')
                    ->action(function ($record) {
                        $record->update([
                            'status' => DeliveryStatus::APPROVED,
                            'approved_by' => auth()->id(),
                            'approved_at' => now(),
                        ]);
                        
                        // Forçar atualização da demanda
                        if ($record->projectDemand) {
                            $record->projectDemand->updateDeliveredQuantity();
                            $record->projectDemand->refresh();
                        }
                        
                        // Forçar atualização do projeto
                        $record->salesProject->refresh();
                    })
                    ->successNotificationTitle('Entrega aprovada!')
                    ->after(fn () => $this->ownerRecord->refresh())
                    ->visible(fn ($record): bool => $record->status === DeliveryStatus::PENDING),

                Tables\Actions\Action::make('reject')
                    ->label('Rejeitar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Motivo da Rejeição')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(fn ($record, array $data) => $record->update([
                        'status' => DeliveryStatus::REJECTED,
                        'notes' => ($record->notes ? $record->notes . "\n\n" : '') . 
                                  'REJEITADO: ' . $data['rejection_reason'],
                    ]))
                    ->successNotificationTitle('Entrega rejeitada')
                    ->visible(fn ($record): bool => $record->status === DeliveryStatus::PENDING),

                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn ($record): bool => $record->status === DeliveryStatus::PENDING),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn ($record): bool => $record->status === DeliveryStatus::PENDING),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()->isSuperAdmin()),
                ]),
            ])
            ->emptyStateHeading('Nenhuma entrega registrada')
            ->emptyStateDescription('Clique em "Nova Entrega" para registrar a primeira entrega de produção.')
            ->emptyStateIcon('heroicon-o-truck');
    }
}
