# Sistema Multi-Tenant com Rotas baseadas em Slug

## Visão Geral

O sistema foi atualizado para suportar **multi-tenancy baseado em slug nas URLs**. Cada organização (tenant) possui um identificador único (slug) que aparece na URL, garantindo isolamento completo de dados e melhor experiência do usuário.

## Estrutura de URLs

### Antes (URLs sem contexto de organização)

```
/provider/dashboard
/associate/projects
/delivery/register
```

### Depois (URLs com slug da organização)

```
/cooperativa-abc/provider/dashboard
/associacao-xyz/associate/projects
/fazenda-verde/delivery/register
```

## Componentes do Sistema

### 1. Middleware: TenantFromSlugMiddleware

**Localização:** `app/Http/Middleware/TenantFromSlugMiddleware.php`

**Função:**

- Resolve o tenant automaticamente a partir do slug na URL
- Define `tenant_id` na sessão para uso global
- Compartilha objeto `$currentTenant` com todas as views
- Configura locale se definido no tenant

**Como funciona:**

```php
// Laravel faz route model binding automático
Route::get('/{tenant:slug}/provider/dashboard', ...)
  ->middleware('tenant.slug');

// No middleware:
$tenant = $request->route('tenant'); // Já é objeto Tenant
session(['tenant_id' => $tenant->id]);
view()->share('currentTenant', $tenant);
```

**Registro:** `bootstrap/app.php`

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'tenant.slug' => \App\Http\Middleware\TenantFromSlugMiddleware::class,
        // ...
    ]);
})
```

### 2. Rotas Atualizadas

**Localização:** `routes/web.php`

Todas as rotas de portais legados (provider, associate, delivery) estão agrupadas com prefixo `{tenant:slug}`:

```php
// Grupo com prefixo tenant slug
Route::prefix('{tenant:slug}')->middleware(['auth', 'tenant.slug'])->group(function () {

    // Provider (Prestador de Serviço)
    Route::prefix('provider')->name('provider.')->group(function () {
        Route::get('/dashboard', [ProviderDashboardController::class, 'index'])
            ->name('dashboard');
        // ... outras rotas
    });

    // Associate (Associado)
    Route::prefix('associate')->name('associate.')->group(function () {
        Route::get('/dashboard', [AssociateDashboardController::class, 'index'])
            ->name('dashboard');
        // ... outras rotas
    });

    // Delivery (Registrador de Entregas)
    Route::prefix('delivery')->name('delivery.')->group(function () {
        Route::get('/dashboard', [DeliveryRegistrationController::class, 'index'])
            ->name('dashboard');
        // ... outras rotas
    });
});
```

### 3. Controllers Atualizados

Todos os controllers de portais legados foram atualizados para:

#### Validar Tenant na Sessão

```php
public function index()
{
    $tenantId = session('tenant_id');
    if (!$tenantId) {
        return redirect()->route('home')
            ->with('error', 'Selecione uma organização primeiro.');
    }
    // ... resto do código
}
```

#### Filtrar Queries por Tenant

```php
// ANTES
$orders = ServiceOrder::where('service_provider_id', $provider->id)->get();

// DEPOIS
$orders = ServiceOrder::where('tenant_id', $tenantId)
    ->where('service_provider_id', $provider->id)
    ->get();
```

#### Redirects com Slug

```php
// ANTES
return redirect()->route('provider.orders');

// DEPOIS
return redirect()->route('provider.orders', [
    'tenant' => request()->route('tenant')->slug
]);
```

**Controllers atualizados:**

- `app/Http/Controllers/Provider/ProviderDashboardController.php` (todos os métodos)
- `app/Http/Controllers/Associate/AssociateDashboardController.php` (todos os métodos)
- `app/Http/Controllers/Delivery/DeliveryRegistrationController.php` (todos os métodos)
- `app/Http/Controllers/HubController.php` (reescrito para seleção de tenant)

### 4. Views Atualizadas

Todas as views dos portais foram atualizadas para incluir o parâmetro `tenant` em rotas:

#### Padrão de Atualização

```blade
{{-- ANTES --}}
<a href="{{ route('provider.dashboard') }}">Dashboard</a>
<a href="{{ route('provider.orders.show', $order->id) }}">Ver Ordem</a>

{{-- DEPOIS --}}
<a href="{{ route('provider.dashboard', ['tenant' => $currentTenant->slug]) }}">Dashboard</a>
<a href="{{ route('provider.orders.show', ['tenant' => $currentTenant->slug, 'order' => $order->id]) }}">Ver Ordem</a>
```

**Views atualizadas:**

- Provider: 11 arquivos (dashboard, orders, show-order, create-order, financial, etc.)
- Associate: 6 arquivos (dashboard, projects, deliveries, ledger, etc.)
- Delivery: 4 arquivos (dashboard, register, project-deliveries)

**Variável Global:** `$currentTenant` está disponível em todas as views através do middleware.

### 5. HubController - Seleção de Tenant

**Localização:** `app/Http/Controllers/HubController.php`

**Fluxo:**

1. Usuário faz login
2. HubController verifica se há `tenant_id` na sessão
3. Se NÃO:
    - Busca tenants associados ao usuário
    - Se tem apenas 1 tenant: define automaticamente e redireciona
    - Se tem múltiplos: mostra tela de seleção (`resources/views/tenant/select.blade.php`)
4. Se SIM:
    - Mostra cards com roles disponíveis
    - URLs dos cards incluem slug do tenant atual

**Exemplo de código:**

```php
public function index(Request $request)
{
    $user = $request->user();
    $sessionTenantId = session('tenant_id');

    // Se não tem tenant na sessão, força seleção
    if (!$sessionTenantId) {
        $userTenants = $user->tenants;

        if ($userTenants->isEmpty()) {
            return view('hub.no-tenants');
        }

        if ($userTenants->count() === 1) {
            $tenant = $userTenants->first();
            session(['tenant_id' => $tenant->id]);
            return redirect()->route('hub'); // Recarrega hub com tenant definido
        }

        return view('tenant.select', ['tenants' => $userTenants]);
    }

    // ... resto do hub com cards de roles
}
```

## Fluxo Completo de Uso

### 1. Login

```
POST /login
↓
Redirect para /hub
```

### 2. Seleção de Tenant (se necessário)

```
GET /hub
↓
Verifica session('tenant_id')
↓
Se NÃO existe: mostra /tenant/select
↓
User seleciona tenant
↓
POST /tenant/select → define session('tenant_id')
↓
Redirect para /hub
```

### 3. Navegação nos Portais

```
GET /hub
↓
User clica no role (ex: "Prestador de Serviço")
↓
GET /cooperativa-abc/provider/dashboard
↓
Middleware 'tenant.slug' resolve tenant e define session
↓
Controller valida tenant_id e filtra dados
↓
View renderizada com $currentTenant disponível
```

### 4. Navegação Interna

```
User clica em "Ordens de Serviço"
↓
GET /cooperativa-abc/provider/orders
↓
Middleware já tem tenant definido
↓
Controller filtra ordens por tenant_id
↓
View usa route('provider.orders.show', ['tenant' => $currentTenant->slug, 'order' => $id])
```

### 5. Logout

```
POST /logout
↓
Limpa session (incluindo tenant_id)
↓
Redirect para /login
```

## Segurança e Isolamento

### 1. Isolamento de Dados

Todas as queries em controllers incluem filtro por `tenant_id`:

```php
$data = Model::where('tenant_id', $tenantId)->get();
```

### 2. Validação Dupla

- Middleware: verifica se tenant no slug existe
- Controller: verifica se user pertence ao tenant (via session)

### 3. AJAX Endpoints

Endpoints AJAX também validam tenant:

```php
public function getProjectDemands($projectId)
{
    $tenantId = session('tenant_id');
    if (!$tenantId) {
        return response()->json(['error' => 'Tenant não encontrado'], 403);
    }

    $demands = ProjectDemand::where('tenant_id', $tenantId)
        ->where('sales_project_id', $projectId)
        ->get();
    //...
}
```

## Patterns e Convenções

### Controllers

**Padrão de Método:**

```php
public function methodName($param1, $param2 = null)
{
    // 1. Validar tenant
    $tenantId = session('tenant_id');
    if (!$tenantId) {
        return redirect()->route('home')
            ->with('error', 'Selecione uma organização primeiro.');
    }

    // 2. Buscar dados com filtro de tenant
    $data = Model::where('tenant_id', $tenantId)
        ->where('other_field', $value)
        ->get();

    // 3. Processar/validar
    // ...

    // 4. Retornar com tenant no redirect
    return redirect()->route('route.name', [
        'tenant' => request()->route('tenant')->slug,
        'param' => $value
    ]);
}
```

### Views

**Pattern para Links:**

```blade
{{-- Link simples --}}
<a href="{{ route('portal.action', ['tenant' => $currentTenant->slug]) }}">Label</a>

{{-- Link com parâmetros --}}
<a href="{{ route('portal.show', ['tenant' => $currentTenant->slug, 'id' => $item->id]) }}">Ver</a>

{{-- Form action --}}
<form action="{{ route('portal.store', ['tenant' => $currentTenant->slug]) }}" method="POST">
    @csrf
    {{-- campos --}}
</form>
```

**Pattern para Navegação:**

```blade
<nav class="nav-tabs">
    <a href="{{ route('portal.dashboard', ['tenant' => $currentTenant->slug]) }}"
       class="nav-tab {{ request()->routeIs('portal.dashboard') ? 'active' : '' }}">
        Dashboard
    </a>
    <a href="{{ route('portal.list', ['tenant' => $currentTenant->slug]) }}"
       class="nav-tab {{ request()->routeIs('portal.list') ? 'active' : '' }}">
        Lista
    </a>
</nav>
```

## Troubleshooting

### Erro: "Selecione uma organização primeiro"

**Causa:** Session não tem `tenant_id` definido
**Solução:**

1. Voltar ao `/hub`
2. Selecionar tenant novamente
3. Se persistir, verificar se user tem tenants associados

### Erro: Route not found / Missing parameter

**Causa:** Rota chamada sem parâmetro `tenant`
**Solução:** Adicionar `['tenant' => $currentTenant->slug]` ao route()

### Erro: Acesso a dados de outro tenant

**Causa:** Query sem filtro `tenant_id`
**Solução:** Adicionar `->where('tenant_id', $tenantId)` em todas as queries

### Erro: $currentTenant undefined na view

**Causa:** Middleware não executado ou rota não incluída no grupo
**Solução:** Verificar se rota está no grupo com middleware `tenant.slug`

## Testes Recomendados

### Teste 1: Isolamento de Dados

1. Login com user vinculado a Tenant A
2. Acessar portal e criar registros
3. Logout
4. Login com user vinculado a Tenant B
5. Verificar que registros de Tenant A não aparecem

### Teste 2: Navegação

1. Login e selecionar tenant
2. Navegar entre páginas do portal
3. Verificar que slug permanece na URL
4. Clicar em links internos
5. Verificar que dados mostrados são do tenant correto

### Teste 3: Segurança

1. Login em Tenant A
2. Copiar URL de um registro
3. Logout
4. Login em Tenant B
5. Tentar acessar URL copiada (substituindo slug)
6. Deve resultar em erro 404 ou redirect

### Teste 4: Multi-Tenant Para Mesmo User

1. Login com user vinculado a múltiplos tenants
2. Selecionar Tenant A no hub
3. Acessar portal, criar registro
4. Voltar ao `/hub`
5. Selecionar Tenant B
6. Verificar que registro de Tenant A não aparece

## Próximos Passos

### Melhorias Futuras

1. **Cache de Tenant:** Cache do tenant na sessão para reduzir queries
2. **Tenant Switching:** Adicionar opção no menu para trocar tenant sem logout
3. **Auditoria:** Log de acessos cross-tenant
4. **Testes Automatizados:** Feature tests para isolamento de dados
5. **Middleware Global:** Aplicar tenant scoping automaticamente em queries (via trait ou Global Scope)

### Documentação Adicional

- Ver `USUARIOS_TESTE.md` para users de teste por tenant
- Ver `FLUXO_FINANCEIRO_SERVICOS.md` para fluxos específicos de portais
- Ver `SISTEMA_PRECOS_NAO_ASSOCIADOS.md` para lógica de preços

## Conclusão

O sistema agora possui isolamento completo multi-tenant com URLs baseadas em slug, garantindo:

- ✅ Segurança: Dados isolados por organização
- ✅ UX: URLs legíveis e contextualizadas
- ✅ Manutenibilidade: Pattern consistente em todos controllers/views
- ✅ Escalabilidade: Suporte para infinitos tenants sem conflito

**Importante:** Sempre incluir filtro `tenant_id` e parâmetro `tenant` em rotas ao criar novos recursos.
