<?php

namespace App\Models;

class PlanningReportSnapshot extends PlanningModel
{
    protected $table = 'planning_report_snapshots';

    protected function casts(): array
    {
        return ['parameters' => 'array', 'result' => 'array'];
    }

    
}
