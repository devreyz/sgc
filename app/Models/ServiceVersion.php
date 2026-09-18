<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class ServiceVersion extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'service_id', 'version', 'status', 'category', 'unit', 'review_mode',
        'allow_provider_create_order', 'customer_pricing_method', 'customer_rate',
        'members_only',
        'customer_percentage', 'receivable_enabled', 'provider_pricing_method',
        'default_provider_rate', 'provider_percentage', 'payable_enabled',
        'execution_config', 'financial_config', 'evidence_config', 'document_config',
        'snapshot_hash', 'published_at', 'published_by', 'retired_at',
    ];

    protected function casts(): array
    {
        return [
            'allow_provider_create_order' => 'boolean', 'receivable_enabled' => 'boolean',
            'members_only' => 'boolean',
            'payable_enabled' => 'boolean', 'customer_rate' => 'decimal:4',
            'customer_percentage' => 'decimal:4', 'default_provider_rate' => 'decimal:4',
            'provider_percentage' => 'decimal:4', 'execution_config' => 'array',
            'financial_config' => 'array', 'evidence_config' => 'array',
            'document_config' => 'array', 'published_at' => 'datetime', 'retired_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $version): void {
            if ($version->getOriginal('status') !== 'draft') {
                if (! in_array($version->status, ['published', 'retired'], true)) {
                    throw ValidationException::withMessages(['status' => 'Uma versão publicada nunca volta a rascunho.']);
                }
                $allowed = ['status', 'retired_at', 'updated_at'];
                if (array_diff(array_keys($version->getDirty()), $allowed)) {
                    throw ValidationException::withMessages(['version' => 'Versão publicada é imutável. Duplique-a para alterar a configuração.']);
                }
            }
        });
        static::deleting(function (self $version): void {
            if ($version->status !== 'draft') {
                throw ValidationException::withMessages(['version' => 'Somente versões em rascunho podem ser excluídas.']);
            }
        });
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(ServiceVersionField::class)->orderBy('sort_order');
    }

    public function providerRates(): HasMany
    {
        return $this->hasMany(ServiceProviderVersionRate::class);
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function isPublished(): bool
    {
        return $this->status !== 'draft';
    }

    public function snapshot(): array
    {
        $this->loadMissing('fields');

        $fields = $this->fields->map->snapshot()->values();
        foreach ((array) data_get($this->financial_config, 'rules', []) as $rule) {
            if (filled($rule['input_key'] ?? null) && ! $fields->contains('key', $rule['input_key'])) {
                $fields->push([
                    'key' => $rule['input_key'],
                    'label' => $rule['input_label'] ?? $rule['description'] ?? 'Valor da regra',
                    'type' => ($rule['input_role'] ?? 'quantity') === 'value' ? 'money' : 'quantity',
                    'phase' => $rule['input_phase'] ?? 'finish',
                    'section' => 'Dados financeiros',
                    'required' => (bool) ($rule['input_required'] ?? true),
                    'visible_to_provider' => true,
                    'editable_by_provider' => true,
                    'visible_to_management' => true,
                    'include_in_documents' => true,
                    'reportable' => true,
                    'sort_order' => 900,
                    'unit' => $rule['input_unit'] ?? null,
                    'decimal_places' => 4,
                    'minimum' => 0,
                    'maximum' => null,
                    'default_value' => null,
                    'options' => [],
                    'conditional_rule' => null,
                    'evidence_for_field' => null,
                    'placeholder' => null,
                    'help' => 'Campo gerado pela regra financeira: '.($rule['description'] ?? 'ajuste'),
                ]);
            }
            if (($rule['evidence_required'] ?? false) && filled($rule['evidence_key'] ?? null) && ! $fields->contains('key', $rule['evidence_key'])) {
                $fields->push([
                    'key' => $rule['evidence_key'],
                    'label' => $rule['evidence_label'] ?? 'Comprovante: '.($rule['description'] ?? 'regra financeira'),
                    'type' => 'file',
                    'phase' => $rule['input_phase'] ?? 'finish',
                    'section' => 'Evidências financeiras',
                    // A obrigatoriedade condicional é verificada pelo cálculo quando a regra for usada.
                    'required' => false,
                    'visible_to_provider' => true,
                    'editable_by_provider' => true,
                    'visible_to_management' => true,
                    'include_in_documents' => true,
                    'reportable' => true,
                    'sort_order' => 901,
                    'unit' => null,
                    'decimal_places' => null,
                    'minimum' => null,
                    'maximum' => null,
                    'default_value' => null,
                    'options' => [],
                    'conditional_rule' => null,
                    'evidence_for_field' => $rule['input_key'] ?? null,
                    'placeholder' => null,
                    'help' => 'Obrigatório quando a regra financeira produzir valor.',
                ]);
            }
        }

        return [
            'service_version_id' => $this->id, 'version' => $this->version,
            'service' => ['id' => $this->service_id, 'name' => $this->service?->name, 'code' => $this->service?->code],
            'category' => $this->category, 'unit' => $this->unit, 'review_mode' => $this->review_mode,
            'members_only' => $this->members_only,
            'customer_pricing_method' => $this->customer_pricing_method, 'customer_rate' => $this->customer_rate,
            'customer_percentage' => $this->customer_percentage, 'receivable_enabled' => $this->receivable_enabled,
            'provider_pricing_method' => $this->provider_pricing_method, 'default_provider_rate' => $this->default_provider_rate,
            'provider_percentage' => $this->provider_percentage, 'payable_enabled' => $this->payable_enabled,
            'execution_config' => $this->execution_config ?? [], 'financial_config' => $this->financial_config ?? [],
            'evidence_config' => $this->evidence_config ?? [], 'document_config' => $this->document_config ?? [],
            'fields' => $fields->values()->all(),
        ];
    }
}
