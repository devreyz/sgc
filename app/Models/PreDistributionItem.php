<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class PreDistributionItem extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['planned_quantity' => 'decimal:4', 'reported_quantity' => 'decimal:4', 'reference_price' => 'decimal:4', 'delivered_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function sheet()
    {
        return $this->belongsTo(PreDistributionSheet::class, 'sheet_id');
    }

    public function project()
    {
        return $this->belongsTo(SalesProject::class, 'sales_project_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
