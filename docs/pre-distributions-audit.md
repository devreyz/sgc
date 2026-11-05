# Planejamento e pré-distribuições: auditoria e implantação incremental

## Estado auditado em 05/11/2026

Laravel ^12.61.1, PHP ^8.2, Filament ^3.2, Spatie Permission (via Shield), Activitylog e DomPDF já instalados. As telas operacionais usam Blade (`layouts.bento`), componentes Blade e JavaScript; não é necessário introduzir Volt nem outra dependência.

### Relacionamentos e pontos de integração

- `tenants` -> `tenant_user` -> `users`. Papéis locais residem no JSON `tenant_user.roles`; `User::checkPermissionTo()` resolve permissões pelo tenant ativo. Papéis globais não substituem a associação local, exceto super_admin.
- `SalesProject` -> cliente principal, `customers` e `organizations` por pivôs. `ProjectDistributionCustomerService::assertAllowed()` é a regra existente de destinatários habilitados.
- `Product.unit` é a unidade oficial. Não há conversão implícita entre kg, un e maços.
- `ProductionDelivery` / `production_deliveries`: recepção com pai e cliente nulos; distribuição com `parent_delivery_id` apontando para uma recepção. Ambas pertencem a tenant/projeto/associado/produto.
- `AssociateReceipt` e `CustomerBillingReceipt`: vínculos atuais e listas históricas de distribuições; snapshots, identidades verificáveis e bloqueios de pagamento/emissão devem ser preservados.
- `DeliveryRegistrationController::distribute()` concentra validação de saldo, clientes, pedidos dos compradores, PricingService, AssociateProjectLimitService, ProjectFinancialCalculator, criação e notificações. O componente `components/delivery/dist-modal.blade.php` é reutilizado pelas telas do registrador.
- `ProductionDeliveryObserver` atualiza demandas e invalida autorizações/folhas quando necessário. Não deve observar tabelas operacionais novas.
- `DeliveryConferenceSheetService` exige distribuições oficiais existentes: não substitui as novas planilhas, que precisam existir antes das recepções.
- `DeliveryQuantityAdjustmentService`, `DeletedDistributionService`, `DeliveryProjectIntegrityService` e serviços de comprovantes/identidades são os pontos de correção oficial, não atalhos de conciliação operacional.

## Invariantes

1. Nenhuma planilha/item operacional pode criar ProductionDelivery, estoque, receitas, lançamentos, pagamentos ou comprovantes financeiros.
2. Quantidade informada nula é pendente; zero é confirmação de não entrega.
3. Todas as referências são validadas pelo tenant explícito e contexto autenticado. O escopo global não é suficiente.
4. A versão da planilha deve acompanhar toda escrita; requisições idempotentes precisam validar autor, operação e payload.
5. Conclusão exige todas as linhas não canceladas respondidas. Retificação e cancelamento são motivados e auditados.
6. Preço preliminar é apenas referência operacional e nunca substitui PricingService na futura criação oficial.
7. Consolidação é projeção dos originais, por tenant/projeto/cliente/produto/unidade. Nomes não são chaves.

## Riscos preexistentes, não corrigidos silenciosamente

- `BelongsToTenant` não restringe consultas se não houver tenant na sessão; serviços novos devem falhar fechados.
- `ProductionDeliveryPolicy` verifica permissões, mas não valida individualmente tenant do objeto; novas policies precisam fazê-lo.
- O cálculo inicial de saldo em `distribute()` soma todos os estados, mas a conferência sob bloqueio exclui rejected/cancelled. Há cálculos em float e tolerância explícita no fluxo atual. Uma mudança exige regressão dedicada.
- Verificações de aprovação antes do bloqueio e regras acopladas ao controller exigem cuidado na futura integração de snippets.
- Não reutilizar `FinancialDistributionService::processDelivery()` para conciliar: ele tem efeitos financeiros/estoque próprios e não é o fluxo de distribuição do portal.

## Modelo proposto

`pre_distribution_sheets` -> projetos autorizados (`pre_distribution_sheet_projects`) -> `pre_distribution_items`. Todas as tabelas operacionais têm tenant explícito. `pre_distribution_operations` armazena idempotência e resultado; `pre_distribution_events` preserva os estados anteriores/posteriores. Fases seguintes: vínculos quantitativos com distribuições oficiais e snapshots imutáveis de relações preliminares. Não reutilizar identidades/QR financeiros.

## Fases e critérios de liberação

1. Base isolada: migrations, models, autorização por tenant, operações transacionais/idempotentes, itens, conclusão, retificação, cancelamento e testes. Feature flag desligada por padrão.
2. Portal do entregador/gestão: formulários mobile, catálogo restrito aos projetos/rotas atribuídos, filtros/paginação, consolidação com origem e alertas de duplicidade.
3. PDF preliminar: snapshot imutável, revisão, texto de caráter preliminar obrigatório e permissões de exportação.
4. Snippets/conciliacão: integração no fluxo oficial único, locks em ordem consistente, saldos decimais revalidados, conciliação parcial e reversões que não permitam recriar quantidade oficial ainda ativa.
5. Regressão e concorrência real: testes em MySQL com conexões/processos independentes, revisão de desempenho/índices, testes de modais e homologação mobile.

Não habilitar o módulo como concluído antes de finalizar as fases. SQLite valida regras funcionais, mas não comprova concorrência de InnoDB.

## Implantação e reversão

- Backup e homologação antes de migrations; conferir versão real das tabelas referenciadas. Não executar migrations automaticamente nesta tarefa.
- Migrations novas apenas. Índices curtos e FKs sem cascade de exclusão. FKs compostas entre entidades operacionais impedem cruzamento de tenant; referências antigas também são verificadas no serviço.
- Papel `entregador` separado de registrador. Nunca conceder permissões de ProductionDelivery, preços ou financeiro a esse papel.
- Desligar `PRE_DISTRIBUTIONS_ENABLED` impede o uso sem apagar informações. Rollback de schema somente se ainda não houver dados: não destruir auditoria em produção.
- Testes devem cobrir nulo/zero, referências de outro tenant, acesso por ID, versão obsoleta, retries/mudança de payload, conclusão/retificação, agrupamento e ausência de efeitos financeiros.

## Estado de entrega

Este documento é um registro incremental. A presença do schema e serviços não significa que portal, PDFs e conciliação oficial estejam liberados. O relatório final da implementação deve indicar explicitamente quais fases foram realmente verificadas.

Implementação e validação desta entrega: [guia técnico e de implantação](pre-distributions-implementation.md). O módulo permanece desativado por padrão até homologação MySQL e mobile.
