# Matriz de integrações do módulo de Serviços

> Estado observado no código em 09/09/2026. “Integração” significa que há caminho de leitura/escrita real, não apenas uma relação declarada no model.

## 1. Matriz funcional

| Capacidade | Artefatos atuais | Integração atual | Lacuna/risco | Direção recomendada |
|---|---|---|---|---|
| Identidade e acesso | `users`, `tenant_user`, Spatie Roles | Associado/prestador localizados por `user_id`; papéis globais e do tenant | Operação depende de login; papel global atravessa tenant no portal | `Member` operacional durável, acesso opcional e papéis estritamente tenant-scoped |
| Catálogo | `services` | Tipo, unidade, preços simples, ativo/inativo | Sem versão, requisitos, composição ou evidências | Versão publicável do serviço e snapshot por execução |
| Habilitação de prestador | `service_provider_services` | Ativa serviço e sobrescreve tarifas | Tarifas também existem no serviço e prestador | Uma política de preço com precedência explícita e versão |
| Ordem/execução | `service_orders` | Solicita, agenda, inicia, mede, finaliza e guarda totais | Estado e responsabilidades sobrecarregados; concorrência | Manter OS como contêiner e consolidar fato de execução validado/imutável |
| Campos configuráveis | Nenhum mecanismo específico | Campos fixos e notas livres | Não atende variação por serviço; notas não são reportáveis | Híbrido: definições tipadas + JSON congelado; promover campos críticos |
| Recursos/insumos | `assets`, `expenses`; estoque separado | Ativo é opcional; combustível e despesa são dados soltos | Sem reserva/consumo de estoque, equipe, equipamento múltiplo ou regra | Linhas de recurso declarativas e adaptadores para estoque/ativos/despesas |
| Composição financeira | `service_order_additions` | Taxa/desconto alteram total; despesa cria `Expense` | Sem contribuição, retenção, reembolso, origem/versionamento | Linhas de composição tipadas, auditáveis e somadas por efeito |
| Recebível do cliente | `final_price`, pagamentos, razão associado | Vários caminhos atualizam estados e razão | Não existe obrigação imutável; sem alocação formal | Obrigação gerada uma vez da execução validada; pagamentos alocados a ela |
| Pagável ao prestador | `provider_payment`, pagamentos, solicitações, razão | Pagamento e pedido de saque parcialmente ligados | Sinais de razão contraditórios; pedido pode duplicar; aprovação em massa | Obrigação separada do prestador, saldo derivado e workflow bloqueado/idempotente |
| Caixa | `cash_movements`, contas bancárias | Algumas ações criam movimento | Outros caminhos não criam; sem vínculo único/reversão | Reusar serviço financeiro robusto com chave de operação e estorno encadeado |
| Despesas | `expenses` polimórficas | Adicional `expense` cria despesa | Reexecução duplica; obrigação e despesa podem divergir | Origem única por linha de composição e chave idempotente |
| Parcelamento | `service_order_payments` pendentes | “Pagamento” também representa parcela futura | Agenda de recebível se confunde com evento de pagamento | Separar plano/parcela da liquidação e sua alocação |
| Renegociação | Não há domínio de serviços | Alteração manual de valores/parcelas | Pode reescrever história | Acordo versionado que referencia obrigações sem apagá-las |
| Documentos | `receipt_path`, `Document`, `GeneratedDocument`, templates | Recibos em path; PDF de OS sob demanda; sync remoto | Fragmentação, ausência de autorização por anexo e exposição pública | `Document`/gerados como metadado canônico; storage privado e download autorizado |
| Contratos/assinaturas | Templates e documento gerado genéricos | Infraestrutura existe fora do fluxo principal | OS não congela contrato/termos nem liga assinatura à versão | Documento gerado a partir do snapshot; assinatura referencia hash/versão |
| Notificações | Observer ao faturar razão | Notificação específica em transição `BILLED` | Fluxo principal geralmente não passa por esse estado | Eventos de domínio pós-commit e notificações idempotentes por evento |
| Relatórios | Relatório de pagamento, extrato por trabalhos/razão | Consulta tabelas e estados diferentes | Margem simplista; exclui estados; null de usuário quebra leitura | Read models reconciliados de obrigação, pagamento, caixa, despesa e execução |
| Auditoria | Activity Log em parte dos models | Registra mudanças gerais | Não substitui versionamento, motivo, aprovação e reversão | Evento operacional/financeiro explícito + ator, tenant, versão e correlação |
| APIs/eventos | Rotas web e observers/jobs | Portal e admin escrevem diretamente | Sem application service único; efeitos divergentes | Casos de uso transacionais únicos usados por todas as interfaces |

### 1.1 Matriz de decisão reutilizar/refatorar/criar

| Capacidade | Existe | Entidade atual | Qualidade | Reutilizar | Refatorar/evoluir | Criar novo no futuro |
|---|---:|---|---|---:|---:|---:|
| catálogo de serviços | Sim | `Service` | Básica | Sim | Sim | Versão/configuração |
| prestador | Sim | `ServiceProvider` | Útil, acoplado ao login/papel | Sim | Sim | `Member` operacional |
| execução | Parcial | `ServiceOrder`, `ServiceProviderWork` | Sobreposta e mutável | Adaptador histórico | Sim | Fato/snapshot validado |
| custom fields | Não no domínio | Notas/campos fixos | Insuficiente | Não | Não | Definições tipadas + valores snapshot |
| evidências | Parcial | `receipt_path`, `Document` | Fragmentada | `Document` | Sim | Requisitos versionados, não nova tabela de arquivo |
| documentos | Sim | `Document`, `GeneratedDocument`, `DocumentTemplate`, `CloudDocument` | Boa base, mal integrada | Sim | Sim | Apenas vínculos/regras ausentes |
| composição | Parcial | `ServiceOrderAddition` | Limitada | Conceito/legado | Sim | Linhas versionadas completas |
| cobrança/obrigação | Parcial | Totais da OS, ledgers | Sem fonte única | Dados históricos | Sim | Obrigação explícita ou generalização existente |
| pagamento parcial | Sim | `ServiceOrderPayment` | Conceitos misturados | Histórico/UI | Sim | Alocação/plano separados, se não generalizáveis |
| despesas | Sim | `Expense` | Reutilizável | Sim | Integração idempotente | Não duplicar |
| contrato | Parcial | Templates/gerados | Infra genérica útil | Sim | Sim | Condições versionadas/vínculo à execução |
| negociação | Não | Parcelas simples | Insuficiente | Infra documental | Não | Acordo que referencia obrigações |
| prestação mensal | Parcial | Relatórios/PDF/templates | Não reconciliada | Renderização | Sim | Read model derivado |
| prestação anual | Parcial | Mesmas bases | Não reconciliada | Renderização | Sim | Consolidação do read model mensal |

## 2. Matriz de segurança e isolamento

| Superfície | Controle presente | Falha observada | Severidade |
|---|---|---|---|
| Tenant por slug no portal | Resolve slug e grava sessão | Não confirma membership do usuário no tenant | **CRÍTICA** |
| Middleware de papel | Aceita papel global ou papel do tenant | Papel global operacional pode autorizar outro tenant | **CRÍTICA** |
| Autocriação de prestador | Busca por `(tenant,user)` | GET cria cadastro quando papel existe; combinado ao item anterior atravessa tenants | **CRÍTICA** |
| Criação de OS no portal | Serviço é relido com tenant | `associate_id` e `asset_id` usam `exists` global, sem igualdade de tenant | **ALTA** |
| Ações da OS no portal | Ordem filtrada por tenant e prestador | Bom padrão de propriedade da OS; entidades aninhadas ainda precisam escopo | MÉDIA |
| Faturar pagamento no admin | Opções visuais são da OS | Backend usa `ServiceOrderPayment::findOrFail(id)` sem exigir pertencimento à OS/tenant/tipo/status | **CRÍTICA** |
| Models tenant-scoped | Global scope por `session('tenant_id')` | Sem sessão, scope não protege; FKs não são compostas por tenant | ALTA |
| Policies do admin | Permissões Shield | Predominantemente capability-only, sem checagem record-level de tenant | ALTA (defesa depende do query scope) |
| Anexos | Paths no disco público e rota `/storage/{path}` | Download não exige autenticação, tenant ou propriedade | **CRÍTICA** para recibos/dados pessoais |
| Uploads | Validação de tipo/tamanho em partes do fluxo | Armazenamento público e path solto; ausência de objeto de autorização | ALTA |
| Exclusão de tenant | FKs com cascade em tabelas históricas | Pode conflitar com retenção/auditoria financeira | ALTA |

## 3. Classificação de cada dependência de `User`

| Classe | Dependência encontrada | Classificação | Tratamento |
|---|---|---|---|
| A | autenticação, `created_by`, `approved_by`, `registered_by`, `uploaded_by` | Dependência legítima | Manter `User` como ator de acesso/auditoria, permitindo ator sistema quando aplicável |
| A | notificações destinadas a quem possui login | Dependência legítima | Manter, mas resolver destinatário por acesso ativo do `Member` |
| B | `associates.user_id`, `service_providers.user_id` | Deve se tornar opcional | Migrar referência operacional para `member_id`; preservar `user_id` durante transição |
| C | nome/identidade via `TenantIdentityService` e `TenantUser` | Semântica de membro disfarçada | Criar identidade operacional própria; `TenantUser` volta a ser somente membership |
| C | papéis globais sincronizados pelo observer do prestador | Semântica de acesso mal posicionada | Remover sincronização global somente após política tenant-scoped e backfill validados |
| D | `whereHas('user', status)` ao listar associados | Suposição perigosa | Filtrar estado operacional do membro, não existência de login |
| D | accessors `associate`/`serviceProvider` de `TenantUser` por `(tenant,user)` | Suposição perigosa | Resolver relações explícitas com `Member` e suportar zero/mais papéis operacionais |
| D | relatórios/views que acessam `associate.user` | Suposição perigosa | Usar nome snapshot/membro, com fallback legado durante migração |
| E | novo vínculo `member_id` e restrições de tenant | Alteração de schema necessária | Adicionar nullable, backfill auditado, índices/FKs, só então endurecer |
| F | forms de associado/membro exigindo email/senha/user | Alteração de formulário necessária | Separar “cadastrar pessoa” de “conceder acesso” |

## 4. Contrato futuro mínimo entre domínios

```text
Catálogo versionado
  -> snapshot de definição + política de preço
  -> execução validada (fato operacional)
  -> linhas de composição congeladas
      -> obrigação do cliente
      -> obrigação do prestador
      -> despesas/recursos/estoque, quando aplicável
  -> plano de liquidação/renegociação
  -> evento de pagamento
      -> alocação na obrigação
      -> movimento de caixa
      -> razão/read model
      -> documento e notificação pós-commit
```

Regras do contrato:

1. Toda escrita recebe `tenant_id` do contexto autenticado, nunca do payload.
2. Toda referência aninhada é recarregada por tenant e, quando aplicável, pelo proprietário.
3. Toda operação financeira tem chave idempotente única por tenant.
4. Toda transição sensível bloqueia a obrigação/ordem e valida a versão esperada.
5. Pagamento não altera a obrigação original; cria evento e alocação. Cancelamento cria reversão.
6. Catálogo posterior não recalcula execução já aceita.
7. Documento sensível é privado e baixado por endpoint autorizado.
8. Todas as interfaces chamam o mesmo caso de uso transacional.
