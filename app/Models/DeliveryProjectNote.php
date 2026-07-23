<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryProjectNote extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'sales_project_id',
        'production_delivery_id',
        'created_by',
        'content',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(SalesProject::class, 'sales_project_id');
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(ProductionDelivery::class, 'production_delivery_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
