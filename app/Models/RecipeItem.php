<?php

namespace App\Models;

class RecipeItem extends PlanningModel
{
    protected function casts(): array
    {
        return ['gross_quantity_g' => 'decimal:8'];
    }
}
