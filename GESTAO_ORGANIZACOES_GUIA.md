# 🏢 Gestão Avançada de Organizações (Tenants)

## 📋 Visão Geral

O sistema SGC agora possui um módulo completo de gestão de organizações (tenants) com suporte a:

- ✅ **Identidade Visual Completa** (logo, cores, favicon)
- ✅ **Dados Institucionais** (missão, visão, valores)
- ✅ **Portal Público** para divulgação
- ✅ **Geração de Documentos Personalizados**
- ✅ **Endereço Completo e Geolocalização**
- ✅ **Redes Sociais Integradas**
- ✅ **Dados Bancários para Transações**
- ✅ **Responsável Legal**

---

## 🚀 Novos Recursos

### 1. **Identidade Visual (Branding)**

Cada organização pode personalizar sua identidade visual:

- **Logo Principal**: Upload de logo em PNG/SVG
- **Logo Tema Escuro**: Versão alternativa para modo escuro
- **Favicon**: Ícone personalizado 32x32px ou 64x64px
- **Cores**:
    - Cor Primária (padrão: `#10b981`)
    - Cor Secundária (padrão: `#6366f1`)
    - Cor de Destaque (padrão: `#f59e0b`)

**Uso Prático:**

- Documentos gerados terão o logo e cores da organização
- Interface personalizada por tenant
- Apps instaláveis com branding próprio

---

### 2. **Portal Público**

Sistema de portal público para divulgação da cooperativa/associação:

#### Configurações:

- **URL Personalizada**: `https://seusite.com/portal/cooperativa-modelo`
- **Descrição Pública**: Texto institucional
- **Recursos Ativados**:
    - ✓ Sobre Nós
    - ✓ Formulário de Contato
    - ✓ Notícias
    - ✓ Galeria de Fotos
    - ✓ Produtos
    - ✓ Serviços
    - ✓ Nossa Equipe
    - ✓ Parceiros

#### Exemplo de Acesso:

```
https://seudominio.com/portal/cooperativa-modelo
```

#### Vantagens:

- Presença online profissional
- Captação de novos associados
- Divulgação de produtos e serviços
- Transparência institucional

---

### 3. **Geração de Documentos Personalizados**

#### Configurações Disponíveis:

```json
{
    "header_height": "80",
    "footer_height": "60",
    "margin_top": "20",
    "margin_bottom": "20",
    "show_logo": true,
    "show_watermark": false,
    "paper_size": "A4",
    "orientation": "portrait"
}
```

#### Variáveis Disponíveis nos Documentos:

- `{{tenant.name}}` - Nome da organização
- `{{tenant.legal_name}}` - Razão social
- `{{tenant.cnpj}}` - CNPJ formatado
- `{{tenant.address}}` - Endereço completo
- `{{tenant.phone}}` - Telefone
- `{{tenant.email}}` - E-mail
- `{{tenant.logo_url}}` - URL do logo
- `{{tenant.primary_color}}` - Cor primária

#### Exemplo de Template:

```html
<div style="text-align: center;">
    <img src="{{tenant.logo_url}}" style="height: 80px;" />
    <h1 style="color: {{tenant.primary_color}};">{{tenant.name}}</h1>
    <p>{{tenant.address}}</p>
    <p>CNPJ: {{tenant.cnpj}} | Fone: {{tenant.phone}}</p>
</div>
```

---

### 4. **Dados Institucionais Completos**

#### Informações da Organização:

- **Nome Fantasia**: Nome de uso comum
- **Razão Social**: Nome legal completo
- **CNPJ**: Cadastro Nacional de Pessoa Jurídica
- **Inscrições**: Estadual e Municipal
- **Data de Fundação**: Registro histórico

#### Endereço Completo:

- Logradouro, Número, Complemento
- Bairro, Cidade, Estado, CEP
- País (padrão: Brasil)
- Coordenadas GPS (latitude/longitude) para mapas

#### Contato:

- E-mail institucional
- Telefone fixo
- Celular/WhatsApp
- Website

---

### 5. **Redes Sociais**

Armazene links para todas as redes sociais:

```json
{
    "facebook": "https://facebook.com/cooperativa",
    "instagram": "https://instagram.com/cooperativa",
    "twitter": "https://twitter.com/cooperativa",
    "linkedin": "https://linkedin.com/company/cooperativa",
    "youtube": "https://youtube.com/@cooperativa",
    "whatsapp": "5567999998888"
}
```

**Uso:**

- Botões de compartilhamento
- Footer de documentos
- Portal público
- E-mails institucionais

---

### 6. **Dados Bancários**

Para transações e pagamentos:

- Nome do Banco
- Código do Banco (ex: 001 - Banco do Brasil)
- Agência com dígito
- Conta com dígito
- Chave PIX (CNPJ, e-mail, telefone ou aleatória)

**Uso:**

- Geração de boletos
- Recebimentos
- Relatórios financeiros
- Notas fiscais

---

### 7. **Responsável Legal**

Informações do representante legal da organização:

- Nome Completo
- CPF
- Cargo/Função (Presidente, Diretor, etc.)

**Uso:**

- Assinatura de documentos
- Contratos
- Atas de reunião
- Certificados

---

## 📊 Interface de Gestão

O Filament Admin possui um formulário completo com **10 abas organizadas**:

### Abas do Formulário:

1. **📄 Básico**: Nome, CNPJ, Slug, Status
2. **📞 Contato**: E-mail, Telefones, Website
3. **📍 Endereço**: Endereço completo e coordenadas GPS
4. **🎨 Identidade Visual**: Logos, favicon e cores
5. **📖 Institucional**: Descrição, missão, visão, valores
6. **🌐 Portal Público**: Configuração do site público
7. **📱 Redes Sociais**: Links para mídias sociais
8. **💰 Dados Bancários**: Informações para transações
9. **👤 Responsável Legal**: Representante da organização
10. **⚙️ Configurações**: Ajustes avançados e documentos

---

## 🛠️ Comandos Úteis

### Executar Nova Migration

```bash
php artisan migrate
```

### Recriar Banco com Novos Dados

```bash
php artisan migrate:fresh --seed
```

### Criar Nova Organização via Tinker

```bash
php artisan tinker
```

```php
$tenant = Tenant::create([
    'name' => 'Minha Cooperativa',
    'slug' => 'minha-cooperativa',
    'cnpj' => '12.345.678/0001-90',
    'email' => 'contato@minhacooperativa.com',
    'phone' => '(67) 3333-4444',
    'city' => 'Campo Grande',
    'state' => 'MS',
    'active' => true,
]);
```

---

## 🎯 Casos de Uso

### 1. **Geração de Documentos Institucionais**

Documentos como atas, certificados e relatórios podem ser gerados automaticamente com:

- Logo da organização
- Cores personalizadas
- Dados de contato
- Assinatura do responsável legal

### 2. **Portal de Divulgação**

A cooperativa pode ter um portal público onde:

- Agricultores podem conhecer a cooperativa
- Novos membros podem se candidatar
- Produtos/serviços são divulgados
- Notícias são publicadas

### 3. **Aplicativo Instalável (PWA)**

Com as informações completas, é possível gerar um PWA (Progressive Web App) com:

- Ícone personalizado (favicon)
- Cores da marca
- Nome da organização
- Splash screen customizado

### 4. **Relatórios Financeiros**

Relatórios podem incluir:

- Dados bancários formatados
- Endereço completo
- CNPJ e inscrições
- Logo institucional

### 5. **Contratos e Documentos Legais**

Contratos automáticos com:

- Dados do responsável legal
- CNPJ e endereço
- Assinaturas digitais
- Identidade visual

---

## 🔧 Métodos Helper do Model

```php
// Endereço formatado completo
$tenant->full_address;
// "Rua das Palmeiras, 123, Centro, Campo Grande, MS, 79002-000"

// URLs de Assets
$tenant->logo_url;      // https://site.com/storage/tenants/logos/logo.png
$tenant->logo_dark_url; // https://site.com/storage/tenants/logos/logo-dark.png
$tenant->favicon_url;   // https://site.com/storage/tenants/favicons/favicon.png

// Verificações
$tenant->hasCompleteAddress();  // bool
$tenant->hasBranding();          // bool

// Scopes
Tenant::active()->get();              // Apenas ativos
Tenant::withPublicPortal()->get();    // Com portal público
```

---

## 📝 Exemplo de Dados Completos

```php
Tenant::create([
    // Identificação
    'name' => 'Cooperativa Agrícola do Vale',
    'legal_name' => 'Cooperativa Agrícola do Vale Ltda',
    'slug' => 'cooperativa-vale',
    'cnpj' => '12.345.678/0001-90',

    // Contato
    'email' => 'contato@coopvale.com.br',
    'phone' => '(67) 3333-4444',
    'mobile' => '(67) 99999-8888',
    'website' => 'www.coopvale.com.br',

    // Endereço
    'address' => 'Avenida Brasil',
    'address_number' => '1500',
    'neighborhood' => 'Centro',
    'city' => 'Dourados',
    'state' => 'MS',
    'zip_code' => '79800-000',

    // Branding
    'primary_color' => '#10b981',
    'secondary_color' => '#6366f1',

    // Institucional
    'description' => 'Cooperativa agrícola focada em agricultura sustentável',
    'foundation_date' => '2015-03-20',

    // Portal Público
    'has_public_portal' => true,
    'public_slug' => 'coopvale',

    // Redes Sociais
    'social_media' => [
        'facebook' => 'https://facebook.com/coopvale',
        'instagram' => 'https://instagram.com/coopvale',
    ],

    // Dados Bancários
    'bank_name' => 'Sicoob',
    'bank_code' => '756',
    'bank_agency' => '4321',
    'bank_account' => '12345-6',
    'pix_key' => '12.345.678/0001-90',

    // Responsável
    'legal_representative_name' => 'Maria Santos',
    'legal_representative_cpf' => '987.654.321-00',
    'legal_representative_role' => 'Presidente',
]);
```

---

## ✅ Checklist de Implementação

- [x] Migration criada com todos os campos
- [x] Model atualizado com fillable e casts
- [x] Resource do Filament com formulário em abas
- [x] Seeder com dados de exemplo
- [x] Métodos helper no model
- [x] Suporte a upload de imagens
- [ ] **Implementar rotas de portal público**
- [ ] **Criar templates de documentos**
- [ ] **Integrar geração de PWA**
- [ ] **Desenvolver API pública**

---

## 🎨 Próximos Passos Sugeridos

### 1. **Portal Público**

Criar rotas e controllers para o portal público:

```php
Route::get('/portal/{slug}', [PortalController::class, 'show']);
Route::get('/portal/{slug}/sobre', [PortalController::class, 'about']);
Route::get('/portal/{slug}/contato', [PortalController::class, 'contact']);
```

### 2. **Geração de Documentos PDF**

Implementar sistema de templates com variáveis:

```php
$pdf = DocumentGenerator::make($tenant)
    ->template('contrato-associacao')
    ->variables($data)
    ->generate();
```

### 3. **PWA Builder**

Sistema automático para gerar PWA:

```php
$pwa = PWABuilder::make($tenant)
    ->withIcon($tenant->favicon)
    ->withColors($tenant->primary_color)
    ->generate();
```

### 4. **API Pública**

Endpoint para consulta pública de informações:

```php
GET /api/public/tenants/{slug}
// Retorna dados públicos da organização
```

---

## 📞 Suporte

Para dúvidas ou sugestões sobre as funcionalidades de organizações, entre em contato com a equipe de desenvolvimento.

**Documentação atualizada em:** 15/02/2026
