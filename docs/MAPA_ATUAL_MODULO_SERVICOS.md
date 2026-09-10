# Mapa atual do módulo de Serviços e Integrações

> Levantamento estático do repositório em 09/09/2026. O banco MySQL local não estava acessível; portanto, cardinalidades e nulabilidade abaixo representam o schema pretendido pelas migrations, não uma inspeção dos dados reais.

## 1. Visão em uma página

```text
User (login global)
  └─ TenantUser (vínculo de acesso ao tenant, papéis e credencial local)
       ├─ Associate ───────┐
       └─ ServiceProvider ─┼─ ambos ainda localizados por tenant_id + user_id
                           │
Service ──< ServiceProviderService >── ServiceProvider
   │                                      │
   └────────────── ServiceOrder ──────────┤
                         │                ├─< ProviderPaymentRequest
                         ├─< ServiceOrderAddition ──> Expense
                         ├─< ServiceOrderPayment ───> CashMovement (por ação, sem FK direta)
                         ├─< ServiceProviderWork (legado ativo)
                         ├─< AssociateLedger (via observer somente em status billed)
                         └─< ServiceProviderLedger (fluxos divergentes)

ServiceOrder / ServiceOrderPayment / ProviderPaymentRequest
  └─ TenantStoredFileObserver ── job ── CloudDocument / Google Drive

Document (polimórfico) e GeneratedDocument/DocumentTemplate existem,
mas ServiceOrder usa receipt_path e PDF sob demanda, sem integração canônica.
```

## 2. Entidades e responsabilidades reais

| Entidade | Responsabilidade atual | Cardinalidades principais | Situação |
|---|---|---|---|
| `User` | Identidade de autenticação global | N:N com tenants por `tenant_user`; 1:N opcional com associados/prestadores | Adequada para acesso, inadequada como identidade operacional obrigatória |
| `TenantUser` | Membership, papéis, estado e credencial no tenant | N:1 `User`; N:1 `Tenant`; unicidade `(tenant_id,user_id)` | Mistura vínculo de acesso com representação de membro |
| `Associate` | Cadastro operacional de associado | N:1 opcional `User`; 1:N ordens/razão/documentos | Dependência funcional de `User` permanece em telas, filtros e identidade |
| `ServiceProvider` | Cadastro operacional e bancário do prestador | N:1 opcional `User`; N:N serviços; 1:N ordens/trabalhos/razão | Cadastro sem login é possível no schema e no admin, mas o portal reacopla identidade e papel |
| `Service` | Catálogo básico e preços atuais | 1:N ordens; N:N prestadores | Sem versão, requisitos, campos configuráveis ou composição declarativa |
| `ServiceProviderService` | Habilitação e tarifa específica | N:1 serviço/prestador; único por par | Duplica tarifas existentes em `Service` e no próprio prestador |
| `ServiceOrder` | Pedido, execução, preço, obrigação e estados de pagamento | N:1 serviço; N:1 prestador; N:1 associado opcional; 1:N pagamentos/acréscimos/trabalhos | Registro central sobrecarregado; verdade operacional de fato, mas não congelada |
| `ServiceOrderAddition` | Despesa, taxa ou desconto lançado no encerramento | N:1 ordem; despesa pode apontar para `Expense` | Embrião útil de composição, sem versão e sem idempotência |
| `ServiceOrderPayment` | Parcela prevista, aviso de pagamento e pagamento liquidado | N:1 ordem | Três conceitos em uma tabela; sem chave de operação, reversão ou vínculo direto ao caixa |
| `ProviderPaymentRequest` | Solicitação de saque/pagamento do prestador | N:1 ordem/prestador | Sem barreira única para solicitação pendente ou controle de saldo concorrente |
| `ServiceProviderWork` | Execução avulsa/legada do prestador | N:1 prestador; N:1 ordem e associado opcionais | Legado ativo no admin; sobrepõe `ServiceOrder` |
| `AssociateLedger` | Razão com saldo do associado | Referência polimórfica | Integração de serviços é parcial e dependente de um estado pouco usado |
| `ServiceProviderLedger` | Razão com saldo do prestador | Referência polimórfica | Sinais contábeis contraditórios entre fluxos novo e legado |
| `Expense` | Despesa genérica, inclusive originada em serviço | Referência polimórfica | Reutilizável; o lançamento pelo serviço não compõe automaticamente o preço do cliente |
| `CashMovement` | Efeito financeiro em conta | Referência polimórfica | Reutilizável, mas ações de serviço não têm idempotência nem reversão vinculada |
| `Document` | Anexo polimórfico canônico em outros domínios | N:1 proprietário polimórfico | Não adotado por ordens/pagamentos de serviço |
| `GeneratedDocument` / `DocumentTemplate` | Documento gerado e template versionável | Polimórfico/template | Reutilizável, mas a OS em PDF é gerada sob demanda e não persistida |
| `CloudDocument` | Estado de sincronização no armazenamento remoto | N:1 proprietário polimórfico | Registro de sincronização, não fonte canônica do anexo |

## 3. Fluxos encontrados

### 3.1 Portal do prestador

1. Middleware resolve o tenant pelo slug e grava `tenant_id` na sessão.
2. Middleware de papéis aceita administrador global, qualquer papel global solicitado ou papel no tenant.
3. O controller procura o prestador por `(tenant_id,user_id)`; se não encontra e o usuário tem papel de prestador, cria o cadastro automaticamente.
4. O prestador cria uma ordem, inicia, finaliza, informa medição e adicionais.
5. Na finalização, o preço é recalculado com o catálogo vigente naquele instante.
6. O prestador pode registrar pagamento do cliente e solicitar seu próprio pagamento.

Pontos de controle bons: consultas de detalhe/ação de uma OS incluem tenant e prestador autenticado. Pontos frágeis: membership não é validado pelo middleware do slug, IDs aninhados não são todos tenant-scoped, criação automática em leitura, upload público e ausência de locks/idempotência.

### 3.2 Workspace administrativo

O Filament expõe recursos para catálogo, prestadores, habilitações, ordens, pagamentos e relatório. As ações da própria OS criam parcelas, recebem do cliente, faturam pagamento, pagam prestador e concluem. Há ainda uma rota de criação direta de `ServiceOrderPayment`, com efeitos financeiros diferentes das ações da OS. Isso produz mais de uma semântica para o mesmo evento.

### 3.3 Legado ativo

`ServiceProviderWork` continua acessível no gerenciador de relação do prestador e alimenta o razão do prestador. Views antigas do portal para trabalhos/formulários permanecem no repositório, mas não foram encontradas nas rotas atuais; são candidatas a não usadas, não código morto comprovado.

## 4. Fronteiras de verdade atuais

| Pergunta | Fonte atual | Qualidade |
|---|---|---|
| O que foi solicitado/executado? | `service_orders` | Parcial: pedido e execução se misturam; valores podem depender do catálogo mutável |
| Quanto o cliente deve? | `service_orders.final_price` | Parcial: valor calculado, sem obrigação financeira imutável e versionada |
| Quanto o prestador deve receber? | `service_orders.provider_payment` | Parcial: não nasce como obrigação; pagamentos/saques podem divergir |
| Quanto foi pago? | Soma de `service_order_payments` | Inconsistente: alguns cálculos filtram `BILLED`, outros somam tipos/status distintos |
| Qual o efeito no caixa? | `cash_movements` criados por algumas ações | Parcial: nem todo caminho cria movimento; não há link/reversão/idempotência uniforme |
| Qual o saldo em razão? | `associate_ledgers` / `service_provider_ledgers` | Não confiável como verdade única do serviço; cobertura e sinais divergem |
| Qual documento prova o fato? | `receipt_path`, PDFs sob demanda e sync remoto | Fragmentado e com risco de acesso público |

## 5. Classificação rápida

- **CANÔNICO/ATIVO:** `Service`, `ServiceProvider`, `ServiceOrder`, recursos Filament e portal atual.
- **ATIVO, MAS CONFLITANTE:** `ServiceOrderPayment`, razões de associado/prestador e seus múltiplos caminhos de escrita.
- **LEGADO ATIVO:** `ServiceProviderWork` e seu pagamento no gerenciador do prestador.
- **PARALELO REUTILIZÁVEL:** `Expense`, `CashMovement`, `Document`, `GeneratedDocument`, templates e armazenamento remoto.
- **CANDIDATO A NÃO USADO:** views antigas `provider/works`, `provider/work-form` e `provider/edit-order`; exigir telemetria e busca em produção antes de retirar.

## 6. Limites desta fotografia

- O MySQL configurado em `127.0.0.1:3306` não respondeu durante a auditoria; `migrate:status` não pôde ser executado.
- Não foi possível medir volume, nulabilidade real, órfãos, duplicidades, distribuição de status ou uso de rotas em produção.
- As migrations não fixam explicitamente o engine nas tabelas centrais; InnoDB é uma expectativa do driver/configuração MySQL, não uma constatação desta auditoria.
- Não foram feitas alterações de schema, dados ou código de produção.
