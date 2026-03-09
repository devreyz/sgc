<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PdvSale extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'pdv_customer_id',
        'customer_name',
        'subtotal',
        'discount_amount',
        'discount_percent',
        'tax_amount',
        'total',
        'amount_paid',
        'change_amount',
        'status',
        'is_fiado',
        'fiado_due_date',
        'interest_rate',
        'notes',
        'created_by',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'change_amount' => 'decimal:2',
            'is_fiado' => 'boolean',
            'fiado_due_date' => 'date',
            'interest_rate' => 'decimal:2',
            'cancelled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(PdvCustomer::class, 'pdv_customer_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PdvSaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PdvSalePayment::class);
    }

    public function fiadoPayments(): HasMany
    {
        return $this->hasMany(PdvFiadoPayment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function getDisplayNameAttribute(): string
    {
        if ($this->customer) {
            return $this->customer->name;
        }
        return $this->customer_name ?? 'Consumidor';
    }

    public function getFiadoRemainingAttribute(): float
    {
        if (!$this->is_fiado) return 0;
        $paid = (float) $this->fiadoPayments()->sum('amount');
        return max(0, (float) $this->total - (float) $this->amount_paid - $paid);
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFiado($query)
    {
        return $query->where('is_fiado', true);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public static function generateCode(int $tenantId): string
    {
        $date = now()->format('Ymd');
        $last = static::where('tenant_id', $tenantId)
            ->where('code', 'like', "PDV-{$date}-%")
            ->orderByDesc('code')
            ->value('code');

        $seq = 1;
        if ($last) {
            $parts = explode('-', $last);
            $seq = (int) end($parts) + 1;
        }

        return sprintf('PDV-%s-%03d', $date, $seq);
    }
}
