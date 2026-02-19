# Guia de Integridade de Dados — SGC Multi-Tenant

## Modelo Conceitual

```
┌──────────────────────────────────────────────────────────────────────────┐
│                        CAMADA GLOBAL (Super Admin)                       │
│                                                                          │
│  ┌──────────┐                                    ┌──────────────────┐    │
│  │  users   │ ← Identidade global, IMUTÁVEL      │   tenants        │    │
│  │──────────│    Nunca pode ser apagado           │──────────────────│    │
│  │ id       │                                    │ id               │    │
│  │ name     │                                    │ name             │    │
│  │ email    │◄──────────────┐                    │ ...              │    │
│  │ password │               │                    └────────┬─────────┘    │
│  └──────────┘               │                             │              │
│        ▲                    │                             │              │
│        │                    │                             │              │
│        │            ┌───────┴────────────────────────────┘              │
│        │            │                                                    │
│  ┌─────┴────────────┴──────────────────────────────────┐                │
│  │              tenant_user (VÍNCULO IMUTÁVEL)          │                │
│  │──────────────────────────────────────────────────────│                │
│  │ id (PK)          ← Chave usada nas entidades        │                │
│  │ tenant_id (FK)   ← Nunca muda                       │                │
│  │ user_id (FK)     ← Pode mudar via EmailSwapService  │                │
│  │ tenant_name      ← Nome no contexto da organização  │                │
│  │ tenant_password  ← Senha local opcional             │                │
│  │ is_admin         ← Admin da organização?            │                │
│  │ roles (JSON)     ← Papéis dentro da organização     │                │
│  │ status (bool)    ← Ativo/Inativo (NUNCA deletado)   │                │
│  │ deactivated_at   ← Quando foi desativado            │                │
│  │ deactivated_by   ← Quem desativou                   │                │
│  │ notes            ← Observações                      │                │
│  └──────────────────────────────────────────────────────┘                │
│                           │                                              │
└───────────────────────────┼──────────────────────────────────────────────┘
                            │
┌───────────────────────────┼──────────────────────────────────────────────┐
│                   CAMADA TENANT (Painel Admin)                           │
│                           │                                              │
│                           ▼                                              │
│  ┌────────────┐  ┌────────────────┐  ┌───────────────────┐              │
│  │ associates │  │service_providers│  │ service_orders    │              │
│  │────────────│  │────────────────│  │───────────────────│              │
│  │ user_id    │  │ user_id        │  │ created_by        │              │
│  │ tenant_id  │  │ tenant_id      │  │ approved_by       │              │
│  │ ...        │  │ ...            │  │ tenant_id         │              │
│  └────────────┘  └────────────────┘  │ ...               │              │
│                                      └───────────────────┘              │
│                                                                          │
│  ┌────────────┐  ┌────────────┐  ┌────────────┐  ┌──────────────┐      │
│  │ expenses   │  │ bank_accts │  │ products   │  │ activity_log │      │
│  │────────────│  │────────────│  │────────────│  │──────────────│      │
│  │ created_by │  │ tenant_id  │  │ tenant_id  │  │ tenant_id ←NEW│     │
│  │ approved_by│  │ ...        │  │ ...        │  │ causer_id    │      │
│  │ tenant_id  │  └────────────┘  └────────────┘  │ ...          │      │
│  │ ...        │                                  └──────────────┘      │
│  └────────────┘                                                          │
│                                                                          │
│  Todas as entidades usam SoftDeletes — são DESATIVADAS, nunca apagadas  │
│  ForceDelete foi removido de TODOS os Resources do painel Admin         │
└──────────────────────────────────────────────────────────────────────────┘
```

---

## Regras Fundamentais

| Regra                           | Implementação                                                                                                                                                    |
| ------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Usuários NUNCA são apagados** | `User::booted()` bloqueia `deleting` (force delete sempre bloqueado, soft delete só super_admin). `UserPolicy` retorna `false` em delete/forceDelete.            |
| **Vínculos NUNCA são apagados** | `TenantUser::booted()` bloqueia `deleting` com RuntimeException. `TenantUserPolicy` retorna `false` em delete/forceDelete. `Tenant::removeUser()` lança exceção. |
| **Vínculos são desativados**    | `TenantUser::deactivate($by)` e `::activate()`. Status rastreado via `status`, `deactivated_at`, `deactivated_by`.                                               |
| **tenant_id nunca muda**        | `TenantUser::booted()` bloqueia alteração de `tenant_id` no evento `updating`.                                                                                   |
| **Entidades usam SoftDeletes**  | Todos os modelos de negócio usam SoftDeletes. Registros são soft-deleted, nunca force-deleted.                                                                   |
| **ForceDelete removido**        | Removido de 29 arquivos (16 BulkActions + 13 EditActions) em todos os Resources do painel Admin.                                                                 |
| **Logs têm tenant_id**          | `BelongsToTenant::tapActivity()` e `User::tapActivity()` injetam `tenant_id` automaticamente.                                                                    |

---

## Arquivos Criados

| Arquivo                                                                       | Propósito                                                                                                                                                   |
| ----------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `app/Models/TenantUser.php`                                                   | Modelo Eloquent para o pivot `tenant_user`. Imutável (não pode ser deletado), com scopes, helpers e log de atividade.                                       |
| `app/Services/EmailSwapService.php`                                           | Troca segura de email: cria/reutiliza User, atualiza `tenant_user.user_id` + `associates.user_id` + `service_providers.user_id`, tudo em transação com log. |
| `app/Filament/Resources/TenantUserResource.php`                               | Resource Filament para gerenciar membros da organização. Substitui o antigo UserResource no painel Admin.                                                   |
| `app/Filament/Resources/TenantUserResource/Pages/ListTenantUsers.php`         | Página de listagem de membros.                                                                                                                              |
| `app/Filament/Resources/TenantUserResource/Pages/CreateTenantUser.php`        | Página de vinculação de novo membro (com lookup de email existente).                                                                                        |
| `app/Filament/Resources/TenantUserResource/Pages/EditTenantUser.php`          | Página de edição de membro (sem ações de exclusão).                                                                                                         |
| `app/Policies/TenantUserPolicy.php`                                           | Policy com bloqueio total de delete/forceDelete/replicate/reorder.                                                                                          |
| `app/Traits/LogsTenantActivity.php`                                           | Trait auxiliar para injeção de tenant_id em logs (disponível para modelos que não usam BelongsToTenant).                                                    |
| `database/migrations/2026_02_18_000001_enhance_tenant_user_for_integrity.php` | Adiciona `status`, `deactivated_at`, `deactivated_by`, `notes` e índices à tabela `tenant_user`.                                                            |
| `database/migrations/2026_02_18_000002_add_tenant_id_to_activity_log.php`     | Adiciona `tenant_id` (FK para tenants) à tabela `activity_log`.                                                                                             |

---

## Arquivos Modificados

### Modelos

| Arquivo                 | Alteração                                                                  |
| ----------------------- | -------------------------------------------------------------------------- |
| `app/Models/User.php`   | `booted()` bloqueia exclusão. `tapActivity()` injeta `tenant_id` nos logs. |
| `app/Models/Tenant.php` | `removeUser()` agora lança RuntimeException.                               |

### Traits

| Arquivo                          | Alteração                                                                                                          |
| -------------------------------- | ------------------------------------------------------------------------------------------------------------------ |
| `app/Traits/BelongsToTenant.php` | Adicionado `tapActivity()` que injeta `tenant_id` nos logs de atividade para todos os modelos que usam este trait. |

### Policies

| Arquivo                       | Alteração                                                                                        |
| ----------------------------- | ------------------------------------------------------------------------------------------------ |
| `app/Policies/UserPolicy.php` | `delete`, `deleteAny`, `forceDelete`, `forceDeleteAny`, `replicate`, `reorder` → `return false`. |

### Resources Filament (Admin)

| Arquivo                                           | Alteração                                                                                                                |
| ------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------ |
| `app/Filament/Resources/UserResource.php`         | `$shouldRegisterNavigation = false`, `canAccess()` retorna `false`. Substituído por TenantUserResource.                  |
| `app/Filament/Resources/CashMovementResource.php` | Adicionado `TrashedFilter`, `RestoreBulkAction`, `RestoreAction`, `getEloquentQuery()` com remoção de SoftDeletingScope. |
| `app/Filament/Resources/ActivityLogResource.php`  | `canViewAny()` permite admins com permissão. `getEloquentQuery()` filtra por `tenant_id` para não-super-admins.          |

### ForceDelete removido (29 arquivos)

**ForceDeleteBulkAction removido de 16 Resources:**
Asset, Associate, BankAccount, ChartAccount, CollectivePurchase, Customer, DirectPurchase, DocumentTemplate, Expense, Loan, Product, PurchaseOrder, SalesProject, ServiceOrder, Service, Supplier.

**ForceDeleteAction removido de 13 Edit pages:**
EditAsset, EditAssociate, EditBankAccount, EditChartAccount, EditCollectivePurchase, EditCustomer, EditExpense, EditProduct, EditPurchaseOrder, EditSalesProject, EditServiceOrder, EditService, EditSupplier.

### RestoreAction adicionado a 6 Edit pages que não tinham:

EditCashMovement, EditDirectPurchase, EditDocumentTemplate, EditEquipment, EditLoan, EditProductionDelivery.

### RestoreBulkAction adicionado a 2 Resources que não tinham:

EquipmentResource, ProductionDeliveryResource.

### Resources Filament (Super Admin)

| Arquivo                                                    | Alteração                                 |
| ---------------------------------------------------------- | ----------------------------------------- |
| `app/Filament/SuperAdmin/Resources/UserResource.php`       | Removido DeleteAction e DeleteBulkAction. |
| `app/Filament/SuperAdmin/Resources/UserTenantResource.php` | Removido DeleteBulkAction.                |
| `app/Filament/SuperAdmin/Resources/TenantResource.php`     | Removido DeleteAction e DeleteBulkAction. |

---

## Tabelas com Referência a `users`

19 tabelas referenciam a tabela `users` através de 26 colunas:

| Coluna             | Tabelas                                                                                                                                                                                 |
| ------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `user_id` (FK)     | tenant_user, associates, service_providers, cash_movements                                                                                                                              |
| `created_by` (FK)  | bank_accounts, cash_movements, chart_accounts, collective_purchases, customers, direct_purchases, expenses, loans, products, purchase_orders, sales_projects, service_orders, suppliers |
| `approved_by` (FK) | direct_purchases, expenses, loans, production_deliveries, purchase_orders, service_orders, service_provider_payments                                                                    |
| `operator_id` (FK) | service_orders                                                                                                                                                                          |
| `causer_id` (FK)   | activity_log                                                                                                                                                                            |

---

## Fluxo de Troca de Email (EmailSwapService)

```
Membro quer trocar email: joao@old.com → joao@new.com
                    │
                    ▼
        ┌─ Email novo já existe como User? ──┐
        │                                     │
       SIM                                   NÃO
        │                                     │
        ▼                                     ▼
  Reutiliza User existente          Cria novo User com
  (se soft-deleted, restaura)       name + email + senha aleatória
        │                                     │
        └──────────┬──────┬───────────────────┘
                   │      │
                   ▼      ▼
          Dentro de DB::transaction():
          1. tenant_user.user_id = novo user.id
          2. associates.user_id = novo user.id (mesmo tenant)
          3. service_providers.user_id = novo user.id (mesmo tenant)
          4. Log de atividade registrado
```

---

## Sistema de Papéis

| Camada           | Mecanismo                                           | Uso                                  |
| ---------------- | --------------------------------------------------- | ------------------------------------ |
| **Global**       | Spatie Permission (`model_has_roles`)               | `super_admin`, `panel_user`          |
| **Por Tenant**   | `tenant_user.is_admin` + `tenant_user.roles` (JSON) | Admin da organização, papéis locais  |
| **Por Resource** | Filament Shield (permissões granulares)             | `view_asset`, `create_expense`, etc. |

---

## Camadas de Proteção

```
┌─────────────────────────────────────────────┐
│ 1. POLICY           → return false          │ ← Primeira barreira
├─────────────────────────────────────────────┤
│ 2. MODEL BOOT       → throw/cancel         │ ← Se policy for ignorada
├─────────────────────────────────────────────┤
│ 3. UI (RESOURCE)    → ação removida         │ ← Sem botão no painel
├─────────────────────────────────────────────┤
│ 4. TENANT SCOPE     → filtro automático     │ ← Isolamento de dados
├─────────────────────────────────────────────┤
│ 5. AUDIT LOG        → tudo registrado       │ ← Rastreio completo
└─────────────────────────────────────────────┘
```

---

## Cuidados ao Rodar `shield:generate`

> ⚠️ **ATENÇÃO**: O comando `php artisan shield:generate --all` sobrescreve as Policies existentes. Após executá-lo, é necessário re-aplicar manualmente os bloqueios em:
>
> - `UserPolicy.php` — delete, forceDelete, replicate, reorder → `return false`
> - `TenantUserPolicy.php` — delete, forceDelete, replicate, reorder → `return false`

---

## Migrações Aplicadas

```bash
# Já executadas:
2026_02_18_000001_enhance_tenant_user_for_integrity  ✅
2026_02_18_000002_add_tenant_id_to_activity_log      ✅
2026_02_19_000001_add_email_history_to_tenant_user    ✅
```

---

## Melhorias de Segurança e Usabilidade (19/02/2026)

### 1. Eliminação de Vazamento de Emails

**Problema**: TenantUserResource listava todos os emails do sistema no Select, expondo informações sensíveis.

**Solução**:

- Substituído `Select` por `TextInput` simples no form de criação
- Lookup de User existente é feito no backend (`CreateTenantUser::mutateFormDataBeforeCreate`)
- Se email já existir, User é reutilizado/restaurado automaticamente
- Se não existir, novo User é criado com senha aleatória (a senha real fica em `tenant_password`)

### 2. Senha no Vínculo

**Problema**: Senha estava sendo criada no User global, comprometendo a separação entre identidade global e acesso por tenant.

**Solução**:

- Campo `tenant_user.tenant_password` (com cast `hashed`) armazena a senha real
- User global recebe senha aleatória forte e nunca é usada para autenticação
- Form de criação: campo `tenant_password` obrigatório
- Form de edição: campo `tenant_password` opcional (se vazio, mantém a atual)
- `EditTenantUser::mutateFormDataBeforeSave()` faz hash da senha antes de salvar

### 3. Histórico de Emails

**Problema**: Ao trocar email via `EmailSwapService`, o histórico era perdido.

**Solução**:

- Adicionado campo `tenant_user.email_history` (JSON)
- `EmailSwapService` registra cada troca: `[{email, changed_at, changed_by, new_email}]`
- TenantUserResource exibe coluna "Histórico de Emails" (toggleable) com todas as trocas
- Tooltip mostra timeline completa: `email1 → email2 em dd/mm/YYYY HH:mm`

### 4. Logs de Atividade Visíveis e Amigáveis

**Problema**: ActivityLogResource não estava visível para admins, e a visualização era técnica demais.

**Solução**:

**Visibilidade**:

- `canViewAny()` e `shouldRegisterNavigation()` já permitem admins com permissão `view_any_activity`
- Filtro automático por `tenant_id` para não-super-admins

**Melhorias na Tabela**:

- Colunas mais legíveis com traduções (User, Ação, Entidade)
- Descrições em português: ✓ Criado, ✎ Atualizado, ⨯ Excluído, ↺ Restaurado
- Coluna "Alterações" mostra preview das mudanças (campo: old → new)
- Coluna "Histórico de Emails" no TenantUserResource
- Tradução automática de entidades (ServiceOrder → Ordem de Serviço, etc.)

**Filtros Avançados**:

- Por ação (created, updated, deleted, restored) — múltipla seleção
- Por entidade (com traduções) — múltipla seleção
- Por usuário (com lookup) — múltipla seleção
- Por tipo de log — múltipla seleção
- Por período (com indicador visual: "De 10/02 até 19/02")

**Relatórios Exportáveis**:

- Botão "Gerar Relatório Completo" com seleção de período
- Exportação CSV (compatível com Excel com UTF-8 BOM)
- Formato: `Data/Hora;Usuário;Ação;Entidade;ID;Descrição`
- Bulk action "Exportar Selecionados"

**Visualização Detalhada**:

- Infolist melhorado com seções claras
- Tabela de alterações side-by-side (valor antigo ← → valor novo)
- Cores: vermelho para old, verde para new
- View customizada: `filament.infolists.activity-changes`
- Campos sensíveis (password, tokens) são automaticamente omitidos
- Valores booleanos traduzidos (Sim/Não)
- Arrays formatados como lista
- Campos traduzidos (name → Nome, email → E-mail, etc.)

**Arquivos Criados**:

- `resources/views/filament/infolists/activity-changes.blade.php` — View customizada para exibir mudanças de forma amigável

---

## Comandos de Verificação

```bash
# Verificar sintaxe PHP
php -l app/Filament/Resources/TenantUserResource.php
php -l app/Services/EmailSwapService.php
php -l app/Models/TenantUser.php
php -l app/Filament/Resources/ActivityLogResource.php

# Rodar migrações
php artisan migrate

# Verificar permissões (após shield:generate)
php artisan shield:generate --all
# ⚠️ Re-aplicar bloqueios em UserPolicy e TenantUserPolicy!

# Boot da aplicação (verifica erros fatais)
php artisan about

# Verificar rotas
php artisan route:list --name=filament
```
