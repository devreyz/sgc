<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class GeneratedDocument extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'template_id',
        'documentable_type',
        'documentable_id',
        'title',
        'content',
        'variables_used',
        'generated_by',
        'signed_at',
        'signature_file',
    ];

    protected function casts(): array
    {
        return [
            'variables_used' => 'array',
            'signed_at' => 'datetime',
        ];
    }

    /**
     * Get the template used.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'template_id');
    }

    /**
     * Get the documentable model.
     */
    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user who generated this document.
     */
    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /**
     * Check if document is signed.
     */
    public function isSigned(): bool
    {
        return !is_null($this->signed_at);
    }

    /**
     * Mark as signed.
     */
    public function markAsSigned(?string $signatureFile = null): void
    {
        $this->update([
            'signed_at' => now(),
            'signature_file' => $signatureFile,
        ]);
    }
}
