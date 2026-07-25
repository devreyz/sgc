<?php

namespace App\Services;

use App\Jobs\SendWebPushNotification;
use App\Jobs\SyncAssociateReceiptToDrive;
use App\Jobs\SyncTenantStoredFileToDrive;
use App\Models\Asset;
use App\Models\AssociateReceipt;
use App\Models\CollectivePurchase;
use App\Models\DirectPurchase;
use App\Models\Expense;
use App\Models\ProviderPaymentRequest;
use App\Models\Revenue;
use App\Models\SalesProject;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderPayment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class QueueTaskInspector
{
    private const STORED_FILE_MODELS = [
        Asset::class,
        CollectivePurchase::class,
        DirectPurchase::class,
        Expense::class,
        ProviderPaymentRequest::class,
        Revenue::class,
        SalesProject::class,
        ServiceOrder::class,
        ServiceOrderPayment::class,
    ];

    private array $tenantCache = [];

    public function pendingForTenant(int $tenantId, int $limit = 100): array
    {
        if (! Schema::hasTable('jobs')) {
            return [];
        }

        return DB::table('jobs')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (object $job): ?array => $this->inspect($job, false))
            ->filter(fn (?array $job): bool => $job !== null && $job['tenant_id'] === $tenantId)
            ->values()
            ->all();
    }

    public function failedForTenant(int $tenantId, int $limit = 100): array
    {
        if (! Schema::hasTable('failed_jobs')) {
            return [];
        }

        return DB::table('failed_jobs')
            ->latest('failed_at')
            ->limit($limit)
            ->get()
            ->map(fn (object $job): ?array => $this->inspect($job, true))
            ->filter(fn (?array $job): bool => $job !== null && $job['tenant_id'] === $tenantId)
            ->values()
            ->all();
    }

    public function failedBelongsToTenant(string $uuid, int $tenantId): bool
    {
        if (! Schema::hasTable('failed_jobs')) {
            return false;
        }

        $row = DB::table('failed_jobs')->where('uuid', $uuid)->first();
        $job = $row ? $this->inspect($row, true) : null;

        return $job !== null && $job['tenant_id'] === $tenantId;
    }

    private function inspect(object $row, bool $failed): ?array
    {
        $payload = json_decode((string) $row->payload, true);
        if (! is_array($payload)) {
            return null;
        }

        $command = (string) data_get($payload, 'data.command', '');
        $class = ltrim((string) ($payload['displayName'] ?? $this->commandClass($command)), '\\');
        $tenantId = $this->tenantId($class, $command);
        if (! $tenantId) {
            return null;
        }

        $availableAt = isset($row->available_at)
            ? Carbon::createFromTimestamp((int) $row->available_at)
            : null;
        $reserved = isset($row->reserved_at) && $row->reserved_at !== null;
        $status = $failed
            ? 'failed'
            : ($reserved ? 'processing' : ($availableAt?->isFuture() ? 'scheduled' : 'waiting'));

        return [
            'id' => $row->id ?? null,
            'uuid' => $row->uuid ?? null,
            'tenant_id' => $tenantId,
            'queue' => (string) ($row->queue ?? ''),
            'name' => $this->friendlyName($class),
            'status' => $status,
            'attempts' => (int) ($row->attempts ?? 0),
            'available_at' => $availableAt,
            'failed_at' => isset($row->failed_at) ? Carbon::parse($row->failed_at) : null,
            'error' => $failed ? $this->safeError((string) ($row->exception ?? '')) : null,
        ];
    }

    private function tenantId(string $class, string $command): ?int
    {
        $direct = $this->integerProperty($command, 'tenantId');
        if ($direct) {
            return $direct;
        }

        if ($class === SyncAssociateReceiptToDrive::class) {
            $receiptId = $this->integerProperty($command, 'receiptId');
            if (! $receiptId) {
                return null;
            }

            return $this->tenantCache["receipt:{$receiptId}"] ??= (int) AssociateReceipt::query()
                ->withoutGlobalScopes()
                ->whereKey($receiptId)
                ->value('tenant_id');
        }

        if ($class === SyncTenantStoredFileToDrive::class) {
            $modelClass = $this->stringProperty($command, 'modelClass');
            $modelId = $this->integerProperty($command, 'modelId');
            if (! $modelClass || ! $modelId || ! in_array($modelClass, self::STORED_FILE_MODELS, true)) {
                return null;
            }

            $cacheKey = "model:{$modelClass}:{$modelId}";

            return $this->tenantCache[$cacheKey] ??= (int) $modelClass::query()
                ->withoutGlobalScopes()
                ->whereKey($modelId)
                ->value('tenant_id');
        }

        return null;
    }

    private function commandClass(string $command): string
    {
        return preg_match('/^O:\d+:"([^"]+)"/', $command, $matches)
            ? $matches[1]
            : '';
    }

    private function integerProperty(string $command, string $property): ?int
    {
        $pattern = '/s:\d+:"(?:\\\\0[^"]+\\\\0)?'.preg_quote($property, '/').'";i:(\d+);/';

        return preg_match($pattern, $command, $matches) ? (int) $matches[1] : null;
    }

    private function stringProperty(string $command, string $property): ?string
    {
        $pattern = '/s:\d+:"(?:\\\\0[^"]+\\\\0)?'.preg_quote($property, '/').'";s:\d+:"([^"]*)";/';

        return preg_match($pattern, $command, $matches) ? $matches[1] : null;
    }

    private function friendlyName(string $class): string
    {
        return match ($class) {
            SendWebPushNotification::class => 'Enviar notificacao push',
            SyncAssociateReceiptToDrive::class => 'Sincronizar comprovante com o Drive',
            SyncTenantStoredFileToDrive::class => 'Sincronizar arquivo com o Drive',
            default => Str::headline(class_basename($class ?: 'Tarefa')),
        };
    }

    private function safeError(string $exception): string
    {
        $firstLine = trim(Str::before($exception, "\n"));
        $firstLine = preg_replace('/[A-Z]:\\\\[^\s]+|\/(?:home|var|srv)\/[^\s]+/i', '[caminho interno]', $firstLine);

        return Str::limit(strip_tags((string) $firstLine), 240, '');
    }
}
