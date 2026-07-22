<?php

namespace App\Observers;

use App\Enums\DeliveryStatus;
use App\Models\ProductionDelivery;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

class ProductionDeliveryObserver
{
    /**
     * Flag to prevent recursive observer calls
     */
    private static bool $processing = false;

    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * Handle the ProductionDelivery "created" event.
     */
    public function created(ProductionDelivery $delivery): void
    {
        if ($delivery->projectDemand) {
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

        if ($delivery->wasChanged(['status', 'quantity', 'customer_id', 'project_demand_id', 'parent_delivery_id'])) {
            // Set processing flag to prevent recursive calls
            self::$processing = true;

            try {
                // Update demand delivered quantity
                if ($delivery->projectDemand) {
                    $delivery->projectDemand->updateDeliveredQuantity();
                }

                $originalDemandId = (int) $delivery->getOriginal('project_demand_id');
                if ($originalDemandId && $originalDemandId !== (int) $delivery->project_demand_id) {
                    \App\Models\ProjectDemand::find($originalDemandId)?->updateDeliveredQuantity();
                }

                // NÃO gerar movimentações financeiras aqui.
                // O faturamento agora é um processo explícito via DistributionBillingService.
                // Distribuições aguardam faturamento manual pelo gestor (billing_status = unbilled).

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
        $delivery->projectDemand?->updateDeliveredQuantity();
    }

    public function restored(ProductionDelivery $delivery): void
    {
        $delivery->projectDemand?->updateDeliveredQuantity();
    }
}
