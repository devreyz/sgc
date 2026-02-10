# Fluxo Financeiro Completo - Ordens de Serviço

## 📊 Visão Geral

Sistema implementado para gerenciar o fluxo financeiro completo de ordens de serviço, separando:

- **Valor cobrado do associado** (pelo serviço recebido)
- **Valor pago ao prestador** (pelo trabalho executado)
- **Lucro da cooperativa** (diferença entre valores)

## 💰 Estrutura de Valores

### Tabela `services`

Cada serviço possui:

| Campo                  | Descrição                              | Exemplo          |
| ---------------------- | -------------------------------------- | ---------------- |
| `base_price`           | Valor cobrado do associado por unidade | R$ 150,00/hora   |
| `provider_hourly_rate` | Valor pago ao prestador por hora       | R$ 50,00/hora    |
| `provider_daily_rate`  | Valor pago ao prestador por diária     | R$ 300,00/dia    |
| `unit`                 | Unidade de medida                      | hora, diaria, km |

**Cálculo do lucro da cooperativa:**

```
Lucro = (base_price - provider_rate) × quantidade
```

**Exemplo:**

- Serviço: Hora de Trator
- `base_price` = R$ 150,00/hora
- `provider_hourly_rate` = R$ 50,00/hora
- Quantidade executada: 8 horas

```
Valor cobrado do associado = 8 × R$ 150 = R$ 1.200,00
Valor pago ao prestador    = 8 × R$ 50  = R$ 400,00
Lucro da cooperativa       = R$ 1.200 - R$ 400 = R$ 800,00
```

## 🔄 Fluxo Completo

### 1. Criação da Ordem de Serviço

**Quem:** Prestador de serviço  
**Onde:** Portal do Prestador → Criar Nova Ordem

**O que acontece:**

- Prestador informa:
    - Tipo de serviço
    - Local
    - Data agendada
    - Quantidade estimada (opcional)
    - Associado (opcional)
    - Equipamento (opcional)
- Sistema cria ordem com status `scheduled`
- **Nenhum movimento financeiro ainda**

**Status:**

- `status` = `scheduled`
- `associate_payment_status` = `pending`
- `provider_payment_status` = `pending`

### 2. Execução do Serviço

**Quem:** Prestador de serviço **Onde:** Portal do Prestador → Ver Ordem → Finalizar Serviço

**O que acontece:**

- Prestador informa:
    - Data de execução
    - **Quantidade efetivamente trabalhada** (campo obrigatório!)
    - Descrição do trabalho realizado
    - Comprovante (PDF/foto) - obrigatório
    - Horímetro/odômetro (se aplicável)
- Sistema calcula:

    ```
    total_price = actual_quantity × base_price        (valor para o associado)
    provider_payment = actual_quantity × provider_rate (valor para o prestador)
    ```

- Sistema **CRIA DÉBITO** no ledger do associado:

    ```php
    AssociateLedger::create([
        'type' => 'DEBIT',
        'category' => 'SERVICO',
        'amount' => total_price,  // Ex: R$ 1.200,00
        'description' => "Serviço executado - OS000123 - Hora Trator - 8 horas"
    ])
    ```

- Atualiza saldo do associado:
    ```
    saldo_anterior = R$ 5.000,00
    débito = R$ 1.200,00
    novo_saldo = R$ 3.800,00 (ele deve pagar)
    ```

**Status:**

- `status` = `completed`
- `associate_payment_status` = `pending` (aguardando pagamento)
- `provider_payment_status` = `pending` (aguardando pg do associado)

**Valores salvos:**

- `actual_quantity` = 8
- `unit_price` = R$ 150 (base_price)
- `total_price` = R$ 1.200 (associado deve pagar)
- `provider_payment` = R$ 400 (prestador receberá)

### 3. Associado Paga pelo Serviço

**Quem:** Admin/Financeiro  
**Onde:** Painel Filament → Ordens de Serviço → Marcar como Pago (Associado)

**O que acontece:**

- Admin confirma que associado pagou
- Sistema **CREDITA** no ledger do associado (removendo débito):

    ```php
    AssociateLedger::create([
        'type' => 'CREDIT',
        'category' => 'SERVICO',
        'amount' => total_price,  // R$ 1.200,00
        'description' => "Pagamento de serviço - OS000123"
    ])
    ```

- Atualiza saldo do associado:

    ```
    saldo_anterior = R$ 3.800,00
    crédito = R$ 1.200,00
    novo_saldo = R$ 5.000,00 (ele pagou)
    ```

- **Registra entrada no caixa da cooperativa:**
    ```php
    CashMovement::create([
        'type' => 'INCOME',
        'amount' => total_price,  // R$ 1.200,00
        'description' => "Pagamento OS000123 - Associado João"
    ])
    ```

**Status:**

- `associate_payment_status` = `paid`
- `associate_paid_at` = agora
- `provider_payment_status` = `pending` (ainda pode pagar)

### 4. Pagamento ao Prestador

**Quem:** Admin/Financeiro  
**Onde:** Painel Filament → Ordens de Serviço → Pagar Prestador

**Requisito:** Associado já deve ter pago!

**O que acontece:**

- Sistema **CREDITA** no ledger do prestador:

    ```php
    ServiceProviderLedger::create([
        'type' => 'CREDIT',
        'category' => 'SERVICO_PRESTADO',
        'amount' => provider_payment,  // R$ 400,00
        'description' => "Pagamento serviço - OS000123 - 8 horas"
    ])
    ```

- Atualiza saldo do prestador:

    ```
    saldo_anterior = R$ 2.000,00
    crédito = R$ 400,00
    novo_saldo = R$ 2.400,00
    ```

- **Registra saída do caixa da cooperativa:**

    ```php
    CashMovement::create([
        'type' => 'EXPENSE',
        'amount' => provider_payment,  // R$ 400,00
        'description' => "Pagamento prestador - OS000123 - João Tratorista"
    ])
    ```

- **Lucro da cooperativa permanece:**
    ```
    Entrada: R$ 1.200,00 (do associado)
    Saída: R$ 400,00 (para o prestador)
    Saldo líquido no caixa: R$ 800,00 (lucro da cooperativa)
    ```

**Status:**

- `provider_payment_status` = `paid`
- `provider_paid_at` = agora

## 📊 Resumo Financeiro

| Transação             | Valor         | Para/De          | Movimento                       |
| --------------------- | ------------- | ---------------- | ------------------------------- |
| Débito Associado      | R$ 1.200,00   | Associado deve   | -R$ 1.200 (saldo associado)     |
| Pagamento Associado   | R$ 1.200,00   | Associado paga   | +R$ 1.200 (caixa cooperativa)   |
| Crédito Associado     | R$ 1.200,00   | Remove débito    | +R$ 1.200 (saldo associado)     |
| Pagamento Prestador   | R$ 400,00     | Cooperativa paga | -R$ 400 (caixa cooperativa)     |
| Crédito Prestador     | R$ 400,00     | Prestador recebe | +R$ 400 (saldo prestador)       |
| **Lucro Cooperativa** | **R$ 800,00** | **Permanece**    | **+R$ 800 (caixa cooperativa)** |

## 🎯 Estados da Ordem

### Status Principal (`status`)

- `scheduled` - Agendada
- `in_progress` - Em execução
- `completed` - Concluída (prestador finalizou)
- `cancelled` - Cancelada
- `billed` - Faturada (legado)

### Status Pagamento Associado (`associate_payment_status`)

- `pending` - Aguardando pagamento do associado
- `paid` - Associado pagou
- `cancelled` - Cancelado

### Status Pagamento Prestador (`provider_payment_status`)

- `pending` - Aguardando pagamento
- `paid` - Prestador foi pago
- `cancelled` - Cancelado

## ✅ Checklist de Implementação

- [x] Adicionar campos `provider_hourly_rate` e `provider_daily_rate` em `services`
- [x] Adicionar campos de controle de pagamento em `service_orders`
- [x] Atualizar modelos com novos campos
- [x] Implementar débito automático ao concluir serviço
- [x] Separar cálculo: valor associado vs valor prestador
- [ ] Criar ações no Filament para marcar pagamentos
- [ ] Implementar pagamento em lote
- [ ] Criar relatórios de pagamentos
- [ ] Adicionar notificações de pagamento pendente
- [ ] Criar dashboard financeiro com lucros

## 🚀 Próximos Passos

1. **Filament Actions** - Botões no painel admin para:
    - Marcar associado como pago
    - Pagar prestador
    - Visualizar comprovantes

2. **Pagamento em Lote** - Selecionar múltiplas ordens e pagar de uma vez

3. **Relatórios** - Dashboards mostrando:
    - Total a receber de associados
    - Total a pagar a prestadores
    - Lucro acumulado da cooperativa
    - Fluxo de caixa mensal

4. **Notificações** - Alertas automáticos para:
    - Associados com pagamento pendente
    - Prestadores aguardando pagamento
    - Ordens concluídas sem pagamento

## 📝 Observações Importantes

1. **Associado sempre paga primeiro** - Prestador só recebe após associado pagar
2. **Valores diferentes** - Associado paga mais, prestador recebe menos, diferença = lucro
3. **Comprovante obrigatório** - Prestador deve enviar foto/PDF ao finalizar
4. **Quantidade real** - Pagamento baseado na quantidade efetivamente trabalhada
5. **Ledger duplo** - Débito/crédito no associado E crédito no prestador
6. **Caixa correto** - Entrada do associado e saída para prestador registradas

## 🔧 Configuração Necessária

Ao cadastrar um serviço no Filament, definir:

1. **Nome do Serviço**: Ex: "Hora de Trator"
2. **Unidade**: hora, diaria, km, etc.
3. **Valor Base** (`base_price`): R$ 150,00 ← cobrado do associado
4. **Valor Hora Prestador** (`provider_hourly_rate`): R$ 50,00 ← pago ao prestador
5. **Valor Diária Prestador** (`provider_daily_rate`): R$ 300,00 ← se unidade = diária

**Importante:** Se não definir `provider_*_rate`, considerar R$ 0 (prestador não recebe).
