<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectMenu extends PlanningModel
{
    protected function casts(): array
    {
        return ['date_from' => 'date', 'date_to' => 'date', 'approved_at' => 'datetime', 'revision' => 'integer', 'version' => 'integer'];
    }

    public function days(): HasMany
    {
        return $this->hasMany(MenuDay::class);
    }
}
