<?php

namespace App\Observers;

use App\Enums\DeliveryStatus;
use App\Models\CustomerBillingReceipt;
use App\Models\ProductionDelivery;
use App\Models\ProjectDemand;
use App\Services\Accounting\BillingAuthorizationValidityService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
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
            Log::error('Falha ao notificar o registro de entrega.', [
                'tenant_id' => $delivery->tenant_id,
                'delivery_id' => $delivery->id,
                'error' => $e->getMessage(),
            ]);
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
                    ProjectDemand::find($originalDemandId)?->updateDeliveredQuantity();
                }

                // NÃO gerar movimentações financeiras aqui.
                // O faturamento agora é um processo explícito via DistributionBillingService.
                // Distribuições aguardam faturamento manual pelo gestor (billing_status = unbilled).

            } catch (\Throwable $e) {
                Log::error('Error processing delivery approval: '.$e->getMessage(), [
                    'delivery_id' => $delivery->id,
                    'trace' => $e->getTraceAsString(),
                ]);
            } finally {
                // Always reset the flag
                self::$processing = false;
            }
        }

        if ($delivery->wasChanged([
            'billing_receipt_id', 'parent_delivery_id', 'product_id', 'customer_id',
            'quantity', 'unit_price', 'status', 'delivery_date',
        ])) {
            $this->invalidateBillingAuthorizations($delivery);
        }
    }

    /**
     * Handle the ProductionDelivery "deleted" event.
     */
    public function deleted(ProductionDelivery $delivery): void
    {
        $delivery->projectDemand?->updateDeliveredQuantity();
        $this->invalidateBillingAuthorizations($delivery);
    }

    public function restored(ProductionDelivery $delivery): void
    {
        $delivery->projectDemand?->updateDeliveredQuantity();
        $this->invalidateBillingAuthorizations($delivery);
    }

    private function invalidateBillingAuthorizations(ProductionDelivery $delivery): void
    {
        $receiptIds = collect([
            $delivery->billing_receipt_id,
            $delivery->getOriginal('billing_receipt_id'),
        ])->filter()->map(fn ($id): int => (int) $id)->unique()->values();

        foreach ($receiptIds as $receiptId) {
            DB::afterCommit(function () use ($receiptId, $delivery): void {
                try {
                    $receipt = CustomerBillingReceipt::withoutGlobalScopes()
                        ->where('tenant_id', $delivery->tenant_id)->find($receiptId);
                    if ($receipt) {
                        app(BillingAuthorizationValidityService::class)->invalidateIfChanged(
                            $receipt,
                            auth()->user(),
                            'Uma distribuição vinculada à cobrança foi alterada.'
                        );
                    }
                } catch (\Throwable $exception) {
                    Log::error('Falha ao verificar autorização após alteração de distribuição.', [
                        'tenant_id' => $delivery->tenant_id,
                        'distribution_id' => $delivery->id,
                        'receipt_id' => $receiptId,
                        'error' => $exception->getMessage(),
                    ]);
                }
            });
        }
    }
}
