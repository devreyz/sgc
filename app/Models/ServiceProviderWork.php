<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceProviderWork extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'service_provider_id',
        'service_order_id',
        'associate_id',
        'work_date',
        'description',
        'hours_worked',
        'unit_price',
        'total_value',
        'location',
        'payment_status',
        'paid_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'paid_date' => 'date',
            'hours_worked' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'total_value' => 'decimal:2',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        // Quando criar um trabalho, registrar crédito no ledger
        static::created(function ($model) {
            if ($model->service_provider_id && $model->payment_status === 'pendente') {
                $currentBalance = $model->serviceProvider->current_balance ?? 0;
                
                ServiceProviderLedger::create([
                    'service_provider_id' => $model->service_provider_id,
                    'type' => \App\Enums\LedgerType::CREDIT,
                    'category' => \App\Enums\ProviderLedgerCategory::SERVICO_PRESTADO,
                    'amount' => $model->total_value,
                    'balance_after' => $currentBalance + $model->total_value,
                    'description' => "Serviço prestado - {$model->description}",
                    'reference_type' => get_class($model),
                    'reference_id' => $model->id,
                    'transaction_date' => $model->work_date,
                    'created_by' => auth()->id(),
                ]);
            }
        });
    }

    public function serviceProvider(): BelongsTo
    {
        return $this->belongsTo(ServiceProvider::class);
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function associate(): BelongsTo
    {
        return $this->belongsTo(Associate::class);
    }

    public function getPaymentStatusLabelAttribute(): string
    {
        return match ($this->payment_status) {
            'pendente' => 'Pendente',
            'pago' => 'Pago',
            'cancelado' => 'Cancelado',
            default => ucfirst($this->payment_status),
        };
    }

    public function scopePending($query)
    {
        return $query->where('payment_status', 'pendente');
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', 'pago');
    }
}
