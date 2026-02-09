# Usuários de Teste - SGC

## Credenciais de Acesso

### Super Admin (Painel Administrativo)

- **URL**: http://127.0.0.1:8000/admin
- **Email**: admin@sgc.com
- **Senha**: password
- **Permissões**: Acesso total ao painel administrativo

### Associado (Portal Externo)

- **URL**: http://127.0.0.1:8000/associate/dashboard
- **Email**: associado@sgc.com
- **Senha**: password
- **Permissões**:
    - Visualizar projetos de venda
    - Visualizar entregas de produção
    - Visualizar livro-razão (ledger)
    - **SEM** acesso ao painel admin

### Prestador de Serviço (Portal Externo)

- **URL**: http://127.0.0.1:8000/provider/dashboard
- **Email**: prestador@sgc.com
- **Senha**: password
- **Permissões**:
    - Visualizar ordens de serviço
    - Registrar trabalhos realizados
    - Visualizar histórico de serviços
    - **SEM** acesso ao painel admin

## Segurança Implementada

### 1. Controle de Acesso ao Painel Admin

- ✅ Apenas roles `super_admin`, `admin` e `financeiro` podem acessar `/admin`
- ✅ Associados e prestadores são **bloqueados** do painel administrativo
- ✅ Usuários inativos não conseguem fazer login

### 2. OAuth Google (Somente Usuários Pré-cadastrados)

- ✅ Login via Google **requer** email previamente registrado no sistema
- ✅ Não é possível criar conta automaticamente via OAuth
- ✅ Tentativas não autorizadas são logadas

### 3. Portais Externos

- ✅ `/provider/*` requer role `service_provider`
- ✅ `/associate/*` requer role `associado`
- ✅ Middleware `CheckUserRole` valida acesso antes de processar requisição

### 4. Permissões Granulares (Filament Shield)

- ✅ Policies geradas para todos os recursos
- ✅ Associados têm apenas permissões de **leitura** (view/view_any)
- ✅ Prestadores podem registrar serviços mas não editar outros dados

## Como Gerenciar Usuários

1. Acesse o painel admin em `/admin`
2. Vá em **Sistema > Usuários**
3. Edite o usuário desejado
4. Na seção **Segurança**, selecione as **Funções (Roles)** apropriadas:
    - `super_admin` - Acesso total
    - `admin` - Gerenciamento geral
    - `financeiro` - Acesso financeiro
    - `associado` - Portal de associados (sem admin)
    - `service_provider` - Portal de prestadores (sem admin)

## Estrutura de Arquivos

### Controllers dos Portais

- `app/Http/Controllers/Provider/ProviderDashboardController.php`
- `app/Http/Controllers/Associate/AssociateDashboardController.php`

### Views dos Portais

- `resources/views/provider/*` - Dashboard, ordens, formulários
- `resources/views/associate/*` - Dashboard, projetos, entregas, ledger

### Middleware Customizado

- `app/Http/Middleware/CheckUserRole.php` - Valida role antes de acessar rotas

### Seeders

- `database/seeders/RolesAndPermissionsSeeder.php` - Roles básicas + usuário admin e associado
- `database/seeders/AssociatePermissionsSeeder.php` - Permissões read-only para associados
- `database/seeders/ServiceProviderSeeder.php` - Role + permissões + usuário prestador

## Comandos Úteis

```bash
# Limpar todos os caches
php artisan optimize:clear

# Recriar roles e permissões
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan db:seed --class=AssociatePermissionsSeeder
php artisan db:seed --class=ServiceProviderSeeder

# Gerar policies do Shield
php artisan shield:generate --all

# Criar um super admin interativamente
php artisan shield:super-admin

# Ver rotas registradas
php artisan route:list --path=provider
php artisan route:list --path=associate
```

## Resolução de Problemas

### Erro: "ERR_TOO_MANY_REDIRECTS"

**Causa**: Usuário sem role apropriada tenta acessar portal
**Solução**: Atribuir role correta ao usuário em Sistema > Usuários

### Erro: "403 Forbidden" ao acessar portal

**Causa**: Usuário não tem registro de ServiceProvider ou Associate vinculado
**Solução**:

- Para prestadores: criar registro em `service_providers` com `user_id`
- Para associados: criar registro em `associates` com `user_id`

### Associado consegue ver painéis admin

**Causa**: Role incorreta ou `canAccessPanel` não funcional
**Solução**: Verificar se usuário tem apenas role `associado` (sem admin/super_admin)

### Views dos portais não carregam

**Causa**: Arquivos blade não existem em `resources/views/provider` ou `resources/views/associate`
**Solução**: Views já foram criadas; verificar se servidor está rodando e caches foram limpos
