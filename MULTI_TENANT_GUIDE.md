# Sistema Multi-Tenant SGC

## Visão Geral

O sistema SGC foi transformado em uma aplicação multi-tenant (multi-organização) completa, permitindo que múltiplas organizações usem o mesmo sistema com isolamento total de dados.

## Características Principais

### 1. Arquitetura Multi-Tenant

- **Um Banco de Dados**: Todas as organizações compartilham o mesmo banco de dados
- **Isolamento por tenant_id**: Cada registro possui um `tenant_id` que garante separação de dados
- **Escopo Automático**: Global scope aplicado automaticamente em todos os models de negócio
- **Super Admin Global**: Usuário especial que pode acessar todos os tenants

### 2. Estrutura de Dados

#### Tabela `tenants`

- `id`: ID do tenant
- `name`: Nome da organização
- `slug`: Identificador único (URL-friendly)
- `settings`: Configurações específicas (JSON)

#### Tabela `tenant_user` (pivot)

- `tenant_id`: ID do tenant
- `user_id`: ID do usuário
- `is_admin`: Se o usuário é admin do tenant
- Permite usuários pertencerem a múltiplos tenants

#### Tabela `users`

- `is_super_admin`: Flag para super admin global
- Não possui `tenant_id` direto (relação many-to-many com tenants)

### 3. Fluxo de Autenticação

1. Usuário faz login
2. Sistema verifica quantos tenants o usuário possui:
    - **1 tenant**: Seleciona automaticamente
    - **Múltiplos tenants**: Redireciona para página de seleção
    - **Nenhum tenant**: Mostra erro (exceto super admin)
3. Tenant selecionado é armazenado na sessão
4. Todas as operações subsequentes são filtradas por esse tenant

### 4. Componentes do Sistema

#### BelongsToTenant Trait

```php
use App\Traits\BelongsToTenant;

class Associate extends Model
{
    use BelongsToTenant;
}
```

Funcionalidades:

- Adiciona global scope automático
- Define `tenant_id` automaticamente ao criar
- Adiciona relacionamento com Tenant
- Ignora escopo para super admin

#### TenantResolver Service

- Resolve tenant atual da sessão
- Valida acesso do usuário
- Gerencia troca de tenant
- Fornece lista de tenants disponíveis

#### TenantMiddleware

- Registra tenant no container do Laravel
- Bloqueia acesso sem tenant ativo
- Permite super admin acessar sem tenant (para seleção)

#### TenantScope

- Filtra automaticamente queries por `tenant_id`
- Ignora filtro para super admin
- Aplicado globalmente em todos os models de negócio

### 5. Spatie Permission Integration

#### Roles por Tenant

- Cada role pertence a um tenant
- Mesma role pode existir em múltiplos tenants
- Unique constraint: `(tenant_id, name, guard_name)`

#### Pivot Tables com Tenant

- `model_has_roles`: Inclui `tenant_id`
- `model_has_permissions`: Inclui `tenant_id`
- Garante que roles/permissions são por tenant

#### Super Admin Bypass

- Super admin ignora todos os scopes
- Pode ver e editar dados de qualquer tenant
- Único autorizado a criar novos tenants

### 6. Modelos Atualizados

Todos os models de negócio incluem `BelongsToTenant`:

**Core**

- Associate, Customer, Supplier, Asset

**Produtos**

- Product, ProductCategory

**Financeiro**

- BankAccount, ChartAccount, Expense, Revenue
- AssociateLedger, CashMovement

**Projetos**

- SalesProject, ProjectDemand, ProjectPayment
- ProductionDelivery

**Compras**

- CollectivePurchase, PurchaseItem, PurchaseOrder, PurchaseOrderItem
- DirectPurchase, DirectPurchaseItem

**Serviços**

- Service, ServiceOrder, ServiceOrderPayment
- ServiceProvider, ServiceProviderLedger, ServiceProviderWork
- ServiceProviderService, ProviderPaymentRequest

**Documentos**

- Document, DocumentTemplate, GeneratedDocument

**Estoque**

- StockMovement

**Equipamentos**

- Equipment, MaintenanceType, MaintenanceSchedule
- MaintenanceRecord, EquipmentReading

**Empréstimos**

- Loan, LoanPayment

### 7. Segurança

#### Prevenção de Vazamento

- `tenant_id` nunca vem do request
- Sempre resolvido internamente
- Bloqueio de mass assignment
- Scope automático em todas queries

#### Validação de Acesso

- Todas policies verificam `tenant_id`
- Controllers validam tenant antes de qualquer operação
- Filament Resources respeitam tenant automaticamente

#### Logs e Auditoria

- Activity log rastreia operações
- Tenant registrado em operações críticas
- Impossível fazer `withoutGlobalScope()` sem ser super admin

### 8. Performance

#### Índices Otimizados

Todas as tabelas possuem:

- `(tenant_id, id)`: Query primária
- `(tenant_id, campo_de_busca)`: Para buscas
- Índices compostos para foreign keys

#### Preparação para Escala

- Estrutura pronta para particionamento por `tenant_id`
- Cache por tenant separado
- Queries sempre filtradas desde o início

### 9. Uso no Código

#### Criar Registro

```php
// Tenant ID é automaticamente adicionado
$associate = Associate::create([
    'user_id' => $userId,
    'cpf_cnpj' => '123.456.789-00',
    // tenant_id adicionado automaticamente
]);
```

#### Buscar Registros

```php
// Automaticamente filtrado por tenant atual
$associates = Associate::all();

// Super admin pode ver tudo
$allAssociates = Associate::withoutGlobalScope('tenant')->get();
```

#### Trocar Tenant

```php
// Usuário
app(TenantResolver::class)->setTenant($tenantId);

// Super Admin pode trocar livremente
session(['tenant_id' => $tenantId]);
```

### 10. Filament Integration

- Recursos respeitam tenant automaticamente
- Super admin vê seletor de tenant no painel
- Usuário comum não pode trocar tenant pelo Filament
- Formulários não permitem editar `tenant_id`
- Listagens filtradas por tenant

### 11. Migração de Dados Existentes

Para migrar dados existentes:

```php
// Associar todos os registros ao tenant padrão
$defaultTenant = Tenant::first();

Associate::withoutGlobalScope('tenant')
    ->whereNull('tenant_id')
    ->update(['tenant_id' => $defaultTenant->id]);
```

### 12. Credenciais de Teste

Após rodar o seeder:

**Super Admin**

- Email: admin@sgc.com
- Password: password
- Acesso: Todos os tenants

**Admin do Tenant**

- Email: admin@tenant.com
- Password: password
- Acesso: Tenant "Organização Padrão"

### 13. Rotas do Sistema

- `/tenant/select`: Seleção de tenant
- `/tenant/switch`: Trocar tenant
- `/tenant/clear`: Limpar tenant ativo
- `/tenant/current`: API - tenant atual

### 14. Comandos Úteis

```bash
# Rodar migrations
php artisan migrate:fresh

# Rodar seeder de tenants
php artisan db:seed --class=TenantSeeder

# Criar novo tenant
php artisan tinker
>>> Tenant::create(['name' => 'Nova Org', 'slug' => 'nova-org'])

# Adicionar usuário a tenant
>>> $tenant->users()->attach($userId, ['is_admin' => true])
```

## Conclusão

O sistema está completamente preparado para multi-tenancy com:

- ✅ Isolamento total de dados
- ✅ Suporte a múltiplos tenants por usuário
- ✅ Super admin global
- ✅ Integração com Filament
- ✅ Integração com Spatie Permission
- ✅ Performance otimizada
- ✅ Segurança robusta
- ✅ Preparado para escala
