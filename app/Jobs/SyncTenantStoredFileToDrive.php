<?php

namespace App\Jobs;

use App\Models\Asset;
use App\Models\CollectivePurchase;
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
use Illuminate\Support\Facades\Storage;
use Throwable;

class SyncTenantStoredFileToDrive implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 120;

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

    public static function dispatchExistingForTenant(int $tenantId): void
    {
        foreach (self::TYPES as $modelClass => [$field]) {
            $modelClass::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereNotNull($field)
                ->where($field, '!=', '')
                ->select('id')
                ->chunkById(100, function ($records) use ($modelClass): void {
                    foreach ($records as $record) {
                        self::dispatch($modelClass, (int) $record->getKey());
                    }
                });
        }
    }

    public function uniqueId(): string
    {
        return hash('sha256', $this->modelClass.'|'.$this->modelId);
    }

    public function handle(TenantGoogleDriveService $drive): void
    {
        $definition = self::TYPES[$this->modelClass] ?? null;
        if (! $definition || ! is_subclass_of($this->modelClass, Model::class)) {
            return;
        }

        /** @var Model|null $model */
        $model = $this->modelClass::withoutGlobalScopes()->find($this->modelId);
        $path = $model?->getAttribute($definition[0]);
        $tenantId = (int) ($model?->getAttribute('tenant_id') ?? 0);

        if (! $model || ! is_string($path) || $path === '' || str_contains($path, '..') || $tenantId <= 0) {
            return;
        }

        if (! TenantCloudStorageConnection::query()->where('tenant_id', $tenantId)->where('status', 'active')->exists()) {
            return;
        }

        $disk = Storage::disk('public')->exists($path) ? Storage::disk('public') : Storage::disk(config('filesystems.default'));
        if (! $disk->exists($path)) {
            return;
        }

        try {
            $drive->putDocument(
                Tenant::query()->findOrFail($tenantId),
                $model,
                $definition[1],
                ['Arquivos', $definition[2], now()->format('Y')],
                basename(str_replace('\\', '/', $path)),
                $disk->get($path),
                $disk->mimeType($path) ?: 'application/octet-stream',
            );
        } catch (Throwable) {
            activity('cloud_storage')->withProperties([
                'tenant_id' => $tenantId,
                'document_type' => $definition[1],
                'record_id' => $model->getKey(),
            ])->log('Falha ao sincronizar arquivo enviado');
        }
    }
}
