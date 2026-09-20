<?php

namespace App\Filament\Resources;

use App\Enums\ServiceType;
use App\Filament\Resources\ServiceResource\Pages;
use App\Filament\Traits\TenantScoped;
use App\Models\Service;
use App\Services\Services\ServicePresetRegistry;
use App\Support\ServiceConfigurationLabels;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Validation\Rules\Unique;

class ServiceResource extends Resource
{
    use TenantScoped;

    protected static ?string $model = Service::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench';

    protected static ?string $navigationGroup = 'Serviços';

    protected static ?string $modelLabel = 'Serviço';

    protected static ?string $pluralModelLabel = 'Serviços';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return auth()->user()?->checkPermissionTo('manage_service_catalog') ?? false;
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit($record): bool
    {
        return static::canViewAny() && (int) $record->tenant_id === (int) session('tenant_id');
    }

    public static function canDelete($record): bool
    {
        return static::canEdit($record) && ! $record->versions()->where('status', '!=', 'draft')->exists();
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canForceDelete($record): bool
    {
        return false;
    }

    public static function canRestore($record): bool
    {
        return static::canViewAny() && (int) $record->tenant_id === (int) session('tenant_id');
    }

    public static function form(Form $form): Form
    {
        $presets = app(ServicePresetRegistry::class)->all();

        return $form->schema([
            Forms\Components\Wizard::make([
                Forms\Components\Wizard\Step::make('Serviço')->description('Nome e modelo inicial')->schema([
                    Forms\Components\TextInput::make('name')->label('Nome do serviço')->required()->maxLength(255),
                    Forms\Components\TextInput::make('code')->label('Código interno')->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('tenant_id', session('tenant_id')))->maxLength(20),
                    Forms\Components\Select::make('version_preset')->label('Modelo inicial')->options(collect($presets)->pluck('label')->all())->default('simple')->required()->live()
                        ->helperText('O modelo adiciona campos e cálculos sugeridos; tudo pode ser revisado antes da publicação.')
                        ->afterStateUpdated(function ($state, callable $set) use ($presets): void {
                            $preset = $presets[$state] ?? $presets['simple'];
                            $set('type', $preset['service_type']);
                            $set('unit', $preset['unit']);
                            $set('version_receivable_enabled', $preset['receivable_enabled']);
                            $set('version_payable_enabled', $preset['payable_enabled']);
                            $set('version_customer_pricing_method', $preset['customer_pricing_method']);
                            $set('version_provider_pricing_method', $preset['provider_pricing_method']);
                        }),
                    Forms\Components\Select::make('type')->label('Tipo do serviço')->options(ServiceType::class)->required()->default(ServiceType::OUTRO),
                    Forms\Components\TextInput::make('unit')->label('Unidade principal')->required()->default('serviço')->maxLength(20),
                    Forms\Components\Textarea::make('description')->label('Descrição')->rows(3)->columnSpanFull(),
                    Forms\Components\Toggle::make('status')->label('Serviço ativo')->default(true),
                ])->columns(2),
                Forms\Components\Wizard\Step::make('Dados coletados')->description('Campos da execução')->schema([
                    Forms\Components\Placeholder::make('preset_fields')->label('Campos sugeridos pelo modelo')->content(function (Get $get) use ($presets): string {
                        $fields = $presets[$get('version_preset')]['fields'] ?? [];

                        return collect($fields)->map(fn (array $field) => '• '.$field['label'].' — '.ServiceConfigurationLabels::phase($field['phase']).($field['required'] ? ' (obrigatório)' : ' (opcional)'))->implode("\n");
                    }),
                    Forms\Components\Placeholder::make('field_notice')->label('Importante')->content('Dados coletados não mudam valores sozinhos. Depois de salvar, abra o rascunho para ajustar os campos antes de definir detalhes das fórmulas.'),
                    Forms\Components\Select::make('version_review_mode')->label('Conferência')->options(['automatic' => 'Aprovação automática', 'manual' => 'Gestor confere antes'])->default('manual')->required(),
                    Forms\Components\Toggle::make('version_allow_provider_create_order')->label('Prestador habilitado pode criar ordem')->default(false),
                    Forms\Components\Toggle::make('version_members_only')->label('Somente membros podem receber este serviço')->default(false),
                ])->columns(2),
                Forms\Components\Wizard\Step::make('Cobrança')->description('O que a organização recebe')->schema([
                    Forms\Components\Toggle::make('version_receivable_enabled')->label('Gerar valor a receber')->live()->default(true),
                    Forms\Components\Select::make('version_customer_pricing_method')->label('Como calcular')->options(ServiceConfigurationLabels::pricingMethods())->default('fixed')->required(fn (Get $get) => $get('version_receivable_enabled')),
                    Forms\Components\TextInput::make('version_customer_rate')->label('Valor fixo, tarifa ou base')->numeric()->prefix('R$')->minValue(0.0001)->required(fn (Get $get) => $get('version_receivable_enabled')),
                    Forms\Components\TextInput::make('version_customer_percentage')->label('Percentual')->numeric()->suffix('%')->minValue(0.0001)->maxValue(100)->required(fn (Get $get) => $get('version_receivable_enabled') && $get('version_customer_pricing_method') === 'percent_of_base'),
                ])->columns(2),
                Forms\Components\Wizard\Step::make('Prestador')->description('O que será pago')->schema([
                    Forms\Components\Toggle::make('version_payable_enabled')->label('Gerar valor a pagar ao prestador')->live()->default(false),
                    Forms\Components\Select::make('version_provider_pricing_method')->label('Como calcular')->options(ServiceConfigurationLabels::pricingMethods(true))->required(fn (Get $get) => $get('version_payable_enabled')),
                    Forms\Components\TextInput::make('version_default_provider_rate')->label('Valor fixo ou tarifa por unidade')->numeric()->prefix('R$')->minValue(0.0001)->required(fn (Get $get) => $get('version_payable_enabled') && in_array($get('version_provider_pricing_method'), ['fixed', 'quantity_x_rate'], true)),
                    Forms\Components\TextInput::make('version_provider_percentage')->label('Percentual da cobrança')->numeric()->suffix('%')->minValue(0.0001)->maxValue(100)->required(fn (Get $get) => $get('version_payable_enabled') && $get('version_provider_pricing_method') === 'percent_of_base'),
                ])->columns(2),
                Forms\Components\Wizard\Step::make('Revisão')->description('Salvar com segurança')->schema([
                    Forms\Components\Placeholder::make('publish_help')->label('Recomendação')->content('Salve como rascunho, ajuste os campos, teste os cálculos e só então publique. Versões publicadas ficam protegidas para preservar o histórico.'),
                    Forms\Components\Toggle::make('version_publish')->label('Publicar imediatamente')->default(false),
                ]),
            ])->columnSpanFull()->visibleOn('create'),
            Forms\Components\Section::make('Dados do serviço')->visibleOn('edit')->schema([
                Forms\Components\TextInput::make('code')->label('Código')->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('tenant_id', session('tenant_id')))->maxLength(20),
                Forms\Components\TextInput::make('name')->label('Nome')->required()->maxLength(255),
                Forms\Components\Select::make('type')->label('Tipo')->options(ServiceType::class)->required(),
                Forms\Components\TextInput::make('unit')->label('Unidade')->required()->maxLength(20),
                Forms\Components\Toggle::make('status')->label('Ativo'),
                Forms\Components\Textarea::make('description')->label('Descrição')->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Código')
                    ->searchable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (ServiceType $state): string => $state->getLabel())
                    ->color(fn (ServiceType $state): string => $state->getColor()),

                Tables\Columns\TextColumn::make('unit')
                    ->label('Unidade')
                    ->badge(),

                Tables\Columns\TextColumn::make('base_price')
                    ->label('Cobrança vigente')
                    ->getStateUsing(fn (Service $record) => $record->currentVersion?->customer_rate)
                    ->money('BRL')
                    ->sortable(),
                Tables\Columns\TextColumn::make('currentVersion.version')->label('Versão')->badge()->placeholder('Rascunho'),

                Tables\Columns\IconColumn::make('status')
                    ->label('Ativo')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(ServiceType::class),
                Tables\Filters\TernaryFilter::make('status')
                    ->label('Ativo'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('versions')
                    ->label('Versões e preços')
                    ->icon('heroicon-o-document-duplicate')
                    ->url(fn (Service $record): string => ServiceVersionResource::getUrl('index', ['tableSearch' => $record->name])),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServices::route('/'),
            'create' => Pages\CreateService::route('/create'),
            'edit' => Pages\EditService::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
