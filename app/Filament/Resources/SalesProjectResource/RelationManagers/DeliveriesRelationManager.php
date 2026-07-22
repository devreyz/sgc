<?php

namespace App\Filament\Resources\SalesProjectResource\RelationManagers;

use App\Enums\BillingStatus;
use App\Enums\DeliveryStatus;
use App\Models\ProductionDelivery;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DeliveriesRelationManager extends RelationManager
{
    protected static string $relationship = 'deliveries';

    protected static ?string $title = 'Entregas e distribuicoes';

    protected static ?string $modelLabel = 'Registro';

    protected static ?string $pluralModelLabel = 'Registros';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'associate:id,tenant_id,user_id,nickname,property_name',
                'product:id,name,unit',
                'customer:id,name,trade_name',
                'parentDelivery:id',
            ]))
            ->defaultSort('delivery_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('delivery_date')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('record_type')
                    ->label('Tipo')
                    ->state(fn (ProductionDelivery $record): string => $record->parent_delivery_id
                        ? 'Distribuicao'
                        : 'Recepcao')
                    ->badge()
                    ->color(fn (ProductionDelivery $record): string => $record->parent_delivery_id ? 'success' : 'gray'),

                Tables\Columns\TextColumn::make('associate_name')
                    ->label('Associado')
                    ->state(fn (ProductionDelivery $record): string => $record->associate?->display_name ?? 'Nao identificado')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->whereHas('associate', fn (Builder $associate) => $associate
                            ->where('nickname', 'like', "%{$search}%")
                            ->orWhere('property_name', 'like', "%{$search}%"))),

                Tables\Columns\TextColumn::make('product.name')
                    ->label('Produto')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Destino')
                    ->formatStateUsing(fn ($state, ProductionDelivery $record): string => $record->parent_delivery_id
                        ? ($record->customer?->trade_name ?: $record->customer?->name ?: 'Nao identificado')
                        : 'Entrada fisica')
                    ->color(fn (ProductionDelivery $record): string => $record->parent_delivery_id ? 'primary' : 'gray'),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Quantidade')
                    ->formatStateUsing(fn ($state, ProductionDelivery $record): string =>
                        number_format((float) $state, 3, ',', '.').' '.($record->product?->unit ?? 'un'))
                    ->alignEnd()
                    ->sortable(),

                Tables\Columns\TextColumn::make('unit_price')
                    ->label('Preco unitario')
                    ->state(fn (ProductionDelivery $record): ?float => $record->parent_delivery_id
                        ? (float) $record->unit_price
                        : null)
                    ->money('BRL')
                    ->placeholder('-')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('gross_value')
                    ->label('Valor bruto')
                    ->state(fn (ProductionDelivery $record): ?float => $record->parent_delivery_id
                        ? (float) $record->gross_value
                        : null)
                    ->money('BRL')
                    ->placeholder('-')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge(),

                Tables\Columns\TextColumn::make('billing_status')
                    ->label('Financeiro')
                    ->state(fn (ProductionDelivery $record): string => $record->parent_delivery_id
                        ? ($record->billing_status?->getLabel() ?? 'Pendente')
                        : 'Nao se aplica')
                    ->badge()
                    ->color(fn (ProductionDelivery $record): string => match ($record->billing_status) {
                        BillingStatus::PAID => 'success',
                        BillingStatus::BILLED => 'warning',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('record_type')
                    ->label('Tipo')
                    ->options([
                        'receptions' => 'Recepcoes',
                        'distributions' => 'Distribuicoes',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'receptions' => $query->whereNull('parent_delivery_id'),
                            'distributions' => $query->whereNotNull('parent_delivery_id'),
                            default => $query,
                        };
                    }),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(DeliveryStatus::class),
            ])
            ->emptyStateHeading('Nenhum registro neste projeto')
            ->emptyStateIcon('heroicon-o-truck');
    }
}
