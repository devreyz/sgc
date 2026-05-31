<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectFee extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'sales_project_id',
        'name',
        'type',       // 'percentage' | 'fixed'
        'nature',     // 'discount' | 'accrual'
        'sort_order',
        'value',
        'active',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'value'      => 'decimal:4',
            'active'     => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function salesProject(): BelongsTo
    {
        return $this->belongsTo(SalesProject::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Calcula o valor desta taxa sobre um montante bruto.
     */
    public function calculate(string $grossAmount): string
    {
        if (! $this->active) {
            return '0';
        }

        if ($this->type === 'percentage') {
            return bcmul($grossAmount, bcdiv((string) $this->value, '100', 8), 8);
        }

        // fixed
        return (string) $this->value;
    }

    public function getNatureLabel(): string
    {
        return $this->nature === 'accrual' ? 'Acréscimo' : 'Desconto';
    }

    public function getTypeLabel(): string
    {
        return $this->type === 'percentage'
            ? number_format((float) $this->value, 2, ',', '.') . '%'
            : 'R$ ' . number_format((float) $this->value, 2, ',', '.');
    }

    /**
     * Label completo para exibição em comprovantes/relatórios.
     * Ex: "Taxa Administrativa (5,50%)" ou "Frete (R$ 150,00)"
     */
    public function getFullLabel(): string
    {
        return $this->name . ' (' . $this->getTypeLabel() . ')';
    }
}
