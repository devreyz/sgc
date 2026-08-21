# Fundação Financeira e Documental - Progresso

## Escopo

Esta fase prepara o núcleo existente para o futuro Portal Contábil. Não cria o portal, workflow fiscal, nota fiscal ou uma segunda fonte financeira.

Documento base: `docs/AUDITORIA_ARQUITETURA_CONTABIL_PROJETOS.md`.

## 2026-08-19 - Caracterização inicial

### Estado real do banco local

- Banco local: MySQL, `@@default_storage_engine = MyISAM`.
- Todas as tabelas financeiras inspecionadas estão em MyISAM.
- `information_schema.referential_constraints`: 0 foreign keys efetivas.
- As migrations declaram FKs e transações, mas não fixam InnoDB para o núcleo.
- `phpunit.xml` usa SQLite em memória; essa suíte não prova locks ou concorrência MySQL/InnoDB.
- Não há configuração de produção versionada que permita afirmar qual engine está ativa no servidor de produção.

**Decisão:** MyISAM permanece bloqueador local para testes transacionais. Nenhuma conversão será executada nesta fase sem o plano exigido pelo gate.

### Dívida histórica observada

- 19 de 23 comprovantes de membro têm diferença entre `delivery_ids` e `associate_receipt_id`.
- Oito distribuições ativas pagas apontam para cobranças de cliente inexistentes.
- Dois comprovantes `paid` têm `amount_paid = 0` e nenhuma parcela suficiente.
- Cinco referências antigas de entrega aparecem duplicadas no extrato do membro.

Esses dados atravessaram versões anteriores. Serão detectados pelo auditor read-only; não serão corrigidos automaticamente.

### Invariantes atuais que funcionam

- Distribuição nasce de entrega-pai e recebe cliente, preço, bruto, taxa e líquido.
- O controller de distribuição revalida saldo físico dentro de transação e usa `lockForUpdate`.
- Não foram encontrados filhos ativos sem pai, cross-tenant ou acima da quantidade recebida.
- Não foram encontradas distribuições ativas aprovadas sem cliente, preço ou quantidade válida.

### Uso de `admin_fee_percentage`

| Arquivo/componente | Classificação | Uso e impacto | Substituição segura |
|---|---|---|---|
| `SalesProject` e migration inicial | configuração legada | percentual padrão histórico | manter coluna; ocultar como configuração principal após migração |
| `ProjectFinancialCalculator` | cálculo operacional ativo | soma percentual legado às taxas modernas | corrigir: fallback legado somente sem `project_fees` |
| `CustomerBillingReceiptService` via `calculateWithFees` | cálculo operacional ativo incorreto | taxa legada contamina cobrança quando existe taxa moderna de cliente | corrigir com teste de regressão |
| `ProductionDelivery::saving` | persistência operacional | grava taxa efetiva devolvida pelo calculador | manter como snapshot de compatibilidade |
| `DeliveryRegistrationController` | recepção/apresentação | grava percentual legado em entrega-pai com valor zero e expõe metadados | remover progressivamente das decisões financeiras |
| `DistributionBillingService` | fluxo legado ativo | persiste percentual efetivo no lote/distribuição | tornar read-only futuramente |
| `FinancialDistributionService` | legado | calcula diretamente pelo percentual em fluxo antigo | não usar para distribuição de projetos; preservar outros módulos |
| `PricingService::calculateDeliveryValues` | helper legado | cálculo percentual direto | não usar como autoridade financeira de projeto |
| `ReceiptFeeColumnService` | PDF/relatório | adiciona coluna legada mesmo quando há taxas modernas | alinhar à precedência do calculador |
| PDFs e `ReceiptConsentRenderer` | apresentação/histórico | exibe percentual legado | manter compatibilidade; preferir snapshot/taxas modernas |
| Filament de projeto/entrega | configuração/UI legada | ainda incentiva edição do percentual | mapear para retirada gradual, sem redesenho nesta fase |
| testes antigos | caracterização | alguns esperam legado + taxas modernas | atualizar apenas quando representam regra antiga, mantendo casos históricos explícitos |

### Fluxos legados ainda graváveis

| Fluxo | Ainda escreve? | Chamadores | Substituto atual | Candidato a read-only |
|---|---|---|---|---|
| `DistributionBillingService` | sim | `DistributionBillingResource` e ação em `ProductionDeliveryResource` | `AssociateReceipt` + pagamentos | sim, após compatibilidade e reconciliação |
| `ProjectPayment` | sim, via Resources/páginas legadas | painel de projeto | pagamentos de comprovantes | sim, após mapear todos os chamadores |
| `FinancialDistributionService::processDelivery` | não no observer de entrega; service ainda existe | sem chamador de entrega localizado | `AssociateReceiptService` | sim para entregas; manter compras/serviços separados |
| pagamento direto em `ProductionDeliveryResource` | sim | ações individuais/em massa | pagamento de `AssociateReceipt` | sim, após bloquear sem quebrar histórico |

### Riscos atuais reproduzíveis em código

- Taxa legada é somada a `project_fees` modernos.
- Taxa legada entra no cálculo de cliente quando há `customer_project_fees`.
- Cobrança do cliente pode bloquear comprovante do membro por `billing_receipt_id`/`billing_status`.
- `CustomerBillingReceiptService` não valida o mesmo conjunto de invariantes do lado do membro.
- Payments leem saldo sem travar comprovante, último extrato e conta.
- Exclusões liberam vínculos a partir de JSON em alguns caminhos.
- Models/policies dos dois comprovantes não aplicam ownership tenant-aware de forma consistente.

### Testes de base já executados

- 32 testes passaram, 174 asserções.
- Cobertura existente confirma verdade financeira por distribuição, PDFs, relatórios, numeração e recibo financeiro genérico.
- Não há teste atual do `CustomerBillingReceiptService`, concorrência real ou coexistência simétrica dos dois lados.

## Gate de banco pendente

Antes de qualquer MyISAM -> InnoDB será necessário apresentar: tabelas e tamanhos, ordem de conversão, janela de manutenção, inconsistências impeditivas, FKs a recriar, backup restaurável e rollback. Nenhuma alteração de engine foi aplicada.

## 2026-08-19 - Correcoes verificadas

### Motor de taxas

- Criados os casos A-J em `ProjectFinancialCalculatorTest`.
- `project_fees` ativos agora substituem o fallback `admin_fee_percentage`; nao sao mais somados a ele.
- Sem `project_fees`, a taxa legada continua funcionando como fallback para projetos antigos.
- `customer_project_fees` sao totalmente independentes: sem taxas do cliente, bruto = liquido; com taxas, somente elas sao aplicadas.
- `ReceiptFeeColumnService` segue a mesma precedencia para nao exibir uma coluna legada que nao participou do calculo.
- Snapshots historicos nao foram recalculados nem alterados.

### Simetria dos documentos

- Adicionado `FinancialDistributionInvariantService` para validar tenant, projeto, status aprovado, entrega-pai, membro, produto, cliente, quantidade e preco em lote.
- `AssociateReceiptService` deixou de bloquear uma distribuicao apenas por ela estar em cobranca de cliente.
- `CustomerBillingReceiptService` bloqueia qualquer segunda cobranca no mesmo lado e nao considera `associate_receipt_id` um conflito.
- Os snapshots agora sao calculados depois do `lockForUpdate`, usando exatamente as linhas reconsultadas e validadas.
- A FK na distribuicao e a fonte do vinculo corrente; `delivery_ids` e atualizado como snapshot/compatibilidade.
- Testes provam as duas ordens de criacao, duplicidade em cada lado e tentativa cross-tenant.

### Tenant safety

- `AssociateReceipt`, `CustomerBillingReceipt`, `AssociateReceiptPayment` e `CustomerReceiptPayment` passaram a usar `BelongsToTenant`.
- Policies dos dois comprovantes exigem tenant da sessao e permissao; exclusao so e admitida para rascunho vazio. Exclusao em massa, force delete e restore financeiro foram desabilitados.
- Services fazem validacao explicita de tenant, inclusive quando usam `withoutGlobalScopes` para jobs e locks controlados.
- `AssociateLedger` e `CashMovement` agora aceitam o `tenant_id` ja validado pelo service; antes o valor era descartado pelo mass assignment e dependia da reinjecao pelo usuario autenticado.

### Pagamentos e recebimentos

- O metodo legado `payReceipt` do membro delega ao fluxo de parcelas moderno.
- Comprovante, parcelas, ultimo saldo do extrato e conta bancaria sao travados dentro de uma unica transacao.
- `amount_paid` e recalculado da soma das parcelas persistidas, nao do valor possivelmente obsoleto no model recebido.
- Conta/caixa e validada explicitamente pelo tenant; uma falha intermediaria reverte parcela, extrato e comprovante.
- Extrato e caixa referenciam a parcela individual, permitindo distinguir pagamentos parciais.
- Quitacao do membro atualiza distribuicoes pela FK `associate_receipt_id`, mesmo quando o JSON diverge.
- O fluxo moderno nao grava mais `billing_status = paid` ao liquidar os documentos; os estados especificos sao a autoridade.
- Repeticao com o mesmo `document_number` no mesmo documento e recusada. Sem uma chave de idempotencia obrigatoria, retries identicos sem numero ainda nao podem ser diferenciados de duas parcelas legitimas.

### Auditor somente leitura

- Criado `finance:audit-integrity` com filtros `--tenant`, `--project` e saida `--json`.
- O comando verifica snapshot x FK, ponteiros inexistentes, cross-tenant/projeto, pais invalidos, cliente/preco/quantidade, pago sem parcelas, total de parcelas divergente, bruto congelado divergente, referencias duplicadas e `billing_status` legado incoerente.
- Teste automatizado confirma que a auditoria encontra divergencias e nao altera registros.
- Execucao local atual: 38 criticos, 32 alertas, 0 informativos. Os achados nao foram reparados automaticamente.

### Testes executados

- Suite final completa: 177 testes, 722 assercoes, todos aprovados.
- Nesse total, os testes adicionais de pagamentos cobrem 3 cenarios e 14 assercoes.
- Nesse total, o teste do auditor cobre 1 cenario e 5 assercoes.
- Concorrencia real permanece nao comprovada porque `phpunit.xml` usa SQLite e o banco MySQL local esta em MyISAM.

## Pendencias e bloqueadores

- Confirmar engine e constraints do banco de producao.
- Planejar conversao controlada para InnoDB caso producao tambem esteja em MyISAM.
- Adicionar uma chave de idempotencia persistida e unica para pagamentos/recebimentos; `document_number` e apenas protecao parcial.
- Executar testes concorrentes em MySQL/InnoDB para duas cobrancas, dois comprovantes e dois pagamentos simultaneos.
- Definir reparo humano para os 38 achados criticos locais antes de qualquer ferramenta de correcao.
- Congelar identidade e apresentacao documental em snapshot minimo requer proposta separada; nenhuma nova tabela foi criada.

## Gate atual

**NO-GO para a Fase 2.** O calculo e os fluxos atuais ficaram mais consistentes, mas atomicidade real, idempotencia obrigatoria e divida historica ainda dependem das decisoes acima.

## FASE 1.5 - CONCORRENCIA, IDEMPOTENCIA E LEGADO

### 1. Engine e constraints

- Producao foi confirmada pelo responsavel com 102 tabelas em InnoDB. Nao existe plano de conversao MyISAM para producao.
- InnoDB nao confirma FKs. Foi criado o comando read-only `finance:audit-schema`, que consulta engine, FKs, indices, uniques e `ON DELETE` no `information_schema`.
- A matriz esperada e o resultado local estao em `docs/accounting-constraints-matrix.md`.
- O banco local antigo permanece MyISAM, com zero FKs reais. Isso e uma caracteristica somente do ambiente local.
- As migrations nao declaram FK para `production_deliveries.associate_receipt_id` nem `production_deliveries.distribution_billing_id`. Nenhuma FK foi adicionada nesta fase.
- A matriz real de producao permanece pendente ate executar `php artisan finance:audit-schema --json` no servidor.

### 2. Idempotencia monetaria

- `document_number` deixou de ser tratado como chave tecnica; continua apenas como referencia externa e regra auxiliar contra documento repetido.
- A migration `2026_08_19_000001_add_operation_keys_to_receipt_payments` adiciona `operation_key` UUID nullable aos dois tipos de parcela.
- A chave e obrigatoria para toda nova operacao iniciada pela aplicacao, pertence ao tenant e possui UNIQUE `(tenant_id, operation_key)` em cada lado.
- O UUID e gerado em campo oculto nos tres formularios Filament que iniciam pagamento/recebimento e segue no mesmo POST em caso de retry.
- O service procura a operacao dentro da transacao antes de validar o status atual do documento. Assim, um retry depois da quitacao retorna sucesso idempotente.
- Mesma chave + mesmo tenant + mesmo documento + mesmo valor nao cria efeitos novos. Reuso com outro valor/documento e recusado. O mesmo UUID pode existir em tenants diferentes sem cruzar dados.
- Parcela, `amount_paid`, extrato, movimento de caixa e saldo da conta continuam na mesma transacao.

Justificativa da migration: nenhum campo existente sobrevive a retries de forma tecnica e nao ambigua. `document_number` e nullable/humano, e referencias polimorficas so existem depois da criacao. A unique no banco e necessaria como ultima barreira contra corrida.

### 3. Locking, deadlocks e rollback

Ordem adotada:

1. comprovante/cobranca;
2. parcelas existentes;
3. ledger do membro quando aplicavel;
4. distribuicoes em ordem crescente de ID;
5. conta bancaria.

Os congelamentos agora travam primeiro o documento e depois as distribuicoes ordenadas. Transacoes de congelamento, substituicao, pagamento e sequencia usam ate cinco tentativas para deadlocks transitórios.

Testes SQLite de injecao de falha comprovaram rollback para:

- Payment criado e falha antes do Ledger;
- Payment + Ledger e falha antes do CashMovement;
- snapshot de comprovante criado e falha no vinculo da distribuicao;
- snapshot de cobranca criado e falha no vinculo da distribuicao.

Nenhum caso deixou parcela, ledger, caixa, total ou FK parcial.

### 4. MySQL/InnoDB

- Criado `phpunit.mysql.xml.example` para um banco dedicado como `sgc_testing`.
- Criada a suite `MySqlFinancialConcurrencyTest`, marcada `mysql-financial` e recusando qualquer database cujo nome nao contenha `test`.
- A suite verifica UNIQUE concorrente da chave de operacao, serializacao por `FOR UPDATE`, coexistencia dos dois lados na mesma distribuicao e sequencia numerica travada.
- No SQLite ela e ignorada explicitamente. No MySQL local atual ela nao pode ser usada como prova porque as tabelas da aplicacao sao MyISAM.
- Para executar: copiar `phpunit.mysql.xml.example` para `phpunit.mysql.xml`, ajustar credenciais de um banco descartavel InnoDB e executar `php artisan test --configuration phpunit.mysql.xml`.

Resultado nesta maquina: suite preparada, mas os tres testes MySQL foram ignorados. Portanto concorrencia de producao ainda nao esta comprovada.

### 5. Auditor agregado e classificacao historica

`finance:audit-integrity` agora retorna tambem codigo, gravidade, quantidade, tenants, projetos, classificacao e tratamento. A execucao local atual encontrou:

| Codigo | Gravidade | Quantidade | Classificacao | Tratamento |
|---|---:|---:|---|---|
| `missing_customer_billing_receipt` | critical | 8 | B/E/F | divida historica sensivel; revisao humana |
| `orphan_distribution` | critical | 28 | A/B/F | fluxo atual bloqueia novos casos; historico exige revisao |
| `paid_without_sufficient_payments` | critical | 2 | B/E/F | reconciliar parcelas, documento e caixa |
| `duplicate_reference` | warning | 6 | B/E/F | investigar antes de estorno/consolidacao |
| `legacy_billing_status_divergence` | warning | 8 | B/C/F | comparar com documentos modernos |
| `snapshot_fk_mismatch` | warning | 18 | B/C/E | FK e verdade corrente; preservar snapshot |

Legenda: A bug reproduzivel/ja bloqueado no fluxo atual; B divida historica; C referencia legada valida; D reparo automatico deterministico; E reparo deterministico financeiramente sensivel; F revisao humana obrigatoria.

Nenhum registro financeiro foi reparado. Um futuro `finance:repair-integrity --dry-run` deve ser separado, logado e aprovado por tipo de problema.

### 6. Fluxos legados ainda gravaveis

| Arquivo/acao | Quem utiliza | Acessivel | Substituto moderno | Risco ao desativar |
|---|---|---|---|---|
| `DistributionBillingResource/Pages/CreateDistributionBilling.php` -> `DistributionBillingService::billDistributions` | gestor com permissao do resource | sim, rota create | comprovante do membro + parcelas | historico continua legivel; confirmar se alguem ainda cria lotes |
| `ProductionDeliveryResource` bulk `bill_distributions` | gestor de entregas | sim | comprovante do membro | pode interromper rotina antiga de faturamento |
| `ProductionDeliveryResource` bulk `pay_associates` | gestor de entregas | sim | `AssociateReceiptService::addPayment` | alto: acao atual marca `paid/billing_status` sem parcelas modernas |
| `ProductionDeliveryResource` acao `pay_single` | ninguem | nao, `visible(false)` | parcelas do comprovante | baixo |
| `project_payments` | nenhum chamador de escrita localizado em `app` | nao localizado | parcelas dos dois comprovantes | manter consulta historica; confirmar integracoes externas |
| `FinancialDistributionService` | observers de compra/servico, nao entrega de projeto | parcialmente | nao substituir fora de entregas | alto se desativado globalmente |

Proposta, ainda nao aplicada: colocar criacao de `distribution_billings` e as duas bulk actions em feature flag inicialmente desligada para novos tenants, depois modo read-only global quando o uso real estiver confirmado. Historico e tabelas devem permanecer.

### 7. `admin_fee_percentage`

- O calculador moderno usa `project_fees`; `admin_fee_percentage` so entra como fallback quando nao ha taxa moderna.
- O campo ainda e editavel em `SalesProjectResource` e, para fluxo standalone legado, em `ProductionDeliveryResource`.
- Metadados e alguns documentos ainda o leem para compatibilidade.
- Proposta: ocultar da configuracao normal de novos projetos e manter visualizacao/fallback para projetos historicos. A coluna nao deve ser removida nem zerada.

### 8. Mudancas aplicadas

- chave UUID tenant-scoped e uniques de idempotencia;
- forms Filament enviando a chave tecnica;
- retries idempotentes depois de quitacao;
- locks ordenados e retry de deadlock;
- auditor agregado e classificacao;
- auditor read-only de schema real;
- testes adicionais de tenant, rollback e idempotencia;
- suite MySQL/InnoDB dedicada e protegida.

### 9. Riscos restantes e gate

Checklist:

- [x] producao confirmada em InnoDB;
- [ ] constraints reais de producao exportadas e revisadas;
- [ ] migration de idempotencia aplicada e confirmada em producao;
- [ ] testes transacionais/concorrentes executados em MySQL/InnoDB;
- [x] mesma distribuicao bloqueada em dois documentos do mesmo lado pelo service;
- [x] comprovante do membro e cobranca do cliente coexistem;
- [x] idempotencia nao depende de `document_number`;
- [x] rollback comprovado por injecao de falha;
- [x] isolamento tenant coberto;
- [x] inconsistencias agregadas e classificadas;
- [x] caminhos legados gravaveis identificados;
- [ ] caminhos legados perigosos colocados em modo somente leitura;
- [x] suite completa final executada depois de todas as mudancas da Fase 1.5.

Resultado final local: **182 testes aprovados, 743 assercoes e 3 testes MySQL/InnoDB ignorados de forma explicita** no runner SQLite. Nao houve falhas.

**Decisao atual: NO-GO para a Fase 2.** Restam evidencias externas indispensaveis: matriz real de constraints de producao, aplicacao da migration e execucao da suite concorrente em MySQL/InnoDB. O Portal Contabil e o workflow fiscal nao foram iniciados.
