<?php

namespace App\Filament\Pages;

use App\Models\Tenant;
use App\Models\TenantCloudStorageConnection;
use App\Models\TenantUser;
use App\Models\AssociateReceipt;
use App\Jobs\SyncAssociateReceiptToDrive;
use App\Jobs\SyncTenantStoredFileToDrive;
use App\Services\TenantGoogleDriveService;
use App\Services\GoogleDriveClientFactory;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class OrganizationSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;
    use HasPageShield;

    public static function canAccess(array $parameters = []): bool
    {
        $user = Filament::auth()->user();

        return $user ? $user->can(static::getPermissionName()) : false;
    }

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static string $view = 'filament.pages.organization-settings';

    protected static ?string $navigationGroup = 'Sistema';

    protected static ?string $navigationLabel = 'Minha Organização';

    protected static ?string $title = 'Configurações da Organização';

    protected static ?int $navigationSort = 5;

    public ?array $data = [];

    public function mount(): void
    {
        $tenantId = session('tenant_id');
        $tenant = $tenantId ? Tenant::find($tenantId) : null;

        if (! $tenant) {
            $this->redirect(route('filament.admin.pages.dashboard'));
            return;
        }

        $driveConnection = $this->canManageGoogleDrive()
            ? $this->googleDriveConnection()
            : null;

        $this->form->fill([
            // Básico
            'name'                     => $tenant->name,
            'legal_name'               => $tenant->legal_name,
            'cnpj'                     => $tenant->cnpj,
            'state_registration'       => $tenant->state_registration,
            'municipal_registration'   => $tenant->municipal_registration,
            'foundation_date'          => $tenant->foundation_date,
            // Contato
            'email'                    => $tenant->email,
            'phone'                    => $tenant->phone,
            'mobile'                   => $tenant->mobile,
            'website'                  => $tenant->website,
            // Endereço
            'zip_code'                 => $tenant->zip_code,
            'address'                  => $tenant->address,
            'address_number'           => $tenant->address_number,
            'address_complement'       => $tenant->address_complement,
            'neighborhood'             => $tenant->neighborhood,
            'city'                     => $tenant->city,
            'state'                    => $tenant->state,
            'country'                  => $tenant->country,
            // Identidade visual
            'logo'                     => $tenant->logo,
            'logo_dark'                => $tenant->logo_dark,
            'primary_color'            => $tenant->primary_color,
            'secondary_color'          => $tenant->secondary_color,
            'accent_color'             => $tenant->accent_color,
            // Institucional
            'description'              => $tenant->description,
            'mission'                  => $tenant->mission,
            'vision'                   => $tenant->vision,
            'values'                   => $tenant->values,
            // Dados bancários
            'bank_name'                => $tenant->bank_name,
            'bank_code'                => $tenant->bank_code,
            'bank_agency'              => $tenant->bank_agency,
            'bank_account'             => $tenant->bank_account,
            'pix_key'                  => $tenant->pix_key,
            // Responsável legal
            'legal_representative_name' => $tenant->legal_representative_name,
            'legal_representative_cpf'  => $tenant->legal_representative_cpf,
            'legal_representative_role' => $tenant->legal_representative_role,
            // Redes sociais
            'social_media'             => $tenant->social_media ?? [],
            'google_drive_client_id'   => $driveConnection?->oauth_client_id,
            'google_drive_client_secret' => null,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Configurações da Organização')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Básico')
                            ->icon('heroicon-o-building-office')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nome Fantasia')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('legal_name')
                                    ->label('Razão Social')
                                    ->maxLength(255)
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('cnpj')
                                    ->label('CNPJ')
                                    ->mask('99.999.999/9999-99')
                                    ->maxLength(18),

                                Forms\Components\TextInput::make('state_registration')
                                    ->label('Inscrição Estadual')
                                    ->maxLength(50),

                                Forms\Components\TextInput::make('municipal_registration')
                                    ->label('Inscrição Municipal')
                                    ->maxLength(50),

                                Forms\Components\DatePicker::make('foundation_date')
                                    ->label('Data de Fundação')
                                    ->displayFormat('d/m/Y'),
                            ])
                            ->columns(4),

                        Forms\Components\Tabs\Tab::make('Contato')
                            ->icon('heroicon-o-phone')
                            ->schema([
                                Forms\Components\TextInput::make('email')
                                    ->label('E-mail')
                                    ->email()
                                    ->maxLength(191),

                                Forms\Components\TextInput::make('phone')
                                    ->label('Telefone')
                                    ->tel()
                                    ->mask('(99) 9999-9999')
                                    ->maxLength(20),

                                Forms\Components\TextInput::make('mobile')
                                    ->label('Celular')
                                    ->tel()
                                    ->mask('(99) 99999-9999')
                                    ->maxLength(20),

                                Forms\Components\TextInput::make('website')
                                    ->label('Website')
                                    ->url()
                                    ->maxLength(255)
                                    ->extraInputAttributes(['type' => 'text']),
                            ])
                            ->columns(2),

                        Forms\Components\Tabs\Tab::make('Endereço')
                            ->icon('heroicon-o-map-pin')
                            ->schema([
                                Forms\Components\TextInput::make('zip_code')
                                    ->label('CEP')
                                    ->mask('99999-999')
                                    ->maxLength(10),

                                Forms\Components\TextInput::make('address')
                                    ->label('Logradouro')
                                    ->maxLength(255)
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('address_number')
                                    ->label('Número')
                                    ->maxLength(20),

                                Forms\Components\TextInput::make('address_complement')
                                    ->label('Complemento')
                                    ->maxLength(100)
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('neighborhood')
                                    ->label('Bairro')
                                    ->maxLength(100),

                                Forms\Components\TextInput::make('city')
                                    ->label('Cidade')
                                    ->maxLength(100),

                                Forms\Components\TextInput::make('state')
                                    ->label('Estado')
                                    ->maxLength(2)
                                    ->placeholder('MS'),

                                Forms\Components\TextInput::make('country')
                                    ->label('País')
                                    ->default('Brasil')
                                    ->maxLength(50),
                            ])
                            ->columns(3),

                        Forms\Components\Tabs\Tab::make('Identidade Visual')
                            ->icon('heroicon-o-paint-brush')
                            ->schema([
                                Forms\Components\FileUpload::make('logo')
                                    ->label('Logo')
                                    ->image()
                                    ->disk('public')
                                    ->visibility('public')
                                    ->directory('tenants/logos')
                                    ->imageEditor()
                                    ->imageEditorAspectRatios(['16:9', '4:3', '1:1'])
                                    ->maxSize(2048)
                                    ->helperText('Logo principal (PNG ou SVG recomendado)')
                                    ->columnSpan(2),

                                Forms\Components\FileUpload::make('logo_dark')
                                    ->label('Logo (Tema Escuro)')
                                    ->image()
                                    ->disk('public')
                                    ->visibility('public')
                                    ->directory('tenants/logos')
                                    ->imageEditor()
                                    ->maxSize(2048)
                                    ->helperText('Versão do logo para tema escuro (opcional)')
                                    ->columnSpan(2),

                                Forms\Components\ColorPicker::make('primary_color')
                                    ->label('Cor Primária')
                                    ->helperText('Cor principal da marca'),

                                Forms\Components\ColorPicker::make('secondary_color')
                                    ->label('Cor Secundária')
                                    ->helperText('Cor secundária'),

                                Forms\Components\ColorPicker::make('accent_color')
                                    ->label('Cor de Destaque')
                                    ->helperText('Cor para destaques e CTAs'),
                            ])
                            ->columns(3),

                        Forms\Components\Tabs\Tab::make('Institucional')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Forms\Components\Textarea::make('description')
                                    ->label('Descrição')
                                    ->rows(3)
                                    ->maxLength(1000)
                                    ->helperText('Breve descrição da organização')
                                    ->columnSpan(2),

                                Forms\Components\Textarea::make('mission')
                                    ->label('Missão')
                                    ->rows(3)
                                    ->maxLength(500)
                                    ->columnSpan(2),

                                Forms\Components\Textarea::make('vision')
                                    ->label('Visão')
                                    ->rows(3)
                                    ->maxLength(500)
                                    ->columnSpan(2),

                                Forms\Components\Textarea::make('values')
                                    ->label('Valores')
                                    ->rows(3)
                                    ->maxLength(500)
                                    ->columnSpan(2),
                            ])
                            ->columns(2),

                        Forms\Components\Tabs\Tab::make('Dados Bancários')
                            ->icon('heroicon-o-banknotes')
                            ->schema([
                                Forms\Components\TextInput::make('bank_name')
                                    ->label('Nome do Banco')
                                    ->maxLength(100),

                                Forms\Components\TextInput::make('bank_code')
                                    ->label('Código do Banco')
                                    ->mask('999')
                                    ->maxLength(10),

                                Forms\Components\TextInput::make('bank_agency')
                                    ->label('Agência')
                                    ->maxLength(20),

                                Forms\Components\TextInput::make('bank_account')
                                    ->label('Conta')
                                    ->maxLength(20),

                                Forms\Components\TextInput::make('pix_key')
                                    ->label('Chave PIX')
                                    ->maxLength(191)
                                    ->helperText('CPF, CNPJ, e-mail, telefone ou chave aleatória')
                                    ->columnSpan(2),
                            ])
                            ->columns(2),

                        Forms\Components\Tabs\Tab::make('Responsável Legal')
                            ->icon('heroicon-o-user-circle')
                            ->schema([
                                Forms\Components\TextInput::make('legal_representative_name')
                                    ->label('Nome Completo')
                                    ->maxLength(255)
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('legal_representative_cpf')
                                    ->label('CPF')
                                    ->mask('999.999.999-99')
                                    ->maxLength(14),

                                Forms\Components\TextInput::make('legal_representative_role')
                                    ->label('Cargo/Função')
                                    ->maxLength(100)
                                    ->placeholder('Ex: Presidente, Diretor, etc.'),
                            ])
                            ->columns(2),

                        Forms\Components\Tabs\Tab::make('Redes Sociais')
                            ->icon('heroicon-o-share')
                            ->schema([
                                Forms\Components\KeyValue::make('social_media')
                                    ->label('Links de Redes Sociais')
                                    ->keyLabel('Rede Social')
                                    ->valueLabel('URL/Link')
                                    ->addActionLabel('Adicionar Rede Social')
                                    ->keyPlaceholder('facebook')
                                    ->valuePlaceholder('https://facebook.com/suapagina')
                                    ->helperText('Ex: facebook, instagram, twitter, linkedin, youtube')
                                    ->columnSpan(2),
                            ])
                            ->columns(2),

                        Forms\Components\Tabs\Tab::make('Google Drive')
                            ->icon('heroicon-o-cloud-arrow-up')
                            ->visible(fn (): bool => $this->canManageGoogleDrive())
                            ->schema([
                                Forms\Components\TextInput::make('google_drive_client_id')
                                    ->label('OAuth Client ID')
                                    ->autocomplete(false)
                                    ->maxLength(512),
                                Forms\Components\TextInput::make('google_drive_client_secret')
                                    ->label('OAuth Client Secret')
                                    ->password()
                                    ->revealable()
                                    ->autocomplete('new-password')
                                    ->placeholder(fn (): string => $this->googleDriveConfigured()
                                        ? 'Mantido sem alteração'
                                        : '')
                                    ->maxLength(1024),
                                Forms\Components\Placeholder::make('google_drive_redirect_uri')
                                    ->label('URI de redirecionamento')
                                    ->content(fn (): string => app(GoogleDriveClientFactory::class)->redirectUri()),
                                Forms\Components\Actions::make([
                                    Forms\Components\Actions\Action::make('save_google_drive_credentials')
                                        ->label('Salvar credenciais')
                                        ->icon('heroicon-o-key')
                                        ->action(fn () => $this->saveGoogleDriveCredentials()),
                                ]),
                                Forms\Components\Placeholder::make('google_drive_status')
                                    ->label('Armazenamento de documentos')
                                    ->content(function (): string {
                                        $connection = $this->googleDriveConnection();
                                        if (! $connection || ! $connection->hasOAuthConfiguration()) {
                                            return 'Credenciais pendentes';
                                        }

                                        if ($connection->status === 'revoked') {
                                            return 'Não conectado';
                                        }

                                        if ($connection->status === 'configured') {
                                            return 'Credenciais salvas';
                                        }

                                        if ($connection->status === 'error') {
                                            return 'A conexão precisa ser renovada';
                                        }

                                        if ($connection->status !== 'active') {
                                            return 'Credenciais pendentes';
                                        }

                                        return 'Conectado'.($connection->last_sync_at
                                            ? ' · última sincronização '.$connection->last_sync_at->format('d/m/Y H:i')
                                            : ' · aguardando o primeiro documento');
                                    }),
                                Forms\Components\Placeholder::make('google_drive_scope')
                                    ->label('Privacidade')
                                    ->content('O SGC acessa somente os arquivos e pastas que ele próprio cria nesta conexão.'),
                                Forms\Components\Actions::make([
                                    Forms\Components\Actions\Action::make('connect_google_drive')
                                        ->label(fn (): string => $this->googleDriveConnection()?->status === 'active'
                                            ? 'Reconectar Google Drive'
                                            : 'Conectar Google Drive')
                                        ->icon('heroicon-o-link')
                                        ->color('primary')
                                        ->disabled(fn (): bool => ! $this->googleDriveConfigured())
                                        ->url(fn (): string => route('settings.google-drive.connect', [
                                            'tenant' => $this->currentTenant()->slug,
                                        ])),
                                    Forms\Components\Actions\Action::make('disconnect_google_drive')
                                        ->label('Desconectar')
                                        ->icon('heroicon-o-link-slash')
                                        ->color('danger')
                                        ->requiresConfirmation()
                                        ->modalDescription('Novos documentos deixarão de ser enviados. Os arquivos existentes permanecerão no Drive da organização.')
                                        ->visible(fn (): bool => $this->googleDriveConnection()?->status === 'active')
                                        ->action(fn () => $this->disconnectGoogleDrive()),
                                    Forms\Components\Actions\Action::make('sync_google_drive')
                                        ->label('Sincronizar agora')
                                        ->icon('heroicon-o-arrow-path')
                                        ->visible(fn (): bool => $this->googleDriveConnection()?->status === 'active')
                                        ->action(fn () => $this->syncGoogleDrive()),
                                ]),
                            ]),
                    ])
                    ->columnSpanFull()
                    ->persistTabInQueryString(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $tenantId = session('tenant_id');
        $tenant = $tenantId ? Tenant::find($tenantId) : null;

        if (! $tenant) {
            Notification::make()
                ->danger()
                ->title('Organização não encontrada')
                ->send();

            return;
        }

        $data = $this->form->getState();

        if ($this->canManageGoogleDrive()
            && (trim((string) ($data['google_drive_client_id'] ?? '')) !== ''
                || trim((string) ($data['google_drive_client_secret'] ?? '')) !== '')) {
            $this->persistGoogleDriveCredentials($data);
            $this->data['google_drive_client_secret'] = null;
        }

        // Campos que o admin NÃO pode alterar
        unset(
            $data['slug'],
            $data['active'],
            $data['has_public_portal'],
            $data['settings'],
            $data['document_settings'],
            $data['google_drive_client_id'],
            $data['google_drive_client_secret'],
        );

        $tenant->update($data);

        Notification::make()
            ->success()
            ->title('Dados atualizados com sucesso!')
            ->body('As informações da sua organização foram salvas.')
            ->send();
    }

    protected function getFormActions(): array
    {
        return [];
    }

    public function disconnectGoogleDrive(): void
    {
        abort_unless($this->canManageGoogleDrive(), 403);

        $connection = $this->googleDriveConnection();
        if ($connection) {
            app(TenantGoogleDriveService::class)->disconnect($connection);
            activity('cloud_storage')->causedBy(auth()->user())->performedOn($this->currentTenant())
                ->withProperties(['tenant_id' => $this->currentTenant()->id, 'provider' => 'google_drive'])
                ->log('Google Drive desconectado');
        }

        Notification::make()->success()->title('Google Drive desconectado')->send();
    }

    public function saveGoogleDriveCredentials(): void
    {
        abort_unless($this->canManageGoogleDrive(), 403);

        $this->persistGoogleDriveCredentials($this->data);
        $this->data['google_drive_client_secret'] = null;

        Notification::make()->success()->title('Credenciais salvas')->send();
    }

    public function syncGoogleDrive(): void
    {
        abort_unless($this->canManageGoogleDrive(), 403);

        $tenant = $this->currentTenant();
        abort_unless($this->googleDriveConnection()?->status === 'active', 409);

        AssociateReceipt::query()
            ->where('tenant_id', $tenant->id)
            ->select('id')
            ->chunkById(100, function ($receipts): void {
                foreach ($receipts as $receipt) {
                    SyncAssociateReceiptToDrive::dispatch((int) $receipt->id);
                }
            });
        SyncTenantStoredFileToDrive::dispatchExistingForTenant($tenant->id);

        activity('cloud_storage')->causedBy(auth()->user())->performedOn($tenant)
            ->withProperties(['tenant_id' => $tenant->id, 'provider' => 'google_drive'])
            ->log('Sincronizacao manual do Google Drive solicitada');

        Notification::make()->success()->title('Sincronização adicionada à fila')->send();
    }

    private function persistGoogleDriveCredentials(array $data): void
    {
        $tenant = $this->currentTenant();
        $connection = $this->googleDriveConnection();
        $clientId = trim((string) ($data['google_drive_client_id'] ?? ''));
        $clientSecret = trim((string) ($data['google_drive_client_secret'] ?? ''));
        $currentClientId = trim((string) $connection?->oauth_client_id);
        $currentClientSecret = trim((string) $connection?->oauth_client_secret);

        if ($clientId === '' || ! str_ends_with($clientId, '.apps.googleusercontent.com')) {
            throw ValidationException::withMessages([
                'data.google_drive_client_id' => 'Informe um OAuth Client ID válido.',
            ]);
        }

        if (($currentClientSecret === '' || ! hash_equals($currentClientId, $clientId)) && $clientSecret === '') {
            throw ValidationException::withMessages([
                'data.google_drive_client_secret' => 'Informe o OAuth Client Secret.',
            ]);
        }

        if ($clientSecret !== '' && mb_strlen($clientSecret) < 8) {
            throw ValidationException::withMessages([
                'data.google_drive_client_secret' => 'O OAuth Client Secret é inválido.',
            ]);
        }

        $credentialsChanged = ! $connection
            || ! hash_equals($currentClientId, $clientId)
            || ($clientSecret !== '' && ! hash_equals($currentClientSecret, $clientSecret));

        $connection ??= new TenantCloudStorageConnection();
        $values = [
            'tenant_id' => $tenant->id,
            'provider' => 'google_drive',
            'oauth_client_id' => $clientId,
            'oauth_client_secret' => $clientSecret !== '' ? $clientSecret : $currentClientSecret,
            'status' => $credentialsChanged || $connection->status !== 'active'
                ? 'configured'
                : 'active',
            'last_error' => null,
        ];

        if ($credentialsChanged) {
            $values += [
                'refresh_token' => null,
                'granted_scopes' => [],
                'root_folder_id' => null,
                'connected_by_user_id' => null,
                'connected_at' => null,
                'last_sync_at' => null,
            ];
        }

        DB::transaction(function () use ($connection, $values, $credentialsChanged, $tenant): void {
            $connection->forceFill($values)->save();

            if ($credentialsChanged) {
                DB::table('cloud_documents')
                    ->where('tenant_id', $tenant->id)
                    ->update([
                        'remote_file_id' => null,
                        'remote_folder_id' => null,
                        'status' => 'pending',
                        'last_error' => null,
                        'updated_at' => now(),
                    ]);
            }
        });

        activity('cloud_storage')->causedBy(auth()->user())->performedOn($tenant)
            ->withProperties(['tenant_id' => $tenant->id, 'provider' => 'google_drive'])
            ->log('Credenciais OAuth do Google Drive atualizadas');
    }

    private function currentTenant(): Tenant
    {
        return Tenant::query()->findOrFail((int) session('tenant_id'));
    }

    private function googleDriveConnection(): ?TenantCloudStorageConnection
    {
        return TenantCloudStorageConnection::query()
            ->where('tenant_id', (int) session('tenant_id'))
            ->first();
    }

    private function googleDriveConfigured(): bool
    {
        return $this->googleDriveConnection()?->hasOAuthConfiguration() ?? false;
    }

    private function canManageGoogleDrive(): bool
    {
        $user = auth()->user();
        if (! $user || $user->isSuperAdmin()) {
            return false;
        }

        return TenantUser::query()
            ->where('tenant_id', (int) session('tenant_id'))
            ->where('user_id', $user->id)
            ->where('status', true)
            ->where('is_admin', true)
            ->exists();
    }
}
