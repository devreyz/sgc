# Matriz de Constraints Financeiras

Data da revisao: 19/08/2026.

## Como obter o schema real

O engine InnoDB nao comprova a existencia de foreign keys. O comando abaixo le
`information_schema` e nao altera dados ou schema:

```bash
php artisan finance:audit-schema
php artisan finance:audit-schema --json
```

O resultado de producao deve ser anexado ao processo de deploy antes de qualquer
proposta de criacao de FK. A informacao disponivel ate agora confirma todas as 102
tabelas de producao em InnoDB, mas nao confirma suas constraints.

## Matriz esperada pelas migrations

| Tabela | Coluna | FK esperada | Indice esperado | Unique | ON DELETE | Risco se ausente |
|---|---|---|---|---|---|---|
| production_deliveries | parent_delivery_id | production_deliveries.id | sim | nao | SET NULL | filho orfao e origem fisica perdida |
| production_deliveries | associate_receipt_id | nenhuma migration de FK | sim | nao | - | ponteiro operacional do lado do membro sem barreira de banco |
| production_deliveries | billing_receipt_id | customer_billing_receipts.id | sim | nao | SET NULL | cobranca orfa |
| production_deliveries | distribution_billing_id | nenhuma migration de FK | sim | nao | - | ponteiro legado orfao |
| production_deliveries | project_payment_id | project_payments.id | sim | nao | SET NULL | ponteiro legado orfao |
| associate_receipts | tenant_id | tenants.id | sim | composto | CASCADE | documento cross-tenant/orfao |
| associate_receipts | sales_project_id | sales_projects.id | sim | composto | CASCADE | documento sem projeto |
| associate_receipts | associate_id | associates.id | sim | nao | CASCADE | documento sem titular |
| associate_receipt_payments | tenant_id | tenants.id | sim | operation_key composto | CASCADE | parcela cross-tenant |
| associate_receipt_payments | associate_receipt_id | associate_receipts.id | sim | nao | CASCADE | parcela orfa |
| associate_receipt_payments | bank_account_id | bank_accounts.id | sim | nao | SET NULL | conta invalida |
| customer_billing_receipts | tenant_id | tenants.id | sim | composto | CASCADE | cobranca cross-tenant/orfa |
| customer_billing_receipts | sales_project_id | sales_projects.id | sim | composto | SET NULL | perda do contexto corrente; snapshot deve preservar historico |
| customer_billing_receipts | customer_id | customers.id | sim | nao | SET NULL | destinatario removido exige snapshot |
| customer_billing_receipts | organization_id | organizations.id | sim | nao | SET NULL | destinatario removido exige snapshot |
| customer_receipt_payments | tenant_id | tenants.id | sim | operation_key composto | CASCADE | recebimento cross-tenant |
| customer_receipt_payments | customer_billing_receipt_id | customer_billing_receipts.id | sim | nao | CASCADE | recebimento orfao |
| customer_receipt_payments | bank_account_id | bank_accounts.id | sim | nao | SET NULL | conta invalida |
| associate_ledgers | associate_id | associates.id | sim | nao | CASCADE | lancamento sem titular |
| cash_movements | bank_account_id | bank_accounts.id | sim | nao | SET NULL | movimento sem conta corrente |
| project_fees | sales_project_id | sales_projects.id | sim | nao | RESTRICT | taxa sem projeto |
| customer_project_fees | sales_project_id | sales_projects.id | sim | nao | CASCADE | taxa de cliente sem projeto |
| receipt_number_sequences | tenant_id | tenants.id | sim | escopo/tipo/ano | CASCADE | colisao ou sequencia orfa |
| receipt_number_sequences | sales_project_id | sales_projects.id | sim | nao | CASCADE | sequencia de projeto orfa |

## Resultado local

- MySQL local antigo: tabelas financeiras em MyISAM.
- FKs reais encontradas: zero.
- Os indices nominais existem em grande parte, mas nomes terminados em `_foreign`
  nao significam que a FK exista.
- A unique de `receipt_number_sequences` existe.
- As uniques de `operation_key` somente existirao depois da migration
  `2026_08_19_000001_add_operation_keys_to_receipt_payments`.

Esse resultado local nao deve ser extrapolado para producao.

## Lacunas conhecidas nas migrations

As migrations atuais nao declaram FK para `production_deliveries.associate_receipt_id`
nem para `production_deliveries.distribution_billing_id`. Isso deve ser tratado como
decisao de schema separada, somente depois de auditar e reconciliar os ponteiros reais.
Nenhuma constraint foi criada automaticamente nesta fase.
