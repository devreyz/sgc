<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DistributionBilling extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'sales_project_id',
        'associate_id',
        'reference',
        'billing_date',
        'period_start',
        'period_end',
        'total_gross',
        'total_admin_fee',
        'total_net',
        'total_distributions',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'billing_date'  => 'date',
            'period_start'  => 'date',
            'period_end'    => 'date',
            'total_gross'   => 'decimal:4',
            'total_admin_fee' => 'decimal:4',
            'total_net'     => 'decimal:4',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function distributions(): HasMany
    {
        return $this->hasMany(ProductionDelivery::class, 'distribution_billing_id');
    }
}
