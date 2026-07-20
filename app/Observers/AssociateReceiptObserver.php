<?php

namespace App\Observers;

use App\Jobs\SyncAssociateReceiptToDrive;
use App\Models\AssociateReceipt;

class AssociateReceiptObserver
{
    public function saved(AssociateReceipt $receipt): void
    {
        if (empty($receipt->delivery_ids) || (float) ($receipt->total_net ?? 0) <= 0) {
            return;
        }

        SyncAssociateReceiptToDrive::dispatch($receipt->id)->afterCommit();
    }
}
