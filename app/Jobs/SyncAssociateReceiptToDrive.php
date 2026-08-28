<?php

namespace App\Jobs;

use App\Models\AssociateReceipt;
use App\Models\TenantCloudStorageConnection;
use App\Services\AssociateReceiptArchiveService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncAssociateReceiptToDrive implements ShouldBeUnique, ShouldQueue
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
        return 'associate-receipt-'.$this->receiptId;
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(AssociateReceiptArchiveService $archive): void
    {
        $receipt = AssociateReceipt::query()->find($this->receiptId);
        if (! $receipt || ! TenantCloudStorageConnection::query()
            ->where('tenant_id', $receipt->tenant_id)
            ->where('status', 'active')
            ->exists()) {
            return;
        }

        try {
            $archive->sync($receipt);
        } catch (Throwable $exception) {
            activity('cloud_storage')->withProperties([
                'tenant_id' => $receipt->tenant_id,
                'receipt_id' => $receipt->id,
                'provider' => 'google_drive',
            ])->log('Falha ao sincronizar comprovante');

            Log::error('Google Drive receipt synchronization failed.', [
                'tenant_id' => $receipt->tenant_id,
                'receipt_id' => $receipt->id,
                'exception_class' => $exception::class,
                'exception_code' => $exception->getCode(),
                'error' => mb_substr($exception->getMessage(), 0, 500),
            ]);

            throw new \RuntimeException('Falha temporaria ao sincronizar comprovante com o Google Drive.');
        }
    }
}
