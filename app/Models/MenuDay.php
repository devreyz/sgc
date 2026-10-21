<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuDay extends PlanningModel
{
    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function meals(): HasMany
    {
        return $this->hasMany(MenuMeal::class);
    }
}
