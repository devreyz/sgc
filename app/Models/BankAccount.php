<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class BankAccount extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'name',
        'type',
        'bank_code',
        'bank_name',
        'agency',
        'agency_digit',
        'account_number',
        'account_digit',
        'initial_balance',
        'current_balance',
        'balance_date',
        'is_default',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'initial_balance' => 'decimal:2',
            'current_balance' => 'decimal:2',
            'balance_date' => 'date',
            'is_default' => 'boolean',
            'status' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'type', 'current_balance', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get expenses paid from this account.
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * Get revenues received in this account.
     */
    public function revenues(): HasMany
    {
        return $this->hasMany(Revenue::class);
    }

    /**
     * Get the full account identification.
     */
    public function getFullIdentificationAttribute(): string
    {
        if ($this->type === 'caixa') {
            return $this->name;
        }
        
        return sprintf(
            '%s - Ag: %s Conta: %s',
            $this->name,
            $this->agency . ($this->agency_digit ? "-{$this->agency_digit}" : ''),
            $this->account_number . ($this->account_digit ? "-{$this->account_digit}" : '')
        );
    }

    /**
     * Scope to get active accounts
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Scope to get the default account
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope to filter by type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
