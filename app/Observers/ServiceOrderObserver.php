<?php

namespace App\Observers;

use App\Enums\ServiceOrderStatus;
use App\Models\ServiceOrder;
use App\Services\FinancialDistributionService;
use App\Services\NotificationService;

class ServiceOrderObserver
{
    public function __construct(
        protected FinancialDistributionService $financialService,
        protected NotificationService $notificationService
    ) {}

    /**
     * Handle the ServiceOrder "updated" event.
     */
    public function updated(ServiceOrder $order): void
    {
        // Check if status just changed to billed
        if ($order->wasChanged('status') && $order->status === ServiceOrderStatus::BILLED) {
            // Process the debit from associate's ledger
            $ledgerEntry = $this->financialService->processServiceOrderBilling($order);

            // Notify associate about the debit
            $this->notificationService->notifyAssociateLedgerDebit($ledgerEntry);

            // Update asset meters if applicable
            if ($order->asset) {
                $asset = $order->asset;
                
                if ($order->horimeter_end) {
                    $asset->horimeter = $order->horimeter_end;
                }
                
                if ($order->odometer_end) {
                    $asset->odometer = $order->odometer_end;
                }
                
                $asset->save();
            }
        }
    }
}
