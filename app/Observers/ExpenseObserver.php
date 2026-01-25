<?php

namespace App\Observers;

use App\Models\Expense;
use App\Services\DocumentUploadService;

class ExpenseObserver
{
    public function __construct(
        protected DocumentUploadService $documentService
    ) {}

    /**
     * Handle the Expense "creating" event.
     */
    public function creating(Expense $expense): void
    {
        // Set created_by if not set
        if (!$expense->created_by) {
            $expense->created_by = auth()->id();
        }

        // Check for overdue status
        if ($expense->status->value === 'pending' && $expense->due_date && $expense->due_date->isPast()) {
            $expense->status = \App\Enums\ExpenseStatus::OVERDUE;
        }
    }

    /**
     * Handle the Expense "updating" event.
     */
    public function updating(Expense $expense): void
    {
        // If being paid, set paid metadata
        if ($expense->isDirty('status') && $expense->status->value === 'paid') {
            if (!$expense->paid_date) {
                $expense->paid_date = now();
            }
            if (!$expense->paid_by) {
                $expense->paid_by = auth()->id();
            }
            if (!$expense->paid_amount) {
                $expense->paid_amount = $expense->total_amount;
            }
        }
    }

    /**
     * Handle the Expense "saved" event.
     * Note: Document upload is handled via the Filament form, not here.
     * This observer tracks the expense for reporting purposes.
     */
    public function saved(Expense $expense): void
    {
        // Log expense for asset cost tracking if linked to an asset
        if ($expense->expenseable_type === \App\Models\Asset::class && $expense->expenseable_id) {
            // The expense is already linked via the polymorphic relationship
            // This enables the "Cost by Asset" report
        }
    }
}
