<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceExecutionEvidence extends Model
{
    use BelongsToTenant;

    protected $table = 'service_execution_evidences';

    protected $fillable = ['service_execution_id', 'document_id', 'field_key', 'evidence_type', 'phase', 'sha256', 'metadata', 'uploaded_by'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function execution(): BelongsTo
    {
        return $this->belongsTo(ServiceExecution::class, 'service_execution_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
