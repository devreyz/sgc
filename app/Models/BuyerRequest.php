<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class BuyerRequest extends Model
{
    use BelongsToTenant, LogsActivity, SoftDeletes;

    public const STATUS_OPEN = 'open';
    public const STATUS_PARTIALLY_FULFILLED = 'partially_fulfilled';
    public const STATUS_FULFILLED = 'fulfilled';
    public const STATUS_EXCEEDED = 'exceeded';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'tenant_id',
        'sales_project_id',
        'organization_id',
        'customer_id',
        'created_by',
        'status',
        'enforce_request_limits',
        'reference_date',
        'notes',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'enforce_request_limits' => 'boolean',
            'reference_date' => 'date',
            'submitted_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['sales_project_id', 'organization_id', 'customer_id', 'status', 'notes'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function salesProject(): BelongsTo
    {
        return $this->belongsTo(SalesProject::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BuyerRequestItem::class);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PARTIALLY_FULFILLED => 'Parcialmente atendida',
            self::STATUS_FULFILLED => 'Atendida',
            self::STATUS_EXCEEDED => 'Excedida',
            self::STATUS_CANCELLED => 'Cancelada',
            default => 'Aberta',
        };
    }
}
