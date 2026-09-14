<?php

namespace App\Services\Services;

use App\Models\ServiceCompositionLine;
use App\Models\ServiceExecution;
use App\Models\ServiceObligation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GenerateServiceObligations
{
    public function __construct(private CanonicalServiceSnapshot $snapshots) {}

    public function handle(ServiceExecution $execution, array $composition, User $actor): array
    {
        return DB::transaction(function () use ($execution, $composition, $actor): array {
            $execution = ServiceExecution::query()->whereKey($execution->id)->where('tenant_id', $execution->tenant_id)->lockForUpdate()->firstOrFail();
            $created = [];
            foreach (['receivable', 'payable'] as $direction) {
                $lines = $composition[$direction] ?? [];
                $total = (float) ($composition[$direction.'_total'] ?? 0);
                $existing = ServiceObligation::query()->where('tenant_id', $execution->tenant_id)->where('service_execution_id', $execution->id)->where('direction', $direction)->first();
                if ($existing) {
                    $created[$direction] = $existing;

                    continue;
                }
                foreach ($execution->compositionLines()->where('direction', $direction)->exists() ? [] : $lines as $line) {
                    $model = new ServiceCompositionLine($line + ['created_by' => $actor->id]);
                    $model->tenant_id = $execution->tenant_id;
                    $model->service_execution_id = $execution->id;
                    $model->save();
                }
                if ($total <= 0) {
                    continue;
                }
                $year = (int) now()->format('Y');
                DB::table('service_obligation_sequences')->insertOrIgnore(['tenant_id' => $execution->tenant_id, 'year' => $year, 'last_number' => 0, 'created_at' => now(), 'updated_at' => now()]);
                $counter = DB::table('service_obligation_sequences')->where('tenant_id', $execution->tenant_id)->where('year', $year)->lockForUpdate()->first();
                $number = ((int) $counter->last_number) + 1;
                DB::table('service_obligation_sequences')->where('tenant_id', $execution->tenant_id)->where('year', $year)->update(['last_number' => $number, 'updated_at' => now()]);
                $party = $direction === 'receivable' ? ['type' => 'associate', 'id' => $execution->associate_id, 'name' => $execution->order->beneficiary_snapshot['name'] ?? null] : ['type' => 'service_provider', 'id' => $execution->service_provider_id, 'name' => $execution->order->provider_snapshot['name'] ?? null];
                $snapshot = ['execution_id' => $execution->id, 'direction' => $direction, 'lines' => $lines, 'total' => $total];
                $obligation = new ServiceObligation(['number' => sprintf('OB-%d-%06d', $year, $number), 'service_execution_id' => $execution->id, 'direction' => $direction, 'associate_id' => $direction === 'receivable' ? $execution->associate_id : null, 'service_provider_id' => $direction === 'payable' ? $execution->service_provider_id : null, 'principal_amount' => $total, 'adjustment_amount' => 0, 'status' => 'open', 'due_date' => now()->toDateString(), 'party_snapshot' => $party, 'composition_snapshot' => $snapshot, 'snapshot_hash' => $this->snapshots->hash($snapshot), 'operation_key' => (string) Str::uuid(), 'frozen_at' => now(), 'created_by' => $actor->id]);
                $obligation->tenant_id = $execution->tenant_id;
                $obligation->save();
                $created[$direction] = $obligation;
            }

            return $created;
        }, 3);
    }
}
