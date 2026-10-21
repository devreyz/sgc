# V2 — Nutrição, distribuição e isolamento por cliente

## Entrega

V2 integrada ao planejamento existente: nutrientes, composições versionadas por produto, massa comestível, estimativa nutricional por período, metas por público e cliente, PDF/XLSX e pendências de nutrição no Centro de Integridade. Receitas e cardápios foram implementados na [V3](planning-v3-implementation.md).

Nenhum dado de tabela alimentar ou limite normativo foi inventado ou importado automaticamente. Composições exigem fonte, versão da fonte, vigência, quantidade de referência e fração comestível. Metas exigem origem e referência.

## Fórmulas e público

```text
massa_comestível_g = massa_bruta_atribuída_ao_período_g × fração_comestível
nutriente_total = massa_comestível_g × composição_do_nutriente / referência_g
nutriente_por_pessoa_dia = nutriente_total / pessoa_dias_do_público
```

A referência é cadastrada, sem pressupor 100 g. A precisão interna é BCMath com 16 casas; apresentação em seis casas para nutrição. As unidades dos nutrientes são imutáveis para evitar reinterpretar valores históricos.

O relatório de consumo da V1 mantém sua semântica. A consolidação **nutricional** soma os nutrientes dos alimentos, mas considera o público uma única vez por cliente, grupo e dia. Exemplo: dois alimentos para o mesmo grupo de 500 pessoas por sete dias representam 3.500 pessoa-dias de público, não 7.000. Contagens conflitantes no mesmo dia, ou várias distribuições sem grupo identificado, tornam o denominador consolidado não calculável. Um único ciclo sem grupo ainda pode ter sua taxa calculada.

Nutrientes ausentes não são zero. O relatório mostra quantidade conhecida, quantidade de ciclos sem composição e taxa não calculável quando a composição é incompleta. Metas não podem resultar em conformidade sem os dados necessários. Filtros de produto/produtor impedem avaliar uma meta para o fornecimento completo; competência física também não é usada para validar metas por período de consumo.

## Distribuição e verdade financeira

A distribuição filha em `production_deliveries` continua sendo a execução física e a origem financeira. Nutrição não salva a distribuição nem modifica quantidade, preço, taxa, líquido, pagamento ou documento fiscal.

Revisões implementadas:

- Consumo/nutrição exigem distribuição e entrega-pai aprovadas, no mesmo tenant/projeto, com produto e produtor compatíveis. Produto e destino precisam pertencer ao tenant. Registros incompatíveis ficam não calculáveis.
- O perfil de atendimento registra `customer_id_snapshot` e `product_id_snapshot`. Se o destino/produto mudar, o atendimento anterior não é usado silenciosamente para o novo contexto; o operador deve confirmá-lo novamente.
- Para perfis legados sem esses campos, o evento de atualização da distribuição preserva o contexto anterior antes da primeira troca de destino/produto. Nenhum backfill presumiu o destino histórico de perfis antigos. Divergências já detectáveis pelo grupo também são recusadas. Mudanças diretas por SQL não executam eventos de models; isso não substitui a auditoria de dados legados.
- A seleção para faturamento (`CustomerBillingSelectionService`) passou a exigir também aprovação, produto e produtor compatíveis na entrega-pai e destinatário existente no mesmo tenant. A validação final dos documentos financeiros continua usando os serviços existentes.
- Reservas em faturamento e separação de comprovante do produtor versus cobrança do cliente permanecem ativas. Alertas nutricionais não bloqueiam comprovantes, cobrança, pagamentos ou fiscal.

Teste com o consolidador financeiro real: distribuição física de 350 kg a R$ 5,00/kg e taxa do cliente de 10% conserva **R$ 1.750,00 brutos, R$ 175,00 de taxa e R$ 1.575,00 líquidos**. O consumo atribuído a outubro pode ser 250 kg, sem substituir os 350 kg na cobrança.

## Isolamento

- Todas as consultas novas filtram tenant explicitamente. Grupos/metas também filtram projeto.
- A consolidação nutricional e a seleção das metas usam a chave cliente + grupo, independentemente do agrupamento visual do relatório.
- Um grupo vinculado ao cliente A não pode receber meta ou atendimento do cliente B.
- Filtros de cliente, produto, produtor e grupo validam seus identificadores no tenant/projeto correspondente. A interseção de filtros nunca amplia os destinatários.
- O seletor de clientes do planejamento mostra clientes habilitados no projeto e seus destinatários históricos; não lista clientes de outros projetos sem vínculo.
- Snapshot guarda o contexto de tenant/projeto e a versão correta do motor (2 em nutrição). Serviço de emissão rejeita salvar um resultado sob outro contexto.
- O catálogo de composição de produtos é compartilhado **dentro do tenant**, pois o produto mantém identidade única. Dados de atendimento e metas são separados por projeto/cliente/grupo.
- As rotas continuam exclusivas do portal autenticado de registradores, com o controle de papéis e tenant já existente. Não foram adicionadas rotas de nutrição ao portal de compradores. Um registrador autorizado da organização continua podendo trabalhar com os vários clientes dos projetos dessa organização; não foi criado um novo papel por cliente.

## Arquivos principais

- Migration aditiva: `database/migrations/2026_10_18_000002_create_project_nutrition_tables.php`. Cria `nutrients`, `food_profiles`, `food_nutrient_values`, `nutrition_target_sets`, `nutrition_targets` e dois campos de contexto no perfil de consumo. Rollback da V2 conserva tabelas da V1 e financeiras.
- Models: `Nutrient`, `FoodProfile`, `FoodNutrientValue`, `NutritionTargetSet`, `NutritionTarget`; imutabilidade integrada a `PlanningModel`. Relação `Product::foodProfiles`.
- Domínio: `NutritionCatalogService`, `NutritionCalculator`, `NutritionAudienceCalculator`, `NutritionReportService`, `DistributionPlanningContext`, em `app/Services/Planning`.
- Integrações: `ProjectPlanningService`, `ConsumptionReportService`, `PlanningIntegrityService`, `ProductionDelivery`, `CustomerBillingSelectionService`.
- HTTP: `ProjectPlanningController`, `DeliveryReportController`, `routes/web.php`. Novas ações POST no planejamento: `nutrients`, `food-profiles`, `nutrition-targets`. Mesmo endpoint de exportação aceita `analysis_mode=nutrition`.
- UI: `delivery/partials/nutrition-planning.blade.php`, inclusão na tela `project-planning`, filtros de cliente/grupo e modo nutricional no modal existente.
- Relatórios: `pdf/partials/nutrition-report.blade.php` e extensão do PDF existente; XLSX acrescenta abas Nutrição, Metas nutricionais e Fontes nutricionais.
- Testes: `NutritionCalculatorTest`, `NutritionPlanningTest`, `BillingDistributionIsolationTest`, além da regressão existente de planejamento, relatórios, financeiro e comprovantes.

## Ativação e roteiro manual

As migrations não foram executadas no banco de trabalho. Em homologação, aplicar V1 e V2 na ordem normal:

```powershell
php artisan migrate
```

1. Abrir Entregas do projeto → Planejamento → Nutrição.
2. Cadastrar um nutriente com sua unidade.
3. Criar composição do produto com referência, fração comestível, fonte/versão e vigência. Incluir todos os nutrientes conhecidos; informar zero apenas quando a fonte declarar zero.
4. Registrar grupo, cliente e público efetivamente atendido nas distribuições.
5. Gerar prévia com análise Nutrição, competência Consumo e filtros de período/cliente/grupo.
6. Cadastrar conjunto completo de metas para esse grupo/cliente e vigência. Conferir conformidade e pendências de composição/público.
7. Exportar PDF/XLSX e consultar o snapshot emitido. Criar outra versão de composição/metas e confirmar que o snapshot antigo permanece igual.
8. Comparar com a cobrança física; valores nutricionais rateados não devem substituir quantidades financeiras/fiscais.
9. Em ambiente de teste, alterar o cliente de uma distribuição ainda editável. O atendimento anterior precisa aparecer como contexto alterado até ser explicitamente confirmado para o novo destino.

## Verificação

Os testes cobrem fórmulas, referência não padronizada, público compartilhado entre produtos, público conflitante, lacunas, unidade, versões, snapshots, cliente/tenant/grupo, mudanças de destino, cobrança com taxa real, renderização da tela, PDF/XLSX e rollback.

- Validação ampliada: **82 testes, 341 assertions, todos aprovados**, incluindo V1, V2, isolamento de faturamento, relatórios operacionais, comprovantes, financeiro e snapshots de múltiplos projetos.
- Suíte completa executada: **532 testes, 2.704 assertions, 36 falhas, quatro erros e 12 skipped**.
- Os 40 casos com falha/erro foram comparados pelo nome de classe e método com o XML da linha de base anterior: **correspondência exata, nenhum caso novo com falha**. Permanecem concentrados em AccountingPortalSecurityTest, CustomerBillingSelectionServiceTest e PdfRenderingCompatibilityTest. Nenhuma falha foi escondida por alteração de expectativa.
- Sintaxe validada para 44 arquivos PHP e os cinco templates envolvidos na V2. A tela nutricional foi renderizada em teste, e as exportações PDF/XLSX foram geradas.

Limites: a migration foi testada em SQLite em memória; ainda requer homologação no MySQL. Não foi feita emissão fiscal real nem inspeção visual no navegador. Configurações alimentares/metas são informadas pelo operador; o cálculo não equivale a aprovação técnica de cardápio.
