<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryConferenceSheetItem extends Model
{
    protected $guarded = ['id'];

    public function sheet(): BelongsTo
    {
        return $this->belongsTo(DeliveryConferenceSheet::class, 'delivery_conference_sheet_id');
    }

    public function distribution(): BelongsTo
    {
        return $this->belongsTo(ProductionDelivery::class, 'distribution_id');
    }
}
