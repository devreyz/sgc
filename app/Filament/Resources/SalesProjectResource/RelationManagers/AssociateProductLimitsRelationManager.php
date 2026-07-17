<?php

namespace App\Filament\Resources\SalesProjectResource\RelationManagers;

use App\Models\Associate;
use App\Models\ProjectAssociateProductLimit;
use App\Services\AssociateProjectLimitService;
use Filament\Forms;
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
                ->label('Produto')->relationship('product', 'name')->searchable()->preload()->required(),
            Forms\Components\TextInput::make('max_quantity')
                ->label('Quantidade maxima')->numeric()->minValue(0.001)->required(),
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
