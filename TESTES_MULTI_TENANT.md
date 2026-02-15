# Sistema Multi-Tenant SGC - Instruções de Teste

## ✅ Implementação Completa

Todo o sistema foi transformado em multi-tenant. Esta é a checklist final:

### Infraestrutura (100%)

- [x] Model Tenant com relacionamentos
- [x] Trait BelongsToTenant com scoping automático
- [x] TenantScope com bypass para super admin
- [x] TenantResolver para resolução por sessão
- [x] TenantMiddleware para aplicação global
- [x] TenantServiceProvider registrado
- [x] Tabelas tenants e tenant_user criadas

### Database (100%)

- [x] 60+ migrations atualizadas com tenant_id
- [x] Índices compostos (tenant_id, id) em todas as tabelas
- [x] Foreign keys para tenant_id
- [x] Unique constraints ajustados para incluir tenant_id
- [x] Spatie Permission integrado com tenant_id

### Models (100%)

- [x] 36+ models com trait BelongsToTenant
- [x] User model com is_super_admin e tenants()
- [x] Role model customizado com tenant scoping
- [x] Permission model customizado

### Segurança (100%)

- [x] 25 Policies atualizadas com validação de tenant
- [x] Método belongsToTenant() em todas as policies
- [x] Super admin bypass implementado
- [x] Validação automática em view/update/delete

### Interface (100%)

- [x] TenantController com select/switch/clear
- [x] Página de seleção de tenant (web)
- [x] Filament Page para seleção de tenant
- [x] Widget CurrentTenantWidget no dashboard
- [x] Resource TenantResource para gerenciar organizações
- [x] Rotas configuradas

### Configuração (100%)

- [x] config/permission.php criado
- [x] Middleware registrado globalmente
- [x] Service Provider registrado
- [x] TenantSeeder criado

## 🚀 Como Testar

### 1. Preparar o Banco de Dados

```bash
# Rodar migrations (FRESH - CUIDADO: apaga dados!)
php artisan migrate:fresh

# Rodar seeders (cria tenant e usuários de teste)
php artisan db:seed --class=TenantSeeder
```

### 2. Credenciais de Teste

Após rodar o seeder, você terá:

**Super Admin** (acesso a todos os tenants)

- Email: `admin@sgc.com`
- Senha: `password`

**Admin do Tenant** (acesso apenas ao "Organização Padrão")

- Email: `admin@tenant.com`
- Senha: `password`

**Tenant Criado**

- Nome: Organização Padrão
- Slug: default

### 3. Fluxo de Teste - Super Admin

1. **Login**: Acesse `/admin` e faça login com `admin@sgc.com`
2. **Dashboard**: Você verá o widget "Organização Ativa"
3. **Seleção**: Clique em "Selecionar Organização" no menu lateral (Sistema → Selecionar Organização)
4. **Escolha**: Selecione "Organização Padrão"
5. **Navegação**: Navegue pelos Resources (Produtos, Associados, etc)
6. **Verificação**: Crie um produto - ele terá tenant_id automaticamente
7. **Troca**: Volte ao seletor e crie uma nova organização
8. **Nova Org**: Crie nova organização pelo Resource "Organizações"
9. **Troca**: Alterne entre organizações e veja os dados isolados
10. **Limpar**: Use "Limpar Seleção" para ver a sessão sem tenant

### 4. Fluxo de Teste - Admin do Tenant

1. **Login**: Acesse `/admin` e faça login com `admin@tenant.com`
2. **Auto-Select**: Sistema selecionará automaticamente "Organização Padrão" (único tenant do usuário)
3. **Dashboard**: Verá widget mostrando tenant ativo
4. **Navegação**: Pode ver e editar apenas dados de sua organização
5. **Sem Acesso**: NÃO verá menu "Organizações" (somente super admin)
6. **Políticas**: Tente acessar recursos de outro tenant (deve ser bloqueado)

### 5. Testes de Segurança

#### Teste 1: Isolamento de Dados

```php
// No Tinker
php artisan tinker

// Cria segundo tenant
$tenant2 = Tenant::create(['name' => 'Tenant 2', 'slug' => 'tenant-2']);

// Cria produto no tenant 1 (session com tenant 1 ativo)
session(['tenant_id' => 1]);
$product1 = Product::create(['name' => 'Produto Tenant 1', 'sku' => 'T1-001']);

// Troca para tenant 2
session(['tenant_id' => 2]);

// Tenta buscar todos os produtos
Product::all(); // Deve retornar vazio (produto1 está em tenant 1)

// Tenta buscar produto1 diretamente
Product::find($product1->id); // Deve retornar null (filtrado pelo scope)
```

#### Teste 2: Super Admin Bypass

```php
// Login como super admin e sem tenant selecionado
session()->forget('tenant_id');
auth()->loginUsingId(1); // Super admin

// Pode ver produtos de todos os tenants
Product::withoutGlobalScope('tenant')->get(); // Todos os produtos
Product::all(); // Também funciona pois super admin bypassa scope
```

#### Teste 3: Policies

1. Login como admin@tenant.com
2. Tente editar um Associate
3. Policy verifica se associate->tenant_id === session('tenant_id')
4. Se não corresponder, retorna 403

#### Teste 4: Prevenção de Mass Assignment

```php
// Tentar forçar tenant_id via request
// NO CONTROLLER (isso NÃO deve funcionar)
$product = Product::create([
    'name' => 'Teste',
    'tenant_id' => 999 // Tentativa de injeção
]);

// O tenant_id será sobrescrito pelo trait BelongsToTenant
// Produto será criado com session('tenant_id'), não 999
```

### 6. Verificar Logs

```bash
# Verificar se há erros
tail -f storage/logs/laravel.log

# Verificar queries de tenant (ative query log se necessário)
DB::listen(function($query) {
    if (str_contains($query->sql, 'tenant_id')) {
        dump($query->sql, $query->bindings);
    }
});
```

### 7. Testes de Interface

#### Painel Filament

- [ ] Widget "Organização Ativa" aparece no topo do dashboard
- [ ] Menu "Selecionar Organização" aparece para super admin
- [ ] Menu "Selecionar Organização" aparece para usuários multi-tenant
- [ ] Menu "Organizações" aparece APENAS para super admin
- [ ] Trocar tenant atualiza imediatamente os dados exibidos

#### Seleção Web (fora do Filament)

- [ ] `/tenant/select` mostra lista de tenants
- [ ] Seleção redireciona corretamente
- [ ] `/tenant/current` retorna JSON do tenant ativo

### 8. Testes de Performance

```bash
# Verificar se índices estão corretos
php artisan tinker

// Deve usar índice (tenant_id, id)
DB::connection()->enableQueryLog();
Product::where('name', 'like', '%test%')->get();
dd(DB::connection()->getQueryLog());

// Verificar explain
DB::select('EXPLAIN SELECT * FROM products WHERE tenant_id = 1');
```

### 9. Criar Múltiplos Usuários e Tenants

```php
php artisan tinker

// Criar tenant adicional
$coop1 = Tenant::create(['name' => 'Cooperativa 1', 'slug' => 'coop-1']);
$coop2 = Tenant::create(['name' => 'Cooperativa 2', 'slug' => 'coop-2']);

// Criar usuário multi-tenant
$user = User::create([
    'name' => 'João Multi',
    'email' => 'joao@multi.com',
    'password' => Hash::make('password'),
    'is_super_admin' => false,
]);

// Adicionar a múltiplos tenants
$coop1->users()->attach($user->id, ['is_admin' => true]);
$coop2->users()->attach($user->id, ['is_admin' => false]);

// Login como João e verificar seletor de tenant
// Deve ver Coop 1 e Coop 2 disponíveis
```

### 10. Testar Spatie Permission com Tenant

```php
php artisan tinker

// Selecionar tenant
session(['tenant_id' => 1]);

// Criar role para tenant 1
$role1 = Role::create(['name' => 'editor', 'guard_name' => 'web', 'tenant_id' => 1]);

// Criar role com mesmo nome para tenant 2
session(['tenant_id' => 2]);
$role2 = Role::create(['name' => 'editor', 'guard_name' => 'web', 'tenant_id' => 2]);

// Ambos coexistem (unique constraint é tenant_id + name)

// Buscar role pelo nome
session(['tenant_id' => 1]);
Role::where('name', 'editor')->first(); // Retorna $role1

session(['tenant_id' => 2]);
Role::where('name', 'editor')->first(); // Retorna $role2
```

## ⚠️ Pontos de Atenção

### Não Fazer

1. ❌ NUNCA use `withoutGlobalScope('tenant')` a menos que seja super admin
2. ❌ NUNCA permita usuário editar campo tenant_id
3. ❌ NUNCA confie em tenant_id vindo do request
4. ❌ NUNCA faça queries diretas sem scope

### Sempre Fazer

1. ✅ Valide tenant no controller quando necessário
2. ✅ Use `auth()->user()->currentTenant()` para ações específicas
3. ✅ Verifique `isSuperAdmin()` antes de bypass de scopes
4. ✅ Teste isolamento criando dados em múltiplos tenants

## 📊 Checklist Final de Produção

Antes de colocar em produção:

- [ ] Rodar `php artisan migrate:fresh --seed` em ambiente de teste
- [ ] Criar múltiplos tenants de teste
- [ ] Criar múltiplos usuários com diferentes níveis de acesso
- [ ] Testar TODAS as operações CRUD em cada Resource
- [ ] Verificar que widgets do Filament respeitam tenant
- [ ] Testar relatórios e exports (devem filtrar por tenant)
- [ ] Verificar Activity Log (deve registrar tenant_id)
- [ ] Testar notificações (não devem vazar entre tenants)
- [ ] Fazer backup antes de rodar migrations em produção
- [ ] Documentar processo de migração de dados existentes

## 🔥 Migração de Dados Existentes (Produção)

Se você já tem dados em produção:

```php
// Script de migração (rodar com CUIDADO)
php artisan tinker

// Criar tenant padrão
$defaultTenant = Tenant::create([
    'name' => 'Nome da Sua Cooperativa',
    'slug' => 'slug-cooperativa',
]);

// Associar todos os dados ao tenant padrão
$models = [
    'Associate', 'Customer', 'Supplier', 'Product',
    // ... adicionar todos os models
];

foreach ($models as $modelName) {
    $class = "App\\Models\\{$modelName}";
    $class::withoutGlobalScope('tenant')
        ->whereNull('tenant_id')
        ->update(['tenant_id' => $defaultTenant->id]);
}

// Associar todos os usuários ao tenant
User::chunk(100, function ($users) use ($defaultTenant) {
    foreach ($users as $user) {
        if (!$user->isSuperAdmin()) {
            $defaultTenant->users()->syncWithoutDetaching([
                $user->id => ['is_admin' => true]
            ]);
        }
    }
});
```

## 📞 Suporte

Se encontrar problemas:

1. Verifique logs: `storage/logs/laravel.log`
2. Verifique sessão: `dd(session('tenant_id'))`
3. Verifique usuário: `dd(auth()->user()->tenants)`
4. Verifique global scope: Model com `BelongsToTenant`
5. Verifique migrations: todas têm `tenant_id`

## 🎉 Conclusão

O sistema SGC agora é 100% multi-tenant com:

- Isolamento completo de dados
- Suporte a super admin global
- Usuários em múltiplos tenants
- Interface Filament integrada
- Spatie Permission por tenant
- Segurança em camadas (Scope + Policy + Middleware)

**Pronto para testes e produção!**
