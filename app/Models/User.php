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
    use HasFactory, HasRoles, LogsActivity, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
        'google_id',
        'avatar',
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
     * Priority: super_admin, admin, financeiro can always access
     * Block ONLY if user has exclusively associado or service_provider roles
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->status) {
            return false;
        }

        // Super admin, admin, and financeiro always have access
        if ($this->hasAnyRole(['super_admin', 'admin', 'financeiro'])) {
            return true;
        }

        // Block if user only has portal roles (no admin access)
        return false;
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
     * Get the service provider profile for the user.
     */
    public function serviceProvider(): HasOne
    {
        return $this->hasOne(ServiceProvider::class);
    }

    /**
     * Check if user is an associate
     */
    public function isAssociate(): bool
    {
        return $this->associate !== null;
    }

    /**
     * Check if user is a service provider
     */
    public function isServiceProvider(): bool
    {
        return $this->serviceProvider !== null;
    }

    /**
     * Check if user has both associate and provider profiles
     */
    public function hasSharedWallet(): bool
    {
        return $this->isAssociate() && $this->isServiceProvider();
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
