<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceNegotiation extends Model
{
    use BelongsToTenant;

    protected $fillable = ['number', 'status', 'payment_plan_id', 'supersedes_id', 'original_amount', 'negotiated_amount', 'terms_snapshot', 'generated_document_id', 'created_by'];

    protected function casts(): array
    {
        return ['original_amount' => 'decimal:2', 'negotiated_amount' => 'decimal:2', 'terms_snapshot' => 'array'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ServicePaymentPlan::class, 'payment_plan_id');
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    public function generatedDocument(): BelongsTo
    {
        return $this->belongsTo(GeneratedDocument::class, 'generated_document_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
