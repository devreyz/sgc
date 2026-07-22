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
                ->live(debounce: 400)
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
