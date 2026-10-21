<?php

namespace App\Models;

class PlanningRuleSet extends PlanningModel
{
    protected $table = 'planning_rule_sets';

    protected function casts(): array
    {
        return ['effective_from' => 'date', 'effective_to' => 'date'];
    }

    public function rules() { return $this->hasMany(PlanningRule::class); }
}
