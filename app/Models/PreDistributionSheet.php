<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class PreDistributionSheet extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['operational_date' => 'date', 'version' => 'integer', 'completed_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function items()
    {
        return $this->hasMany(PreDistributionItem::class, 'sheet_id');
    }

    public function events()
    {
        return $this->hasMany(PreDistributionEvent::class, 'sheet_id');
    }

    public function projects()
    {
        return $this->belongsToMany(SalesProject::class, 'pre_distribution_sheet_projects', 'sheet_id', 'sales_project_id')->withPivot('tenant_id');
    }

    public function operator()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function getCodeAttribute(): string
    {
        return 'PD-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }
}
