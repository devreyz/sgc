<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FinancialDocumentIdentity extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'public_id',
        'reference_code',
        'documentable_type',
        'documentable_id',
        'created_by',
    ];

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function checks(): HasMany
    {
        return $this->hasMany(FinancialCheckInstrument::class);
    }
}
