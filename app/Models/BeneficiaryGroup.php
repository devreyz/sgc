<?php

namespace App\Models;

class BeneficiaryGroup extends PlanningModel
{
    protected $table = 'beneficiary_groups';

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function populationPeriods() { return $this->hasMany(BeneficiaryPopulationPeriod::class); }
}
