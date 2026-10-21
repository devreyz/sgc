<?php

namespace App\Models;

class DistributionDailyAudience extends PlanningModel
{
    protected $table = 'distribution_daily_audiences';

    protected function casts(): array
    {
        return ['date' => 'date', 'served_people' => 'integer'];
    }

    
}
