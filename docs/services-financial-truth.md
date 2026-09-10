# Verdade financeira dos serviços

## Duas obrigações independentes

O valor cobrado do cliente não determina implicitamente o valor pago ao prestador. A execução validada pode gerar:

- conta a receber da organização (`receivable`);
- conta a pagar ao prestador (`payable`).

Cada obrigação tem principal, ajustes auditáveis, pagamentos próprios, saldo e estado. Receber do cliente não marca o prestador como pago e pagar o prestador não liquida o cliente.

## Cálculo do prestador

A precedência é: tarifa específica para prestador + versão do serviço; depois tarifa padrão da versão. Os métodos aceitos são valor fixo, quantidade multiplicada pela tarifa e percentual do valor-base do serviço. Se o serviço exige pagamento ao prestador e não há regra válida, o aceite é bloqueado. Reembolsos de recursos podem ser somados explicitamente ao payable.

No aceite, as linhas e a regra aplicada são congeladas com método, tarifa, percentual, origem da precedência e hash. O cálculo não é refeito quando o catálogo muda.

## Pagamento e caixa

Pagamentos aceitam parcelas, bloqueiam sobrepagamento sob lock transacional e usam chave idempotente. Conta bancária é validada no tenant. Um pagamento ao prestador gera saída de caixa; um recebimento gera entrada. Estorno cria evento e movimento inversos, preservando o histórico.

O saldo é derivado: `principal + ajustes - alocações líquidas`. Registros financeiros não são apagados; correções usam ajuste ou estorno motivado.
