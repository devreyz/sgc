# Sistema de Fluxo de Caixa para Cooperativa

## Implementação Completa

Este documento descreve o sistema de fluxo de caixa implementado para gerenciar os projetos de venda da cooperativa.

## 📋 Funcionalidades Implementadas

### 1. **Painel de Caixa (Dashboard)**

- Widget `CashFlowWidget` exibindo:
    - Saldo atual do caixa da cooperativa
    - Saldo total em contas bancárias
    - Saldo total consolidado
    - Entradas e saídas do mês
    - Entradas e saídas do dia
    - Gráfico de tendência dos últimos 7 dias

### 2. **Novos Status do Projeto**

O enum `ProjectStatus` foi expandido com novos estados para representar o fluxo completo:

- `DRAFT` - Rascunho
- `ACTIVE` - Em Execução
- `SUSPENDED` - Suspenso
- **`AWAITING_DELIVERY`** - Aguardando Entrega (novo)
- **`DELIVERED`** - Entregue ao Cliente (novo)
- **`AWAITING_PAYMENT`** - Aguardando Pagamento (novo)
- **`PAYMENT_RECEIVED`** - Pagamento Recebido (novo)
- **`ASSOCIATES_PAID`** - Associados Pagos (novo)
- `COMPLETED` - Concluído
- `CANCELLED` - Cancelado

### 3. **Tabelas Criadas**

#### `project_payments`

Registra todos os pagamentos relacionados ao projeto:

- Pagamentos recebidos do cliente
- Pagamentos feitos aos associados
- Status, valores, datas e referências

#### `cash_movements`

Registra todas as movimentações de caixa:

- Entradas (income)
- Saídas (expense)
- Transferências entre contas
- Saldos após cada movimentação

#### Campos adicionados em `sales_projects`:

- `delivered_date` - Data da entrega ao cliente
- `payment_received_date` - Data do recebimento do pagamento
- `received_amount` - Valor recebido do cliente
- `admin_fee_collected` - Taxa administrativa coletada
- `associates_paid_amount` - Valor total pago aos associados
- `payment_bank_account_id` - Conta bancária do pagamento

### 4. **Fluxo Completo de Pagamento**

#### Passo 1: Marcar como Entregue

- **Quando**: Após todas as entregas dos associados serem aprovadas
- **Ação**: Botão "Marcar Entregue" na listagem de projetos
- **Efeito**: Muda status para `DELIVERED`

#### Passo 2: Receber Pagamento do Cliente

- **Quando**: Após entregar o produto ao cliente
- **Ação**: Botão "Receber Pagamento"
- **Campos**:
    - Data do pagamento
    - Valor recebido
    - Conta bancária (onde foi depositado)
    - Forma de pagamento
    - Número do documento
- **Efeito**:
    - Muda status para `PAYMENT_RECEIVED`
    - Cria registro em `project_payments` (tipo: `client_payment`)
    - Cria movimento de caixa (entrada)
    - Atualiza saldo da conta bancária

#### Passo 3: Pagar Associados

- **Quando**: Após receber o pagamento do cliente
- **Ação**: Botão "Pagar Associados"
- **Campos**:
    - Data do pagamento
    - Conta para pagamento
    - Forma de pagamento
- **Efeito**:
    - Muda status para `ASSOCIATES_PAID`
    - Para cada entrega aprovada:
        - Cria registro em `project_payments` (tipo: `associate_payment`)
        - Cria registro em `associate_ledgers` (crédito)
        - Atualiza saldo do associado
    - Cria movimento de caixa (saída)
    - Atualiza saldo da conta bancária

#### Passo 4: Coletar Taxa Administrativa

- **Quando**: Após pagar todos os associados
- **Ação**: Botão "Coletar Taxa"
- **Campos**:
    - Conta de caixa (destino)
    - Data da coleta
- **Efeito**:
    - Muda status para `COMPLETED`
    - Cria movimento de caixa (entrada no caixa)
    - Atualiza saldo do caixa da cooperativa
    - **Este valor fica disponível para despesas**

### 5. **Resource de Movimentos de Caixa**

- Visualizar todos os movimentos
- Filtrar por tipo, conta e período
- Criar movimentos manuais (entradas, saídas, transferências)
- Cada movimento atualiza automaticamente o saldo da conta

## 🔄 Fluxo Resumido

```
1. Criar Projeto de Venda
   ↓
2. Registrar Entregas dos Associados
   ↓
3. Aprovar Entregas
   ↓
4. [Marcar como Entregue ao Cliente]
   ↓
5. [Receber Pagamento do Cliente] → Deposita em conta bancária
   ↓
6. [Pagar Associados] → Deduz da conta bancária
   ↓
7. [Coletar Taxa Administrativa] → Transfere para caixa da cooperativa
   ↓
8. Projeto Concluído
```

## 💰 Gestão do Caixa

### Contas Disponíveis

- **Caixa** (type: 'caixa'): Dinheiro físico da cooperativa
- **Contas Bancárias**: Saldos em bancos

### Movimentos de Caixa

Todos os movimentos ficam registrados e podem ser:

- **Automáticos**: Gerados pelo fluxo de projetos
- **Manuais**: Criados para despesas, receitas extras, etc.

### Despesas

Após coletar a taxa administrativa, o saldo no caixa pode ser usado para:

- Pagar despesas operacionais
- Investimentos
- Transferir para conta bancária

## 📊 Widgets e Relatórios

### Widget de Caixa

Exibe no dashboard principal:

- Saldo em caixa
- Saldo em bancos
- Total consolidado
- Movimentação mensal e diária
- Gráfico de tendência

### Filtros Disponíveis

- Por tipo de movimento (entrada/saída/transferência)
- Por conta bancária
- Por período (data início e fim)
- Por projeto

## 🎯 Benefícios

1. **Rastreabilidade Total**: Cada real é rastreado desde o recebimento até o pagamento
2. **Transparência**: Associados podem ver seus pagamentos no ledger
3. **Controle de Caixa**: Visão clara do saldo disponível
4. **Auditoria**: Logs de todas as operações financeiras
5. **Gestão Financeira**: Separação clara entre caixa e contas bancárias

## 📝 Notas Importantes

- Todas as operações são registradas com audit log
- Os saldos são atualizados automaticamente
- O sistema valida se há saldo suficiente antes de cada operação
- Cada etapa do fluxo só pode ser executada no momento correto
- Não é possível pular etapas no fluxo de pagamento

## 🚀 Próximos Passos

Para começar a usar:

1. Execute as migrações: `php artisan migrate`
2. Configure uma conta de caixa no menu "Contas Bancárias"
3. Crie projetos de venda
4. Registre e aprove entregas
5. Siga o fluxo de pagamento descrito acima

## 📧 Suporte

Em caso de dúvidas ou problemas, consulte os logs do sistema em `storage/logs/laravel.log`.
