<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'description',
        'content',
        'available_variables',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'available_variables' => 'array',
            'is_active' => 'boolean',
        ];
    }

    // Available template types
    public const TYPES = [
        'contract' => 'Contrato',
        'declaration' => 'Declaração',
        'receipt' => 'Recibo',
        'authorization' => 'Autorização',
        'report' => 'Relatório',
        'other' => 'Outro',
    ];

    // Available variable groups
    public static function getAvailableVariables(): array
    {
        return [
            'Cooperativa' => [
                '{{cooperativa.nome}}' => 'Nome da Cooperativa',
                '{{cooperativa.cnpj}}' => 'CNPJ da Cooperativa',
                '{{cooperativa.endereco}}' => 'Endereço da Cooperativa',
                '{{cooperativa.cidade}}' => 'Cidade da Cooperativa',
                '{{cooperativa.estado}}' => 'Estado da Cooperativa',
                '{{cooperativa.telefone}}' => 'Telefone da Cooperativa',
            ],
            'Associado' => [
                '{{associado.nome}}' => 'Nome do Associado',
                '{{associado.cpf}}' => 'CPF do Associado',
                '{{associado.rg}}' => 'RG do Associado',
                '{{associado.endereco}}' => 'Endereço do Associado',
                '{{associado.cidade}}' => 'Cidade do Associado',
                '{{associado.estado}}' => 'Estado do Associado',
                '{{associado.telefone}}' => 'Telefone do Associado',
                '{{associado.email}}' => 'E-mail do Associado',
                '{{associado.propriedade}}' => 'Nome da Propriedade',
                '{{associado.dap_caf}}' => 'Nº DAP/CAF',
                '{{associado.matricula}}' => 'Nº Matrícula',
            ],
            'Projeto' => [
                '{{projeto.titulo}}' => 'Título do Projeto',
                '{{projeto.numero_contrato}}' => 'Número do Contrato',
                '{{projeto.cliente}}' => 'Nome do Cliente',
                '{{projeto.data_inicio}}' => 'Data de Início',
                '{{projeto.data_fim}}' => 'Data de Término',
                '{{projeto.valor_total}}' => 'Valor Total',
                '{{projeto.taxa_admin}}' => 'Taxa Administrativa (%)',
            ],
            'Financeiro' => [
                '{{financeiro.valor}}' => 'Valor em R$',
                '{{financeiro.valor_extenso}}' => 'Valor por Extenso',
                '{{financeiro.saldo}}' => 'Saldo da Conta',
            ],
            'Data e Hora' => [
                '{{data.hoje}}' => 'Data de Hoje',
                '{{data.hoje_extenso}}' => 'Data de Hoje por Extenso',
                '{{data.mes_atual}}' => 'Mês Atual',
                '{{data.ano_atual}}' => 'Ano Atual',
            ],
        ];
    }

    /**
     * Get the creator of the template.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get generated documents from this template.
     */
    public function generatedDocuments(): HasMany
    {
        return $this->hasMany(GeneratedDocument::class, 'template_id');
    }

    /**
     * Scope for active templates.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Generate document with variables replaced.
     */
    public function generateDocument(array $variables, ?Model $documentable = null): GeneratedDocument
    {
        $content = $this->content;

        // Replace all variables
        foreach ($variables as $key => $value) {
            $content = str_replace($key, $value ?? '', $content);
        }

        // Create generated document
        return GeneratedDocument::create([
            'template_id' => $this->id,
            'documentable_type' => $documentable ? get_class($documentable) : null,
            'documentable_id' => $documentable?->id,
            'title' => $this->name . ' - ' . now()->format('d/m/Y'),
            'content' => $content,
            'variables_used' => $variables,
            'generated_by' => auth()->id(),
        ]);
    }
}
