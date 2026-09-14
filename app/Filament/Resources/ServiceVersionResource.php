<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceVersionResource\Pages;
use App\Filament\Resources\ServiceVersionResource\RelationManagers;
use App\Filament\Traits\TenantScoped;
use App\Models\ServiceVersion;
use App\Services\Services\ServiceCatalogService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ServiceVersionResource extends Resource
{
    use TenantScoped;

    protected static ?string $model = ServiceVersion::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';

    protected static ?string $navigationGroup = 'Serviços';

    protected static ?string $modelLabel = 'Versão de serviço';

    protected static ?string $pluralModelLabel = 'Versões e preços';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return auth()->user()?->checkPermissionTo('manage_service_catalog') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return static::canViewAny() && $record->status === 'draft' && (int) $record->tenant_id === (int) session('tenant_id');
    }

    public static function canDelete($record): bool
    {
        return static::canViewAny() && $record->status === 'draft' && (int) $record->tenant_id === (int) session('tenant_id');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identificação')
                ->schema([
                    Forms\Components\Select::make('service_id')->label('Serviço')->relationship('service', 'name')->disabled()->dehydrated(),
                    Forms\Components\TextInput::make('version')->label('Versão')->disabled(),
                    Forms\Components\TextInput::make('unit')->label('Unidade de medição')->required()->maxLength(30),
                    Forms\Components\Select::make('review_mode')->label('Conferência')->options(['automatic' => 'Automática', 'manual' => 'Manual'])->required(),
                    Forms\Components\Toggle::make('allow_provider_create_order')->label('Prestador habilitado pode criar ordem'),
                    Forms\Components\Select::make('execution_config.quantity_field')->options(fn (?ServiceVersion $record) => static::numericFieldOptions($record))->searchable()->label('Campo que informa a quantidade principal')->helperText('Selecione um campo numérico obrigatório. Ex.: horas, dias ou km. Vazio: quantidade padrão.'),
                    Forms\Components\Select::make('execution_config.meter_start_field')->options(fn (?ServiceVersion $record) => static::numericFieldOptions($record))->searchable()->label('Campo da medição inicial')->helperText('Para calcular pela diferença de medidores, selecione os dois campos e deixe a quantidade principal vazia.'),
                    Forms\Components\Select::make('execution_config.meter_end_field')->options(fn (?ServiceVersion $record) => static::numericFieldOptions($record))->searchable()->label('Campo da medição final'),
                ])->columns(2),
            Forms\Components\Section::make('Cobrança da organização')
                ->description('Serviço interno: desative a cobrança. A remuneração do prestador continua independente.')
                ->schema([
                    Forms\Components\Toggle::make('receivable_enabled')->label('Gerar conta a receber')->live(),
                    Forms\Components\Select::make('execution_config.customer_quantity_field')->options(fn (?ServiceVersion $record) => static::numericFieldOptions($record))->searchable()->label('Quantidade para cobrar')->helperText('Selecione o campo, por exemplo horas. Vazio: usa a quantidade principal.'),
                    Forms\Components\Select::make('customer_pricing_method')->label('Cálculo')->options(['fixed' => 'Valor fixo', 'quantity_x_rate' => 'Quantidade × tarifa', 'percent_of_base' => 'Percentual da base']),
                    Forms\Components\TextInput::make('customer_rate')->label('Valor, tarifa ou base')->numeric()->prefix('R$')->minValue(0.0001)->required(fn (Get $get) => $get('receivable_enabled')),
                    Forms\Components\TextInput::make('customer_percentage')->label('Percentual')->numeric()->suffix('%')->minValue(0.0001)->maxValue(100)->required(fn (Get $get) => $get('receivable_enabled') && $get('customer_pricing_method') === 'percent_of_base'),
                ])->columns(2),
            Forms\Components\Section::make('Remuneração do prestador')
                ->description('Por hora: quantidade horas × tarifa/hora. Por diária: campo dias × tarifa/dia. Valor fixo: uma quantia por execução. PIX ou dinheiro é escolhido ao registrar o pagamento.')
                ->schema([
                    Forms\Components\Toggle::make('payable_enabled')->label('Gerar conta a pagar ao prestador')->live(),
                    Forms\Components\Select::make('execution_config.provider_quantity_field')->options(fn (?ServiceVersion $record) => static::numericFieldOptions($record))->searchable()->label('Quantidade para remunerar')->helperText('Selecione a quantidade. Pode ser dias enquanto a cobrança usa horas. Vazio: quantidade principal.'),
                    Forms\Components\TextInput::make('execution_config.provider_unit')->label('Unidade da remuneração')->placeholder('hora, dia, km, unidade')->maxLength(30),
                    Forms\Components\Select::make('provider_pricing_method')->label('Cálculo padrão')->options(['fixed' => 'Valor fixo', 'quantity_x_rate' => 'Quantidade × tarifa', 'percent_of_base' => 'Percentual da cobrança']),
                    Forms\Components\TextInput::make('default_provider_rate')->label('Valor/tarifa padrão')->numeric()->prefix('R$')->minValue(0),
                    Forms\Components\TextInput::make('provider_percentage')->label('Percentual padrão')->numeric()->suffix('%')->minValue(0)->maxValue(100),
                ])->columns(2),
            Forms\Components\Section::make('Taxas, descontos e adicionais')->description('Cada regra usa um valor configurado ou uma variável numérica obrigatória do fluxo. Percentuais usam o valor base, sem juros sobre outras taxas.')->collapsed()->schema([
                Forms\Components\Repeater::make('financial_config.rules')->label('Regras financeiras')->default([])->schema([
                    Forms\Components\TextInput::make('description')->label('Descrição')->required()->maxLength(191),
                    Forms\Components\Select::make('direction')->label('Aplicar em')->options(['receivable' => 'Cobrança do beneficiário', 'payable' => 'Remuneração do prestador'])->required(),
                    Forms\Components\Select::make('method')->label('Como calcular')->options(['fixed_addition' => 'Valor adicional', 'fixed_deduction' => 'Desconto em valor', 'percent_addition' => 'Taxa percentual', 'percent_deduction' => 'Desconto percentual', 'quantity_x_rate' => 'Quantidade × valor'])->required(),
                    Forms\Components\Select::make('effect')->label('Efeito')->options(['add' => 'Acrescentar', 'subtract' => 'Descontar'])->default('add')->required(),
                    Forms\Components\TextInput::make('value')->label('Valor ou tarifa configurada')->numeric()->minValue(0)->default(0),
                    Forms\Components\TextInput::make('percentage')->label('Percentual configurado')->numeric()->minValue(0)->maxValue(100)->default(0),
                    Forms\Components\Select::make('value_field')->options(fn (?ServiceVersion $record) => static::numericFieldOptions($record))->searchable()->label('Ou usar o valor do campo')->helperText('Chave de um campo numérico obrigatório. Ex.: desconto, litros. Substitui o valor/percentual configurado.'),
                    Forms\Components\Select::make('quantity_field')->options(fn (?ServiceVersion $record) => static::numericFieldOptions($record))->searchable()->label('Campo da quantidade')->helperText('Opcional, para quantidade × valor. Ex.: litros ou km.'),
                ])->columns(2)->addActionLabel('Adicionar taxa ou desconto')->itemLabel(fn ($state) => $state['description'] ?? 'Nova regra')->collapsible(),
            ]),
        ]);
    }

    public static function numericFieldOptions(?ServiceVersion $record): array
    {
        return $record?->fields()->whereIn('type', ['integer', 'decimal', 'quantity', 'money', 'meter'])->where('required', true)->pluck('label', 'key')->all() ?? [];
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('service.name')->label('Serviço')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('version')->label('Versão')->badge()->sortable(),
            Tables\Columns\TextColumn::make('status')->label('Estado')->badge()->color(fn (string $state) => $state === 'published' ? 'success' : ($state === 'draft' ? 'warning' : 'gray')),
            Tables\Columns\TextColumn::make('unit')->label('Unidade'),
            Tables\Columns\TextColumn::make('customer_rate')->label('Cobrança')->money('BRL')->placeholder('—'),
            Tables\Columns\TextColumn::make('default_provider_rate')->label('Prestador')->money('BRL')->placeholder('—'),
            Tables\Columns\IconColumn::make('allow_provider_create_order')->label('Cria OS')->boolean(),
        ])->filters([
            Tables\Filters\SelectFilter::make('status')->options(['draft' => 'Rascunho', 'published' => 'Publicada', 'retired' => 'Encerrada']),
        ])->actions([
            Tables\Actions\ViewAction::make(),
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make()->label('Excluir rascunho'),
            Tables\Actions\Action::make('activate')->label('Ativar para novas ordens')->visible(fn ($record) => $record->status === 'retired')->requiresConfirmation()->action(fn ($record) => app(ServiceCatalogService::class)->setActive($record, true)),
            Tables\Actions\Action::make('deactivate')->label('Desativar para novas ordens')->visible(fn ($record) => $record->status === 'published')->requiresConfirmation()->action(fn ($record) => app(ServiceCatalogService::class)->setActive($record, false)),
            Tables\Actions\Action::make('publish')->label('Publicar')->icon('heroicon-o-check-circle')->color('success')->visible(fn (ServiceVersion $record) => $record->status === 'draft')->requiresConfirmation()->action(function (ServiceVersion $record): void {
                app(ServiceCatalogService::class)->publish($record, auth()->user());
                Notification::make()->success()->title('Versão publicada')->send();
            }),
            Tables\Actions\Action::make('clone')->label('Nova versão')->icon('heroicon-o-document-duplicate')->action(function (ServiceVersion $record): void {
                app(ServiceCatalogService::class)->clone($record);
                Notification::make()->success()->title('Nova versão criada como rascunho')->send();
            }),
        ])->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [RelationManagers\FieldsRelationManager::class, RelationManagers\ProviderRatesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServiceVersions::route('/'),
            'view' => Pages\ViewServiceVersion::route('/{record}'),
            'edit' => Pages\EditServiceVersion::route('/{record}/edit'),
        ];
    }
}
