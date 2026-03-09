<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PdvSaleResource\Pages;
use App\Filament\Traits\TenantScoped;
use App\Models\PdvSale;
use App\Services\PdvService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class PdvSaleResource extends Resource
{
    use TenantScoped;

    protected static ?string $model = PdvSale::class;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $navigationGroup = 'PDV';
    protected static ?string $modelLabel = 'Venda PDV';
    protected static ?string $pluralModelLabel = 'Vendas PDV';
    protected static ?int $navigationSort = 10;

    public static function getNavigationBadge(): ?string
    {
        $tenantId = session('tenant_id');
        if (!$tenantId) return null;
        $count = PdvSale::where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->where('is_fiado', true)
            ->whereRaw('total > amount_paid')
            ->count();
        return $count > 0 ? (string)$count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()
                ->schema([
                    Forms\Components\TextInput::make('code')->label('Código')->disabled(),
                    Forms\Components\TextInput::make('display_name')->label('Cliente')->disabled(),
                    Forms\Components\Select::make('status')->label('Status')
                        ->options(['open' => 'Aberta', 'completed' => 'Concluída', 'cancelled' => 'Cancelada'])
                        ->disabled(),
                    Forms\Components\TextInput::make('total')->label('Total')->disabled()->prefix('R$'),
                    Forms\Components\Textarea::make('notes')->label('Observações')->disabled()->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Código')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data/Hora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('display_name')
                    ->label('Cliente')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function ($q) use ($search) {
                            $q->where('customer_name', 'like', "%{$search}%")
                              ->orWhereHas('customer', fn ($r) => $r->where('name', 'like', "%{$search}%"));
                        });
                    })
                    ->placeholder('Consumidor'),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Itens')
                    ->counts('items')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('subtotal')
                    ->label('Subtotal')
                    ->money('BRL')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('discount_amount')
                    ->label('Desconto')
                    ->money('BRL')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('BRL')
                    ->weight('bold')
                    ->color('success'),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn ($state) => match($state) {
                        'completed' => 'Concluída',
                        'cancelled' => 'Cancelada',
                        'open' => 'Aberta',
                        default => ucfirst($state),
                    })
                    ->colors([
                        'success' => 'completed',
                        'danger' => 'cancelled',
                        'warning' => 'open',
                    ]),

                Tables\Columns\IconColumn::make('is_fiado')
                    ->label('Fiado')
                    ->boolean()
                    ->trueIcon('heroicon-o-clock')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('warning')
                    ->falseColor('success'),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Operador')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'completed' => 'Concluída',
                        'cancelled' => 'Cancelada',
                        'open' => 'Aberta',
                    ]),

                Tables\Filters\TernaryFilter::make('is_fiado')
                    ->label('Fiado')
                    ->trueLabel('Apenas fiado')
                    ->falseLabel('Sem fiado'),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('De'),
                        Forms\Components\DatePicker::make('until')->label('Até'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn ($q) => $q->whereDate('created_at', '>=', $data['from']))
                            ->when($data['until'], fn ($q) => $q->whereDate('created_at', '<=', $data['until']));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) $indicators[] = 'De: ' . $data['from'];
                        if ($data['until'] ?? null) $indicators[] = 'Até: ' . $data['until'];
                        return $indicators;
                    }),

                Tables\Filters\Filter::make('today')
                    ->label('Hoje')
                    ->query(fn ($query) => $query->whereDate('created_at', today()))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Ver Detalhes')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(fn (PdvSale $record): string => static::getUrl('view', ['record' => $record])),

                Tables\Actions\Action::make('print')
                    ->label('Imprimir')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn (PdvSale $record): string => route('pdv.sale.receipt', [
                        'tenant' => $record->tenant_id ? (\App\Models\Tenant::find($record->tenant_id)?->slug ?? session('tenant_slug')) : session('tenant_slug'),
                        'sale' => $record->id,
                    ]))
                    ->openUrlInNewTab()
                    ->visible(fn (PdvSale $record) => $record->status === 'completed'),

                Tables\Actions\Action::make('pay_fiado')
                    ->label('Receber Fiado')
                    ->icon('heroicon-o-banknotes')
                    ->color('warning')
                    ->visible(fn (PdvSale $record) => $record->is_fiado && $record->fiado_remaining > 0)
                    ->form([
                        Forms\Components\TextInput::make('amount')
                            ->label('Valor a Receber')
                            ->numeric()
                            ->prefix('R$')
                            ->required()
                            ->minValue(0.01),
                        Forms\Components\Select::make('payment_method')
                            ->label('Forma de Pagamento')
                            ->options([
                                'dinheiro' => 'Dinheiro',
                                'pix' => 'PIX',
                                'cartao' => 'Cartão',
                                'transferencia' => 'Transferência',
                                'cheque' => 'Cheque',
                                'outro' => 'Outro',
                            ])
                            ->required()
                            ->default('dinheiro'),
                        Forms\Components\Textarea::make('notes')->label('Observações'),
                    ])
                    ->action(function (PdvSale $record, array $data) {
                        try {
                            app(PdvService::class)->payFiado($record, (float) $data['amount'], $data['payment_method'], $data['notes'] ?? null);
                            Notification::make()->title('Pagamento registrado!')->success()->send();
                        } catch (\Exception $e) {
                            Notification::make()->title('Erro: ' . $e->getMessage())->danger()->send();
                        }
                    }),

                Tables\Actions\Action::make('cancel')
                    ->label('Cancelar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (PdvSale $record) => $record->status === 'completed')
                    ->form([
                        Forms\Components\Textarea::make('reason')->label('Motivo do Cancelamento')->required(),
                    ])
                    ->action(function (PdvSale $record, array $data) {
                        try {
                            app(PdvService::class)->cancelSale($record, $data['reason']);
                            Notification::make()->title('Venda cancelada.')->warning()->send();
                        } catch (\Exception $e) {
                            Notification::make()->title('Erro: ' . $e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateIcon('heroicon-o-shopping-bag')
            ->emptyStateHeading('Nenhuma venda encontrada')
            ->emptyStateDescription('As vendas realizadas no PDV aparecerão aqui.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPdvSales::route('/'),
            'view' => Pages\ViewPdvSale::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
