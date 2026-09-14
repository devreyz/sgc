<?php

namespace App\Console\Commands;

use App\Models\ServiceExecutionEvidence;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditServiceFoundation extends Command
{
    protected $signature = 'services:audit-foundation';

    protected $description = 'Verifica tabelas, evidências, beneficiários e vínculos de caixa dos serviços sem alterar dados';

    public function handle(): int
    {
        $failed = false;
        foreach (['service_versions', 'service_executions', 'service_execution_evidences', 'service_obligations', 'service_payment_events', 'service_payment_allocations'] as $table) {
            $exists = Schema::hasTable($table);
            $this->line($table.': '.($exists ? 'OK' : 'AUSENTE'));
            $failed = $failed || ! $exists;
        }
        if ($failed) {
            return self::FAILURE;
        }
        $this->line('Tabela resolvida pelo model de evidência: '.(new ServiceExecutionEvidence)->getTable());
        $this->line('Ordens versionadas sem nome do beneficiário: '.DB::table('service_orders')->whereNotNull('service_version_id')->get(['beneficiary_snapshot'])->filter(fn ($order) => blank(data_get(json_decode($order->beneficiary_snapshot ?? '{}', true), 'name')))->count());
        $this->line('Pagamentos confirmados sem caixa: '.DB::table('service_payment_events')->where('status', 'confirmed')->whereNull('cash_movement_id')->count());
        $this->line('Ordens do piloto antigo: '.DB::table('service_orders')->whereNull('service_version_id')->count());

        return self::SUCCESS;
    }
}
