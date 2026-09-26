<?php

namespace App\Services\Accounting;

use App\Enums\FiscalAmountSource;
use App\Models\BillingAuthorization;
use App\Models\CustomerBillingReceipt;
use App\Models\FiscalProfile;

class FiscalGateService
{
    public function __construct(private readonly AccountingProcessIntegrityService $integrity,
        private readonly BillingAuthorizationValidityService $validity, private readonly FiscalProfileService $profiles) {}

    public function evaluate(CustomerBillingReceipt $receipt, ?int $expectedTenantId = null): array
    {
        $blocks = [];
        $warnings = [];
        $checks = [];
        $add = function (bool $ok, string $code, string $message) use (&$blocks, &$checks): void {
            $checks[] = ['code' => $code, 'passed' => $ok];
            if (! $ok) {
                $blocks[] = ['code' => $code, 'message' => $message];
            }
        };
        $add($expectedTenantId === null || (int) $receipt->tenant_id === $expectedTenantId, 'tenant_mismatch', 'A cobrança não pertence à organização atual.');
        $inspection = $this->integrity->inspect($receipt);
        $add($inspection['blocking_count'] === 0, 'financial_integrity_error', 'A cobrança ainda possui dados financeiros que precisam ser concluídos ou corrigidos.');
        $receipt->loadMissing(['tenant', 'organization', 'customer.organization']);
        $authorization = BillingAuthorization::withoutGlobalScopes()->where('tenant_id', $receipt->tenant_id)
            ->where('customer_billing_receipt_id', $receipt->id)->where('active_marker', true)->latest('sequence')->first();
        $authorizationValid = $authorization ? $this->validity->isValid($receipt, $authorization) : false;
        $fiscalAuthorization = $authorizationValid ? $authorization : null;
        $checks[] = ['code' => 'buyer_authorization_optional', 'passed' => true];
        if (! $authorization) {
            $warnings[] = ['code' => 'buyer_authorization_not_sent', 'message' => 'A folha será gerada sem autorização eletrônica da compradora.'];
        }
        if ($authorization) {
            $checks[] = ['code' => 'buyer_authorization_current', 'passed' => $authorizationValid];
            if (! $authorizationValid) {
                $warnings[] = ['code' => 'buyer_authorization_outdated', 'message' => 'A autorização eletrônica existente está desatualizada e não será usada na folha.'];
            }
        }
        $projectId = $receipt->sales_project_id ? (int) $receipt->sales_project_id : null;
        $profile = $this->profiles->resolve((int) $receipt->tenant_id, $projectId)
            ?? $this->profiles->latest((int) $receipt->tenant_id, $projectId)
            ?? ($projectId ? $this->profiles->latest((int) $receipt->tenant_id, null) : null);
        $add((bool) $profile, 'fiscal_profile_missing', 'Configure a emissão fiscal para este tenant ou projeto.');
        if ($profile) {
            $this->profileChecks($receipt, $fiscalAuthorization, $profile, $add);
        }
        $amount = $profile ? $this->amount($profile, $fiscalAuthorization, $receipt) : null;
        $add($amount !== null && bccomp($amount, '0', 4) > 0, 'fiscal_amount_unresolved', 'Defina a regra do valor fiscal antes de preparar a emissão.');

        return ['ready' => $blocks === [], 'status' => $blocks === [] ? 'ready' : 'blocked', 'blocks' => $blocks,
            'warnings' => $warnings, 'checks' => $checks, 'expected_fiscal_amount' => $amount,
            'document_type' => $profile?->document_type?->value, 'document_type_label' => $profile?->document_type?->label(),
            'authorization' => $authorization, 'profile' => $profile,
            'snapshot' => $fiscalAuthorization?->snapshot ?? $this->receiptSnapshot($receipt)];
    }

    private function profileChecks(CustomerBillingReceipt $receipt, ?BillingAuthorization $authorization, FiscalProfile $profile, callable $add): void
    {
        $add($profile->status === 'active' && $profile->active_marker, 'fiscal_profile_inactive', 'A configuração fiscal não está ativa.');
        $add((bool) $profile->document_type, 'document_type_missing', 'Informe o tipo de documento esperado.');
        $add((bool) $profile->amount_source, 'fiscal_amount_source_missing', 'Informe a origem do valor fiscal esperado.');
        $tenant = $receipt->tenant;
        if ($profile->require_issuer_tax_id) {
            $add(filled($tenant?->cnpj), 'issuer_incomplete', 'CNPJ do emitente não informado.');
        }
        if ($profile->require_issuer_address) {
            $add(filled($tenant?->address) && filled($tenant?->city) && filled($tenant?->state), 'issuer_address_incomplete', 'Endereço fiscal do emitente incompleto.');
        }
        if ($profile->require_recipient_tax_id) {
            $recipientDocument = data_get($authorization?->snapshot, 'recipient.document')
                ?: $receipt->organization?->cnpj
                ?: $receipt->customer?->cnpj;
            $add(filled($recipientDocument), 'recipient_incomplete', 'CPF/CNPJ do destinatário não informado.');
        }
    }

    private function amount(FiscalProfile $profile, ?BillingAuthorization $authorization, CustomerBillingReceipt $receipt): ?string
    {
        return match ($profile->amount_source) {
            FiscalAmountSource::AUTHORIZED_GROSS => (string) (data_get($authorization?->snapshot, 'totals.gross') ?? $receipt->total_gross),
            FiscalAmountSource::AUTHORIZED_FINAL => (string) (data_get($authorization?->snapshot, 'totals.net') ?? $receipt->total_net),
            default => null,
        };
    }

    private function receiptSnapshot(CustomerBillingReceipt $receipt): array
    {
        return [
            'identity' => [
                'tenant' => ['id' => (int) $receipt->tenant_id],
                'receipt' => ['id' => (int) $receipt->id],
                'project' => ['id' => $receipt->sales_project_id, 'name' => $receipt->project?->title],
                'period' => ['from' => $receipt->from_date?->format('d/m/Y'), 'to' => $receipt->to_date?->format('d/m/Y')],
            ],
            'recipient' => [
                'organization_id' => $receipt->organization_id,
                'name' => $receipt->recipient_name,
                'document' => $receipt->organization?->cnpj ?: $receipt->customer?->cnpj,
                'address' => $receipt->organization?->address ?: $receipt->customer?->address,
                'city' => $receipt->organization?->city ?: $receipt->customer?->city,
                'state' => $receipt->organization?->state ?: $receipt->customer?->state,
            ],
            'lines' => collect($receipt->documentLines())->map(fn (array $line): array => [
                'product' => ['id' => $line['product_id'] ?? null, 'name' => $line['product'] ?? 'Produto não identificado', 'unit' => $line['unit'] ?? ''],
                'quantity' => (string) ($line['quantity'] ?? 0),
                'unit_price' => (string) ($line['unit_price'] ?? 0),
                'gross' => (string) ($line['document_gross'] ?? $line['document_amount'] ?? 0),
                'net' => (string) ($line['document_amount'] ?? 0),
            ])->values()->all(),
            'totals' => ['gross' => (string) $receipt->total_gross, 'fees' => (string) $receipt->total_fees, 'net' => (string) $receipt->total_net],
            'document' => ['notes' => (string) $receipt->notes],
        ];
    }
}
