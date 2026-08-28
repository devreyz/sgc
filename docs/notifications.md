# Notificacoes Android (Firebase)

## Implantacao

1. Execute `php artisan migrate --force`.
2. Configure o Firebase Cloud Messaging conforme a secao abaixo.
3. Execute `php artisan config:cache` e `php artisan route:cache`.
4. Mantenha o dominio em HTTPS.

Web Push de navegador esta desativado. Assinaturas legadas sao revogadas pela migration e nao ha mais registro ou envio pelo navegador.

## Fila em hospedagem compartilhada

As notificacoes Android usam a fila `notifications`, processada a cada minuto. Documentos e tarefas comuns continuam a cada cinco minutos. Configure um cron a cada minuto:

```cron
* * * * * cd /caminho/absoluto/do/projeto && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Nao exponha o scheduler ou o worker por rota HTTP.

Para confirmar pela linha de comando que o cron esta rodando, use:

```bash
php artisan system:cron-status
```

O campo `Ultimo heartbeat do scheduler` deve mudar a cada minuto. A fila `notifications` deve ficar em zero ou diminuir logo apos um envio.

## Limpeza de falhas Web Push legadas

Depois que o Web Push foi desativado, use o modo de consulta para contar as falhas antigas:

```bash
php artisan notifications:purge-legacy-webpush
```

Se a quantidade estiver correta, remova somente essas falhas:

```bash
php artisan notifications:purge-legacy-webpush --force
```

O comando procura exclusivamente por `SendWebPushNotification` dentro de `failed_jobs`; ele nao remove falhas do FCM, documentos ou demais filas.

## Operacao

- A central fica em `/{tenant}/notifications`.
- Administradores configuram eventos em `/{tenant}/settings/notifications`.
- A permissao push e solicitada apenas quando o usuario toca em **Ativar notificacoes**.
- Distribuicoes possuem bloqueio fixo de push, mesmo se o banco for alterado manualmente.
- Toda notificacao push tambem gera um registro interno para historico e leitura.
- Endpoints de navegador sao criptografados; somente o hash e pesquisavel.

## Android e Firebase Cloud Messaging

O banco `notifications` e a fonte oficial. O canal FCM somente entrega um aviso resumido e uma rota para o registro da central; o clique volta ao Laravel, que confirma autenticacao, vinculo ativo, tenant e propriedade da notificacao antes de abrir o destino.

Configuracao do servidor:

1. No Firebase, cadastre o app Android `br.rzin.sgc` e baixe `google-services.json` para `android/app/google-services.json`.
2. Ative a Firebase Cloud Messaging API (HTTP v1).
3. Gere uma conta de servico com permissao minima para envio FCM e salve o JSON fora de `public/`, por exemplo em `storage/app/private/firebase/service-account.json`.
4. Configure `FCM_ENABLED=true`, `FCM_PROJECT_ID` e `GOOGLE_APPLICATION_CREDENTIALS` no `.env` do Laravel.
5. Execute `npm install`, `npx cap sync android`, `php artisan migrate --force` e recarregue o cache de configuracao.

Nunca envie o JSON da conta de servico ao repositorio, ao APK ou ao frontend. `google-services.json` identifica o projeto Android, mas tambem fica ignorado no projeto e deve ser instalado no ambiente de build por um canal controlado.

## Ciclo de vida e troca de conta

- Cada instalacao recebe um UUID aleatorio nao secreto. O servidor guarda apenas seu HMAC.
- O token FCM fica criptografado no banco e seu HMAC e usado para pesquisa e rotacao.
- O dispositivo e vinculado ao usuario autenticado e ao identificador aleatorio da sessao Laravel.
- O logout revoga no servidor todos os dispositivos ligados a essa sessao, mesmo se a limpeza nativa falhar.
- O aplicativo tambem tenta excluir o registro e invalidar o token FCM antes de concluir o logout.
- Ao entrar com outra conta, a mesma instalacao e transferida atomicamente para o usuario atual; registros conflitantes sao revogados.
- Um worker sempre reconfirma usuario ativo e vinculo ativo no tenant antes de enviar.

## Teste direcionado

```bash
php artisan notifications:test-push --user=ID_OU_EMAIL --tenant=ID_OU_SLUG
php artisan queue:work --queue=notifications --tries=3
```

O comando cria primeiro a notificacao no banco. O worker entrega a versao resumida a todos os dispositivos Android ativos do usuario. Entregas bem-sucedidas ficam em `push_delivery_receipts`, evitando reenvio do mesmo registro durante uma repeticao da fila.

## Canais Android

- `general`: avisos gerais.
- `operations`: entregas e estoque.
- `documents`: comprovantes e documentos.
- `financial`: eventos financeiros de maior importancia.

O conteudo exibido na tela bloqueada e propositalmente generico. Valores, nomes e outros detalhes permanecem apenas na central autenticada.
