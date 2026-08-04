<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class FinancialReceiptItem extends Model
{
    protected $fillable = ['position', 'description', 'quantity', 'unit', 'unit_price', 'total_amount', 'reference'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'unit_price' => 'decimal:4', 'total_amount' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $item): void {
            if ($item->receipt && ! $item->receipt->isDraft()) {
                throw ValidationException::withMessages(['items' => 'Itens de um recibo emitido não podem ser alterados.']);
            }
            $item->total_amount = round((float) $item->quantity * (float) $item->unit_price, 2);
        });

        static::deleting(function (self $item): void {
            if ($item->receipt && ! $item->receipt->isDraft()) {
                throw ValidationException::withMessages(['items' => 'Itens de um recibo emitido não podem ser removidos.']);
            }
        });
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(FinancialReceipt::class, 'financial_receipt_id');
    }
}
