<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Filament\Traits\HasExportActions;
use App\Filament\Traits\TenantScoped;
use App\Models\Customer;
use App\Services\CustomerHierarchyService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;

class CustomerResource extends Resource
{
    use HasExportActions;
    use TenantScoped;

    protected static ?string $model = Customer::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationGroup = 'Cadastros';

    protected static ?string $modelLabel = 'Cliente';

    protected static ?string $pluralModelLabel = 'Clientes';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dados do Cliente')
                    ->schema([
                        Forms\Components\Select::make('type')
                            ->label('Tipo')
                            ->options([
                                'prefeitura' => 'Prefeitura',
                                'escola' => 'Escola',
                                'creche' => 'Creche',
                                'hospital' => 'Hospital',
                                'outros' => 'Outros',
                            ])
                            ->required(),

                        Forms\Components\TextInput::make('name')
                            ->label('Razão Social')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('trade_name')
                            ->label('Nome Fantasia')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('cnpj')
                            ->label('CNPJ')
                            ->mask('99.999.999/9999-99')
                            ->nullable()
                            ->maxLength(18)
                            ->disabled(fn (?Model $record): bool => $record instanceof Customer
                                && app(CustomerHierarchyService::class)->hasLinkedData($record))
                            ->helperText(fn (?Model $record): string => $record instanceof Customer
                                && app(CustomerHierarchyService::class)->hasLinkedData($record)
                                    ? 'CNPJ preservado porque este cliente ja possui historico.'
                                    : 'Pode ser compartilhado somente entre matriz e filiais ou clientes da mesma organização.'),

                        Forms\Components\TextInput::make('ie')
                            ->label('Inscrição Estadual')
                            ->maxLength(20),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Organização e Tabela de Preços')
                    ->description('Organize matriz, filiais, agrupamento comprador e preços sem alterar o histórico das unidades.')
                    ->schema([
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
                            ->options(function (?Model $record): array {
                                return Customer::query()
                                    ->where('tenant_id', session('tenant_id'))
                                    ->whereNull('parent_customer_id')
                                    ->where('status', true)
                                    ->when($record, fn (Builder $query) => $query->where('id', '!=', $record->id))
                                    ->orderBy('name')
                                    ->get(['id', 'name', 'trade_name', 'cnpj'])
                                    ->mapWithKeys(fn (Customer $customer) => [
                                        $customer->id => $customer->name.($customer->trade_name ? ' · '.$customer->trade_name : '').($customer->cnpj ? ' · '.$customer->cnpj : ''),
                                    ])
                                    ->all();
                            })
                            ->searchable()
                            ->preload()
                            ->disabled(fn (?Model $record): bool => $record instanceof Customer
                                && app(CustomerHierarchyService::class)->hasLinkedData($record))
                            ->required(fn (Get $get): bool => $get('unit_type') === 'branch')
                            ->visible(fn (Get $get): bool => $get('unit_type') === 'branch')
                            ->live()
                            ->afterStateUpdated(function ($state, Get $get, Set $set): void {
                                if (! $state) {
                                    return;
                                }

                                $parent = Customer::query()
                                    ->where('tenant_id', session('tenant_id'))
                                    ->find($state);
                                if (! $parent) {
                                    return;
                                }
                                if (! $get('cnpj') && $parent->cnpj) {
                                    $set('cnpj', $parent->cnpj);
                                }
                                if (! $get('organization_id') && $parent->organization_id) {
                                    $set('organization_id', $parent->organization_id);
                                }
                            })
                            ->helperText('A filial pode usar o mesmo CNPJ da matriz.'),

                        Forms\Components\Select::make('organization_id')
                            ->label('Organização')
                            ->relationship('organization', 'name', fn ($query) => $query->where('tenant_id', session('tenant_id'))->where('active', true))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->disabled(fn (?Model $record): bool => $record instanceof Customer
                                && app(CustomerHierarchyService::class)->hasLinkedData($record))
                            ->placeholder('— Nenhuma —')
                            ->helperText('Unidades da mesma família devem usar a mesma organização quando vinculadas.'),

                        Forms\Components\Select::make('price_table_id')
                            ->label('Tabela de Preços')
                            ->relationship('priceTable', 'name', fn ($query) => $query->where('tenant_id', session('tenant_id'))->where('active', true))
                            ->searchable()
                            ->preload()
                            ->placeholder('— Usar preços padrão do produto —')
                            ->helperText('Preços desta tabela serão usados nas distribuições para este cliente'),

                        Forms\Components\Placeholder::make('historical_identity_notice')
                            ->label('Cadastro protegido')
                            ->content('Matriz, filial, organização e CNPJ foram preservados porque existem registros históricos. Contato, endereço, situação e tabela de preços continuam editáveis.')
                            ->visible(fn (?Model $record): bool => $record instanceof Customer
                                && app(CustomerHierarchyService::class)->hasLinkedData($record))
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsed(false),

                Forms\Components\Section::make('Endereço')
                    ->schema([
                        Forms\Components\TextInput::make('address')
                            ->label('Endereço')
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('district')
                            ->label('Bairro')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('city')
                            ->label('Cidade')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('state')
                            ->label('Estado')
                            ->options([
                                'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas',
                                'BA' => 'Bahia', 'CE' => 'Ceará', 'DF' => 'Distrito Federal',
                                'ES' => 'Espírito Santo', 'GO' => 'Goiás', 'MA' => 'Maranhão',
                                'MT' => 'Mato Grosso', 'MS' => 'Mato Grosso do Sul', 'MG' => 'Minas Gerais',
                                'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná', 'PE' => 'Pernambuco',
                                'PI' => 'Piauí', 'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte',
                                'RS' => 'Rio Grande do Sul', 'RO' => 'Rondônia', 'RR' => 'Roraima',
                                'SC' => 'Santa Catarina', 'SP' => 'São Paulo', 'SE' => 'Sergipe',
                                'TO' => 'Tocantins',
                            ])
                            ->searchable()
                            ->required(),

                        Forms\Components\TextInput::make('zip_code')
                            ->label('CEP')
                            ->mask('99999-999')
                            ->maxLength(9),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Contato')
                    ->schema([
                        Forms\Components\TextInput::make('contact_name')
                            ->label('Nome do Contato')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('phone')
                            ->label('Telefone')
                            ->tel()
                            ->mask('(99) 99999-9999')
                            ->maxLength(20),

                        Forms\Components\TextInput::make('email')
                            ->label('E-mail')
                            ->email()
                            ->maxLength(255),

                        Forms\Components\Toggle::make('status')
                            ->label('Ativo')
                            ->default(true),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Observações')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label('Observações')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => ucfirst($state)),

                Tables\Columns\TextColumn::make('name')
                    ->label('Razão Social')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('organization.name')
                    ->label('Organização')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('unit_type')
                    ->label('Unidade')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => match ($state) {
                        'headquarters' => 'Matriz',
                        'branch' => 'Filial',
                        default => 'Independente',
                    })
                    ->color(fn ($state): string => match ($state) {
                        'headquarters' => 'primary',
                        'branch' => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('priceTable.name')
                    ->label('Tabela de Preços')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('cnpj')
                    ->label('CNPJ')
                    ->searchable(),

                Tables\Columns\TextColumn::make('city')
                    ->label('Cidade')
                    ->searchable(),

                Tables\Columns\TextColumn::make('state')
                    ->label('UF')
                    ->badge(),

                Tables\Columns\TextColumn::make('contact_name')
                    ->label('Contato'),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Telefone'),

                Tables\Columns\IconColumn::make('status')
                    ->label('Ativo')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'pessoal' => 'Pessoal',
                        'governo' => 'Governo',
                        'empresa' => 'Empresa',
                    ]),
                Tables\Filters\TernaryFilter::make('status')
                    ->label('Ativo'),
            ])
            ->headerActions([
                self::getExportAction(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('duplicate')
                    ->label('Duplicar')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('info')
                    ->modalHeading('Duplicar Cliente')
                    ->modalDescription('Preencha os dados do novo cliente. Os preços personalizados por produto serão copiados automaticamente.')
                    ->modalSubmitActionLabel('Criar Cópia')
                    ->form(fn (Customer $record): array => [
                        Forms\Components\Section::make('Dados do Novo Cliente')
                            ->schema([
                                Forms\Components\Select::make('type')
                                    ->label('Tipo')
                                    ->options([
                                        'prefeitura' => 'Prefeitura',
                                        'escola' => 'Escola',
                                        'creche' => 'Creche',
                                        'hospital' => 'Hospital',
                                        'outros' => 'Outros',
                                    ])
                                    ->required()
                                    ->default($record->type),

                                Forms\Components\TextInput::make('name')
                                    ->label('Razão Social')
                                    ->required()
                                    ->maxLength(255)
                                    ->default('Cópia de '.$record->name),

                                Forms\Components\TextInput::make('trade_name')
                                    ->label('Nome Fantasia')
                                    ->maxLength(255)
                                    ->default($record->trade_name),

                                Forms\Components\TextInput::make('cnpj')
                                    ->label('CNPJ')
                                    ->mask('99.999.999/9999-99')
                                    ->maxLength(18)
                                    ->default($record->cnpj)
                                    ->helperText('O mesmo CNPJ é permitido para matriz e filial.'),

                                Forms\Components\Select::make('unit_type')
                                    ->label('Estrutura da unidade')
                                    ->options([
                                        'independent' => 'Unidade independente',
                                        'branch' => 'Filial',
                                    ])
                                    ->default('branch')
                                    ->required()
                                    ->live(),

                                Forms\Components\Select::make('parent_customer_id')
                                    ->label('Matriz')
                                    ->options(fn (): array => Customer::query()
                                        ->where('tenant_id', session('tenant_id'))
                                        ->whereNull('parent_customer_id')
                                        ->where('status', true)
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->all())
                                    ->default($record->parent_customer_id ?: $record->id)
                                    ->required(fn (Get $get): bool => $get('unit_type') === 'branch')
                                    ->visible(fn (Get $get): bool => $get('unit_type') === 'branch')
                                    ->searchable()
                                    ->preload(),

                                Forms\Components\Select::make('organization_id')
                                    ->label('Organização')
                                    ->relationship('organization', 'name', fn ($query) => $query
                                        ->where('tenant_id', session('tenant_id'))
                                        ->where('active', true))
                                    ->default($record->organization_id)
                                    ->searchable()
                                    ->preload(),

                                Forms\Components\TextInput::make('responsible_name')
                                    ->label('Nome do Responsável')
                                    ->maxLength(255)
                                    ->default($record->responsible_name),

                                Forms\Components\TextInput::make('email')
                                    ->label('E-mail')
                                    ->email()
                                    ->maxLength(255)
                                    ->default($record->email),

                                Forms\Components\TextInput::make('phone')
                                    ->label('Telefone')
                                    ->tel()
                                    ->mask('(99) 99999-9999')
                                    ->maxLength(20)
                                    ->default($record->phone),

                                Forms\Components\TextInput::make('city')
                                    ->label('Cidade')
                                    ->maxLength(255)
                                    ->default($record->city),

                                Forms\Components\Select::make('state')
                                    ->label('Estado')
                                    ->options([
                                        'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas',
                                        'BA' => 'Bahia', 'CE' => 'Ceará', 'DF' => 'Distrito Federal',
                                        'ES' => 'Espírito Santo', 'GO' => 'Goiás', 'MA' => 'Maranhão',
                                        'MT' => 'Mato Grosso', 'MS' => 'Mato Grosso do Sul', 'MG' => 'Minas Gerais',
                                        'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná', 'PE' => 'Pernambuco',
                                        'PI' => 'Piauí', 'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte',
                                        'RS' => 'Rio Grande do Sul', 'RO' => 'Rondônia', 'RR' => 'Roraima',
                                        'SC' => 'Santa Catarina', 'SP' => 'São Paulo', 'SE' => 'Sergipe',
                                        'TO' => 'Tocantins',
                                    ])
                                    ->searchable()
                                    ->default($record->state),
                            ])
                            ->columns(2),
                    ])
                    ->action(function (Customer $record, array $data): void {
                        $tenantId = session('tenant_id');

                        DB::transaction(function () use ($record, $data, $tenantId): void {
                            $newCustomer = $record->replicate();
                            $newCustomer->tenant_id = $tenantId;
                            $newCustomer->name = $data['name'];
                            $newCustomer->trade_name = $data['trade_name'] ?? null;
                            $newCustomer->cnpj = $data['cnpj'] ?? null;
                            $newCustomer->type = $data['type'];
                            $newCustomer->unit_type = $data['unit_type'];
                            $newCustomer->parent_customer_id = $data['unit_type'] === 'branch'
                                ? $data['parent_customer_id']
                                : null;
                            $newCustomer->organization_id = $data['organization_id'] ?? null;
                            $newCustomer->responsible_name = $data['responsible_name'] ?? null;
                            $newCustomer->email = $data['email'] ?? null;
                            $newCustomer->phone = $data['phone'] ?? null;
                            $newCustomer->city = $data['city'] ?? null;
                            $newCustomer->state = $data['state'] ?? null;
                            $newCustomer->save();

                            Notification::make()
                                ->title('Nova unidade criada')
                                ->body('O cliente foi criado mantendo a tabela de preços e o vínculo informado.')
                                ->success()
                                ->send();
                        });
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function ($records): void {
                            foreach ($records as $record) {
                                app(CustomerHierarchyService::class)->ensureCanDelete($record);
                            }
                        }),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'view' => Pages\ViewCustomer::route('/{record}'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    protected static function getExportColumns(): array
    {
        return [
            'type' => 'Tipo',
            'name' => 'Razão Social',
            'trade_name' => 'Nome Fantasia',
            'cnpj' => 'CNPJ',
            'ie' => 'Inscrição Estadual',
            'address' => 'Endereço',
            'district' => 'Bairro',
            'city' => 'Cidade',
            'state' => 'UF',
            'zip_code' => 'CEP',
            'contact_name' => 'Contato',
            'phone' => 'Telefone',
            'email' => 'E-mail',
        ];
    }
}
