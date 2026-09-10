# Progresso e gates

## Entregue

- catálogo versionado, presets e formulário configurável;
- portal próprio do prestador e workspace de gestão;
- execução, evidências privadas e recursos estruturados;
- composição congelada e obrigações independentes;
- cálculo correto da remuneração padrão ou específica do prestador;
- pagamentos parciais, idempotência, caixa, estorno e ajustes;
- solicitações de repasse, planos, negociação e documentos;
- relatórios derivados, permissões, políticas e isolamento por tenant;
- comando de reset seguro do piloto;
- testes funcionais, de contrato e suíte concorrente MySQL.

## Gates de validação

- Gate A — segurança e escopo: coberto por testes de contrato e regressão de convites.
- Gate B — verdade financeira: coberto por cenários de cálculo, parcial, total, sobrepagamento e estorno.
- Gate C — imutabilidade e idempotência: coberto no fluxo de aceite e nas operações financeiras.
- Gate D — concorrência real: a suíte MySQL/InnoDB existe e deve rodar no CI ou ambiente com MySQL; SQLite não comprova locks de linha.
- Gate E — migração completa: deve ser executada em clone MySQL antes da produção. A suíte SQLite integral do repositório é bloqueada por uma migration antiga que usa `MODIFY`, não por esta fundação.

## Riscos residuais

Antes de produção, executar migration, suíte completa e reset em clone MySQL; revisar perfis reais de permissão; homologar templates documentais e validar o volume dos índices com dados representativos.
