<?php

namespace App\Services;

use App\Models\Associate;
use App\Models\DocumentTemplate;
use App\Models\GeneratedDocument;
use App\Models\SalesProject;
use App\Models\PurchaseProject;
use Illuminate\Database\Eloquent\Model;

class DocumentGeneratorService
{
    protected array $variables = [];

    public function __construct(
        private readonly NumberInWordsService $numberInWords,
    ) {}

    /**
     * Generate document from template with context.
     */
    public function generate(
        DocumentTemplate $template, 
        ?Associate $associate = null,
        ?Model $project = null,
        array $customVariables = []
    ): GeneratedDocument {
        $this->variables = [];

        // Load cooperativa variables
        $this->loadCooperativaVariables();

        // Load date variables
        $this->loadDateVariables();

        // Load associate variables if provided
        if ($associate) {
            $this->loadAssociateVariables($associate);
        }

        // Load project variables if provided
        if ($project) {
            $this->loadProjectVariables($project);
        }

        // Merge custom variables
        $this->variables = array_merge($this->variables, $customVariables);

        // Generate document
        return $template->generateDocument($this->variables, $project ?? $associate);
    }

    /**
     * Load cooperativa info from config.
     */
    protected function loadCooperativaVariables(): void
    {
        $this->variables['{{cooperativa.nome}}'] = config('app.name', 'SGC');
        $this->variables['{{cooperativa.cnpj}}'] = config('sgc.cnpj', '00.000.000/0001-00');
        $this->variables['{{cooperativa.endereco}}'] = config('sgc.endereco', '');
        $this->variables['{{cooperativa.cidade}}'] = config('sgc.cidade', '');
        $this->variables['{{cooperativa.estado}}'] = config('sgc.estado', '');
        $this->variables['{{cooperativa.telefone}}'] = config('sgc.telefone', '');
    }

    /**
     * Load current date variables.
     */
    protected function loadDateVariables(): void
    {
        $now = now();
        
        $this->variables['{{data.hoje}}'] = $now->format('d/m/Y');
        $this->variables['{{data.hoje_extenso}}'] = $this->dateExtense($now);
        $this->variables['{{data.mes_atual}}'] = $now->translatedFormat('F');
        $this->variables['{{data.ano_atual}}'] = $now->format('Y');
        $this->variables['{{data.ano_atual_extenso}}'] = $this->numberInWords->number($now->year);
    }

    /**
     * Load associate variables.
     */
    protected function loadAssociateVariables(Associate $associate): void
    {
        $associate->load('user');

        $this->variables['{{associado.nome}}'] = $associate->display_name ?? '';
        $this->variables['{{associado.cpf}}'] = $associate->cpf_cnpj ?? '';
        $this->variables['{{associado.rg}}'] = $associate->rg ?? '';
        $this->variables['{{associado.endereco}}'] = $associate->address ?? '';
        $this->variables['{{associado.cidade}}'] = $associate->city ?? '';
        $this->variables['{{associado.estado}}'] = $associate->state ?? '';
        $this->variables['{{associado.telefone}}'] = $associate->phone ?? $associate->whatsapp ?? '';
        $this->variables['{{associado.email}}'] = $associate->user?->email ?? '';
        $this->variables['{{associado.propriedade}}'] = $associate->property_name ?? '';
        $this->variables['{{associado.dap_caf}}'] = $associate->dap_caf ?? '';
        $this->variables['{{associado.matricula}}'] = $associate->registration_number ?? '';

        // Financial
        $balance = $associate->current_balance ?? 0;
        $this->variables['{{financeiro.saldo}}'] = 'R$ ' . number_format($balance, 2, ',', '.');
        $this->variables['{{financeiro.saldo_extenso}}'] = $this->numberInWords->money($balance);
    }

    /**
     * Load project variables.
     */
    protected function loadProjectVariables(Model $project): void
    {
        if ($project instanceof SalesProject) {
            $project->load('customer');
            
            $this->variables['{{projeto.titulo}}'] = $project->title ?? '';
            $this->variables['{{projeto.numero_contrato}}'] = $project->contract_number ?? '';
            $this->variables['{{projeto.cliente}}'] = $project->customer?->name ?? '';
            $this->variables['{{projeto.data_inicio}}'] = $project->start_date?->format('d/m/Y') ?? '';
            $this->variables['{{projeto.data_inicio_extenso}}'] = $project->start_date?->translatedFormat('d \\d\\e F \\d\\e Y') ?? '';
            $this->variables['{{projeto.data_fim}}'] = $project->end_date?->format('d/m/Y') ?? '';
            $this->variables['{{projeto.data_fim_extenso}}'] = $project->end_date?->translatedFormat('d \\d\\e F \\d\\e Y') ?? '';
            $projectValue = $project->total_value ?? 0;
            $adminFeePercentage = $project->admin_fee_percentage ?? 0;
            $this->variables['{{projeto.valor_total}}'] = 'R$ ' . number_format($projectValue, 2, ',', '.');
            $this->variables['{{projeto.valor_total_extenso}}'] = $this->numberInWords->money($projectValue);
            $this->variables['{{projeto.taxa_admin}}'] = number_format($adminFeePercentage, 1, ',', '.') . '%';
            $this->variables['{{projeto.taxa_admin_extenso}}'] = $this->numberInWords->percentage($adminFeePercentage);
        } elseif ($project instanceof PurchaseProject) {
            $this->variables['{{projeto.titulo}}'] = $project->title ?? '';
            $this->variables['{{projeto.numero_contrato}}'] = $project->contract_number ?? '';
            $this->variables['{{projeto.cliente}}'] = '';
            $this->variables['{{projeto.data_inicio}}'] = $project->start_date?->format('d/m/Y') ?? '';
            $this->variables['{{projeto.data_inicio_extenso}}'] = $project->start_date?->translatedFormat('d \\d\\e F \\d\\e Y') ?? '';
            $this->variables['{{projeto.data_fim}}'] = $project->end_date?->format('d/m/Y') ?? '';
            $this->variables['{{projeto.data_fim_extenso}}'] = $project->end_date?->translatedFormat('d \\d\\e F \\d\\e Y') ?? '';
            $projectValue = $project->estimated_value ?? 0;
            $this->variables['{{projeto.valor_total}}'] = 'R$ ' . number_format($projectValue, 2, ',', '.');
            $this->variables['{{projeto.valor_total_extenso}}'] = $this->numberInWords->money($projectValue);
            $this->variables['{{projeto.taxa_admin}}'] = '';
            $this->variables['{{projeto.taxa_admin_extenso}}'] = '';
        }
    }

    /**
     * Set a financial value.
     */
    public function setFinancialValue(float $value): self
    {
        $this->variables['{{financeiro.valor}}'] = 'R$ ' . number_format($value, 2, ',', '.');
        $this->variables['{{financeiro.valor_extenso}}'] = $this->numberInWords->money($value);
        
        return $this;
    }

    /**
     * Convert date to extensive format in Portuguese.
     */
    protected function dateExtense(\Carbon\Carbon $date): string
    {
        return $date->translatedFormat('d \d\e F \d\e Y');
    }

    /**
     * Get current variables.
     */
    public function getVariables(): array
    {
        return $this->variables;
    }
}
