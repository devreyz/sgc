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
use Illuminate\Support\HtmlString;

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
                ->searchable()
                ->live()
                ->required(),
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
                ->required(),
            Forms\Components\Placeholder::make('allocation_progress')
                ->label('Disponibilidade da meta')
                ->content(fn (Get $get): HtmlString => $this->allocationProgress($get)),
            Forms\Components\Textarea::make('notes')->label('Observacoes')->columnSpanFull(),
        ]);
    }

    private function allocationProgress(Get $get): HtmlString
    {
        $productId = (int) ($get('product_id') ?? 0);
        if (! $productId) {
            return new HtmlString('<p class="text-sm text-gray-500 dark:text-gray-400">Selecione um produto para consultar a disponibilidade.</p>');
        }

        $summary = app(AssociateProjectLimitService::class)->productAllocationSummary(
            $this->getOwnerRecord(),
            $productId,
            filled($get('associate_id')) ? (int) $get('associate_id') : null,
        );

        if ($summary['project_maximum'] === null) {
            return new HtmlString(
                '<div class="rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">'.
                'Este produto nao possui meta geral. O limite sera individual.</div>'
            );
        }

        $maximum = (float) $summary['project_maximum'];
        $allocated = (float) $summary['allocated_to_others'];
        $available = (float) $summary['available_for_associate'];
        $allocatedPercent = $maximum > 0 ? min(100, max(0, ($allocated / $maximum) * 100)) : 0;
        $availablePercent = max(0, 100 - $allocatedPercent);
        $format = static fn (float $value): string => number_format($value, 3, ',', '.');
        $ariaLabel = sprintf(
            'Meta total %s. Reservado para outros associados %s. Disponivel %s.',
            $format($maximum),
            $format($allocated),
            $format($available),
        );

        return new HtmlString(sprintf(
            '<div class="space-y-3 rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-white/10 dark:bg-white/5" role="group" aria-label="%s">'.
                '<div class="flex items-center justify-between gap-3 text-sm">'.
                    '<span class="font-medium text-gray-600 dark:text-gray-300">Meta total</span>'.
                    '<strong class="text-gray-950 dark:text-white">%s</strong>'.
                '</div>'.
                '<div class="flex h-3 w-full overflow-hidden rounded bg-gray-200 dark:bg-gray-700" role="progressbar" aria-label="Quantidade reservada" aria-valuemin="0" aria-valuemax="%s" aria-valuenow="%s">'.
                    '<span class="h-full" style="width: %.4f%%; background-color: #d97706" title="Reservado: %s"></span>'.
                    '<span class="h-full" style="width: %.4f%%; background-color: #15803d" title="Disponivel: %s"></span>'.
                '</div>'.
                '<div class="grid grid-cols-1 gap-2 text-xs sm:grid-cols-2">'.
                    '<div class="flex items-center gap-2 text-gray-700 dark:text-gray-200"><span class="h-2.5 w-2.5 rounded-sm" style="background-color: #d97706" aria-hidden="true"></span><span>Reservado</span><strong class="ml-auto">%s</strong></div>'.
                    '<div class="flex items-center gap-2 text-gray-700 dark:text-gray-200"><span class="h-2.5 w-2.5 rounded-sm" style="background-color: #15803d" aria-hidden="true"></span><span>Disponivel</span><strong class="ml-auto">%s</strong></div>'.
                '</div>'.
            '</div>',
            e($ariaLabel),
            $format($maximum),
            $format($maximum),
            $format(min($allocated, $maximum)),
            $allocatedPercent,
            $format($allocated),
            $availablePercent,
            $format($available),
            $format($allocated),
            $format($available),
        ));
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
