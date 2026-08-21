# SGC — Checklist jurídico antes da publicação

Os documentos já estão estruturados, mas **não publique sem preencher e validar os dados reais da operação**.

## 1. Identidade jurídica

Edite apenas `assets/legal-config.js` e preencha:

- `providerName` — razão social ou nome do responsável pelo serviço;
- `providerDocument` — CNPJ ou CPF, conforme o modelo jurídico;
- `providerAddress` — endereço comercial/contato aplicável;
- `privacyEmail` — canal para solicitações LGPD;
- `supportEmail` — suporte geral;
- `securityEmail` — divulgação responsável de vulnerabilidades;
- `dpoName` e `dpoEmail` — encarregado, quando aplicável;
- `jurisdiction` — foro previsto no instrumento contratual, quando juridicamente permitido;
- `hostingRegion` — país/região real de hospedagem.

## 2. Fornecedores e subprocessadores

Preencha o array `subprocessors` em `assets/legal-config.js` apenas com fornecedores realmente usados.

Revise, por exemplo, se existirem:

- hospedagem/cloud;
- e-mail transacional;
- armazenamento/backup;
- monitoramento de erros e logs;
- meios de pagamento;
- autenticação social;
- analytics;
- suporte/chat;
- OCR/IA/APIs de terceiros.

Para cada fornecedor, informe finalidade, país/região e categorias gerais de dados envolvidas.

## 3. Cookies e rastreamento

A landing entregue **não carrega analytics nem marketing por padrão**.

Se adicionar Google Analytics, Meta Pixel ou outro rastreador:

1. não carregue o script antes do consentimento quando a base aplicável for consentimento;
2. consulte `window.SGCConsent.has('analytics')` ou `window.SGCConsent.has('marketing')`;
3. atualize `legal/cookies.html` com nome do fornecedor, finalidade e retenção aproximada;
4. atualize `legal/subprocessadores.html` / configuração quando houver tratamento por terceiro;
5. teste Aceitar, Recusar e Preferências em navegador limpo.

## 4. Fluxo LGPD

Defina internamente:

- quem recebe solicitações de titulares;
- como verificar identidade sem coletar dados excessivos;
- como localizar dados por usuário/tenant;
- como exportar, corrigir, bloquear, anonimizar e excluir;
- como encaminhar pedidos à organização controladora quando o SGC atuar como operador;
- como documentar a resposta;
- quais dados precisam ser mantidos por obrigação legal, segurança ou exercício regular de direitos.

## 5. Exclusão de conta

A página pública está em `legal/exclusao.html`.

Antes de divulgar em loja de aplicativos ou produção:

- configure o e-mail de privacidade;
- documente o fluxo interno de exclusão;
- defina o que é exclusão de acesso versus exclusão de registros históricos;
- valide dependências entre conta, organização, documentos e movimentações;
- teste o processo ponta a ponta.

## 6. Segurança

A página `legal/seguranca.html` foi escrita para não prometer controles inexistentes.

Antes de publicar, valide quais controles realmente existem, incluindo:

- autenticação e recuperação de conta;
- isolamento multi-tenant;
- autorização/roles;
- CSRF e proteção de sessão;
- rate limiting e proteção contra brute force;
- logs de auditoria;
- gestão de segredos;
- backups e restauração;
- atualização de dependências;
- resposta a incidentes;
- canal de vulnerabilidades.

Nunca publique afirmações como “100% seguro” ou “criptografia em tudo” sem validação técnica.

## 7. Contratos B2B

`legal/dpa.html` é **modelo de Adendo de Tratamento de Dados** e está marcado `noindex`.

Antes de usá-lo contratualmente:

- alinhe com o contrato comercial;
- defina controlador e operador por fluxo;
- descreva escopo, duração e categorias de dados;
- ajuste subprocessadores;
- defina aviso de incidentes;
- defina retorno/exclusão após término;
- valide transferências internacionais;
- faça revisão jurídica.

## 8. Retenção

Substitua descrições genéricas por períodos e critérios compatíveis com:

- contratos;
- obrigações fiscais/contábeis aplicáveis;
- documentos da organização;
- logs de segurança;
- suporte;
- backups;
- prevenção a fraude e exercício regular de direitos.

Não use um prazo arbitrário apenas para preencher a política.

## 9. Formulários

O formulário comercial da landing e os formulários LGPD do pacote não enviam dados para banco de dados por padrão.

Ao conectar um backend:

- proteja com CSRF quando aplicável;
- valide dados no servidor;
- aplique rate limiting;
- minimize os campos;
- registre consentimento somente quando ele for realmente a base usada;
- não envie dados sensíveis por e-mail sem necessidade;
- atualize a Política de Privacidade para refletir o fluxo real.

## 10. Revisão final

Antes de produção:

- testar todos os links do rodapé;
- testar banner de cookies em navegador limpo;
- testar alteração posterior das preferências;
- verificar páginas mobile;
- confirmar que não existe texto `PREENCHER:` visível;
- revisar fornecedores;
- revisar termos comerciais;
- revisar política com profissional jurídico familiarizado com LGPD e com o modelo de negócio do SGC.
