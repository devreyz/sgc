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
        'financial_limit',
        'status',
        'notes',
        'valid_from',
        'valid_until',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'financial_limit' => 'decimal:2',
            'valid_from' => 'date',
            'valid_until' => 'date',
        ];
    }

    public function salesProject(): BelongsTo
    {
        return $this->belongsTo(SalesProject::class);
    }

    public function associate(): BelongsTo
    {
        return $this->belongsTo(Associate::class);
    }

}
