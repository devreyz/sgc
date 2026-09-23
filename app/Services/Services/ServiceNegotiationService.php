<?php

namespace App\Services\Services;

use App\Models\ServiceNegotiation;
use App\Models\ServiceObligation;
use App\Models\ServicePaymentPlan;
use App\Models\User;
use App\Services\FinancialDocumentIdentityService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceNegotiationService
{
    public function create(int $tenantId, array $obligationIds, array $installments, User $actor, ?ServiceNegotiation $supersedes = null): ServiceNegotiation
    {
        return DB::transaction(function () use ($tenantId, $obligationIds, $installments, $actor, $supersedes) {
            if ($installments === [] || count($installments) > 120) {
                throw ValidationException::withMessages(['installments' => 'Quantidade de parcelas inválida.']);
            }
            $obligations = ServiceObligation::query()
                ->where('tenant_id', $tenantId)
                ->where('direction', 'receivable')
                ->whereIn('id', $obligationIds)
                ->with(['execution.order.service'])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            if ($obligations->count() !== count(array_unique($obligationIds))) {
                throw ValidationException::withMessages(['obligations' => 'Uma ou mais obrigações não pertencem à organização.']);
            }
            $parties = $obligations->map(fn (ServiceObligation $obligation): string => implode(':', [
                (string) data_get($obligation->party_snapshot, 'type', 'party'),
                (string) (data_get($obligation->party_snapshot, 'id') ?: (str(data_get($obligation->party_snapshot, 'name', ''))->ascii()->lower()->squish()->toString() ?: 'unknown-'.$obligation->id)),
            ]))->unique();
            if ($parties->count() !== 1) {
                throw ValidationException::withMessages(['obligation_ids' => 'Um termo só pode reunir obrigações da mesma pessoa. Crie um termo separado para cada beneficiário.']);
            }
            $alreadyNegotiatedQuery = DB::table('service_payment_plan_obligations as link')
                ->join('service_payment_plans as plans', 'plans.id', '=', 'link.service_payment_plan_id')
                ->where('plans.tenant_id', $tenantId)->where('plans.status', 'active')
                ->whereIn('link.service_obligation_id', $obligations->pluck('id'));
            if ($supersedes?->payment_plan_id) {
                $alreadyNegotiatedQuery->where('plans.id', '!=', $supersedes->payment_plan_id);
            }
            $alreadyNegotiated = $alreadyNegotiatedQuery->exists();
            if ($alreadyNegotiated) {
                throw ValidationException::withMessages(['obligation_ids' => 'Uma das obrigações já pertence a um termo ativo. Conclua ou substitua o termo existente antes de negociar novamente.']);
            }
            $total = round((float) $obligations->sum('balance'), 2);
            if ($total <= 0) {
                throw ValidationException::withMessages(['obligations' => 'As obrigações selecionadas não possuem saldo.']);
            }
            $schedule = $this->normalizeSchedule($total, $installments);
            $plan = new ServicePaymentPlan(['status' => 'active', 'description' => 'Termo de negociação de serviços', 'created_by' => $actor->id]);
            $plan->tenant_id = $tenantId;
            $plan->save();
            foreach ($obligations as $obligation) {
                $plan->obligations()->attach($obligation->id, ['included_amount' => $obligation->balance]);
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
            $year = (int) now()->format('Y');
            DB::table('service_negotiation_sequences')->insertOrIgnore(['tenant_id' => $tenantId, 'year' => $year, 'last_number' => 0, 'created_at' => now(), 'updated_at' => now()]);
            $counter = DB::table('service_negotiation_sequences')->where('tenant_id', $tenantId)->where('year', $year)->lockForUpdate()->first();
            $number = ((int) $counter->last_number) + 1;
            DB::table('service_negotiation_sequences')->where('tenant_id', $tenantId)->where('year', $year)->update(['last_number' => $number, 'updated_at' => now()]);
            $negotiation = new ServiceNegotiation([
                'number' => sprintf('TN-%d-%06d', $year, $number),
                'status' => 'active',
                'payment_plan_id' => $plan->id,
                'supersedes_id' => $supersedes?->id,
                'original_amount' => $total,
                'negotiated_amount' => $total,
                'terms_snapshot' => [
                    'created_at' => now()->toIso8601String(),
                    'created_by' => ['id' => $actor->id, 'name' => $actor->name],
                    'party' => $obligations->pluck('party_snapshot')->filter()->first(),
                    'obligations' => $obligations->map(fn (ServiceObligation $obligation) => [
                        'id' => $obligation->id,
                        'number' => $obligation->number,
                        'order_number' => $obligation->execution?->order?->number,
                        'service' => $obligation->execution?->order?->service?->name,
                        'due_date' => $obligation->due_date?->toDateString(),
                        'included_amount' => $obligation->balance,
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
            $negotiation->save();
            if ($supersedes) {
                $supersedes = ServiceNegotiation::query()->where('tenant_id', $tenantId)->whereKey($supersedes->id)->lockForUpdate()->firstOrFail();
                $supersedes->update(['status' => 'superseded']);
            }

            return $negotiation->fresh('plan.installments');
        }, 3);
    }

    public function normalizeSchedule(float $total, array $installments): array
    {
        $entryCount = 0;
        $schedule = collect($installments)->values()->map(function (array $item, int $index) use (&$entryCount): array {
            $kind = ($item['kind'] ?? 'installment') === 'entry' ? 'entry' : 'installment';
            $entryCount += $kind === 'entry' ? 1 : 0;
            $amount = round((float) ($item['amount'] ?? 0), 2);
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
        $scheduledTotal = round((float) collect($schedule)->sum('amount'), 2);
        if (abs($scheduledTotal - round($total, 2)) > 0.009) {
            throw ValidationException::withMessages([
                'installments' => 'A soma da entrada e das parcelas deve ser exatamente R$ '.number_format($total, 2, ',', '.').'.',
            ]);
        }

        return $schedule;
    }
}
