<?php

namespace App\Models;

class NutritionTarget extends PlanningModel
{
    protected function casts(): array
    {
        return ['min_value' => 'decimal:8', 'max_value' => 'decimal:8'];
    }

    
}
