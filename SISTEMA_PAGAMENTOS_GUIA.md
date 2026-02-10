# Sistema de Pagamentos de Ordens de Serviço - Guia de Uso

## 📊 Visão Geral

Sistema completo para gerenciar pagamentos de ordens de serviço com fluxo dual:

- **Associados** pagam o valor cheio do serviço
- **Prestadores** recebem valor menor (taxa predefinida)
- **Cooperativa** retém a diferença como lucro

---

## 🎯 Funcionalidades Implementadas

### 1. **Dashboard de Pagamentos** (Widget)

No painel principal do admin (`/admin`), você verá:

- 📈 **A Receber dos Associados**: Total pendente de recebimento
- 📉 **A Pagar aos Prestadores**: Total a ser pago aos prestadores
- 💰 **Lucro Acumulado**: Lucro já realizado + potencial

**Atualização**: Automática a cada 30 segundos

---

### 2. **Ações Individuais nas Ordens de Serviço**

No recurso **Ordens de Serviço** (`/admin/service-orders`):

#### 🟢 Marcar Pagamento do Associado

**Quando usar**: Quando o associado efetuar o pagamento

**Como usar**:

1. Localize a ordem com status "Concluída" e "Pgto Associado: Pendente"
2. Clique no botão **"Marcar Pago"** (ícone de check verde)
3. Preencha:
    - Data do pagamento
    - Referência (opcional): ID da transação, comprovante, etc.
    - Observações (opcional)
4. Confirme

**O que acontece**:

- ✅ Status do pagamento do associado atualizado para "Pago"
- ✅ Lançamento no ledger do associado marcado como pago
- ✅ Entrada de caixa registrada automaticamente
- ✅ Habilitada a opção de pagar o prestador

---

#### 🟡 Pagar Prestador

**Quando usar**: Após o associado pagar (status "Pago")

**Como usar**:

1. Localize a ordem com "Pgto Associado: Pago" e "Pgto Prestador: Pendente"
2. Clique no botão **"Pagar Prestador"** (ícone de notas amarelo)
3. Preencha:
    - Data do pagamento
    - Método de pagamento (PIX, Transferência, etc.)
    - Referência (opcional)
    - Observações (opcional)
4. Confirme

**O que acontece**:

- ✅ Status do pagamento do prestador atualizado para "Pago"
- ✅ Trabalho do prestador marcado como pago
- ✅ Saída de caixa registrada automaticamente
- ⚠️ **IMPORTANTE**: Só pode pagar prestador se o associado já pagou!

---

### 3. **Pagamento em Lote**

No recurso **Ordens de Serviço**, você pode pagar múltiplos prestadores de uma vez:

**Como usar**:

1. Filtre as ordens:
    - Status: Concluída
    - Pgto Associado: Pago
    - Pgto Prestador: Pendente
2. Selecione as ordens desejadas (checkbox)
3. No menu de ações em lote, clique **"Pagar Prestadores em Lote"**
4. Confirme o total e preencha:
    - Data do pagamento (única para todos)
    - Método de pagamento
    - Observações (aplicado a todos)
5. Confirme

**O que acontece**:

- ✅ Todos os prestadores selecionados são pagos de uma vez
- ✅ Saídas de caixa individuais criadas para cada um
- ✅ Notificação com resumo: quantidade e total pago

---

### 4. **Relatório de Pagamentos**

Acesse via menu: **Serviços → Relatório de Pagamentos** (`/admin/service-orders-payment-report`)

#### 📊 Cards de Resumo no Topo:

- Total de ordens concluídas
- Total a receber
- Total a pagar
- Lucro total realizado

#### 📋 Tabela Detalhada:

**Colunas**:

- Número da OS
- Data de execução
- Associado
- Serviço
- Valor Associado (com total)
- Pagamento Prestador (com total)
- Lucro Cooperativa (com total)
- Status Pgto Associado
- Status Pgto Prestador
- Datas de pagamento (opcional)

**Filtros disponíveis**:

- Status de pagamento do associado
- Status de pagamento do prestador
- Período de execução (de/até)
- Associado específico
- Serviço específico

**Recursos**:

- ✅ Totalização automática de valores
- ✅ Ordenação por qualquer coluna
- ✅ Busca por número da OS
- ✅ Atualização a cada 30 segundos
- ✅ Exportação de dados

---

## 🔍 Filtros na Listagem Principal

Na listagem de **Ordens de Serviço**, novos filtros foram adicionados:

### Filtros de Pagamento:

- **Pgto Associado**:
    - Pendente
    - Pago
    - Cancelado

- **Pgto Prestador**:
    - Pendente
    - Pago
    - Cancelado

**Exemplo de uso**:

- Ver todas as ordens onde associado não pagou: Filtrar "Pgto Associado = Pendente"
- Ver prestadores aguardando pagamento: "Pgto Associado = Pago" + "Pgto Prestador = Pendente"

---

## 🎨 Indicadores Visuais

### Badges de Status:

- 🟡 **Pendente** - Amarelo
- 🟢 **Pago** - Verde
- 🔴 **Cancelado** - Vermelho

### Colunas da Tabela:

- **Valor Associado**: Valor que o associado deve pagar
- **Pagto Prestador**: Valor que será pago ao prestador (oculto por padrão - toggle para exibir)
- **Pgto Associado**: Badge com status e data no tooltip
- **Pgto Prestador**: Badge com status e data no tooltip

---

## 📱 Fluxo Completo

```
1. Prestador executa serviço
   ↓
2. Sistema calcula automaticamente:
   - Valor para associado (quantidade × preço base)
   - Valor para prestador (quantidade × taxa prestador)
   - Lucro cooperativa (diferença)
   ↓
3. Cria débito automático no ledger do associado
   ↓
4. Admin marca associado como pago
   ↓
5. Entrada de caixa registrada
   ↓
6. Admin paga prestador (individual ou lote)
   ↓
7. Saída de caixa registrada
   ↓
8. Lucro da cooperativa realizado
```

---

## ⚙️ Configuração de Valores

### Definir Taxa do Prestador:

1. Acesse **Serviços** no admin
2. Edite o serviço desejado
3. Defina:
    - **Valor por Hora** (preço base que o associado paga)
    - **Taxa Prestador - Hora** (valor que o prestador recebe)
    - **Valor por Diária** (se aplicável)
    - **Taxa Prestador - Diária** (se aplicável)

**Exemplo**:

- Serviço: Trator com implemento
- Valor por Hora: R$ 150,00 (associado paga)
- Taxa Prestador - Hora: R$ 50,00 (prestador recebe)
- Lucro: R$ 100,00 por hora (cooperativa)

---

## 📊 Relatórios e Dashboards

### Widget do Dashboard:

- Atualização em tempo real
- Gráficos de tendência
- Visão rápida dos valores

### Página de Relatórios:

- Análise detalhada
- Filtros avançados
- Totalizações por período
- Exportação para Excel/PDF

---

## 🔐 Permissões

As seguintes permissões são necessárias:

- **Ver ordens de serviço**: Visualizar listagem
- **Editar ordens de serviço**: Marcar pagamentos
- **Ver relatórios**: Acessar página de relatórios
- **Gerenciar pagamentos**: Executar ações de pagamento

---

## 💡 Dicas

1. **Filtre antes de selecionar lote**: Use os filtros para mostrar apenas ordens elegíveis para pagamento
2. **Use a referência de pagamento**: Facilita rastreamento e auditoria
3. **Confira o relatório regularmente**: Acompanhe valores pendentes
4. **Widget do dashboard**: Visualização rápida sem entrar em relatórios
5. **Exportar dados**: Use para prestação de contas e análises externas

---

## 🐛 Solução de Problemas

### Botão "Marcar Pago" não aparece:

- ✅ Verifique se o status da ordem é "Concluída"
- ✅ Verifique se o status de pagamento do associado é "Pendente"

### Botão "Pagar Prestador" não aparece:

- ✅ Associado precisa ter pago primeiro
- ✅ Status de pagamento do associado deve ser "Pago"
- ✅ Valor do prestador deve ser maior que zero

### Pagamento em lote não funciona:

- ✅ Todas as ordens selecionadas devem atender aos critérios
- ✅ Use os filtros para garantir elegibilidade

---

## 📞 Suporte

Para dúvidas ou problemas:

1. Verifique este guia primeiro
2. Consulte a documentação técnica (FLUXO_FINANCEIRO_SERVICOS.md)
3. Entre em contato com o administrador do sistema
