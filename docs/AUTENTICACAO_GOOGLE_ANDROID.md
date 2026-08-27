# Autenticação Google no Android

O aplicativo Capacitor usa o Credential Manager para obter um ID Token Google destinado ao backend. O navegador continua usando o fluxo Laravel Socialite existente.

## Configuração Laravel

Configure no `.env` do servidor:

```dotenv
GOOGLE_WEB_CLIENT_ID=000000000000-example.apps.googleusercontent.com
GOOGLE_ANDROID_CLIENT_ID=000000000000-example.apps.googleusercontent.com
```

`GOOGLE_WEB_CLIENT_ID` é o OAuth Client ID do tipo **Aplicativo da Web** usado como audience do ID Token. É um identificador público, não um Client Secret.

`GOOGLE_ANDROID_CLIENT_ID` é o OAuth Client ID do tipo **Android** associado ao package `br.rzin.sgc` e ao certificado de assinatura. Também é público. Ele é usado pelo backend para validar o claim `azp` quando presente.

O `GOOGLE_CLIENT_SECRET` permanece somente no servidor e nunca deve ser copiado para o projeto Android.

## Configuração do build Android

Informe o mesmo valor de `GOOGLE_WEB_CLIENT_ID` ao Gradle por uma destas opções:

1. Em `android/gradle.properties`:

```properties
GOOGLE_WEB_CLIENT_ID=000000000000-example.apps.googleusercontent.com
```

2. Ou pela variável de ambiente `GOOGLE_WEB_CLIENT_ID` antes de compilar.

O valor é incorporado ao APK como recurso público. Nenhum Client Secret é usado no aplicativo.

## Clientes e certificados no Google Cloud

- Web OAuth Client: usado como `serverClientId` no Android e como audience no Laravel.
- Android OAuth Client de desenvolvimento: package `br.rzin.sgc` e SHA-1 do certificado debug.
- Android OAuth Client de produção: mesmo package e SHA-1 do certificado release, quando houver.
- Ao publicar na Play Store, cadastrar também o SHA-1 do Google Play App Signing.

## Fluxo

1. A página solicita ao Laravel um nonce de uso único vinculado à sessão.
2. O plugin `NativeAuth` abre o Credential Manager.
3. O Google retorna apenas um ID Token ao JavaScript.
4. O JavaScript envia o ID Token via POST HTTPS com CSRF e cookies da sessão.
5. O Laravel valida assinatura, issuer, audience, `azp`, expiração, `iat`, `sub`, e-mail verificado e nonce.
6. A política existente encontra ou vincula a conta e verifica usuário e vínculo ativos.
7. A sessão Laravel é regenerada e o usuário segue para a seleção de organização ou painel aplicável.

O ID Token não é salvo em localStorage, sessionStorage, IndexedDB, banco de dados ou logs.
