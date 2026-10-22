<?php

namespace App\Services\Accounting;

use App\Enums\CustomerReceiptStatus;
use App\Enums\ReceiptStatus;
use App\Jobs\SyncAssociateReceiptToDrive;
use App\Jobs\SyncCustomerBillingReceiptToDrive;
use App\Models\AssociateReceipt;
use App\Models\CloudDocument;
use App\Models\CustomerBillingReceipt;
use App\Models\TenantCloudStorageConnection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class AccountingReceiptDocumentService
{
    /** Refresh the PDF only; never freeze/reopen or recalculate a financial document. */
    public function requestRefresh(AssociateReceipt|CustomerBillingReceipt $receipt): void
    {
        $valid = $receipt instanceof AssociateReceipt
            ? ! in_array($receipt->status, [ReceiptStatus::DRAFT, ReceiptStatus::OBSOLETE, ReceiptStatus::CANCELLED], true)
            : $receipt->status !== CustomerReceiptStatus::DRAFT;
        if (! $valid || empty($receipt->delivery_ids)) {
            throw ValidationException::withMessages(['document' => 'Confira e emita/atualize o conteúdo do comprovante antes de sincronizar o PDF. Documentos cancelados não podem ser reativados.']);
        }
        if (! Schema::hasTable('cloud_documents') || ! Schema::hasTable('tenant_cloud_storage_connections') || ! TenantCloudStorageConnection::query()
            ->where('tenant_id', $receipt->tenant_id)->where('provider', 'google_drive')->where('status', 'active')->exists()) {
            throw ValidationException::withMessages(['document' => 'Google Drive não conectado. Você ainda pode baixar um PDF gerado agora; reconecte o Drive para atualizar a cópia arquivada.']);
        }
        $connection = config('queue.default');
        if (config("queue.connections.{$connection}.driver") === 'sync') {
            throw ValidationException::withMessages(['document' => 'A atualização do Drive requer uma fila assíncrona (documents). Configure o worker; o download atualizado continua disponível.']);
        }
        $document = CloudDocument::unguarded(fn () => CloudDocument::query()->firstOrCreate([
            'tenant_id' => $receipt->tenant_id, 'provider' => 'google_drive',
            'document_type' => $receipt instanceof AssociateReceipt ? 'associate_receipt' : 'customer_billing_receipt',
            'documentable_type' => $receipt->getMorphClass(), 'documentable_id' => $receipt->id,
        ], ['remote_path' => 'Comprovantes/atualizacao-pendente/comprovante-'.$receipt->id.'.pdf', 'status' => 'pending']));
        $previous = $document->only(['status', 'metadata', 'last_error']);
        $document->forceFill([
            'status' => 'pending', 'last_error' => null,
            'metadata' => array_merge($document->metadata ?? [], ['visual_refresh_requested_at' => now()->toIso8601String()]),
        ])->save();
        try {
            if ($receipt instanceof AssociateReceipt) {
                SyncAssociateReceiptToDrive::dispatch((int) $receipt->id);
            } else {
                SyncCustomerBillingReceiptToDrive::dispatch((int) $receipt->id);
            }
        } catch (\Throwable $exception) {
            if ($document->wasRecentlyCreated) $this->recordFailure($receipt);
            else $document->forceFill($previous)->save();
            report($exception);
            throw ValidationException::withMessages(['document' => 'Não foi possível agendar a atualização. Tente novamente; o download atualizado continua disponível.']);
        }
    }

    public function recordFailure(AssociateReceipt|CustomerBillingReceipt $receipt): void
    {
        $document = $this->document($receipt);
        if ($document && data_get($document->metadata, 'visual_refresh_requested_at')) {
            $document->forceFill(['status' => 'failed', 'last_error' => 'A atualização solicitada não foi concluída. Confira a conexão e os dados do comprovante.'])->save();
        }
    }

    public function document(AssociateReceipt|CustomerBillingReceipt $receipt): ?CloudDocument
    {
        if (! Schema::hasTable('cloud_documents')) return null;

        return CloudDocument::query()->where('tenant_id', $receipt->tenant_id)->where('provider', 'google_drive')
            ->where('documentable_type', $receipt->getMorphClass())->where('documentable_id', $receipt->id)
            ->where('document_type', $receipt instanceof AssociateReceipt ? 'associate_receipt' : 'customer_billing_receipt')->first();
    }
}
