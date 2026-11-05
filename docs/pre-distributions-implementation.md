# Planejamento e Pré-distribuições

## Estado da entrega

Implementação incremental de um módulo **operacional**, independente de recebimentos e de faturamento. `PRE_DISTRIBUTIONS_ENABLED=false` permanece como padrão. Nenhuma migration foi executada no banco em uso e nenhuma dependência foi adicionada.

O código inclui gestão e portal do entregador, consolidação, PDFs preservados, snippets no modal existente, conciliação com distribuições existentes e reversão auditada. A liberação definitiva continua pendente da execução da suíte em MySQL e da homologação visual/operacional em dispositivos reais. As validações SQLite não comprovam concorrência InnoDB.

Auditoria anterior à implementação: [pre-distributions-audit.md](pre-distributions-audit.md). A arquitetura real é Laravel 12, Blade/JavaScript e Filament 3; a operação existente não usa componentes Volt. O novo portal segue essa arquitetura, sem introduzir um segundo mecanismo financeiro.

## Fluxos disponíveis

1. No hub, o registrador/admin abre **Planejamento e Pré-distribuições**, cria a planilha, escolhe projetos e entregador e adiciona destinos/produtos. Não exige recepção de produtor, preço oficial nem associação presumida a produtor.
2. O entregador acessa o mesmo portal com acesso restrito às suas planilhas. Filtra destinos e pendências, informa quantidade, horário e observações; salva uma linha ou as alterações da página. Vazio continua pendente; zero confirma ausência de entrega.
3. A conclusão registra ator, horário e versão. Correções exigem reabertura pelo gestor e justificativa; solicitações do entregador aparecem no histórico. Cancelamento preserva os dados e é terminal.
4. O gestor pode editar dados/atribuição do rascunho e duplicar um planejamento. A cópia mantém destinos e previsões, mas não copia quantidades realizadas, horários, notas de execução, cancelamentos ou conciliações.
5. Selecionar planilhas permite consolidar por projeto, cliente, produto e unidade, filtrar período/entregador/projeto/cliente/organização e consultar todas as páginas das origens. Registros do mesmo contexto/dia geram aviso de possível duplicidade, sem bloqueio automático.
6. A relação preliminar pode conter apenas quantidades ou referências de preço/valores estimados. O histórico permite baixar o arquivo preservado e emitir nova revisão com os filtros anteriores. Cada revisão recebe snapshot, checksum e arquivo privado próprios; a revisão anterior não é sobrescrita.
7. No modal existente de distribuição, **Utilizar pré-distribuições** consulta linhas positivas de planilhas concluídas, compatíveis por IDs e unidade. O registrador escolhe o item e ajusta a quantidade. Linhas manuais continuam disponíveis e devem ser revisadas antes de salvar.
8. **Conferir conciliação**, na planilha, mostra informado, conciliado válido, reservado, disponível, alertas e vínculos. É possível escolher uma distribuição oficial existente e vincular parte de sua quantidade, sem criar ou alterar valores oficiais.
9. A reversão só libera quantidade depois da remoção/cancelamento/rejeição oficial pelo fluxo autorizado existente. O vínculo original e a justificativa permanecem. Reativar/restaurar uma distribuição cujo vínculo foi revertido é impedido para não duplicar a quantidade liberada, inclusive com o módulo desabilitado.

## Modelo e separação de responsabilidades

| Entidade | Finalidade |
|---|---|
| `pre_distribution_sheets` | Código PD, UUID, data operacional, responsável, criador, situação, versão e conclusão/cancelamento |
| `pre_distribution_sheet_projects` | Projetos permitidos na planilha, com FK composta por tenant |
| `pre_distribution_items` | Destino, produto, unidade capturada, previsão, quantidade informada, referência opcional, horário, modalidade, notas e cancelamento |
| `pre_distribution_operations` | Chave UUID única por tenant, ator, tipo, hash da requisição e resultado confirmado |
| `pre_distribution_events` | Histórico de negócio com antes/depois, versões, ator, justificativa e operação; model impede atualização/exclusão |
| `pre_distribution_links` | Relação quantitativa muitos-para-muitos, snapshot oficial, origem/ator/operação e dados da reversão |
| `pre_distribution_reports` | Série/código, revisão, snapshot das fontes e versões, arquivo privado e SHA-256 |
| `pre_distribution_security_events` | Metadados duráveis de respostas inválidas do módulo, sem corpos, observações, nomes ou tokens |

As referências legadas a projetos/clientes/produtos são verificadas por tenant nos serviços. As relações operacionais usam FKs compostas e exclusão restrita. O ID oficial nos vínculos é deliberadamente histórico, sem cascade/FK que apague o histórico quando o fluxo existente remover uma distribuição. Sua existência, origem e compatibilidade são novamente verificadas na conciliação e no diagnóstico.

Nenhum serviço novo cria recebimentos, estoque, receitas, obrigações, comprovantes financeiros ou lançamentos de caixa. A única criação oficial continua em `DeliveryRegistrationController::distribute()`, com `PricingService`, `ProjectFinancialCalculator`, limites de projeto/associado, autorização dos destinos e regras de consumo já existentes. O preço preliminar **nunca** substitui o preço oficial.

## Integridade, autorização e concorrência

- Contexto exige feature flag, tenant explícito igual ao da sessão, usuário ativo e vínculo ativo. As consultas novas não dependem do comportamento aberto do scope legado quando falta tenant.
- `entregador`: somente `view_pre_distributions` e `record_pre_distributions`. `registrador_entregas` e `admin`: também `manage_pre_distributions`. Não foram concedidas permissões financeiras/oficiais ao entregador. O painel Filament conserva sua restrição existente; o papel pode ser atribuído no cadastro de membros da organização.
- Gestor/entregador usam o mesmo controle de acesso, mas o entregador recebe apenas as próprias planilhas. Preços de referência e estados históricos completos não são enviados ao entregador. Catálogos administrativos, relatórios e conciliação exigem gestão; a conciliação exige também papel autorizado para operações oficiais.
- Toda escrita operacional exige chave UUID; edições também exigem versão. Repetir a mesma chave/ator/tipo/payload retorna o resultado anterior. Mudar o payload com a mesma chave retorna 409. Acesso é revalidado antes de replay após transferência ou desativação do entregador.
- O primeiro lock é a linha do tenant. Ele serializa escritas do módulo, inclusive chaves ainda inexistentes; depois são bloqueadas planilhas/itens em ordem de ID e a recepção/distribuição oficial. Esse bloqueio deliberadamente conservador reduz vazão por tenant e precisa de medição em homologação.
- Nos snippets, a operação, os vínculos e as distribuições são confirmados na **mesma transação existente**. Replays são consultados antes das verificações preliminares de saldo para tratar perda de resposta após commit. Exceções, inclusive erros de execução, desfazem a unidade de confirmação.
- Serviços usam tentativas transacionais limitadas a três; emissão de PDF usa uma tentativa por produzir arquivo. O controller oficial mantém uma tentativa por confirmação: em falha o cliente pode reenviar a mesma chave. Não há confirmação financeira automática nem processamento parcial implícito.
- Quantidades são armazenadas em DECIMAL(18,4); comparações novas de reserva/limite usam BCMath. O saldo é novamente verificado sob lock. As regras numéricas antigas do controller foram preservadas e seus riscos estão na auditoria.
- A unidade capturada usa collation binária no MySQL, para que agrupamentos e filtros não juntem unidades apenas por ignorar diferenças de caixa/acentuação. Não há conversão automática de unidades.
- Contexto de item conciliado não pode ser trocado. Após reabertura autorizada, a quantidade informada pode ser retificada desde que não fique abaixo da reserva existente. Referências de preço continuam apenas operacionais, com avisos independentes. Alterações não recalculam distribuições financeiras existentes.
- Mudanças/remoção de distribuição, origem ausente/incompatível, excesso sobre a recepção, mudança de unidade e destino desautorizado geram diagnóstico. Vínculos divergentes continuam reservando quantidade até resolução explícita; não há liberação silenciosa que permita duplicação.
- Uma distribuição pode atender vários itens preliminares; um item pode ter várias distribuições de diferentes produtores. Ambos os lados têm limites próprios. Pares duplicados na mesma vinculação manual são rejeitados com mensagem de validação.

## PDFs e volume

Os PDFs usam o cabeçalho e tema compartilhados dos comprovantes, quantidades/preços com quatro casas, valores monetários com duas, paginação à direita e data/hora no rodapé. Código, revisão e **PRELIMINAR** aparecem em todas as páginas. O aviso preliminar obrigatório permanece no documento. Preços diferentes não são tratados como uma tabela única nem convertidos automaticamente; totais são separados por produto/projeto/unidade.

Os arquivos ficam no disk `local` privado em `pre-distribution-reports/{tenant}/{uuid}.pdf`, sem publicação no Drive, QR financeiro ou URL pública. Download autentica o usuário e verifica SHA-256; arquivo ausente/corrompido retorna conflito, sem regeneração sobre o original. Backup deve incluir banco **e** armazenamento privado.

Listagens são paginadas: planilhas 20; itens/origens 100; consolidação/histórico 50; conciliação/snippets/candidatos/relatórios 30. Catálogos de projetos/entregadores têm busca e limite de 300 com indicação de mais resultados; catálogo de organizações limita-se a 300. Quantidade máxima por planilha: 500; alterações por lote: 100; seleção de planilhas: 100. PDF captura até 500 linhas por padrão (`max_report_items`), rejeitando excesso sem omitir linhas silenciosamente. Alterar esse limite exige ensaio de memória/tempo.

Consultas de progresso carregam vínculos, distribuições, recepções e produtos em lote; autorização de clientes é reaproveitada por projeto na página. A escrita reaproveita catálogos por operação, sem manter permissões em cache entre escritas. Histórico de vínculos de um item ainda é retornado integralmente no diagnóstico; volumes históricos extensos precisam de benchmark/paginação adicional antes de ampliar uso.

## Arquivos e integração

Criados:

- Configuração: `config/pre_distributions.php`.
- Migrations de `2026_11_05_000001_create_pre_distribution_foundation.php` a `2026_11_05_000007_enforce_transactional_pre_distribution_tables.php`.
- Models: `PreDistributionSheet`, `PreDistributionItem`, `PreDistributionOperation`, `PreDistributionEvent`.
- Policy: `app/Policies/PreDistributionSheetPolicy.php`.
- Serviços: `app/Services/PreDistributions/{PreDistributionAccess,PreDistributionSheetService,PreDistributionQueryService,PreDistributionReconciliationService,PreDistributionReportService}.php`.
- Controller: `app/Http/Controllers/Delivery/PreDistributionController.php`; middleware: `app/Http/Middleware/AuditPreDistributionFailures.php`.
- Interface: `resources/views/delivery/pre-distributions.blade.php`, `resources/js/pre-distributions.js`, `resources/css/pre-distributions.css`.
- PDF: `resources/views/pdf/pre-distributions.blade.php`.
- Testes: `tests/Feature/PreDistributionFoundationTest.php`, `tests/Feature/MySqlPreDistributionConcurrencyTest.php`.
- Documentação: auditoria e este guia.

Modificados: `.env.example`, `routes/web.php`, `vite.config.js`, `HubController`, `PortalNavigation`, rótulo do papel no `TenantUserResource`, integração pontual do `DeliveryRegistrationController`, proteção de reativação no `ProductionDeliveryObserver`, prefixo opcional no `PdfUpdateFooterService` e componente compartilhado `delivery/dist-modal.blade.php`. O build inclui manifest e somente os novos assets do módulo. O prefixo permite identificar o caráter preliminar em todas as páginas, sem duplicar linhas de rodapé; os documentos anteriores mantêm o padrão sem prefixo. Não foram substituídas páginas/modais existentes, relatórios financeiros ou motores de cálculo.

Rotas novas ficam em `/{tenant}/pre-distributions`, com listagem/catálogo, CRUD operacional, conclusão/correção, histórico, consolidação/origens, snippets, conciliação/reversão e histórico/download de relatórios. O endpoint financeiro antigo conserva sua URL. Nenhum componente Livewire novo foi necessário.

## Validação e pendências

O MySQL configurado localmente não estava acessível (erro de conexão 2002); nenhuma tabela MySQL foi criada para tentar contornar isso.

Resultados em 05/11/2026:

| Verificação | Resultado |
|---|---|
| `php vendor/phpunit/phpunit/phpunit tests/Feature/PreDistributionFoundationTest.php --colors=never` | **58 testes aprovados**; SQLite `:memory:` com fixtures isoladas; cenário de unidades também distingue `kg` e `KG` |
| Limites do associado, ajustes de quantidade, planejamento existente, relatórios operacionais, preferências do modal e calculadora financeira | **65 testes, 264 assertions, aprovados** |
| `tests/Unit/PdfUpdateFooterTest.php` após extensão opcional do rodapé | **3 testes, 50 assertions, aprovados** |
| Execução final conjunta do módulo e de todos os conjuntos relacionados acima | **126 testes, 714 assertions, aprovados** |
| Suíte ampla da alteração | 729 testes; 9 erros, 41 falhas e 71 ignorados; 50 ocorrências de falha no total |
| Suíte ampla no HEAD anterior `48d5caa` | 612 testes; **os mesmos 9 erros e 41 falhas**, 12 ignorados |
| Comparação dos nomes completos dos testes com falha | Conjuntos idênticos; nenhuma ocorrência adicional na suíte ampla executada |
| MySQL | Não executado: conexão local indisponível; 59 cenários novos MySQL ignorados na suíte ampla, além dos 12 já ignorados na base |
| `npm run build` | Aprovado; manifest e entrada JS/CSS do módulo gerados |
| Sintaxe PHP, `node --check` do módulo e JS extraído do modal, `git diff --check` | Aprovados |
| Laravel Pint nos arquivos PHP novos | Aplicado; arquivos antigos não foram reformatados por inteiro |
| Rotas | Listadas com `php artisan route:list --path=pre-distributions --except-vendor` |
| Configuração atual | `php artisan config:show pre_distributions`: `enabled=false`, `max_report_items=500` |
| PDF | Amostras de uma e cinco páginas renderizadas com Poppler e inspecionadas visualmente, incluindo repetição de cabeçalhos de tabela, paginação e rodapé preliminar |

A base foi extraída com `git archive HEAD` para um diretório temporário separado, usando o mesmo `vendor`, autoload apontado para a cópia anterior, configuração SQLite em memória e variáveis de teste equivalentes. Nenhum arquivo atual foi substituído para fazer a comparação. Os nomes completos dos 50 testes com falha foram comparados, não apenas as contagens.

As ocorrências preexistentes concentram-se em `AccountingPortalSecurityTest`, `CustomerBillingSelectionServiceTest`, `DeliveryConferenceSheetServiceTest`, `ReceiptConsentCustomizationTest`, `PortalNavigationTest`, `ReceiptQuantityTotalTest` e uma expectativa fixa de quantidade de páginas em `PdfRenderingCompatibilityTest`. Não foram mascaradas, removidas ou corrigidas silenciosamente como parte deste módulo. A suíte ampla **não está verde**; a comparação demonstra sua situação anterior, não uma autorização de implantação.

Comando do conjunto de regressão relacionado:

```powershell
php vendor/phpunit/phpunit/phpunit tests/Feature/AssociateProjectLimitServiceTest.php tests/Feature/DeliveryQuantityAdjustmentTest.php tests/Feature/ProjectPlanningTest.php tests/Feature/DeliveryOperationalReportTest.php tests/Unit/DeliveryDistributionPreferenceContractTest.php tests/Unit/ProjectFinancialCalculatorTest.php --colors=never
```

A suíte MySQL verifica as regras funcionais, a conversão de tabelas MyISAM e o bloqueio de uma segunda conexão durante a confirmação. Após o commit, testa versão, idempotência e saldo. Exige um banco descartável cujo nome corresponda exatamente a `sgc_pre_distributions_test_[a-z0-9_]+` e autorização explícita para recriar as tabelas.

Em um banco dedicado já criado pelo administrador, com credenciais restritas a ele:

```powershell
$env:DB_CONNECTION = 'mysql'
$env:DB_DATABASE = 'sgc_pre_distributions_test_validation'
$env:PRE_DISTRIBUTIONS_MYSQL_DESTRUCTIVE_TESTS = '1'
php vendor/phpunit/phpunit/phpunit tests/Feature/MySqlPreDistributionConcurrencyTest.php
```

Executar apenas essa classe nessa configuração e restaurar as variáveis do ambiente ao concluir. Também pendem homologação do fluxo completo em Android/PWA/desktop, ensaio de carga/memória, discussão do limite do catálogo de organizações e do histórico muito extenso, e correção/homologação das falhas preexistentes da suíte ampla.

## Implantação e reversão

1. Fazer backup do banco e do armazenamento privado; homologar sobre cópia, mantendo a flag desligada. Conferir `php artisan migrate:status` e eventuais migrations antigas pendentes separadamente.
2. Aplicar as migrations pendentes em ordem crescente, com o módulo desligado. A migration `000007` converte tabelas do módulo criadas com MyISAM para InnoDB e restaura as chaves estrangeiras. Homologar sobre cópia com dados, pois referências órfãs podem exigir correção antes de concluir. Conferir o motor, as chaves estrangeiras e o cadastro dos três nomes de permissão.
3. Compilar/publicar `npm run build`, incluindo `public/build/manifest.json`, JS e CSS novos; atualizar caches de configuração/views conforme a rotina já usada no ambiente. Não publicar apenas as fontes ou apenas o manifest.
4. Atribuir papel `entregador` no vínculo ativo do tenant e preparar uma planilha de homologação com projetos/destinos reais autorizados. Atribuição é por organização, sem conceder acesso financeiro global.
5. Executar suíte MySQL e homologação mobile, confirmar as pendências relevantes e só então configurar `PRE_DISTRIBUTIONS_ENABLED=true` e reconstruir o cache de configuração.
6. Conferir pós-implantação: isolamento por IDs/tenant, nulo versus zero, rascunho/conclusão/reabertura, dupla confirmação, limites de ambos os lados, preços oficiais, reversão e restauração bloqueada, download de revisão anterior e acesso negado do entregador aos módulos oficiais.

Reversão preferencial: desligar a flag e atualizar o cache. Isso preserva dados, arquivos, histórico e operações idempotentes. As migrations recusam rollback quando há registros; a de permissões deliberadamente não remove papéis já atribuídos. Não apagar operações para "limpar" retries, não eliminar arquivos privados referenciados e não remover as proteções de restauração de registros já conciliados. Exclusão/arquivamento definitivo exige um procedimento próprio após exportação e conferência de todas as referências; não está automatizado nesta entrega.
