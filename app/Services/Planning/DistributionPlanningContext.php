<?php

namespace App\Services\Planning;

use App\Models\DistributionConsumptionProfile;
use App\Models\ProductionDelivery;
use Illuminate\Support\Facades\Schema;

final class DistributionPlanningContext
{
    public function reason(ProductionDelivery $distribution, ?DistributionConsumptionProfile $profile = null): ?string
    {
        $parent = $distribution->parentDelivery;
        $status = fn ($record) => $record?->status instanceof \BackedEnum ? $record->status->value : $record?->status;
        if (! $distribution->isDistribution() || $status($distribution) !== 'approved'
            || ! $parent || $parent->parent_delivery_id !== null || $status($parent) !== 'approved'
            || (int) $parent->tenant_id !== (int) $distribution->tenant_id
            || (int) $parent->sales_project_id !== (int) $distribution->sales_project_id
            || (int) $parent->product_id !== (int) $distribution->product_id
            || (int) $parent->associate_id !== (int) $distribution->associate_id
            || ! $distribution->customer || (int) $distribution->customer->tenant_id !== (int) $distribution->tenant_id
            || ! $distribution->product || (int) $distribution->product->tenant_id !== (int) $distribution->tenant_id) {
            return 'invalid_distribution_context';
        }
        if ($profile && (($profile->customer_id_snapshot !== null && (int) $profile->customer_id_snapshot !== (int) $distribution->customer_id)
            || ($profile->product_id_snapshot !== null && (int) $profile->product_id_snapshot !== (int) $distribution->product_id))) {
            return 'changed_distribution_context';
        }
        if ($profile?->beneficiary_group_id) {
            $group = $profile->beneficiaryGroup;
            if (! $group || (int) $group->tenant_id !== (int) $distribution->tenant_id
                || (int) $group->sales_project_id !== (int) $distribution->sales_project_id
                || ($group->customer_id && (int) $group->customer_id !== (int) $distribution->customer_id)) {
                return 'invalid_beneficiary_context';
            }
        }
        return null;
    }

    /** Preserve the original audience destination before an operational reassignment. */
    public function preserveOriginalContext(ProductionDelivery $distribution): void
    {
        if (! $distribution->isDirty(['customer_id', 'product_id', 'sales_project_id', 'tenant_id', 'parent_delivery_id'])
            || ! Schema::hasColumn('distribution_consumption_profiles', 'customer_id_snapshot')) {
            return;
        }
        $profile = DistributionConsumptionProfile::withoutGlobalScopes()->where('tenant_id', $distribution->getRawOriginal('tenant_id'))
            ->where('sales_project_id', $distribution->getRawOriginal('sales_project_id'))->where('distribution_id', $distribution->id)->first();
        if ($profile && ($profile->customer_id_snapshot === null || $profile->product_id_snapshot === null)) {
            $profile->customer_id_snapshot ??= $distribution->getRawOriginal('customer_id');
            $profile->product_id_snapshot ??= $distribution->getRawOriginal('product_id');
            $profile->save();
        }
    }
}
