# Google Drive por organizacao

O Google Drive usado para documentos e separado do login Google. Cada organizacao concede acesso a propria conta, e o SGC usa apenas o escopo `drive.file`, que permite operar nos arquivos criados pelo proprio SGC.

## Configuracao do Google Cloud

1. Ative a Google Drive API no projeto Google Cloud do SGC.
2. Configure a tela de consentimento OAuth.
3. Crie um cliente OAuth do tipo Aplicativo da Web.
4. Cadastre exatamente `https://seu-dominio/auth/google-drive/callback` como URI de redirecionamento autorizada.
5. Preencha no servidor:

```dotenv
GOOGLE_DRIVE_OAUTH_CLIENT_ID=
GOOGLE_DRIVE_OAUTH_CLIENT_SECRET=
GOOGLE_DRIVE_OAUTH_REDIRECT="https://seu-dominio/auth/google-drive/callback"
```

Depois execute `php artisan config:clear` e `php artisan migrate --force`.

## Conexao de uma organizacao

Um membro administrador autenticado recentemente abre **Minha Organizacao > Google Drive** e seleciona **Conectar Google Drive**. O super-admin nao ve nem pode executar essa configuracao. O client secret da aplicacao fica somente no ambiente do servidor; o refresh token de cada organizacao e criptografado com `APP_KEY` e nunca volta para a interface.

O consentimento deve ser feito com a conta Google que sera proprietaria do arquivo. O SGC cria a estrutura:

```text
SGC - Nome da organizacao/
  Comprovantes/
    Associados/Ano/Projeto-ID/
    Pagamentos/Ano/Projeto-ID/
  Arquivos/
    Despesas/Ano/
    Receitas/Ano/
    Projetos de venda/Ano/
    Compras e ordens de servico/Ano/
```

Comprovantes regenerados e anexos substituidos atualizam o mesmo arquivo pelo ID remoto. O banco mantem checksum e versao para evitar uploads duplicados. Tambem sao enviados documentos de patrimonio, compras, despesas, receitas, projetos, ordens de servico e comprovantes de pagamentos de servicos/prestadores.

## Processamento automatico

As sincronizacoes usam a fila `documents`. Em producao mantenha um worker ativo, por exemplo:

```shell
php artisan queue:work --queue=documents,default --tries=3 --timeout=120
```

Ao conectar uma organizacao, os comprovantes existentes tambem sao enfileirados. Falhas ficam registradas sem token, codigo OAuth ou credenciais. Os arquivos ja enviados permanecem na conta da organizacao depois da desconexao.

## Seguranca operacional

- Use HTTPS em producao.
- Nao reutilize as credenciais OAuth do login caso elas tenham outra politica de acesso.
- Restrinja a URI de callback no Google Cloud ao dominio oficial.
- Proteja e mantenha copia segura da `APP_KEY`; ela e necessaria para descriptografar os refresh tokens.
- Nao execute workers de ambientes diferentes contra a mesma fila.
- Revogue o acesso tambem em `myaccount.google.com/permissions` se houver suspeita de comprometimento.
