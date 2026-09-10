# Segurança do módulo de serviços

Todas as consultas operacionais aplicam `tenant_id` e o middleware exige vínculo ativo do usuário com o tenant, exceto para o superadministrador. Papéis operacionais são lidos do vínculo `TenantUser`; não há autorização por papel global para prestador ou equipe local.

O portal impõe escopo pelo prestador ligado ao usuário. A gestão usa permissões específicas para catálogo, fila, revisão, aprovação, recursos, contas a receber, contas a pagar, estorno, acordos e relatórios.

IDs de rota nunca bastam para autorizar: ordem, execução, evidência, obrigação, pagamento, conta bancária e documento são recarregados no tenant corrente. Escritas críticas usam transação, locks e chaves idempotentes. Evidências não são expostas diretamente pelo armazenamento público.

O teste de contrato cobre ausência de criação automática, isolamento do prestador e locks/idempotência; a suíte funcional cobre cálculo, pagamento parcial, sobrepagamento, estorno e aceite.
