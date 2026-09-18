<?php

namespace App\Filament\Resources\ServiceVersionResource\RelationManagers;

use App\Models\ServiceProvider;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ProviderRatesRelationManager extends RelationManager
{
    protected static string $relationship = 'providerRates';

    protected static ?string $title = 'Remuneração individual por prestador';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('service_provider_id')->label('Prestador habilitado')->options(fn () => ServiceProvider::query()->where('status', true)->whereHas('services', fn ($query) => $query->whereKey($this->getOwnerRecord()->service_id))->orderBy('name')->pluck('name', 'id'))->searchable()->required()->unique('service_provider_version_rates', 'service_provider_id', ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('tenant_id', session('tenant_id'))->where('service_version_id', $this->getOwnerRecord()->id)),
            Forms\Components\Placeholder::make('override_warning')->label('Como funciona')->content('Esta exceção altera somente o valor pago a este prestador. A fórmula e a fonte da quantidade continuam sendo as definidas na versão do serviço.')->columnSpanFull(),
            Forms\Components\Hidden::make('calculation_method')->default(fn () => $this->getOwnerRecord()->provider_pricing_method)->afterStateHydrated(fn ($component) => $component->state($this->getOwnerRecord()->provider_pricing_method)),
            Forms\Components\TextInput::make('rate')->label('Tarifa individual por unidade')->numeric()->prefix('R$')->minValue(0.0001)->required(fn () => $this->getOwnerRecord()->provider_pricing_method === 'quantity_x_rate')->visible(fn () => $this->getOwnerRecord()->provider_pricing_method === 'quantity_x_rate'),
            Forms\Components\TextInput::make('fixed_amount')->label('Valor fixo individual por execução')->numeric()->prefix('R$')->minValue(0.01)->required(fn () => $this->getOwnerRecord()->provider_pricing_method === 'fixed')->visible(fn () => $this->getOwnerRecord()->provider_pricing_method === 'fixed'),
            Forms\Components\TextInput::make('percentage')->label('Percentual individual da cobrança')->numeric()->suffix('%')->minValue(0.0001)->maxValue(100)->required(fn () => $this->getOwnerRecord()->provider_pricing_method === 'percent_of_base')->visible(fn () => $this->getOwnerRecord()->provider_pricing_method === 'percent_of_base'),
            Forms\Components\Toggle::make('active')->label('Ativa')->default(true),
            Forms\Components\Textarea::make('notes')->label('Observações')->columnSpanFull(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('provider.name')->label('Prestador')->searchable(),
            Tables\Columns\TextColumn::make('calculation_method')->label('Fórmula da versão')->badge()->formatStateUsing(fn (string $state) => match ($state) { 'fixed' => 'Valor fixo', 'quantity_x_rate' => 'Quantidade × tarifa', 'percent_of_base' => 'Percentual da cobrança', default => $state }),
            Tables\Columns\TextColumn::make('rate')->label('Tarifa')->money('BRL')->placeholder('—'),
            Tables\Columns\TextColumn::make('fixed_amount')->label('Fixo')->money('BRL')->placeholder('—'),
            Tables\Columns\TextColumn::make('percentage')->label('%')->suffix('%')->placeholder('—'),
            Tables\Columns\IconColumn::make('active')->label('Ativa')->boolean(),
        ])->headerActions([
            Tables\Actions\CreateAction::make()->visible(fn () => $this->getOwnerRecord()->status === 'draft'),
        ])->actions([
            Tables\Actions\EditAction::make()->visible(fn () => $this->getOwnerRecord()->status === 'draft'),
            Tables\Actions\DeleteAction::make()->visible(fn () => $this->getOwnerRecord()->status === 'draft'),
        ]);
    }
}
