# COMANDOS ÚTEIS - MULTI-TENANT

## 📋 Setup Inicial

### 1. Executar Migrations

```bash
php artisan migrate
```

### 2. Popular Dados Iniciais

```bash
php artisan db:seed --class=TenantSeeder
```

**Credenciais padrão criadas:**

- Super Admin: `superadmin@sgc.com` / `password`
- Admin Tenant: `admin@sgc.com` / `password`

⚠️ **Alterar senhas em produção!**

### 3. Configurar Filament Shield

```bash
# Instalar Shield (se ainda não instalado)
php artisan shield:install

# Gerar recursos para todas as policies
php artisan shield:generate --all

# Definir super_admin (se necessário)
php artisan shield:super-admin --user=1
```

---

## 🛠️ Gestão de Tenants

### Criar Novo Tenant

```bash
# Interativo
php artisan tenant:manage create

# Com parâmetros
php artisan tenant:manage create --name="Cooperativa ABC" --slug="cooperativa-abc"
```

### Listar Tenants

```bash
php artisan tenant:manage list
```

### Vincular Usuário a Tenant

```bash
# Por email
php artisan tenant:manage assign --tenant=cooperativa-abc --user=usuario@email.com

# Com permissão de admin
php artisan tenant:manage assign --tenant=1 --user=2 --admin

# Interativo
php artisan tenant:manage assign
```

### Remover Usuário de Tenant

```bash
php artisan tenant:manage remove --tenant=1 --user=usuario@email.com

# Interativo
php artisan tenant:manage remove
```

---

## 👤 Criar Usuários

### Via Tinker

```bash
php artisan tinker
```

```php
// Criar usuário
$user = User::create([
    'name' => 'Nome do Usuário',
    'email' => 'usuario@email.com',
    'password' => Hash::make('senha123'),
    'status' => true,
]);

// Atribuir role
$user->assignRole('admin');

// Vincular a tenant
$tenant = Tenant::find(1);
$tenant->addUser($user, true); // true = is_admin
```

### Criar Super Admin

```bash
php artisan tinker
```

```php
$user = User::create([
    'name' => 'Super Admin',
    'email' => 'super@email.com',
    'password' => Hash::make('senha123'),
    'status' => true,
]);

$user->assignRole('super_admin');
```

---

## 🔍 Validação e Debug

### Verificar Tenant Atual

```bash
php artisan tinker
```

```php
// Simular sessão
session(['tenant_id' => 1]);

// Verificar tenant ativo
app(TenantResolver::class)->current();

// Listar produtos (deve filtrar por tenant)
Product::all();
```

### Verificar Isolamento

```php
// Tenant 1
session(['tenant_id' => 1]);
$p1 = Product::create(['name' => 'Produto T1']);

// Tenant 2
session(['tenant_id' => 2]);
Product::create(['name' => 'Produto T2']);
Product::all(); // Deve mostrar apenas "Produto T2"

// Verificar
Product::withoutTenant()->count(); // 2 produtos no total
Product::forTenant(1)->count(); // 1 produto
Product::forTenant(2)->count(); // 1 produto
```

### Listar Tenants de um Usuário

```php
$user = User::find(1);
$user->tenants; // Collection de Tenants

// Verificar admin
$user->isTenantAdmin(1); // bool
$user->adminTenants; // Tenants onde é admin
```

### Verificar Usuários de um Tenant

```php
$tenant = Tenant::find(1);
$tenant->users; // Collection de Users
$tenant->admins; // Collection de admins

// Verificar
$tenant->isAdmin($user); // bool
```

---

## 🧹 Limpar Cache

```bash
# Limpar cache de aplicação
php artisan cache:clear

# Limpar cache de configuração
php artisan config:clear

# Limpar cache de rotas
php artisan route:clear

# Limpar cache de views
php artisan view:clear

# Limpar tudo
php artisan optimize:clear
```

---

## 🗄️ Migrations

### Rollback e Refazer

```bash
# Rollback última migration
php artisan migrate:rollback

# Rollback todas
php artisan migrate:reset

# Refazer tudo
php artisan migrate:fresh

# Refazer com seed
php artisan migrate:fresh --seed
```

### Status

```bash
php artisan migrate:status
```

---

## 🔐 Segurança

### Alterar Senha de Usuário

```bash
php artisan tinker
```

```php
$user = User::where('email', 'usuario@email.com')->first();
$user->password = Hash::make('nova_senha_segura');
$user->save();
```

### Desativar Usuário

```php
$user = User::find(1);
$user->status = false;
$user->save();
```

### Desativar Tenant

```php
$tenant = Tenant::find(1);
$tenant->active = false;
$tenant->save();
```

---

## 📊 Relatórios e Queries

### Contar Registros por Tenant

```php
foreach (Tenant::all() as $tenant) {
    echo "{$tenant->name}: " . Product::forTenant($tenant->id)->count() . " produtos\n";
}
```

### Usuários sem Tenant

```php
User::whereDoesntHave('tenants')->get();
```

### Tenants sem Usuários

```php
Tenant::whereDoesntHave('users')->get();
```

### Usuários com Múltiplos Tenants

```php
User::has('tenants', '>', 1)->with('tenants')->get();
```

---

## 🚀 Deploy

### Para Produção

1. **Executar migrations**

    ```bash
    php artisan migrate --force
    ```

2. **Popular dados iniciais**

    ```bash
    php artisan db:seed --class=TenantSeeder --force
    ```

3. **Alterar senhas padrão**

    ```bash
    php artisan tinker
    # Alterar senha do super admin
    # Alterar senha do admin
    ```

4. **Gerar permissões Shield**

    ```bash
    php artisan shield:generate --all
    ```

5. **Otimizar**
    ```bash
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    ```

---

## 🧪 Testes

### Teste Manual de Isolamento

1. Acessar `/super-admin`
2. Criar 2 tenants: "Cooperativa A" e "Cooperativa B"
3. Criar usuário e vincular aos 2 tenants
4. Fazer logout e login com esse usuário
5. Selecionar "Cooperativa A"
6. Criar produtos
7. Trocar para "Cooperativa B" (seletor no topo)
8. Verificar que produtos de A não aparecem
9. Criar produtos em B
10. Validar isolamento total

### Teste de Super Admin

1. Fazer login como super admin
2. Acessar `/super-admin`
3. Verificar gestão de tenants
4. Verificar gestão de usuários
5. Acessar `/admin` (opcional)
6. Verificar que pode ver todos os dados (sem filtro de tenant)

---

## ⚠️ Solução de Problemas

### Erro: "No tenant found"

```bash
# Verificar se usuário está vinculado
php artisan tinker
```

```php
$user = User::find(1);
$user->tenants; // Deve ter pelo menos 1
```

**Solução:**

```php
$tenant = Tenant::first();
$tenant->addUser($user);
```

### Erro: Permission denied

```bash
# Verificar roles
php artisan tinker
```

```php
$user = User::find(1);
$user->roles; // Ver roles atribuídas
```

**Solução:**

```php
$user->assignRole('admin');
```

### Registros não aparecem

```bash
# Verificar tenant_id nos registros
php artisan tinker
```

```php
Product::withoutTenant()->get(['id', 'name', 'tenant_id']);
```

**Solução:**

```php
// Atualizar registros órfãos
Product::whereNull('tenant_id')->update(['tenant_id' => 1]);
```

### Widget não aparece

```bash
# Limpar cache
php artisan view:clear
php artisan cache:clear
```

---

## 📝 Logs

### Verificar Logs de Erros

```bash
tail -f storage/logs/laravel.log
```

### Logs de Queries (Debug)

No `AppServiceProvider`:

```php
\DB::listen(function($query) {
    \Log::info($query->sql, $query->bindings);
});
```

---

## 🎯 Performance

### Índices Criados

As migrations já incluem índices em:

- `(tenant_id, id)` em todas as tabelas
- Melhoram performance de queries filtradas por tenant

### Cache de Tenant

O `TenantResolver` já usa cache automático (5 minutos):

```php
Cache::remember("tenant.{$tenantId}", 300, ...);
```

---

## 📚 Documentação Adicional

- **Guia Completo**: `MULTI_TENANT_GUIDE.md`
- **Filament Docs**: https://filamentphp.com
- **Laravel Multi-Tenancy**: https://laravel.com/docs/eloquent#global-scopes
- **Spatie Permission**: https://spatie.be/docs/laravel-permission

---

## ✅ Checklist Pós-Deploy

- [ ] Migrations executadas
- [ ] TenantSeeder executado
- [ ] Senhas padrão alteradas
- [ ] Super admin configurado
- [ ] Tenants criados
- [ ] Usuários vinculados
- [ ] Shield configurado
- [ ] Permissões geradas
- [ ] Testes de isolamento realizados
- [ ] Cache otimizado
- [ ] Logs verificados
- [ ] Backup configurado

---

✨ **Sistema Multi-Tenant pronto para uso!**
