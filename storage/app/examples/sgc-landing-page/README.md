# SGC — Landing Page

Landing page estática completa do **Sistema de Gestão Cooperativa**, com identidade visual em verde, roxo e laranja pastel.

## Arquivos principais

- `index.html` — página completa
- `assets/styles.css` — Tailwind CSS já compilado; não depende de CDN
- `assets/app.js` — menu mobile, animações de entrada e formulário demonstrativo
- `assets/sgc-symbol.png` — símbolo principal com fundo transparente
- `assets/logo-horizontal.webp` — logo horizontal
- `assets/sgc-desktop.webp` — captura da interface desktop
- `assets/sgc-mobile.webp` — captura da interface mobile
- `assets/splash-dark.webp` — splash screen escura
- `assets/favicon.ico` e variações PNG
- `site.webmanifest` — manifest básico para PWA
- `src.css` e `build-tailwind.js` — fontes usadas para gerar o CSS

## Paleta utilizada

- Verde principal: `#22B573`
- Roxo: `#8E7CCB`
- Laranja pastel: `#FFB266`
- Grafite / hero: `#101923`
- Fundo claro: `#F7F9F8`

## Como visualizar

Abra `index.html` diretamente no navegador ou sirva a pasta com qualquer servidor HTTP.

Exemplo:

```bash
python -m http.server 8080
```

Depois acesse `http://localhost:8080`.

## Formulário

O formulário de demonstração está propositalmente sem endpoint externo. Ele valida os campos no front-end e mostra um aviso. Antes de publicar, conecte o `#demoForm` ao backend/API do SGC ou ao serviço de formulários que desejar.

## Tailwind

O CSS já está compilado e funciona offline. Para recompilar usando a instalação local do Tailwind utilizada na criação:

```bash
node build-tailwind.js
```

Se for mover o projeto para outro ambiente, você pode substituir esse processo pelo seu pipeline normal com Tailwind/Vite.

## Central Legal e LGPD

O pacote agora inclui uma central jurídica completa em `legal/`:

- `legal/index.html` — Central Legal e de Privacidade
- `legal/privacidade.html` — Política de Privacidade
- `legal/termos.html` — Termos de Uso e Serviço
- `legal/cookies.html` — Política de Cookies
- `legal/direitos.html` — Direitos do Titular (LGPD)
- `legal/exclusao.html` — Exclusão de Conta e Dados
- `legal/uso-aceitavel.html` — Política de Uso Aceitável
- `legal/seguranca.html` — Segurança e Divulgação Responsável
- `legal/subprocessadores.html` — Subprocessadores e Fornecedores
- `legal/dpa.html` — modelo de Adendo de Tratamento de Dados (noindex)
- `legal/acessibilidade.html` — Declaração de Acessibilidade

### Configuração jurídica em um único arquivo

Preencha `assets/legal-config.js` antes de publicar. Campos ainda não configurados aparecem destacados nas páginas para evitar publicação acidental de dados inventados.

### Consentimento de cookies

A landing e as páginas legais possuem banner de consentimento com:

- necessários sempre ativos;
- preferências opcionais;
- análise opcional;
- marketing opcional;
- rejeição de opcionais;
- consentimento granular;
- alteração posterior das preferências;
- armazenamento local da escolha.

Nenhuma ferramenta de analytics ou marketing é carregada por padrão.

Leia também `LEGAL-CHECKLIST.md` antes de colocar a página em produção.
