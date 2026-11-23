<?php

namespace App\Services\Services;

use App\Models\ServiceExecution;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceOrderDetails
{
    public static function canChangeVersion(ServiceOrder $order): bool
    {
        return $order->operational_status === 'scheduled'
            && $order->execution?->status === 'draft'
            && $order->execution->started_at === null;
    }

    public static function editable(ServiceOrder $order): bool
    {
        return ! $order->isBilled() && $order->operational_status !== 'cancelled'
            && in_array($order->execution?->status, ['draft', 'in_progress', 'submitted', 'rejected', 'validated'], true);
    }

    public static function token(ServiceOrder $order): string
    {
        return hash('sha256', json_encode([
            $order->only(['execution_date', 'scheduled_at', 'scheduled_date', 'start_time', 'end_time', 'location', 'order_data', 'beneficiary_snapshot']),
            $order->execution?->status, $order->execution?->lock_version,
        ], JSON_THROW_ON_ERROR));
    }

    public function update(ServiceOrder $order, array $data, User $actor): ServiceOrder
    {
        return DB::transaction(function () use ($order, $data, $actor): ServiceOrder {
            $execution = ServiceExecution::query()->where('tenant_id', $order->tenant_id)->where('service_order_id', $order->id)->lockForUpdate()->firstOrFail();
            $locked = ServiceOrder::query()->where('tenant_id', $order->tenant_id)->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $locked->setRelation('execution', $execution);
            if (! self::editable($locked) || ! hash_equals(self::token($locked), $data['details_token'])) {
                throw ValidationException::withMessages(['order' => 'A ordem mudou. Reabra a edição para conferir os dados atuais.']);
            }
            $before = $locked->only(['execution_date', 'scheduled_at', 'start_time', 'end_time', 'location', 'order_data', 'beneficiary_snapshot']);
            $scheduled = filled($data['scheduled_at'] ?? null) ? Carbon::parse($data['scheduled_at']) : null;
            $locked->fill([
                'execution_date' => $data['execution_date'] ?? null,
                'scheduled_at' => $scheduled, 'scheduled_date' => $scheduled?->toDateString(),
                'start_time' => $data['start_time'] ?? null, 'end_time' => $data['end_time'] ?? null,
                'location' => $data['location'] ?? null,
                'order_data' => array_replace((array) $locked->order_data, ['notes' => $data['notes'] ?? null]),
                'beneficiary_snapshot' => array_replace((array) $locked->beneficiary_snapshot, ['name' => trim($data['beneficiary_name'])]),
            ]);
            if (! $locked->isDirty()) return $locked;
            $locked->save();
            if (! $execution->isFrozen()) $execution->increment('lock_version');
            activity('service_order')->performedOn($locked)->causedBy($actor)->withProperties([
                'tenant_id' => $locked->tenant_id, 'before' => $before,
                'after' => $locked->only(array_keys($before)), 'reason' => $data['reason'],
            ])->log('Detalhes da ordem corrigidos');
            return $locked;
        }, 3);
    }
}
