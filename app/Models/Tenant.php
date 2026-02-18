<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Tenant extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'legal_name',
        'slug',
        'cnpj',
        'state_registration',
        'municipal_registration',
        'email',
        'phone',
        'mobile',
        'website',
        'address',
        'address_number',
        'address_complement',
        'neighborhood',
        'city',
        'state',
        'zip_code',
        'country',
        'latitude',
        'longitude',
        'logo',
        'logo_dark',
        'favicon',
        'primary_color',
        'secondary_color',
        'accent_color',
        'description',
        'mission',
        'vision',
        'values',
        'foundation_date',
        'social_media',
        'active',
        'has_public_portal',
        'public_slug',
        'public_description',
        'public_features',
        'settings',
        'document_settings',
        'bank_name',
        'bank_code',
        'bank_agency',
        'bank_account',
        'pix_key',
        'legal_representative_name',
        'legal_representative_cpf',
        'legal_representative_role',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'has_public_portal' => 'boolean',
            'settings' => 'array',
            'social_media' => 'array',
            'public_features' => 'array',
            'document_settings' => 'array',
            'foundation_date' => 'date',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    /**
     * Activity Log configuration
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'slug', 'active', 'settings'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-generate slug from name if not provided
        static::creating(function ($tenant) {
            if (empty($tenant->slug)) {
                $tenant->slug = Str::slug($tenant->name);
            }
        });
    }

    /**
     * Get all users belonging to this tenant.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_user')
            ->withPivot('is_admin')
            ->withTimestamps();
    }

    /**
     * Get admin users for this tenant.
     */
    public function admins(): BelongsToMany
    {
        return $this->users()->wherePivot('is_admin', true);
    }

    /**
     * Check if a user is an admin of this tenant.
     */
    public function isAdmin(User $user): bool
    {
        return $this->users()
            ->wherePivot('user_id', $user->id)
            ->wherePivot('is_admin', true)
            ->exists();
    }

    /**
     * Add a user to this tenant.
     */
    public function addUser(User $user, bool $isAdmin = false): void
    {
        if (!$this->users()->where('user_id', $user->id)->exists()) {
            $this->users()->attach($user->id, ['is_admin' => $isAdmin]);
        }
    }

    /**
     * Remove a user from this tenant.
     */
    /**
     * DEPRECADO: Use TenantUser::deactivate() ao invés de remover vínculos.
     * Vínculos NUNCA devem ser removidos, apenas desativados.
     *
     * @throws \RuntimeException sempre
     */
    public function removeUser(User $user): void
    {
        throw new \RuntimeException(
            'Vínculos não podem ser removidos. Use TenantUser::deactivate() para desativar o acesso.'
        );
    }

    /**
     * Make a user an admin of this tenant.
     */
    public function makeAdmin(User $user): void
    {
        $this->users()->updateExistingPivot($user->id, ['is_admin' => true]);
    }

    /**
     * Remove admin privileges from a user.
     */
    public function removeAdmin(User $user): void
    {
        $this->users()->updateExistingPivot($user->id, ['is_admin' => false]);
    }

    /**
     * Scope to get only active tenants.
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope to get tenants with public portal active.
     */
    public function scopeWithPublicPortal($query)
    {
        return $query->where('has_public_portal', true);
    }

    /**
     * Get the full address formatted.
     */
    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address,
            $this->address_number,
            $this->address_complement,
            $this->neighborhood,
            $this->city,
            $this->state,
            $this->zip_code,
        ]);

        return implode(', ', $parts);
    }

    /**
     * Get logo URL.
     */
    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo ? asset('storage/' . $this->logo) : null;
    }

    /**
     * Get logo dark URL.
     */
    public function getLogoDarkUrlAttribute(): ?string
    {
        return $this->logo_dark ? asset('storage/' . $this->logo_dark) : null;
    }

    /**
     * Get favicon URL.
     */
    public function getFaviconUrlAttribute(): ?string
    {
        return $this->favicon ? asset('storage/' . $this->favicon) : null;
    }

    /**
     * Check if tenant has complete address.
     */
    public function hasCompleteAddress(): bool
    {
        return !empty($this->address) 
            && !empty($this->city) 
            && !empty($this->state);
    }

    /**
     * Check if tenant has branding configured.
     */
    public function hasBranding(): bool
    {
        return !empty($this->logo) 
            || !empty($this->primary_color);
    }
}
