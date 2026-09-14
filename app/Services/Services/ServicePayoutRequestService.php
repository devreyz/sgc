<?php

namespace App\Services\Services;

use App\Models\ServiceObligation;
use App\Models\ServicePayoutRequest;
use App\Models\ServicePayoutRequestItem;
use App\Models\ServiceProvider;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServicePayoutRequestService
{
    public function create(ServiceProvider $provider, array $amounts, string $operationKey, User $actor): ServicePayoutRequest
    {
        return DB::transaction(function () use ($provider, $amounts, $operationKey, $actor) {
            if ($existing = ServicePayoutRequest::query()->where('tenant_id', $provider->tenant_id)->where('operation_key', $operationKey)->first()) {
                return $existing->load('items');
            }$provider = ServiceProvider::query()->whereKey($provider->id)->where('tenant_id', $provider->tenant_id)->lockForUpdate()->firstOrFail();
            $items = [];
            $total = 0;
            foreach ($amounts as $obligationId => $requested) {
                $obligation = ServiceObligation::query()->whereKey($obligationId)->where('tenant_id', $provider->tenant_id)->where('direction', 'payable')->where('service_provider_id', $provider->id)->lockForUpdate()->first();
                if (! $obligation) {
                    throw ValidationException::withMessages(['obligations' => 'Obrigação não elegível para este prestador.']);
                }$reserved = (float) ServicePayoutRequestItem::query()->where('service_obligation_id', $obligation->id)->whereHas('request', fn ($q) => $q->whereIn('status', ['pending', 'approved']))->sum('amount');
                $available = max(0, $obligation->balance - $reserved);
                $requested = round((float) $requested, 2);
                if ($requested <= 0 || $requested > $available + 0.0001) {
                    throw ValidationException::withMessages(['amount' => 'Valor solicitado supera o saldo disponível.']);
                }$items[] = [$obligation, $requested];
                $total += $requested;
            }if ($total <= 0) {
                throw ValidationException::withMessages(['amount' => 'Selecione ao menos uma obrigação com valor disponível.']);
            }$request = new ServicePayoutRequest(['service_provider_id' => $provider->id, 'operation_key' => $operationKey, 'amount' => $total, 'status' => 'pending', 'bank_snapshot' => ['bank_name' => $provider->bank_name, 'agency' => $provider->bank_agency, 'account' => $provider->bank_account, 'pix_key' => $provider->pix_key], 'requested_by' => $actor->id]);
            $request->tenant_id = $provider->tenant_id;
            $request->save();
            foreach ($items as [$obligation,$amount]) {
                ServicePayoutRequestItem::create(['service_payout_request_id' => $request->id, 'service_obligation_id' => $obligation->id, 'amount' => $amount]);
            }

            return $request->fresh('items');
        }, 3);
    }
}
