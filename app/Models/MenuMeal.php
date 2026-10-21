<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuMeal extends PlanningModel
{
    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }
}
