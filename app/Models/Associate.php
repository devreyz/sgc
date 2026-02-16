<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Associate extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'user_id',
        'cpf_cnpj',
        'rg',
        'dap_caf',
        'dap_caf_expiry',
        'property_name',
        'address',
        'district',
        'city',
        'state',
        'zip_code',
        'property_area',
        'phone',
        'whatsapp',
        'bank_name',
        'bank_agency',
        'bank_account',
        'bank_account_type',
        'pix_key',
        'pix_key_type',
        'admission_date',
        'registration_number',
        'member_code',
        'validation_token',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'dap_caf_expiry' => 'date',
            'property_area' => 'decimal:2',
            'admission_date' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['cpf_cnpj', 'dap_caf', 'dap_caf_expiry', 'property_name', 'city', 'state'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get the latest balance for the associate.
     */
    public function getCurrentBalanceAttribute(): float
    {
        return (float) ($this->ledgerEntries()->latest('id')->first()?->balance_after ?? 0);
    }

    /**
     * Get the user that owns the associate profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the ledger entries for the associate.
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(AssociateLedger::class);
    }

    /**
     * Get the production deliveries for the associate.
     */
    public function productionDeliveries(): HasMany
    {
        return $this->hasMany(ProductionDelivery::class);
    }

    /**
     * Get the purchase orders for the associate.
     */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    /**
     * Get the service orders for the associate.
     */
    public function serviceOrders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class);
    }

    /**
     * Get the documents for the associate.
     */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /**
     * Calculate current balance from ledger entries.
     */


    /**
     * Get the full name with registration number.
     */
    public function getFullIdentificationAttribute(): string
    {
        $registration = $this->registration_number ? " ({$this->registration_number})" : '';
        return $this->user->name . $registration;
    }

    /**
     * Check if DAP/CAF is expired.
     */
    public function isDapCafExpired(): bool
    {
        if (!$this->dap_caf_expiry) {
            return false;
        }
        return $this->dap_caf_expiry->isPast();
    }

    /**
     * Check if DAP/CAF is expiring soon (30 days).
     */
    public function isDapCafExpiringSoon(): bool
    {
        if (!$this->dap_caf_expiry) {
            return false;
        }
        return $this->dap_caf_expiry->isBetween(now(), now()->addDays(30));
    }

    /**
     * Scope to get active associates
     */
    public function scopeActive($query)
    {
        return $query->whereHas('user', fn($q) => $q->where('status', true));
    }
}
