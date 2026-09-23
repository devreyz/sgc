<?php

namespace App\Services;

use App\Enums\CustomerReceiptStatus;
use App\Enums\ReceiptStatus;
use App\Models\AssociateReceipt;
use App\Models\CustomerBillingReceipt;
use App\Models\FinancialCheckInstrument;
use App\Models\FinancialDocumentIdentity;
use App\Models\FinancialReceipt;
use App\Models\ServiceObligation;
use App\Models\ServicePaymentPlanInstallment;
use App\Models\User;

class FinancialDocumentPresenter
{
    public function __construct(private readonly FinancialDocumentPaymentService $payments) {}

    public function present(FinancialDocumentIdentity $identity, ?User $user): array
    {
        $document = $identity->documentable;
        $latestCheck = $identity->checks->first();
        [$technicalStatus, $humanStatus, $tone] = $this->status($document, $latestCheck);
        $canView = $user ? $this->canView($user, $identity) : false;
        $canPay = $user && $canView ? $this->canPay($user, $identity) : false;
        $details = $canView ? $this->inTenant((int) $identity->tenant_id, fn (): array => [
            'total' => $this->payments->total($identity),
            'balance' => $this->payments->balance($identity),
            'party' => $this->party($document),
            'project' => $this->project($document),
            'payments' => $this->paymentHistory($document),
        ]) : ['total' => 0.0, 'balance' => 0.0, 'party' => null, 'project' => null, 'payments' => collect()];
        $total = $details['total'];
        $balance = $details['balance'];
        $paid = max(0, round($total - $balance, 2));
        $pendingCheck = $latestCheck?->status === 'issued' ? $latestCheck : null;
        $canLiquidate = $canPay && $balance > 0 && ! in_array($technicalStatus, ['draft', 'obsolete', 'cancelled', 'paid'], true);

        $actions = [];
        if ($pendingCheck && $canPay) {
            $actions[] = ['key' => 'deliver_check', 'label' => 'Confirmar entrega', 'primary' => true];
            $actions[] = ['key' => 'cancel_check', 'label' => 'Cancelar cheque', 'primary' => false];
        } elseif ($canLiquidate) {
            $actions[] = ['key' => 'pay', 'label' => ($document instanceof CustomerBillingReceipt || $document instanceof ServicePaymentPlanInstallment) ? 'Registrar recebimento' : 'Pagar agora', 'primary' => true];
            if (! $document instanceof ServicePaymentPlanInstallment) {
                $actions[] = ['key' => 'issue_check', 'label' => 'Emitir cheque', 'primary' => false];
            }
        }

        return [
            'identity' => $identity,
            'document' => $document,
            'reference' => $identity->reference_code,
            'number' => $this->number($document),
            'kind' => $this->kind($document),
            'issued_at' => $this->issuedAt($document),
            'technical_status' => $technicalStatus,
            'human_status' => $humanStatus,
            'tone' => $tone,
            'is_authentic' => true,
            'can_view_details' => $canView,
            'can_pay' => $canPay,
            'total' => $canView ? $total : null,
            'paid' => $canView ? $paid : null,
            'balance' => $canView ? $balance : null,
            'party' => $details['party'],
            'project' => $details['project'],
            'payments' => $details['payments'],
            'checks' => $canView ? $identity->checks : collect(),
            'pending_check' => $canView ? $pendingCheck : null,
            'actions' => $actions,
            'primary_action' => collect($actions)->firstWhere('primary', true),
        ];
    }

    public function canPay(User $user, FinancialDocumentIdentity $identity): bool
    {
        if (! $this->tenantMember($user, (int) $identity->tenant_id)) {
            return false;
        }

        return $this->inTenant((int) $identity->tenant_id, function () use ($user, $identity): bool {
            $document = $identity->documentable;

            return match ($document?->getMorphClass()) {
                AssociateReceipt::class => $user->checkPermissionTo('update_associate::receipt'),
                CustomerBillingReceipt::class => $user->checkPermissionTo('update_customer::billing::receipt'),
                ServiceObligation::class => $user->checkPermissionTo('record_service_payment')
                    && $user->checkPermissionTo($document->direction === 'payable' ? 'manage_service_payables' : 'manage_service_receivables'),
                ServicePaymentPlanInstallment::class => $user->checkPermissionTo('manage_service_receivables')
                    && $user->checkPermissionTo('manage_service_agreements'),
                default => false,
            };
        });
    }

    private function canView(User $user, FinancialDocumentIdentity $identity): bool
    {
        if (! $this->tenantMember($user, (int) $identity->tenant_id)) {
            return false;
        }
        $document = $identity->documentable;
        if ($document instanceof AssociateReceipt && (int) $document->associate?->user_id === (int) $user->id) {
            return true;
        }
        if ($document instanceof ServiceObligation && $document->direction === 'payable'
            && (int) $document->provider?->user_id === (int) $user->id) {
            return true;
        }

        return $this->inTenant((int) $identity->tenant_id, fn (): bool => match ($document?->getMorphClass()) {
            AssociateReceipt::class => $user->checkPermissionTo('view_associate::receipt'),
            CustomerBillingReceipt::class => $user->checkPermissionTo('view_customer::billing::receipt'),
            ServiceObligation::class => $user->checkPermissionTo('view_service_financials'),
            ServicePaymentPlanInstallment::class => $user->checkPermissionTo('manage_service_agreements'),
            FinancialReceipt::class => $user->checkPermissionTo('view_financial::receipt'),
            default => false,
        });
    }

    private function tenantMember(User $user, int $tenantId): bool
    {
        return $user->isSuperAdmin() || $user->belongsToTenant($tenantId);
    }

    private function inTenant(int $tenantId, callable $callback): mixed
    {
        $previous = session('tenant_id');
        session(['tenant_id' => $tenantId]);
        try {
            return $callback();
        } finally {
            if ($previous) {
                session(['tenant_id' => $previous]);
            } else {
                session()->forget('tenant_id');
            }
        }
    }

    private function status(mixed $document, ?FinancialCheckInstrument $check): array
    {
        if ($check?->status === 'issued') {
            return ['check_issued', 'Cheque emitido · aguardando entrega', 'warning'];
        }
        $status = $document?->status?->value ?? (string) ($document?->status ?? 'unknown');

        return match ($status) {
            ReceiptStatus::DRAFT->value, CustomerReceiptStatus::DRAFT->value => ['draft', 'Emitido em rascunho', 'neutral'],
            ReceiptStatus::OBSOLETE->value => ['obsolete', 'Documento obsoleto', 'danger'],
            ReceiptStatus::PENDING_PAYMENT->value => ['pending_payment', $document instanceof CustomerBillingReceipt ? 'Aguardando recebimento' : 'Aguardando pagamento', 'warning'],
            ReceiptStatus::PARTIALLY_PAID->value => ['partially_paid', 'Em pagamento', 'info'],
            ReceiptStatus::PAID->value => ['paid', $document instanceof CustomerBillingReceipt ? 'Recebido' : 'Pago', 'success'],
            'cancelled', 'canceled' => ['cancelled', 'Documento cancelado', 'danger'],
            'issued' => ['paid', 'Recebimento confirmado', 'success'],
            'open', 'pending' => ['pending_payment', $document instanceof ServiceObligation && $document->direction === 'receivable' ? 'Aguardando recebimento' : 'Aguardando pagamento', 'warning'],
            'scheduled' => ['pending_payment', 'Parcela aguardando recebimento', 'warning'],
            default => ['unknown', 'Situação em análise', 'neutral'],
        };
    }

    private function number(mixed $document): string
    {
        if ($document instanceof ServicePaymentPlanInstallment) {
            return ($document->kind === 'entry' ? 'Entrada' : 'Parcela').' '.$document->number;
        }

        return (string) ($document?->formatted_number ?? $document?->number ?? '—');
    }

    private function kind(mixed $document): string
    {
        return match ($document?->getMorphClass()) {
            AssociateReceipt::class => 'Pagamento ao membro',
            CustomerBillingReceipt::class => 'Cobrança do cliente',
            ServiceObligation::class => $document->direction === 'payable' ? 'Pagamento de serviço ao prestador' : 'Cobrança de serviço',
            ServicePaymentPlanInstallment::class => 'Cobrança de termo de negociação',
            FinancialReceipt::class => 'Recibo de recebimento',
            default => 'Comprovante financeiro',
        };
    }

    private function issuedAt(mixed $document): mixed
    {
        return $document?->issued_at ?? $document?->frozen_at ?? $document?->created_at;
    }

    private function party(mixed $document): string
    {
        return match ($document?->getMorphClass()) {
            AssociateReceipt::class => (string) ($document->associate?->display_name ?? 'Membro'),
            CustomerBillingReceipt::class => (string) $document->recipient_name,
            ServiceObligation::class => (string) (data_get($document->party_snapshot, 'name')
                ?? $document->provider?->name ?? $document->associate?->display_name ?? 'Parte não identificada'),
            ServicePaymentPlanInstallment::class => (string) data_get($document->plan?->obligations()->first()?->party_snapshot, 'name', 'Parte não identificada'),
            FinancialReceipt::class => (string) ($document->payer_name ?: 'Pagador não identificado'),
            default => '—',
        };
    }

    private function project(mixed $document): ?string
    {
        return match ($document?->getMorphClass()) {
            AssociateReceipt::class => $document->project?->title,
            CustomerBillingReceipt::class => $document->project_summary,
            ServiceObligation::class => $document->execution?->order?->service?->name,
            ServicePaymentPlanInstallment::class => 'Termo de negociação de serviços',
            default => null,
        };
    }

    private function paymentHistory(mixed $document): mixed
    {
        if ($document instanceof AssociateReceipt || $document instanceof CustomerBillingReceipt) {
            return $document->payments()->with('creator')->latest('payment_date')->get();
        }
        if ($document instanceof ServiceObligation) {
            return $document->allocations()->with('paymentEvent')->latest('id')->get()
                ->map(fn ($allocation) => (object) [
                    'amount' => $allocation->amount,
                    'payment_date' => $allocation->paymentEvent?->payment_date,
                    'payment_method' => $allocation->paymentEvent?->payment_method,
                    'document_number' => data_get($allocation->paymentEvent?->metadata, 'document_number'),
                ]);
        }
        if ($document instanceof ServicePaymentPlanInstallment) {
            return $document->paymentEvent ? collect([(object) [
                'amount' => $document->paymentEvent->amount,
                'payment_date' => $document->paymentEvent->payment_date,
                'payment_method' => $document->paymentEvent->payment_method,
                'document_number' => 'NEG-'.$document->service_payment_plan_id.'-'.$document->number,
            ]]) : collect();
        }

        return collect();
    }
}
