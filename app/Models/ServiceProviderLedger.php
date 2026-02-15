<?php

namespace App\Models;

use App\Enums\LedgerType;
use App\Enums\ProviderLedgerCategory;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceProviderLedger extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'service_provider_id',
        'type',
        'amount',
        'balance_after',
        'description',
        'notes',
        'reference_type',
        'reference_id',
        'category',
        'created_by',
        'transaction_date',
    ];

    protected function casts(): array
    {
        return [
            'type' => LedgerType::class,
            'category' => ProviderLedgerCategory::class,
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'transaction_date' => 'date',
        ];
    }

    public function serviceProvider(): BelongsTo
    {
        return $this->belongsTo(ServiceProvider::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
