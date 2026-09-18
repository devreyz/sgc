<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Support\Facades\DB;

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
        'is_pdv_default',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'valid_from'  => 'date',
            'valid_until' => 'date',
            'active'      => 'boolean',
            'is_pdv_default' => 'boolean',
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

    protected static function booted(): void
    {
        static::updating(function (self $table) {
            if ($table->getOriginal('is_pdv_default') && ! $table->active) {
                throw new \LogicException('Defina outra tabela como padrão do PDV antes de desativar esta.');
            }
        });

        static::deleting(function (self $table) {
            if ($table->is_pdv_default) {
                throw new \LogicException('Defina outra tabela como padrão do PDV antes de excluir esta.');
            }
        });
    }

    /** Define a única tabela padrão do PDV para esta organização. */
    public static function setPdvDefault(int $tenantId, int $priceTableId): void
    {
        DB::transaction(function () use ($tenantId, $priceTableId) {
            $table = static::query()->where('tenant_id', $tenantId)->where('active', true)->lockForUpdate()->findOrFail($priceTableId);
            static::query()->where('tenant_id', $tenantId)->update(['is_pdv_default' => false]);
            $table->update(['is_pdv_default' => true]);
        });
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
