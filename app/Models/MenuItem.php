<?php

namespace App\Models;

class MenuItem extends PlanningModel
{
    protected function casts(): array
    {
        return ['quantity_per_person' => 'decimal:8'];
    }
}
