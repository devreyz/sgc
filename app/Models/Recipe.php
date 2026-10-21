<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Recipe extends PlanningModel
{
    protected function casts(): array
    {
        return ['yield_portions' => 'decimal:8', 'portion_weight_g' => 'decimal:8'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(RecipeItem::class);
    }
}
