<?php

namespace App\Filament\Resources\SalesProjectResource\RelationManagers;

use App\Models\BuyerRequest;
use App\Services\BuyerRequestFulfillmentService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class BuyerRequestsRelationManager extends RelationManager
{
    protected static string $relationship = 'buyerRequests';

    protected static ?string $title = 'Solicitacoes da Organizacao';

    protected static ?string $modelLabel = 'Solicitacao';

    protected static ?string $pluralModelLabel = 'Solicitacoes';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('organization_id')
                    ->label('Organizacao compradora')
                    ->relationship('organization', 'name')
                    ->disabled()
                    ->dehydrated(false),

                Forms\Components\Select::make('customer_id')
                    ->label('Unidade/destino')
                    ->relationship('customer', 'name')
                    ->disabled()
                    ->dehydrated(false),

                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options([
                        BuyerRequest::STATUS_OPEN => 'Aberta',
                        BuyerRequest::STATUS_PARTIALLY_FULFILLED => 'Parcialmente atendida',
                        BuyerRequest::STATUS_FULFILLED => 'Atendida',
                        BuyerRequest::STATUS_EXCEEDED => 'Excedida',
                        BuyerRequest::STATUS_CANCELLED => 'Cancelada',
                    ])
                    ->required(),

                Forms\Components\Toggle::make('enforce_request_limits')
                    ->label('Limitar distribuicao ao solicitado')
                    ->helperText('Quando ativo, o painel de entregas bloqueia distribuicoes acima das quantidades desta solicitacao.'),

                Forms\Components\Textarea::make('notes')
                    ->label('Observacoes')
                    ->rows(3)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->modifyQueryUsing(fn ($query) => $query->with(['organization', 'customer', 'items']))
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('organization.name')
                    ->label('Organizacao')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Unidade')
                    ->placeholder('Todas')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        BuyerRequest::STATUS_PARTIALLY_FULFILLED => 'Parcialmente atendida',
                        BuyerRequest::STATUS_FULFILLED => 'Atendida',
                        BuyerRequest::STATUS_EXCEEDED => 'Excedida',
                        BuyerRequest::STATUS_CANCELLED => 'Cancelada',
                        default => 'Aberta',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        BuyerRequest::STATUS_FULFILLED => 'success',
                        BuyerRequest::STATUS_PARTIALLY_FULFILLED => 'warning',
                        BuyerRequest::STATUS_EXCEEDED => 'danger',
                        BuyerRequest::STATUS_CANCELLED => 'gray',
                        default => 'info',
                    }),

                Tables\Columns\IconColumn::make('enforce_request_limits')
                    ->label('Limite')
                    ->boolean(),

                Tables\Columns\TextColumn::make('requested_total')
                    ->label('Solicitado')
                    ->state(fn (BuyerRequest $record): float => (float) $record->items->sum('requested_quantity'))
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 3, ',', '.')),

                Tables\Columns\TextColumn::make('distributed_total')
                    ->label('Distribuido')
                    ->state(function (BuyerRequest $record): float {
                        return (float) app(BuyerRequestFulfillmentService::class)
                            ->summaryForRequest($record)['distributed'];
                    })
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 3, ',', '.')),

                Tables\Columns\TextColumn::make('submitted_at')
                    ->label('Enviada em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        BuyerRequest::STATUS_OPEN => 'Aberta',
                        BuyerRequest::STATUS_PARTIALLY_FULFILLED => 'Parcialmente atendida',
                        BuyerRequest::STATUS_FULFILLED => 'Atendida',
                        BuyerRequest::STATUS_EXCEEDED => 'Excedida',
                        BuyerRequest::STATUS_CANCELLED => 'Cancelada',
                    ]),
                Tables\Filters\TernaryFilter::make('enforce_request_limits')
                    ->label('Limite ativo'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Ajustar'),
            ])
            ->emptyStateHeading('Nenhuma solicitacao enviada')
            ->emptyStateDescription('As solicitacoes feitas no portal externo aparecem aqui.')
            ->emptyStateIcon('heroicon-o-inbox');
    }
}
