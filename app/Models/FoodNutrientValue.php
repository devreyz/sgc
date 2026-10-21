<?php

namespace App\Models;

class FoodNutrientValue extends PlanningModel
{
    protected function casts(): array
    {
        return ['amount' => 'decimal:8'];
    }

    public function nutrient(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Nutrient::class);
    }
}
