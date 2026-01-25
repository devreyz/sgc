<?php

namespace App\Observers;

use App\Enums\DeliveryStatus;
use App\Models\ProductionDelivery;
use App\Services\FinancialDistributionService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

class ProductionDeliveryObserver
{
    /**
     * Flag to prevent recursive observer calls
     */
    private static bool $processing = false;

    public function __construct(
        protected FinancialDistributionService $financialService,
        protected NotificationService $notificationService
    ) {}

    /**
     * Handle the ProductionDelivery "created" event.
     */
    public function created(ProductionDelivery $delivery): void
    {
        // If created already approved, update demand quantity
        if ($delivery->status === DeliveryStatus::APPROVED && $delivery->projectDemand) {
            $delivery->projectDemand->updateDeliveredQuantity();
        }
        
        // Notify about new delivery
        try {
            $this->notificationService->notifyDelivery($delivery);
        } catch (\Throwable $e) {
            Log::error('Error notifying delivery: ' . $e->getMessage());
        }
    }

    /**
     * Handle the ProductionDelivery "updating" event.
     */
    public function updating(ProductionDelivery $delivery): void
    {
        // Skip if already processing to prevent infinite loops
        if (self::$processing) {
            return;
        }

        // Check if status is changing to approved
        if ($delivery->isDirty('status') && $delivery->status === DeliveryStatus::APPROVED) {
            // Set approval metadata
            $delivery->approved_by = auth()->id();
            $delivery->approved_at = now();
        }
    }

    /**
     * Handle the ProductionDelivery "updated" event.
     */
    public function updated(ProductionDelivery $delivery): void
    {
        // Skip if already processing to prevent infinite loops
        if (self::$processing) {
            return;
        }

        // Check if status just changed to approved
        if ($delivery->wasChanged('status') && $delivery->status === DeliveryStatus::APPROVED) {
            // Set processing flag to prevent recursive calls
            self::$processing = true;

            try {
                // Update demand delivered quantity
                if ($delivery->projectDemand) {
                    $delivery->projectDemand->updateDeliveredQuantity();
                }
                
                // Process the financial distribution (Split 90/10)
                $result = $this->financialService->processDelivery($delivery);

                // Notify associate about the credit
                if (isset($result['ledger_entry'])) {
                    $this->notificationService->notifyAssociateLedgerCredit($result['ledger_entry']);
                }
            } catch (\Throwable $e) {
                Log::error('Error processing delivery approval: ' . $e->getMessage(), [
                    'delivery_id' => $delivery->id,
                    'trace' => $e->getTraceAsString()
                ]);
            } finally {
                // Always reset the flag
                self::$processing = false;
            }
        }
    }

    /**
     * Handle the ProductionDelivery "deleted" event.
     */
    public function deleted(ProductionDelivery $delivery): void
    {
        // If delivery was approved, we might need to reverse the financial entries
        // This is a complex operation and should be handled carefully
        // For now, we'll just log it - reversals should be done manually
    }
}
