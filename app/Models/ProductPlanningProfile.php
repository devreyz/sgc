<?php

namespace App\Models;

class ProductPlanningProfile extends PlanningModel
{
    protected $table = 'product_planning_profiles';

    protected function casts(): array
    {
        return ['effective_from' => 'date', 'effective_to' => 'date', 'mass_conversion_factor' => 'decimal:8'];
    }

    
}
