<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentVerification extends Model
{
    protected $fillable = [
        'hash',
        'document_template_id',
        'verification_data',
        'generated_by',
        'generated_at',
        'verified_at',
        'verification_count',
        'last_verification_ip',
    ];

    protected function casts(): array
    {
        return [
            'verification_data' => 'array',
            'generated_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'document_template_id');
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /**
     * Mark document as verified
     */
    public function markAsVerified(string $ip = null): void
    {
        $this->update([
            'verified_at' => now(),
            'verification_count' => $this->verification_count + 1,
            'last_verification_ip' => $ip,
        ]);
    }
}
