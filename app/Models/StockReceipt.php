<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Recebimento Avulso de Estoque
 * Entrada de produto sem vínculo a projeto.
 * Status só muda via ação "Confirmar" ou "Cancelar".
 */
class StockReceipt extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'product_id',
        'supplier_id',
        'origin_type',
        'associate_id',
        'origin_name',
        'origin_document',
        'origin_phone',
        'quantity',
        'unit_cost',
        'total_cost',
        'status',
        'receipt_date',
        'batch',
        'expiry_date',
        'quality_grade',
        'quality_notes',
        'stock_movement_id',
        'associate_ledger_id',
        'notes',
        'confirmed_by',
        'confirmed_at',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity'     => 'decimal:3',
            'unit_cost'    => 'decimal:2',
            'total_cost'   => 'decimal:2',
            'receipt_date' => 'date',
            'expiry_date'  => 'date',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'quantity', 'unit_cost'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function associate(): BelongsTo
    {
        return $this->belongsTo(Associate::class);
    }

    public function associateLedger(): BelongsTo
    {
        return $this->belongsTo(AssociateLedger::class);
    }

    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }

    /**
     * Get the origin display name.
     */
    public function getOriginDisplayAttribute(): string
    {
        return match ($this->origin_type) {
            'supplier'  => $this->supplier?->name ?? 'Fornecedor não informado',
            'associate' => optional($this->associate?->user)->name ?? $this->associate?->property_name ?? 'Associado',
            'other'     => $this->origin_name ?? 'Não informado',
            default     => $this->supplier?->name ?? $this->origin_name ?? '—',
        };
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }
}
