# Arquitetura do núcleo de serviços

## Decisão central

O módulo usa um núcleo configurável, versionado e orientado a execução. `Service` representa a identidade comercial; `ServiceVersion` congela regras e campos; `ServiceOrder` agenda e identifica; `ServiceExecution` registra o realizado; linhas de composição explicam o cálculo; obrigações independentes registram o que o cliente deve e o que a organização deve ao prestador.

## Fluxo

1. A gestão cria uma versão em rascunho, configura campos, cobrança e remuneração.
2. A publicação torna a versão imutável. Alterações futuras exigem duplicação.
3. A OS guarda snapshots do serviço, beneficiário e prestador.
4. O prestador inicia, registra evidências e valores e envia para conferência.
5. O aceite manual ou automático congela a execução e sua composição.
6. São geradas, de forma idempotente, até duas obrigações: `receivable` e `payable`.
7. Pagamentos são eventos alocados às obrigações e podem ser parciais ou estornados.

## Identidade

Não foi criada uma segunda tabela concorrente de “membro”. `TenantUser` continua sendo o vínculo de acesso por organização. `Associate` e `ServiceProvider` continuam sendo identidades de negócio e aceitam operação sem usuário; acesso ao portal só existe quando o prestador está explicitamente ligado ao usuário naquele tenant. Uma unificação estrutural de identidades fica fora deste corte porque exigiria migração global de módulos maduros.

## Compatibilidade

As telas legadas que gravavam fatos financeiros de serviço foram desativadas. Dados históricos permanecem preservados. Novas ordens são distinguidas por `service_version_id` e usam exclusivamente os novos serviços de aplicação.
