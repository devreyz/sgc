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
            Forms\Components\Section::make('1. Dados gerais e medição operacional')
                ->description('DADOS COLETADOS NÃO ALTERAM VALORES FINANCEIROS por conta própria. Eles registram o que aconteceu e podem ser escolhidos como entradas das fórmulas abaixo. Somente uma fórmula ou termo que cite o campo produz efeito financeiro.')
                ->schema([
                    Forms\Components\Select::make('service_id')->label('Serviço')->relationship('service', 'name')->disabled()->dehydrated(),
                    Forms\Components\TextInput::make('version')->label('Versão')->disabled(),
                    Forms\Components\TextInput::make('unit')->label('Unidade de medição')->required()->maxLength(30),
                    Forms\Components\Select::make('review_mode')->label('Conferência')->options(['automatic' => 'Automática', 'manual' => 'Manual'])->required(),
                    Forms\Components\Toggle::make('allow_provider_create_order')->label('Prestador habilitado pode criar ordem'),
                    Forms\Components\Toggle::make('members_only')->label('Somente para membros')->helperText('Quando ativo, a ordem só pode ser criada com um membro/associado selecionado.'),
                    Forms\Components\Select::make('execution_config.quantity_mode')->label('Como obter a quantidade executada')->options([
                        'fixed_one' => 'Uma unidade por execução',
                        'field' => 'Valor informado em um campo',
                        'meter_difference' => 'Diferença entre medição final e inicial',
                    ])->required()->live()->helperText('Define a quantidade operacional registrada na OS; não obriga cobrança e prestador a usarem a mesma fórmula.'),
                    Forms\Components\Select::make('execution_config.quantity_field')->options(fn (?ServiceVersion $record) => static::numericFieldOptions($record))->searchable()->label('Campo da quantidade operacional')->visible(fn (Get $get) => $get('execution_config.quantity_mode') === 'field'),
                    Forms\Components\Select::make('execution_config.meter_start_field')->options(fn (?ServiceVersion $record) => static::numericFieldOptions($record))->searchable()->label('Medição operacional inicial')->visible(fn (Get $get) => $get('execution_config.quantity_mode') === 'meter_difference'),
                    Forms\Components\Select::make('execution_config.meter_end_field')->options(fn (?ServiceVersion $record) => static::numericFieldOptions($record))->searchable()->label('Medição operacional final')->visible(fn (Get $get) => $get('execution_config.quantity_mode') === 'meter_difference'),
                ])->columns(2),
            Forms\Components\Section::make('2. Fórmula do valor a receber')
                ->description('Define somente o que o beneficiário deve à organização. Exemplo: (horímetro final − inicial) × tarifa. Não interfere na remuneração do prestador.')
                ->schema([
                    Forms\Components\Toggle::make('receivable_enabled')->label('Gerar conta a receber')->live(),
                    Forms\Components\Select::make('customer_pricing_method')->label('Fórmula base')->options(['fixed' => 'Valor fixo por execução', 'quantity_x_rate' => 'Quantidade calculada × tarifa', 'percent_of_base' => 'Percentual de um valor base'])->live(),
                    Forms\Components\Select::make('execution_config.customer_quantity_mode')->label('Fonte da quantidade da cobrança')->options([
                        'primary' => 'Usar a medição operacional', 'fixed_one' => 'Uma unidade', 'field' => 'Usar um campo numérico', 'meter_difference' => 'Diferença entre duas medições',
                    ])->default('primary')->live()->visible(fn (Get $get) => $get('receivable_enabled') && $get('customer_pricing_method') === 'quantity_x_rate')->helperText('Esta fonte é exclusiva da cobrança.'),
                    Forms\Components\Select::make('execution_config.customer_quantity_field')->options(fn (?ServiceVersion $record) => static::numericFieldOptions($record))->searchable()->label('Campo usado na quantidade da cobrança')->visible(fn (Get $get) => $get('receivable_enabled') && $get('customer_pricing_method') === 'quantity_x_rate' && $get('execution_config.customer_quantity_mode') === 'field'),
                    Forms\Components\Select::make('execution_config.customer_meter_start_field')->options(fn (?ServiceVersion $record) => static::numericFieldOptions($record))->searchable()->label('Medição inicial da cobrança')->visible(fn (Get $get) => $get('receivable_enabled') && $get('customer_pricing_method') === 'quantity_x_rate' && $get('execution_config.customer_quantity_mode') === 'meter_difference'),
                    Forms\Components\Select::make('execution_config.customer_meter_end_field')->options(fn (?ServiceVersion $record) => static::numericFieldOptions($record))->searchable()->label('Medição final da cobrança')->visible(fn (Get $get) => $get('receivable_enabled') && $get('customer_pricing_method') === 'quantity_x_rate' && $get('execution_config.customer_quantity_mode') === 'meter_difference'),
                    Forms\Components\TextInput::make('execution_config.customer_unit')->label('Unidade exibida na cobrança')->placeholder('hora, dia, km, unidade')->maxLength(30)->visible(fn (Get $get) => $get('receivable_enabled') && $get('customer_pricing_method') === 'quantity_x_rate'),
                    Forms\Components\TextInput::make('customer_rate')->label(fn (Get $get) => match ($get('customer_pricing_method')) { 'fixed' => 'Valor fixo por execução', 'quantity_x_rate' => 'Tarifa por unidade', default => 'Valor base do percentual' })->numeric()->prefix('R$')->minValue(0.0001)->required(fn (Get $get) => $get('receivable_enabled'))->visible(fn (Get $get) => $get('receivable_enabled')),
                    Forms\Components\TextInput::make('customer_percentage')->label('Percentual da cobrança')->numeric()->suffix('%')->minValue(0.0001)->maxValue(100)->required(fn (Get $get) => $get('receivable_enabled') && $get('customer_pricing_method') === 'percent_of_base')->visible(fn (Get $get) => $get('receivable_enabled') && $get('customer_pricing_method') === 'percent_of_base'),
                ])->columns(2),
            Forms\Components\Section::make('3. Fórmula da remuneração do prestador')
                ->description('Calculada separadamente. Pode usar horas, diárias, quilômetros, outra diferença de medidores, valor fixo ou percentual da cobrança.')
                ->schema([
                    Forms\Components\Toggle::make('payable_enabled')->label('Gerar conta a pagar ao prestador')->live(),
                    Forms\Components\Select::make('provider_pricing_method')->label('Fórmula base')->options(['fixed' => 'Valor fixo por execução', 'quantity_x_rate' => 'Quantidade calculada × tarifa', 'percent_of_base' => 'Percentual do valor base da cobrança'])->live(),
                    Forms\Components\Select::make('execution_config.provider_quantity_mode')->label('Fonte da quantidade da remuneração')->options([
                        'primary' => 'Usar a medição operacional', 'fixed_one' => 'Uma unidade', 'field' => 'Usar um campo numérico', 'meter_difference' => 'Diferença entre duas medições',
                    ])->default('primary')->live()->visible(fn (Get $get) => $get('payable_enabled') && $get('provider_pricing_method') === 'quantity_x_rate')->helperText('Esta fonte é exclusiva da remuneração do prestador.'),
                    Forms\Components\Select::make('execution_config.provider_quantity_field')->options(fn (?ServiceVersion $record) => static::numericFieldOptions($record))->searchable()->label('Campo usado na remuneração')->visible(fn (Get $get) => $get('payable_enabled') && $get('provider_pricing_method') === 'quantity_x_rate' && $get('execution_config.provider_quantity_mode') === 'field'),
                    Forms\Components\Select::make('execution_config.provider_meter_start_field')->options(fn (?ServiceVersion $record) => static::numericFieldOptions($record))->searchable()->label('Medição inicial da remuneração')->visible(fn (Get $get) => $get('payable_enabled') && $get('provider_pricing_method') === 'quantity_x_rate' && $get('execution_config.provider_quantity_mode') === 'meter_difference'),
                    Forms\Components\Select::make('execution_config.provider_meter_end_field')->options(fn (?ServiceVersion $record) => static::numericFieldOptions($record))->searchable()->label('Medição final da remuneração')->visible(fn (Get $get) => $get('payable_enabled') && $get('provider_pricing_method') === 'quantity_x_rate' && $get('execution_config.provider_quantity_mode') === 'meter_difference'),
                    Forms\Components\TextInput::make('execution_config.provider_unit')->label('Unidade exibida na remuneração')->placeholder('hora, dia, km, unidade')->maxLength(30)->visible(fn (Get $get) => $get('payable_enabled') && $get('provider_pricing_method') === 'quantity_x_rate'),
                    Forms\Components\TextInput::make('default_provider_rate')->label(fn (Get $get) => $get('provider_pricing_method') === 'fixed' ? 'Valor fixo por execução' : 'Tarifa por unidade')->numeric()->prefix('R$')->minValue(0.0001)->required(fn (Get $get) => $get('payable_enabled') && in_array($get('provider_pricing_method'), ['fixed', 'quantity_x_rate'], true))->visible(fn (Get $get) => $get('payable_enabled') && in_array($get('provider_pricing_method'), ['fixed', 'quantity_x_rate'], true))->helperText(fn (Get $get) => $get('provider_pricing_method') === 'quantity_x_rate' ? 'Ex.: 20 horas × R$ 30,00 = R$ 600,00.' : 'Pago uma vez por execução, independentemente da quantidade.'),
                    Forms\Components\TextInput::make('provider_percentage')->label('Percentual da cobrança')->numeric()->suffix('%')->minValue(0.0001)->maxValue(100)->required(fn (Get $get) => $get('payable_enabled') && $get('provider_pricing_method') === 'percent_of_base')->visible(fn (Get $get) => $get('payable_enabled') && $get('provider_pricing_method') === 'percent_of_base')->helperText('Use somente quando a remuneração for uma porcentagem do valor base cobrado.'),
                    Forms\Components\Placeholder::make('provider_override_notice')->label('Tarifas individuais')->content('Uma tarifa individual ativa, cadastrada abaixo para um prestador, substitui integralmente esta fórmula padrão. Se não quiser essa substituição, desative ou exclua a tarifa individual no rascunho.')->visible(fn (Get $get) => (bool) $get('payable_enabled'))->columnSpanFull(),
                ])->columns(2),
            Forms\Components\Section::make('4. Termos adicionais das fórmulas')
                ->description('ESTES TERMOS ALTERAM O FINANCEIRO. O total de cada lado será: fórmula base + adicionais − descontos. Cada termo pertence somente à cobrança OU à remuneração. O modo simples cria automaticamente o campo que aparecerá para preenchimento na execução e nos documentos.')
                ->schema([
                Forms\Components\Placeholder::make('formula_help')->label('Como funciona')->content('Exemplo do óleo: escolha “Cobrança do beneficiário”, “Quantidade informada × tarifa”, “Descontar”, tarifa por litro e crie o campo automático “Litros de óleo fornecido”. Resultado: total a receber = serviço − (litros × tarifa).'),
                Forms\Components\Repeater::make('financial_config.rules')->label('Termos adicionais')->default([])->schema([
                    Forms\Components\TextInput::make('description')->label('Descrição')->required()->maxLength(191),
                    Forms\Components\Select::make('direction')->label('Este termo altera')->options(['receivable' => 'Valor a receber pela organização', 'payable' => 'Valor a pagar ao prestador'])->required(),
                    Forms\Components\Select::make('method')->label('Fórmula deste termo')->options(['fixed_addition' => 'Valor fixo ou informado', 'fixed_deduction' => 'Valor fixo ou informado', 'percent_addition' => 'Percentual do valor base', 'percent_deduction' => 'Percentual do valor base', 'quantity_x_rate' => 'Quantidade informada × tarifa'])->required()->live()->afterStateUpdated(fn ($state, callable $set) => $set('input_role', $state === 'quantity_x_rate' ? 'quantity' : 'value')),
                    Forms\Components\Select::make('effect')->label('Efeito')->options(['add' => 'Acrescentar', 'subtract' => 'Descontar'])->default('add')->required(),
                    Forms\Components\TextInput::make('value')->label('Valor fixo ou tarifa por unidade')->numeric()->minValue(0)->default(0)->helperText('Para quantidade × tarifa, informe aqui o preço de cada unidade.'),
                    Forms\Components\TextInput::make('percentage')->label('Percentual configurado')->numeric()->minValue(0)->maxValue(100)->default(0),
                    Forms\Components\Select::make('value_field')->options(fn (?ServiceVersion $record) => static::numericFieldOptions($record))->searchable()->label('Ou usar o valor do campo')->helperText('Chave de um campo numérico obrigatório. Ex.: desconto, litros. Substitui o valor/percentual configurado.'),
                    Forms\Components\Select::make('quantity_field')->options(fn (?ServiceVersion $record) => static::numericFieldOptions($record))->searchable()->label('Campo da quantidade')->helperText('Opcional, para quantidade × valor. Ex.: litros ou km.'),
                    Forms\Components\TextInput::make('input_key')->label('Campo que aparecerá na execução — chave')->alphaDash()->maxLength(80)->placeholder('litros_oleo')->helperText('Cria automaticamente o campo, sem precisar cadastrá-lo novamente em Campos do fluxo.'),
                    Forms\Components\TextInput::make('input_label')->label('Nome do campo automático')->maxLength(160)->placeholder('Litros de óleo fornecido')->required(fn (Get $get) => filled($get('input_key'))),
                    Forms\Components\Select::make('input_role')->label('O campo informa')->options(['quantity' => 'Quantidade que será multiplicada pela tarifa', 'value' => 'Valor monetário ou percentual'])->default('quantity')->required(fn (Get $get) => filled($get('input_key'))),
                    Forms\Components\TextInput::make('input_unit')->label('Unidade do campo')->placeholder('litro, km, hora'),
                    Forms\Components\Select::make('input_phase')->label('Quando preencher')->options(['start' => 'Ao iniciar', 'execution' => 'Durante a execução', 'finish' => 'Ao finalizar'])->default('finish'),
                    Forms\Components\Toggle::make('input_required')->label('Entrada obrigatória')->default(true),
                    Forms\Components\Toggle::make('evidence_required')->label('Exigir comprovante quando a regra for usada')->live(),
                    Forms\Components\TextInput::make('evidence_key')->label('Chave do comprovante automático')->alphaDash()->maxLength(80)->placeholder('comprovante_oleo')->required(fn (Get $get) => (bool) $get('evidence_required')),
                    Forms\Components\TextInput::make('evidence_label')->label('Nome do comprovante')->maxLength(160)->placeholder('Nota ou foto do óleo')->required(fn (Get $get) => (bool) $get('evidence_required')),
                    Forms\Components\Select::make('evidence_field')->options(fn (?ServiceVersion $record) => static::evidenceFieldOptions($record))->searchable()->label('Comprovante obrigatório')->helperText('Quando a regra produzir valor, exige esta foto, nota ou arquivo antes da validação.'),
                ])->columns(2)->addActionLabel('Adicionar termo à fórmula')->itemLabel(fn ($state) => $state['description'] ?? 'Novo termo')->collapsible(),
            ]),
        ]);
    }

    public static function numericFieldOptions(?ServiceVersion $record): array
    {
        return $record?->fields()->whereIn('type', ['integer', 'decimal', 'quantity', 'money', 'meter'])->where('required', true)->pluck('label', 'key')->all() ?? [];
    }

    public static function evidenceFieldOptions(?ServiceVersion $record): array
    {
        return $record?->fields()->whereIn('type', ['image', 'file', 'signature'])->pluck('label', 'key')->all() ?? [];
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
            Tables\Columns\IconColumn::make('members_only')->label('Só membros')->boolean(),
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
