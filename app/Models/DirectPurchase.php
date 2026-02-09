<?php

namespace App\Models;

use App\Enums\DirectPurchaseStatus;
use App\Enums\DirectPurchasePaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class DirectPurchase extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'supplier_id',
        'total_value',
        'discount',
        'final_value',
        'status',
        'payment_status',
        'bank_account_id',
        'payment_method',
        'purchase_date',
        'expected_delivery_date',
        'actual_delivery_date',
        'payment_date',
        'invoice_number',
        'invoice_path',
        'description',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
        'received_by',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => DirectPurchaseStatus::class,
            'payment_status' => DirectPurchasePaymentStatus::class,
            'total_value' => 'decimal:2',
            'discount' => 'decimal:2',
            'final_value' => 'decimal:2',
            'purchase_date' => 'date',
            'expected_delivery_date' => 'date',
            'actual_delivery_date' => 'date',
            'payment_date' => 'date',
            'approved_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'payment_status', 'total_value', 'final_value'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            // Calcular valor final
            if (isset($model->total_value)) {
                $model->final_value = $model->total_value - ($model->discount ?? 0);
            }
        });
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DirectPurchaseItem::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function updateTotalValue(): void
    {
        $this->total_value = $this->items()->sum(\DB::raw('quantity * unit_price'));
        $this->final_value = $this->total_value - ($this->discount ?? 0);
        $this->save();
    }
}
