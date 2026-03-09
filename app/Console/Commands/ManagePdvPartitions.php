<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ManagePdvPartitions extends Command
{
    protected $signature = 'pdv:partitions
                            {action=status : Action: status, extend, archive}
                            {--quarters=4 : Quarters ahead to create when extending}
                            {--archive-before= : Archive partitions before this date (YYYY-MM-DD)}';

    protected $description = 'Gerencia partições trimestrais da tabela pdv_sales';

    public function handle(): int
    {
        return match ($this->argument('action')) {
            'status' => $this->showStatus(),
            'extend' => $this->extendPartitions(),
            'archive' => $this->archivePartitions(),
            default => $this->error("Ação inválida. Use: status, extend, archive") ?? 1,
        };
    }

    private function showStatus(): int
    {
        $partitions = $this->getPartitions();

        if ($partitions->isEmpty()) {
            $this->warn('Tabela pdv_sales não está particionada.');
            return 0;
        }

        $this->info('Partições da tabela pdv_sales:');
        $this->table(
            ['Partição', 'Limite', 'Registros', 'Tamanho (MB)'],
            $partitions->map(fn ($p) => [
                $p->PARTITION_NAME,
                $p->PARTITION_DESCRIPTION === 'MAXVALUE'
                    ? 'MAXVALUE'
                    : Carbon::createFromTimestamp((int) $p->PARTITION_DESCRIPTION)->format('Y-m-d'),
                number_format($p->TABLE_ROWS),
                round(($p->DATA_LENGTH + $p->INDEX_LENGTH) / 1024 / 1024, 2),
            ])
        );

        $total = $partitions->sum('TABLE_ROWS');
        $totalSize = $partitions->sum(fn ($p) => $p->DATA_LENGTH + $p->INDEX_LENGTH);
        $this->info("Total: " . number_format($total) . " registros, " . round($totalSize / 1024 / 1024, 2) . " MB");

        return 0;
    }

    private function extendPartitions(): int
    {
        $partitions = $this->getPartitions();

        if ($partitions->isEmpty()) {
            $this->error('Tabela não está particionada. Execute a migration de particionamento primeiro.');
            return 1;
        }

        // Encontrar o último trimestre existente (exceto p_future)
        $lastPartition = $partitions
            ->filter(fn ($p) => $p->PARTITION_NAME !== 'p_future')
            ->sortByDesc('PARTITION_DESCRIPTION')
            ->first();

        if (!$lastPartition) {
            $this->error('Nenhuma partição trimestral encontrada.');
            return 1;
        }

        // Calcular novo limite a partir do último timestamp
        $lastTimestamp = (int) $lastPartition->PARTITION_DESCRIPTION;
        $lastDate = Carbon::createFromTimestamp($lastTimestamp);
        $quartersToAdd = (int) $this->option('quarters');

        $newParts = [];
        $current = $lastDate->copy();

        for ($i = 0; $i < $quartersToAdd; $i++) {
            $year = $current->year;
            $quarter = (int) ceil($current->month / 3);
            $name = "p{$year}_q{$quarter}";

            $nextQuarter = $current->copy()->addMonths(3);
            $newParts[] = "PARTITION {$name} VALUES LESS THAN ({$nextQuarter->timestamp})";

            $current = $nextQuarter;
        }

        if (empty($newParts)) {
            $this->info('Nenhuma partição nova necessária.');
            return 0;
        }

        // Reorganizar p_future para adicionar as novas partições antes
        $sql = "ALTER TABLE pdv_sales REORGANIZE PARTITION p_future INTO (\n"
            . implode(",\n", $newParts)
            . ",\nPARTITION p_future VALUES LESS THAN (MAXVALUE)\n)";

        $this->info("Criando {$quartersToAdd} novas partições...");
        DB::statement($sql);
        $this->info('Partições criadas com sucesso.');

        $this->showStatus();
        return 0;
    }

    private function archivePartitions(): int
    {
        $beforeDate = $this->option('archive-before');
        if (!$beforeDate) {
            $beforeDate = Carbon::now()->subYear()->startOfQuarter()->format('Y-m-d');
            $this->warn("Nenhuma data informada. Usando: {$beforeDate}");
        }

        $partitions = $this->getPartitions()
            ->filter(fn ($p) => $p->PARTITION_NAME !== 'p_future'
                && $p->PARTITION_DESCRIPTION !== 'MAXVALUE'
                && Carbon::createFromTimestamp((int) $p->PARTITION_DESCRIPTION)->format('Y-m-d') <= $beforeDate
                && $p->TABLE_ROWS == 0
            );

        if ($partitions->isEmpty()) {
            $this->info('Nenhuma partição vazia para arquivar antes de ' . $beforeDate);
            return 0;
        }

        $names = $partitions->pluck('PARTITION_NAME')->toArray();
        $this->warn('Partições vazias que serão removidas: ' . implode(', ', $names));

        if (!$this->confirm('Deseja continuar?')) {
            return 0;
        }

        foreach ($names as $name) {
            DB::statement("ALTER TABLE pdv_sales DROP PARTITION {$name}");
            $this->info("Partição {$name} removida.");
        }

        return 0;
    }

    private function getPartitions()
    {
        $dbName = DB::getDatabaseName();

        return collect(DB::select("
            SELECT PARTITION_NAME, PARTITION_DESCRIPTION, TABLE_ROWS, DATA_LENGTH, INDEX_LENGTH
            FROM information_schema.PARTITIONS
            WHERE TABLE_SCHEMA = ?
              AND TABLE_NAME = 'pdv_sales'
              AND PARTITION_NAME IS NOT NULL
            ORDER BY PARTITION_ORDINAL_POSITION
        ", [$dbName]));
    }
}
