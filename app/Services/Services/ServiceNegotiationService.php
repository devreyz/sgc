<?php

namespace App\Services\Services;

use App\Models\ServiceNegotiation;
use App\Models\ServiceObligation;
use App\Models\ServicePaymentPlan;
use App\Models\User;
use App\Services\FinancialDocumentIdentityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceNegotiationService
{
    public function eligible(int $tenantId, ?int $includedPlanId = null): Builder
    {
        return ServiceObligation::query()->where('tenant_id', $tenantId)->where('direction', 'receivable')
            ->whereIn('status', ['open', 'partially_paid'])
            ->whereHas('execution', fn ($execution) => $execution->where('tenant_id', $tenantId)->where('status', 'validated')
                ->whereHas('order', fn ($order) => $order->where('tenant_id', $tenantId)->where('status', '!=', 'cancelled')
                    ->where(fn ($state) => $state->whereNull('operational_status')->orWhere('operational_status', '!=', 'cancelled'))))
            ->whereNotIn('id', DB::table('service_payment_plan_obligations as link')
                ->join('service_payment_plans as plan', 'plan.id', '=', 'link.service_payment_plan_id')
                ->where('plan.tenant_id', $tenantId)->where('plan.status', 'active')
                ->when($includedPlanId, fn ($query) => $query->where('plan.id', '!=', $includedPlanId))
                ->select('link.service_obligation_id'));
    }

    public function cancel(ServiceNegotiation $agreement, string $reason, User $actor): void
    {
        DB::transaction(function () use ($agreement, $reason, $actor): void {
            $agreement = ServiceNegotiation::query()->where('tenant_id', $agreement->tenant_id)->lockForUpdate()->findOrFail($agreement->id);
            $plan = $agreement->plan()->lockForUpdate()->firstOrFail();
            $installments = $plan->installments()->orderBy('id')->lockForUpdate()->get();
            if ($agreement->status !== 'active' || $plan->status !== 'active'
                || $plan->receiptEvents()->where('status', 'confirmed')->exists()
                || $installments->contains(fn ($item) => $item->status !== 'scheduled' || $item->service_payment_event_id)) {
                throw ValidationException::withMessages(['reason' => 'Somente acordos ativos sem parcelas recebidas podem ser cancelados.']);
            }
            foreach ($installments as $installment) {
                $installment->update(['status' => 'cancelled']);
            }
            $plan->update(['status' => 'cancelled']);
            $agreement->update(['status' => 'cancelled']);
            activity('service_negotiation')->performedOn($agreement)->causedBy($actor)
                ->withProperties(['reason' => $reason])->log('Acordo de serviço cancelado');
        }, 3);
    }

    public function create(int $tenantId, array $obligationIds, array $installments, User $actor, ?ServiceNegotiation $supersedes = null, ?string $operationKey = null): ServiceNegotiation
    {
        $obligationIds = array_map('intval', $obligationIds);
        sort($obligationIds);
        $operationHash = hash('sha256', json_encode([$obligationIds, $installments, $supersedes?->id], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($tenantId, $obligationIds, $installments, $actor, $supersedes, $operationKey, $operationHash) {
            if ($installments === [] || count($installments) > 120) {
                throw ValidationException::withMessages(['installments' => 'Quantidade de parcelas inválida.']);
            }
            $year = (int) now()->format('Y');
            DB::table('service_negotiation_sequences')->insertOrIgnore(['tenant_id' => $tenantId, 'year' => $year, 'last_number' => 0, 'created_at' => now(), 'updated_at' => now()]);
            $sequence = DB::table('service_negotiation_sequences')->where('tenant_id', $tenantId)->where('year', $year);
            $counter = (clone $sequence)->lockForUpdate()->first();
            if ($operationKey) {
                $existing = ServiceNegotiation::query()->where('tenant_id', $tenantId)->where('operation_key', $operationKey)->lockForUpdate()->first();
                if ($existing) {
                    if (data_get($existing->terms_snapshot, 'operation_hash') !== $operationHash) {
                        throw ValidationException::withMessages(['operation_key' => 'Esta operação já foi usada com outras condições.']);
                    }

                    return $existing->load('plan.installments');
                }
            }
            $previousPlan = null;
            if ($supersedes) {
                $supersedes = ServiceNegotiation::query()->where('tenant_id', $tenantId)->lockForUpdate()->findOrFail($supersedes->id);
                $previousPlan = $supersedes->plan()->lockForUpdate()->firstOrFail();
                if ($supersedes->status !== 'active' || $previousPlan->status !== 'active') {
                    throw ValidationException::withMessages(['supersedes' => 'Somente acordos ativos com saldo podem ser renegociados.']);
                }
            }
            $obligations = ServiceObligation::query()->where('tenant_id', $tenantId)
                ->whereIn('id', $obligationIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $eligible = $this->eligible($tenantId, $previousPlan?->id)->whereIn('id', $obligationIds)->pluck('id');
            if ($eligible->count() !== count($obligationIds) || $obligations->count() !== count($obligationIds)) {
                throw ValidationException::withMessages(['obligation_ids' => 'Selecione somente saldos disponíveis para negociação nesta organização.']);
            }
            $obligations->load(['execution.order.service', 'associate']);
            $balances = $obligations->mapWithKeys(fn (ServiceObligation $item) => [$item->id => $item->balanceCents()]);
            if ($balances->contains(fn (int $balance) => $balance <= 0)) {
                throw ValidationException::withMessages(['obligation_ids' => 'Uma obrigação mudou de situação. Atualize a lista e selecione somente saldos em aberto.']);
            }
            $parties = $obligations->pluck('associate_id')->unique();
            $previousIds = $previousPlan?->obligations()->pluck('service_obligations.id')->all() ?? [];
            $keepsLegacyGroup = $previousPlan && ! array_diff($obligationIds, $previousIds);
            if ($parties->count() !== 1 || ($obligations->count() > 1 && ! $parties->first() && ! $keepsLegacyGroup)
                || $obligations->contains(fn (ServiceObligation $item) => $item->associate_id
                    && (int) $item->associate?->tenant_id !== $tenantId)) {
                throw ValidationException::withMessages(['obligation_ids' => 'Um termo só pode reunir obrigações da mesma pessoa. Crie um termo separado para cada beneficiário.']);
            }
            if ($previousPlan) {
                $pendingIds = $this->eligible($tenantId, $previousPlan->id)
                    ->whereIn('id', $previousIds)->pluck('id')->all();
                if ($pendingIds === [] || array_diff($pendingIds, $obligationIds)) {
                    throw ValidationException::withMessages(['obligation_ids' => 'Inclua todos os saldos pendentes do acordo anterior.']);
                }
            }
            $total = ServiceMoney::decimal($balances->sum());
            $schedule = $this->normalizeSchedule($total, $installments);
            $plan = new ServicePaymentPlan(['status' => 'active', 'description' => 'Termo de negociação de serviços', 'created_by' => $actor->id]);
            $plan->tenant_id = $tenantId;
            $plan->save();
            foreach ($obligations as $obligation) {
                $plan->obligations()->attach($obligation->id, ['included_amount' => ServiceMoney::decimal($balances[$obligation->id])]);
            }
            foreach ($schedule as $index => $scheduled) {
                $item = $plan->installments()->make([
                    'number' => $index + 1,
                    'kind' => $scheduled['kind'],
                    'due_date' => $scheduled['due_date'],
                    'amount' => $scheduled['amount'],
                    'status' => 'scheduled',
                ]);
                $item->tenant_id = $tenantId;
                $item->save();
                app(FinancialDocumentIdentityService::class)->ensure($item, $actor);
            }
            $number = ((int) $counter->last_number) + 1;
            $sequence->update(['last_number' => $number, 'updated_at' => now()]);
            $negotiation = new ServiceNegotiation([
                'number' => sprintf('TN-%d-%06d', $year, $number),
                'status' => 'active',
                'payment_plan_id' => $plan->id,
                'supersedes_id' => $supersedes?->id,
                'original_amount' => $total,
                'negotiated_amount' => $total,
                'terms_snapshot' => [
                    'created_at' => now()->toIso8601String(),
                    'created_by' => ['id' => $actor->id, 'name' => $actor->getAttributes()['name'] ?? null],
                    'party' => array_replace((array) $obligations->first()->party_snapshot, array_filter([
                        'name' => data_get($obligations->first()->execution?->order?->beneficiary_snapshot, 'name'),
                        'document' => $obligations->first()->associate?->cpf_cnpj,
                    ])),
                    'obligations' => $obligations->map(fn (ServiceObligation $obligation) => [
                        'id' => $obligation->id,
                        'number' => $obligation->number,
                        'order_number' => $obligation->execution?->order?->number,
                        'service' => $obligation->execution?->order?->service?->name,
                        'due_date' => $obligation->due_date?->toDateString(),
                        'included_amount' => ServiceMoney::decimal($balances[$obligation->id]),
                    ])->all(),
                    'installments' => $plan->installments->map(fn ($item) => [
                        'number' => $item->number,
                        'kind' => $item->kind,
                        'due_date' => $item->due_date->toDateString(),
                        'amount' => $item->amount,
                    ])->all(),
                ],
                'created_by' => $actor->id,
            ]);
            $negotiation->tenant_id = $tenantId;
            if ($operationKey) {
                $negotiation->operation_key = $operationKey;
                $negotiation->terms_snapshot = $negotiation->terms_snapshot + ['operation_hash' => $operationHash];
            }
            $negotiation->save();
            if ($supersedes) {
                $previousPlan->installments()->where('status', '!=', 'paid')->update(['status' => 'cancelled']);
                $previousPlan->update(['status' => 'superseded']);
                $supersedes->update(['status' => 'superseded']);
            }
            activity('service_negotiation')->performedOn($negotiation)->causedBy($actor)
                ->withProperties(['obligation_ids' => $obligationIds, 'supersedes_id' => $supersedes?->id])
                ->log($supersedes ? 'Acordo de serviço renegociado' : 'Acordo de serviço criado');

            return $negotiation->fresh('plan.installments');
        }, 3);
    }

    public function normalizeSchedule(string|int|float $total, array $installments): array
    {
        $entryCount = 0;
        $schedule = collect($installments)->values()->map(function (array $item, int $index) use (&$entryCount): array {
            $kind = ($item['kind'] ?? 'installment') === 'entry' ? 'entry' : 'installment';
            $entryCount += $kind === 'entry' ? 1 : 0;
            $amount = ServiceMoney::cents($item['amount'] ?? 0);
            if ($amount <= 0) {
                throw ValidationException::withMessages(["installments.{$index}.amount" => 'Informe um valor maior que zero.']);
            }

            $rawDueDate = trim((string) ($item['due_date'] ?? ''));
            try {
                if ($rawDueDate === '') {
                    throw new \InvalidArgumentException;
                }
                $dueDate = Carbon::parse($rawDueDate)->toDateString();
            } catch (\Throwable) {
                throw ValidationException::withMessages(["installments.{$index}.due_date" => 'Informe uma data válida.']);
            }

            return ['kind' => $kind, 'due_date' => $dueDate, 'amount' => $amount];
        })->all();

        if ($entryCount > 1) {
            throw ValidationException::withMessages(['installments' => 'O plano pode possuir somente uma entrada.']);
        }
        $schedule = collect($schedule)->sortBy(fn (array $item): int => $item['kind'] === 'entry' ? 0 : 1)->values()->all();
        if (array_sum(array_column($schedule, 'amount')) !== ServiceMoney::cents($total)) {
            throw ValidationException::withMessages([
                'installments' => 'A soma da entrada e das parcelas deve ser exatamente R$ '.number_format((float) $total, 2, ',', '.').'.',
            ]);
        }

        return array_map(fn (array $item) => array_replace($item, ['amount' => ServiceMoney::decimal($item['amount'])]), $schedule);
    }
}
