<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssociateReceipt extends Model
{
    protected $fillable = [
        'tenant_id',
        'sales_project_id',
        'associate_id',
        'receipt_year',
        'receipt_number',
        'issued_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'receipt_year' => 'integer',
            'receipt_number' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(SalesProject::class, 'sales_project_id');
    }

    public function associate(): BelongsTo
    {
        return $this->belongsTo(Associate::class);
    }

    /**
     * Gera o próximo número sequencial de recibo para um tenant/ano.
     */
    public static function nextNumber(int $tenantId, int $year): int
    {
        $max = static::where('tenant_id', $tenantId)
            ->where('receipt_year', $year)
            ->max('receipt_number');

        return ($max ?? 0) + 1;
    }

    /**
     * Número formatado: ex. "0042/2026"
     */
    public function getFormattedNumberAttribute(): string
    {
        return str_pad($this->receipt_number, 4, '0', STR_PAD_LEFT) . '/' . $this->receipt_year;
    }
}
