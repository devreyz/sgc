<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class DocumentTemplate extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'template_category',
        'system_template_key',
        'description',
        'content',
        'available_variables',
        'is_active',
        'created_by',
        'header_layout_id',
        'footer_layout_id',
        'visible_sections',
        'visible_columns',
        'custom_fields',
        'paper_size',
        'paper_orientation',
        'section_order',
    ];

    protected function casts(): array
    {
        return [
            'available_variables' => 'array',
            'visible_sections'    => 'array',
            'visible_columns'     => 'array',
            'custom_fields'       => 'array',
            'section_order'       => 'array',
            'is_active'           => 'boolean',
        ];
    }

    // Template categories
    const CATEGORIES = [
        'system' => 'PDF do Sistema',
        'custom' => 'Personalizado',
    ];

    // Available template types
    public const TYPES = [
        'contract' => 'Contrato',
        'declaration' => 'Declaração',
        'receipt' => 'Recibo',
        'authorization' => 'Autorização',
        'report' => 'Relatório',
        'other' => 'Outro',
    ];

    // Paper sizes
    const PAPER_SIZES = [
        'a4'     => 'A4',
        'a3'     => 'A3',
        'letter' => 'Letter',
        'legal'  => 'Legal',
    ];

    // Paper orientations
    const PAPER_ORIENTATIONS = [
        'portrait'  => 'Retrato',
        'landscape' => 'Paisagem',
    ];

    // Custom field types
    const FIELD_TYPES = [
        'text'     => 'Texto',
        'textarea' => 'Texto Longo',
        'number'   => 'Número',
        'currency' => 'Valor Monetário',
        'date'     => 'Data',
        'select'   => 'Seleção',
    ];

    /**
     * System template definitions: key => [label, blade_view, sections, columns]
     */
    public static function getSystemTemplateDefinitions(): array
    {
        return [
            'deliveries_associate' => [
                'label'       => 'Entregas por Associado',
                'blade_view'  => 'pdf.deliveries-by-associate',
                'type'        => 'report',
                'description' => 'Relatório de entregas agrupadas por associado',
                'sections' => [
                    'filters'        => 'Filtros Aplicados',
                    'summary_cards'  => 'Cards de Resumo',
                    'deliveries'     => 'Tabela de Entregas',
                    'totals'         => 'Totais por Grupo',
                ],
                'columns' => [
                    'date'        => 'Data',
                    'project'     => 'Projeto',
                    'product'     => 'Produto',
                    'quantity'    => 'Quantidade',
                    'unit_value'  => 'Valor Unitário',
                    'gross_value' => 'Valor Bruto',
                    'admin_fee'   => 'Taxa Adm.',
                    'net_value'   => 'Valor Líquido',
                    'status'      => 'Status',
                ],
                'paper_orientation' => 'landscape',
            ],
            'deliveries_product' => [
                'label'       => 'Entregas por Produto',
                'blade_view'  => 'pdf.deliveries-by-product',
                'type'        => 'report',
                'description' => 'Relatório de entregas agrupadas por produto',
                'sections' => [
                    'filters'       => 'Filtros Aplicados',
                    'summary_cards' => 'Cards de Resumo',
                    'deliveries'    => 'Tabela de Entregas',
                    'totals'        => 'Totais por Grupo',
                ],
                'columns' => [
                    'date'          => 'Data',
                    'associate'     => 'Associado',
                    'quantity'      => 'Quantidade',
                    'unit_value'    => 'Valor Unitário',
                    'gross_value'   => 'Valor Bruto',
                    'admin_fee'     => 'Taxa Adm.',
                    'net_value'     => 'Valor Líquido',
                    'status'        => 'Status',
                ],
                'paper_orientation' => 'landscape',
            ],
            'deliveries_report' => [
                'label'       => 'Relatório Geral de Entregas',
                'blade_view'  => 'pdf.deliveries-report-v2',
                'type'        => 'report',
                'description' => 'Relatório geral de entregas com filtros',
                'sections' => [
                    'filters'       => 'Filtros Aplicados',
                    'summary_cards' => 'Cards de Resumo',
                    'data_table'    => 'Tabela de Dados',
                ],
                'columns' => [
                    'date'          => 'Data',
                    'associate'     => 'Associado',
                    'product'       => 'Produto',
                    'quantity'      => 'Quantidade',
                    'gross_value'   => 'Valor Bruto',
                    'admin_fee'     => 'Taxa Adm.',
                    'net_value'     => 'Valor Líquido',
                    'status'        => 'Status',
                    'project'       => 'Projeto',
                ],
                'paper_orientation' => 'landscape',
            ],
            'folha_campo' => [
                'label'       => 'Folha de Campo',
                'blade_view'  => 'pdf.folha-campo',
                'type'        => 'other',
                'description' => 'Folha de campo para visitas e registros de projeto',
                'sections' => [
                    'project_info'  => 'Informações do Projeto',
                    'associates'    => 'Lista de Associados',
                    'signature'     => 'Assinaturas',
                ],
                'columns' => [
                    'name'          => 'Nome',
                    'cpf'           => 'CPF',
                    'property'      => 'Propriedade',
                    'quantity'      => 'Quantidade',
                    'signature'     => 'Assinatura',
                ],
                'paper_orientation' => 'portrait',
            ],
            'project_associate_receipt' => [
                'label'       => 'Comprovante Individual de Associado',
                'blade_view'  => 'pdf.project-associate-receipt',
                'type'        => 'receipt',
                'description' => 'Comprovante de participação e pagamento por associado no projeto',
                'sections' => [
                    'associate_info' => 'Dados do Associado',
                    'project_info'   => 'Dados do Projeto',
                    'deliveries'     => 'Entregas',
                    'financial'      => 'Resumo Financeiro',
                    'signature'      => 'Assinatura',
                ],
                'columns' => [
                    'date'           => 'Data',
                    'product'        => 'Produto',
                    'quantity'       => 'Quantidade',
                    'unit_value'     => 'Valor Unitário',
                    'gross_value'    => 'Valor Bruto',
                    'admin_fee'      => 'Taxa Adm.',
                    'net_value'      => 'Valor Líquido',
                ],
                'paper_orientation' => 'portrait',
            ],
            'project_final_report' => [
                'label'       => 'Relatório Final de Projeto',
                'blade_view'  => 'pdf.project-final-report-v2',
                'type'        => 'report',
                'description' => 'Relatório final consolidado do projeto',
                'sections' => [
                    'executive_summary' => 'Resumo Executivo',
                    'project_info'      => 'Informações do Projeto',
                    'deliveries'        => 'Entregas Realizadas',
                    'associates'        => 'Participação por Associado',
                    'financial'         => 'Resumo Financeiro',
                ],
                'columns' => [
                    'associate'     => 'Associado',
                    'deliveries'    => 'Nº Entregas',
                    'quantity'      => 'Quantidade',
                    'gross_value'   => 'Valor Bruto',
                    'admin_fee'     => 'Taxa Adm.',
                    'net_value'     => 'Valor Líquido',
                ],
                'paper_orientation' => 'portrait',
            ],
            'cash_movement_report' => [
                'label'       => 'Relatório de Movimentação de Caixa',
                'blade_view'  => 'pdf.cash-movement-report',
                'type'        => 'report',
                'description' => 'Relatório de entradas e saídas financeiras',
                'sections' => [
                    'filters'       => 'Filtros Aplicados',
                    'summary'       => 'Resumo Financeiro',
                    'transactions'  => 'Lançamentos',
                ],
                'columns' => [
                    'date'          => 'Data',
                    'description'   => 'Descrição',
                    'type'          => 'Tipo',
                    'value'         => 'Valor',
                    'balance'       => 'Saldo',
                ],
                'paper_orientation' => 'portrait',
            ],
            'associate_statement' => [
                'label'       => 'Declaração de Associado',
                'blade_view'  => 'pdf.associate-statement',
                'type'        => 'declaration',
                'description' => 'Declaração confirmando vínculo de associado',
                'sections' => [
                    'header'        => 'Cabeçalho',
                    'content'       => 'Conteúdo da Declaração',
                    'signature'     => 'Assinatura',
                ],
                'columns' => [],
                'paper_orientation' => 'portrait',
            ],
            'service_provider_statement' => [
                'label'       => 'Declaração de Prestador de Serviços',
                'blade_view'  => 'pdf.service-provider-statement',
                'type'        => 'declaration',
                'description' => 'Declaração de prestação de serviços',
                'sections' => [
                    'header'        => 'Cabeçalho',
                    'content'       => 'Conteúdo da Declaração',
                    'signature'     => 'Assinatura',
                ],
                'columns' => [],
                'paper_orientation' => 'portrait',
            ],
            'service_providers_report' => [
                'label'       => 'Relatório de Prestadores de Serviços',
                'blade_view'  => 'pdf.service-providers-report',
                'type'        => 'report',
                'description' => 'Relatório com dados dos prestadores de serviços',
                'sections' => [
                    'filters'       => 'Filtros Aplicados',
                    'summary'       => 'Resumo',
                    'data_table'    => 'Tabela de Prestadores',
                ],
                'columns' => [
                    'name'          => 'Nome',
                    'cpf_cnpj'      => 'CPF/CNPJ',
                    'service_type'  => 'Tipo de Serviço',
                    'value'         => 'Valor',
                    'status'        => 'Status',
                ],
                'paper_orientation' => 'landscape',
            ],
        ];
    }

    /**
     * Check if this is a system template.
     */
    public function isSystem(): bool
    {
        return $this->template_category === 'system';
    }

    /**
     * Check if a section is visible.
     */
    public function isSectionVisible(string $section): bool
    {
        if (empty($this->visible_sections)) {
            return true;
        }
        return in_array($section, $this->visible_sections);
    }

    /**
     * Check if a column is visible.
     */
    public function isColumnVisible(string $column): bool
    {
        if (empty($this->visible_columns)) {
            return true;
        }
        return in_array($column, $this->visible_columns);
    }

    /**
     * Get section definition for system template.
     */
    public function getSystemDefinition(): ?array
    {
        if (!$this->system_template_key) {
            return null;
        }
        return static::getSystemTemplateDefinitions()[$this->system_template_key] ?? null;
    }

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

    public function headerLayout(): BelongsTo
    {
        return $this->belongsTo(PdfLayoutTemplate::class, 'header_layout_id');
    }

    public function footerLayout(): BelongsTo
    {
        return $this->belongsTo(PdfLayoutTemplate::class, 'footer_layout_id');
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
            'generated_by' => Auth::id(),
        ]);
    }
}
