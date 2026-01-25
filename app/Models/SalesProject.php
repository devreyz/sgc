<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class SalesProject extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'title',
        'code',
        'type',
        'customer_id',
        'start_date',
        'end_date',
        'reference_year',
        'total_value',
        'admin_fee_percentage',
        'status',
        'contract_number',
        'process_number',
        'document_path',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => ProjectType::class,
            'status' => ProjectStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'total_value' => 'decimal:2',
            'admin_fee_percentage' => 'decimal:2',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'code', 'type', 'status', 'total_value', 'admin_fee_percentage'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get the customer for this project.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the demands (product goals) for this project.
     */
    public function demands(): HasMany
    {
        return $this->hasMany(ProjectDemand::class);
    }

    /**
     * Get the production deliveries for this project.
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(ProductionDelivery::class);
    }

    /**
     * Get the expenses related to this project.
     */
    public function expenses(): MorphMany
    {
        return $this->morphMany(Expense::class, 'expenseable');
    }

    /**
     * Get the revenues from this project.
     */
    public function revenues(): MorphMany
    {
        return $this->morphMany(Revenue::class, 'revenueable');
    }

    /**
     * Get the documents for this project.
     */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /**
     * Get the user who created this project.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Calculate the total delivered value.
     */
    public function getTotalDeliveredValueAttribute(): float
    {
        return $this->deliveries()
            ->where('status', 'approved')
            ->sum('gross_value');
    }

    /**
     * Calculate the total admin fees collected.
     */
    public function getTotalAdminFeesAttribute(): float
    {
        return $this->deliveries()
            ->where('status', 'approved')
            ->sum('admin_fee_amount');
    }

    /**
     * Calculate the progress percentage.
     */
    public function getProgressPercentageAttribute(): float
    {
        $totalTarget = $this->demands()->sum('target_quantity');
        $totalDelivered = $this->demands()->sum('delivered_quantity');
        
        if ($totalTarget <= 0) {
            return 0;
        }
        
        return min(100, ($totalDelivered / $totalTarget) * 100);
    }

    /**
     * Check if project is active.
     */
    public function isActive(): bool
    {
        return $this->status === ProjectStatus::ACTIVE;
    }

    /**
     * Scope to get active projects
     */
    public function scopeActive($query)
    {
        return $query->where('status', ProjectStatus::ACTIVE);
    }

    /**
     * Scope to filter by type
     */
    public function scopeOfType($query, ProjectType $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to filter by year
     */
    public function scopeOfYear($query, int $year)
    {
        return $query->where('reference_year', $year);
    }
}
