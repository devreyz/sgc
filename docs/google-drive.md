# Google Drive por organizacao

O Google Drive usado para documentos é separado do login Google. Cada organização autoriza sua própria conta. O escopo `drive.file` permite trabalhar com os itens criados pelo SGC ou autorizados pelo usuário.

## Configuração única do servidor

1. No projeto do Google Cloud do SGC, ative **Google Drive API** e **Google Picker API**.
2. Configure o consentimento OAuth e crie um cliente do tipo **Aplicativo da Web**. Em produção, se o público for externo, publique o aplicativo em vez de mantê-lo em teste. Com a permissão de Drive, os tokens de atualização de um aplicativo externo em teste expiram em sete dias.
3. No cliente OAuth, adicione a origem JavaScript do SGC, por exemplo `https://sgc.ultror.com`. Adicione a URI de redirecionamento exata `https://sgc.ultror.com/auth/google-drive/callback`, usando o domínio definido em `APP_URL`.
4. No mesmo projeto, crie uma chave de API, restrinja-a ao **Google Picker API** e aos sites `https://sgc.ultror.com/*` e `https://docs.google.com/*`. Use o número do projeto para o App ID do Picker.
5. Preencha as variáveis no `.env` do servidor e regenere o cache de configuração com `php artisan config:cache`.

```dotenv
SGC_DRIVE_CLIENT_ID=
SGC_DRIVE_CLIENT_SECRET=
SGC_DRIVE_PICKER_APP_ID=
SGC_DRIVE_PICKER_API_KEY=
```

O cliente OAuth é compartilhado na configuração do servidor. Os tokens do Drive de cada organização são independentes e criptografados com a `APP_KEY`. A seleção visual depende do App ID e da chave do Picker.

## Conexão de uma organização

Em **Minha Organização → Google Drive**, um membro administrador seleciona **Conectar com Google** ou **Reconectar ou trocar conta**. Autorize o e-mail da organização e confira qual conta aparece na confirmação. O SGC cria `SGC - Nome da organização` no Meu Drive dessa conta ou dentro de uma pasta própria escolhida pelo usuário. Uma pasta compartilhada por outra conta não é usada como destino.

O cliente central é usado em todas as novas autorizações. Na troca de conta, os novos arquivos vão para a pasta da conta escolhida. Os arquivos anteriores ficam onde foram gravados e o SGC mantém a referência e o acesso histórico enquanto a autorização anterior estiver válida. O SGC não cria permissões de compartilhamento durante a conexão.

O SGC cria a estrutura:

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

Ao conectar uma organizacao, os comprovantes existentes tambem sao enfileirados. Falhas ficam registradas sem token, codigo OAuth ou credenciais. **Desconectar** remove o token armazenado para aquela organização, preservando seus arquivos. Revogar o aplicativo na própria conta Google afeta todas as conexões dessa conta com o projeto OAuth.

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
