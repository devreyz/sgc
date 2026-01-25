<?php

namespace App\Observers;

use App\Enums\LedgerType;
use App\Models\AssociateLedger;
use App\Services\NotificationService;

class AssociateLedgerObserver
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * Handle the AssociateLedger "creating" event.
     */
    public function creating(AssociateLedger $entry): void
    {
        // Set created_by if not set
        if (!$entry->created_by) {
            $entry->created_by = auth()->id();
        }

        // Set transaction_date if not set
        if (!$entry->transaction_date) {
            $entry->transaction_date = now()->toDateString();
        }
    }

    /**
     * Handle the AssociateLedger "created" event.
     */
    public function created(AssociateLedger $entry): void
    {
        // Notifications are handled by the respective observers
        // (ProductionDeliveryObserver, PurchaseOrderObserver, etc.)
        // This is to avoid duplicate notifications
    }
}
