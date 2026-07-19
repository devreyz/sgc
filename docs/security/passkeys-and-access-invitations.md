# Passkeys, Google e convites de acesso

## Arquitetura atual

- `User` continua sendo a conta global de autenticacao.
- `TenantUser` continua sendo o vinculo e a identidade interna de cada organizacao.
- `Associate.user_id` liga o associado a uma conta global e agora pode ser nulo antes do primeiro acesso.
- `Passkey` pertence somente a `User`; a credencial e localizada globalmente pelo `credential_id`.
- `OAuthAccount` liga o `sub` permanente do Google a `User`. E-mail nao faz vinculacao automatica.
- `AccessInvitation` pertence obrigatoriamente ao par tenant/associado e guarda somente hashes.
- `SecurityEvent` registra eventos estruturados sem token, codigo, challenge, cookies ou credenciais.

O login por passkey resolve primeiro a credencial e a conta global. O tenant somente e selecionado depois da autenticacao, com base em vinculos ativos carregados no servidor.

## Fluxos implementados

### Passkey

O backend usa `laravel/passkeys`, sobre `web-auth/webauthn-lib`, para CBOR, COSE, assinatura, origin, RP ID, rpIdHash, UP, UV e contador. As opcoes exigem verificacao de usuario e credencial descobrivel. Challenges possuem 32 bytes, ficam na sessao, expiram e sao removidos antes da verificacao para impedir replay.

Usuarios autenticados podem adicionar ou revogar passkeys em `/security`. Alteracoes exigem autenticacao recente; a confirmacao pode ser feita por uma passkey da propria conta ou por uma conta Google ja vinculada. A ultima passkey nao pode ser revogada sem outro metodo de acesso.

### Google

O Socialite permanece responsavel pelo Authorization Code Flow. O provider seguro habilita PKCE, mantem `state`, adiciona `nonce` de uso unico e valida assinatura do ID token, issuer, audience, expiracao, nonce, `email_verified` e `sub`. O e-mail nao cria nem une contas.

### Convite

Um usuario autorizado gera o convite na pagina do associado. O token tem mais de 32 bytes aleatorios e o codigo possui dez caracteres independentes. O banco guarda SHA-256 do token e Argon2id do codigo combinado com pepper.

O GET `/acesso/{token}` nao consome o convite. Ele guarda apenas o ID na sessao e responde com redirect 303 para uma URL limpa. A pagina publica nao revela associado ou tenant e recebe cabecalhos `no-store`, `no-referrer`, `DENY` e CSP contra frames.

A validacao do codigo usa CSRF, limites por convite/sessao/IP, cinco tentativas e `lockForUpdate`. O grant resultante dura no maximo dez minutos e serve apenas para cadastrar a passkey. A criacao da conta, vinculo, credencial e consumo do convite ocorre na mesma transacao.

## Implantacao

1. Configure uma origem canonica HTTPS em `APP_URL`.
2. Configure `WEBAUTHN_RP_ID` sem protocolo e sem porta.
3. Configure `WEBAUTHN_ALLOWED_ORIGINS` com origens exatas, separadas por virgula.
4. Gere peppers independentes e longos para `ACCESS_INVITATION_CODE_PEPPER` e `SECURITY_AUDIT_PEPPER`.
5. Em producao, use `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true` e `SESSION_SAME_SITE=lax`.
6. Execute `php artisan migrate --force` e `npm run build`.
7. Limpe caches com `php artisan optimize:clear` e depois use o processo normal de cache da aplicacao.
8. Configure HSTS no proxy ou servidor HTTP. Nao aceite HTTP em producao.

`localhost` por HTTP e aceito somente em ambiente local/testing. RP ID e URL de convite nunca sao derivados livremente do header `Host`.

## Recuperacao

Depois de verificar a identidade fora do sistema, um administrador autorizado emite novo link e codigo para o mesmo associado. Se ja houver `user_id`, a nova passkey e adicionada a conta existente. O fluxo nunca substitui o vinculo e nunca cria outra conta nesse caso. Passkeys antigas permanecem ate revogacao explicita.

## Auditoria

A listagem somente leitura `Sistema > Eventos de seguranca` no Filament exige `security-events.view` e e filtrada por `tenant_id`. Superadministradores sao tratados separadamente. O nome do responsavel e resolvido por `tenant_id + user_id` via `TenantIdentityService`.

Eventos incluem criacao, envio, claim, bloqueio, consumo e revogacao de convite; cadastro, uso e revogacao de passkey; login/vinculacao Google; falhas WebAuthn; tentativa cross-tenant e anomalia de contador.

## Ameacas corrigidas

- Auto-merge por e-mail do Google: removido; `sub` e a chave do provedor.
- IDOR de convite: consultas administrativas incluem tenant e associado autorizados pelo servidor.
- Scanner de e-mail consumindo link: GET apenas redireciona e nao altera status.
- Replay: challenge e retirado da sessao na primeira tentativa.
- Roubo de link: o codigo independente continua obrigatorio e possui limite de tentativas.
- Corrida de consumo: convite, associado e credencial usam transacao, locks e indices unicos.
- Credential stuffing: limitadores combinam sessao, IP, convite, usuario e tenant conforme o fluxo.
- Host header injection: links usam somente `APP_URL` validada.
- Vazamento em logs: o auditor remove chaves sensiveis e armazena apenas hash HMAC do IP.
- Cross-tenant: nenhum `tenant_id`, `associate_id` ou `user_id` recebido no login decide a conta autenticada.

## Checklist de seguranca

- [x] Chaves privadas e biometria nunca chegam ao servidor.
- [x] `credential_id` e unico globalmente e credenciais revogadas ficam fora do login.
- [x] Token, codigo e challenge sao independentes, curtos e de uso unico.
- [x] Convites expirados, bloqueados, revogados e consumidos nao voltam a pendente.
- [x] Operacoes administrativas possuem autorizacao no backend.
- [x] Sessao e CSRF sao regenerados depois de autenticacao e alteracoes criticas.
- [x] Redirect depois do login e calculado internamente.
- [x] Conta Google com mesmo e-mail nao e unida automaticamente.
- [x] Eventos de seguranca sao isolados por tenant.
- [ ] Validar a cerimonia real em dispositivos homologados antes da liberacao: Android/Chrome, Windows Hello/Edge, iPhone/Safari, macOS/Safari e chave FIDO externa.
- [ ] Executar teste E2E com autenticador virtual no pipeline quando Playwright ou Dusk estiver disponivel.

## Testes automatizados

`tests/Feature/AccessInvitationSecurityTest.php` cobre hashes, ausencia de segredo nos logs, scanner, URL limpa, cabecalhos, bloqueio na quinta tentativa, claim unico, grant sem autenticacao, IDOR entre tenants, opcoes WebAuthn, challenge de uso unico, expiracao, revogacao, user handle aleatorio e regras de vinculacao Google.

Os validadores criptograficos de assinatura, origin, RP ID, rpIdHash, UV e contador sao fornecidos pela biblioteca WebAuthn. A homologacao ainda deve executar cerimonias reais/virtuais; mocks de JSON nao substituem uma prova criptografica completa.

## Dependencias e risco residual

As atualizacoes compativeis reduziram a auditoria Composer de 42 para 3 advisories e a auditoria npm de 9 para zero. Permanecem tres registros sobre `laravel/framework`; dois representam o mesmo problema de CRLF em validacao de e-mail e um trata de confusao de caminho em URL temporariamente assinada. A propria base de advisories marca toda a linha Laravel 11 como afetada, sem versao corrigida nessa major.

Eliminar esse risco exige um projeto separado de atualizacao para Laravel 12.61.1 ou superior, com revisao de compatibilidade do SGC e regressao completa. Nao foi feita uma troca major silenciosa nesta entrega porque ela poderia quebrar os portais e o Filament existentes. Ate a migracao, nao use entrada de e-mail nao confiavel para construir cabecalhos e evite URLs temporariamente assinadas para decisoes de seguranca sem validacao adicional do caminho.
