<?php

namespace App\Services\Services;

use App\Models\ServiceExecution;
use Carbon\CarbonInterface;

class ServiceReportService
{
    public function summary(int $tenantId, CarbonInterface $from, CarbonInterface $to, ?int $providerId = null, ?int $serviceId = null): array
    {
        $executions = ServiceExecution::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('status', 'validated')->whereBetween('validated_at', [$from, $to])->with(['order.service', 'obligations.allocations.paymentEvent', 'resources'])->when($providerId, fn ($q) => $q->where('service_provider_id', $providerId))->when($serviceId, fn ($q) => $q->whereHas('order', fn ($oq) => $oq->where('service_id', $serviceId)))->get();
        $obligations = $executions->flatMap->obligations;
        $monthly = $executions->groupBy(fn ($execution) => $execution->validated_at->format('Y-m'))->map(function ($rows, $month) {
            $obs = $rows->flatMap->obligations;

            return ['month' => $month, 'executions' => $rows->count(), 'quantity' => round((float) $rows->sum('quantity'), 4), 'service_value' => round((float) $obs->where('direction', 'receivable')->sum('total_amount'), 2), 'received' => round((float) $obs->where('direction', 'receivable')->sum('paid_amount'), 2), 'provider_due' => round((float) $obs->where('direction', 'payable')->sum('total_amount'), 2), 'provider_paid' => round((float) $obs->where('direction', 'payable')->sum('paid_amount'), 2)];
        })->sortKeys()->values();

        return ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'executions' => $executions->count(), 'quantity' => round((float) $executions->sum('quantity'), 4), 'service_value' => round((float) $obligations->where('direction', 'receivable')->sum('total_amount'), 2), 'received' => round((float) $obligations->where('direction', 'receivable')->sum('paid_amount'), 2), 'receivable_balance' => round((float) $obligations->where('direction', 'receivable')->sum('balance'), 2), 'provider_due' => round((float) $obligations->where('direction', 'payable')->sum('total_amount'), 2), 'provider_paid' => round((float) $obligations->where('direction', 'payable')->sum('paid_amount'), 2), 'provider_balance' => round((float) $obligations->where('direction', 'payable')->sum('balance'), 2), 'resource_amount' => round((float) $executions->flatMap->resources->sum('amount'),2), 'monthly' => $monthly, 'rows' => $executions];
    }
}
