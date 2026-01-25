<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, SoftDeletes, LogsActivity, HasRoles;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => 'boolean',
        ];
    }

    /**
     * Activity Log configuration
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Check if user can access Filament panel
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->status;
    }

    /**
     * Check if user is super admin
     * This allows Filament Shield to bypass all permission checks
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    /**
     * Get the associate profile for the user.
     */
    public function associate(): HasOne
    {
        return $this->hasOne(Associate::class);
    }

    /**
     * Check if user is an associate
     */
    public function isAssociate(): bool
    {
        return $this->associate !== null;
    }

    /**
     * Get the ledger entries for the user's associate profile.
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasManyThrough(
            AssociateLedger::class,
            Associate::class,
            'user_id',
            'associate_id'
        );
    }

    /**
     * Get expenses created by this user
     */
    public function createdExpenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'created_by');
    }

    /**
     * Get service orders where this user is the operator
     */
    public function operatedServiceOrders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class, 'operator_id');
    }
}
