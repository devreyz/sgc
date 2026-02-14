<?php

namespace App\Observers;

use App\Models\ServiceProviderLedger;
use App\Enums\LedgerType;

class ServiceProviderLedgerObserver
{
    /**
     * Handle the ServiceProviderLedger "creating" event.
     */
    public function creating(ServiceProviderLedger $ledger): void
    {
        // Calcular o balance_after baseado no último registro
        $lastBalance = ServiceProviderLedger::where('service_provider_id', $ledger->service_provider_id)
            ->latest('transaction_date')
            ->latest('id')
            ->value('balance_after') ?? 0;

        // Se é crédito, adiciona; se é débito, subtrai
        if ($ledger->type === LedgerType::CREDIT) {
            $ledger->balance_after = $lastBalance + $ledger->amount;
        } else {
            $ledger->balance_after = $lastBalance - $ledger->amount;
        }
    }
}
