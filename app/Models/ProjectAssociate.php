<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectAssociate extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'sales_project_id',
        'associate_id',
    ];

    public function salesProject(): BelongsTo
    {
        return $this->belongsTo(SalesProject::class);
    }

    public function associate(): BelongsTo
    {
        return $this->belongsTo(Associate::class);
    }
}
