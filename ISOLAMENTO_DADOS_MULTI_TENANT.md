# 🔒 Guia de Isolamento de Dados Multi-Tenant

## ⚠️ Problemas Críticos Resolvidos

### 1. ✅ Admin Via Role Super_Admin no Painel

**Problema**: Admins podiam ver a role `super_admin` na listagem de roles.  
**Solução**: Criado `RoleResource` customizado que sobrescreve o do Shield e filtra `super_admin`.

**Arquivo**: [`app/Filament/Resources/RoleResource.php`](app/Filament/Resources/RoleResource.php)

```php
public static function getEloquentQuery(): Builder
{
    $query = parent::getEloquentQuery();

    // Super admin vê todas as roles
    if (Auth::user()?->hasRole('super_admin')) {
        return $query;
    }

    // Admins NÃO podem ver super_admin
    return $query->where('name', '!=', 'super_admin');
}
```

### 2. ✅ Admin Acessando Painel Super-Admin

**Problema**: Admins conseguiam acessar `/super-admin`.  
**Solução**: Criado middleware `EnsureSuperAdmin` aplicado ao super-admin panel.

**Arquivo**: [`app/Http/Middleware/EnsureSuperAdmin.php`](app/Http/Middleware/EnsureSuperAdmin.php)

### 3. ✅ Selects Mostrando Dados de Todas as Organizações

**Problema**: Ao criar associado/prestador, Select de usuários mostrava TODOS os usuários do sistema.  
**Solução**: Adicionado `modifyQueryUsing` nos Selects de user_id.

**Arquivos Corrigidos**:

- [`app/Filament/Resources/AssociateResource.php`](app/Filament/Resources/AssociateResource.php)
- [`app/Filament/Resources/ServiceProviderResource.php`](app/Filament/Resources/ServiceProviderResource.php)
- [`app/Filament/Resources/UserResource.php`](app/Filament/Resources/UserResource.php) (filtro de roles)

**Padrão Implementado**:

```php
Forms\Components\Select::make('user_id')
    ->relationship(
        name: 'user',
        titleAttribute: 'name',
        modifyQueryUsing: function ($query) {
            $tenantId = session('tenant_id');
            if ($tenantId && !auth()->user()?->hasRole('super_admin')) {
                $query->whereHas('tenants', function ($q) use ($tenantId) {
                    $q->where('tenant_id', $tenantId);
                });
            }
            return $query;
        }
    )
```

### 4. ✅ Widgets Mostrando Dados de Todas as Organizações (CRÍTICO)

**Problema**: TODOS os 6 widgets do dashboard mostravam estatísticas agregadas de TODAS as organizações.  
**Solução**: Adicionado `where('tenant_id', session('tenant_id'))` em TODAS as queries dos widgets.

**Arquivos Corrigidos**:

- [`app/Filament/Widgets/ServiceOrdersPaymentsWidget.php`](app/Filament/Widgets/ServiceOrdersPaymentsWidget.php)
- [`app/Filament/Widgets/CashSummaryWidget.php`](app/Filament/Widgets/CashSummaryWidget.php)
- [`app/Filament/Widgets/AssociatesBalanceWidget.php`](app/Filament/Widgets/AssociatesBalanceWidget.php)
- [`app/Filament/Widgets/LowStockWidget.php`](app/Filament/Widgets/LowStockWidget.php)
- [`app/Filament/Widgets/PendingPaymentRequestsWidget.php`](app/Filament/Widgets/PendingPaymentRequestsWidget.php)
- [`app/Filament/Widgets/ProjectsProgressWidget.php`](app/Filament/Widgets/ProjectsProgressWidget.php)

### 5. ✅ Pages Customizadas Mostrando Dados Globais

**Problema**: Página de relatório mostrava dados de todas as organizações.  
**Solução**: Adicionado filtro de tenant na query.

**Arquivo Corrigido**:

- [`app/Filament/Pages/ServiceOrdersPaymentReport.php`](app/Filament/Pages/ServiceOrdersPaymentReport.php)

### 6. ✅ Usuários Criados Não Vinculados à Organização

**Problema**: Ao criar usuário no painel admin, ele não era vinculado automaticamente à organização.  
**Solução**: Adicionado hook `afterCreate()` que vincula o usuário à organização atual.

**Arquivo Corrigido**:

- [`app/Filament/Resources/UserResource/Pages/CreateUser.php`](app/Filament/Resources/UserResource/Pages/CreateUser.php)

**Implementação**:

```php
protected function afterCreate(): void
{
    $tenantId = session('tenant_id');
    if ($tenantId) {
        $this->record->tenants()->attach($tenantId, [
            'is_admin' => false,
            'roles' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
```

### 7. ✅ Constraints Unique Globais (CRÍTICO)

**Problema**: CPF/CNPJ, códigos e SKUs eram únicos GLOBALMENTE, impedindo organizações diferentes de terem fornecedores/produtos com mesmos identificadores.

**Exemplo**: Cooperativa A e B não podiam ambas cadastrar fornecedor com CNPJ 12.345.678/0001-90.

**Solução**:

1. Criada migration para alterar constraints do banco (simples → compostas)
2. Modificada validação em formulários para adicionar escopo de tenant

**Arquivos Corrigidos**:

- [`database/migrations/2026_02_15_070000_change_unique_constraints_to_tenant_scoped.php`](database/migrations/2026_02_15_070000_change_unique_constraints_to_tenant_scoped.php)
- [`app/Filament/Resources/SupplierResource.php`](app/Filament/Resources/SupplierResource.php) - cpf_cnpj
- [`app/Filament/Resources/CustomerResource.php`](app/Filament/Resources/CustomerResource.php) - cnpj
- [`app/Filament/Resources/ServiceProviderResource.php`](app/Filament/Resources/ServiceProviderResource.php) - cpf
- [`app/Filament/Resources/AssociateResource.php`](app/Filament/Resources/AssociateResource.php) - cpf_cnpj
- [`app/Filament/Resources/ProductResource.php`](app/Filament/Resources/ProductResource.php) - sku
- [`app/Filament/Resources/EquipmentResource.php`](app/Filament/Resources/EquipmentResource.php) - code
- [`app/Filament/Resources/ServiceResource.php`](app/Filament/Resources/ServiceResource.php) - code
- [`app/Filament/Resources/ChartAccountResource.php`](app/Filament/Resources/ChartAccountResource.php) - code
- [`app/Filament/Resources/AssetResource.php`](app/Filament/Resources/AssetResource.php) - code

**Migration**:

- Remove constraints UNIQUE simples (ex: `suppliers.cpf_cnpj`)
- Adiciona constraints UNIQUE compostas (ex: `suppliers.cpf_cnpj + tenant_id`)

**Padrão de Validação**:

```php
Forms\Components\TextInput::make('cpf_cnpj')
    ->unique(ignoreRecord: true, modifyRuleUsing: function (Unique $rule) {
        return $rule->where('tenant_id', session('tenant_id'));
    })
```

**Resultado**: Agora cada organização pode ter seus próprios fornecedores, produtos e serviços com códigos iguais aos de outras organizações.

### 8. ✅ Novo Fluxo de Criação de Usuário (Wizard)

**Problema**: Fluxo de criação criava sempre novo usuário, sem possibilidade de adicionar usuário existente.

**Solução**: Implementado wizard em 2 etapas:

**Etapa 1: Verificação de E-mail**

- Usuário digita o e-mail
- Sistema verifica se e-mail já existe
- Se existe E usuário já está na organização → mensagem de aviso
- Se existe E NÃO está na organização → pula para adicionar à organização
- Se NÃO existe → pede nome e senha

**Etapa 2: Dados do Usuário**

- **Usuário encontrado**: Mostra nome e permite adicionar à organização
- **Usuário novo**: Solicita nome, senha, status e roles

**Arquivo Modificado**:

- [`app/Filament/Resources/UserResource/Pages/CreateUser.php`](app/Filament/Resources/UserResource/Pages/CreateUser.php)

**Benefícios**:

- ✅ Permite adicionar usuário existente em múltiplas organizações
- ✅ Evita duplicação de e-mails
- ✅ Mantém integridade dos dados
- ✅ UX intuitiva com wizard

### 9. ✅ Traduções PT-BR e Labels de Validação

**Problema**: Mensagens de erro exibindo "validation.unique" e outras chaves não traduzidas.

**Solução**: Publicadas e configuradas traduções em português brasileiro.

**Arquivos Criados/Modificados**:

- [`lang/pt_BR/validation.php`](lang/pt_BR/validation.php) - Traduções de validação
- [`lang/pt_BR.json`](lang/pt_BR.json) - Traduções de atributos
- [`config/app.php`](config/app.php) - Já estava com locale pt_BR

**Mensagens Customizadas**:

```php
// validation.php
'unique' => 'Este :attribute já está em uso nesta organização.',

'attributes' => [
    'email' => 'e-mail',
    'cpf_cnpj' => 'CPF/CNPJ',
    'code' => 'código',
    'sku' => 'SKU',
    // ... outros
],
```

**Resultado**: Mensagens de validação agora aparecem em português claro e contextualizado.

### 10. ✅ Nome de Usuário Específico por Organização

**Problema**: Mesmo usuário em múltiplas organizações exibia sempre o mesmo nome global, sem privacidade entre organizações.

**Exemplo**: Usuário com email `joao@email.com` cadastrado na Cooperativa A como "João Silva" e na Cooperativa B como "José Santos" (usando email do filho) — ambas viam "João Silva".

**Solução**: Implementado sistema de nome específico por organização na pivot `tenant_user`.

**Modificações no Banco**:

- Migration [`2026_02_15_080000_add_tenant_name_password_to_tenant_user.php`](database/migrations/2026_02_15_080000_add_tenant_name_password_to_tenant_user.php)
- Adicionadas colunas `tenant_name` e `tenant_password` na tabela `tenant_user`

**Modificações no Model User**:

- [`app/Models/User.php`](app/Models/User.php):
    - Adicionado `withPivot('tenant_name', 'tenant_password')` no relacionamento `tenants()`
    - Método `getTenantName(?int $tenantId = null)`: retorna tenant_name quando disponível, senão name global
    - Accessor `display_name`: retorna automaticamente o nome correto conforme contexto do tenant

**Modificações no CreateUser**:

- [`app/Filament/Resources/UserResource/Pages/CreateUser.php`](app/Filament/Resources/UserResource/Pages/CreateUser.php):
    - Não exibe mais o nome global do usuário existente
    - Solicita nome e senha específicos da organização (obrigatórios)
    - Salva `tenant_name` e `tenant_password` (hash) na pivot ao vincular usuário

**Exibição Atualizada em 15+ Arquivos**:

Todos os lugares que exibiam `user.name` foram atualizados para `user.display_name`:

- Resources: `AssociateResource`, `ServiceProviderResource`, `UserResource`, `ActivityLogResource`, etc.
- Pages: `ServiceOrdersPaymentReport`, `ViewAssociate`, `ViewProductionDelivery`, `ViewActivityLog`
- RelationManagers: `WorksRelationManager`, `DeliveriesRelationManager`, `OrdersRelationManager`

**Comportamento**:

```php
// Novo usuário
CreateUser → name vai para users.name E tenant_user.tenant_name

// Usuário existente adicionado em nova org
CreateUser → solicita novo nome → vai para tenant_user.tenant_name
             (users.name permanece inalterado)

// Exibição no sistema
$user->display_name  // Retorna tenant_name da org atual, ou name global como fallback

// Login subsequente
user.name pode mudar (ex: OAuth atualiza) → tenant_name na pivot NÃO muda
```

**Resultado**: Cada organização vê o nome que definiu para aquele usuário, mantendo privacidade e independência entre organizações.

### 11. ✅ Accessor `name` Sobrescrito no Model User

**Problema**: Alguns lugares do código ainda usavam `user.name` diretamente (ex: `titleAttribute` em Selects, formulários inline).

**Solução**: Sobrescrito o accessor `name` no model User para retornar automaticamente `tenant_name` quando em contexto de organização.

**Modificações no Model**:

- [`app/Models/User.php`](app/Models/User.php):
    - Método `getNameAttribute($value)`: intercepta acesso ao atributo `name`
    - Verifica se está no painel super-admin (`/super-admin`) → retorna nome global
    - Se em contexto de tenant → retorna `tenant_name` da pivot (ou fallback para global)

**Comportamento**:

```php
// No painel super-admin
$user->name → "João Silva" (sempre nome global)

// No painel /admin (tenant context)
$user->name → "José Santos" (tenant_name da pivot)
$user->display_name → "José Santos" (mesmo resultado)
```

**Modificações em AssociateResource**:

- [`app/Filament/Resources/AssociateResource.php`](app/Filament/Resources/AssociateResource.php):
    - Select `user_id` com `getOptionLabelFromRecordUsing` usando `display_name`
    - `createOptionForm` com validação de email existente
    - `createOptionUsing` personalizado que:
        - Verifica se email já existe no sistema
        - Se existe: adiciona à organização atual (salva `tenant_name` e `tenant_password` na pivot)
        - Se não existe: cria novo usuário E salva na pivot
    - Notificação quando usuário existente é vinculado

**Modificações em ServiceProviderResource**:

- [`app/Filament/Resources/ServiceProviderResource.php`](app/Filament/Resources/ServiceProviderResource.php):
    - Select `user_id` com `getOptionLabelFromRecordUsing` usando `display_name`

**Resultado**:

- ✅ Todos os Selects de usuário mostram nome por organização
- ✅ Formulário inline de criar usuário valida email existente
- ✅ Usuários existentes podem ser adicionados via formulário inline
- ✅ Nome é exibido consistentemente por organização em TODO o sistema
- ✅ Painel super-admin não é afetado (sempre vê nome global)

---

## 🛡️ Camadas de Proteção Implementadas

### Camada 1: Model (BelongsToTenant Trait)

Todos os modelos têm o trait `BelongsToTenant` que adiciona `tenant_id`:

```php
// Aplicado automaticamente em create/update
protected static function boot()
{
    parent::boot();

    static::creating(function ($model) {
        if (!$model->tenant_id && session('tenant_id')) {
            $model->tenant_id = session('tenant_id');
        }
    });
}
```

### Camada 2: Resource (TenantScoped Trait)

22 resources aplicam o trait `TenantScoped`:

```php
use App\Filament\Traits\TenantScoped;

class MyResource extends Resource
{
    use TenantScoped;
}
```

**Trait implementa**:

```php
public static function getEloquentQuery(): Builder
{
    $query = parent::getEloquentQuery();

    // Super admin vê tudo
    if (Auth::user()?->hasRole('super_admin')) {
        return $query;
    }

    // Filtrar por tenant
    $tenantId = session('tenant_id');
    if ($tenantId) {
        return $query->where('tenant_id', $tenantId);
    }

    return $query->whereRaw('1 = 0');
}
```

### Camada 3: Forms (Selects com modifyQueryUsing)

Selects que apontam para `User` ou entidades sem `tenant_id` precisam de filtro manual:

```php
->relationship(
    name: 'user',
    titleAttribute: 'name',
    modifyQueryUsing: fn($query) => $query->whereHas('tenants', fn($q) =>
        $q->where('tenant_id', session('tenant_id'))
    )
)
```

### Camada 4: Middleware (TenantMiddleware)

Define `session('tenant_id')` para todas as requisições no painel admin.

### Camada 5: Policies (RolePolicy, etc.)

Bloqueiam ações indevidas mesmo se o usuário tentar acessar diretamente.

---

## ✅ Checklist de Isolamento por Organização

### Para Novos Resources

- [ ] Model usa `BelongsToTenant` trait?
- [ ] Resource usa `TenantScoped` trait?
- [ ] Selects de relacionamentos filtram corretamente?
- [ ] Policy verifica `tenant_id` quando necessário?

### Para Novos Selects/Relacionamentos

**Se o relacionamento é com User**:

```php
->relationship(
    name: 'user',
    titleAttribute: 'name',
    modifyQueryUsing: function ($query) {
        $tenantId = session('tenant_id');
        if ($tenantId && !auth()->user()?->hasRole('super_admin')) {
            $query->whereHas('tenants', function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId);
            });
        }
        return $query;
    }
)
```

**Se o relacionamento é com modelo que tem tenant_id**:

```php
// Não precisa de filtro manual - TenantScoped já filtra!
->relationship('supplier', 'name')
```

**Se o relacionamento é com Role**:

```php
->relationship('roles', 'name', function ($query) {
    if (!Auth::user()?->hasRole('super_admin')) {
        $query->where('name', '!=', 'super_admin');
    }
})
```

---

## 🧪 Como Testar Isolamento

### Teste 1: Admin não vê super_admin role

1. Login como admin de organização
2. Ir em `/admin/shield/roles`
3. ✅ Não deve aparecer "Super Admin" na lista

### Teste 2: Admin não vê dados de outras organizações

1. Login como admin da "Organização A"
2. Verificar listagem de qualquer resource
3. ✅ Deve mostrar APENAS dados da Organização A

### Teste 3: Selects filtram por organização

1. Login como admin da "Organização A"
2. Criar novo Associado
3. Abrir Select de "Usuário"
4. ✅ Deve mostrar APENAS usuários vinculados à Organização A

### Teste 4: Admin não acessa super-admin

1. Login como admin
2. Tentar acessar `/super-admin`
3. ✅ Deve redirecionar para `/admin`

### Teste 5: Usuário em 2 organizações vê dados corretos

1. Criar usuário vinculado a "Org A" e "Org B"
2. Login e selecionar "Org A"
3. ✅ Ver apenas dados da Org A
4. Trocar para "Org B"
5. ✅ Ver apenas dados da Org B

---

## 📦 Arquivos Modificados

### Novos Arquivos

- `app/Http/Middleware/EnsureSuperAdmin.php` - Bloqueia não-super-admins do painel
- `app/Filament/Resources/RoleResource.php` - Filtra super_admin role
- `app/Filament/Traits/TenantScoped.php` - Trait para filtrar resources
- `app/Console/Commands/ApplyTenantScopingCommand.php` - Comando para aplicar trait

### Arquivos Modificados - Resources

- `app/Filament/Resources/AssociateResource.php` - Select de user filtrado
- `app/Filament/Resources/ServiceProviderResource.php` - Select de user filtrado
- `app/Filament/Resources/UserResource.php` - Filtros e select de roles
- `app/Providers/Filament/SuperAdminPanelProvider.php` - Middleware aplicado
- 22 outros resources com `TenantScoped` trait aplicado

### Arquivos Modificados - Widgets (CRÍTICO)

**TODOS os 6 widgets foram corrigidos para filtrar por tenant_id**:

- `app/Filament/Widgets/ServiceOrdersPaymentsWidget.php` - 9 queries corrigidas
- `app/Filament/Widgets/CashSummaryWidget.php` - 5 queries corrigidas
- `app/Filament/Widgets/AssociatesBalanceWidget.php` - 7 queries corrigidas
- `app/Filament/Widgets/LowStockWidget.php` - 1 query corrigida
- `app/Filament/Widgets/PendingPaymentRequestsWidget.php` - 1 query corrigida
- `app/Filament/Widgets/ProjectsProgressWidget.php` - 1 query corrigida

### Arquivos Modificados - Pages

- `app/Filament/Pages/ServiceOrdersPaymentReport.php` - Query filtrada por tenant

---

## 🚨 Regras de Ouro

1. **NUNCA** liste entidades sem filtrar por `tenant_id` ou `session('tenant_id')`
2. **SEMPRE** use `TenantScoped` trait em resources de dados organizacionais
3. **SEMPRE** filtre Selects de User com `whereHas('tenants')`
4. **NUNCA** mostre role `super_admin` para admins de organização
5. **SEMPRE** teste com 2 organizações diferentes para garantir isolamento
6. **SEMPRE** use `display_name` ao exibir nome de usuário (respeita tenant_name da pivot)
7. **NUNCA** use `user.name` diretamente em tabelas/infolists — use `user.display_name`
8. **O accessor `name`** do User agora retorna automaticamente `tenant_name` em contexto de organização
9. **Selects de User** devem usar `getOptionLabelFromRecordUsing(fn ($record) => $record->display_name)`
10. **Ao criar usuário inline** (createOptionForm), sempre salvar `tenant_name` na pivot

---

## 🔍 Como Encontrar Vazamentos de Dados

### Comando para listar Selects sem filtro:

```bash
grep -r "->relationship('user'" app/Filament/Resources/
```

### Comando para listar resources sem TenantScoped:

```bash
grep -L "use TenantScoped" app/Filament/Resources/*Resource.php
```

### Verificar models sem BelongsToTenant:

```bash
grep -L "use BelongsToTenant" app/Models/*.php
```

---

## 📚 Referências

- [BelongsToTenant Trait](app/Models/Traits/BelongsToTenant.php)
- [TenantScoped Trait](app/Filament/Traits/TenantScoped.php)
- [Sistema Roles Permissions Multi-Tenant](SISTEMA_ROLES_PERMISSIONS_MULTI_TENANT.md)
- [TenantMiddleware](app/Http/Middleware/TenantMiddleware.php)
