<?php

namespace App\Filament\Resources;

use App\Enums\ServiceType;
use App\Filament\Resources\ServiceResource\Pages;
use App\Filament\Traits\TenantScoped;
use App\Models\Service;
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

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dados do Serviço')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Código')
                            ->unique(ignoreRecord: true, modifyRuleUsing: function (Unique $rule) {
                                return $rule->where('tenant_id', session('tenant_id'));
                            })
                            ->maxLength(20),

                        Forms\Components\TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('type')
                            ->label('Tipo')
                            ->options(ServiceType::class)
                            ->required()
                            ->default(ServiceType::OUTRO),

                        Forms\Components\TextInput::make('unit')
                            ->label('Unidade')
                            ->required()
                            ->default('hora')
                            ->maxLength(20),

                        Forms\Components\Toggle::make('status')
                            ->label('Ativo')
                            ->default(true),

                        Forms\Components\Textarea::make('description')
                            ->label('Descrição')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Configuração da primeira versão')
                    ->description('Defina uma vez como o serviço é executado, cobrado e remunerado. Depois de publicada, a versão fica protegida para preservar o histórico.')
                    ->visibleOn('create')
                    ->schema([
                        Forms\Components\Select::make('version_review_mode')->label('Conferência')->options(['automatic' => 'Automática', 'manual' => 'Manual'])->default('manual')->required(),
                        Forms\Components\Toggle::make('version_allow_provider_create_order')->label('Prestador pode criar ordem')->default(false),
                        Forms\Components\Toggle::make('version_receivable_enabled')->label('Gerar cobrança ao cliente/associado')->live()->default(true),
                        Forms\Components\Select::make('version_customer_pricing_method')->label('Como cobrar')->options(['fixed' => 'Valor fixo', 'quantity_x_rate' => 'Quantidade × tarifa', 'percent_of_base' => 'Percentual da base'])->default('quantity_x_rate')->required(fn ($get) => $get('version_receivable_enabled')),
                        Forms\Components\TextInput::make('version_customer_rate')->label('Valor, tarifa ou base de cobrança')->numeric()->prefix('R$')->minValue(0.0001)->required(fn (Get $get) => $get('version_receivable_enabled')),
                        Forms\Components\TextInput::make('version_customer_percentage')->label('Percentual de cobrança')->numeric()->suffix('%')->minValue(0.0001)->maxValue(100)->required(fn (Get $get) => $get('version_receivable_enabled') && $get('version_customer_pricing_method') === 'percent_of_base'),
                        Forms\Components\Toggle::make('version_payable_enabled')->label('Gerar valor a pagar ao prestador')->live()->default(true),
                        Forms\Components\Select::make('version_provider_pricing_method')->label('Como remunerar')->options(['fixed' => 'Valor fixo', 'quantity_x_rate' => 'Quantidade × tarifa', 'percent_of_base' => 'Percentual da cobrança'])->default('quantity_x_rate')->required(fn ($get) => $get('version_payable_enabled')),
                        Forms\Components\TextInput::make('version_default_provider_rate')->label('Valor/tarifa padrão do prestador')->numeric()->prefix('R$')->minValue(0.0001)->required(fn (Get $get) => $get('version_payable_enabled') && in_array($get('version_provider_pricing_method'), ['fixed', 'quantity_x_rate'], true)),
                        Forms\Components\TextInput::make('version_provider_percentage')->label('Percentual do prestador')->numeric()->suffix('%')->minValue(0.0001)->maxValue(100)->required(fn (Get $get) => $get('version_payable_enabled') && $get('version_provider_pricing_method') === 'percent_of_base'),
                        Forms\Components\Toggle::make('version_publish')->label('Publicar e disponibilizar agora')->default(true),
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
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
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
