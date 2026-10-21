<?php

namespace App\Models;

class NutritionTargetSet extends PlanningModel
{
    protected function casts(): array
    {
        return ['effective_from' => 'date', 'effective_to' => 'date'];
    }

    public function targets(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(NutritionTarget::class);
    }
}
