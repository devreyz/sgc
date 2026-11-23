<?php

namespace App\Services\Services;

use App\Enums\ServiceOrderStatus;
use App\Enums\ExpenseStatus;
use App\Models\CloudDocument;
use App\Models\Document;
use App\Models\Expense;
use App\Models\GeneratedDocument;
use App\Models\ServiceExecution;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Services\FinancialDocumentIdentityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ServiceOrderCorrection
{
    public function __construct(
        private ServiceOrderNumberService $numbers,
        private FinancialDocumentIdentityService $identities,
        private ServicePaymentService $payments,
    ) {}

    public function bill(ServiceOrder $order, User $actor, int $version): void
    {
        DB::transaction(function () use ($order, $actor, $version): void {
            $execution = $order->execution()->where('tenant_id', $order->tenant_id)->lockForUpdate()->firstOrFail();
            $order = ServiceOrder::query()->where('tenant_id', $order->tenant_id)->lockForUpdate()->findOrFail($order->id);
            if ($order->isBilled()) return;
            if ($execution->status !== 'validated' || $order->operational_status !== 'validated' || $execution->lock_version !== $version) {
                throw ValidationException::withMessages(['order' => 'Confira a execução atual antes de faturar a ordem.']);
            }
            $obligations = $execution->obligations()->where('tenant_id', $order->tenant_id)->orderBy('id')->lockForUpdate()->get();
            $order->forceFill(['billed_at' => now(), 'billed_by' => $actor->id])->save();
            activity('service_order')->performedOn($order)->causedBy($actor)->withProperties([
                'tenant_id' => $order->tenant_id, 'number' => $order->number, 'execution_revision' => $execution->revision,
                'obligations' => $obligations->map(fn ($item) => [
                    'id' => $item->id, 'direction' => $item->direction, 'total' => $item->total_amount,
                ])->all(),
            ])->log('Ordem faturada');
        }, 3);
    }

    // Returns false when the order must be retained as the source of financial reversals.
    public function handle(ServiceOrder $order, string $action, string $reason, User $actor, bool $deleteGeneratedNotes = false): bool
    {
        return DB::transaction(function () use ($order, $action, $reason, $actor, $deleteGeneratedNotes): bool {
            $execution = $order->execution()->where('tenant_id', $order->tenant_id)->lockForUpdate()->firstOrFail();
            $order = ServiceOrder::withTrashed()->where('tenant_id', $order->tenant_id)->lockForUpdate()->findOrFail($order->id);
            if ($order->isBilled()) {
                throw ValidationException::withMessages(['reason' => 'A ordem já foi faturada e está fechada para edição ou exclusão.']);
            }
            $allowed = $action === 'purge' ? ['draft', 'in_progress', 'submitted', 'rejected', 'validated', 'cancelled'] : ['validated', 'cancelled'];
            if (! in_array($execution->status, $allowed, true)) {
                throw ValidationException::withMessages(['reason' => 'A situação da ordem mudou. Atualize a lista antes de continuar.']);
            }

            $obligations = $execution->obligations()->where('tenant_id', $order->tenant_id)->orderBy('id')->lockForUpdate()->get();
            $ids = $obligations->modelKeys();
            if ($order->paid || $order->paid_date || $order->associate_paid_at || $order->provider_paid_at
                || $order->payments()->exists() || $order->ledgerEntries()->withTrashed()->exists()
                || $order->works()->withTrashed()->where('payment_status', 'pago')->exists()
                || DB::table('provider_payment_requests')->where('service_order_id', $order->id)->exists()
                || DB::table('service_payment_plan_obligations')->whereIn('service_obligation_id', $ids)->exists()
                || DB::table('service_payout_request_items')->whereIn('service_obligation_id', $ids)->exists()) {
                throw ValidationException::withMessages(['reason' => 'A ordem tem negociação, parcelas, repasse ou lançamentos financeiros antigos vinculados. Consulte o dossiê.']);
            }

            $properties = ['tenant_id' => $order->tenant_id, 'number' => $order->number, 'reason' => $reason];
            $hasPayments = $obligations->contains(fn ($item) => $item->allocations()->exists());
            if ($hasPayments && $action === 'reopen') {
                throw ValidationException::withMessages(['reason' => 'A execução tem pagamentos registrados. Os detalhes e a tarifa podem ser corrigidos pelo dossiê enquanto a ordem estiver aberta.']);
            }
            if ($action === 'purge') {
                $properties['deleted_generated_document_ids'] = $this->removeGeneratedNotes($order, $execution, $deleteGeneratedNotes);
                $properties += $this->detachExecutionRecords($order, $execution);
            } else {
                $properties += ['previous_execution' => $execution->getAttributes(), 'previous_obligations' => $obligations->toArray(),
                    'previous_composition' => $execution->compositionLines()->get()->toArray(),
                    'previous_adjustments' => $obligations->flatMap(fn ($item) => $item->adjustments()->get())->toArray()];
            }
            if ($hasPayments) {
                $properties['reversed_payment_ids'] = $this->reversePayments($order, $ids, $reason, $actor);
            }
            activity('service_order')->performedOn($order)->causedBy($actor)->withProperties($properties)
                ->log($hasPayments ? 'Ordem retirada da lista com estorno' : ($action === 'purge' ? 'Ordem excluída definitivamente' : 'Ordem reaberta para correção'));

            foreach ($obligations as $obligation) {
                $this->identities->invalidateDocument($obligation, $reason, $actor);
                if ($hasPayments) {
                    $obligation->update(['status' => 'cancelled']);
                } else {
                    $obligation->adjustments()->delete();
                }
            }
            if ($hasPayments) {
                if ($execution->status !== 'cancelled') {
                    $execution->update(['status' => 'cancelled', 'lock_version' => $execution->lock_version + 1]);
                }
                $order->update(['status' => ServiceOrderStatus::CANCELLED, 'operational_status' => 'cancelled',
                    'cancelled_at' => now(), 'cancelled_by' => $actor->id, 'cancellation_reason' => $reason]);
                $order->delete();
                return false;
            }
            $execution->obligations()->where('tenant_id', $order->tenant_id)->delete();
            $execution->compositionLines()->where('tenant_id', $order->tenant_id)->delete();
            if ($action === 'purge') {
                $this->numbers->release($order->tenant_id, $order->number);
                ServiceExecution::query()->where('tenant_id', $order->tenant_id)->whereKey($execution->id)->delete();
                $order->forceDelete();
                return true;
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
            return true;
        }, 3);
    }

    private function removeGeneratedNotes(ServiceOrder $order, ServiceExecution $execution, bool $confirmed): array
    {
        $linked = fn ($query) => $query->where('tenant_id', $order->tenant_id)->where(fn ($query) => $query
            ->whereMorphedTo('documentable', $order)->orWhereMorphedTo('documentable', $execution));

        if ($order->receipt_path) {
            throw ValidationException::withMessages(['reason' => 'A ordem tem um comprovante financeiro legado vinculado.']);
        }

        $notes = $linked(GeneratedDocument::withTrashed())->orderBy('id')->lockForUpdate()->get();
        if ($notes->isEmpty()) {
            return [];
        }

        $ids = $notes->modelKeys();
        if ($notes->contains(fn (GeneratedDocument $note) => $note->status !== 'draft' || $note->isSigned() || $note->signature_file
                || ! in_array(data_get($note->document_settings, 'template_key'), ['service_order', 'service_internal_note', 'service_execution'], true))
            || DB::table('service_negotiations')->whereIn('generated_document_id', $ids)->exists()
            || CloudDocument::query()->where('tenant_id', $order->tenant_id)
                ->where('documentable_type', $notes->first()->getMorphClass())->whereIn('documentable_id', $ids)->exists()) {
            throw ValidationException::withMessages(['reason' => 'A ordem tem documentos protegidos. Somente notas e vias da OS geradas como rascunho podem ser excluídas por aqui.']);
        }

        if (! $confirmed) {
            throw ValidationException::withMessages(['delete_generated_notes' => 'Marque a opção de excluir as notas e vias em rascunho para liberar o número.']);
        }

        $notes->each->forceDelete();

        return $ids;
    }

    private function detachExecutionRecords(ServiceOrder $order, ServiceExecution $execution): array
    {
        $owners = fn ($query, string $relation) => $query->where('tenant_id', $order->tenant_id)
            ->where(fn ($query) => $query->whereMorphedTo($relation, $order)->orWhereMorphedTo($relation, $execution));
        $expenses = $owners(Expense::withTrashed(), 'expenseable');
        $documents = $owners(Document::withTrashed(), 'documentable');
        $cloud = $owners(CloudDocument::query(), 'documentable');
        $resources = $execution->resources()->where('tenant_id', $order->tenant_id);
        $evidences = $execution->evidences()->where('tenant_id', $order->tenant_id);
        $generatedExpenses = (clone $expenses)->where('expenseable_type', $execution->getMorphClass())
            ->whereIn('id', (clone $resources)->where('effect', 'create_association_expense')->select('expense_id'))
            ->whereIn('status', [ExpenseStatus::PENDING->value, ExpenseStatus::OVERDUE->value])
            ->where(fn ($query) => $query->whereNull('paid_amount')->orWhere('paid_amount', 0));
        $audit = [
            'detached_expense_ids' => (clone $expenses)->pluck('id')->all(),
            'detached_document_ids' => (clone $documents)->pluck('id')->all(),
            'detached_cloud_document_ids' => (clone $cloud)->pluck('id')->all(),
            'removed_resources' => (clone $resources)->get()->toArray(),
            'cancelled_expense_ids' => (clone $generatedExpenses)->pluck('id')->all(),
        ];
        $generatedExpenses->toBase()->update(['status' => ExpenseStatus::CANCELLED->value, 'updated_at' => now()]);
        $expenses->toBase()->update(['expenseable_type' => null, 'expenseable_id' => null]);
        $documents->toBase()->update(['documentable_type' => null, 'documentable_id' => null]);
        $cloud->toBase()->update(['documentable_type' => null, 'documentable_id' => null]);
        $evidences->delete();
        $resources->delete();
        $order->works()->withTrashed()->where('payment_status', 'pendente')->toBase()
            ->update(['payment_status' => 'cancelado', 'updated_at' => now()]);

        return $audit;
    }

    private function reversePayments(ServiceOrder $order, array $obligationIds, string $reason, User $actor): array
    {
        $payments = \App\Models\ServicePaymentEvent::query()->where('tenant_id', $order->tenant_id)
            ->where('event_type', 'payment')->where('status', 'confirmed')
            ->whereHas('allocations', fn ($query) => $query->whereIn('service_obligation_id', $obligationIds))
            ->orderBy('id')->lockForUpdate()->get();
        $reversed = [];
        foreach ($payments as $payment) {
            if ($payment->allocations()->whereNotIn('service_obligation_id', $obligationIds)->exists()
                || data_get($payment->metadata, 'payment_plan_id')) {
                throw ValidationException::withMessages(['reason' => 'Este pagamento envolve outras ordens ou parcelas. Revise o lançamento no financeiro.']);
            }
            foreach ($payment->allocations()->with('obligation')->get()->pluck('obligation.direction')->unique() as $direction) {
                abort_unless($actor->checkPermissionTo($direction === 'payable' ? 'manage_service_payables' : 'manage_service_receivables'), 403);
            }
            $reversed[] = $this->payments->reverse($payment, $reason, (string) Str::uuid(), $actor)->id;
        }

        return $reversed;
    }
}
