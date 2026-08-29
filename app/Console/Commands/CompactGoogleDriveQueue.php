<?php

namespace App\Console\Commands;

use App\Jobs\SyncAssociateReceiptToDrive;
use App\Models\AssociateReceipt;
use App\Services\AssociateReceiptDriveState;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CompactGoogleDriveQueue extends Command
{
    protected $signature = 'drive:compact-queue';

    protected $description = 'Remove tarefas duplicadas ou permanentemente inválidas de comprovantes do Drive';

    public function handle(AssociateReceiptDriveState $state): int
    {
        $removedDuplicates = 0;
        $removedInvalid = 0;
        $removedFailures = 0;

        if (Schema::hasTable('jobs')) {
            $groups = [];
            DB::table('jobs')->where('queue', 'documents')->whereNull('reserved_at')
                ->orderByDesc('id')->get(['id', 'payload'])
                ->each(function (object $row) use (&$groups): void {
                    $receiptId = $this->receiptId((string) $row->payload);
                    if ($receiptId) {
                        $groups[$receiptId][] = (int) $row->id;
                    }
                });

            foreach ($groups as $receiptId => $jobIds) {
                $receipt = AssociateReceipt::withoutGlobalScopes()->find($receiptId);
                if (! $receipt || ! $state->eligible($receipt)) {
                    $removedInvalid += DB::table('jobs')->whereIn('id', $jobIds)->delete();
                    continue;
                }

                // O job não carrega uma cópia do PDF: ao executar ele lê a
                // versão atual. Portanto basta manter a tarefa mais recente.
                $obsolete = array_slice($jobIds, 1);
                if ($obsolete !== []) {
                    $removedDuplicates += DB::table('jobs')->whereIn('id', $obsolete)->delete();
                }
            }
        }

        if (Schema::hasTable('failed_jobs')) {
            DB::table('failed_jobs')->where('queue', 'documents')->get(['uuid', 'payload'])
                ->each(function (object $row) use ($state, &$removedFailures): void {
                    $receiptId = $this->receiptId((string) $row->payload);
                    if (! $receiptId) {
                        return;
                    }
                    $receipt = AssociateReceipt::withoutGlobalScopes()->find($receiptId);
                    if (! $receipt || ! $state->eligible($receipt)) {
                        $removedFailures += DB::table('failed_jobs')->where('uuid', $row->uuid)->delete();
                    }
                });
        }

        $this->info("Fila compactada: {$removedDuplicates} duplicada(s), {$removedInvalid} inválida(s) e {$removedFailures} falha(s) permanente(s) removidas.");

        return self::SUCCESS;
    }

    private function receiptId(string $payload): ?int
    {
        $decoded = json_decode($payload, true);
        if (! is_array($decoded) || ltrim((string) ($decoded['displayName'] ?? ''), '\\') !== SyncAssociateReceiptToDrive::class) {
            return null;
        }

        $command = (string) data_get($decoded, 'data.command', '');
        $pattern = '/s:\d+:"(?:\\0[^"]+\\0)?receiptId";i:(\d+);/';

        return preg_match($pattern, $command, $matches) ? (int) $matches[1] : null;
    }
}
