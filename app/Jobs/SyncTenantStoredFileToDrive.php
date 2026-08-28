<?php

namespace App\Jobs;

use App\Models\Asset;
use App\Models\CollectivePurchase;
use App\Models\CloudDocument;
use App\Models\DirectPurchase;
use App\Models\Expense;
use App\Models\ProviderPaymentRequest;
use App\Models\Revenue;
use App\Models\SalesProject;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderPayment;
use App\Models\Tenant;
use App\Models\TenantCloudStorageConnection;
use App\Services\TenantGoogleDriveService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SyncTenantStoredFileToDrive implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 3600;

    public int $tries = 3;

    public int $timeout = 120;

    public bool $failOnTimeout = true;

    private const TYPES = [
        Asset::class => ['document_path', 'asset_document', 'Patrimonio'],
        CollectivePurchase::class => ['document_path', 'collective_purchase_document', 'Compras coletivas'],
        DirectPurchase::class => ['invoice_path', 'direct_purchase_invoice', 'Compras diretas'],
        Expense::class => ['document_path', 'expense_document', 'Despesas'],
        ProviderPaymentRequest::class => ['receipt_path', 'provider_payment_receipt', 'Pagamentos de prestadores'],
        Revenue::class => ['document_path', 'revenue_document', 'Receitas'],
        SalesProject::class => ['document_path', 'sales_project_document', 'Projetos de venda'],
        ServiceOrder::class => ['receipt_path', 'service_order_receipt', 'Ordens de servico'],
        ServiceOrderPayment::class => ['receipt_path', 'service_order_payment_receipt', 'Pagamentos de servicos'],
    ];

    public function __construct(public readonly string $modelClass, public readonly int $modelId)
    {
        $this->onQueue('documents');
    }

    public static function pathFieldFor(Model $model): ?string
    {
        return self::TYPES[$model::class][0] ?? null;
    }

    public static function dispatchExistingForTenant(int $tenantId): int
    {
        $dispatched = 0;
        foreach (self::TYPES as $modelClass => [$field]) {
            $modelClass::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereNotNull($field)
                ->where($field, '!=', '')
                ->select('id')
                ->chunkById(100, function ($records) use ($modelClass, &$dispatched): void {
                    foreach ($records as $record) {
                        self::dispatch($modelClass, (int) $record->getKey());
                        $dispatched++;
                    }
                });
        }

        return $dispatched;
    }

    public function uniqueId(): string
    {
        return hash('sha256', $this->modelClass.'|'.$this->modelId);
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(TenantGoogleDriveService $drive): void
    {
        $definition = self::TYPES[$this->modelClass] ?? null;
        if (! $definition || ! is_subclass_of($this->modelClass, Model::class)) {
            $this->fail(new \RuntimeException('Tipo de documento inválido para sincronização com o Google Drive.'));

            return;
        }

        /** @var Model|null $model */
        $model = $this->modelClass::withoutGlobalScopes()->find($this->modelId);
        $path = $model?->getAttribute($definition[0]);
        $tenantId = (int) ($model?->getAttribute('tenant_id') ?? 0);

        if (! $model) {
            Log::notice('Google Drive stored-file synchronization skipped because the record no longer exists.', [
                'model_class' => $this->modelClass,
                'record_id' => $this->modelId,
            ]);

            return;
        }

        if (! is_string($path) || $path === '' || str_contains($path, '..') || $tenantId <= 0) {
            $this->recordFailure($model, $tenantId, $definition, (string) $path, 'O caminho local do arquivo é inválido.');
            $this->fail(new \RuntimeException('O caminho local do arquivo é inválido.'));

            return;
        }

        if (! TenantCloudStorageConnection::query()->where('tenant_id', $tenantId)->where('status', 'active')->exists()) {
            Log::notice('Google Drive stored-file synchronization skipped because the connection is inactive.', [
                'tenant_id' => $tenantId,
                'document_type' => $definition[1],
                'record_id' => $model->getKey(),
            ]);

            return;
        }

        $disk = Storage::disk('public')->exists($path) ? Storage::disk('public') : Storage::disk(config('filesystems.default'));
        if (! $disk->exists($path)) {
            $message = 'O arquivo original não existe mais no armazenamento do servidor.';
            $this->recordFailure($model, $tenantId, $definition, $path, $message);
            $this->fail(new \RuntimeException($message));

            return;
        }

        try {
            $folders = ['Arquivos', $definition[2], now()->format('Y')];
            if ($model instanceof SalesProject) {
                $folders = [
                    'Arquivos',
                    $definition[2],
                    (string) ($model->reference_year ?: now()->format('Y')),
                    $model->driveFolderName(),
                ];
            }

            $drive->putDocument(
                Tenant::query()->findOrFail($tenantId),
                $model,
                $definition[1],
                $folders,
                basename(str_replace('\\', '/', $path)),
                $disk->get($path),
                $disk->mimeType($path) ?: 'application/octet-stream',
            );
        } catch (Throwable $exception) {
            activity('cloud_storage')->withProperties([
                'tenant_id' => $tenantId,
                'document_type' => $definition[1],
                'record_id' => $model->getKey(),
            ])->log('Falha ao sincronizar arquivo enviado');

            Log::error('Google Drive stored-file synchronization failed.', [
                'tenant_id' => $tenantId,
                'document_type' => $definition[1],
                'record_id' => $model->getKey(),
                'exception_class' => $exception::class,
                'exception_code' => $exception->getCode(),
                'error' => mb_substr($exception->getMessage(), 0, 500),
            ]);

            throw new \RuntimeException(
                'Não foi possível sincronizar o arquivo com o Google Drive. Consulte o diagnóstico da conexão.',
                0,
                $exception,
            );
        }
    }

    /** @param array{0: string, 1: string, 2: string} $definition */
    private function recordFailure(Model $model, int $tenantId, array $definition, string $path, string $message): void
    {
        if ($tenantId <= 0) {
            return;
        }

        $identity = [
            'tenant_id' => $tenantId,
            'provider' => 'google_drive',
            'document_type' => $definition[1],
            'documentable_type' => $model->getMorphClass(),
            'documentable_id' => $model->getKey(),
        ];
        $document = CloudDocument::query()->where($identity)->first() ?? new CloudDocument();
        if (! $document->exists) {
            $document->forceFill($identity);
        }
        $document->forceFill([
            'remote_path' => str_replace('\\', '/', $path ?: 'arquivo-indisponivel'),
            'status' => 'failed',
            'last_error' => $message,
        ])->save();

        TenantCloudStorageConnection::query()
            ->where('tenant_id', $tenantId)
            ->first()
            ?->forceFill(['last_error' => $message])
            ->save();

        Log::warning('Google Drive stored-file synchronization rejected before upload.', [
            'tenant_id' => $tenantId,
            'document_type' => $definition[1],
            'record_id' => $model->getKey(),
            'reason' => $message,
        ]);
    }
}
