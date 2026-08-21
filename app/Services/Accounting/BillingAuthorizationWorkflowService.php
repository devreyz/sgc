<?php

namespace App\Services\Accounting;

use App\Enums\BillingAuthorizationStatus;
use App\Enums\CustomerReceiptStatus;
use App\Exceptions\BillingAuthorizationBlockedException;
use App\Models\BillingAuthorization;
use App\Models\CustomerBillingReceipt;
use App\Models\OrganizationAuthorizedEmail;
use App\Models\ProductionDelivery;
use App\Models\User;
use App\Services\FinancialDistributionInvariantService;
use App\Services\TenantIdentityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BillingAuthorizationWorkflowService
{
    public function __construct(
        private readonly BillingAuthorizationSnapshotService $snapshots,
        private readonly BillingAuthorizationValidityService $validity,
        private readonly BillingAuthorizationNotificationService $notifications,
        private readonly AccountingProcessIntegrityService $integrity,
        private readonly FinancialDistributionInvariantService $distributionIntegrity,
        private readonly TenantIdentityService $identities,
    ) {}

    public function send(CustomerBillingReceipt $receipt, User $actor, string $operationKey): BillingAuthorization
    {
        $operationKey = strtolower(trim($operationKey));
        if (! Str::isUuid($operationKey)) {
            throw new BillingAuthorizationBlockedException([
                $this->issue('invalid_operation_key', 'Reabra a ação e tente novamente.'),
            ]);
        }

        $resent = false;
        $authorization = DB::transaction(function () use ($receipt, $actor, $operationKey, &$resent): BillingAuthorization {
            $lockedReceipt = CustomerBillingReceipt::withoutGlobalScopes()
                ->where('tenant_id', $receipt->tenant_id)
                ->lockForUpdate()->findOrFail($receipt->id);
            $lockedReceipt->loadMissing('project');

            $existing = BillingAuthorization::withoutGlobalScopes()
                ->where('tenant_id', $lockedReceipt->tenant_id)
                ->where('operation_key', $operationKey)
                ->lockForUpdate()->first();
            if ($existing) {
                if ((int) $existing->customer_billing_receipt_id === (int) $lockedReceipt->id) {
                    return $existing;
                }
                throw new BillingAuthorizationBlockedException([
                    $this->issue('operation_key_reused', 'A chave técnica já foi usada em outro processo.'),
                ]);
            }

            $organization = $this->snapshots->organizationFor($lockedReceipt);
            $rounds = BillingAuthorization::withoutGlobalScopes()
                ->where('tenant_id', $lockedReceipt->tenant_id)
                ->where('customer_billing_receipt_id', $lockedReceipt->id)
                ->where('organization_id', $organization->id)
                ->orderBy('sequence')->lockForUpdate()->get();
            if ($rounds->contains(fn (BillingAuthorization $round): bool => (bool) $round->active_marker)) {
                throw new BillingAuthorizationBlockedException([
                    $this->issue('active_round_exists', 'Já existe uma versão aguardando resposta ou autorizada.'),
                ]);
            }

            $distributions = ProductionDelivery::withoutGlobalScopes()
                ->where('tenant_id', $lockedReceipt->tenant_id)
                ->where('billing_receipt_id', $lockedReceipt->id)
                ->orderBy('id')->lockForUpdate()->get();
            $issues = $this->sendIssues($lockedReceipt, $organization->id, $distributions);
            if ($issues !== []) {
                throw new BillingAuthorizationBlockedException($issues, 'Não é possível enviar esta cobrança.');
            }

            $snapshot = $this->snapshots->build($lockedReceipt, $distributions);
            $snapshotIssues = $this->snapshotIssues($snapshot);
            if ($snapshotIssues !== []) {
                throw new BillingAuthorizationBlockedException($snapshotIssues, 'Não é possível enviar esta cobrança.');
            }
            $hash = $this->snapshots->hash($snapshot);
            $sequence = ((int) $rounds->max('sequence')) + 1;
            $resent = $sequence > 1;
            $authorization = BillingAuthorization::withoutGlobalScopes()->create([
                'tenant_id' => $lockedReceipt->tenant_id,
                'customer_billing_receipt_id' => $lockedReceipt->id,
                'organization_id' => $organization->id,
                'sequence' => $sequence,
                'status' => BillingAuthorizationStatus::SENT,
                'active_marker' => true,
                'snapshot_version' => BillingAuthorizationSnapshotService::VERSION,
                'snapshot' => $snapshot,
                'snapshot_hash' => $hash,
                'current_hash' => $hash,
                'operation_key' => $operationKey,
                'sent_by' => $actor->id,
                'sent_by_name' => $this->identities->displayName((int) $lockedReceipt->tenant_id, (int) $actor->id),
                'sent_at' => now(),
            ]);

            activity()->performedOn($authorization)->causedBy($actor)
                ->withProperties([
                    'tenant_id' => $authorization->tenant_id,
                    'receipt_id' => $lockedReceipt->id,
                    'organization_id' => $organization->id,
                    'sequence' => $sequence,
                    'snapshot_hash' => $hash,
                    'operation_key' => $operationKey,
                ])->log($resent ? 'Cobrança reenviada para autorização' : 'Cobrança enviada para autorização');

            return $authorization;
        }, 5);

        if ($authorization->wasRecentlyCreated) {
            DB::afterCommit(fn () => $this->notifications->sent($authorization->fresh(['receipt.tenant']), $resent));
        }

        return $authorization;
    }

    public function authorize(BillingAuthorization $authorization, User $user, OrganizationAuthorizedEmail $access, ?string $message = null): BillingAuthorization
    {
        $stale = false;
        $changed = false;
        $result = DB::transaction(function () use ($authorization, $user, $access, $message, &$stale, &$changed): BillingAuthorization {
            $round = $this->lockBuyerRound($authorization, $user, $access);
            if ($round->status === BillingAuthorizationStatus::AUTHORIZED) {
                return $round;
            }
            if (! $round->status->isRespondable()) {
                throw new BillingAuthorizationBlockedException([$this->issue('already_responded', 'Esta versão já recebeu uma resposta.')]);
            }

            $currentHash = $this->validity->currentHash($round->receipt, true);
            $currentStateIsInvalid = ! $this->validity->hasCompatibleFinancialStatus($round->receipt)
                || $this->integrity->inspect($round->receipt)['critical_count'] > 0;
            if ($currentStateIsInvalid || ! hash_equals((string) $round->snapshot_hash, $currentHash)) {
                $this->markInvalidated($round, $currentHash, null, $currentStateIsInvalid
                    ? 'A cobrança deixou de estar íntegra ou em situação compatível antes da resposta.'
                    : 'A cobrança mudou antes da resposta da organização.');
                $stale = true;

                return $round;
            }

            $round->forceFill([
                'status' => BillingAuthorizationStatus::AUTHORIZED,
                'response_decision' => BillingAuthorizationStatus::AUTHORIZED->value,
                'responded_by' => $user->id,
                'responded_by_name' => $this->buyerName($access),
                'responded_at' => now(),
                'response_message' => $this->cleanMessage($message),
                'current_hash' => $currentHash,
            ])->save();
            $changed = true;
            activity()->performedOn($round)->causedBy($user)
                ->withProperties(['tenant_id' => $round->tenant_id, 'receipt_id' => $round->customer_billing_receipt_id, 'sequence' => $round->sequence])
                ->log('Faturamento autorizado pela organização compradora');

            return $round;
        }, 5);

        if ($stale) {
            DB::afterCommit(fn () => $this->notifications->invalidated($result->fresh(['receipt.tenant'])));
            throw new BillingAuthorizationBlockedException([$this->issue('stale_round', 'A cobrança foi alterada. Aguarde o envio de uma nova versão.')]);
        }
        if ($changed) {
            DB::afterCommit(fn () => $this->notifications->responded($result->fresh(['receipt.tenant']), true));
        }

        return $result;
    }

    public function requestCorrection(BillingAuthorization $authorization, User $user, OrganizationAuthorizedEmail $access, string $reason): BillingAuthorization
    {
        $reason = $this->cleanMessage($reason);
        if ($reason === '') {
            throw new BillingAuthorizationBlockedException([$this->issue('reason_required', 'Informe o que precisa ser corrigido.')]);
        }
        $changed = false;
        $stale = false;
        $result = DB::transaction(function () use ($authorization, $user, $access, $reason, &$changed, &$stale): BillingAuthorization {
            $round = $this->lockBuyerRound($authorization, $user, $access);
            if ($round->status === BillingAuthorizationStatus::CORRECTION_REQUESTED) {
                return $round;
            }
            if (! $round->status->isRespondable()) {
                throw new BillingAuthorizationBlockedException([$this->issue('already_responded', 'Esta versão já recebeu uma resposta.')]);
            }

            $currentHash = $this->validity->currentHash($round->receipt, true);
            $currentStateIsInvalid = ! $this->validity->hasCompatibleFinancialStatus($round->receipt)
                || $this->integrity->inspect($round->receipt)['critical_count'] > 0;
            if ($currentStateIsInvalid || ! hash_equals((string) $round->snapshot_hash, $currentHash)) {
                $this->markInvalidated($round, $currentHash, null, $currentStateIsInvalid
                    ? 'A cobrança deixou de estar íntegra ou em situação compatível antes da resposta.'
                    : 'A cobrança mudou antes da resposta da organização.');
                $stale = true;

                return $round;
            }

            $round->forceFill([
                'status' => BillingAuthorizationStatus::CORRECTION_REQUESTED,
                'active_marker' => null,
                'response_decision' => BillingAuthorizationStatus::CORRECTION_REQUESTED->value,
                'responded_by' => $user->id,
                'responded_by_name' => $this->buyerName($access),
                'responded_at' => now(),
                'response_message' => $reason,
            ])->save();
            $changed = true;
            activity()->performedOn($round)->causedBy($user)
                ->withProperties(['tenant_id' => $round->tenant_id, 'receipt_id' => $round->customer_billing_receipt_id, 'sequence' => $round->sequence])
                ->log('Correção solicitada pela organização compradora');

            return $round;
        }, 5);

        if ($stale) {
            DB::afterCommit(fn () => $this->notifications->invalidated($result->fresh(['receipt.tenant'])));
            throw new BillingAuthorizationBlockedException([$this->issue('stale_round', 'A cobrança foi alterada. Aguarde o envio de uma nova versão.')]);
        }
        if ($changed) {
            DB::afterCommit(fn () => $this->notifications->responded($result->fresh(['receipt.tenant']), false));
        }

        return $result;
    }

    public function cancel(BillingAuthorization $authorization, User $actor, string $reason): BillingAuthorization
    {
        $reason = $this->cleanMessage($reason);
        if ($reason === '') {
            throw new BillingAuthorizationBlockedException([$this->issue('reason_required', 'Informe o motivo do cancelamento.')]);
        }

        return DB::transaction(function () use ($authorization, $actor, $reason): BillingAuthorization {
            $round = BillingAuthorization::withoutGlobalScopes()->where('tenant_id', $authorization->tenant_id)
                ->lockForUpdate()->findOrFail($authorization->id);
            if (in_array($round->status, [BillingAuthorizationStatus::INVALIDATED, BillingAuthorizationStatus::CANCELLED], true)) {
                return $round;
            }
            $round->forceFill([
                'status' => BillingAuthorizationStatus::CANCELLED,
                'active_marker' => null,
                'cancelled_at' => now(),
                'cancelled_by' => $actor->id,
                'cancellation_reason' => $reason,
            ])->save();
            activity()->performedOn($round)->causedBy($actor)
                ->withProperties(['tenant_id' => $round->tenant_id, 'receipt_id' => $round->customer_billing_receipt_id, 'reason' => $reason])
                ->log('Rodada de autorização cancelada administrativamente');

            return $round;
        }, 5);
    }

    private function sendIssues(CustomerBillingReceipt $receipt, int $organizationId, $distributions): array
    {
        $issues = [];
        if ($receipt->status !== CustomerReceiptStatus::PENDING_PAYMENT) {
            $issues[] = $this->issue('receipt_not_closed', 'A cobrança precisa estar financeiramente fechada e aguardando recebimento.');
        }
        if (! $receipt->sales_project_id || ! DB::table('sales_project_organizations')
            ->where('sales_project_id', $receipt->sales_project_id)->where('organization_id', $organizationId)->exists()) {
            $issues[] = $this->issue('organization_not_participant', 'A organização não participa deste projeto de venda.');
        }
        if (! OrganizationAuthorizedEmail::withoutGlobalScope('tenant')->where('tenant_id', $receipt->tenant_id)
            ->where('organization_id', $organizationId)->where('active', true)->exists()) {
            $issues[] = $this->issue('organization_without_access', 'A organização não possui e-mail autorizado ativo.');
        }
        $inspection = $this->integrity->inspect($receipt->setRelation('billingDistributions', $distributions));
        $issues = array_merge($issues, $inspection['issues']);

        if ($issues === []) {
            try {
                $this->distributionIntegrity->assertProjectContext($receipt->project, (int) $receipt->tenant_id, (int) $receipt->sales_project_id);
                $this->distributionIntegrity->assertCommon($distributions, $receipt->project, (int) $receipt->tenant_id);
                $this->distributionIntegrity->assertCustomerRecipient($distributions, (int) $receipt->tenant_id, $receipt->customer_id, $receipt->organization_id);
            } catch (\Throwable $exception) {
                $issues[] = $this->issue('financial_invariant_failed', $exception->getMessage());
            }
        }

        return $issues;
    }

    private function lockBuyerRound(BillingAuthorization $authorization, User $user, OrganizationAuthorizedEmail $access): BillingAuthorization
    {
        $roundSeed = BillingAuthorization::withoutGlobalScopes()
            ->where('tenant_id', $authorization->tenant_id)
            ->findOrFail($authorization->id);
        $receipt = CustomerBillingReceipt::withoutGlobalScopes()
            ->where('tenant_id', $roundSeed->tenant_id)
            ->lockForUpdate()
            ->findOrFail($roundSeed->customer_billing_receipt_id);
        $round = BillingAuthorization::withoutGlobalScopes()
            ->where('tenant_id', $receipt->tenant_id)
            ->where('customer_billing_receipt_id', $receipt->id)
            ->lockForUpdate()
            ->findOrFail($roundSeed->id);
        $round->setRelation('receipt', $receipt);
        $emailMatches = mb_strtolower(trim((string) $access->email)) === mb_strtolower(trim((string) $user->email));
        if (! $access->active || ! $emailMatches
            || (int) $access->tenant_id !== (int) $round->tenant_id
            || (int) $access->organization_id !== (int) $round->organization_id) {
            abort(404);
        }

        return $round;
    }

    private function snapshotIssues(array $snapshot): array
    {
        $lineGross = '0.0000';
        $lineNet = '0.0000';
        foreach ((array) data_get($snapshot, 'lines', []) as $line) {
            $lineGross = bcadd($lineGross, (string) ($line['gross'] ?? 0), 4);
            $lineNet = bcadd($lineNet, (string) ($line['net'] ?? 0), 4);
        }
        $lineFees = bcsub($lineGross, $lineNet, 4);
        $issues = [];
        foreach ([
            'gross' => [$lineGross, (string) data_get($snapshot, 'totals.gross', 0), 'O valor bruto das distribuições diverge do total fechado.'],
            'fees' => [$lineFees, (string) data_get($snapshot, 'totals.fees', 0), 'As taxas das distribuições divergem do total fechado.'],
            'net' => [$lineNet, (string) data_get($snapshot, 'totals.net', 0), 'O valor líquido das distribuições diverge do total fechado.'],
        ] as $code => [$computed, $frozen, $message]) {
            if ($this->decimalDifferenceExceeds($computed, $frozen, '0.0100')) {
                $issues[] = $this->issue('snapshot_'.$code.'_mismatch', $message);
            }
        }

        return $issues;
    }

    private function decimalDifferenceExceeds(string $left, string $right, string $tolerance): bool
    {
        $difference = bcsub($left, $right, 4);
        if (bccomp($difference, '0', 4) < 0) {
            $difference = bcmul($difference, '-1', 4);
        }

        return bccomp($difference, $tolerance, 4) > 0;
    }

    private function markInvalidated(BillingAuthorization $round, string $currentHash, ?User $actor, string $reason): void
    {
        $round->forceFill([
            'status' => BillingAuthorizationStatus::INVALIDATED,
            'active_marker' => null,
            'current_hash' => $currentHash,
            'invalidated_at' => now(),
            'invalidated_by' => $actor?->id,
            'invalidation_reason' => $reason,
        ])->save();
        $log = activity()->performedOn($round)->withProperties([
            'tenant_id' => $round->tenant_id,
            'receipt_id' => $round->customer_billing_receipt_id,
            'snapshot_hash' => $round->snapshot_hash,
            'current_hash' => $currentHash,
        ]);
        if ($actor) {
            $log->causedBy($actor);
        }
        $log->log('Autorização da organização invalidada');
    }

    private function cleanMessage(?string $message): string
    {
        return Str::limit(trim(strip_tags((string) $message)), 1000, '');
    }

    private function buyerName(OrganizationAuthorizedEmail $access): string
    {
        $name = trim((string) $access->name);

        return $name !== '' ? $name : 'Representante autorizado';
    }

    private function issue(string $code, string $message): array
    {
        return compact('code', 'message');
    }
}
