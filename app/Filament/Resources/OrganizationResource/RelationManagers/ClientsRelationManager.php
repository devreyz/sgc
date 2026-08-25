<?php

namespace App\Filament\Resources\OrganizationResource\RelationManagers;

use App\Models\Customer;
use App\Models\PriceTable;
use App\Services\CustomerHierarchyService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ClientsRelationManager extends RelationManager
{
    protected static string $relationship = 'clients';

    protected static ?string $title = 'Clientes (Escolas, Creches, etc.)';

    protected static ?string $modelLabel = 'cliente';

    protected static ?string $pluralModelLabel = 'clientes';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nome')
                ->required()
                ->columnSpan(2),

            Forms\Components\TextInput::make('cnpj')
                ->label('CNPJ')
                ->mask('99.999.999/9999-99')
                ->placeholder('00.000.000/0000-00')
                ->disabled(fn (?Model $record): bool => $record instanceof Customer
                    && app(CustomerHierarchyService::class)->hasLinkedData($record))
                ->helperText(fn (?Model $record): string => $record instanceof Customer
                    && app(CustomerHierarchyService::class)->hasLinkedData($record)
                        ? 'CNPJ preservado porque este cliente ja possui historico.'
                        : 'Matriz e filiais desta organização podem compartilhar o CNPJ.'),

            Forms\Components\Select::make('unit_type')
                ->label('Estrutura da unidade')
                ->options([
                    'independent' => 'Unidade independente',
                    'headquarters' => 'Matriz',
                    'branch' => 'Filial',
                ])
                ->default('independent')
                ->required()
                ->disabled(fn (?Model $record): bool => $record instanceof Customer
                    && app(CustomerHierarchyService::class)->hasLinkedData($record))
                ->live()
                ->afterStateUpdated(function (?string $state, Set $set): void {
                    if ($state !== 'branch') {
                        $set('parent_customer_id', null);
                    }
                }),

            Forms\Components\Select::make('parent_customer_id')
                ->label('Matriz')
                ->options(fn (?Model $record): array => Customer::query()
                    ->where('tenant_id', session('tenant_id'))
                    ->where('organization_id', $this->getOwnerRecord()->id)
                    ->whereNull('parent_customer_id')
                    ->where('status', true)
                    ->when($record, fn ($query) => $query->where('id', '!=', $record->id))
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->searchable()
                ->preload()
                ->disabled(fn (?Model $record): bool => $record instanceof Customer
                    && app(CustomerHierarchyService::class)->hasLinkedData($record))
                ->required(fn (Get $get): bool => $get('unit_type') === 'branch')
                ->visible(fn (Get $get): bool => $get('unit_type') === 'branch')
                ->afterStateUpdated(function ($state, Get $get, Set $set): void {
                    if (! $state || $get('cnpj')) {
                        return;
                    }

                    $cnpj = Customer::query()
                        ->where('tenant_id', session('tenant_id'))
                        ->where('organization_id', $this->getOwnerRecord()->id)
                        ->whereKey($state)
                        ->value('cnpj');
                    if ($cnpj) {
                        $set('cnpj', $cnpj);
                    }
                }),

            Forms\Components\Select::make('type')
                ->label('Tipo')
                ->options([
                    'escola' => 'Escola',
                    'creche' => 'Creche',
                    'prefeitura' => 'Prefeitura',
                    'hospital' => 'Hospital',
                    'restaurante' => 'Restaurante / Refeitório',
                    'mercado' => 'Mercado',
                    'outro' => 'Outro',
                ])
                ->required()
                ->default('escola'),

            Forms\Components\Select::make('price_table_id')
                ->label('Tabela de Preços')
                ->options(fn () => PriceTable::where('tenant_id', session('tenant_id'))
                    ->active()->pluck('name', 'id'))
                ->searchable()
                ->placeholder('— Nenhuma (usar preços do produto) —')
                ->helperText('Tabela de preços padrão para este cliente'),

            Forms\Components\Placeholder::make('historical_identity_notice')
                ->label('Vínculo protegido')
                ->content('Esta unidade ja possui historico. O vínculo com a organização e a estrutura matriz/filial nao podem ser removidos.')
                ->visible(fn (?Model $record): bool => $record instanceof Customer
                    && app(CustomerHierarchyService::class)->hasLinkedData($record))
                ->columnSpanFull(),

            Forms\Components\TextInput::make('city')
                ->label('Cidade'),

            Forms\Components\TextInput::make('state')
                ->label('UF')
                ->maxLength(2),

            Forms\Components\Toggle::make('status')
                ->label('Ativo')
                ->default(true),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->weight('semibold'),

                Tables\Columns\TextColumn::make('unit_type')
                    ->label('Unidade')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => match ($state) {
                        'headquarters' => 'Matriz',
                        'branch' => 'Filial',
                        default => 'Independente',
                    }),

                Tables\Columns\TextColumn::make('parentCustomer.name')
                    ->label('Matriz')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('cnpj')
                    ->label('CNPJ')
                    ->placeholder('—'),

                Tables\Columns\BadgeColumn::make('type')
                    ->label('Tipo')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'escola' => 'Escola',
                        'creche' => 'Creche',
                        'prefeitura' => 'Prefeitura',
                        'hospital' => 'Hospital',
                        'restaurante' => 'Refeitório',
                        'mercado' => 'Mercado',
                        default => 'Outro',
                    }),

                Tables\Columns\TextColumn::make('priceTable.name')
                    ->label('Tabela de Preços')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('city')
                    ->label('Cidade')
                    ->placeholder('—'),

                Tables\Columns\IconColumn::make('status')
                    ->label('Ativo')
                    ->boolean(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['tenant_id'] = session('tenant_id');

                        return $data;
                    }),
                Tables\Actions\AssociateAction::make()
                    ->label('Vincular Cliente ou Matriz Existente')
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['name', 'trade_name'])
                    ->recordTitle(fn (Model $record): string => $record->name.($record->trade_name && $record->trade_name !== $record->name
                            ? " ({$record->trade_name})"
                            : '')
                    ),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DissociateAction::make()
                    ->disabled(fn (Customer $record): bool => app(CustomerHierarchyService::class)->organizationLinkIsLocked($record))
                    ->tooltip(fn (Customer $record): ?string => app(CustomerHierarchyService::class)->organizationLinkIsLocked($record)
                        ? 'A organização já possui comprovante ligado às entregas. Desative o cliente para preservar o histórico.'
                        : null),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DissociateBulkAction::make()
                        ->before(function ($records): void {
                            foreach ($records as $record) {
                                app(CustomerHierarchyService::class)->ensureCanDissociate($record);
                            }
                        }),
                ]),
            ]);
    }
}
