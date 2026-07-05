# Briefing do Layout Principal do Sistema SGC

Este documento descreve o layout principal unificado do sistema, pensado para substituir a experiência visual atual de todos os portais, com exceção do PDV. A meta é criar uma interface bonita, moderna, responsiva e consistente, com comportamento de aplicativo nativo no mobile.

## Objetivo

- Criar um layout base único para todos os portais do sistema, exceto o PDV.
- Unificar navegação, tipografia, espaçamento, cartões, cabeçalhos, ações e estados vazios.
- Melhorar a experiência em desktop e principalmente em mobile.
- Fazer o mobile se parecer com um app nativo, com barras fixas, áreas de conteúdo bem definidas, ações claras e scroll previsível.
- Reduzir a sensação de páginas soltas e visuais inconsistentes entre módulos.

## O Que Este Layout Deve Cobrir

- Hub principal do usuário.
- Portal do associado.
- Portal de entrega.
- Portal do prestador de serviço.
- Páginas de carteira/cartão.
- Telas de autenticação e estado vazio que ainda fazem parte da jornada.
- Painel administrativo do Filament, como base visual e de navegação do backoffice.

## Fora Do Escopo

- PDV, incluindo `/pdv`, histórico, recibo e telas associadas.
- Interfaces de impressão/PDF.
- Documentos de verificação pública.

## Direção Visual

- Visual limpo, profissional, contemporâneo e estável.
- Cartões com bordas suaves, mas não exageradamente arredondadas.
- Hierarquia forte entre cabeçalho, conteúdo principal, ações e estados secundários.
- Uso consistente de cores de estado: sucesso, aviso, erro, informação e neutro.
- Evitar aparência genérica de dashboard aberto sem identidade.
- Evitar excesso de gradientes chamativos, blobs decorativos ou composição pesada.
- Em mobile, parecer um app: navegação fácil, cartões densos, botões acessíveis, sem sensação de site “encaixado”.

## Princípios De Layout

- Estrutura base com `header`, `nav`, `main`, `aside` quando necessário e `footer` opcional.
- Sidebar colapsável no desktop.
- Topbar compacta e fixa ou semi-fixa.
- Conteúdo principal com largura máxima controlada e áreas de leitura confortáveis.
- Cartões e painéis com respiro, mas sem desperdício de espaço.
- Evitar telas com elementos empilhados sem separação clara.
- Evitar sobreposição de listas, cards e barras de ação.
- Permitir rolagem interna apenas onde fizer sentido, sem roubar a rolagem da página inteira.

## Mobile Primeiro

- Layout precisa ser totalmente responsivo.
- Em telas pequenas, a navegação deve virar barra inferior, topo reduzido ou menu com acesso fácil.
- Ações principais devem ficar sempre visíveis ou facilmente alcançáveis.
- Listas devem virar cards densos e escaneáveis.
- Tabelas extensas devem ter versão mobile própria.
- Modais devem abrir como sheet inferior ou painel quase fullscreen, quando fizer sentido.
- Espaçamentos devem ser compactos, porém legíveis.
- Elementos clicáveis precisam ter área suficiente para toque.

## Padrões De Comportamento

- Scroll previsível.
- Cabeçalho e ações principais preservados quando necessário.
- Estados vazios claros e úteis.
- Carregamento com skeletons ou placeholders discretos.
- Feedback imediato para ações críticas.
- Atualização parcial de conteúdo sempre que possível, evitando reload total da página.
- Foco visual e de teclado bem definido.

## Estrutura Visual Esperada

- Header do portal com nome do sistema, nome da organização e perfil do usuário.
- Navegação principal contextual por portal.
- Área de conteúdo com título, subtítulo, filtros e ações.
- Seções organizadas em cards ou painéis, com prioridade visual clara.
- Rodapé leve ou inexistente, dependendo do contexto.

## Páginas E Rotas Que Devem Usar Este Layout

### Entrada, hub e seleção

- `/` e `home`; hub inicial, seleção de painel.
- `/tenant/select`; seleção de organização.
- `/login`; autenticação.
- `/profile`; perfil do usuário.

### Portal do associado

- `/{tenant}/associate/dashboard`; dashboard do associado.
- `/{tenant}/associate/projects`; lista de projetos.
- `/{tenant}/associate/projects/{project}`; detalhes do projeto.
- `/{tenant}/associate/deliveries`; entregas do associado.
- `/{tenant}/associate/ledger`; extrato/razão.
- `/{tenant}/associate/no-profile`; estado de perfil ausente.

### Portal de entrega

- `/{tenant}/delivery`; dashboard de entregas.
- `/{tenant}/delivery/projects`; lista de projetos.
- `/{tenant}/delivery/projects/{project}` e variações de projeto; visão de entregas do projeto.
- `/{tenant}/delivery/register/{project?}`; registro de entrega.
- `/{tenant}/delivery/all-deliveries`; lista geral de entregas.
- `/{tenant}/delivery/projects/{project}/deliveries`; histórico de entregas do projeto.
- `/{tenant}/delivery/projects/{project}/producers`; produtores do projeto.
- `/{tenant}/delivery/sheet`; seletor/gerador de fichas.

### Portal do prestador de serviço

- `/{tenant}/provider/dashboard`; dashboard.
- `/{tenant}/provider/orders`; ordens.
- `/{tenant}/provider/orders/create`; criar ordem.
- `/{tenant}/provider/orders/{order}`; visualizar ordem.
- `/{tenant}/provider/orders/{order}/start`; iniciar execução.
- `/{tenant}/provider/orders/{order}/complete`; concluir execução.
- `/{tenant}/provider/financial`; financeiro.
- `/{tenant}/provider/orders/{order}/register-payment`; registrar pagamento.
- `/{tenant}/provider/financial/request-payment/{order}`; solicitar pagamento.
- `/{tenant}/provider/works`; trabalhos.
- `/{tenant}/provider/work-form`; formulário de trabalho.
- `/{tenant}/provider/no-profile`; estado de perfil ausente.

### Carteira e cartão

- `/{tenant}/wallet`; carteira do associado.
- `/{tenant}/wallet/print-card`; impressão de cartão.

### Painel administrativo Filament

- `/admin`; painel administrativo principal.
- Recursos e páginas de administração, cadastros, financeiro, compras coletivas, projetos de venda, estoque, serviços e outras áreas do Filament.
- O layout deve dialogar com o estilo do Filament, mas pode ser mais refinado e coerente com o resto do sistema.

## Telas Que Precisam De Tratamento Especial

- Hub com seleção de painéis.
- Seleção de tenant.
- Dashboards com muitos cards e atalhos.
- Listas extensas com filtros.
- Telas de registro e distribuição, com modais e atualizações parciais.
- Telas com tabelas em desktop e cards em mobile.
- Telas de impressão, que podem manter comportamento próprio, mas ainda devem respeitar identidade visual.
- Estados de erro, vazio, sem perfil e sem acesso.

## Componentes Que Devem Ser Padronizados

- Barra superior do portal.
- Navegação lateral ou inferior.
- Títulos de página.
- Subtítulos e textos de apoio.
- Cards de resumo.
- Tabelas.
- Chips de status.
- Botões de ação.
- Filtros.
- Modais.
- Empty states.
- Toasts e alertas.
- Paginação.
- Formulários.
- Campos de busca.

## Regras De Interface

- Não criar layouts “soltos” ou genéricos.
- Não deixar listas e cards colados sem respiro visual.
- Não usar cards dentro de cards como estrutura padrão de página.
- Não deixar o conteúdo principal crescer sem limite em desktop.
- Não deixar o painel lateral empurrar a página inteira quando só o conteúdo interno deveria rolar.
- Não depender apenas de cor para indicar estado.
- Não usar botões grandes demais em áreas densas.
- Não usar linguagem visual do PDV nesse layout principal.

## Requisitos Técnicos

- CSS com variáveis de tema centralizadas.
- Componentização de cabeçalho, navegação, card, filtros, empty state, modal e toast.
- Suporte a desktop, tablet e mobile.
- Compatibilidade com múltiplos ports/rotas por tenant.
- Estrutura pronta para atualização parcial de listas e modais sem reload total.
- Manter performance aceitável em páginas com muitos registros.

## Tom De Produto

- Profissional.
- Confiável.
- Operacional.
- Moderno.
- Denso, mas legível.
- Mais parecido com sistema de trabalho real do que com site institucional.

## Prompt Base Para Implementação

```text
Crie um layout principal unificado para o sistema SGC, servindo para todos os portais exceto o PDV.

O layout deve ser responsivo, moderno, profissional e com aparência de aplicativo nativo no mobile.

Ele precisa cobrir:
- hub inicial e seleção de tenant;
- portal do associado;
- portal de entrega;
- portal do prestador de serviço;
- carteira/cartão;
- painel administrativo Filament;
- telas de autenticação e estados vazios relevantes.

Requisitos:
- header consistente com nome do sistema, tenant e usuário;
- navegação clara e contextual por portal;
- conteúdo principal com hierarquia forte;
- cards, tabelas, filtros, modais, toasts e empty states padronizados;
- mobile com navegação compacta, cartões densos e comportamento semelhante a app nativo;
- desktop com sidebar/topbar, áreas bem separadas e rolagem previsível;
- evitar sobreposição de elementos, excesso de cartões aninhados e visual genérico;
- permitir atualizações parciais de conteúdo sem reload total quando possível;
- manter o PDV fora deste layout.

Liste explicitamente as rotas e telas suportadas pelo layout e descreva como cada portal deve se comportar em desktop e mobile.
```

## Observação Final

O layout deve ser a base visual do sistema inteiro fora do PDV. A ideia é que o usuário reconheça a mesma linguagem visual em qualquer portal, mas sem perder o contexto de cada área.
