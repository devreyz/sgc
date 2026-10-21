<?php

namespace App\Models;

class BeneficiaryPopulationPeriod extends PlanningModel
{
    protected $table = 'beneficiary_population_periods';

    protected function casts(): array
    {
        return ['effective_from' => 'date', 'effective_to' => 'date', 'population' => 'integer'];
    }

    
}
