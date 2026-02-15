<?php

namespace App\Models;

use App\Enums\LedgerCategory;
use App\Enums\LedgerType;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AssociateLedger extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'associate_id',
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
            'category' => LedgerCategory::class,
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'transaction_date' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['type', 'amount', 'balance_after', 'description', 'category'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get the associate that owns the ledger entry.
     */
    public function associate(): BelongsTo
    {
        return $this->belongsTo(Associate::class);
    }

    /**
     * Get the reference model (polymorphic).
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user who created this entry.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope to get only credits
     */
    public function scopeCredits($query)
    {
        return $query->where('type', LedgerType::CREDIT);
    }

    /**
     * Scope to get only debits
     */
    public function scopeDebits($query)
    {
        return $query->where('type', LedgerType::DEBIT);
    }

    /**
     * Scope to filter by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('transaction_date', [$startDate, $endDate]);
    }

    /**
     * Scope to filter by category
     */
    public function scopeByCategory($query, LedgerCategory $category)
    {
        return $query->where('category', $category);
    }
}
