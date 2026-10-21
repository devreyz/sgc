<?php

namespace App\Models;

class ProjectPlanningSetting extends PlanningModel
{
    protected $table = 'project_planning_settings';

    protected function casts(): array
    {
        return ['default_coverage_days' => 'integer'];
    }

    
}
