<?php

namespace App\Models;

use App\Enums\DeliveryConferenceGroupingMode;
use App\Enums\DeliveryConferenceStatus;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class DeliveryConferenceSheet extends Model
{
    use BelongsToTenant, LogsActivity;

    protected $fillable = [
        'tenant_id', 'sales_project_id', 'customer_id', 'organization_id',
        'receipt_year', 'receipt_number', 'number', 'period_start', 'period_end',
        'grouping_mode', 'status', 'snapshot_version', 'snapshot', 'snapshot_hash',
        'issued_at', 'issued_by', 'reviewed_at', 'reviewed_by', 'review_note',
        'invalidated_at', 'invalidation_reason', 'revision', 'supersedes_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'grouping_mode' => DeliveryConferenceGroupingMode::class,
            'status' => DeliveryConferenceStatus::class,
            'snapshot' => 'array',
            'snapshot_version' => 'integer',
            'revision' => 'integer',
            'issued_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'invalidated_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly([
            'period_start', 'period_end', 'grouping_mode', 'status', 'number',
            'snapshot_hash', 'reviewed_at', 'review_note', 'invalidated_at', 'revision',
        ])->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(SalesProject::class, 'sales_project_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(self::class, 'supersedes_id');
    }

    public function distributions(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductionDelivery::class,
            'delivery_conference_sheet_items',
            'delivery_conference_sheet_id',
            'distribution_id'
        )->withTimestamps();
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function getRecipientNameAttribute(): string
    {
        return (string) ($this->organization?->name ?? $this->customer?->name ?? data_get($this->snapshot, 'recipient.name', '—'));
    }

    public function isDraft(): bool
    {
        return $this->status === DeliveryConferenceStatus::DRAFT;
    }

    public function isApproved(): bool
    {
        return $this->status === DeliveryConferenceStatus::APPROVED;
    }

    public function getFormattedNumberAttribute(): string
    {
        return $this->number ?: 'Rascunho #'.$this->getKey();
    }
}
