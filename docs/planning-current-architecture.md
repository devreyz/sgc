# Auditoria e integração de planejamento

## Arquitetura encontrada

| Model / tabela | Responsabilidade |
| --- | --- |
| Tenant / tenants | Organização isolada por tenant_id, sessão e middleware de tenant |
| SalesProject / sales_projects | Projeto, clientes, produtores, preferências de relatório |
| ProductionDelivery / production_deliveries | Recepção e distribuição: a distribuição é uma filha via parent_delivery_id |
| Product / products | Cadastro único; já possui perishable e shelf_life_days |
| Associate / associates | Produtor/membro, com identidade resolvida por TenantIdentityService |
| Customer / customers | Cliente/unidade recebedora, hierarquia e organização |
| AssociateReceipt / associate_receipts | Comprovante do produtor |
| CustomerBillingReceipt / customer_billing_receipts | Faturamento do cliente |
| StockMovement / stock_movements | Movimentação de estoque existente |

As migrations relevantes criam sales_projects, production_deliveries e products (2024), acrescentam parent_delivery_id (2026-04-30), faturamento (2026-05-27), comprovantes e preferências (2026-08-15). Não existe tabela independente de distribuições.

## Pontos de extensão

- DeliveryRegistrationController: aprovação, distribuição, comprovantes e Centro de Integridade.
- DeliveryReportService + DeliveryReportController: filtros, preferências e exportação operacional.
- DeliveryOperationalReportExport, Maatwebsite Excel e TemplatedPdfService: infraestrutura de exportação existente.
- DeliveryProjectIntegrityService: pendências operacionais; verificações utilizadas por comprovantes precisam continuar independentes de planejamento.
- ProjectFinancialCalculator, DistributionBillingService e serviços Accounting: verdade financeira/fiscal preservada.
- delivery/project-deliveries e delivery/partials/report-export-modal: interface existente.
- BelongsToTenant e LogsActivity: escopo e auditoria. O escopo global é condicional à sessão; serviços novos devem filtrar tenant explicitamente.
- Acesso do portal: auth + any.role:registrador_entregas, dentro das rotas do tenant.
- Testes PHPUnit com SQLite em memória e schemas mínimos por teste. BCMath já é usado pelo financeiro.

## Proposta adaptada

Reutilizar produtos, durabilidade, projetos, distribuições, autorização do portal, logs e exportadores. Estender por relações auxiliares: grupos/população histórica, configurações por projeto/produto, perfil de consumo 1:1, público diário, conjuntos de regras imutáveis e snapshots de relatórios emitidos.

A primeira entrega segue a V1 prioritária do plano: público, cobertura, durabilidade, pessoa-dia, interseção temporal, consumo, regras e conformidade. Nutrição, receitas, cardápios, FEFO e planejamento automático são fases posteriores, expressamente descritas como tais no documento.

## Riscos e decisões

- Nunca salvar ProductionDelivery para atualizar apenas consumo: seus eventos executam cálculos financeiros.
- Não preencher público histórico com população de referência. Ausência de perfil permanece não calculável.
- Não converter unidades sem fator explícito. shelf_life_days não é cobertura.
- Regras novas não entram nos bloqueios operacionais/financeiros dos comprovantes.
- Relatórios padrão seguem o caminho existente quando analysis_mode não é informado.
- Migrações aditivas, reversíveis; nenhuma migração automática no banco de trabalho.
- Datas são dias civis inclusivos; quantidades calculadas com BCMath, arredondadas apenas na apresentação.
