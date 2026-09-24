SGC UI Design System

Este documento é a referência oficial para criar e refatorar interfaces do SGC.

O padrão visual foi definido a partir das telas que tiveram o melhor resultado no produto:

Dashboard do associado

Workspace do projeto do associado

Essas telas são a referência. O Design System existe para reproduzir essa linguagem em outros módulos, e não para substituir o estilo delas por uma biblioteca genérica.

1. Identidade visual do SGC

O SGC deve parecer um workspace operacional, não um site institucional, landing page ou dashboard genérico de template.

A aparência deve transmitir:

organização;

confiabilidade;

densidade informacional controlada;

hierarquia clara;

pouco ruído visual;

resposta rápida a ações;

consistência entre desktop e celular.

Princípios visuais

Branco e tons neutros formam a base.

Cor aparece para comunicar módulo, ação, estado ou progresso.

Uma tela não deve parecer um mosaico de cartões coloridos.

Shells principais usam borda suave, raio de aproximadamente 12 px e sombra baixa.

Componentes internos usam raio menor, normalmente 8–9 px.

Cabeçalhos são compactos e informativos.

Ícones ajudam a reconhecer contexto, mas não substituem texto.

Listas e tabelas são preferidas quando o conteúdo é denso.

Cards soltos são usados apenas quando o registro realmente precisa de contexto próprio.

Não use glassmorphism, blur ou efeitos decorativos sem necessidade funcional.

2. Referência visual concreta

O padrão deve se aproximar do Dashboard e do Project Workspace do associado.

Shell principal

border: 1px solid var(--ui-color-border);
border-radius: var(--ui-radius-lg); /* 12px */
background: var(--ui-color-surface);
box-shadow: var(--ui-shadow-sm);

Cabeçalho de seção

altura aproximada: 62 px;

ícone: 39 × 39 px;

raio do ícone: 9 px;

título: cerca de 0.92 rem, peso forte;

descrição curta e discreta;

ação ou contador no lado direito;

fundo quase branco com diferença muito sutil de superfície.

Conteúdo

padding típico: 0.65–0.75 rem;

gap entre blocos principais: 0.78 rem;

densidade compacta, mas sem reduzir texto a ponto de prejudicar leitura;

evitar áreas vazias exageradas.

3. Paleta oficial

A paleta é a mesma consolidada nas páginas de associado.

Uso

Token

Valor base

Marca / ação principal

--ui-color-primary

#219653

Informação / navegação

--ui-color-info

#3478d4

Sucesso / pago / aprovado

--ui-color-success

#219653

Atenção / pendência

--ui-color-warning

#c38418

Perigo / erro

--ui-color-danger

#cf5050

Documento / financeiro secundário

--ui-color-violet

#8a4bd2

Preço / comparação técnica

--ui-color-cyan

#168eae

Histórico / sem ênfase

--ui-color-neutral

#64748b

Superfícies e texto

Token

Função

--ui-color-canvas

fundo geral

--ui-color-surface

seções e cards

--ui-color-surface-soft

agrupamentos leves

--ui-color-surface-muted

trilhas e fundos discretos

--ui-color-border

borda comum

--ui-color-border-strong

campos e divisões importantes

--ui-color-text

texto principal

--ui-color-text-secondary

texto auxiliar

--ui-color-text-muted

metadados

Regra mais importante sobre cor

Não escolha uma cor apenas para "deixar bonito".

Use o tom porque ele tem significado ou identidade clara.

Exemplo:

<span class="ui-icon-box" data-tone="violet">
    <i class="ph-fill ph-folder-open"></i>
</span>

Cor não deve preencher uma seção inteira sem motivo.

4. Arquitetura dos arquivos

Arquivo

Responsabilidade

resources/css/theme.css

tokens, cores, tipografia, medidas, raios e sombras

resources/css/design-system.css

componentes reutilizáveis ui-*

resources/css/app.css

entrada principal do Vite/Tailwind

CSS local da Blade

somente composição específica daquela tela

O theme.css também fornece aliases para variáveis legadas como --color-text, --color-border e --color-surface, permitindo migração gradual das telas que já seguem visualmente o padrão aprovado.

5. Convenções de classe

componente público: ui-*;

parte interna: BEM, por exemplo ui-section__header;

variante: --, por exemplo ui-btn--primary;

estado transitório: is-*;

semântica visual: data-tone;

comportamento JavaScript: data-*.

Evite usar uma classe puramente visual como seletor de JS.

6. Layout de página

ui-page

Raiz padrão:

<main class="ui-page">
    ...
</main>

A largura normal é 1380 px (86.25rem), igual ao padrão consolidado nas páginas de associado.

Variações:

ui-page--narrow

ui-page--wide

Workspace em duas colunas

Use:

<div class="ui-workspace-grid">
    <div class="ui-stack">
        ...
    </div>

    <aside class="ui-stack">
        ...
    </aside>
</div>

Em telas menores a composição vira uma coluna.

7. Cabeçalho de página

Para telas que precisam de um cabeçalho interno semelhante ao Project Workspace:

<header class="ui-page-head" data-tone="primary">
    <div class="ui-page-head__start">
        <span class="ui-icon-box ui-icon-box--lg">
            <i class="ph-fill ph-folder-open"></i>
        </span>

        <div class="ui-page-head__copy">
            <h1 class="ui-page-head__title">Projeto PNAE 2026</h1>

            <div class="ui-page-head__meta">
                <span><i class="ph ph-calendar"></i> 2026</span>
                <span><i class="ph ph-buildings"></i> Prefeitura</span>
            </div>
        </div>
    </div>

    <div class="ui-page-head__actions">
        ...
    </div>
</header>

Não transforme todo cabeçalho em uma faixa colorida. O tom aparece de forma suave.

8. Seções

A seção é o componente principal das telas.

<section class="ui-section">
    <header class="ui-section__header">
        <div class="ui-section__heading">
            <span class="ui-icon-box" data-tone="violet">
                <i class="ph-fill ph-folder-open"></i>
            </span>

            <div class="ui-section__copy">
                <h2>Projetos</h2>
                <p>Projetos em que você participa.</p>
            </div>
        </div>

        <div class="ui-section__actions">
            <span class="ui-count">4</span>
        </div>
    </header>

    <div class="ui-section__body">
        ...
    </div>
</section>

Variações

ui-section--flat: remove sombra;

ui-section--raised: use somente quando houver necessidade real de elevação;

ui-section__body--flush: conteúdo encosta na borda, útil para listas e tabelas.

9. Action List — padrão para Hub e atalhos

O Hub não deve ser uma grade de cards promocionais.

Para portais, módulos, recursos e menus operacionais, use Action List.

<nav class="ui-action-list">
    <a class="ui-action-row" data-tone="violet" href="#">
        <span class="ui-icon-box ui-icon-box--sm">
            <i class="ph-fill ph-shield-check"></i>
        </span>

        <span class="ui-action-row__copy">
            <strong class="ui-action-row__title">Administração</strong>
            <span class="ui-action-row__description">
                Configuração e controle
            </span>
        </span>

        <span class="ui-action-row__action">
            <i class="ph ph-arrow-right"></i>
        </span>
    </a>
</nav>

Esse padrão é preferível a vários ui-card independentes porque:

reduz ruído;

mantém leitura rápida;

ocupa melhor o desktop;

funciona naturalmente no mobile;

combina com a linguagem da fila/listagem do SGC.

10. Cards

Cards não são o componente padrão para tudo.

Use ui-card quando o conteúdo possuir uma unidade visual própria.

<article class="ui-card">
    ...
</article>

Para card clicável:

<a class="ui-card ui-card--interactive" data-tone="info" href="#">
    ...
</a>

O hover não deve fazer o card flutuar. A resposta visual usa fundo suave, borda e uma marca lateral discreta.

11. Botões

<button class="ui-btn" type="button">
    Cancelar
</button>

<button class="ui-btn ui-btn--primary" type="submit">
    <i class="ph ph-check"></i>
    Salvar
</button>

Regras:

no máximo uma ação principal dominante por área;

ação secundária fica branca;

perigo usa ui-btn--danger;

botão apenas com ícone usa ui-btn--icon;

não criar botões gigantes sem motivo;

não recriar botão em CSS local.

12. Ícones

Use Phosphor.

Preferência:

ph-fill para identidade de módulo e ícones de destaque;

ph regular para metadados e ações secundárias.

Tamanhos oficiais:

padrão de seção: 39 px;

pequeno: 32 px;

grande: 42 px.

O fundo do icon box pode usar o tom semântico, mas de forma suave.

13. Badges e contadores

Badge

<span class="ui-badge ui-badge--dot" data-tone="warning">
    Pendente
</span>

Badge serve para:

status;

categoria curta;

estado.

Não use badge/pill para qualquer texto pequeno.

Contador

<span class="ui-count">12 registros</span>

Use o contador em cabeçalho, não como decoração repetitiva.

14. Tabs

O visual segue o Project Workspace.

<div class="ui-tabs-wrap ui-tabs-wrap--sticky">
    <nav class="ui-tabs" role="tablist">
        <button class="ui-tab is-active" data-tone="info">
            <i class="ph ph-chart-bar"></i>
            Resumo
        </button>

        <button class="ui-tab" data-tone="violet">
            <i class="ph ph-folder-open"></i>
            Documentos
        </button>
    </nav>
</div>

Regra de responsividade

Não crie scroll horizontal fora de tabelas.

As tabs devem:

quebrar linha quando necessário;

virar grade de duas colunas em telas muito estreitas;

ou ser substituídas por navegação mais simples se a quantidade for excessiva.

15. Destaque operacional / Hero

Use ui-highlight somente para uma informação realmente importante.

É o padrão do valor principal do Dashboard e dos resumos do Workspace.

<article class="ui-highlight" data-tone="success">
    <div>
        <span class="ui-highlight__kicker">
            <i class="ph-fill ph-wallet"></i>
            Ainda a receber
        </span>

        <strong class="ui-highlight__value">
            R$ 1.240,00
        </strong>

        <p class="ui-highlight__help">
            Valor líquido que permanece pendente.
        </p>
    </div>
</article>

Não colocar vários heroes na mesma tela.

16. Métricas

<div class="ui-metrics">
    <article class="ui-metric" data-tone="info">
        <div class="ui-metric__head">
            <span class="ui-icon-box ui-icon-box--sm">
                <i class="ph-fill ph-receipt"></i>
            </span>

            <span class="ui-metric__label">Faturado no mês</span>
        </div>

        <strong class="ui-metric__value">R$ 2.500,00</strong>
    </article>
</div>

Métricas devem ser compactas. Não transformar cada número em um card grande.

17. Formulários

Classes:

ui-form

ui-form-grid

ui-field

ui-label

ui-input

ui-select

ui-textarea

ui-help

ui-error

ui-check

ui-actions

Exemplo:

<div class="ui-field ui-field--6">
    <label class="ui-label" for="name">
        Nome <span class="ui-label__required">*</span>
    </label>

    <input
        class="ui-input"
        id="name"
        name="name"
        required
    >

    <span class="ui-help">
        Nome exibido nos documentos.
    </span>
</div>

No mobile:

não usar autofocus;

input textual deve ter tamanho que não provoque zoom indesejado;

formulário vira uma coluna quando necessário.

18. Toolbar e filtros

Uma toolbar simples não precisa de um card próprio.

<div class="ui-toolbar">
    <div class="ui-toolbar__main">
        ...
    </div>

    <div class="ui-toolbar__actions">
        ...
    </div>
</div>

Quando for necessário agrupar visualmente:

<div class="ui-toolbar ui-toolbar--surface">
    ...
</div>

Evite empilhar:

seção → card → toolbar → card → input

quando uma estrutura mais simples resolve.

19. Tabelas

Use tabela quando a comparação entre várias linhas for importante.

<div class="ui-table-wrap">
    <table class="ui-table ui-table--responsive">
        <thead>
            <tr>
                <th>Produto</th>
                <th>Quantidade</th>
                <th>Situação</th>
            </tr>
        </thead>

        <tbody>
            <tr data-tone="success">
                <td class="ui-table__wide" data-label="Produto">
                    <strong class="ui-table__primary">Milho</strong>
                    <span class="ui-table__secondary">Entrega 20/09/2026</span>
                </td>

                <td class="ui-table__number" data-label="Quantidade">
                    120 kg
                </td>

                <td data-label="Situação">
                    <span class="ui-badge" data-tone="success">
                        Aprovada
                    </span>
                </td>
            </tr>
        </tbody>
    </table>
</div>

Mobile

Tabela responsiva vira cards separados por linha, com:

borda comum;

faixa lateral de 3 px pelo tom quando houver estado;

data-label para identificar cada valor;

nada de scroll horizontal, salvo tabela realmente impossível de compactar.

20. Records

Use ui-record quando cada registro precisa mostrar contexto, status e múltiplos valores próprios.

Não use ui-record apenas porque "card é bonito".

Exemplos adequados:

entrega;

distribuição;

comprovante;

ordem com vários estados.

Para listagem densa simples, prefira tabela ou action list.

21. Alertas

<div class="ui-alert" data-tone="warning">
    <span class="ui-icon-box ui-icon-box--sm">
        <i class="ph ph-warning"></i>
    </span>

    <div class="ui-alert__copy">
        <strong>Atenção.</strong>
        O limite deste projeto está próximo.
    </div>
</div>

Alerta deve ser contextual e curto.

22. Estado vazio, erro e carregamento

Use:

ui-state

ui-state__content

ui-state__icon

ui-skeleton

O vazio padrão é simples e branco. Não transforme o estado vazio em um grande painel cinza.

23. Dialog

Use <dialog> com:

ui-dialog

ui-dialog__header

ui-dialog__title

ui-dialog__body

ui-dialog__footer

Não use blur no backdrop.

Ao abrir dialogs, sheets ou overlays:

Escape fecha o topo;

no Android/browser Back, o topo deve fechar antes da navegação da página;

restaure foco quando possível.

24. CSS local

CSS local é permitido para:

grid exclusivo daquela página;

gráfico ou visualização especializada;

dimensões vindas de dados;

comportamento específico;

integração documentada com legado.

CSS local não deve recriar:

botão;

input;

badge;

seção;

action list;

tabela genérica;

estado vazio;

dialog.

Exemplo correto

.my-page .project-layout {
    display: grid;
    grid-template-columns: minmax(0, 1.4fr) 320px;
    gap: var(--ui-page-gap);
}

25. Antipadrões

Evite:

card para cada informação;

vários cards coloridos lado a lado sem hierarquia;

gradientes decorativos grandes;

glassmorphism;

backdrop-filter: blur(...);

border-radius excessivo;

pill em todos os rótulos;

botão com cor forte para ação secundária;

texto excessivamente pequeno;

iconografia sem rótulo;

excesso de texto explicativo;

scroll horizontal em tabs, filtros ou cards;

espaços vazios grandes no desktop;

animação de card subindo no hover;

transformar tela de trabalho em landing page.

26. Responsividade

Desktop

Aproveite largura.

Não concentre tudo em uma coluna estreita quando houver espaço.

Tablet

Reduza colunas antes de reduzir texto.

Mobile

shell continua claro e organizado;

cabeçalho reduz altura;

descrição secundária pode ser ocultada;

ações com texto podem virar ícone quando o significado continuar claro;

tabelas viram cards;

grids viram uma coluna;

não permitir overflow lateral indevido;

evitar autofocus.

Referência mínima: 360 px.

27. Migração do legado

A migração deve ser incremental.

Aliases de tema foram mantidos para views que ainda usam:

--color-text

--color-text-secondary

--color-text-muted

--color-border

--color-border-strong

--color-surface

--color-surface-soft

--shadow-sm

Isso permite migrar primeiro a estrutura sem destruir as páginas que já estão visualmente corretas.

Não faça substituição global cega de classes.

28. Ordem recomendada

tokens do theme.css;

shell e cabeçalho de seção;

botões e inputs;

badges e estados;

action lists;

tabs;

métricas e progresso;

tabelas;

records;

dialogs;

remoção gradual do CSS legado redundante.

29. Checklist

Antes de concluir uma tela:

parece parte do mesmo produto que o Dashboard do associado?

parece parte do mesmo produto que o Project Workspace?

a base visual é branca/neutra?

a cor está sendo usada com significado?

há menos cards do que realmente necessário?

seções principais usam aproximadamente 12 px de raio?

elementos internos usam aproximadamente 8–9 px?

sombra é leve?

cabeçalho de seção é compacto?

ícones ajudam a hierarquia sem substituir texto?

há no máximo uma ação principal dominante por área?

listas densas usam tabela ou action list?

não existe scroll horizontal indevido?

não existe blur de modal?

não existe animação decorativa exagerada?

mobile funciona em 360 px?

foco de teclado permanece visível?

JS usa data-* para comportamento?

alterações visuais não removeram lógica existente?

30. Instrução curta para IA

Ao criar ou refatorar uma interface do SGC, use o Dashboard do associado e o Project Workspace como referência visual principal. O produto deve parecer um workspace operacional: superfícies brancas, bordas suaves, raio de 12 px nos shells, 8–9 px em componentes internos, sombra leve, cabeçalhos compactos e ícones com fundos suaves. Use cor somente para identidade, estado, progresso ou ação. Prefira seções, action lists e tabelas a grades de cards. Não use glass, blur, excesso de pills, animações flutuantes ou scroll horizontal fora de tabelas. Reutilize ui-*, use data-tone para semântica, preserve data-* usados por JavaScript e mantenha compatibilidade funcional durante a migração.