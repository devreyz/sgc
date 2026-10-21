# V3 — Receitas, cardápios e demanda

A V3 implementa os sete critérios da parte 72 do plano: receitas versionadas, cardápios, composição por refeição, análise diária/semanal, demanda por ingrediente, comparação com distribuição física e sugestões de entrega conforme durabilidade. Reutiliza produtos, clientes, exportações e Centro de Integridade existentes.

## Uso

No planejamento do projeto, abra **Receitas e cardápios**. Cadastre uma receita informando ingredientes em gramas brutas, rendimento em porções, peso declarado da porção e fonte. Alterações criam outra versão; cardápios existentes continuam apontando para a versão original. O catálogo de receitas é compartilhado dentro da organização.

Crie um cardápio com cliente, grupo, código e período. Adicione refeições por data, declarando público e produtos em gramas brutas por pessoa ou receitas em porções por pessoa. O período deve estar dentro do projeto e pode cobrir até 366 dias. Salvar uma refeição substitui sua composição inteira. Uma revisão concorrente é recusada até atualizar a página.

O fluxo é **Planejado → Calculado → Aguardando validação → Aprovado → Em execução → Finalizado**. Calcular preserva um snapshot; enviar à validação exige composição completa e público diário não ambíguo. Antes da aprovação, voltar ao planejamento permite editar e calcular novamente, sem apagar o snapshot antigo. Após a aprovação, use uma nova versão para alterar o cardápio.

A aprovação exige usuário autenticado, autorização `approve_menus` na organização e declaração de responsável, identificação profissional e referência do parecer. Administradores seguem a autorização administrativa já existente. O sistema registra essa declaração e não verifica a habilitação profissional. Calcular não aprova automaticamente; alertas de metas ficam disponíveis para análise humana.

## Cálculo e rastreabilidade

```text
porções planejadas = porções por pessoa × público da refeição
demanda do ingrediente = gramas brutas da receita × porções planejadas / rendimento declarado
demanda de produto direto = gramas brutas por pessoa × público da refeição
nutrientes = motor V2, composição vigente na data e fração comestível declarada
taxa semanal = soma dos nutrientes / soma dos pessoa-dias diários
```

Peso da porção é uma informação declarada; não infere fatores de cocção ou retenção. Público diário é contado uma vez quando as refeições informam a mesma quantidade de pessoas. Quantidades conflitantes tornam o denominador diário e semanal desconhecido. A semana começa na segunda-feira. Nutriente ausente permanece desconhecido, sem substituição por zero ou média de médias.

O cálculo preserva versões de receitas, ingredientes, composições, metas, durabilidade e resultados. Durante revisão/execução, esses dados permanecem congelados. A comparação física é atualizada separadamente ao consultar ou exportar; cada exportação preserva seu próprio snapshot, com motor versão 3, parâmetros e contexto.

## Distribuição e verdade financeira

O realizado compara exclusivamente distribuições físicas aprovadas do mesmo projeto, cliente, grupo e período, com contexto e conversão de massa válidos. Distribuições sem grupo não são atribuídas automaticamente: sua quantidade de registros é mostrada à parte. Contextos inválidos ou conversões ausentes deixam o saldo correspondente não calculável.

O relatório mostra demanda bruta, massa distribuída, diferença e saldo positivo a distribuir. Isso não comprova preparo ou consumo efetivo. Cardápios com períodos sobrepostos podem consultar a mesma distribuição; não existe reserva ou alocação exclusiva de estoque entre cardápios.

Nenhum serviço novo cria distribuição, compra, reserva, cobrança, pagamento ou lançamento fiscal. Quantidades financeiras continuam vindo dos serviços operacionais existentes. Demanda não é necessidade líquida de compra; o saldo não desconta estoques automaticamente.

O cronograma agrupa demandas por produto a partir do primeiro dia necessário, usando durabilidade inclusiva (`data + dias − 1`). Mantém separadas fontes e durabilidades diferentes. Sem durabilidade, não sugere lote. Trata-se de estimativa; validade real de lote, condições de armazenamento e autorização de compra continuam fora desse cálculo.

## Isolamento e segurança

- Consultas e gravações filtram organização explicitamente; cardápios, grupos e snapshots também exigem projeto. Grupo vinculado a outro cliente é recusado.
- Receitas e produtos de outra organização são recusados. Identificadores de refeições não podem atravessar cardápios ou clientes.
- Exportações de cardápio usam seu contexto próprio e recusam filtros externos que poderiam reinterpretá-lo.
- Aprovação é validada no serviço, além da visibilidade do botão. Edições/transições usam bloqueio transacional e revisão otimista.
- Snapshots e receitas são imutáveis pelos models. As rotas preservam autenticação, CSRF, papel de registrador e controle de tenant existentes; não há novas rotas no portal comprador.
- Isolamento por cliente refere-se a dados, cálculos e validação de vínculos. Registradores autorizados da organização continuam trabalhando com os clientes dos seus projetos, sem novo papel individual por cliente.

## Implantação em homologação

Os testes usam SQLite em memória. Nenhuma migração ou carga foi executada no banco de trabalho.

Após backup e revisão em homologação, aplicar as migrations aditivas V1, V2 e V3 e cadastrar a permissão:

```shell
php artisan migrate
php artisan db:seed --class=PlanningMenuPermissionsSeeder
```

O seeder cria a permissão, sem concedê-la automaticamente aos registradores. Configure sua atribuição aos papéis autorizados. A migration V3 cria somente seis tabelas: `recipes`, `recipe_items`, `project_menus`, `menu_days`, `menu_meals`, `menu_items`. Seu rollback remove somente essas tabelas; deve ser usado apenas quando houver intenção explícita de descartar os dados V3.

Validar manualmente em homologação: criar receita e duas versões de cardápio; conferir público e cálculos; vincular distribuições ao grupo correto; testar revisão, aprovação e execução com usuários de permissões diferentes; exportar PDF/XLSX; conferir o Centro de Integridade e comparar um faturamento anterior e posterior. Nenhum dado alimentar ou limite normativo é carregado automaticamente.

## Arquivos e verificação

Domínio: `MenuPlanningService`, `MenuReportService`, `RecipeDemandCalculator`, `MenuNutrientAggregator`, `MenuDeliveryScheduler`, `MenuIntegrityService`. Os motores compartilham `FoodCompositionResolver` e `QuantityMassConverter` com V1/V2.

Integrações: `ProjectMenuController`, `ProjectPlanningController`, `DeliveryReportController`, `MenuReportExport`, `PlanningReportSheet`, telas de planejamento/cardápio e template PDF. Relatórios incluem refeições, dias, semanas, fontes, metas, demanda, comparativo, cronograma e pendências.

Testes específicos: `MenuCalculatorsTest` e `MenuPlanningTest`. Cobrem rendimento, público sem duplicação, taxa semanal ponderada, durabilidade, imutabilidade, transições, revisão concorrente, autorização, isolamento, snapshot V3, exportações e preservação de quantidades financeiras. A seleção ampliada V1/V2/V3, distribuição e financeiro passou com **107 testes e 420 asserções**, registrada em `output/planning-v3-targeted-test.log`. PHP de 33 arquivos, seis templates Blade e JavaScript das novas telas passaram na verificação de sintaxe. PDF e XLSX são gerados nos testes; a aparência final e a interação no navegador devem ser conferidas em homologação.

Neste ambiente Windows, a restrição de leitura de arquivos temporários impede o `BinaryFileResponse` do teste de download. O teste do endpoint substitui apenas a resposta de download; a geração real do XLSX permanece coberta separadamente. A suíte é executada com configuração equivalente em `output/phpunit-planning.xml`, por limitação do bootstrap do Artisan no sandbox.

Suíte completa: **557 testes, 2.786 asserções, 36 falhas, quatro erros e 12 ignorados**. Comparação automática das chaves classe/método do JUnit encontrou exatamente as mesmas 40 falhas/erros da linha de base, sem novas ocorrências. Permanecem os problemas anteriores em `AccountingPortalSecurityTest`, `CustomerBillingSelectionServiceTest` e `PdfRenderingCompatibilityTest`. Evidências: `output/planning-v3-junit.xml`, `output/planning-v3-full-test.log` e `output/planning-baseline-junit.xml`. Isso não representa uma suíte completa verde; representa ausência de novas falhas identificadas nessa comparação.
