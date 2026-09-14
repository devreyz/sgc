<?php

namespace App\Services\Services;

use App\Models\ServiceExecution;
use App\Models\ServicePaymentAllocation;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ServiceReportService
{
    public function summary(int $tenantId, CarbonInterface $from, CarbonInterface $to, ?int $providerId = null, ?int $serviceId = null, ?int $assetId = null): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();
        if ($from->greaterThan($to)) {
            throw ValidationException::withMessages(['from' => 'O início deve ser anterior ao fim do período.']);
        }
        $executions = ServiceExecution::query()->where('tenant_id', $tenantId)->where('status', 'validated')->whereBetween('validated_at', [$from, $to])
            ->with(['order.service', 'obligations.allocations.paymentEvent', 'obligations.adjustments', 'resources', 'compositionLines'])
            ->when($providerId, fn ($q) => $q->where('service_provider_id', $providerId))
            ->when($serviceId, fn ($q) => $q->whereHas('order', fn ($oq) => $oq->where('service_id', $serviceId)))
            ->when($assetId, fn ($q) => $q->whereHas('order', fn ($oq) => $oq->where('asset_id', $assetId)))->orderBy('validated_at')->get();
        $details = $executions->map(function ($execution) use ($to): array {
            $row = ['execution_id' => $execution->id, 'date' => $execution->validated_at->format('d/m/Y'), 'month' => $execution->validated_at->format('Y-m'),
                'number' => $execution->order->number, 'service' => $execution->order->service->name,
                'beneficiary' => data_get($execution->order->beneficiary_snapshot, 'name', '—'),
                'provider' => data_get($execution->order->provider_snapshot, 'name', '—'),
                'unit' => $execution->unit, 'quantity' => (float) $execution->quantity,
                'service_value' => 0, 'received' => 0, 'receivable_balance' => 0, 'provider_due' => 0, 'provider_paid' => 0, 'provider_balance' => 0,
                'fields' => [], 'composition' => $execution->compositionLines->toArray(), 'payments' => [],
                'resources' => $execution->resources->toArray()];
            foreach ($execution->obligations as $obligation) {
                $total = (float) $obligation->principal_amount + (float) $obligation->adjustments->filter(fn ($a) => $a->created_at <= $to)->sum('amount');
                $allocations = $obligation->allocations->filter(fn ($a) => $a->paymentEvent && in_array($a->paymentEvent->status, ['confirmed', 'reversed'], true) && $a->paymentEvent->payment_date <= $to);
                $paid = round((float) $allocations->sum('amount'), 2);
                $receivable = $obligation->direction === 'receivable';
                $row[$receivable ? 'service_value' : 'provider_due'] += $total;
                $row[$receivable ? 'received' : 'provider_paid'] += $paid;
                $row[$receivable ? 'receivable_balance' : 'provider_balance'] += round($total - $paid, 2);
                foreach ($allocations as $allocation) {
                    $event = $allocation->paymentEvent;
                    $row['payments'][] = ['date' => $event->payment_date->format('d/m/Y'), 'direction' => $obligation->direction, 'method' => $event->payment_method, 'amount' => (float) $allocation->amount, 'event_id' => $event->id, 'cash_movement_id' => $event->cash_movement_id];
                }
            }
            foreach (data_get($execution->catalog_snapshot, 'fields', []) as $field) {
                if ($field['reportable'] ?? false) {
                    $row['fields'][$field['label']] = data_get($execution->values, $field['key']);
                }
            }

            return $row;
        });
        $totals = $this->totals($details);
        $periodPayments = ServicePaymentAllocation::query()->where('tenant_id', $tenantId)
            ->whereHas('paymentEvent', fn ($q) => $q->whereIn('status', ['confirmed', 'reversed'])->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()]))
            ->whereHas('obligation.execution', function ($q) use ($providerId, $serviceId, $assetId): void {
                $q->when($providerId, fn ($e) => $e->where('service_provider_id', $providerId))
                    ->when($serviceId, fn ($e) => $e->whereHas('order', fn ($o) => $o->where('service_id', $serviceId)))
                    ->when($assetId, fn ($e) => $e->whereHas('order', fn ($o) => $o->where('asset_id', $assetId)));
            })->with(['obligation', 'paymentEvent'])->get();

        return $totals + [
            'from' => $from->toDateString(), 'to' => $to->toDateString(), 'details' => $details, 'rows' => $executions,
            'monthly' => $details->groupBy('month')->map(fn ($rows, $month) => ['month' => $month] + $this->totals($rows))->values(),
            'resource_amount' => round((float) $executions->flatMap->resources->sum('amount'), 2),
            'cash_received_period' => round((float) $periodPayments->filter(fn ($a) => $a->obligation->direction === 'receivable')->sum('amount'), 2),
            'cash_paid_period' => round((float) $periodPayments->filter(fn ($a) => $a->obligation->direction === 'payable')->sum('amount'), 2),
            'payments_without_cash' => $periodPayments->filter(fn ($a) => ! $a->paymentEvent->cash_movement_id)->count(),
        ];
    }

    private function totals(Collection $rows): array
    {
        $result = ['executions' => $rows->count(), 'quantities' => $rows->groupBy('unit')->map(fn ($items) => round((float) $items->sum('quantity'), 4))->all()];
        $result['quantity'] = count($result['quantities']) === 1 ? array_values($result['quantities'])[0] : null;
        foreach (['service_value', 'received', 'receivable_balance', 'provider_due', 'provider_paid', 'provider_balance'] as $key) {
            $result[$key] = round((float) $rows->sum($key), 2);
        }

        return $result;
    }
}
