<?php

namespace App\Console\Commands;

use App\Services\FinancialIntegrityAuditService;
use Illuminate\Console\Command;

class AuditFinancialIntegrity extends Command
{
    protected $signature = 'finance:audit-integrity
        {--tenant= : Limita a um tenant}
        {--project= : Limita a um projeto}
        {--json : Retorna JSON para automacao}';

    protected $description = 'Audita a integridade financeira sem alterar dados';

    public function handle(FinancialIntegrityAuditService $auditor): int
    {
        $result = $auditor->audit(
            $this->option('tenant') !== null ? (int) $this->option('tenant') : null,
            $this->option('project') !== null ? (int) $this->option('project') : null,
        );

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->components->info(sprintf(
                'Auditoria concluida: %d critico(s), %d alerta(s), %d informativo(s).',
                $result['summary']['critical'],
                $result['summary']['warning'],
                $result['summary']['info'],
            ));
            $this->table(
                ['Codigo', 'Gravidade', 'Quantidade', 'Tenants', 'Projetos', 'Classe', 'Tratamento'],
                collect($result['aggregates'])->map(fn (array $row): array => [
                    $row['code'],
                    $row['severity'],
                    $row['count'],
                    implode(', ', $row['tenants']) ?: '-',
                    implode(', ', $row['projects']) ?: '-',
                    $row['classification'],
                    $row['remediation'],
                ])->all(),
            );
            $this->table(
                ['Severidade', 'Tenant', 'Projeto', 'Tipo', 'Registro', 'Codigo', 'Mensagem'],
                collect($result['issues'])->map(fn (array $issue): array => [
                    $issue['severity'],
                    $issue['tenant_id'] ?? '-',
                    $issue['project_id'] ?? '-',
                    $issue['record_type'],
                    $issue['record_id'],
                    $issue['code'],
                    $issue['message'],
                ])->all(),
            );
        }

        return $result['summary']['critical'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
