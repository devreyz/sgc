<?php

namespace App\Models;

class FoodProfile extends PlanningModel
{
    protected function casts(): array
    {
        return ['reference_quantity_g' => 'decimal:8', 'edible_fraction' => 'decimal:8', 'effective_from' => 'date', 'effective_to' => 'date'];
    }

    public function nutrientValues(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(FoodNutrientValue::class);
    }
}
