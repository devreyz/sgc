# Google Drive por organizacao

O Google Drive usado para documentos e separado do login Google. Cada organizacao concede acesso a propria conta, e o SGC usa apenas o escopo `drive.file`, que permite operar nos arquivos criados pelo proprio SGC.

## Configuracao do Google Cloud

1. Ative a Google Drive API em um projeto Google Cloud controlado pela organizacao.
2. Configure a tela de consentimento OAuth.
3. Crie um cliente OAuth do tipo Aplicativo da Web.
4. Em **Minha Organizacao > Google Drive**, copie a URI de redirecionamento exibida.
5. Cadastre essa URI exatamente nas URIs de redirecionamento autorizadas do cliente OAuth.
6. Informe o Client ID e o Client Secret nessa mesma pagina e salve.
7. Selecione **Conectar Google Drive** e conclua o consentimento com a conta da organizacao.

As credenciais OAuth, o refresh token e os erros tecnicos sao criptografados no banco com a `APP_KEY`. Nao existem variaveis globais de Google Drive no `.env`; cada tenant possui uma conexao independente.

## Conexao de uma organizacao

Um membro administrador autenticado recentemente configura e conecta o Drive. O super-admin nao ve nem pode executar essa configuracao. O Client Secret nunca e devolvido pelo backend depois de salvo; deixar o campo vazio preserva o valor atual.

Ao trocar o Client ID ou o Client Secret, o token anterior e descartado e a organizacao precisa autorizar novamente. Isso impede que um token emitido para um cliente OAuth seja reutilizado com credenciais diferentes.

O consentimento deve ser feito com a conta Google que sera proprietaria do arquivo. O SGC cria a estrutura:

```text
SGC - Nome da organizacao/
  Comprovantes/
    Associados/Ano/01 - Nome do projeto/
    Pagamentos/Ano/01 - Nome do projeto/
  Arquivos/
    Despesas/Ano/
    Receitas/Ano/
    Projetos de venda/Ano/01 - Nome do projeto/
    Compras e ordens de servico/Ano/
```

O numero inicial da pasta e o ID interno do projeto com pelo menos dois digitos. Assim, projetos com o mesmo titulo continuam separados, por exemplo `01 - PAA 2026` e `02 - PAA 2026`.

Comprovantes regenerados e anexos substituidos atualizam o mesmo arquivo pelo ID remoto. Se o nome da pasta mudar, o arquivo e movido na proxima sincronizacao. O banco mantem checksum e versao para evitar uploads duplicados. Tambem sao enviados documentos de patrimonio, compras, despesas, receitas, projetos, ordens de servico e comprovantes de pagamentos de servicos/prestadores.

## Processamento automatico

As sincronizacoes usam a fila `documents`. Em servidor com worker persistente, use:

```shell
php artisan queue:work --queue=notifications,documents,default --tries=3 --timeout=120
```

### Hostinger e hospedagem compartilhada

O projeto agenda um worker de execucao unica a cada cinco minutos, protegido por `withoutOverlapping`. No painel de cron da hospedagem, execute o scheduler do Laravel a cada minuto:

```cron
* * * * * cd /caminho/absoluto/do/projeto && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Use o caminho do executavel PHP selecionado para o dominio na Hostinger. O worker processa `notifications,documents,default`, encerra quando a fila esvazia, limita cada job a 120 segundos e o processo a 240 segundos. Nao crie uma URL publica para disparar a fila e nao coloque tokens no comando do cron.

Depois de atualizar nomes de projetos ou implantar esta versao, use **Minha Organizacao > Google Drive > Sincronizar agora**. Os jobs serao processados na proxima passagem do cron.

Ao conectar uma organizacao, os comprovantes existentes tambem sao enfileirados. Falhas ficam registradas sem token, codigo OAuth ou credenciais. Os arquivos ja enviados permanecem na conta da organizacao depois da desconexao.

## Seguranca operacional

- Use HTTPS em producao.
- Nao reutilize as credenciais OAuth do login caso elas tenham outra politica de acesso.
- Restrinja a URI de callback no Google Cloud ao dominio oficial.
- Proteja e mantenha copia segura da `APP_KEY`; ela e necessaria para descriptografar os refresh tokens.
- Nao envie Client ID, Client Secret ou refresh token por suporte, logs ou capturas de tela.
- Nao execute workers de ambientes diferentes contra a mesma fila.
- Mantenha `QUEUE_CONNECTION=database`, `DB_QUEUE_RETRY_AFTER=180` e um cache com locks atomicos, como `database`.
- Revogue o acesso tambem em `myaccount.google.com/permissions` se houver suspeita de comprometimento.

## Publicacao em producao

Antes de publicar, confirme no `.env` do servidor:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://sgc.ultror.com
LOG_LEVEL=warning

SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax

CACHE_STORE=database
QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=180
```

Preserve a `APP_KEY` existente. Troca-la torna impossivel descriptografar as credenciais OAuth e refresh tokens ja armazenados.

Na implantacao execute, a partir da raiz do projeto:

```shell
composer install --no-dev --classmap-authoritative --no-interaction
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Depois configure o cron do scheduler e confira `php artisan schedule:list`. O cron deve chamar somente um comando CLI; nunca exponha `schedule:run`, `queue:work` ou sincronizacao do Drive por rota publica.
