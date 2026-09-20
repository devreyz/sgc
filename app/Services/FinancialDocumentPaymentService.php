<?php

namespace App\Services;

use App\Models\AssociateReceipt;
use App\Models\CustomerBillingReceipt;
use App\Models\FinancialDocumentIdentity;
use App\Models\FinancialReceipt;
use App\Models\ServiceObligation;
use App\Models\User;
use App\Services\Services\ServicePaymentService;
use Illuminate\Validation\ValidationException;

class FinancialDocumentPaymentService
{
    public function pay(FinancialDocumentIdentity $identity, array $data, User $actor): void
    {
        $document = $identity->documentable;
        if (! $document || (int) $document->tenant_id !== (int) $identity->tenant_id) {
            throw ValidationException::withMessages(['document' => 'O comprovante financeiro não está disponível.']);
        }
        if (($data['payment_method'] ?? null) === 'cheque' && ! ($data['check_delivery'] ?? false)) {
            throw ValidationException::withMessages(['payment_method' => 'Emita o cheque primeiro e liquide somente quando ele for entregue.']);
        }

        $payload = [
            'amount' => $data['amount'],
            'operation_key' => $data['operation_key'],
            'payment_date' => $data['payment_date'],
            'payment_method' => $data['payment_method'],
            'bank_account_id' => $data['bank_account_id'],
            'document_number' => $data['document_number'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];

        match ($document::class) {
            AssociateReceipt::class => app(AssociateReceiptService::class)->addPayment($document, $payload),
            CustomerBillingReceipt::class => app(CustomerBillingReceiptService::class)->addPayment($document, $payload),
            ServiceObligation::class => app(ServicePaymentService::class)->record(
                $document,
                (float) $payload['amount'],
                $payload['payment_method'],
                $payload['payment_date'],
                (int) $payload['bank_account_id'],
                $payload['operation_key'],
                $actor,
                ['financial_document_identity_id' => $identity->id, 'document_number' => $payload['document_number']],
            ),
            default => throw ValidationException::withMessages(['document' => 'Este comprovante não aceita liquidação por este fluxo.']),
        };

        activity('financial_document_payment')
            ->performedOn($identity)
            ->causedBy($actor)
            ->withProperties([
                'tenant_id' => $identity->tenant_id,
                'document_type' => $identity->documentable_type,
                'document_id' => $identity->documentable_id,
                'amount' => round((float) $payload['amount'], 2),
                'payment_method' => $payload['payment_method'],
                'operation_key' => $payload['operation_key'],
            ])->log('Liquidação registrada pelo comprovante verificável');
    }

    public function balance(FinancialDocumentIdentity $identity): float
    {
        $document = $identity->documentable;

        return match ($document?->getMorphClass()) {
            AssociateReceipt::class, CustomerBillingReceipt::class => (float) $document->remaining_amount,
            ServiceObligation::class => (float) $document->balance,
            FinancialReceipt::class => 0.0,
            default => 0.0,
        };
    }

    public function total(FinancialDocumentIdentity $identity): float
    {
        $document = $identity->documentable;

        return match ($document?->getMorphClass()) {
            AssociateReceipt::class, CustomerBillingReceipt::class => (float) $document->total_net,
            ServiceObligation::class => (float) $document->total_amount,
            FinancialReceipt::class => (float) $document->total_amount,
            default => 0.0,
        };
    }
}
