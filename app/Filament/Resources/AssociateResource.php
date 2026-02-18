<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AssociateResource\Pages;
use App\Filament\Resources\AssociateResource\RelationManagers;
use App\Filament\Traits\HasExportActions;
use App\Filament\Traits\TenantScoped;
use App\Models\Associate;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Unique;

class AssociateResource extends Resource
{
    use HasExportActions;
    use TenantScoped;

    protected static ?string $model = Associate::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Cadastros';

    protected static ?string $modelLabel = 'Associado';

    protected static ?string $pluralModelLabel = 'Associados';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Associado')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Dados Pessoais')
                            ->schema([
                                Forms\Components\Select::make('user_id')
                                    ->label('Usuário')
                                    ->relationship(
                                        name: 'user',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: function ($query) {
                                            $tenantId = session('tenant_id');
                                            if ($tenantId && ! Auth::user()?->hasRole('super_admin')) {
                                                // Filtrar apenas usuários desta organização
                                                $query->whereHas('tenants', function ($q) use ($tenantId) {
                                                    $q->where('tenant_id', $tenantId);
                                                });
                                            }

                                            return $query;
                                        }
                                    )
                                    ->getOptionLabelFromRecordUsing(fn (User $record) => $record->display_name)
                                    ->searchable(['name', 'email'])
                                    ->preload()
                                    ->required()
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')
                                            ->label('Nome Completo')
                                            ->required()
                                            ->maxLength(255)
                                            ->helperText('Nome como deve aparecer nesta organização'),
                                        Forms\Components\TextInput::make('email')
                                            ->label('E-mail')
                                            ->email()
                                            ->required()
                                            ->maxLength(255)
                                            ->helperText('Se o e-mail já existir no sistema, o usuário será vinculado a esta organização'),
                                        Forms\Components\TextInput::make('password')
                                            ->label('Senha')
                                            ->password()
                                            ->required()
                                            ->minLength(8)
                                            ->helperText('Senha específica desta organização (mínimo 8 caracteres)'),
                                    ])
                                    ->createOptionUsing(function (array $data): int {
                                        $tenantId = session('tenant_id');

                                        // Verificar se usuário já existe por email
                                        $existingUser = User::where('email', $data['email'])->first();

                                        if ($existingUser) {
                                            // Usuário existe - adicionar à organização atual
                                            if ($tenantId && ! $existingUser->tenants()->where('tenant_id', $tenantId)->exists()) {
                                                $existingUser->tenants()->attach($tenantId, [
                                                    'tenant_name' => $data['name'],
                                                    'tenant_password' => Hash::make($data['password']),
                                                    'is_admin' => false,
                                                    'roles' => json_encode([]),
                                                    'created_at' => now(),
                                                    'updated_at' => now(),
                                                ]);

                                                \Filament\Notifications\Notification::make()
                                                    ->title('Usuário existente vinculado à organização')
                                                    ->success()
                                                    ->send();
                                            }

                                            return $existingUser->id;
                                        }

                                        // Usuário não existe - criar novo
                                        $user = User::create([
                                            'name' => $data['name'],
                                            'email' => $data['email'],
                                            'password' => Hash::make($data['password']),
                                            'status' => true,
                                        ]);

                                        // Vincular à organização atual
                                        if ($tenantId) {
                                            $user->tenants()->attach($tenantId, [
                                                'tenant_name' => $data['name'],
                                                'tenant_password' => Hash::make($data['password']),
                                                'is_admin' => false,
                                                'roles' => json_encode([]),
                                                'created_at' => now(),
                                                'updated_at' => now(),
                                            ]);
                                        }

                                        return $user->id;
                                    }),

                                Forms\Components\TextInput::make('cpf_cnpj')
                                    ->label('CPF/CNPJ')
                                    ->required()
                                    ->unique(ignoreRecord: true, modifyRuleUsing: function (Unique $rule) {
                                        return $rule->where('tenant_id', session('tenant_id'));
                                    })
                                    ->mask('999.999.999-99')
                                    ->maxLength(18),

                                Forms\Components\TextInput::make('rg')
                                    ->label('RG')
                                    ->maxLength(20),

                                Forms\Components\TextInput::make('dap_caf')
                                    ->label('DAP/CAF')
                                    ->maxLength(50),

                                Forms\Components\DatePicker::make('dap_caf_expiry')
                                    ->label('Validade DAP/CAF'),

                                Forms\Components\TextInput::make('registration_number')
                                    ->label('Nº Matrícula')
                                    ->maxLength(255),

                                Forms\Components\DatePicker::make('admission_date')
                                    ->label('Data de Admissão'),
                            ])
                            ->columns(2),

                        Forms\Components\Tabs\Tab::make('Propriedade')
                            ->schema([
                                Forms\Components\TextInput::make('property_name')
                                    ->label('Nome da Propriedade')
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('property_area')
                                    ->label('Área (hectares)')
                                    ->numeric()
                                    ->suffix('ha'),

                                Forms\Components\TextInput::make('address')
                                    ->label('Endereço')
                                    ->maxLength(255)
                                    ->columnSpanFull(),

                                Forms\Components\TextInput::make('district')
                                    ->label('Bairro/Comunidade')
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

                        Forms\Components\Tabs\Tab::make('Contato')
                            ->schema([
                                Forms\Components\TextInput::make('phone')
                                    ->label('Telefone')
                                    ->tel()
                                    ->mask('(99) 99999-9999')
                                    ->maxLength(20),

                                Forms\Components\TextInput::make('whatsapp')
                                    ->label('WhatsApp')
                                    ->tel()
                                    ->mask('(99) 99999-9999')
                                    ->maxLength(20),
                            ])
                            ->columns(2),

                        Forms\Components\Tabs\Tab::make('Dados Bancários')
                            ->schema([
                                Forms\Components\TextInput::make('bank_name')
                                    ->label('Banco')
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('bank_agency')
                                    ->label('Agência')
                                    ->maxLength(10),

                                Forms\Components\TextInput::make('bank_account')
                                    ->label('Conta')
                                    ->maxLength(20),

                                Forms\Components\Select::make('bank_account_type')
                                    ->label('Tipo de Conta')
                                    ->options([
                                        'corrente' => 'Corrente',
                                        'poupanca' => 'Poupança',
                                    ]),

                                Forms\Components\Select::make('pix_key_type')
                                    ->label('Tipo de Chave PIX')
                                    ->options([
                                        'cpf' => 'CPF',
                                        'cnpj' => 'CNPJ',
                                        'email' => 'E-mail',
                                        'phone' => 'Telefone',
                                        'random' => 'Aleatória',
                                    ]),

                                Forms\Components\TextInput::make('pix_key')
                                    ->label('Chave PIX')
                                    ->maxLength(255),
                            ])
                            ->columns(2),

                        Forms\Components\Tabs\Tab::make('Observações')
                            ->schema([
                                Forms\Components\Textarea::make('notes')
                                    ->label('Observações')
                                    ->rows(5)
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.display_name')
                    ->label('Nome')
                    ->searchable(['users.name'])
                    ->sortable(['users.name']),

                Tables\Columns\TextColumn::make('cpf_cnpj')
                    ->label('CPF/CNPJ')
                    ->searchable(),

                Tables\Columns\TextColumn::make('dap_caf')
                    ->label('DAP/CAF')
                    ->searchable(),

                Tables\Columns\TextColumn::make('dap_caf_expiry')
                    ->label('Validade DAP/CAF')
                    ->date('d/m/Y')
                    ->color(fn (Associate $record): string => $record->isDapCafExpired() ? 'danger' :
                        ($record->isDapCafExpiringSoon() ? 'warning' : 'success')
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('city')
                    ->label('Cidade')
                    ->searchable(),

                Tables\Columns\TextColumn::make('state')
                    ->label('UF')
                    ->badge(),

                Tables\Columns\TextColumn::make('current_balance')
                    ->label('Saldo')
                    ->money('BRL')
                    ->color(fn ($state): string => $state >= 0 ? 'success' : 'danger'),

                Tables\Columns\TextColumn::make('user.status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state ? 'Ativo' : 'Inativo')
                    ->color(fn ($state): string => $state ? 'success' : 'danger'),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\SelectFilter::make('state')
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
                    ]),
                Tables\Filters\Filter::make('dap_expiring')
                    ->label('DAP/CAF Vencendo')
                    ->query(fn (Builder $query): Builder => $query->whereBetween('dap_caf_expiry', [now(), now()->addDays(30)])
                    ),
            ])
            ->headerActions([
                self::getExportAction(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
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
            RelationManagers\LedgerEntriesRelationManager::class,
            RelationManagers\ProductionDeliveriesRelationManager::class,
            RelationManagers\PurchaseOrdersRelationManager::class,
            RelationManagers\ServiceOrdersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssociates::route('/'),
            'create' => Pages\CreateAssociate::route('/create'),
            'view' => Pages\ViewAssociate::route('/{record}'),
            'edit' => Pages\EditAssociate::route('/{record}/edit'),
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
            'user.display_name' => 'Nome',
            'cpf_cnpj' => 'CPF/CNPJ',
            'rg' => 'RG',
            'dap_caf' => 'DAP/CAF',
            'dap_caf_expiry' => 'Validade DAP/CAF',
            'registration_number' => 'Nº Matrícula',
            'admission_date' => 'Data Admissão',
            'property_name' => 'Propriedade',
            'property_area' => 'Área (ha)',
            'address' => 'Endereço',
            'district' => 'Bairro',
            'city' => 'Cidade',
            'state' => 'UF',
            'zip_code' => 'CEP',
            'phone' => 'Telefone',
            'whatsapp' => 'WhatsApp',
            'bank_name' => 'Banco',
            'bank_agency' => 'Agência',
            'bank_account' => 'Conta',
            'pix_key' => 'Chave PIX',
        ];
    }
}
