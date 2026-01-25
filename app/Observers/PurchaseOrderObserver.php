<?php

namespace App\Observers;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Services\FinancialDistributionService;
use App\Services\NotificationService;

class PurchaseOrderObserver
{
    public function __construct(
        protected FinancialDistributionService $financialService,
        protected NotificationService $notificationService
    ) {}

    /**
     * Handle the PurchaseOrder "updating" event.
     */
    public function updating(PurchaseOrder $order): void
    {
        // Check if status is changing to delivered
        if ($order->isDirty('status') && $order->status === PurchaseOrderStatus::DELIVERED) {
            // Set delivery metadata
            $order->delivered_by = auth()->id();
            $order->delivered_at = now();
            $order->delivery_date = now()->toDateString();
        }
    }

    /**
     * Handle the PurchaseOrder "updated" event.
     */
    public function updated(PurchaseOrder $order): void
    {
        // Check if status just changed to delivered
        if ($order->wasChanged('status') && $order->status === PurchaseOrderStatus::DELIVERED) {
            // Only debit if payment method is 'saldo'
            if ($order->payment_method === 'saldo') {
                // Process the debit from associate's ledger
                $ledgerEntry = $this->financialService->processPurchaseOrderDelivery($order);

                // Notify associate about the debit
                $this->notificationService->notifyAssociateLedgerDebit($ledgerEntry);
            }
        }
    }
}
