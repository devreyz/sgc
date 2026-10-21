<?php

namespace App\Models;

class DistributionConsumptionProfile extends PlanningModel
{
    protected $table = 'distribution_consumption_profiles';

    protected $fillable = [
        'tenant_id', 'sales_project_id', 'distribution_id', 'beneficiary_group_id',
        'count_method', 'served_people', 'service_events', 'person_days_direct',
        'coverage_start_date', 'coverage_end_date', 'shelf_life_days_snapshot',
        'expires_at', 'expiry_source', 'mass_conversion_factor_snapshot',
        'coverage_source', 'calculation_enabled', 'notes', 'created_by', 'updated_by',
        'customer_id_snapshot', 'product_id_snapshot',
    ];

    protected function casts(): array
    {
        return ['coverage_start_date' => 'date', 'coverage_end_date' => 'date', 'expires_at' => 'date', 'calculation_enabled' => 'boolean', 'mass_conversion_factor_snapshot' => 'decimal:8', 'person_days_direct' => 'decimal:8'];
    }

    public function dailyAudiences() { return $this->hasMany(DistributionDailyAudience::class); }
    public function distribution() { return $this->belongsTo(ProductionDelivery::class, 'distribution_id'); }
    public function beneficiaryGroup() { return $this->belongsTo(BeneficiaryGroup::class); }
}
