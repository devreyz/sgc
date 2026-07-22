<?php

namespace App\Filament\Resources\SalesProjectResource\RelationManagers;

use App\Models\Associate;
use App\Models\Product;
use App\Models\ProjectAssociateProductLimit;
use App\Services\AssociateProjectLimitService;
use Filament\Forms;
use Filament\Forms\Get;
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
        return $form->schema([
            Forms\Components\Select::make('associate_id')
                ->label('Associado')
                ->options(fn () => Associate::where('tenant_id', session('tenant_id'))
                    ->get()->mapWithKeys(fn (Associate $associate) => [$associate->id => $associate->display_name]))
                ->searchable()->required(),
            Forms\Components\Select::make('product_id')
                ->label('Produto')
                ->options(fn (): array => Product::query()
                    ->where('tenant_id', $this->getOwnerRecord()->tenant_id)
                    ->where('status', true)
                    ->whereIn('id', app(\App\Services\ProjectDistributionCustomerService::class)
                        ->pricedProductIds($this->getOwnerRecord()))
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->searchable()
                ->preload()
                ->live()
                ->required(),
            Forms\Components\TextInput::make('max_quantity')
                ->label('Quantidade maxima')
                ->numeric()
                ->minValue(0.001)
                ->helperText(function (Get $get): string {
                    $productId = (int) ($get('product_id') ?? 0);
                    if (! $productId) {
                        return 'Selecione um produto para consultar a disponibilidade.';
                    }

                    $summary = app(AssociateProjectLimitService::class)->productAllocationSummary(
                        $this->getOwnerRecord(),
                        $productId,
                        filled($get('associate_id')) ? (int) $get('associate_id') : null,
                    );

                    if ($summary['project_maximum'] === null) {
                        return 'Este produto nao possui meta geral; o limite sera individual.';
                    }

                    return sprintf(
                        'Meta do projeto: %s | Comprometido com os demais: %s | Disponivel: %s',
                        number_format($summary['project_maximum'], 3, ',', '.'),
                        number_format($summary['allocated_to_others'], 3, ',', '.'),
                        number_format($summary['available_for_associate'], 3, ',', '.')
                    );
                })
                ->required(),
            Forms\Components\Textarea::make('notes')->label('Observacoes')->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->where('status', 'active'))
            ->columns([
                Tables\Columns\TextColumn::make('associate.display_name')->label('Associado')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('product.name')->label('Produto')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('max_quantity')->label('Limite')->numeric(3)->sortable(),
                Tables\Columns\TextColumn::make('reference_unit_price')->label('Preco de referencia')->money('BRL'),
                Tables\Columns\TextColumn::make('status')->label('Status')->badge(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn (): bool => app(AssociateProjectLimitService::class)
                        ->projectMode($this->getOwnerRecord())['allows_product_limits'])
                    ->using(function (array $data): ProjectAssociateProductLimit {
                        $project = $this->getOwnerRecord();
                        $associate = Associate::where('tenant_id', $project->tenant_id)->findOrFail($data['associate_id']);

                        return app(AssociateProjectLimitService::class)->setProductLimit(
                            $project, $associate, (int) $data['product_id'],
                            (float) $data['max_quantity'], $data['notes'] ?? null
                        );
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->using(function (ProjectAssociateProductLimit $record, array $data): ProjectAssociateProductLimit {
                        $project = $this->getOwnerRecord();
                        $associate = Associate::where('tenant_id', $project->tenant_id)->findOrFail($data['associate_id']);

                        return app(AssociateProjectLimitService::class)->setProductLimit(
                            $project, $associate, (int) $data['product_id'],
                            (float) $data['max_quantity'], $data['notes'] ?? null
                        );
                    }),
                Tables\Actions\Action::make('archive')
                    ->label('Arquivar')->icon('heroicon-o-archive-box')->requiresConfirmation()
                    ->action(fn (ProjectAssociateProductLimit $record) => $record->update([
                        'status' => 'archived',
                        'archived_at' => now(),
                        'archived_by' => auth()->id(),
                        'archive_reason' => 'Arquivado manualmente no projeto.',
                    ])),
            ])
            ->bulkActions([]);
    }
}
