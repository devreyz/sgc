<?php

namespace App\Jobs;

use App\Models\CustomerBillingReceipt;
use App\Models\TenantCloudStorageConnection;
use App\Services\CustomerBillingReceiptArchiveService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncCustomerBillingReceiptToDrive implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 3600;
    public int $tries = 3;
    public int $timeout = 120;
    public bool $failOnTimeout = true;

    public function __construct(public readonly int $receiptId)
    {
        $this->onQueue('documents');
    }

    public function uniqueId(): string
    {
        return 'customer-billing-receipt-'.$this->receiptId;
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(CustomerBillingReceiptArchiveService $archive): void
    {
        $receipt = CustomerBillingReceipt::withoutGlobalScopes()->find($this->receiptId);
        if (! $receipt) {
            return;
        }

        if (! TenantCloudStorageConnection::query()
            ->where('tenant_id', $receipt->tenant_id)
            ->where('provider', 'google_drive')
            ->where('status', 'active')
            ->exists()) {
            Log::notice('Customer billing receipt Drive synchronization skipped because the connection is inactive.', [
                'tenant_id' => $receipt->tenant_id,
                'receipt_id' => $receipt->id,
            ]);

            return;
        }

        try {
            $archive->sync($receipt);
        } catch (Throwable $exception) {
            Log::error('Customer billing receipt Drive synchronization failed.', [
                'tenant_id' => $receipt->tenant_id,
                'receipt_id' => $receipt->id,
                'error' => mb_substr($exception->getMessage(), 0, 500),
            ]);

            throw $exception;
        }
    }
}
