<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\TenantResource\Pages;
use App\Models\Tenant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationGroup = 'Gestão de Organizações';

    protected static ?string $navigationLabel = 'Organizações';

    protected static ?string $modelLabel = 'Organização';

    protected static ?string $pluralModelLabel = 'Organizações';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Informações da Organização')
                    ->tabs([
                        // Aba 1: Informações Básicas
                        Forms\Components\Tabs\Tab::make('Básico')
                            ->icon('heroicon-o-building-office')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nome Fantasia')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug($state)))
                                    ->columnSpan(2),
                                
                                Forms\Components\TextInput::make('legal_name')
                                    ->label('Razão Social')
                                    ->maxLength(255)
                                    ->columnSpan(2),
                                
                                Forms\Components\TextInput::make('slug')
                                    ->label('Identificador (Slug)')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true)
                                    ->helperText('Identificador único da organização (URL amigável)'),
                                
                                Forms\Components\TextInput::make('cnpj')
                                    ->label('CNPJ')
                                    ->mask('99.999.999/9999-99')
                                    ->unique(ignoreRecord: true)
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
                                
                                Forms\Components\Toggle::make('active')
                                    ->label('Organização Ativa')
                                    ->default(true)
                                    ->helperText('Organizações inativas não podem ser acessadas')
                                    ->columnSpan(2),
                            ])
                            ->columns(4),

                        // Aba 2: Contato
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
                                    ->prefix('https://')
                                    ->placeholder('www.exemplo.com.br')
                                    ->extraInputAttributes(['type' => 'text']), // Evita erro de validação nativa do browser que bloqueia save se campo estiver em aba oculta
                            ])
                            ->columns(2),

                        // Aba 3: Endereço
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
                                
                                Forms\Components\TextInput::make('latitude')
                                    ->label('Latitude')
                                    ->numeric()
                                    ->placeholder('-20.4697')
                                    ->helperText('Coordenada GPS (opcional)'),
                                
                                Forms\Components\TextInput::make('longitude')
                                    ->label('Longitude')
                                    ->numeric()
                                    ->placeholder('-54.6201')
                                    ->helperText('Coordenada GPS (opcional)'),
                            ])
                            ->columns(3),

                        // Aba 4: Branding
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
                                    ->helperText('Logo principal (formato PNG ou SVG recomendado)')
                                    ->columnSpan(2),
                                
                                Forms\Components\FileUpload::make('logo_dark')
                                    ->label('Logo (Tema Escuro)')
                                    ->image()
                                    ->directory('tenants/logos')
                                    ->imageEditor()
                                    ->maxSize(2048)
                                    ->helperText('Versão do logo para tema escuro (opcional)')
                                    ->columnSpan(2),
                                
                                Forms\Components\FileUpload::make('favicon')
                                    ->label('Favicon')
                                    ->image()
                                    ->directory('tenants/favicons')
                                    ->imageEditorAspectRatios(['1:1'])
                                    ->maxSize(512)
                                    ->helperText('Ícone 32x32px ou 64x64px'),
                                
                                Forms\Components\ColorPicker::make('primary_color')
                                    ->label('Cor Primária')
                                    ->default('#10b981')
                                    ->helperText('Cor principal da marca'),
                                
                                Forms\Components\ColorPicker::make('secondary_color')
                                    ->label('Cor Secundária')
                                    ->default('#6366f1')
                                    ->helperText('Cor secundária'),
                                
                                Forms\Components\ColorPicker::make('accent_color')
                                    ->label('Cor de Destaque')
                                    ->default('#f59e0b')
                                    ->helperText('Cor para destaques e CTAs'),
                            ])
                            ->columns(3),

                        // Aba 5: Institucional
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
                                    ->helperText('Liste os principais valores da organização')
                                    ->columnSpan(2),
                            ])
                            ->columns(2),

                        // Aba 6: Portal Público
                        Forms\Components\Tabs\Tab::make('Portal Público')
                            ->icon('heroicon-o-globe-alt')
                            ->schema([
                                Forms\Components\Toggle::make('has_public_portal')
                                    ->label('Ativar Portal Público')
                                    ->helperText('Permite que visitantes acessem informações públicas')
                                    ->live()
                                    ->columnSpan(2),
                                
                                Forms\Components\TextInput::make('public_slug')
                                    ->label('URL do Portal')
                                    ->maxLength(100)
                                    ->unique(ignoreRecord: true)
                                    ->prefix(url('/portal') . '/')
                                    ->helperText('URL amigável para acesso público')
                                    ->visible(fn (Forms\Get $get) => $get('has_public_portal')),
                                
                                Forms\Components\Textarea::make('public_description')
                                    ->label('Descrição Pública')
                                    ->rows(4)
                                    ->maxLength(1000)
                                    ->helperText('Texto que aparecerá na página pública')
                                    ->visible(fn (Forms\Get $get) => $get('has_public_portal'))
                                    ->columnSpan(2),
                                
                                Forms\Components\CheckboxList::make('public_features')
                                    ->label('Recursos Públicos Ativos')
                                    ->options([
                                        'about' => 'Sobre Nós',
                                        'contact' => 'Formulário de Contato',
                                        'news' => 'Notícias',
                                        'gallery' => 'Galeria de Fotos',
                                        'products' => 'Produtos',
                                        'services' => 'Serviços',
                                        'team' => 'Nossa Equipe',
                                        'partners' => 'Parceiros',
                                    ])
                                    ->columns(2)
                                    ->helperText('Selecione quais recursos estarão disponíveis')
                                    ->visible(fn (Forms\Get $get) => $get('has_public_portal'))
                                    ->columnSpan(2),
                            ])
                            ->columns(2),

                        // Aba 7: Redes Sociais
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
                                    ->helperText('Ex: facebook, instagram, twitter, linkedin, youtube, whatsapp')
                                    ->columnSpan(2),
                            ])
                            ->columns(2),

                        // Aba 8: Dados Bancários
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

                        // Aba 9: Responsável Legal
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
                                    ->placeholder('Ex: Presidente, Diretor, etc.')
                                    ->helperText('Cargo do responsável legal na organização'),
                            ])
                            ->columns(2),

                        // Aba 10: Configurações Avançadas
                        Forms\Components\Tabs\Tab::make('Configurações')
                            ->icon('heroicon-o-cog-6-tooth')
                            ->schema([
                                Forms\Components\KeyValue::make('document_settings')
                                    ->label('Configurações de Documentos')
                                    ->keyLabel('Configuração')
                                    ->valueLabel('Valor')
                                    ->addActionLabel('Adicionar Configuração')
                                    ->helperText('Configurações personalizadas para geração de documentos')
                                    ->columnSpan(2),
                                
                                Forms\Components\KeyValue::make('settings')
                                    ->label('Configurações Gerais')
                                    ->keyLabel('Chave')
                                    ->valueLabel('Valor')
                                    ->addActionLabel('Adicionar Configuração')
                                    ->helperText('Configurações adicionais da organização')
                                    ->columnSpan(2),
                            ])
                            ->columns(2),
                    ])
                    ->columnSpanFull()
                    ->persistTabInQueryString(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('slug')
                    ->label('Identificador')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->badge(),
                
                Tables\Columns\IconColumn::make('active')
                    ->label('Status')
                    ->boolean()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('users_count')
                    ->label('Usuários')
                    ->counts('users')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criada em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Atualizada em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('active')
                    ->label('Status')
                    ->placeholder('Todas')
                    ->trueLabel('Apenas Ativas')
                    ->falseLabel('Apenas Inativas'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                // BLOQUEADO: Organizações (tenants) não podem ser deletadas.
                // Use desativação (active = false) ao invés de exclusão.
            ])
            ->bulkActions([
                // BLOQUEADO: Sem ações destrutivas em massa para organizações.
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
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }
}
