<?php

namespace App\Services\Services;

use App\Models\CashMovement;
use App\Models\ProviderPaymentRequest;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderPayment;
use App\Models\ServiceProviderWork;
use Illuminate\Support\Facades\DB;

class ServicePilotResetService
{
    public function preflight(): array
    {
        $orderIds = DB::table('service_orders')->whereNull('service_version_id')->pluck('id');
        $workIds = DB::table('service_provider_works')->pluck('id');
        $types = [ServiceOrder::class, ServiceOrderPayment::class, ProviderPaymentRequest::class, ServiceProviderWork::class];

        return [
            'service_orders_pilot' => $orderIds->count(),
            'service_provider_works' => DB::table('service_provider_works')->count(),
            'service_order_payments' => DB::table('service_order_payments')->whereIn('service_order_id', $orderIds)->count(),
            'provider_payment_requests' => DB::table('provider_payment_requests')->whereIn('service_order_id', $orderIds)->count(),
            'service_order_additions' => DB::table('service_order_additions')->whereIn('service_order_id', $orderIds)->count(),
            'service_provider_ledgers_proven' => DB::table('service_provider_ledgers')->whereIn('reference_type', $types)->count(),
            'associate_ledgers_proven' => DB::table('associate_ledgers')->whereIn('reference_type', $types)->count(),
            'cash_movements_proven' => DB::table('cash_movements')->whereIn('reference_type', $types)->count(),
            'expenses_proven' => DB::table('expenses')->where(function ($q) use ($orderIds) {
                $q->where('expenseable_type', ServiceOrder::class)->whereIn('expenseable_id', $orderIds);
            })->count(),
            'documents_proven' => DB::table('documents')->whereIn('documentable_type', $types)->count(),
            'pilot_order_ids' => $orderIds->take(100)->all(),
            'pilot_work_ids' => $workIds->take(100)->all(),
        ];
    }

    public function reset(): array
    {
        $before = $this->preflight();
        DB::transaction(function () {
            $orderIds = DB::table('service_orders')->whereNull('service_version_id')->pluck('id');
            $types = [ServiceOrder::class, ServiceOrderPayment::class, ProviderPaymentRequest::class, ServiceProviderWork::class];
            DB::table('cloud_documents')->whereIn('owner_type', $types)->delete();
            DB::table('documents')->whereIn('documentable_type', $types)->delete();
            CashMovement::withoutGlobalScopes()->whereIn('reference_type', $types)->whereNull('deleted_at')->orderByDesc('id')->get()->each->delete();
            DB::table('associate_ledgers')->whereIn('reference_type', $types)->delete();
            DB::table('service_provider_ledgers')->whereIn('reference_type', $types)->delete();
            DB::table('expenses')->where('expenseable_type', ServiceOrder::class)->whereIn('expenseable_id', $orderIds)->delete();
            DB::table('service_order_additions')->whereIn('service_order_id', $orderIds)->delete();
            DB::table('provider_payment_requests')->whereIn('service_order_id', $orderIds)->delete();
            DB::table('service_order_payments')->whereIn('service_order_id', $orderIds)->delete();
            DB::table('service_provider_works')->delete();
            DB::table('service_orders')->whereIn('id', $orderIds)->delete();
        }, 3);

        return $before;
    }
}
