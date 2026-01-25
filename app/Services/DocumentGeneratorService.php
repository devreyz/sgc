<?php

namespace App\Services;

use App\Models\Associate;
use App\Models\DocumentTemplate;
use App\Models\GeneratedDocument;
use App\Models\SalesProject;
use App\Models\PurchaseProject;
use Illuminate\Database\Eloquent\Model;
use NumberFormatter;

class DocumentGeneratorService
{
    protected array $variables = [];

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
    }

    /**
     * Load associate variables.
     */
    protected function loadAssociateVariables(Associate $associate): void
    {
        $associate->load('user');

        $this->variables['{{associado.nome}}'] = $associate->user?->name ?? '';
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
            $this->variables['{{projeto.data_fim}}'] = $project->end_date?->format('d/m/Y') ?? '';
            $this->variables['{{projeto.valor_total}}'] = 'R$ ' . number_format($project->total_value ?? 0, 2, ',', '.');
            $this->variables['{{projeto.taxa_admin}}'] = number_format($project->admin_fee_percentage ?? 0, 1, ',', '.') . '%';
        } elseif ($project instanceof PurchaseProject) {
            $this->variables['{{projeto.titulo}}'] = $project->title ?? '';
            $this->variables['{{projeto.numero_contrato}}'] = $project->contract_number ?? '';
            $this->variables['{{projeto.cliente}}'] = '';
            $this->variables['{{projeto.data_inicio}}'] = $project->start_date?->format('d/m/Y') ?? '';
            $this->variables['{{projeto.data_fim}}'] = $project->end_date?->format('d/m/Y') ?? '';
            $this->variables['{{projeto.valor_total}}'] = 'R$ ' . number_format($project->estimated_value ?? 0, 2, ',', '.');
            $this->variables['{{projeto.taxa_admin}}'] = '';
        }
    }

    /**
     * Set a financial value.
     */
    public function setFinancialValue(float $value): self
    {
        $this->variables['{{financeiro.valor}}'] = 'R$ ' . number_format($value, 2, ',', '.');
        $this->variables['{{financeiro.valor_extenso}}'] = $this->moneyExtense($value);
        
        return $this;
    }

    /**
     * Convert money to words in Portuguese.
     */
    protected function moneyExtense(float $value): string
    {
        if (!class_exists(NumberFormatter::class)) {
            return 'R$ ' . number_format($value, 2, ',', '.');
        }

        $formatter = new NumberFormatter('pt_BR', NumberFormatter::SPELLOUT);
        
        $reais = floor($value);
        $centavos = round(($value - $reais) * 100);

        $text = $formatter->format($reais);
        $text .= $reais === 1.0 ? ' real' : ' reais';

        if ($centavos > 0) {
            $text .= ' e ' . $formatter->format($centavos);
            $text .= $centavos === 1.0 ? ' centavo' : ' centavos';
        }

        return ucfirst($text);
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
