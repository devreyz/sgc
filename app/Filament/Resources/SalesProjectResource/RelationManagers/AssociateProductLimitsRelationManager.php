<?php

namespace App\Filament\Resources\SalesProjectResource\RelationManagers;

use App\Models\Associate;
use App\Models\Product;
use App\Models\ProjectAssociateProductLimit;
use App\Models\TenantUser;
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
                ->maxValue(fn (Get $get, ?ProjectAssociateProductLimit $record): ?float => $this->quantityCapacity($get, $record)['maximum'])
                ->live(debounce: 400)
                ->required(),
            Forms\Components\Placeholder::make('allocation_progress')
                ->label('Disponibilidade da meta')
                ->content(fn (Get $get): HtmlString => $this->allocationProgress($get)),
            Forms\Components\Placeholder::make('simulated_value')
                ->label('Valor e quantidade sugerida')
                ->content(fn (Get $get, ?ProjectAssociateProductLimit $record): HtmlString => $this->simulatedValue($get, $record)),
            Forms\Components\Textarea::make('notes')->label('Observacoes')->columnSpanFull(),
        ]);
    }

    private function simulatedValue(Get $get, ?ProjectAssociateProductLimit $record): HtmlString
    {
        $associateId = (int) ($get('associate_id') ?? 0);
        $productId = (int) ($get('product_id') ?? 0);
        $quantity = max(0, (float) ($get('max_quantity') ?? 0));
        if (! $associateId || ! $productId) {
            return new HtmlString('<p class="text-sm text-gray-500 dark:text-gray-400">Selecione associado e produto para simular o valor.</p>');
        }

        $capacity = $this->quantityCapacity($get, $record);
        if ($capacity['price'] === null) {
            return new HtmlString('<p class="text-sm text-danger-600">Produto sem preco na tabela do cliente.</p>');
        }

        $price = (float) $capacity['price'];
        $value = $quantity * $price;
        $associateTotal = $capacity['associate_planned_without_current'] + $value;
        $projectTotal = $capacity['project_planned_without_current'] + $value;
        $associateCeiling = $capacity['associate_ceiling'];
        $projectCeiling = $capacity['project_ceiling'];
        $hasExcess = ($associateCeiling !== null && $associateTotal > $associateCeiling + .005)
            || ($projectCeiling !== null && $projectTotal > $projectCeiling + .005)
            || ($capacity['maximum'] !== null && $quantity > $capacity['maximum'] + .0005);
        $money = static fn (float $number): string => 'R$ '.number_format($number, 2, ',', '.');
        $formatQuantity = static fn (float $number): string => number_format($number, 3, ',', '.');
        $unit = e((string) ($capacity['unit'] ?: 'un'));

        $capacityRows = '';
        if ($capacity['project_financial_quantity'] !== null) {
            $capacityRows .= '<div style="display:flex;justify-content:space-between;gap:10px"><span>Saldo financeiro do projeto</span><strong>'.$money($capacity['project_financial_remaining']).'</strong></div>';
            $capacityRows .= '<div style="display:flex;justify-content:space-between;gap:10px"><span>Maximo pelo saldo do projeto</span><strong>'.$formatQuantity($capacity['project_financial_quantity']).' '.$unit.'</strong></div>';
        }
        if ($capacity['associate_financial_quantity'] !== null) {
            $capacityRows .= '<div style="display:flex;justify-content:space-between;gap:10px"><span>Saldo financeiro do associado</span><strong>'.$money($capacity['associate_financial_remaining']).'</strong></div>';
            $capacityRows .= '<div style="display:flex;justify-content:space-between;gap:10px"><span>Maximo pelo teto do associado</span><strong>'.$formatQuantity($capacity['associate_financial_quantity']).' '.$unit.'</strong></div>';
        }
        if ($capacity['product_quantity'] !== null) {
            $capacityRows .= '<div style="display:flex;justify-content:space-between;gap:10px"><span>Maximo pela meta do produto</span><strong>'.$formatQuantity($capacity['product_quantity']).' '.$unit.'</strong></div>';
        }

        return new HtmlString(
            '<div style="display:grid;gap:9px;padding:12px;border:1px solid '.($hasExcess ? '#fecaca' : '#dbe4dd').';border-radius:8px;background:'.($hasExcess ? '#fef2f2' : '#f8faf9').'">'.
                '<div style="display:flex;justify-content:space-between;gap:10px"><span>Preco unitario</span><strong>'.$money($price).'</strong></div>'.
                '<div style="display:flex;justify-content:space-between;gap:10px"><span>Este limite</span><strong>'.$money($value).'</strong></div>'.
                '<div style="display:flex;justify-content:space-between;gap:10px"><span>Total do associado</span><strong>'.$money($associateTotal).($associateCeiling === null ? '' : ' / '.$money($associateCeiling)).'</strong></div>'.
                '<div style="display:flex;justify-content:space-between;gap:10px"><span>Total do projeto</span><strong>'.$money($projectTotal).($projectCeiling === null ? '' : ' / '.$money($projectCeiling)).'</strong></div>'.
                '<div style="height:1px;background:#dbe4dd;margin:2px 0"></div>'.
                $capacityRows.
                ($capacity['maximum'] === null
                    ? '<div style="padding:9px 10px;border-radius:7px;background:#eef2ff;color:#3730a3;font-size:12px;font-weight:700">Sem teto calculavel para este produto.</div>'
                    : '<div style="padding:9px 10px;border-radius:7px;background:#dcfce7;color:#166534;font-size:12px;font-weight:700">Quantidade maxima sugerida: '.$formatQuantity($capacity['maximum']).' '.$unit.'</div>').
                ($hasExcess ? '<div style="color:#b91c1c;font-size:12px;font-weight:700">Reduza a quantidade para respeitar os tetos financeiros.</div>' : '').
            '</div>'
        );
    }

    /**
     * Calcula a capacidade total da cota atual, excluindo o proprio registro
     * durante a edicao para que seu valor anterior nao seja contado duas vezes.
     *
     * @return array<string, float|string|null>
     */
    private function quantityCapacity(Get $get, ?ProjectAssociateProductLimit $record): array
    {
        $project = $this->getOwnerRecord();
        $associateId = (int) ($get('associate_id') ?? 0);
        $productId = (int) ($get('product_id') ?? 0);
        $empty = [
            'price' => null,
            'unit' => null,
            'maximum' => null,
            'project_financial_quantity' => null,
            'project_financial_remaining' => null,
            'associate_financial_quantity' => null,
            'associate_financial_remaining' => null,
            'product_quantity' => null,
            'project_planned_without_current' => 0.0,
            'associate_planned_without_current' => 0.0,
            'project_ceiling' => null,
            'associate_ceiling' => null,
        ];

        if (! $associateId || ! $productId) {
            return $empty;
        }

        $associate = Associate::query()
            ->where('tenant_id', $project->tenant_id)
            ->find($associateId);
        $product = Product::query()
            ->where('tenant_id', $project->tenant_id)
            ->find($productId);
        if (! $associate || ! $product) {
            return $empty;
        }

        $service = app(AssociateProjectLimitService::class);
        $price = $service->projectMode($project)['customer']?->priceTable?->priceFor($productId);
        if ($price === null || (float) $price <= 0) {
            return array_replace($empty, ['unit' => $product->unit]);
        }

        $recordId = $record?->exists ? $record->id : null;
        $projectPlanned = $service->simulatedLimitValue($project, null, $recordId);
        $associatePlanned = $service->simulatedLimitValue($project, $associateId, $recordId);
        $projectCeiling = (float) $project->total_value > 0 ? (float) $project->total_value : null;
        $associateCeiling = $service->financialLimit($project, $associate);
        $allocation = $service->productAllocationSummary($project, $productId, $associateId);

        $projectFinancialQuantity = $projectCeiling === null
            ? null
            : max(0, ($projectCeiling - $projectPlanned) / (float) $price);
        $projectFinancialRemaining = $projectCeiling === null
            ? null
            : max(0, $projectCeiling - $projectPlanned);
        $associateFinancialQuantity = $associateCeiling === null
            ? null
            : max(0, ($associateCeiling - $associatePlanned) / (float) $price);
        $associateFinancialRemaining = $associateCeiling === null
            ? null
            : max(0, $associateCeiling - $associatePlanned);
        $productQuantity = $allocation['project_maximum'] === null
            ? null
            : (float) $allocation['available_for_associate'];
        $caps = collect([$projectFinancialQuantity, $associateFinancialQuantity, $productQuantity])
            ->filter(fn ($value) => $value !== null);
        $maximum = $caps->isEmpty() ? null : floor((float) $caps->min() * 1000) / 1000;

        return [
            'price' => (float) $price,
            'unit' => $product->unit,
            'maximum' => $maximum,
            'project_financial_quantity' => $projectFinancialQuantity === null ? null : floor($projectFinancialQuantity * 1000) / 1000,
            'project_financial_remaining' => $projectFinancialRemaining,
            'associate_financial_quantity' => $associateFinancialQuantity === null ? null : floor($associateFinancialQuantity * 1000) / 1000,
            'associate_financial_remaining' => $associateFinancialRemaining,
            'product_quantity' => $productQuantity === null ? null : floor($productQuantity * 1000) / 1000,
            'project_planned_without_current' => $projectPlanned,
            'associate_planned_without_current' => $associatePlanned,
            'project_ceiling' => $projectCeiling,
            'associate_ceiling' => $associateCeiling,
        ];
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
        $allocated = min($maximum, (float) $summary['allocated_to_others']);
        $available = max(0, (float) $summary['available_for_associate']);
        $requested = max(0, (float) ($get('max_quantity') ?? 0));
        $current = min($requested, $available);
        $free = max(0, $available - $current);
        $excess = max(0, $requested - $available);
        $allocatedPercent = $maximum > 0 ? ($allocated / $maximum) * 100 : 0;
        $currentPercent = $maximum > 0 ? ($current / $maximum) * 100 : 0;
        $freePercent = max(0, 100 - $allocatedPercent - $currentPercent);
        $format = static fn (float $value): string => number_format($value, 3, ',', '.');
        $ariaLabel = sprintf(
            'Meta total %s. Reservado para outros associados %s. Limite atual %s. Saldo livre %s.',
            $format($maximum),
            $format($allocated),
            $format($current),
            $format($free),
        );

        return new HtmlString(sprintf(
            '<div role="group" aria-label="%s" style="display:grid;gap:12px;padding:14px;border:1px solid #dbe4dd;border-radius:8px;background:#f8faf9">'.
                '<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;font-size:13px;color:#475569">'.
                    '<span style="font-weight:600">Meta total</span>'.
                    '<strong style="color:#0f172a;font-size:14px">%s</strong>'.
                '</div>'.
                '<div style="display:flex;width:100%%;height:12px;overflow:hidden;border-radius:6px;background:#e2e8f0" role="progressbar" aria-label="Distribuicao da meta" aria-valuemin="0" aria-valuemax="%s" aria-valuenow="%s">'.
                    '<span style="display:block;width:%.4f%%;height:100%%;background:#d97706" title="Outros associados: %s"></span>'.
                    '<span style="display:block;width:%.4f%%;height:100%%;background:#15803d" title="Este limite: %s"></span>'.
                    '<span style="display:block;width:%.4f%%;height:100%%;background:#bbf7d0" title="Saldo livre: %s"></span>'.
                '</div>'.
                '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:8px;font-size:12px">'.
                    '<div style="display:flex;align-items:center;gap:7px;color:#475569"><span style="width:10px;height:10px;border-radius:3px;background:#d97706"></span><span>Outros</span><strong style="margin-left:auto;color:#0f172a">%s</strong></div>'.
                    '<div style="display:flex;align-items:center;gap:7px;color:#475569"><span style="width:10px;height:10px;border-radius:3px;background:#15803d"></span><span>Este limite</span><strong style="margin-left:auto;color:#0f172a">%s</strong></div>'.
                    '<div style="display:flex;align-items:center;gap:7px;color:#475569"><span style="width:10px;height:10px;border-radius:3px;background:#bbf7d0"></span><span>Livre</span><strong style="margin-left:auto;color:#0f172a">%s</strong></div>'.
                '</div>'.
                '%s'.
            '</div>',
            e($ariaLabel),
            $format($maximum),
            $format($maximum),
            $format(min($maximum, $allocated + $current)),
            $allocatedPercent,
            $format($allocated),
            $currentPercent,
            $format($current),
            $freePercent,
            $format($free),
            $format($allocated),
            $format($current),
            $format($free),
            $excess > 0
                ? '<div style="padding:9px 10px;border:1px solid #fecaca;border-radius:7px;background:#fef2f2;color:#b91c1c;font-size:12px;font-weight:600">Reduza '.$format($excess).' para respeitar a meta disponível.</div>'
                : '',
        ));
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->where('status', 'active'))
            ->description(fn (): HtmlString => $this->budgetSummary())
            ->columns([
                Tables\Columns\TextColumn::make('associate.display_name')
                    ->label('Associado')
                    ->searchable(query: static function ($query, string $search) {
                        $tenantId = (int) session('tenant_id');

                        return $query->whereHas('associate', static function ($associateQuery) use ($tenantId, $search): void {
                            $associateQuery->whereIn('user_id', TenantUser::query()
                                ->where('tenant_id', $tenantId)
                                ->where('tenant_name', 'like', '%'.$search.'%')
                                ->select('user_id'));
                        });
                    }),
                Tables\Columns\TextColumn::make('product.name')->label('Produto')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('max_quantity')->label('Limite')->numeric(3)->sortable(),
                Tables\Columns\TextColumn::make('reference_unit_price')->label('Preco de referencia')->money('BRL'),
                Tables\Columns\TextColumn::make('estimated_value')
                    ->label('Valor planejado')
                    ->state(fn (ProjectAssociateProductLimit $record): float => (float) $record->max_quantity * (float) $record->reference_unit_price)
                    ->money('BRL')
                    ->sortable(false),
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

    private function budgetSummary(): HtmlString
    {
        $summary = app(AssociateProjectLimitService::class)
            ->simulatedBudgetSummary($this->getOwnerRecord());
        $money = static fn (?float $number): string => $number === null
            ? 'Sem teto financeiro'
            : 'R$ '.number_format($number, 2, ',', '.');

        return new HtmlString(
            '<div style="display:flex;flex-wrap:wrap;gap:8px 18px;padding:10px 12px;border:1px solid #dbe4dd;border-radius:8px;background:#f8faf9;font-size:13px">'.
                '<span>Planejado: <strong>'.$money($summary['planned_value']).'</strong></span>'.
                '<span>Teto do projeto: <strong>'.$money($summary['ceiling']).'</strong></span>'.
                '<span>Disponivel: <strong>'.$money($summary['remaining']).'</strong></span>'.
            '</div>'
        );
    }
}
