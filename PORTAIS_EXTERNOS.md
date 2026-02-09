# Portais Externos e Autenticação Google OAuth 🚀

Este documento descreve a implementação dos portais externos para Prestadores de Serviço e Associados, com autenticação via Google OAuth.

## 📋 Visão Geral

### Funcionalidades Implementadas

#### 🌐 Autenticação Google OAuth

- Login unificado usando conta Google
- Sincronização automática de usuários
- Redirecionamento inteligente baseado em perfil (role)
- Página de login customizada e responsiva

#### 👨‍🌾 Portal do Associado (`/associate`)

**Dashboards e Visualizações:**

- Dashboard com estatísticas gerais
- Visualização de projetos de venda
- Detalhes completos de cada projeto (progresso, entregas, pagamentos)
- Histórico de entregas com filtros
- Extrato financeiro completo (ledger)

**Funcionalidades:**

- Acompanhamento de projetos ativos
- Visualização de entregas pendentes e realizadas
- Consulta de saldo e transações financeiras
- Filtros por data e status
- Interface otimizada para mobile

#### 🔧 Portal do Prestador de Serviço (`/provider`)

**Dashboards e Visualizações:**

- Dashboard com ordens pendentes e em andamento
- Listagem de ordens de serviço atribuídas
- Histórico de serviços prestados

**Funcionalidades:**

- Registro de serviços prestados (horas, valor, descrição)
- Upload de comprovantes (PDF, imagens)
- Visualização de saldo e pagamentos
- Filtros por status e período
- Interface responsiva com estilo Bento

### 🎨 Design System - Layout Bento

O layout utiliza o conceito **Bento Grid**, focado em:

- **Mobile First**: Otimizado para dispositivos móveis
- **Responsivo**: Adapta-se a tablets e desktops
- **Cards modulares**: Informações organizadas em blocos visuais
- **Usabilidade**: Navegação intuitiva e ações rápidas
- **Performance**: CSS puro, sem dependências pesadas

## 🔧 Configuração

### 1. Instalar Dependências

O Laravel Socialite já foi instalado:

```bash
composer require laravel/socialite
```

### 2. Configurar Google OAuth

#### Passo 1: Criar Projeto no Google Cloud Console

1. Acesse: https://console.cloud.google.com/
2. Crie um novo projeto ou selecione um existente
3. Ative a **Google+ API** ou **Google Identity**

#### Passo 2: Criar Credenciais OAuth 2.0

1. Vá para **APIs & Services > Credentials**
2. Clique em **Create Credentials > OAuth 2.0 Client ID**
3. Configure a tela de consentimento (OAuth consent screen):
    - User Type: External
    - Nome do aplicativo: SGC - Sistema de Gestão de Cooperativa
    - Domínio autorizado: seu domínio
4. Tipo de aplicativo: **Web application**
5. Adicione as **Authorized redirect URIs**:
    ```
    http://localhost:8000/auth/google/callback
    http://127.0.0.1:8000/auth/google/callback
    https://seudominio.com.br/auth/google/callback
    ```
6. Copie o **Client ID** e **Client Secret**

#### Passo 3: Configurar `.env`

Adicione as credenciais no arquivo `.env`:

```env
# Google OAuth
GOOGLE_CLIENT_ID=seu-client-id-aqui.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=seu-client-secret-aqui
GOOGLE_REDIRECT_URI=${APP_URL}/auth/google/callback
```

### 3. Executar Migrations

```bash
php artisan migrate
```

Isso adicionará os campos necessários na tabela `users`:

- `google_id` - ID do usuário no Google
- `avatar` - URL da foto de perfil
- `password` (nullable) - Senha opcional

### 4. Configurar Roles/Permissões

Certifique-se de que os usuários tenham as roles corretas:

**Para Associados:**

```php
$user->assignRole('associate');
```

**Para Prestadores de Serviço:**

```php
$user->assignRole('service_provider');
```

**Para Administradores:**

```php
$user->assignRole('admin');
```

### 5. Vincular Email aos Registros

Os portais buscam registros vinculados ao email do usuário:

**Prestador de Serviço:**

```php
ServiceProvider::where('email', $user->email)->first();
```

**Associado:**

```php
Associate::where('email', $user->email)->first();
```

**Importante**: Cadastre emails nos modelos `ServiceProvider` e `Associate` para que os usuários possam acessar os portais.

## 🚀 Uso

### Fluxo de Autenticação

1. Usuário acessa a página inicial `/`
2. Clica em "Entrar com Google"
3. É redirecionado para o Google OAuth
4. Após autorização, retorna para a aplicação
5. Sistema cria ou atualiza o usuário automaticamente
6. Redireciona para o portal adequado:
    - `/provider/dashboard` - Prestador de Serviço
    - `/associate/dashboard` - Associado
    - `/admin` - Administrador

### Rotas Disponíveis

#### Autenticação

- `GET /` - Página inicial/login
- `GET /auth/google` - Inicia OAuth Google
- `GET /auth/google/callback` - Callback OAuth
- `POST /logout` - Logout

#### Portal do Prestador (`/provider/*`)

- `GET /provider/dashboard` - Dashboard principal
- `GET /provider/orders` - Lista de ordens de serviço
- `GET /provider/orders/{order}/work` - Formulário de registro de serviço
- `POST /provider/orders/{order}/work` - Salvar serviço prestado
- `GET /provider/works` - Histórico de serviços

#### Portal do Associado (`/associate/*`)

- `GET /associate/dashboard` - Dashboard principal
- `GET /associate/projects` - Lista de projetos
- `GET /associate/projects/{project}` - Detalhes do projeto
- `GET /associate/deliveries` - Lista de entregas
- `GET /associate/ledger` - Extrato financeiro

## 📱 Recursos Mobile

### Interface Otimizada

- **Touch-friendly**: Botões e áreas clicáveis amplas
- **Scroll suave**: Listas otimizadas para scroll vertical
- **Tabelas responsivas**: Tables com scroll horizontal em mobile
- **Navigation tabs**: Menu fixo no topo com scroll horizontal

### Performance

- CSS inline para reduzir requisições
- Sem dependências JavaScript (apenas HTML/CSS)
- Imagens otimizadas e lazy loading
- Caching de assets

## 🔒 Segurança

### Middleware

Todas as rotas dos portais usam o middleware `auth`:

```php
Route::prefix('provider')->middleware('auth')->group(...)
Route::prefix('associate')->middleware('auth')->group(...)
```

### Validação de Acesso

Os controllers verificam se o usuário possui registro vinculado:

```php
$provider = ServiceProvider::where('email', $user->email)->first();
if (!$provider) {
    return redirect('/')->with('error', 'Não cadastrado...');
}
```

### CSRF Protection

Todos os formulários incluem `@csrf` token.

## 🎨 Personalização

### Cores do Sistema (CSS Variables)

```css
--color-primary: #10b981; /* Verde principal */
--color-primary-dark: #059669; /* Verde escuro */
--color-secondary: #6366f1; /* Roxo */
--color-danger: #ef4444; /* Vermelho */
--color-warning: #f59e0b; /* Amarelo */
--color-success: #10b981; /* Verde sucesso */
```

### Modificar Layout

Edite o arquivo principal:

```
resources/views/layouts/bento.blade.php
```

### Customizar Views

- Prestador: `resources/views/provider/*.blade.php`
- Associado: `resources/views/associate/*.blade.php`

## 🐛 Troubleshooting

### Erro: "Unauthorized redirect_uri"

- Verifique se a URI de callback está registrada no Google Console
- Confirme que a URL no `.env` está correta

### Erro: "Você não está cadastrado como prestador/associado"

- Certifique-se de que o email do usuário Google corresponde ao email cadastrado em `service_providers` ou `associates`
- Verifique se o registro possui o campo `email` preenchido

### Erro ao fazer login (página em branco)

- Verifique os logs: `storage/logs/laravel.log`
- Confirme que as migrations foram executadas
- Teste se o Google OAuth está configurado corretamente

### Timezone inválida

No arquivo `config/app.php`, use um timezone válido:

```php
'timezone' => 'America/Sao_Paulo',
```

Não use offset direto como `-03:00`.

## 📊 Próximos Passos

### Melhorias Sugeridas

- [ ] Adicionar notificações push
- [ ] Implementar chat entre associado e cooperativa
- [ ] Sistema de upload de fotos de entregas
- [ ] Dashboard com gráficos (Chart.js)
- [ ] Exportação de extratos em PDF
- [ ] Modo offline com Service Workers
- [ ] Multi-idioma (i18n)

### Integração Filament Admin

Para usar Google OAuth também no painel Filament, crie um FilamentPlugin customizado ou use o SimpleLightPHP Socialite plugin.

## 📞 Suporte

Para dúvidas ou problemas:

1. Verifique os logs em `storage/logs/laravel.log`
2. Consulte a documentação do Laravel Socialite: https://laravel.com/docs/socialite
3. Documentação Google OAuth: https://developers.google.com/identity/protocols/oauth2

---

**Desenvolvido com ❤️ para cooperativas agrícolas**
