<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class ServiceVersionField extends Model
{
    use BelongsToTenant;

    public const TYPES = ['text', 'textarea', 'integer', 'decimal', 'money', 'quantity', 'date', 'datetime', 'boolean', 'select', 'member', 'associate', 'provider', 'asset', 'location', 'image', 'file', 'signature', 'meter'];

    public const PHASES = ['order', 'start', 'execution', 'finish', 'review'];

    protected $fillable = ['service_version_id', 'key', 'label', 'type', 'phase', 'section', 'required', 'visible_to_provider', 'editable_by_provider', 'visible_to_management', 'include_in_documents', 'reportable', 'sort_order', 'unit', 'decimal_places', 'minimum', 'maximum', 'default_value', 'options', 'conditional_rule', 'evidence_for_field', 'placeholder', 'help'];

    protected function casts(): array
    {
        return ['required' => 'boolean', 'visible_to_provider' => 'boolean', 'editable_by_provider' => 'boolean', 'visible_to_management' => 'boolean', 'include_in_documents' => 'boolean', 'reportable' => 'boolean', 'default_value' => 'array', 'options' => 'array', 'conditional_rule' => 'array', 'minimum' => 'decimal:4', 'maximum' => 'decimal:4'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $field): void {
            if ($field->version?->isPublished()) {
                throw ValidationException::withMessages(['field' => 'Campos de versão publicada são imutáveis.']);
            }
        });
        static::deleting(function (self $field): void {
            if ($field->version?->isPublished()) {
                throw ValidationException::withMessages(['field' => 'Campos de versão publicada não podem ser excluídos.']);
            }
        });
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ServiceVersion::class, 'service_version_id');
    }

    public function snapshot(): array
    {
        return $this->only(['key', 'label', 'type', 'phase', 'section', 'required', 'visible_to_provider', 'editable_by_provider', 'visible_to_management', 'include_in_documents', 'reportable', 'sort_order', 'unit', 'decimal_places', 'minimum', 'maximum', 'default_value', 'options', 'conditional_rule', 'evidence_for_field', 'placeholder', 'help']);
    }
}
