# Notificacoes e PWA

## Implantacao

1. Execute `php artisan migrate --force`.
2. Gere as chaves com `php artisan webpush:vapid`.
3. Salve as duas chaves no `.env` como `WEBPUSH_VAPID_PUBLIC_KEY` e `WEBPUSH_VAPID_PRIVATE_KEY`.
4. Configure `WEBPUSH_VAPID_SUBJECT` com um e-mail administrativo no formato `mailto:email@dominio`.
5. Execute `php artisan config:cache` e `php artisan route:cache`.
6. Mantenha o dominio em HTTPS. Somente `localhost` funciona sem HTTPS para desenvolvimento.

O service worker usa somente a rede e remove caches antigos durante a ativacao. Ele nao guarda paginas, respostas, arquivos ou dados do usuario.

## Fila em hospedagem compartilhada

As notificacoes push usam a fila `notifications`. O scheduler existente processa `notifications,documents,default` e encerra quando as filas esvaziam. Configure um cron a cada minuto:

```cron
* * * * * cd /caminho/absoluto/do/projeto && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Nao exponha o scheduler ou o worker por rota HTTP.

## Operacao

- A central fica em `/{tenant}/notifications`.
- Administradores configuram eventos em `/{tenant}/settings/notifications`.
- A permissao push e solicitada apenas quando o usuario toca em **Ativar notificacoes**.
- Distribuicoes possuem bloqueio fixo de push, mesmo se o banco for alterado manualmente.
- Toda notificacao push tambem gera um registro interno para historico e leitura.
- Endpoints de navegador sao criptografados; somente o hash e pesquisavel.
