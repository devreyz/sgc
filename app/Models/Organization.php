<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Organization extends Model
{
    use BelongsToTenant, SoftDeletes, LogsActivity;

    protected $fillable = [
        'tenant_id',
        'name',
        'short_name',
        'cnpj',
        'type',
        'responsible_name',
        'responsible_role',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'notes',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'type', 'city', 'state', 'active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Clientes (escolas, creches, etc.) subordinados a esta organização.
     */
    public function clients(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /**
     * Projetos de venda que incluem esta organização.
     */
    public function salesProjects(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(SalesProject::class, 'sales_project_organizations', 'organization_id', 'sales_project_id');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public static function typeOptions(): array
    {
        return [
            'municipio'   => 'Município',
            'estado'      => 'Estado',
            'federal'     => 'Federal',
            'conab'       => 'CONAB',
            'hospital'    => 'Hospital / Saúde',
            'cooperativa' => 'Cooperativa',
            'outro'       => 'Outro',
        ];
    }
}
