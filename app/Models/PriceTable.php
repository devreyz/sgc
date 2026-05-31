<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PriceTable extends Model
{
    use BelongsToTenant, SoftDeletes, LogsActivity;

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'year',
        'valid_from',
        'valid_until',
        'notes',
        'active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'valid_from'  => 'date',
            'valid_until' => 'date',
            'active'      => 'boolean',
            'year'        => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'code', 'year', 'active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Itens (produto × preço) desta tabela.
     */
    public function items(): HasMany
    {
        return $this->hasMany(PriceTableItem::class);
    }

    /**
     * Clientes que usam esta tabela como padrão.
     */
    public function clients(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Retorna o preço de venda para um produto nesta tabela, ou null se não cadastrado.
     */
    public function priceFor(int $productId): ?string
    {
        $item = $this->items()->where('product_id', $productId)->first();
        return $item?->sale_price ? (string) $item->sale_price : null;
    }
}
