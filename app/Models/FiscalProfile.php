<?php

namespace App\Models;

use App\Enums\FiscalAmountSource;
use App\Enums\FiscalDocumentType;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FiscalProfile extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'sales_project_id', 'scope_key', 'version', 'status', 'active_marker',
        'document_type', 'amount_source', 'require_issuer_tax_id', 'require_issuer_address',
        'require_recipient_tax_id', 'require_xml', 'require_pdf', 'standard_notes', 'profile_hash', 'created_by'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'active_marker' => 'boolean', 'document_type' => FiscalDocumentType::class,
            'amount_source' => FiscalAmountSource::class, 'require_issuer_tax_id' => 'boolean',
            'require_issuer_address' => 'boolean', 'require_recipient_tax_id' => 'boolean',
            'require_xml' => 'boolean', 'require_pdf' => 'boolean'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(SalesProject::class, 'sales_project_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
