# 🔐 Sistema de Roles e Permissions Multi-Tenant

## 📋 Visão Geral

Este sistema implementa **roles e permissions por organização (tenant)** usando Laravel + Filament Shield + Spatie Permission.

**IMPORTANTE**: As roles são definições globais no sistema, mas a **atribuição** de roles aos usuários é feita **por tenant** através da tabela pivot `tenant_user`.

## 🏗️ Arquitetura

### 1. Roles Globais (Tabela `roles`)

As roles são definições globais que existem uma vez no sistema:

```php
- super_admin      // Acesso total ao sistema (painel super-admin)
- admin            // Administrador da organização (acesso completo ao painel da org)
- financeiro       // Acesso a módulos financeiros
- operador_caixa   // Operador de caixa
- assistente       // Visualização apenas
- associado        // Portal do associado
- prestador        // Portal do prestador
```

**Características:**

- ✓ Criadas uma vez no sistema
- ✓ Cada role tem permissions associadas globalmente
- ✓ **Apenas super_admin pode criar, editar ou deletar roles**
- ✗ Admins de organizações **NÃO podem criar roles**, apenas atribuir as existentes

### 2. Atribuição de Roles por Tenant (Tabela Pivot `tenant_user`)

A tabela pivot `tenant_user` armazena o vínculo entre usuários e organizações, **incluindo as roles**:

```sql
CREATE TABLE tenant_user (
    user_id BIGINT UNSIGNED,
    tenant_id BIGINT UNSIGNED,
    is_admin BOOLEAN DEFAULT 0,
    roles JSON NULL,              -- ✨ NOVO: Roles do usuário nesta organização
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    PRIMARY KEY (user_id, tenant_id)
);
```

**Exemplo de dados:**

```json
// Usuário ID 5 na Organização ID 1
{
    "user_id": 5,
    "tenant_id": 1,
    "is_admin": true,
    "roles": ["admin", "financeiro"]
}

// MESMO usuário ID 5 na Organização ID 2 (roles diferentes!)
{
    "user_id": 5,
    "tenant_id": 2,
    "is_admin": false,
    "roles": ["operador_caixa"]
}
```

### 3. Permissions (Tabela `permissions`)

Permissions são globais e vinculadas às roles:

```
view_asset, create_asset, update_asset, delete_asset
view_cash_movement, create_cash_movement, ...
view_expense, create_expense, ...
```

**São geradas automaticamente pelo Shield** com o comando:

```bash
php artisan shield:generate --all
```

## 🔧 Como Funciona

### Verificação de Role por Tenant

```php
// Modelo User - Métodos customizados

// Obter roles do usuário em um tenant específico
$user->getRolesForTenant($tenantId); // ['admin', 'financeiro']

// Verificar se usuário tem role em um tenant
$user->hasRoleInTenant('admin', $tenantId); // true/false

// Atribuir role a usuário em um tenant
$user->assignRoleToTenant('financeiro', $tenantId);

// Remover role de usuário em um tenant
$user->removeRoleFromTenant('financeiro', $tenantId);

// Sincronizar roles (substituir todas)
$user->syncRolesForTenant(['admin', 'financeiro'], $tenantId);
```

### Filtros por Tenant nos Resources

Todos os resources do painel admin aplicam automaticamente filtro por tenant:

```php
// app/Filament/Traits/TenantScoped.php

trait TenantScoped
{
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        // Super admin vê tudo
        if (Auth::user()?->hasRole('super_admin')) {
            return $query;
        }

        // Filtrar por tenant da sessão
        $tenantId = session('tenant_id');
        if ($tenantId) {
            return $query->where('tenant_id', $tenantId);
        }

        return $query->whereRaw('1 = 0');
    }
}
```

**22 resources aplicam este trait** automaticamente:

- AssetResource, BankAccountResource, CashMovementResource, etc.

**UserResource usa filtro customizado** (relacionamento many-to-many):

```php
$query->whereHas('tenants', function ($q) use ($tenantId) {
    $q->where('tenant_id', $tenantId);
});
```

## 🛡️ Proteções de Segurança

### 1. RolePolicy - Apenas Super Admin Pode Gerenciar Roles

```php
// app/Policies/RolePolicy.php

public function create(User $user): bool
{
    // ✗ Admins NÃO podem criar roles
    return $user->isSuperAdmin();
}

public function update(User $user, Role $role): bool
{
    // ✗ Admins NÃO podem editar roles
    return $user->isSuperAdmin();
}

public function delete(User $user, Role $role): bool
{
    // ✗ Admins NÃO podem deletar roles
    // ✗ Não pode deletar super_admin ou admin
    if (in_array($role->name, ['super_admin', 'admin'])) {
        return false;
    }
    return $user->isSuperAdmin();
}
```

### 2. Filtragem Automática por Tenant

- **Todos os recursos** no painel `/admin` são filtrados por `tenant_id` da sessão
- **Super admins** veem todos os registros (sem filtro)
- **Admins** veem apenas dados da sua organização

### 3. Separação de Painéis

- `/admin` - Painel da organização (admin, financeiro, etc.)
- `/super-admin` - Painel global (apenas super_admin)

## 📦 Comandos Úteis

### Aplicar Filtro de Tenant a Todos os Resources

```bash
php artisan tenant:apply-scoping
```

Este comando adiciona automaticamente o trait `TenantScoped` a todos os resources que têm `tenant_id`.

### Gerar Roles e Permissions

```bash
# Gerar todas as permissions e policies
php artisan shield:generate --all

# Seeder com roles padrão e permissions
php artisan db:seed --class=RolesAndPermissionsSeeder
```

### Limpar Caches

```bash
php artisan config:clear
php artisan cache:clear
php artisan permission:cache-reset
```

## 🎯 Exemplo Prático de Uso

### Cenário: Usuário Maria

Maria trabalha em **duas organizações**:

**Organização A (Cooperativa ABC):**

- Role: `admin` + `financeiro`
- Pode: Gerenciar tudo na Cooperativa ABC
- Não pode: Criar/editar roles, ver dados de outras organizações

**Organização B (Cooperativa XYZ):**

- Role: `operador_caixa`
- Pode: Apenas operações de caixa na Cooperativa XYZ
- Não pode: Ver despesas, compras, ou dados da Cooperativa ABC

### Como Maria Alterna Entre Organizações

1. Maria faz login no sistema
2. Seleciona "Cooperativa ABC" no seletor de organizações
3. Session `tenant_id` = ID da Cooperativa ABC
4. Maria vê dados e tem permissions de `admin` + `financeiro` para ABC

5. Maria troca para "Cooperativa XYZ"
6. Session `tenant_id` = ID da Cooperativa XYZ
7. Maria vê apenas dados de caixa e tem permissions de `operador_caixa` para XYZ

## 🔄 Migrations Aplicadas

1. `2026_02_15_060000_add_roles_to_tenant_user_table.php`
    - Adiciona coluna `roles` (JSON) na tabela tenant_user
    - Migra roles globais existentes para o pivot

2. `2026_02_15_061000_remove_tenant_id_from_roles_and_permissions.php`
    - Remove `tenant_id` das tabelas `roles` e `permissions`
    - Roles e permissions agora são globais

## ✅ Checklist de Implementação

- [x] Migration para adicionar `roles` em `tenant_user`
- [x] Migration para remover `tenant_id` de `roles` e `permissions`
- [x] Métodos no User model para gerenciar roles por tenant
- [x] Trait `TenantScoped` para filtrar resources
- [x] Comando `tenant:apply-scoping` para aplicar trait
- [x] 22 resources com filtro de tenant aplicado
- [x] UserResource com filtro customizado
- [x] RolePolicy bloqueando criação/edição por admins
- [x] Seeder com roles padrão e permissions
- [x] Documentação completa

## 📚 Referências

- [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission/v6/introduction)
- [Filament Shield](https://filamentphp.com/plugins/bezhansalleh-shield)
- [Laravel Multi-Tenancy](https://laravel.com/docs/11.x/packages#multi-tenancy)

## 🚨 IMPORTANTE

**Admins de organizações:**

- ✓ Podem ATRIBUIR roles existentes aos usuários de sua organização
- ✗ NÃO podem criar novas roles
- ✗ NÃO podem editar roles existentes
- ✗ NÃO podem deletar roles
- ✗ NÃO podem ver dados de outras organizações

**Apenas Super Admins:**

- ✓ Podem criar, editar, deletar roles
- ✓ Podem ver dados de todas as organizações
- ✓ Têm acesso ao painel `/super-admin`
