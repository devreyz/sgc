<?php

namespace App\Filament\Pages;

use App\Models\Tenant;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class OrganizationSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static string $view = 'filament.pages.organization-settings';

    protected static ?string $navigationGroup = 'Sistema';

    protected static ?string $navigationLabel = 'Minha Organização';

    protected static ?string $title = 'Configurações da Organização';

    protected static ?int $navigationSort = 5;

    public ?array $data = [];

    public static function canAccess(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        // super_admin nunca usa este painel (ele tem o painel dedicado)
        if ($user->hasRole('super_admin')) {
            return false;
        }

        // Apenas o role admin da organização tem acesso
        return $user->hasRole('admin');
    }

    public function mount(): void
    {
        $tenantId = session('tenant_id');
        $tenant = $tenantId ? Tenant::find($tenantId) : null;

        if (! $tenant) {
            $this->redirect(route('filament.admin.pages.dashboard'));
            return;
        }

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
                                    ->directory('tenants/logos')
                                    ->imageEditor()
                                    ->imageEditorAspectRatios(['16:9', '4:3', '1:1'])
                                    ->maxSize(2048)
                                    ->helperText('Logo principal (PNG ou SVG recomendado)')
                                    ->columnSpan(2),

                                Forms\Components\FileUpload::make('logo_dark')
                                    ->label('Logo (Tema Escuro)')
                                    ->image()
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

        // Campos que o admin NÃO pode alterar
        unset($data['slug'], $data['active'], $data['has_public_portal'], $data['settings'], $data['document_settings']);

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
}
