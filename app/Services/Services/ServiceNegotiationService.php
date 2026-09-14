<?php

namespace App\Services\Services;

use App\Models\ServiceNegotiation;
use App\Models\ServiceObligation;
use App\Models\ServicePaymentPlan;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceNegotiationService
{
    public function create(int $tenantId, array $obligationIds, int $installments, string $firstDueDate, User $actor, ?ServiceNegotiation $supersedes = null): ServiceNegotiation
    {
        return DB::transaction(function () use ($tenantId, $obligationIds, $installments, $firstDueDate, $actor, $supersedes) {
            if ($installments < 1 || $installments > 120) {
                throw ValidationException::withMessages(['installments' => 'Quantidade de parcelas inválida.']);
            }$obligations = ServiceObligation::query()->where('tenant_id', $tenantId)->where('direction', 'receivable')->whereIn('id', $obligationIds)->orderBy('id')->lockForUpdate()->get();
            if ($obligations->count() !== count(array_unique($obligationIds))) {
                throw ValidationException::withMessages(['obligations' => 'Uma ou mais obrigações não pertencem à organização.']);
            }$total = round((float) $obligations->sum('balance'), 2);
            if ($total <= 0) {
                throw ValidationException::withMessages(['obligations' => 'As obrigações selecionadas não possuem saldo.']);
            }$plan = new ServicePaymentPlan(['status' => 'active', 'description' => 'Termo de negociação de serviços', 'created_by' => $actor->id]);
            $plan->tenant_id = $tenantId;
            $plan->save();
            foreach ($obligations as $obligation) {
                $plan->obligations()->attach($obligation->id, ['included_amount' => $obligation->balance]);
            }$base = floor(($total / $installments) * 100) / 100;
            $last = round($total - $base * ($installments - 1), 2);
            $due = Carbon::parse($firstDueDate);
            for ($i = 1; $i <= $installments; $i++) {
                $item = $plan->installments()->make(['number' => $i, 'due_date' => $due->copy()->addMonthsNoOverflow($i - 1), 'amount' => $i === $installments ? $last : $base, 'status' => 'scheduled']);
                $item->tenant_id = $tenantId;
                $item->save();
            }$year = (int) now()->format('Y');
            DB::table('service_negotiation_sequences')->insertOrIgnore(['tenant_id' => $tenantId, 'year' => $year, 'last_number' => 0, 'created_at' => now(), 'updated_at' => now()]);
            $counter = DB::table('service_negotiation_sequences')->where('tenant_id', $tenantId)->where('year', $year)->lockForUpdate()->first();
            $number = ((int) $counter->last_number) + 1;
            DB::table('service_negotiation_sequences')->where('tenant_id', $tenantId)->where('year', $year)->update(['last_number' => $number, 'updated_at' => now()]);
            $negotiation = new ServiceNegotiation(['number' => sprintf('TN-%d-%06d', $year, $number), 'status' => 'active', 'payment_plan_id' => $plan->id, 'supersedes_id' => $supersedes?->id, 'original_amount' => $total, 'negotiated_amount' => $total, 'terms_snapshot' => ['obligations' => $obligations->map(fn ($o) => ['id' => $o->id, 'number' => $o->number, 'balance' => $o->balance])->all(), 'installments' => $plan->installments->map(fn ($i) => ['number' => $i->number, 'due_date' => $i->due_date->toDateString(), 'amount' => $i->amount])->all()], 'created_by' => $actor->id]);
            $negotiation->tenant_id = $tenantId;
            $negotiation->save();
            if ($supersedes) {
                $supersedes = ServiceNegotiation::query()->where('tenant_id', $tenantId)->whereKey($supersedes->id)->lockForUpdate()->firstOrFail();
                $supersedes->update(['status' => 'superseded']);
            }

            return $negotiation->fresh('plan.installments');
        }, 3);
    }
}
