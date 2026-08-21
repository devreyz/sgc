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
        $add($inspection['critical_count'] === 0, 'financial_integrity_error', 'A cobrança possui inconsistências financeiras que precisam ser corrigidas.');
        $authorization = BillingAuthorization::withoutGlobalScopes()->where('tenant_id', $receipt->tenant_id)
            ->where('customer_billing_receipt_id', $receipt->id)->where('active_marker', true)->latest('sequence')->first();
        $add((bool) $authorization, 'authorization_missing', 'Esta cobrança ainda não possui autorização ativa.');
        $authorizationValid = $authorization ? $this->validity->isValid($receipt, $authorization) : false;
        if ($authorization) {
            $add($authorizationValid, 'authorization_invalid', 'A autorização está desatualizada ou inválida.');
            $add((int) data_get($authorization->snapshot, 'identity.tenant.id') === (int) $receipt->tenant_id, 'authorization_tenant_mismatch', 'A autorização não corresponde à organização desta cobrança.');
            $add((int) data_get($authorization->snapshot, 'identity.receipt.id') === (int) $receipt->id, 'authorization_receipt_mismatch', 'A autorização não corresponde a esta cobrança.');
            $expectedOrganizationId = (int) ($receipt->organization_id ?: $receipt->customer?->organization_id);
            $add($expectedOrganizationId > 0 && (int) data_get($authorization->snapshot, 'recipient.organization_id') === $expectedOrganizationId, 'recipient_mismatch', 'O destinatário autorizado não corresponde à cobrança atual.');
        }
        $projectId = $receipt->sales_project_id ? (int) $receipt->sales_project_id : null;
        $profile = $this->profiles->resolve((int) $receipt->tenant_id, $projectId)
            ?? $this->profiles->latest((int) $receipt->tenant_id, $projectId)
            ?? ($projectId ? $this->profiles->latest((int) $receipt->tenant_id, null) : null);
        $add((bool) $profile, 'fiscal_profile_missing', 'Configure a emissão fiscal para este tenant ou projeto.');
        if ($profile) {
            $this->profileChecks($receipt, $authorization, $profile, $add);
        }
        $amount = ($profile && $authorizationValid) ? $this->amount($profile, $authorization) : null;
        $add($amount !== null && bccomp($amount, '0', 4) > 0, 'fiscal_amount_unresolved', 'Defina a regra do valor fiscal antes de preparar a emissão.');

        return ['ready' => $blocks === [], 'status' => $blocks === [] ? 'ready' : 'blocked', 'blocks' => $blocks,
            'warnings' => $warnings, 'checks' => $checks, 'expected_fiscal_amount' => $amount,
            'document_type' => $profile?->document_type?->value, 'document_type_label' => $profile?->document_type?->label(),
            'authorization' => $authorization, 'profile' => $profile];
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
            $add(filled(data_get($authorization?->snapshot, 'recipient.document')), 'recipient_incomplete', 'CPF/CNPJ do destinatário não informado.');
        }
    }

    private function amount(FiscalProfile $profile, BillingAuthorization $authorization): ?string
    {
        return match ($profile->amount_source) {
            FiscalAmountSource::AUTHORIZED_GROSS => (string) data_get($authorization->snapshot, 'totals.gross'),
            FiscalAmountSource::AUTHORIZED_FINAL => (string) data_get($authorization->snapshot, 'totals.net'),
            default => null,
        };
    }
}
