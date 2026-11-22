<?php

namespace App\Services\Services;

use App\Enums\ServiceOrderStatus;
use App\Models\CloudDocument;
use App\Models\Expense;
use App\Models\GeneratedDocument;
use App\Models\ServiceExecution;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Services\FinancialDocumentIdentityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceOrderCorrection
{
    public function __construct(
        private ServiceOrderNumberService $numbers,
        private FinancialDocumentIdentityService $identities,
    ) {}

    public function handle(ServiceOrder $order, string $action, string $reason, User $actor): void
    {
        DB::transaction(function () use ($order, $action, $reason, $actor): void {
            $execution = $order->execution()->where('tenant_id', $order->tenant_id)->lockForUpdate()->firstOrFail();
            $order = ServiceOrder::withTrashed()->where('tenant_id', $order->tenant_id)->lockForUpdate()->findOrFail($order->id);
            $allowed = $action === 'purge' ? ['cancelled'] : ['validated', 'cancelled'];
            if (! in_array($execution->status, $allowed, true)) {
                throw ValidationException::withMessages(['reason' => 'A situação da ordem mudou. Atualize a lista antes de continuar.']);
            }

            $obligations = $execution->obligations()->where('tenant_id', $order->tenant_id)->orderBy('id')->lockForUpdate()->get();
            $ids = $obligations->modelKeys();
            if ($order->paid || $order->payments()->exists() || $order->ledgerEntries()->exists()
                || DB::table('provider_payment_requests')->where('service_order_id', $order->id)->exists()
                || $obligations->contains(fn ($item) => $item->allocations()->exists() || $item->adjustments()->exists())
                || DB::table('service_payment_plan_obligations')->whereIn('service_obligation_id', $ids)->exists()
                || DB::table('service_payout_request_items')->whereIn('service_obligation_id', $ids)->exists()) {
                throw ValidationException::withMessages(['reason' => 'Esta ordem tem histórico de pagamento, ajuste, negociação ou repasse. Consulte o dossiê.']);
            }

            if ($action === 'purge' && $this->hasLinkedRecords($order, $execution)) {
                throw ValidationException::withMessages(['reason' => 'A ordem tem documentos, despesas, anexos, recursos ou registros de prestador vinculados.']);
            }

            $properties = ['tenant_id' => $order->tenant_id, 'number' => $order->number, 'reason' => $reason];
            if ($action === 'reopen') {
                $properties += ['previous_execution' => $execution->getAttributes(), 'previous_obligations' => $obligations->toArray(),
                    'previous_composition' => $execution->compositionLines()->get()->toArray()];
            }
            activity('service_order')->performedOn($order)->causedBy($actor)->withProperties($properties)
                ->log($action === 'purge' ? 'Ordem excluída definitivamente' : 'Ordem reaberta para correção');

            foreach ($obligations as $obligation) {
                $this->identities->invalidateDocument($obligation, $reason, $actor);
            }
            $execution->obligations()->where('tenant_id', $order->tenant_id)->delete();
            $execution->compositionLines()->where('tenant_id', $order->tenant_id)->delete();
            if ($action === 'purge') {
                $this->numbers->release($order->tenant_id, $order->number);
                ServiceExecution::query()->where('tenant_id', $order->tenant_id)->whereKey($execution->id)->delete();
                $order->forceDelete();
                return;
            }

            $started = $execution->started_at !== null;
            ServiceExecution::query()->where('tenant_id', $order->tenant_id)->whereKey($execution->id)->update([
                'status' => $started ? 'in_progress' : 'draft',
                'revision' => $execution->revision + 1, 'lock_version' => $execution->lock_version + 1,
                'quantity' => null, 'derived_values' => null, 'snapshot_hash' => null,
                'submitted_at' => null, 'submitted_by' => null, 'validated_at' => null, 'validated_by' => null,
                'rejected_at' => null, 'reviewed_by' => null, 'review_reason' => null,
                'submit_operation_key' => null, 'review_operation_key' => null,
            ]);
            if ($order->trashed()) $order->restore();
            $order->update([
                'status' => $started ? ServiceOrderStatus::IN_PROGRESS : ServiceOrderStatus::SCHEDULED,
                'operational_status' => $started ? 'in_progress' : 'scheduled',
                'cancelled_at' => null, 'cancelled_by' => null, 'cancellation_reason' => null,
                'actual_quantity' => null, 'final_price' => 0, 'provider_payment' => 0,
            ]);
        }, 3);
    }

    private function hasLinkedRecords(ServiceOrder $order, ServiceExecution $execution): bool
    {
        if ($order->expenses()->withTrashed()->exists() || $order->additions()->exists()
            || $execution->evidences()->exists() || $execution->resources()->exists()
            || $order->works()->exists()
            || Expense::withTrashed()->where('tenant_id', $order->tenant_id)
                ->where('expenseable_type', $execution->getMorphClass())->where('expenseable_id', $execution->id)->exists()) {
            return true;
        }

        $linked = fn ($query) => $query->where('tenant_id', $order->tenant_id)->where(fn ($query) => $query
            ->whereMorphedTo('documentable', $order)->orWhereMorphedTo('documentable', $execution));

        return $linked(GeneratedDocument::withTrashed())->exists() || $linked(CloudDocument::query())->exists();
    }
}
