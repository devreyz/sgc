# Auditoria arquitetural profunda — módulo de Serviços e Integrações

**Data da análise:** 09/09/2026  
**Escopo:** código, migrations, models, services, observers, jobs, policies, middleware, rotas, recursos Filament, controllers, views, documentação e testes do repositório.  
**Natureza:** auditoria estática e somente leitura. Nenhuma arquitetura, migration, refatoração ou correção de dados foi implementada.

## 1. Resumo executivo

O módulo é funcional, mas não possui uma fronteira única e confiável entre **pedido**, **execução**, **obrigação**, **pagamento**, **caixa** e **razão**. `ServiceOrder` acumulou quase todas essas responsabilidades, enquanto `ServiceProviderWork`, `ServiceOrderPayment`, os dois ledgers e ações administrativas alternativas mantêm interpretações concorrentes do mesmo fato.

Os riscos mais urgentes não são de estética arquitetural. São de isolamento multi-tenant e integridade financeira:

1. **CRÍTICO — travessia de tenant no portal:** o middleware do slug troca o tenant da sessão sem validar membership; o middleware de papel aceita papéis globais; o controller pode então criar automaticamente um prestador no tenant acessado.
2. **CRÍTICO — IDOR em pagamento aninhado:** a ação administrativa de faturar seleciona `ServiceOrderPayment` pelo ID bruto, sem garantir que pertença à OS, tenant, tipo e estado esperados.
3. **CRÍTICO — anexos sensíveis públicos:** recibos são gravados em disco público e uma rota genérica serve qualquer path sem autorização por usuário/tenant/proprietário.
4. **ALTO — ausência de verdade financeira única:** caminhos diferentes criam combinações diferentes de pagamento, caixa, ledger e status. Um deles soma inclusive pagamentos do cliente e do prestador.
5. **ALTO — concorrência/idempotência:** conclusão, recebimento, pagamento ao prestador, parcelamento, pedido de saque e geração do número da OS não possuem a proteção já usada por módulos financeiros mais novos.
6. **ALTO — valores históricos mutáveis por catálogo:** o preço é calculado na conclusão com tarifas atuais, e não a partir de uma versão/snapshot aceito na solicitação ou execução.
7. **ALTO — identidade operacional acoplada ao login:** embora FKs recentes aceitem `User` nulo, telas, filtros, relações e papéis ainda presumem que associado/prestador é usuário.

A direção segura é evolutiva: preservar `ServiceOrder` e todos os históricos, criar uma identidade operacional durável (`Member`) independente de login, congelar catálogo e composição na execução validada e gerar, a partir dela, obrigações separadas para cliente e prestador. Pagamentos passam a ser eventos idempotentes alocados às obrigações e a infraestrutura financeira existente de caixa/reversão deve ser reutilizada. `ServiceProviderWork` deve permanecer legível por um adaptador até que uso e dados sejam reconciliados.

## 2. Método, cobertura e limitações

Foram inspecionados artefatos nas seguintes áreas:

- models e enums de usuário, tenant, associado, prestador, catálogo, OS, trabalhos, pagamentos, ledgers, despesas, caixa, estoque e documentos;
- migrations que criam e alteram essas estruturas;
- rotas web, middleware de tenant/papéis e policies;
- portal do prestador e recursos/ações Filament;
- observers, jobs de armazenamento remoto e serviços financeiros/documentais reutilizáveis;
- factories e testes automatizados;
- documentação técnica existente.

O projeto executa Laravel 12.64 em PHP 8.3.1. A consulta de status das migrations falhou porque o MySQL configurado em `127.0.0.1:3306` não estava disponível. Consequências:

- o relatório descreve o **schema pretendido pelas migrations**, não garante o schema implantado;
- não foram quantificados registros órfãos, duplicados, nulos, sobrepagamentos, divergências de saldo nem uso real das rotas;
- não se pode confirmar engine, constraints efetivamente aplicadas ou drift de produção;
- classificações como “candidato a não usado” exigem telemetria e consulta ao banco antes de qualquer retirada.

## 3. Arquitetura atual

O mapa completo está em [MAPA_ATUAL_MODULO_SERVICOS.md](MAPA_ATUAL_MODULO_SERVICOS.md) e a matriz de integrações em [MATRIZ_INTEGRACOES_SERVICOS.md](MATRIZ_INTEGRACOES_SERVICOS.md).

### 3.1 Identidade: não existe `Member` real

Não foi encontrado model/tabela `Member`. O conceito mais próximo é `TenantUser`, tabela `tenant_user`, que hoje representa membership/acesso e guarda:

- `tenant_id` e `user_id`, únicos em conjunto;
- estado administrativo, `is_admin` e papéis JSON;
- nome e senha específicos do tenant;
- dados de desativação, notas e histórico de email.

Essa estrutura é um vínculo de acesso, não uma identidade operacional estável. Ela exige `user_id`, bloqueia exclusão e troca de tenant no model e ainda expõe accessors que localizam associado/prestador pelo mesmo `(tenant_id,user_id)`. Associado e prestador, por sua vez, apontam opcionalmente para `users`.

As migrations mais recentes tornam `users.name`, `users.email`, `users.password` e `associates.user_id` anuláveis; `service_providers.user_id` já era anulável. Isso reduz a obrigação física, mas não remove a obrigação comportamental:

- formulário de associado ainda exige selecionar/criar usuário;
- recurso de membro exige email/nome/senha e cria ou reaproveita `User`;
- portal lista associados com `whereHas('user')` ativo;
- identidade/nome do associado passa por `TenantIdentityService` baseado em usuário;
- relatórios e views leem `associate.user`;
- observer do prestador sincroniza papéis no `User` global.

No admin de prestadores, `user_id` já é opcional, portanto a afirmação “o cadastro sempre obriga usuário” é parcialmente verdadeira: é verdadeira na experiência de associado/membro e em dependências de leitura, mas não na FK nem no formulário atual de prestador.

### 3.2 Catálogo e tarifas

`Service` é um catálogo simples com código, nome, descrição, tipo, unidade, preços base/associado/não associado, tarifas do prestador, cobrança mínima, ativo padrão e status. O formulário principal não expõe todos os campos do model, especialmente parte das tarifas do prestador.

Há três lugares concorrentes para tarifa do prestador:

1. `service_providers.hourly_rate/daily_rate`, usado pelo trabalho legado;
2. `services.provider_hourly_rate/provider_daily_rate`;
3. `service_provider_services.provider_*_rate`, como override por par.

Não há versão publicada, vigência, requisitos de execução, evidências obrigatórias, recursos, regras de composição, campos customizados, aprovação ou contrato por tipo de serviço.

### 3.3 Ordem e execução

`ServiceOrder` concentra:

- solicitação e agenda;
- cliente/associado, prestador, serviço e ativo;
- início, fim, medidores, combustível, distância e descrição;
- quantidades estimada/real e tarifas;
- total, desconto, total final e pagamento do prestador;
- estados operacionais e financeiros;
- flags/timestamps de pagamento e IDs de movimentos legados;
- comprovante, observações, criação e aprovação.

Ela é a verdade operacional de fato, porém não é uma verdade congelada. A conclusão recalcula valores usando o catálogo/pivot vigente naquele momento. Duas execuções concorrentes da conclusão podem recriar acréscimos e despesas, porque não há lock, versão otimista nem chave de operação. A geração do número consulta o último registro global, incrementa e grava sem lock; a unicidade global evita algumas duplicidades apenas lançando erro, e a numeração atravessa tenants.

### 3.4 Adicionais e recursos

`ServiceOrderAddition` suporta `expense`, `fee` e `discount`. Taxas e descontos afetam o valor do cliente; despesas criam `Expense` polimórfica, mas não afetam automaticamente o total. O mecanismo é um bom embrião de composição, porém faltam:

- versão/origem de regra;
- unidade, quantidade e preço unitário explícitos por linha;
- contribuição, retenção, dedução, reembolso e ajuste;
- vínculo único/idempotente com despesa ou consumo;
- recursos múltiplos (pessoas, ativos, materiais, estoque);
- snapshot das definições usadas.

Combustível e medidores são colunas soltas. O estoque possui domínio próprio, mas a OS não reserva, consome nem estorna item de estoque de forma integrada.

### 3.5 Pagamentos, obrigações, caixa e ledgers

`ServiceOrderPayment` representa ao mesmo tempo:

- parcela futura (`PENDING`);
- aviso/comprovante registrado pelo prestador;
- pagamento efetivamente faturado (`BILLED` ou, em outro caminho, `PAID`);
- recebimento do cliente e pagamento ao prestador, discriminados por `type`.

Não possui chave idempotente, número de parcela/acordo, FK direta para movimento de caixa, evento de reversão ou unicidade de operação. O model calcula recebido/pago somando `amount` em status `BILLED`, mas mantém um total legado que soma todos os tipos e estados. Campos `discount`, `fees` e `final_amount` não são a base uniforme dos cálculos.

O resultado é uma pluralidade de verdades:

- ação da OS recebe cliente e cria `ServiceOrderPayment` + `CashMovement`;
- ação de faturamento seleciona um pagamento pendente e cria caixa;
- página de criação direta de pagamento salva primeiro e, no `afterCreate`, atualiza ledger/status com uma regra diferente e sem movimento de caixa;
- conclusão financeira da OS depende de estados específicos;
- observer cria débito no razão do associado apenas quando a OS muda para `BILLED`, transição que o fluxo principal normalmente não usa;
- pagamento do prestador no fluxo novo grava **crédito** no ledger, enquanto pagamento de `ServiceProviderWork` grava **débito**;
- a OS concluída não cria de forma consistente a obrigação inicial no razão do prestador;
- pagamento parcial ao prestador aprova todas as solicitações pendentes da OS, independentemente do valor.

Assim, nem `service_orders`, nem pagamentos, nem caixa, nem os ledgers podem ser declarados isoladamente como verdade financeira íntegra.

### 3.6 Solicitação de pagamento do prestador

O GET evita mostrar nova solicitação quando existe uma pendente, mas o POST não impõe essa condição transacionalmente. Não há constraint para uma solicitação ativa por escopo, chave idempotente, lock do saldo nem soma reservada de outras solicitações. Reenvio ou concorrência podem superar o valor disponível.

### 3.7 Legado paralelo

`ServiceProviderWork` ainda é usado no gerenciador de trabalhos dentro do recurso administrativo do prestador, cria crédito no ledger ao nascer e permite marcar como pago com débito. Logo, é **LEGADO ATIVO**, não código morto.

Views antigas `provider/works.blade.php`, `provider/work-form.blade.php` e `provider/edit-order.blade.php` não aparecem nas rotas atuais. Uma delas referencia `receipt_path` que não existe no model/migration de trabalho. Classificação: **CANDIDATO A NÃO USADO**, sujeito a logs de acesso, links externos, jobs, integrações e dados antes de remoção.

### 3.8 Documentos, contratos e armazenamento

Há três mecanismos potencialmente reutilizáveis:

- `Document`: anexo polimórfico com metadados;
- `GeneratedDocument` + `DocumentTemplate`: geração, variáveis, estado e assinaturas;
- `CloudDocument`: controle de sincronização/versionamento no armazenamento do tenant.

O módulo de serviços não os usa como fonte canônica. A OS e o pagamento guardam paths diretos; o PDF da OS é gerado sob demanda. Observers/jobs sincronizam certos paths para Google Drive, mas o registro remoto é estado de sync, não autorização do documento.

O risco imediato está na rota `/storage/{path}` e no disco público: o path conhecido pode ser baixado sem autenticação ou teste de tenant. Recibos, documentos bancários e dados pessoais precisam de armazenamento privado e endpoint autorizado.

### 3.9 Relatórios

O relatório de pagamentos filtra apenas OS `COMPLETED`, enquanto o domínio possui `AWAITING_PAYMENT`, `PAID` e `BILLED`. Ele trata margem como `final_price - provider_payment`, sem despesas, taxas, descontos efetivos, situação da liquidação ou custo de recursos. O mapeamento de status não cobre de modo uniforme `BILLED`. Consultas que dependem de `associate.user` podem omitir ou falhar para associados sem login.

## 4. Achados por severidade

### 4.1 Críticos

#### C-01 — autorização de portal atravessa tenants

**Evidência:** `TenantFromSlugMiddleware` resolve o slug e escreve a sessão, sem conferir membership. `CheckAnyRole` aceita papel global antes do papel por tenant. `ProviderDashboardController::getProvider()` cria prestador automaticamente quando encontra papel aplicável.

**Cenário:** usuário com papel global `service_provider` obtido no tenant A acessa `/tenant-b/provider/dashboard`; o middleware seleciona B, o papel global passa e o GET pode criar o prestador em B.

**Impacto:** criação e possível leitura/escrita não autorizada em outro tenant.  
**Ação imediata:** bloquear portal por membership ativo no tenant do slug e aceitar somente papel tenant-scoped para papéis operacionais; transformar autocriação em caso de uso explícito e autorizado.  
**Rollback:** feature flag para o novo middleware; manter sessão anterior somente se membership for válido.  
**Teste obrigatório:** matriz usuário/tenant/papel global/papel local e tentativa de autocriação cruzada.

#### C-02 — IDOR em faturamento de pagamento

**Evidência:** a action da OS usa `ServiceOrderPayment::findOrFail($data['payment_id'])`; as opções do formulário são filtradas, mas a mutação não reconfirma `service_order_id`, tenant, tipo e estado.

**Impacto:** payload adulterado pode faturar pagamento de outra OS/tenant e criar caixa referenciando a OS visível.  
**Ação:** recarregar pagamento pela relação bloqueada da OS e validar tipo/status/tenant dentro da transação.  
**Teste:** payload com ID de outra OS e outro tenant deve resultar em 404/403 sem efeitos.

#### C-03 — comprovantes expostos por path público

**Evidência:** uploads em disco público, URLs por `Storage::url` e rota genérica de storage sem autenticação/propriedade.

**Impacto:** vazamento de documento, dados bancários e pessoais.  
**Ação:** inventariar paths e acessos, migrar para privado de modo compatível, endpoint de download com autorização record-level e URLs temporárias quando remoto.  
**Rollback:** leitura dual controlada durante migração, nunca republicar novos anexos.

### 4.2 Altos

| ID | Achado | Consequência |
|---|---|---|
| A-01 | Fluxos financeiros divergentes entre actions, página de pagamento, observer e trabalho legado | Caixa, status e ledgers não reconciliam |
| A-02 | Sem idempotência/locks em recebimento, pagamento, conclusão, saque e parcelamento | Duplicidade, sobrepagamento e dupla despesa |
| A-03 | Preço da execução usa tarifa corrente | Mudança no catálogo altera economicamente serviço em andamento |
| A-04 | IDs de associado/ativo no portal usam `exists` global | Relacionamento cruzado entre tenants |
| A-05 | Papéis de prestador sincronizados globalmente no `User` | Privilégio operacional escapa do tenant |
| A-06 | Pedido de pagamento sem reserva/constraint concorrente | Solicitações excedem o saldo disponível |
| A-07 | Razão do prestador usa sinais contraditórios | Saldo exibido pode inverter obrigação/pagamento |
| A-08 | FKs tenantizadas são simples e muitos registros históricos usam cascade | Banco não impede referência cross-tenant; exclusão pode apagar história |
| A-09 | Estados operacionais e financeiros estão no mesmo enum/registro | Transições ambíguas e relatórios incompletos |

### 4.3 Médios

| ID | Achado | Consequência |
|---|---|---|
| M-01 | Número de OS é “último + 1” global sem lock | Colisão em concorrência e ausência de sequência por tenant |
| M-02 | Associado sem usuário some do seletor do portal | Cadastro operacional válido não pode ser atendido |
| M-03 | Dados de cliente não associado vão para `notes` | Não são validáveis, pesquisáveis ou auditáveis como identidade snapshot |
| M-04 | Medidores não validam fim >= início | Execução fisicamente inconsistente |
| M-05 | Não há evidência/foto/assinatura obrigatória configurável | Aprovação e contestação frágeis |
| M-06 | Relatório calcula “lucro” sem custos/despesas/liquidação | Indicador gerencial enganoso |
| M-07 | Catálogo não expõe de forma coerente todos os campos do model | Configuração invisível ou dependente de código |
| M-08 | Ausência de eventos/casos de uso únicos | Portal e admin implementam regras distintas |

### 4.4 Baixos e dívida de clareza

- views não roteadas e documentação podem induzir manutenção em caminhos obsoletos;
- nomes `PAID` e `BILLED` são usados como equivalentes em partes do módulo;
- comentários/campos legados permanecem ao lado das relações novas;
- `CloudDocument` pode ser interpretado incorretamente como documento canônico, embora seja principalmente estado de sincronização.

### 4.5 Matriz de riscos rastreável

| ID | Severidade | Tipo | Evidência (arquivo:linha / símbolo) | Impacto | Recomendação |
|---|---|---|---|---|---|
| C-01 | CRITICAL | TENANT, SECURITY | `app/Http/Middleware/TenantFromSlugMiddleware.php:26,35`; `CheckAnyRole.php:30,35,46`; `ProviderDashboardController.php:46,63` | Usuário autorizado em A pode selecionar B pelo slug e materializar prestador | Membership ativo obrigatório; papéis operacionais locais; criação explícita |
| C-02 | CRITICAL | SECURITY, FINANCIAL | `app/Filament/Resources/ServiceOrderResource.php:610,650-653` (`billClientPayment`) | ID adulterado fatura pagamento alheio e gera movimento incoerente | Resolver pela relação da OS, tenant/tipo/status, sob lock |
| C-03 | CRITICAL | SECURITY | `routes/web.php:49-54`; uploads e `receipt_path` no portal | Exposição pública de recibos/dados pessoais | Storage privado e download por policy |
| A-01 | HIGH | FINANCIAL, DATA_INTEGRITY | `CreateServiceOrderPayment.php:27,44,51,67,77,84`; actions em `ServiceOrderResource` | Writers produzem caixa/ledger/status distintos | Caso de uso único e reconciliação antes de migração |
| A-02 | HIGH | FINANCIAL, DATA_INTEGRITY | `ServiceOrderResource::finishExecution/generateInstallments/billClientPayment/payProvider`; `ProviderDashboardController::completeOrder/storePaymentRequest/storeClientPayment` | Retry ou concorrência duplica valor/efeito | `operation_key`, unique, transação e locks |
| A-03 | HIGH | FINANCIAL, ARCHITECTURE | `ProviderDashboardController::completeOrder`; `ServiceOrderResource::finishExecution` | Tarifa atual altera economia de execução iniciada | Versão e snapshot de catálogo/composição |
| A-04 | HIGH | TENANT, SECURITY | `ProviderDashboardController.php:276,282` | Associado/ativo de outro tenant pode ser ligado à OS | Regra `exists` tenantizada e reload explícito |
| A-05 | HIGH | TENANT, SECURITY | `app/Models/ServiceProvider.php:155-180` (`syncRolesToUser`) | Papel de pessoa/tenant vira privilégio global | Membership/papel local; migração auditada |
| A-06 | HIGH | FINANCIAL, DATA_INTEGRITY | `ProviderDashboardController::requestPayment/storePaymentRequest`; ausência de unique ativa | Saques concorrentes excedem saldo | Reserva, lock e chave idempotente |
| A-07 | HIGH | FINANCIAL | `ServiceProviderWork.php:47-53`; `WorksRelationManager.php:194-216`; `ServiceOrderResource` pagamento do prestador | Crédito/débito têm semânticas divergentes | Definir obrigação/sinais e reconciliar legado |
| A-08 | HIGH | TENANT, DATA_INTEGRITY | migrations centrais usam FKs simples e `cascadeOnDelete`; tenant global scope depende da sessão | Relação cross-tenant não é barrada fisicamente; histórico pode ser apagado | Constraints compostas/validação e política de retenção em fase futura |
| A-09 | HIGH | ARCHITECTURE, MAINTAINABILITY | `ServiceOrderStatus` contém estados operacionais e financeiros; `ServiceOrderPaymentStatus` contém `PAID` e `BILLED` | State machine ambígua | Estados separados e projeção compatível |
| M-01 | MEDIUM | DATA_INTEGRITY | `app/Models/ServiceOrder.php:103-105` | Colisão de número e sequência global | Contador transacional por tenant |
| M-02 | MEDIUM | UX, ARCHITECTURE | `Associate.php:167-169`; `ProviderDashboardController.php:250` | Membro sem login desaparece | Estado operacional no Member; login opcional |
| M-03 | MEDIUM | DATA_INTEGRITY, UX | `ProviderDashboardController::storeOrder` grava não associado em notas | Identidade não pesquisável/validável | Snapshot estruturado do tomador |
| M-04 | MEDIUM | DATA_INTEGRITY | `completeOrder` valida presença/tipo, sem invariante fim >= início | Medição inválida | Regra de domínio por tipo de medidor |
| M-05 | MEDIUM | ARCHITECTURE, UX | Catálogo não define evidências; OS tem um único `receipt_path` | Aprovação frágil | Requisitos condicionais versionados |
| M-06 | MEDIUM | FINANCIAL | relatório de pagamento usa `final_price-provider_payment` e filtro `COMPLETED` | Prestação/margem incorreta ou incompleta | Read model reconciliado |
| M-07 | MEDIUM | MAINTAINABILITY | campos de tarifa no model/migration não têm exposição uniforme no `ServiceResource` | Configuração implícita/invisível | Uma política de preço editável e versionada |
| M-08 | MEDIUM | ARCHITECTURE | regras duplicadas em controller e actions Filament | Interfaces discordam | Application services compartilhados |
| L-01 | LOW | LEGACY | views `provider/works`, `work-form`, `edit-order` sem rota localizada | Confusão e superfície esquecida | Telemetria e call mapping de produção antes de aposentar |
| I-01 | INFO | MAINTAINABILITY | só existe `UserFactory`; busca em testes não encontrou cenário ponta a ponta de serviços | Regressões não são detectadas | Factories e testes diagnósticos/contratuais antes da mudança |

## 5. Verdades recomendadas

### 5.1 Verdade operacional

**Recomendação:** manter `ServiceOrder` como contêiner compatível de solicitação/agenda e fazer da **execução validada e congelada** a verdade operacional futura. Ela deve conter:

- referência à OS, tenant, membro cliente e membro prestador;
- versão do catálogo e política aplicada;
- dados tipados e snapshot dos campos configuráveis;
- medições, recursos, evidências e composição;
- ator, horário, versão concorrente e aprovação;
- hash determinístico ou mecanismo equivalente para detectar alteração após aceite.

Não é necessário impor agora um nome de tabela. O importante é a fronteira: solicitar não produz fato financeiro; concluir tecnicamente produz uma execução candidata; validar/aprovar congela o fato; só então são derivadas obrigações.

### 5.2 Verdade financeira

**Recomendação:** uma **obrigação imutável/versionada** gerada uma vez a partir da execução validada:

- obrigação a receber do cliente;
- obrigação a pagar ao prestador;
- cada qual com linhas, vencimento, moeda, partes, tenant, origem e estado próprio;
- correções são novos ajustes/reversões, não edição silenciosa do principal;
- pagamentos são eventos com chave de operação;
- alocações ligam pagamentos a uma ou mais obrigações;
- movimento de caixa é efeito contábil do pagamento, não a obrigação;
- ledger e relatórios são projeções/reconciliações, não a única origem.

O sistema já possui padrões mais maduros em recebimentos de associados/clientes: `operation_key` único por tenant, `lockForUpdate`, transação, movimento reverso vinculado e testes MySQL de concorrência. A evolução de serviços deve reutilizar esses serviços/padrões ou generalizá-los, evitando um “terceiro financeiro”.

### 5.3 Renegociação

Renegociação não deve reescrever OS ou obrigação original. Um acordo deve:

- referenciar explicitamente obrigações e saldo elegível;
- congelar termos, parcelas, juros/desconto e aprovadores;
- manter versões e motivo;
- gerar plano de liquidação separado dos eventos pagos;
- suportar cancelamento por reversão e restabelecimento do saldo;
- preservar cadeia original → acordo → parcelas → pagamentos → caixa.

## 6. Estratégia para `Member` com `User` opcional

### 6.1 Decisão recomendada

Criar futuramente um registro operacional `Member` por tenant, independente de autenticação. `User` continua sendo login/ator global; `TenantUser` continua membership de acesso. Associado e prestador passam a referenciar `Member`.

`Member` deve guardar identidade mínima estável do tenant: nome, contatos, documento normalizado quando cabível, estado operacional e metadados de auditoria. O acesso é uma relação opcional entre `Member` e `TenantUser`/`User`, não condição para existência do membro.

Evoluir `TenantUser` para virar o próprio Member parece econômico, mas perpetua `user_id NOT NULL`, credencial, papéis e lifecycle de acesso dentro da identidade operacional. Separar é mais seguro semanticamente e permite membro sem login, mais de um acesso autorizado no futuro e suspensão de login sem apagar a pessoa.

### 6.2 Migração sem quebra

1. Adicionar `members` e `member_id` anulável em associado/prestador, sem remover `user_id`.
2. Fazer inventário por tenant de `(TenantUser, Associate, ServiceProvider)` e gerar relatório de ambiguidades.
3. Backfill determinístico, preservando um Member compartilhado quando a mesma pessoa exerce papéis; conflitos exigem revisão humana.
4. Adotar dual-read: `member` primeiro, fallback por `(tenant,user)`; registrar fallback em telemetria.
5. Adotar escrita única no novo modelo e compatibilidade explícita para caminhos antigos durante janela curta.
6. Alterar forms: “cadastrar membro” e ação separada “conceder acesso”.
7. Migrar filtros, relatórios, notificações e papéis para relações explícitas.
8. Após métricas zerarem fallback e auditoria aprovar, tornar `member_id` obrigatório onde a regra exigir. `user_id` legado permanece até uma fase posterior e reversível.

### 6.3 Classificação de migrations futuras

| Classe | Exemplos | Regra de execução |
|---|---|---|
| **SAFE** | criar tabela nova; adicionar colunas nullable; adicionar índices não bloqueantes suportados | Deploy aditivo, sem mudar leitura existente |
| **NEEDS BACKFILL** | popular `member_id`, snapshot e referência de obrigação | Job reentrante por lotes, checkpoint, dry-run e reconciliação |
| **NEEDS HUMAN REVIEW** | dois usuários para a mesma pessoa; um usuário ligado a múltiplos associados/prestadores incompatíveis; tenant ausente | Fila de decisão, sem escolha automática destrutiva |
| **HIGH RISK** | `NOT NULL`, unique sobre dados antigos, trocar FK/cascade, remover coluna/status, mover anexos públicos | Somente após pré-check, métricas, backup, canário e rollback ensaiado |

## 7. Catálogo configurável e snapshot

### 7.1 Modelo recomendável

Separar identidade estável do serviço de sua versão publicável:

- serviço: código, nome e ciclo de vida;
- versão: vigência, status rascunho/publicado/aposentado, unidades, requisitos e política;
- definições de campo: chave estável, tipo, unidade, validação, obrigatoriedade, visibilidade, indexação e regra de evidência;
- política de preço/recursos: linhas declarativas e precedência de tarifa;
- snapshot de execução: cópia normalizada da versão, entradas e resultados efetivamente usados.

### 7.2 Campos customizados: híbrido controlado

Evitar tanto “uma coluna para cada serviço” quanto EAV irrestrito. Usar:

- definições relacionais versionadas;
- valores congelados em JSON normalizado por execução;
- tabela de valores tipados ou colunas promovidas apenas para campos que exigem filtro, agregação, índice, constraint ou cálculo frequente;
- schema de validação server-side; UI é derivada, não autoridade;
- nenhuma fórmula PHP/JS arbitrária armazenada pelo usuário.

Campos financeiros, identificadores, datas de competência e medições de segurança devem ser tipados/promovidos. Observações raras e não pesquisadas podem ficar no snapshot JSON.

### 7.3 Composição declarativa

Cada linha deve registrar pelo menos: tipo (`base`, `additional`, `fee`, `contribution`, `discount`, `deduction`, `reimbursement`, `resource`, `adjustment`), origem/regra/versão, descrição snapshot, quantidade, unidade, tarifa, valor, efeito no cliente, efeito no prestador, conta/categoria opcional e referência ao recurso/despesa.

A composição é calculada por um serviço de domínio puro, testável e versionado. Depois da validação, linhas e totais são congelados; alteração gera nova versão/ajuste.

## 8. Integração com recursos, estoque, despesas e documentos

### 8.1 Recursos

- ativos: suportar um ou mais, função no serviço e medições de entrada/saída;
- equipe: membros/prestadores e papéis por execução, sem inferência por login;
- materiais/estoque: reserva, consumo e estorno através do serviço de estoque existente, com referência idempotente à linha da execução;
- despesas: uma despesa por linha/origem, criada uma vez e reconciliável;
- combustível: tratar como medição/recurso tipado, não somente duas colunas sem unidade/política.

### 8.2 Documentos e assinaturas

Adotar `Document` como registro canônico de anexo e `GeneratedDocument` para artefatos gerados/assináveis. O contrato/OS gerado deve referenciar:

- execução e obrigação;
- template e versão;
- variáveis/snapshot usados;
- hash, signatários, horários e estado;
- arquivo privado e registro remoto, quando houver.

`CloudDocument` continua responsável por sincronização e versão remota, sem assumir autorização de domínio.

## 9. Segurança, tenant e autorização

### 9.1 Invariantes obrigatórios

1. Tenant vem do contexto autenticado e membership ativo, nunca apenas do slug/sessão/payload.
2. Papel operacional deve ser consultado no tenant corrente; papel global fica restrito a funções administrativas explicitamente globais.
3. Toda entidade referenciada é recarregada por tenant dentro do caso de uso.
4. Relação aninhada é validada no backend (`payment` pertence à `order`, que pertence ao tenant e ao ator autorizado).
5. Policies avaliam capability e record ownership/tenant; global scopes são defesa complementar.
6. Jobs carregam tenant/origem explicitamente, usam `withoutGlobalScopes` apenas com predicado de tenant e são idempotentes.
7. Downloads usam autorização e storage privado.
8. Dados históricos não são apagados por exclusão operacional de tenant/membro; retenção deve ser decisão explícita.

### 9.2 Admin versus portal

O painel Filament tem defesa mais forte: middleware/tenant resolver e `TenantScoped` retornam vazio sem sessão para usuário normal. Ainda assim, actions customizadas precisam revalidar registros; opções filtradas no frontend não substituem autorização de mutação. O portal é a prioridade porque aceita slug e papel global sem confirmar membership.

## 10. Concorrência, idempotência, auditoria e reversão

Operações sensíveis devem seguir um protocolo único:

1. receber `operation_key` UUID;
2. iniciar transação;
3. carregar tenant, ordem/execução/obrigação com `lockForUpdate` em ordem consistente;
4. verificar operação existente por `(tenant_id, operation_key)`;
5. validar saldo, estado, versão e propriedade;
6. criar evento, alocações, caixa, documento/outbox;
7. atualizar projeções;
8. commit; notificações e sync somente pós-commit;
9. retry retorna o mesmo resultado;
10. cancelamento cria reversão vinculada, nunca delete/edição invisível.

Aplicar a: numeração, aceitar/concluir execução, gerar obrigação, gerar parcelas, registrar/faturar/cancelar pagamento, pagar prestador, solicitar/aprovar/rejeitar saque, criar despesa/consumo e gerar/assinar documento.

## 11. Estratégia de transição e compatibilidade

### Fase 0 — contenção e observabilidade

**Objetivo:** fechar riscos críticos sem mudar domínio.

- corrigir membership/papel tenant-scoped no portal;
- revalidar entidades aninhadas e downloads;
- impedir novas gravações financeiras por caminhos divergentes via feature flags/permissões;
- instrumentar contadores por rota/action, fallback de identidade, status e divergência;
- criar consultas de reconciliação e testes de segurança/concurrency.

**Gate:** zero travessia de tenant nos testes; inventário de writers e anexos aprovado.  
**Rollback:** flags por fluxo e restauração apenas da leitura antiga; não desfazer eventos já gravados.

### Fase 1 — identidade operacional aditiva

- criar Member e vínculos nullable;
- backfill por lotes e fila de conflitos;
- dual-read com telemetria;
- separar cadastro de concessão de acesso.

**Gate:** 100% dos registros ativos resolvidos ou explicitamente excepcionados; nenhum login alterado.  
**Rollback:** desligar leitura preferencial de Member; colunas novas permanecem.

### Fase 2 — catálogo versionado e execução snapshot

- introduzir versões/definições/composição;
- congelar novas execuções;
- adaptar serviços existentes como versão inicial;
- manter leitura do `ServiceOrder` antigo por adaptador.

**Gate:** cálculo paralelo sem diferenças não explicadas; aprovação de amostra por tipo de serviço.  
**Rollback:** novas OS voltam ao escritor antigo por flag; snapshots já criados permanecem auditáveis.

### Fase 3 — obrigações e pagamentos unificados

- gerar recebível/pagável a partir da execução validada;
- usar chave de operação, locks, alocação, caixa e reversão;
- todas as interfaces chamam o mesmo application service;
- manter projeção compatível nos campos legados quando necessário.

**Gate:** reconciliação diária entre obrigações, pagamentos, caixa, despesas e ledgers; nenhuma diferença sem ticket.  
**Rollback:** parar novas obrigações e manter leitura dual; jamais apagar ou regravar história.

### Fase 4 — documentos, contratos, recursos e renegociação

- storage privado e registro canônico;
- contratos assináveis ligados ao snapshot;
- recursos/estoque/despesas idempotentes;
- acordo de renegociação referencial.

### Fase 5 — consolidação do legado

- classificar cada `ServiceProviderWork` como mapeado, independente ou conflito;
- congelar criação legada após telemetria e comunicação;
- manter leitura e relatórios compatíveis;
- aposentar código apenas com prova de não uso e plano de restauração.

Nenhuma fase exige apagar dados antigos. Deprecação deve ser por escrita bloqueada, adaptador de leitura, reconciliação e retenção.

### 11.1 Gates e migrations prováveis por fase

| Fase | Dependências | Migrations prováveis (não implementadas) | Risco/compatibilidade | Testes e gate GO/NO-GO |
|---|---|---|---|---|
| 0 — contenção | Nenhuma | Em princípio nenhuma; eventual índice/chave idempotente é aditivo | Correção de autorização pode bloquear acessos hoje indevidos | GO somente com matriz tenant/IDOR e inventário de writers/anexos |
| 1 — identidade | Fase 0 + inventário real | `members`; `member_id` nullable; índices e FKs aditivos | Backfill e ambiguidades; preservar `user_id` e login | GO com 100% ativos resolvidos/excepcionados e rollback de leitura testado |
| 2 — catálogo/execução | Member e decisão de truth boundary | versões, definições, snapshots/linhas; tudo aditivo | Divergência de cálculo e UI | GO após shadow calculation e amostra aprovada por tipo |
| 3 — obrigações/pagamentos | Execução congelada + padrão financeiro | obrigação, linha, alocação e operation key, ou generalização equivalente | Dupla escrita/duplicidade | GO com reconciliação diária zerada e concurrency MySQL verde |
| 4 — documentos/recursos/acordos | Fases 2–3 | vínculos/requisitos; metadados privados; acordo/versionamento | Migração de arquivos e efeitos externos | GO com autorização, hash, estorno e cadeia documental verificados |
| 5 — legado | Telemetria e reconciliação | Nenhuma necessária para congelar; remoções são `HIGH RISK` e posteriores | Relatórios/integrações ocultos | GO para bloquear escrita; NO-GO para apagar sem prova de uso/dados |

## 12. Rollout, rollback e gates operacionais

### 12.1 Rollout

- flags separadas por tenant e capacidade;
- primeiro tenant interno/canário com pequeno volume;
- shadow calculation de preço e obrigação;
- escrita dual somente quando inevitável, com uma fonte declarada como primária;
- reconciliação automática diária e painel de exceções;
- expansão por coortes após janela financeira completa.

### 12.2 Rollback

- migrations aditivas; não remover coluna/enum na mesma release da troca;
- leitores compatíveis com ambos os formatos;
- eventos novos não são apagados: são interrompidos ou revertidos contabilmente;
- jobs têm checkpoint e podem ser pausados/reexecutados;
- contratos e documentos mantêm versões anteriores;
- backup e ensaio de restauração antes de qualquer migration `HIGH RISK`.

### 12.3 Gates mínimos

- segurança: suite cross-tenant/IDOR/download verde;
- dados: contagens e totais reconciliados por tenant e competência;
- concorrência: testes MySQL reais para retry e corridas;
- operação: nenhum fluxo ativo sem owner, telemetria e runbook;
- financeiro: obrigação = alocado + saldo ± ajustes; caixa e reversões encadeados;
- identidade: fallback `user_id` medido e tendendo a zero;
- legado: nenhuma retirada sem 30–90 dias de ausência comprovada ou aprovação formal.

## 13. Plano de testes

Não há factory específica de serviço/prestador/OS e não foram encontrados testes dedicados ao fluxo completo do módulo. Prioridades:

### Segurança

- usuário de A acessa slug de B com papel global/local;
- associado/ativo/pagamento/conta de outro tenant em payload adulterado;
- prestador tenta operar OS de outro prestador;
- download anônimo, de outro tenant e após revogação;
- superadmin versus admin tenant-scoped, com decisões explícitas.

### Domínio e contratos

- membro sem login cria/recebe serviço;
- suspender login não desativa identidade operacional;
- catálogo muda após solicitação e snapshot preserva preço;
- campos tipados inválidos, requisitos/evidências e recursos;
- composição testa cada tipo e arredondamento.

### Financeiro

- pagamento parcial/total/excedente;
- cliente e prestador jamais entram na soma um do outro;
- parcelas futuras não são tratadas como dinheiro recebido;
- retry com mesma chave retorna mesmo resultado;
- duas requisições simultâneas não sobrepagam;
- estorno restaura saldos sem apagar evento;
- pagamento parcial não aprova solicitações além do valor.

### Concorrência MySQL

- numeração simultânea por tenant;
- duas conclusões da mesma execução;
- dois pagamentos/saques simultâneos;
- criação única de despesa/estoque/documento;
- deadlock retry e ordem consistente de locks.

### Migração e legado

- backfill reentrante;
- conflitos de identidade enviados a revisão;
- leitura de OS/trabalho antigo idêntica antes/depois;
- relatório reconciliado por tenant/status/período;
- rollback de flag sem perda.

## 14. Consultas de diagnóstico para ambiente com banco

Estas verificações não foram executadas e devem ser adaptadas ao schema implantado:

- associados/prestadores sem usuário, com usuário de outro tenant ou compartilhamentos ambíguos;
- `tenant_user` ausente/inativo para usuários ligados a entidades operacionais;
- FKs de serviço, associado, ativo, prestador, pagamento, conta e despesa com tenant divergente;
- múltiplas solicitações de pagamento pendentes por OS/prestador;
- soma paga acima de `final_price`/`provider_payment`;
- pagamentos sem caixa, caixas sem pagamento e movimentos repetidos;
- razão sem origem, saldo_after descontínuo e sinais incompatíveis;
- OS em estados impossíveis ou com medidor final menor que inicial;
- acréscimos/despesas duplicados por ordem/descrição/valor/horário;
- paths públicos, arquivos ausentes, duplicados ou não sincronizados;
- `ServiceProviderWork` ativos, pagos e não mapeados a OS;
- distribuição real dos estados `PAID`, `BILLED`, `AWAITING_PAYMENT`, `COMPLETED`;
- drift entre `migrate:status`, migrations e `information_schema`.

## 15. Backlog priorizado

| Prioridade | Item | Severidade | Dependência | Critério de aceite |
|---|---|---|---|---|
| P0 | Validar membership e papéis por tenant no portal | Crítica | Nenhuma | Matriz cross-tenant verde; nenhuma autocriação por GET não autorizado |
| P0 | Corrigir autorização record-level de pagamentos e demais entidades aninhadas | Crítica | Nenhuma | IDs adulterados não causam efeitos |
| P0 | Proteger comprovantes/documentos | Crítica | Inventário de paths | Download autenticado/autorizado; novos arquivos privados |
| P0 | Inventariar e congelar writers financeiros divergentes | Alta | Telemetria/flags | Um caso de uso por evento; writers antigos identificados |
| P1 | Adicionar idempotência, locks e reversão aos eventos atuais | Alta | Modelo de operação | Testes MySQL de concorrência e retry verdes |
| P1 | Reconciliação de OS/pagamentos/caixa/ledgers/despesas | Alta | Acesso ao banco | Painel de exceções e baseline aprovado |
| P1 | Introduzir Member aditivo e backfill | Alta | Inventário de identidade | Membro sem login suportado; login atual inalterado |
| P1 | Definir verdade operacional/financeira e state machines separadas | Alta | Decisão arquitetural | ADR aprovado e invariantes testáveis |
| P2 | Catálogo versionado, campos híbridos e snapshot | Alta | Member/execução | Alteração de catálogo não muda execução aceita |
| P2 | Obrigações cliente/prestador e alocação de pagamentos | Alta | Financeiro unificado | Reconciliação e estorno ponta a ponta |
| P2 | Integrar documento gerado/contrato/assinatura | Média | Snapshot e storage | Artefato liga versão, hash e signatários |
| P2 | Recursos, estoque e despesas por linha idempotente | Média | Composição | Reserva/consumo/estorno reconciliáveis |
| P3 | Renegociação sem reescrita histórica | Média | Obrigações | Cadeia original-acordo-parcela-pagamento preservada |
| P3 | Read models e relatórios reconciliados | Média | Obrigações/pagamentos | Margem e saldos explicáveis por linha |
| P3 | Congelar e adaptar `ServiceProviderWork` | Média | Telemetria/reconciliação | Sem novas escritas; todo legado classificado |
| P4 | Retirar candidatos não usados | Baixa | Prova de não uso | Aprovação, backup e restauração ensaiada |

## 16. Inventário de superfícies, fluxos e responsabilidades

### 16.1 Artefatos centrais

| Categoria | Artefatos observados | Classificação |
|---|---|---|
| Models/enums | `Service`, `ServiceProvider`, `ServiceProviderService`, `ServiceOrder`, `ServiceOrderAddition`, `ServiceOrderPayment`, `ProviderPaymentRequest`, `ServiceProviderWork`, ledgers e enums de status/tipo | ACTIVE, exceto `ServiceProviderWork`: LEGACY ACTIVE |
| Controllers | `ProviderDashboardController` | ACTIVE |
| Filament | Resources de serviços, prestadores, vínculo, ordens, pagamentos, relatório; relation manager de trabalhos | ACTIVE; criação direta de pagamento é ACTIVE CONFLICTING |
| Blade | dashboard, lista/criação/detalhe da OS, financeiro/pagamento; views antigas de trabalhos/form | ACTIVE e UNUSED CANDIDATE conforme rota |
| Middleware/policies | `TenantFromSlugMiddleware`, `CheckAnyRole`, middleware/tenant resolver Filament, policies Shield | ACTIVE; protections insuficientes no portal/actions |
| Observers/jobs | `ServiceOrderObserver`; observers de prestador; `TenantStoredFileObserver`; job de sync Drive | ACTIVE |
| Events/listeners | Nenhum evento de domínio específico de serviços localizado | GAP |
| Commands/scheduler | Nenhum comando específico de serviços localizado no call mapping estático | UNKNOWN em produção externa |
| APIs | Nenhuma API específica de serviços localizada; fluxo é web/Filament | NÃO ENCONTRADA |
| Livewire/Volt | Filament usa Livewire internamente; componente de domínio independente não foi localizado | NÃO ENCONTRADO |
| Activity log | Models centrais registram atividade em graus diferentes | ACTIVE, mas não substitui evento financeiro |
| Soft delete | OS, serviço, prestador, solicitações e documentos relevantes usam em grande parte soft delete; pagamentos/ledgers não uniformemente | MISTO |
| Force delete | Não foi encontrado workflow seguro específico de serviços; policies geradas podem expor capabilities genéricas | REVIEW REQUIRED |

### 16.2 Quem faz o quê

| Entidade/fato | Quem cria | Quem altera/valida | Quem paga | Quem visualiza | Documento/consumidores |
|---|---|---|---|---|---|
| Prestador | Admin Filament; portal também autocria por acesso | Admin; observer sincroniza papel | — | Admin e próprio prestador | Ordens, trabalhos, razão, relatório |
| Serviço/catálogo | Usuário com permissão do Resource | Mesmo perfil; não há publish/approve | — | Admin e prestador habilitado | OS e vínculo prestador-serviço |
| Ordem | Admin ou próprio prestador | Admin/prestador iniciam e finalizam; aprovação operacional distinta não existe | Cliente paga e associação paga prestador | Admin, prestador proprietário | PDF sob demanda; pagamentos, despesas, trabalhos, ledgers |
| Execução | Gravada na própria OS; trabalho legado é caminho paralelo | Finalização calcula valores; não há reviewer/approver separado | — | Admin/prestador | Financeiro nasce diretamente, sem boundary de validação |
| Pagamento cliente | Admin ou prestador registra; admin fatura | Admin | Cliente | Admin/prestador | Comprovante path, caixa em alguns caminhos, ledger em outro |
| Pagamento prestador | Admin action/bulk action | Admin | Associação | Admin/prestador | Caixa, ledger e aprovação de requests |
| Solicitação de saque | Prestador | Admin indiretamente ao pagar | Associação | Admin/prestador | Dados bancários/recibo potencial |
| Despesa da OS | Finalização cria via adicional | Admin/prestador conforme origem | Associação conforme fluxo genérico de despesa | Admin financeiro | `Expense`, eventualmente caixa/documento por fluxo externo |
| Trabalho legado | Admin no relation manager | Admin marca pago | Associação | Admin e relatórios do prestador | Ledger; sem caixa/documento uniforme |

### 16.3 Traços dos fluxos reais

```text
Acesso ao portal
/{tenant}/provider/*
 -> TenantFromSlugMiddleware (seleciona sessão)
 -> CheckAnyRole (global OU tenant)
 -> ProviderDashboardController::getProvider
 -> ServiceProvider query / possível create
 -> views do portal

Lançar e finalizar serviço no portal
POST /{tenant}/provider/orders
 -> storeOrder -> validação + lookup parcial por tenant -> ServiceOrder::create
POST /{tenant}/provider/orders/{order}/complete
 -> completeOrder -> DB transaction
 -> tarifas correntes Service/ServiceProviderService
 -> ServiceOrder update + ServiceOrderAddition -> Expense
 -> TenantStoredFileObserver/job quando path aplicável

Receber cliente no admin
ServiceOrderResource action
 -> ServiceOrderPayment create/update
 -> CashMovement create
 -> ServiceOrder/status update

Criar pagamento pela Resource dedicada
ServiceOrderPaymentResource/CreateServiceOrderPayment
 -> record já persistido
 -> afterCreate transaction
 -> AssociateLedger e/ou ServiceProviderLedger
 -> soma de payments + ServiceOrder status
 -> não cria o mesmo CashMovement do fluxo anterior

Pagar trabalho legado
ServiceProviderResource/WorksRelationManager
 -> ServiceProviderWork update
 -> ServiceProviderLedger DEBIT
```

### 16.4 Criação, edição, aprovação, cancelamento e notificação

- **Criação de prestador:** Filament permite usuário opcional; portal cria implicitamente quando o papel passa. O vínculo conceitual Member–Provider não existe; é inferido por `user_id`.
- **Edição:** Resources e portal alteram diretamente models; não há application service comum nem versão otimista.
- **Aprovação:** existe `approved_by`/ações financeiras, mas não foi localizada uma etapa independente e consistente de revisão/aprovação da execução.
- **Cancelamento/exclusão:** status `CANCELLED` existe, porém não há workflow uniforme que reverta composição, pagamento, caixa, ledger, despesa, estoque e documento. Soft delete não deve ser usado como estorno.
- **Notificação:** o observer notifica débito do associado apenas na transição da OS a `BILLED`; não há orquestração completa por eventos de serviço. Email/WhatsApp futuro deve ser efeito pós-commit e jamais verdade.
- **Fiscal:** não há integração fiscal específica e ela deve continuar desacoplada; a obrigação pode futuramente emitir um estado/evento “pronta para fiscal”.

### 16.5 Permissões e workspace

Os Resources dependem das permissions geradas/Shield e o portal usa roles nominalmente. Não foram localizadas capabilities granulares equivalentes a `review_service_execution`, `approve_service_execution`, `manage_service_financials`, contratos e prestação de contas. O futuro workspace deve compor telas por permissions e casos de uso, não por nomes rígidos de cargo.

Preservar do admin atual: visão compartilhada com o portal sobre a mesma OS, filtros tenant-scoped, actions contextuais e resources separados para catálogo/habilitação. Evoluir: fila de revisão, aprovação com motivo, visão reconciliada de obrigações/pagamentos/despesas, documentos e exceções.

### 16.6 UX futura sem burocracia

- catálogo configurado/publicado uma vez, com presets e duplicação de versão;
- prestador vê somente campos/requisitos aplicáveis e defaults já preenchidos;
- requisitos condicionais aparecem quando a resposta os ativa;
- cálculo e contabilidade ficam no servidor e não na tela do prestador;
- gestor revisa exceções, não redigita a execução;
- prestação mensal/anual é derivada de read models reconciliados;
- textos/anexos nunca alteram valor sem linha estruturada explícita;
- configuração avançada pode existir em seções progressivas, mantendo o caminho simples curto.

### 16.7 Schema e constraints pretendidos pelas migrations

| Estrutura | PK/FKs e nulabilidade relevante | Índices/unique | Delete/histórico |
|---|---|---|---|
| `users` | bigint padrão; `name/email/password` nasceram NOT NULL e a migration `2026_07_18_000001` pretende torná-los nullable | email unique continua, aceitando múltiplos NULL conforme MySQL | soft delete |
| `tenant_user` | `tenant_id` e `user_id` NOT NULL | unique `(tenant_id,user_id)` | cascade para tenant/user; incompatível com Member sem User |
| `associates` | `user_id` nasceu NOT NULL/cascade; migration `2026_07_18_000001` pretende nullable/nullOnDelete | `cpf_cnpj` global unique, não por tenant | soft delete; unique global pode impedir documento igual em tenants distintos |
| `service_providers` | `user_id` nullable/nullOnDelete; campos operacionais próprios | CPF nullable unique global | soft delete |
| `services` | ativo padrão nullable; tenant adicionado em migration transversal | código global unique no create original | soft delete; referências históricas usam cascades em alguns pontos |
| `service_provider_services` | prestador/serviço NOT NULL; tenant adicionado depois | unique pelo par, sem tenant na chave | cascade; tarifa sem vigência |
| `service_orders` | associado nasceu NOT NULL e depois nullable; serviço NOT NULL; ativo/operador/criador/aprovador nullable; prestador adicionado depois | número global unique | soft delete; serviço/associado originalmente cascade |
| `service_order_payments` | OS NOT NULL; conta/registrador nullable; tenant por migration posterior | sem operation key/unique de evento | OS cascade; sem soft delete/reversal uniforme |
| `provider_payment_requests` | prestador/OS NOT NULL; aprovador nullable | sem unique para pendência ativa | soft delete; cascade para prestador/OS |
| `service_order_additions` | tenant/OS NOT NULL; conta/despesa/criador nullable | sem chave idempotente por origem | OS/tenant cascade |
| `service_provider_works` | prestador NOT NULL; OS/associado nullable | sem unicidade operacional | soft delete; prestador cascade |
| `documents` | proprietário polimórfico; uploader nullable; tenant adicionado depois | índices polimórficos esperados; sem autorização física por tenant | soft delete |

Pontos transversais: Laravel usa bigint autoincrement e timestamps padrão nas criações; a maioria das FKs é física, porém não composta com `tenant_id`. Várias migrations transversais adicionaram tenant depois do desenho inicial, frequentemente nullable durante transição. As tabelas centrais não fixam `ENGINE=InnoDB` na migration; confirmar no `information_schema`. Antes de qualquer constraint futura, medir duplicidade global versus por tenant, órfãos e cascades reais.

### 16.8 Respostas objetivas às perguntas de decisão

1. **O que faz hoje?** Cadastra catálogo/prestador, habilita serviços, registra/inicia/finaliza OS, lança adicionais/despesas, recebe cliente, paga prestador, sincroniza arquivos e emite PDF/relatórios limitados.
2. **O que está em uso?** Portal atual, Resources de serviço/prestador/OS/pagamentos e trabalhos legados no admin são chamados pelo código/rotas; confirmar frequência em produção.
3. **O que é legado?** `ServiceProviderWork` é legado ativo; views antigas sem rota são candidatas a não usadas; campos/status/payment paths antigos coexistem.
4. **O que reutilizar?** Despesa, caixa, documentos/templates, sync remoto, snapshots/hash e padrões financeiros idempotentes dos módulos de recebimento/entrega.
5. **Onde há acoplamento a User?** FKs legadas, `TenantUser`, identity service, filtros `whereHas(user)`, relatórios/views, forms e sincronização global de roles.
6. **Member sem User é viável?** Sim, mas com Member operacional novo/aditivo, backfill e dual-read; apenas tornar FK nullable não resolve as suposições.
7. **Verdade operacional?** Execução validada e congelada, ligada à OS e ao snapshot do catálogo.
8. **Verdade financeira?** Obrigações imutáveis/versionadas derivadas da execução, uma para o cliente e outra para o prestador; pagamentos são eventos alocados.
9. **Como configurar sem caos?** Versões de catálogo, definições tipadas, snapshot JSON controlado e linhas declarativas; presets/defaults e UI progressiva.
10. **Como integrar demais domínios?** Por referências idempotentes a serviços genéricos existentes, sem duplicar arquivo, despesa, caixa ou estoque.
11. **Como manter portal simples?** Resolver identidade no servidor, aplicar template automaticamente, mostrar poucos campos condicionais e esconder contabilidade.
12. **Como fortalecer workspace?** Permissions granulares, fila de revisão/aprovação, actions servidas pelos mesmos casos de uso e visão reconciliada.
13. **Como preservar histórico?** Migrations aditivas, snapshots, adaptadores, leitura dual, reversões e nenhuma exclusão/regravação silenciosa.
14. **Qual sequência?** Contenção de segurança → identidade → catálogo/snapshot → obrigações/pagamentos → documentos/recursos/acordos → consolidação do legado.

## 17. Decisões arquiteturais propostas

1. `User` é login/ator; `Member` é identidade operacional por tenant; acesso é opcional.
2. `ServiceOrder` permanece compatível como solicitação; execução validada é o fato operacional congelado.
3. Obrigação versionada é a verdade financeira; pagamento, caixa e ledger são eventos/efeitos/projeções.
4. Catálogo é versionado e nunca recalcula história.
5. Campos configuráveis usam modelo híbrido controlado e validação server-side.
6. Composição é declarativa, tipada, auditável e sem código arbitrário.
7. Um único caso de uso transacional serve portal, admin, API e jobs.
8. Toda operação financeira é idempotente, bloqueada, reversível e pós-commit para efeitos externos.
9. Documento canônico é privado, autorizado e ligado ao snapshot/hash.
10. Legado é preservado por adaptador e reconciliação; retirada nunca é requisito para avançar.

## 18. Conclusão

O módulo tem peças reutilizáveis suficientes para uma evolução segura: tenant scoping do painel, despesas e caixa polimórficos, documentos/templates, armazenamento remoto, snapshots com hash e serviços financeiros maduros em outros domínios. O maior ganho não virá de reescrever tudo, mas de **estabelecer fronteiras e invariantes**, fechar primeiro as falhas cross-tenant/IDOR/documentos, unificar os writers financeiros e migrar de forma aditiva.

Até que essas garantias existam, ampliar catálogo, contratos ou renegociação sobre o fluxo atual aumentará a quantidade de estados impossíveis. A sequência recomendada é: contenção → identidade → snapshot operacional → obrigação financeira → integrações → consolidação do legado.
