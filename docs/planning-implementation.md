# Planejamento e consumo — entrega V1

> A V2 foi implementada posteriormente. Consulte [planning-v2-implementation.md](planning-v2-implementation.md) para nutrição e revisão de distribuição, verdade financeira e isolamento por cliente. Os números de testes abaixo registram a entrega original da V1.

## Escopo entregue

Implementação da V1 prioritária do plano fornecido. Nutrição, receitas, cardápios, planejamento automático e FEFO continuam nas fases posteriores descritas nesse plano. Nenhuma meta legal/nutricional foi presumida.

Arquitetura auditada antes da implementação: [planning-current-architecture.md](planning-current-architecture.md).

| Recurso | Implementação |
| --- | --- |
| Grupos beneficiários | Cadastro por projeto/tenant, vínculo opcional com unidade autorizada no projeto |
| População histórica | Fonte, vigência, fechamento do período anterior e rejeição de sobreposição |
| Cobertura e durabilidade | Padrão do projeto; configuração do produto por projeto/vigência; substituição explícita na distribuição |
| Validade | Reutiliza expiry_date do StockMovement da recepção; depois, validade manual; por último, estimativa desde a data física pela durabilidade. Registra a origem e valida tenant/produto |
| Público | Pessoas atendidas, atendimentos separados, pessoa-dias diretos, atendimento diário; pessoas únicas não inferidas |
| Consumo | BCMath, datas inclusivas, ciclos com interseção, rateio uniforme, consolidação ponderada |
| Unidades | kg/g ou fator explícito em gramas/unidade; ausência de conversão é não calculável |
| Critérios | Conjuntos completos em versões imutáveis; origem, vigência, operador, limites, escopo e severidade |
| Conformidade | Critérios por ciclo completo ou período; alerta ou bloqueio exclusivo da emissão do relatório |
| Relatórios | Mesmos endpoint/modal e pacotes PDF/XLSX; agrupamento por produto, unidade ou produtor; detalhamento dos ciclos |
| Histórico | Snapshot imutável de cada emissão com filtros, parâmetros, população, regras, versões e distribuições/comprovantes de origem |
| Integridade | Pendências no Centro existente, domínio planning, acesso à distribuição correspondente |
| Compatibilidade | Relatórios padrão seguem o serviço existente; perfis opcionais; comprovantes e serviços financeiro/fiscal preservados |

## Arquivos e responsabilidades

- Migration: `database/migrations/2026_10_18_000001_create_project_planning_tables.php`. Nove tabelas auxiliares, FKs, índices e rollback em ordem inversa. Nenhuma coluna legada alterada.
- Models novos: `PlanningModel`, `BeneficiaryGroup`, `BeneficiaryPopulationPeriod`, `ProjectPlanningSetting`, `ProductPlanningProfile`, `PlanningRuleSet`, `PlanningRule`, `DistributionConsumptionProfile`, `DistributionDailyAudience`, `PlanningReportSnapshot`.
- Relações adicionadas: `SalesProject::beneficiaryGroups/planningRuleSets`, `Product::planningProfiles`, `ProductionDelivery::consumptionProfile`. Perfis têm público diário e grupo; conjuntos têm regras.
- `app/Services/Planning/ProjectPlanningService.php`: validação autoritativa no backend, referências do mesmo tenant/projeto, transações e locks. Não há FormRequest duplicado; os mesmos validadores atendem ao portal e ao salvamento opcional no modal de distribuição.
- `ConsumptionCalculator.php`: motor independente de HTTP/view; pessoa-dia, interseção e consolidação.
- `PlanningRuleEvaluator.php`: comparação decimal sem executar expressões fornecidas pelo usuário.
- `ConsumptionReportService.php`: consulta por competência, filtros, agrupamento, seleção temporal de regras, rastreabilidade e snapshots.
- `PlanningIntegrityService.php`: acrescenta pendências apenas à consulta do Centro; não modifica o inspetor utilizado pelo fluxo de comprovantes.
- `app/Http/Controllers/Delivery/ProjectPlanningController.php`: tela, cadastros e leitura de snapshots; `DeliveryReportController` estendido com análise opcional; `DeliveryRegistrationController` grava atendimento opcional na mesma transação da distribuição.
- Rotas em `routes/web.php`: GET `/{tenant}/delivery/projects/{project}/planning`, POST `/planning/{action}` (settings, groups, population, products, rules, consumption), GET `/planning/snapshots/{snapshot}`. Exportação continua em `/reports/export`.
- UI: `resources/views/delivery/project-planning.blade.php`, acesso em `project-deliveries.blade.php`, campos recolhíveis em `components/delivery/dist-modal.blade.php`, opções de análise/competência no modal de relatório existente.
- Exportação: `app/Exports/ConsumptionReportExport.php` (Resumo, Ciclos, Agrupamento, Conformidade, Metodologia), `resources/views/pdf/consumption-report.blade.php`. Valores textuais no XLSX previnem fórmulas a partir de nomes cadastrados.
- Fixture local opcional: `database/seeders/PlanningOctoberDemoSeeder.php`, fora do DatabaseSeeder.
- Testes: `tests/Unit/ConsumptionCalculatorTest.php`, `tests/Feature/ProjectPlanningTest.php`; fixture compartilhada em `tests/Concerns/BuildsDeliveryReportSchema.php`. Os testes operacionais existentes mantêm as mesmas verificações, agora reutilizando essa fixture.

## Autorização e auditoria

O portal mantém `auth` + `any.role:registrador_entregas` e os middlewares externos de tenant existentes. Não foram criadas permissões com nomes diferentes do padrão atual. Gerenciar critérios usa a mesma autorização atual do portal; uma separação adicional de papéis pode ser uma evolução posterior.

Serviços exigem tenant ativo igual ao projeto e filtram explicitamente o contexto. Distribuições devem ser filhas do mesmo projeto. Grupos devem pertencer ao projeto e, quando vinculados a uma unidade, coincidir com o destino da distribuição. Identificadores de outro contexto são rejeitados. `tenant_id`, usuários de auditoria e versão não vêm do formulário.

Spatie Activitylog registra alterações e o responsável. A substituição do público diário também registra valores anteriores/novos. Regras, conjuntos e snapshots rejeitam alterações/exclusões via model; novas regras são emitidas como nova versão.

## Semântica dos resultados

- `calculated`, `partial`, `not_calculable`, `not_applicable` distinguem ausência de dados de zero.
- Totais de consumo incluem somente numerador/denominador dos mesmos ciclos calculáveis. Quantidade física permanece visível nas linhas incompletas.
- Atendimento diário exige registro para cada dia da interseção, inclusive dias com zero; lacunas não são interpretadas como ausência de público.
- Pessoa-dias diretos são rateados uniformemente, assim como a quantidade, quando o relatório corta um ciclo.
- Critérios de ciclo são aplicados ao ciclo completo e à versão vigente no início desse ciclo. Critérios do período precisam de vigência cobrindo o intervalo inteiro. Uma prévia pode mudar ao se cadastrar uma versão retroativa; uma emissão antiga permanece disponível no snapshot original.
- Cobertura maior que durabilidade e consumo após validade geram pendências. Não alteram automaticamente quantidades, comprovantes ou pagamentos.
- Bloqueio de relatório é explícito e ocorre somente em critérios avaliáveis descumpridos com política `report_block`. Dados incompletos são mostrados como pendências, sem bloqueio financeiro.
- A competência física no novo relatório usa a data da distribuição. A competência de consumo inclui ciclos iniciados fora do intervalo. O relatório operacional padrão conserva sua semântica anterior.
- Quantidades calculadas usam BCMath com 16 casas internas; apresentação em quatro casas. Não há arredondamento monetário na fórmula.

## Ativação

As migrations **não foram aplicadas ao banco de trabalho**. Os testes criam e removem tabelas somente no SQLite em memória.

No ambiente de homologação, aplicar a migration antes de acessar os novos cadastros:

```powershell
php artisan migrate
```

O rollback dessa migration remove apenas as nove tabelas novas. Como qualquer rollback de tabela, também remove os dados novos nelas registrados; não é um mecanismo de desfazer emissões individuais.

Entrar no portal de entregas, abrir um projeto e selecionar **Planejamento, atendimento e consumo**. A seção opcional no modal de distribuição também permite informar pessoas e cobertura no momento do registro. Distribuições antigas continuam sem perfil até informação explícita do operador.

## Verificação manual

1. Cadastrar grupo com população de referência 3.000, fonte e vigência.
2. Configurar cobertura de sete dias para o projeto/produto.
3. Registrar distribuição A: 29/09/2026, 350 kg, 500 pessoas, sete dias.
4. Registrar distribuição B: 06/10/2026, 360 kg, 520 pessoas, sete dias.
5. Consultar 01/10 a 31/10, competência consumo: A = 250 kg/2.500 pessoa-dias; B = 360 kg/3.640 pessoa-dias; total = 610 kg/6.140 pessoa-dias; consumo ≈ 99,3485 g/pessoa/dia.
6. Alternar para distribuição: A fica fora do período, B permanece com 360 kg. Exportar PDF/XLSX e consultar o snapshot emitido.
7. Criar critério interno com limite e origem explícitos. Emitir relatório, cadastrar nova versão e confirmar que o snapshot anterior conserva a regra original.
8. Informar durabilidade de quatro dias e cobertura de sete: consumo continua calculável e aparece a pendência de durabilidade.
9. Testar produto em pés sem fator: quantidade física aparece e consumo fica não calculável. Configurar fator documentado e salvar novamente o atendimento para registrar o novo parâmetro.
10. Conferir relatório operacional e comprovantes existentes, antes/depois, sem mudar valores financeiros.

Fixture opcional, somente com APP_ENV local/testing:

```powershell
php artisan db:seed --class=PlanningOctoberDemoSeeder
```

Ela solicita IDs de organização, produtor, unidade e produto **já precificado para essa unidade**, cria um projeto separado e os dois ciclos do exemplo. Não altera a população atendida de distribuições existentes. Não foi executada no banco de trabalho.

## Validação executada e limites

- Testes direcionados: **30 testes, 116 assertions, todos aprovados**, abrangendo domínio, persistência, isolamento, população histórica, regras, snapshots, renderização da tela, validade do estoque, PDF/XLSX, rollback e regressão operacional.
- Suíte completa no momento da execução: **504 testes, 2.607 assertions; 36 falhas, quatro erros, 12 skipped**. As falhas/erros ficaram em AccountingPortalSecurityTest, CustomerBillingSelectionServiceTest e PdfRenderingCompatibilityTest.
- Comparação de controle: os cinco arquivos PHP legados alterados foram carregados a partir de `git show HEAD` em um processo separado. Os 94 testes dessas três classes reproduziram **as mesmas 36 falhas e quatro erros**. Exemplos: fixture contábil sem report_annotations, fixture de seleção sem products, expectativas contábeis divergentes e contagem de páginas de PDF. Não foram corrigidos módulos não relacionados.
- PHP novo/alterado e os cinco templates envolvidos passaram na verificação de sintaxe. Há templates antigos de outros módulos com problemas de compilação; não foram alterados.
- Neste sandbox Windows, `is_readable/is_writable` do PHP retornam false para arquivos que podem ser lidos/escritos normalmente. PHPUnit foi iniciado com autoload previamente carregado e configuração temporária sem bootstrap. Pint interrompeu pela mesma verificação de escrita; não houve mudança de dependências ou permissões para contornar isso.
- A migration foi testada em SQLite, incluindo rollback. Ainda requer homologação no MySQL real. Não foi feita inspeção visual em navegador ou emissão fiscal real.
- Nutrição/quantidade comestível, receitas/cardápios, beneficiários nominais, consumo diário real, fator de cobertura global do produto e planejamento automático não fazem parte desta V1.
