<?php

namespace App\Models;

use App\Enums\BillingAuthorizationStatus;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingAuthorization extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'customer_billing_receipt_id',
        'organization_id',
        'sequence',
        'status',
        'active_marker',
        'snapshot_version',
        'snapshot',
        'snapshot_hash',
        'current_hash',
        'operation_key',
        'sent_by',
        'sent_by_name',
        'sent_at',
        'response_decision',
        'responded_by',
        'responded_by_name',
        'responded_at',
        'response_message',
        'invalidated_at',
        'invalidated_by',
        'invalidation_reason',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'status' => BillingAuthorizationStatus::class,
            'active_marker' => 'boolean',
            'snapshot_version' => 'integer',
            'snapshot' => 'array',
            'sent_at' => 'datetime',
            'responded_at' => 'datetime',
            'invalidated_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(CustomerBillingReceipt::class, 'customer_billing_receipt_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function respondedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }

    public function invalidatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invalidated_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
