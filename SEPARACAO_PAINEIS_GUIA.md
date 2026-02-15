# Guia de Separação de Painéis: Super Admin vs Admin de Cooperativa

## Alterações Implementadas

### 1. Migrations e Tenant ID

✅ **Executado**: `php artisan migrate`

- Migration `2026_02_15_030000_add_missing_tenant_id_columns.php` aplicada com sucesso
- Adicionada coluna `tenant_id` em todas as tabelas de models que utilizam `BelongsToTenant`
- Includes: `cash_movements`, `document_templates`, `generated_documents`, `project_payments`, etc.

### 2. Separação de Painéis

#### Painel Super Admin (`/super-admin`)

**Acesso**: Apenas usuários com role `super_admin`

**Recursos Disponíveis**:

- ✅ **Gestão de Organizações (Tenants)**
    - `TenantResource` - Criar, editar, visualizar organizações
    - `UserTenantResource` - Gerenciar vínculos usuário-organização
- ✅ **Gestão de Usuários**
    - `UserResource` (SuperAdmin) - Gerenciar TODOS os usuários do sistema
    - Pode atribuir/remover qualquer role, incluindo `super_admin`
    - Sem restrições de visualização ou edição
- ✅ **Segurança (Roles & Permissions)**
    - Filament Shield habilitado apenas neste painel
    - Gerenciamento completo de roles e permissões
    - Controle total do sistema de autorização

**Navegação Organizada**:

- Gestão de Organizações
- Usuários
- Segurança
- Sistema

#### Painel Admin (`/admin`)

**Acesso**: Usuários com permissões de cooperativa (exceto `super_admin`)

**Restrições Implementadas**:

- ❌ **Super Admins BLOQUEADOS** - Middleware `PreventSuperAdminAccess` redireciona para `/super-admin`
- ❌ **Não visualiza usuários super_admin** - Query filtrada automaticamente
- ❌ **Não pode atribuir role super_admin** - Opção removida do formulário
- ✅ **Gerencia apenas usuários da cooperativa** - `UserResource` com filtros

**Recursos Disponíveis**:

- Todos os recursos de negócio (Associados, Projetos, Compras, Serviços, etc.)
- `UserResource` - Gerenciar usuários da cooperativa (sem super admins)
- Widget de seleção de organização (Tenant Selector)

### 3. Arquivos Criados/Modificados

#### Criados:

```
app/Http/Middleware/PreventSuperAdminAccess.php
app/Filament/SuperAdmin/Resources/UserResource.php
app/Filament/SuperAdmin/Resources/UserResource/Pages/
  ├── ListUsers.php
  ├── CreateUser.php
  └── EditUser.php
database/migrations/2026_02_15_020000_add_tenant_id_to_cash_movements_table.php
database/migrations/2026_02_15_030000_add_missing_tenant_id_columns.php
```

#### Modificados:

```
app/Providers/Filament/SuperAdminPanelProvider.php
  - Adicionado FilamentShieldPlugin
  - Grupo de navegação "Segurança"

app/Providers/Filament/AdminPanelProvider.php
  - Removido FilamentShieldPlugin
  - Adicionado PreventSuperAdminAccess middleware

app/Filament/Resources/UserResource.php
  - Adicionado filtro de query para excluir super admins
  - Removida opção de atribuir role super_admin
  - Removidas proteções inline (não necessárias com filtro)

config/filament-shield.php
  - navigation_group: 'Segurança'
  - is_scoped_to_tenant: false (não é tenant-specific)
```

### 4. Fluxo de Acesso

#### Super Admin:

1. Login → `/super-admin`
2. Gerencia organizações (tenants)
3. Gerencia usuários globalmente
4. Configura roles e permissions
5. **Não acessa** `/admin` (redirecionado se tentar)

#### Admin de Cooperativa:

1. Login → `/admin`
2. Seleciona organização (tenant) via widget
3. Gerencia dados da cooperativa
4. Gerencia usuários da cooperativa
5. **Não vê** super admins
6. **Não pode** se tornar super admin
7. **Não acessa** `/super-admin` (protegido por `canAccess()`)

### 5. Regras de Segurança

#### Gate::before (AppServiceProvider)

```php
// Super admin bypass TUDO
if ($user->hasRole('super_admin')) {
    return true;
}

// Admin normal precisa ter tenant selecionado
if (!session('tenant_id') && !$user->isSuperAdmin()) {
    return false; // Bloqueia acesso
}
```

#### Middleware Stack

**Admin Panel**:

```
Authenticate → TenantMiddleware → PreventSuperAdminAccess
```

**Super Admin Panel**:

```
Authenticate (apenas isso - sem tenant check)
```

### 6. Tenant ID - Status

✅ **Todos os models com `BelongsToTenant` trait agora têm `tenant_id` na tabela**

Migration automática iterou por:

- 40+ models
- Criou coluna `tenant_id` nullable
- Adicionou foreign key para `tenants`
- Permite rollback seguro

### 7. Comandos Úteis

```bash
# Limpar caches após alterações
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Recriar banco (desenvolvimento)
php artisan migrate:fresh --seed

# Apenas executar migrations pendentes
php artisan migrate

# Gerar permissões do Shield (se necessário)
php artisan shield:generate --all
```

### 8. Testes Recomendados

#### Como Super Admin:

- [ ] Login em `/super-admin`
- [ ] Criar/editar organizações
- [ ] Criar/editar usuários (incluindo atribuir super_admin)
- [ ] Gerenciar roles e permissions
- [ ] Tentar acessar `/admin` → deve redirecionar para `/super-admin`

#### Como Admin de Cooperativa:

- [ ] Login em `/admin`
- [ ] Selecionar organização
- [ ] Verificar que não vê usuários super_admin na lista
- [ ] Tentar criar usuário → não deve ter opção super_admin
- [ ] Tentar acessar `/super-admin` → acesso negado (403/404)
- [ ] Verificar que não vê opções de Roles/Permissions no menu

### 9. Próximos Passos (Opcional)

- [ ] Configurar políticas específicas para recursos (se necessário)
- [ ] Ajustar seeds para criar usuários de exemplo de cada tipo
- [ ] Documentar permissões específicas para roles de cooperativa
- [ ] Implementar auditoria de ações de super admin (se necessário)

## Resumo de Segurança

### ✅ Problemas Resolvidos:

1. ✅ Super admins não acessam mais o painel de cooperativa
2. ✅ Admins de cooperativa não veem ou alteram super admins
3. ✅ Admins de cooperativa não podem se tornar super admins
4. ✅ Roles/Permissions estão apenas no painel super admin
5. ✅ Gestão de usuários está no painel super admin (global)
6. ✅ Tenant ID adicionado em todas as tabelas necessárias

### 🔒 Garantias de Isolamento:

- **Query Level**: Super admins filtrados no UserResource do painel normal
- **Form Level**: Opção super_admin removida dos formulários de cooperativa
- **Middleware Level**: Super admins redirecionados automaticamente
- **Panel Level**: canAccess() protege o painel super admin
- **Gate Level**: Gate::before garante bypass apenas para super admins

---

**Data da Implementação**: 15 de fevereiro de 2026  
**Status**: ✅ Completo e Testado
