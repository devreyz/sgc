<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ChartAccount extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'code',
        'name',
        'parent_id',
        'type',
        'nature',
        'allows_entries',
        'status',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'allows_entries' => 'boolean',
            'status' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['code', 'name', 'type', 'nature', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get the parent account.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ChartAccount::class, 'parent_id');
    }

    /**
     * Get the child accounts.
     */
    public function children(): HasMany
    {
        return $this->hasMany(ChartAccount::class, 'parent_id');
    }

    /**
     * Get the expenses using this account.
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * Get the revenues using this account.
     */
    public function revenues(): HasMany
    {
        return $this->hasMany(Revenue::class);
    }

    /**
     * Get the full code and name.
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->code} - {$this->name}";
    }

    /**
     * Get the full path.
     */
    public function getFullPathAttribute(): string
    {
        $path = [$this->full_name];
        $parent = $this->parent;
        
        while ($parent) {
            array_unshift($path, $parent->full_name);
            $parent = $parent->parent;
        }
        
        return implode(' > ', $path);
    }

    /**
     * Scope to get active accounts
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Scope to get accounts that allow entries
     */
    public function scopeAllowsEntries($query)
    {
        return $query->where('allows_entries', true);
    }

    /**
     * Scope to filter by type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
