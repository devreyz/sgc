<?php

namespace App\Models;

class PlanningRule extends PlanningModel
{
    protected $table = 'planning_rules';

    protected function casts(): array
    {
        return ['value' => 'decimal:8', 'value_max' => 'decimal:8'];
    }

    
}
