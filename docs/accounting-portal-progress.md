# Progresso do Portal Contábil

## Gate de entrada

A Fase 2 foi iniciada após a confirmação do responsável de que os testes e evidências da Fase 1.5 foram executados.

## Fase 2A - concluída

### Backend e segurança

- [x] portal separado em `/{tenant}/accounting`;
- [x] permissões `view_accounting_portal` e `view_accounting_processes`;
- [x] permissões futuras de revisão e solicitação de correção separadas;
- [x] papel `contador` cadastrado sem acesso operacional implícito;
- [x] entrada no hub e navegação central por `PortalNavigation`;
- [x] resolução explícita de `tenant_id + receipt_id`;
- [x] respostas privadas e sem cache;
- [x] APIs paginadas e payload limitado;
- [x] verificação focada da integridade da cobrança;
- [x] resolvedor central de status humano e próxima ação.

### Interface

- [x] fila de trabalho sem categorias vazias;
- [x] lista de processos com busca e filtros;
- [x] tabela no desktop e apresentação móvel compacta;
- [x] skeletons, estados vazios e mensagens de erro;
- [x] dossiê inicial com abas;
- [x] origem, financeiro, comprovantes relacionados, documentos e timeline;
- [x] renderização dos dados no frontend por `fetch`.

### Regras financeiras preservadas

- [x] `CustomerBillingReceipt` permanece como conta a receber;
- [x] snapshots persistidos alimentam os totais exibidos;
- [x] somente distribuições são linhas financeiras;
- [x] entrega-pai aparece somente como referência física;
- [x] nenhum pagamento, recebimento ou movimento de caixa foi duplicado;
- [x] nenhuma estrutura fiscal ou de autorização fictícia foi criada.

## Arquivos principais

- `app/Http/Controllers/Accounting/AccountingPortalController.php`
- `app/Services/Accounting/AccountingNextActionResolver.php`
- `app/Services/Accounting/AccountingProcessIntegrityService.php`
- `resources/views/accounting/`
- `public/assets/accounting-portal.css`
- `public/assets/accounting-portal.js`
- `database/migrations/2026_08_20_000001_register_accounting_portal_permissions.php`

## Verificações da Fase 2A

Executadas durante a implementação:

```text
php artisan route:list --name=accounting
php artisan view:cache
php artisan test tests/Feature/AccountingPortalSecurityTest.php tests/Unit/AccountingNextActionResolverTest.php
vendor/bin/pint --test <arquivos alterados>
git diff --check
```

Cobertura específica adicionada:

- acesso por permissão tenant-aware;
- papel customizado com permissão explícita;
- negação sem permissão;
- IDOR entre tenants;
- payload limitado e paginação máxima;
- fila sem contagens zeradas;
- saldo calculado pelo snapshot;
- filtros sem expansão cross-tenant;
- distribuição como linha financeira;
- entrega-pai somente como rastreabilidade;
- estados humanos e próxima ação;
- precedência de inconsistência crítica.

Resultado final do gate local:

```text
196 testes aprovados
791 asserções
3 testes MySQL/InnoDB ignorados explicitamente no runner SQLite
0 falhas
```

## Implantação

1. executar backup e o procedimento normal de deploy;
2. executar `php artisan migrate --force`;
3. limpar cache de permissões/configuração conforme o deploy;
4. confirmar os papéis tenant-aware de contadores, tesoureiros e presidência;
5. abrir `/{tenant}/accounting` e validar a fila com dados reais;
6. executar a suíte relacionada e a suíte completa no ambiente de homologação.

## Fase 2B - concluída em código

### Entregas

- [x] `BillingAuthorization` como rodada histórica sem exclusão;
- [x] migration InnoDB com índices, idempotência, uma rodada ativa e FKs condicionais à engine das tabelas-pai;
- [x] snapshot imutável versionado, construído somente no backend;
- [x] SHA-256 determinístico e validação dos totais das linhas;
- [x] envio e reenvio atômicos com lock e `operation_key`;
- [x] autorização e solicitação de correção idempotentes;
- [x] motivo obrigatório e sanitizado para correção/cancelamento;
- [x] invalidação preventiva e garantia final por comparação de hash;
- [x] área `Cobranças para análise` no portal comprador;
- [x] visualização exclusiva do snapshot, taxas e anexos protegidos;
- [x] fila e dossiê contábil com situação e histórico de rodadas;
- [x] permissões, policy, rate limit, activity log e notificações;
- [x] processos legados sem autorização fictícia;
- [x] nenhuma estrutura fiscal ou nova verdade financeira criada.

### Testes específicos da Fase 2B

O conjunto focado cobre autorização por permissão, IDOR/cross-tenant, integridade financeira, preço da distribuição, taxas congeladas, hash e ordenação, idempotência, rodada ativa, sequência, portal comprador, resposta única, correção, snapshot histórico, invalidação de quantidade/preço/taxa/destinatário/estado/observação, mudança não material, cancelamento, fila, minimização de dados, activity log e destinatário de notificação.

Há uma suíte separada `mysql-billing-authorization` com cinco cenários InnoDB:

- dois envios concorrentes;
- duas autorizações concorrentes;
- autorização concorrendo com pedido de correção;
- alteração material concorrendo com autorização;
- índice de rodada ativa preservando histórico.

No runner SQLite esses cenários são ignorados explicitamente, pois SQLite não prova `SELECT ... FOR UPDATE`.

Resultado local após integrar a Fase 2B:

```text
43 testes focados aprovados
159 asserções focadas
226 testes aprovados na suíte completa
910 asserções na suíte completa
8 testes MySQL/InnoDB ignorados no SQLite (5 pertencem à Fase 2B)
0 falhas
```

### Implantação da Fase 2B

1. fazer backup e publicar aplicação + migrations no mesmo deploy;
2. executar `php artisan migrate --force`;
3. limpar cache de permissões e configuração;
4. confirmar os papéis que podem enviar/cancelar autorização;
5. validar uma cobrança real em homologação, incluindo correção e reenvio;
6. executar a suíte MySQL/InnoDB em banco dedicado contendo `test` no nome;
7. somente então avaliar o gate da Fase 2C.

Comando recomendado no servidor de testes, após copiar e ajustar `phpunit.mysql.xml.example` para `phpunit.mysql.xml`:

```text
php artisan config:clear
php vendor/phpunit/phpunit/phpunit --configuration=phpunit.mysql.xml
```

O arquivo inclui tanto a suíte concorrente financeira da fundação quanto os cinco cenários de autorização da Fase 2B. O banco configurado precisa ser descartável e conter `test` no nome; as suítes recusam qualquer outro nome.

## Próximo gate

A próxima subfase possível é a **2C**, mas ela não deve começar automaticamente. O gate depende da suíte completa, da execução InnoDB e da validação de homologação com uma organização real.

- confirmar que snapshots históricos continuam idênticos após alterações operacionais;
- confirmar entrega real das notificações configuradas;
- não iniciar o `FiscalGate` enquanto algum item do gate 2B permanecer pendente.

### Parecer atual

**NO-GO operacional temporário para a Fase 2C.** O código da Fase 2B e o gate técnico automatizado estão verdes. Em 21/08/2026 foram obtidas as seguintes evidências adicionais usando banco local separado e uma cópia anonimizada operacionalmente do schema de produção:

- `8/8` testes concorrentes MySQL/InnoDB aprovados, com `20` asserções e nenhum skip;
- cadeia completa de migrations reconstruída do zero em MySQL, incluindo as duas migrations da Fase 2B;
- suíte global com `226` testes, `910` asserções e zero falhas;
- auditoria da cópia com zero inconsistências críticas, 27 avisos históricos e nenhuma escrita de reparo;
- `billing_authorizations`, `customer_billing_receipts`, `organizations`, `tenants` e `users` confirmadas em InnoDB;
- sete FKs físicas de `billing_authorizations` confirmadas no schema importado.

Os avisos históricos encontrados foram: 13 divergências de snapshot/FK em comprovantes de membros, 13 em comprovantes de clientes e uma divergência de bruto congelado em cobrança de cliente. A FK atual permanece como verdade operacional; a cobrança divergente deve ser revisada antes de entrar em uma nova rodada de autorização.

Falta somente a evidência operacional que não pode ser produzida por teste automatizado:

1. validar o fluxo completo em navegador com uma organização compradora de homologação;
2. confirmar a entrega real das notificações no canal configurado.

Esse parecer não bloqueia a implantação da Fase 2B. Após as duas confirmações manuais, o gate pode ser alterado para GO sem nova alteração estrutural.

## Fase 2C - concluída em código

### Entregas

- [x] perfil fiscal versionado por tenant com override por projeto;
- [x] origem do valor limitada ao bruto ou total final do snapshot autorizado;
- [x] Gate central integrado à próxima ação e ao dossiê;
- [x] fila fiscal paginada, responsiva e carregada por API privada;
- [x] ações separadas de revisar, configurar e preparar;
- [x] preparação somente leitura baseada no snapshot;
- [x] permissões, IDOR, rate limit e activity log;
- [x] nenhuma regra tributária, nota fiscal, upload ou fila persistida.

### Evidências locais em 21/08/2026

- migrations `2026_08_21_000003` e `000004` aplicadas na cópia local;
- teste focado: 46 testes e 193 asserções, zero falhas;
- suíte global: 234 testes e 957 asserções, zero falhas; 8 cenários concorrentes MySQL ignorados pelo runner SQLite;
- navegação: 10 rotas GET e 4 POST contábeis; comandos POST com throttle;
- Blade compilado e arquivos PHP sem erro de sintaxe;
- fila sem snapshot completo;
- cobertura de Gate, estados da autorização, perfis, snapshot, override, permissão, IDOR e auditoria.

### Implantação

```text
php artisan migrate --force
php artisan permission:cache-reset
php artisan optimize:clear
php artisan test tests/Feature/AccountingPortalSecurityTest.php
php artisan test
```

Tenants existentes permanecem com **configuração fiscal pendente** até confirmação explícita. A Fase 2D não foi iniciada.
