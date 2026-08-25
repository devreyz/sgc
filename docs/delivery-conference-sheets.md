# Folhas de Conferência de Entregas

## Objetivo e posição no fluxo

A Folha de Conferência de Entregas é uma evidência operacional que apresenta produtos, unidades e quantidades efetivamente distribuídas. Ela não é nota fiscal, cobrança, comprovante financeiro nem autorização para faturamento e não contém valores.

Fluxo humano: entrega → distribuição → folha de conferência → conferência do destinatário → aprovação → preparação da cobrança → autorização → contabilidade/fiscal.

A fonte de cada linha é exclusivamente `production_deliveries` com `parent_delivery_id IS NOT NULL`, status `approved`, quantidade positiva e pertencente ao mesmo tenant, projeto, destinatário e período. A entrega-pai permanece apenas como rastreabilidade física. `project_demands.unit_price`, preços recalculados e `billing_status` não participam da folha.

## Interface

A criação ocorre exclusivamente em `/{tenant}/delivery/conference-sheets`, dentro do portal de registro de entregas. O operador escolhe projeto, cliente ou organização e período, visualiza todas as distribuições elegíveis em tabela e seleciona explicitamente as linhas da folha. A tela oferece seleção rápida e informa quantas distribuições disponíveis estão ficando de fora.

No desktop a fila usa uma tabela plana. No mobile usa cartões com número, destinatário, período, situação e ação principal. A gestão administrativa aparece no Filament em **Projetos de Venda → Folhas de Conferência**, sem ação de criação ou exclusão; o botão de criação direciona ao portal.

Clientes inativos ficam fora da criação, mas relacionamentos históricos e snapshots continuam resolvendo seus nomes.

## Tabelas e invariantes

- `delivery_conference_sheets`: tenant, projeto, um destinatário (`customer_id` XOR `organization_id`), período, agrupamento, estado, numeração, snapshot, hash, revisão, atores e datas.
- `delivery_conference_sheet_items`: relacionamento explícito com distribuições e `UNIQUE(delivery_conference_sheet_id, distribution_id)`.
- Nenhuma tabela guarda preço, taxa ou total financeiro da folha.
- Não há `SoftDeletes`, `DELETE`, `FORCE DELETE` ou bulk delete. Erros são cancelados ou substituídos.
- Índices cobrem as filas por tenant/projeto/status/período e destinatários, além da busca reversa por distribuição.

As migrations são aditivas. As tabelas novas usam InnoDB. As FKs físicas são instaladas quando todas as tabelas-pai do MySQL suportam FKs; as validações de ownership permanecem obrigatórias no backend em qualquer engine.

## Estados e transições

- `draft`: período, destinatário, modo e itens ainda podem ser preparados novamente.
- `issued`: número reservado, snapshot e SHA-256 congelados.
- `approved`: o retorno físico foi registrado como aprovado.
- `correction_requested`: exige motivo e permite criar nova revisão.
- `rejected`: exige motivo e permanece histórico.
- `cancelled`: exige motivo e permanece histórico.
- `superseded`: revisão anterior substituída por uma nova emissão.

Transições são executadas por `DeliveryConferenceSheetService` dentro de transações. A emissão bloqueia a folha e as distribuições em ordem de ID, revalida os invariantes e impede que uma distribuição pertença acidentalmente a duas folhas ativas. A sequência é reservada pelo `ProjectReceiptNumberingService` com o tipo independente `delivery_conference` e prefixo visual `FC-` conforme a configuração de numeração do projeto.

## Snapshot e validade

Na emissão, o snapshot versão 1 congela identidade do tenant, projeto e destinatário; número e revisão; período e modo; IDs, datas, clientes, produtos, unidades e quantidades; e as linhas exatamente como agrupadas no PDF.

O SHA-256 usa JSON determinístico com chaves ordenadas, distribuições ordenadas por ID e quantidades normalizadas em quatro casas. PDFs emitidos usam apenas o snapshot. Alterações materiais em produto, cliente, unidade, quantidade, data, status ou vínculo disparam nova verificação. A aprovação histórica não é apagada; a folha passa a aparecer como aprovada, porém desatualizada, e não prepara cobrança.

## PDF oficial

O PDF é gerado pelo `TemplatedPdfService`, respeitando a identidade visual configurada pelo tenant. O cabeçalho exibe **FOLHA DE CONFERÊNCIA DE ENTREGAS** e, em destaque, **DOCUMENTO DE CONFERÊNCIA — SEM VALOR FISCAL**.

Cliente individual é agrupado por produto + unidade. Para organização, cada cliente recebe uma folha separada, sempre com cabeçalho completo. Unidades diferentes nunca são somadas. Cada produto possui uma linha com quantidade e unidade na mesma coluna, caixa de seleção **OK** e espaço de correção. O documento repete o cabeçalho em quebras físicas, evita quebra interna das linhas e termina somente com assinatura do responsável e data da entrega.

O modelo **Folha de Conferência de Entregas** fica disponível em Filament > Sistema > PDFs do Sistema. A configuração permite mostrar ou ocultar identificação, destinatário/projeto, checklist, resumo financeiro e assinatura; escolher data, cliente, produto, quantidade/unidade, valor médio unitário, valor total, OK e correção; ajustar escala, orientação, cabeçalho, rodapé e tema; e configurar os campos do responsável. Valores e resumo financeiro são opcionais e permanecem desativados por padrão.

Snapshots versão 2 congelam valor unitário e valor bruto das distribuições. O valor médio unitário de uma linha agrupada é ponderado pela quantidade. Folhas históricas versão 1 continuam válidas, mas exibem valores como indisponíveis caso uma configuração financeira seja aplicada, evitando consultar preços atuais e alterar silenciosamente o documento histórico.

## Imagens, documentos e Google Drive

O retorno aceita JPEG, PNG e WebP. Antes do envio, o navegador respeita orientação, limita o maior lado a 2560 px sem ampliar e converte para WebP em qualidade inicial 0,89, reduzindo moderadamente apenas para arquivos muito grandes. A interface mostra preview, resolução e tamanho final. Nenhum filtro, recorte, remoção de fundo ou transformação de contraste é aplicado.

O backend limita MIME, tamanho e quantidade, calcula SHA-256 e não duplica o mesmo conteúdo. Cada página é um `Document` na categoria `delivery_conference_signed`, guardado localmente de forma privada. A sincronização reutiliza `TenantGoogleDriveService` e `CloudDocument`, na pasta do tenant/projeto/folha. Falha do Drive não perde nem desfaz o arquivo local.

Downloads sempre resolvem `tenant_id + sheet_id + document_id`; IDs de outro tenant retornam 404.

## Integração com cobrança

Uma folha aprovada não cria cobrança automaticamente. A ação **Preparar cobrança** aceita uma ou várias folhas, valida mesmo tenant, projeto e destinatário, aprovação, validade atual, ausência de distribuições duplicadas e ausência de `billing_receipt_id`.

O sistema cria apenas um rascunho de `CustomerBillingReceipt` com a união deduplicada dos IDs e abre o fluxo Filament existente. `CustomerBillingReceiptService` continua sendo a única autoridade que, na emissão financeira, reconsulta e bloqueia distribuições, resolve preços e taxas, calcula valores e congela o snapshot financeiro. Nenhum valor da folha é transferido para a cobrança.

A cobertura **não cobrada / parcialmente cobrada / totalmente cobrada** é derivada de `production_deliveries.billing_receipt_id`; não existe coluna redundante na folha.

## Permissões, Shield e auditoria

Permissões registradas no catálogo Spatie/Filament Shield:

- `view_delivery_conference_sheets`;
- `create_delivery_conference_sheets`;
- `issue_delivery_conference_sheets`;
- `review_delivery_conference_sheets`;
- `upload_delivery_conference_documents`;
- `prepare_billing_from_delivery_conference`;
- `cancel_delivery_conference_sheets`.

Policies conferem tenant e permissão. Nenhuma decisão é baseada diretamente no nome de um papel. A migration apenas concede conjuntos iniciais coerentes aos papéis existentes; permissões podem ser ajustadas pelo Shield. Criação, emissão, retorno, anexos, invalidação, revisão, cancelamento e preparação de cobrança entram no `activity_log`, sem conteúdo binário.

## Segurança e desempenho

- Todas as resoluções usam tenant explícito, inclusive sem global scopes.
- Projeto, destinatário, folha, distribuição e documento são revalidados no backend.
- Distribuições são consultadas por filtros indexados; o projeto inteiro não é carregado no frontend.
- Listas são paginadas e o modo consolidado agrupa no backend.
- Emissão usa `DB::transaction`, `lockForUpdate` e ordem consistente.
- Nenhuma entrada oculta ou lista enviada pelo navegador é tratada como autoridade.

## Deploy e verificação

1. Fazer backup e publicar aplicação e migrations juntas.
2. Executar `php artisan migrate --force`.
3. Executar `php artisan permission:cache-reset` e `php artisan optimize:clear`.
4. Confirmar permissões por papel/tenant no Shield.
5. Rodar testes focados, suíte global e suíte MySQL/InnoDB em banco descartável com `test` no nome.
6. Rodar `php artisan view:cache`, `php artisan route:list`, Pint e `git diff --check`.

Checklist manual de produção:

1. Abrir projeto real, escolher cliente e criar folha.
2. Confirmar somente distribuições aprovadas do período.
3. Conferir no PDF identidade, aviso sem valor fiscal, produtos, unidades e quantidades.
4. Imprimir, preencher, assinar, fotografar e anexar todas as páginas.
5. Confirmar preview, legibilidade, download protegido e sincronização no Drive.
6. Registrar aprovado; alterar uma distribuição em homologação e confirmar desatualização.
7. Criar nova revisão quando houver correção.
8. Selecionar folhas aprovadas compatíveis e preparar cobrança.
9. Confirmar união dos IDs e que preços, taxas e valores vêm somente do serviço financeiro.
10. Conferir activity log, cobertura derivada e bloqueios cross-tenant.
