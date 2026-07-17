<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectAssociateProductLimit extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'sales_project_id',
        'associate_id',
        'product_id',
        'max_quantity',
        'reference_unit_price',
        'status',
        'notes',
        'archived_at',
        'archived_by',
        'archive_reason',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'max_quantity' => 'decimal:4',
            'reference_unit_price' => 'decimal:4',
            'archived_at' => 'datetime',
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

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
