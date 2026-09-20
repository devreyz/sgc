# Conferência pré-produção — 19/09/2026

## Resultado

O código corrigido passou na suíte completa: 395 testes e 1.885 asserções, com 12 testes de concorrência dependentes de MySQL ignorados pelo ambiente atual. Também passou na compilação do front-end e na geração completa do APK Android de depuração. As auditorias Composer e npm não apresentaram vulnerabilidades conhecidas após a atualização das dependências.

O ambiente atual **não deve ser promovido sem tratar os itens operacionais abaixo**. Eles dependem de dados, credenciais ou decisão humana e não podem ser corrigidos automaticamente sem risco contábil ou envio indevido de documentos.

## Bloqueadores operacionais

1. Produção deve usar `APP_ENV=production`, `APP_DEBUG=false`, HTTPS, `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`, `SESSION_SAME_SITE=lax` e `SESSION_ENCRYPT=true`.
2. O Firebase/FCM está desativado no ambiente conferido (`FCM_ENABLED=false`, projeto e credencial ausentes). Portanto, notificações Android não serão entregues até a configuração do HTTP v1 e o vínculo de ao menos um aparelho.
3. O Google Drive está conectado e já possui documentos sincronizados, porém restaram tarefas pendentes. Antes do corte, execute um worker `documents` no ambiente autorizado e confirme `drive:diagnose` sem fila nem erros. Não processe a fila contra uma conta cujo destino não tenha sido conferido.
4. A auditoria financeira encontrou 28 alertas históricos e nenhum erro crítico. Os alertas incluem dois movimentos de caixa apontando para a mesma venda PDV e divergências entre snapshots de comprovantes e vínculos atuais. Preserve os snapshots e faça reconciliação humana antes de estornar, consolidar ou regenerar esses documentos.
5. Existe uma ordem versionada sem nome de beneficiário no snapshot. Conferir antes de emitir documento definitivo.

## Fluxo contábil conferido

- Rascunho sem itens ou valores é classificado como **preparação**, não como erro crítico.
- A emissão e o envio para autorização continuam bloqueados enquanto houver dados obrigatórios incompletos.
- Erro crítico fica reservado a quebra estrutural: tenant/projeto/destinatário incompatível, linha financeira sem origem ou snapshot fechado inválido.
- Nos dados conferidos existem 12 rascunhos a completar e zero processos com erro crítico.
- O painel apresenta o fluxo real: preparação, autorização, emissão fiscal, recebimento e prestação de contas.

## Comandos de liberação

```text
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan finance:audit-integrity
php artisan services:audit-foundation
php artisan drive:diagnose
php artisan notifications:diagnose --tenant=ID_OU_SLUG
composer audit --locked
npm audit --omit=dev
```

Mantenha workers separados para `documents`, `notifications` e a fila padrão. Valide backup restaurável do banco e da conta de armazenamento antes do corte.

## Controles conferidos

- QR Code usa UUID aleatório, rota limitada, consulta pública mínima e detalhes/ações protegidos por organização e permissão.
- Recibos financeiros gerais e obrigações de serviço passaram a ter identidade verificável; o QR não executa pagamentos por si só.
- Pagamentos e estornos usam transações, bloqueio de linha e chaves de idempotência nos fluxos verificados.
- Evidências e anexos validam tipo/tamanho no servidor, usam armazenamento privado ou Google Drive explicitamente selecionado e imagens são normalizadas.
- A câmera Android usa o aplicativo nativo, URI privada temporária, normalização de orientação, redimensionamento e limite de tamanho; não há captura em segundo plano.
- O visualizador PDF web usa o blob sem parâmetros incompatíveis e oferece abertura em nova aba como fallback.
- O service worker não responde por páginas HTML autenticadas; limita o cache à rota offline e recursos estáticos não versionados, isolados pela versão da aplicação.
- Notificações de comprovantes apontam para a página estável de verificação. A rota recebida pelo APK é restrita ao formato interno de abertura de notificação.
- Consentimentos opcionais são versionados, revogáveis e desativados por padrão. Declarações de comprovantes não são apresentadas como consentimento geral de tratamento de dados.

## Referências LGPD

- Lei nº 13.709/2018 (LGPD).
- Guia de Cookies e Dados Pessoais da ANPD.
- Resolução CD/ANPD nº 19/2024 sobre transferência internacional de dados.

Este registro é uma conferência técnica e não substitui parecer jurídico, contábil ou fiscal da organização.
