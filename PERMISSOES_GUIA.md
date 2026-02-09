# Guia de Permissões - Filament Shield + Laravel Permission

## 📚 Como Funciona a Integração

O sistema usa **duas camadas** de controle de acesso que trabalham juntas:

### 1. **Laravel Permission (Spatie)**

Package: `spatie/laravel-permission`

**Responsável por:**

- Gerenciar **Roles** (Funções) e **Permissions** (Permissões)
- Atribuir roles aos usuários
- Vincular permissões às roles
- Verificações básicas: `$user->hasRole('admin')`, `$user->can('edit_article')`

**Estrutura:**

```
User → hasMany Roles → hasMany Permissions
```

### 2. **Filament Shield**

Package: `bezhansalleh/filament-shield`

**Responsável por:**

- Gerar **Policies** automaticamente para cada Resource do Filament
- Criar **permissões granulares** para CRUD de cada recurso
- Integrar com o Laravel Permission para verificar permissões no painel Filament

**Permissões geradas automaticamente:**

```php
view_{resource}           // Ver um registro específico
view_any_{resource}       // Listar registros
create_{resource}         // Criar novo
update_{resource}         // Editar
delete_{resource}         // Deletar
restore_{resource}        // Restaurar soft-deleted
force_delete_{resource}   // Deletar permanentemente
replicate_{resource}      // Duplicar
reorder_{resource}        // Reordenar
```

---

## 🏗️ Arquitetura do Sistema

### Roles (Funções) Configuradas

| Role               | Descrição                | Acesso                                 |
| ------------------ | ------------------------ | -------------------------------------- |
| `super_admin`      | Administrador Total      | Painel Admin (bypass todas permissões) |
| `admin`            | Administrador            | Painel Admin (sujeito a permissões)    |
| `financeiro`       | Financeiro               | Painel Admin (acesso financeiro)       |
| `associado`        | Associado da Cooperativa | Portal Externo `/associate/*`          |
| `service_provider` | Prestador de Serviço     | Portal Externo `/provider/*`           |

### Fluxo de Autenticação

```
1. Usuário faz login (Google OAuth ou senha)
   ↓
2. Sistema verifica roles do usuário
   ↓
3. Redireciona baseado na prioridade:
   - super_admin/admin/financeiro → /admin
   - service_provider → /provider/dashboard
   - associado → /associate/dashboard
```

### Controle de Acesso ao Painel Admin

**Arquivo:** `app/Models/User.php`

```php
public function canAccessPanel(Panel $panel): bool
{
    if (!$this->status) {
        return false; // Usuário inativo
    }

    // Admins sempre podem acessar
    if ($this->hasAnyRole(['super_admin', 'admin', 'financeiro'])) {
        return true;
    }

    // Portal users não acessam admin
    return false;
}
```

**Lógica:**

- ✅ Super_admin, admin, financeiro → **SEMPRE** podem acessar `/admin`
- ❌ Associado ou service_provider (sem admin) → **BLOQUEADOS** de `/admin`
- ✅ Usuário com `admin` + `associado` → pode acessar **ambos** os painéis

---

## 🛡️ Como o Filament Shield Funciona

### 1. Geração de Permissões e Policies

Comando:

```bash
php artisan shield:generate --all
```

**O que acontece:**

1. Escaneia todos os Resources em `app/Filament/Resources`
2. Cria uma **Policy** para cada Resource (ex: `AssociatePolicy.php`)
3. Cria **permissões** no banco para cada ação CRUD
4. Registra as policies no `AuthServiceProvider` automaticamente

### 2. Verificação de Permissão

Quando um usuário tenta acessar um Resource no Filament:

```
User acessa /admin/associates
    ↓
Filament chama AssociatePolicy::viewAny()
    ↓
Policy verifica: $user->can('view_any_associate')
    ↓
Laravel Permission verifica se alguma role do user tem essa permissão
    ↓
Retorna true/false
```

### 3. Super Admin Bypass

**Método especial:**

```php
public function isSuperAdmin(): bool
{
    return $this->hasRole('super_admin');
}
```

Se retornar `true`, o Filament Shield **ignora todas** as verificações de permissão.

---

## 🔧 Configuração Atual

### Seeders Executados

#### 1. `RolesAndPermissionsSeeder`

- Cria roles básicas: `super_admin`, `admin`, `financeiro`, `associado`, `service_provider`
- Cria usuários de teste: admin, associado

#### 2. `AssociatePermissionsSeeder`

- Atribui permissões **read-only** para `associado`:
    - `view_sales_project` e `view_any_sales_project`
    - `view_production_delivery` e `view_any_production_delivery`
    - `view_associate_ledger` e `view_any_associate_ledger`

#### 3. `ServiceProviderSeeder`

- Cria role `service_provider`
- Define permissões básicas:
    - `view_service_orders`
    - `create_service_work`
    - `view_own_service_work`
- Cria usuário de teste: prestador

### Middleware Customizado

**Arquivo:** `app/Http/Middleware/CheckUserRole.php`

```php
public function handle(Request $request, Closure $next, string $role): Response
{
    if (!$request->user()) {
        return redirect('/login');
    }

    if (!$request->user()->hasRole($role)) {
        return redirect('/')->with('error', 'Sem permissão.');
    }

    return $next($request);
}
```

**Uso nas rotas:**

```php
Route::prefix('provider')
    ->middleware(['auth', 'role:service_provider'])
    ->group(function () {
        // Rotas do portal provider
    });
```

---

## 📝 Como Gerenciar Permissões

### 1. Atribuir Role a um Usuário

**Via Interface (Painel Admin):**

1. Acesse **Sistema > Usuários**
2. Edite o usuário desejado
3. Na seção **Segurança**, selecione as **Funções (Roles)**
4. Salve

**Via Código:**

```php
$user = User::find(1);
$user->assignRole('admin');
// ou múltiplas
$user->assignRole(['admin', 'financeiro']);
```

### 2. Atribuir Permissão a uma Role

**Via Seeder:**

```php
$role = Role::findByName('financeiro');
$permissions = ['view_expense', 'create_expense', 'update_expense'];
$role->givePermissionTo($permissions);
```

**Via Interface:**

- Atualmente não há interface gráfica para gerenciar permissões individuais
- Recomenda-se usar seeders para configurações iniciais
- Para ajustes pontuais, use `php artisan tinker`

### 3. Verificar Permissões de um Usuário

**Em código:**

```php
// Verificar role
if ($user->hasRole('admin')) { }

// Verificar permissão específica
if ($user->can('edit_article')) { }

// Verificar qualquer role
if ($user->hasAnyRole(['admin', 'financeiro'])) { }

// Verificar todas as roles
if ($user->hasAllRoles(['admin', 'financeiro'])) { }
```

**Via tinker:**

```bash
php artisan tinker
>>> $user = User::find(1)
>>> $user->roles->pluck('name')
>>> $user->permissions->pluck('name')
```

---

## 🐛 Troubleshooting

### Problema 1: Admin não consegue acessar `/admin`

**Causa:** Usuário tem role `associado` ou `service_provider` junto com `admin`  
**Solução:** ✅ **Já corrigido!** A lógica agora prioriza roles de admin

### Problema 2: "403 Você não está cadastrado como prestador"

**Causa:** User tem role `service_provider`, mas não tem registro na tabela `service_providers`  
**Solução:**

1. Crie o registro manualmente em **Sistema > Prestadores de Serviço**
2. Vincule pelo campo `user_id`
3. Ou remova a role `service_provider` se não for prestador

### Problema 3: Shield não está aplicando permissões

**Causa:** Policies não foram geradas ou cache desatualizado  
**Solução:**

```bash
php artisan shield:generate --all
php artisan optimize:clear
```

### Problema 4: Super Admin não tem acesso a tudo

**Causa:** Método `isSuperAdmin()` não está retornando true  
**Verificação:**

```bash
php artisan tinker
>>> User::find(1)->isSuperAdmin()
```

**Correção:** Atribuir role `super_admin`

---

## 🎯 Casos de Uso Comuns

### Criar um novo Admin

```bash
php artisan tinker
>>> $user = User::create([
    'name' => 'Novo Admin',
    'email' => 'novo@admin.com',
    'password' => Hash::make('senha_segura'),
    'status' => true,
]);
>>> $user->assignRole('admin');
```

### Dar permissões financeiras a alguém

```bash
>>> $user = User::where('email', 'financeiro@sgc.com')->first();
>>> $user->assignRole('financeiro');
>>> $role = Role::findByName('financeiro');
>>> $role->givePermissionTo([
    'view_any_expense',
    'view_expense',
    'create_expense',
    'update_expense'
]);
```

### Remover acesso ao admin de um associado

```bash
>>> $user = User::find(5);
>>> $user->removeRole('admin');
>>> $user->assignRole('associado'); // se ainda não tiver
```

---

## 📦 Estrutura de Arquivos

```
app/
├── Models/
│   └── User.php              # canAccessPanel, isSuperAdmin
├── Policies/                 # Geradas pelo Shield
│   ├── AssociatePolicy.php
│   ├── ExpensePolicy.php
│   └── ...
├── Http/Middleware/
│   └── CheckUserRole.php     # Middleware customizado
└── Filament/Resources/
    └── UserResource.php      # Gerenciar usuários e roles

database/seeders/
├── RolesAndPermissionsSeeder.php
├── AssociatePermissionsSeeder.php
└── ServiceProviderSeeder.php

routes/
└── web.php                   # Rotas com middleware role
```

---

## 🔄 Workflow Recomendado

### Para adicionar novo tipo de usuário:

1. **Criar a Role:**

```bash
php artisan tinker
>>> Role::create(['name' => 'nova_role']);
```

2. **Definir Permissões:** (via seeder)

```php
$role = Role::findByName('nova_role');
$role->givePermissionTo(['lista', 'de', 'permissões']);
```

3. **Criar Middleware ou Lógica de Redirect** (se necessário)

4. **Testar:**

- Atribuir role a um usuário de teste
- Fazer login e verificar acesso
- Testar tanto acesso permitido quanto bloqueado

---

## 📚 Referências

- [Laravel Permission Docs](https://spatie.be/docs/laravel-permission)
- [Filament Shield Docs](https://github.com/bezhanSalleh/filament-shield)
- [Filament Authorization](https://filamentphp.com/docs/3.x/panels/users#authorization)
