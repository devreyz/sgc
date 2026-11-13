# SGC Campo — atualização visual compatível

**Paletas selecionadas:** tema claro Campo (`#f4f4ef`, superfície `#fffefb`, lateral `#173c2e`, destaque `#d9f172`) e tema escuro Grafite & Ouro (`#111418`, superfície `#191e24`, lateral `#151a20`, destaque `#e7c87d`).

## Conteúdo do pacote

Os oito arquivos foram derivados dos arquivos enviados. As definições de rotas, vínculos com tenants, nomes de classes, atributos `data-*`, diretivas Blade, eventos de navegação e formulários foram preservados.

| Arquivo | Substituição sugerida |
|---|---|
| `bento.blade.php` | Local do layout `bento.blade.php` original (frequentemente `resources/views/layouts/bento.blade.php`) |
| `nav.blade.php` | Componente `<x-portal.nav>` (frequentemente `resources/views/components/portal/nav.blade.php`) |
| `app.css` | `resources/css/app.css` |
| `theme.css` | `resources/css/theme.css` |
| `design-system.css` | `resources/css/design-system.css` |
| `accounting-portal.css` | **Mesmo caminho** do `accounting-portal.css` que já existe no projeto |
| `pre-distributions.css` | **Mesmo caminho** do `pre-distributions.css` que já existe no projeto |
| `document-quick-verification.css` | **Mesmo caminho** do `document-quick-verification.css` que já existe no projeto |

> Os caminhos reais dos CSS específicos não aparecem nos uploads; mantenha as referências `@vite`, `<link>`, `@push('styles')` ou `asset(...)` existentes. Não coloque indiscriminadamente esses arquivos em `resources/css` se originalmente estão em `public/css`.

## Instalação segura

1. Crie um branch e faça backup dos oito arquivos originais. Não aplique diretamente em produção.
2. Copie os arquivos do ZIP **para os mesmos caminhos** dos arquivos originais, preservando os imports e carregamentos já existentes.
3. Reconstrua os estilos com `npm run build` (ou `npm run dev` durante o desenvolvimento).
4. Limpe as views e caches do Laravel: `php artisan view:clear` e `php artisan optimize:clear`.
5. Teste os fluxos em um ambiente de homologação. Somente publique quando os fluxos e a responsividade estiverem aprovados.

## Modo escuro

O layout Bento aplica o tema antes dos estilos carregarem e traz um botão de alternância no cabeçalho. Preferência em `localStorage['sgc-campo-theme']`; se não existir, respeita `prefers-color-scheme`. Publica o evento `sgc:theme-change` para outros scripts que precisem reagir. O atributo aplicado ao `<html>` é `data-theme="light"` ou `data-theme="dark"`.

Se houver outras páginas usando layouts diferentes de Bento, elas receberão os tokens do `theme.css`, mas precisam aplicar o mesmo atributo `data-theme` para mostrar o escuro. O controle visual incluído aqui fica especificamente no Bento.

## Compatibilidade adotada

- Mantidas as interfaces `--ui-*`, `--color-*`, `--app-*` e `--ws-*` por meio de aliases semânticos.
- Mantidos os seletores `.nav-tabs`, `.nav-tab`, `.bento-container`, `.bento-grid`, `.ui-*`, `.acc-*`, `.pd-*` e `.qv-*`.
- Mantidos os contratos de navegação: `data-nav-key`, `data-nav-event`, `bento:navigation-action`, `aria-current`, links, métodos de formulários e CSRF.
- Mantida a escolha de exibir ou ocultar entradas da navegação mobile, mas restabelecida sua visualização no tablet como menu lateral.
- Preservadas as funcionalidades de impressão, relatórios, verificação e pré-distribuições; somente os estilos desses módulos foram alterados.
- Telas muito específicas que trazem cores diretamente no próprio Blade ou em outros arquivos CSS não enviados podem precisar de ajustes localizados.

## Verificações executadas

- Parsing de CSS sem erros estruturais nos arquivos enviados.
- Lint de PHP nos arquivos Blade (verificação apenas sintática; não compila diretivas Blade).
- Comparação de contagens das principais diretivas Blade antes/depois, sem remoções.
- Testes com Chromium das paletas e do shell em 390, 820 e 1440 px, sem overflow horizontal no cenário de teste.
- Testes dos controles de alternância claro ↔ escuro e `aria-pressed`.
- Testes visuais básicos das áreas de contabilidade, pré-distribuições e verificação em ambos os temas.

**Limite:** não foi possível executar o Laravel real, carregar todas as views, verificar regras de autorização nem testar os fluxos com backend. Esses testes precisam ocorrer no projeto original.

## Checklist indispensável antes de publicar

- [ ] Menu de cada portal / tenant, inclusive links, botões, formulários e item ativo.
- [ ] Barra inferior no celular com menus longos; rolagem horizontal quando necessário.
- [ ] Drawer da conta, troca de organização, logout e notificações.
- [ ] Cadastros, edição, validações e confirmação de entregas e pré-distribuições.
- [ ] Financeiro: valores, recibos, conciliações, impressão e exports.
- [ ] Verificação por QR e camera e modais de permissão.
- [ ] Formulários, tabelas, alertas, impressões e diálogos nos dois temas.
- [ ] Telas em 320, 390, 768, 1024, 1280 e 1920 px.
- [ ] PWA (status bar / safe-area / teclado mobile) e contraste com usuário real.

## Reversão

Restaure os oito arquivos de backup e execute novamente o build do Vite e a limpeza de caches das views.
