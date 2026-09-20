<?php

namespace App\Observers;

use App\Enums\ReceiptStatus;
use App\Jobs\SyncAssociateReceiptToDrive;
use App\Models\AssociateReceipt;
use App\Models\Tenant;
use App\Services\TenantNotificationDispatcher;
use App\Services\FinancialDocumentIdentityService;

class AssociateReceiptObserver
{
    public function __construct(
        private readonly TenantNotificationDispatcher $notifications,
        private readonly FinancialDocumentIdentityService $identities,
    ) {}

    public function created(AssociateReceipt $receipt): void
    {
        $this->notifyReceipt($receipt, 'receipt.generated');
    }

    public function saved(AssociateReceipt $receipt): void
    {
        if ($receipt->wasChanged('status') && $receipt->status === ReceiptStatus::OBSOLETE) {
            $this->notifyReceipt($receipt, 'receipt.obsolete');
        } elseif (! $receipt->wasRecentlyCreated && $receipt->wasChanged(['delivery_ids', 'total_net', 'total_gross'])) {
            $this->notifyReceipt($receipt, 'receipt.generated');
        }

        $syncChanged = $receipt->wasRecentlyCreated || $receipt->wasChanged([
            'delivery_ids', 'total_gross', 'total_fees', 'total_net', 'fee_snapshot',
            'issued_at', 'notes', 'status', 'amount_paid',
        ]);

        if ($syncChanged
            && $receipt->status !== ReceiptStatus::OBSOLETE
            && ! empty($receipt->delivery_ids)
            && (float) ($receipt->total_net ?? 0) > 0) {
            SyncAssociateReceiptToDrive::dispatch($receipt->id)->afterCommit();
        }
    }

    private function notifyReceipt(AssociateReceipt $receipt, string $event): void
    {
        $receipt->loadMissing('associate.user');
        $tenant = Tenant::query()->find($receipt->tenant_id, ['id', 'slug']);
        if (! $tenant) {
            return;
        }

        $configuredRoles = $this->notifications->configuredRoles($event, $tenant->id);
        $roles = array_values(array_diff($configuredRoles, ['associado']));
        $recipients = $this->notifications->usersForRoles($tenant->id, $roles);

        $obsolete = $event === 'receipt.obsolete';
        $identity = $this->identities->ensure($receipt);
        $documentUrl = $identity
            ? route('financial-documents.show', $identity->public_id, false)
            : '/'.$tenant->slug.'/notifications';
        $message = [
            'title' => $obsolete ? 'Comprovante precisa ser regenerado' : 'Comprovante gerado',
            'body' => 'Comprovante '.$receipt->formatted_number.' de '.$receipt->associate?->display_name.'.',
            'url' => $documentUrl,
            'role_urls' => [
                'registrador_entregas' => $documentUrl,
                'associado' => $documentUrl,
                'visualizador_entregas' => $documentUrl,
                'financeiro' => $documentUrl,
                'tesoureiro' => $documentUrl,
                'admin' => $documentUrl,
            ],
            'icon' => $obsolete ? 'file-warning' : 'file-check-2',
            'action_label' => $obsolete ? 'Corrigir comprovante' : 'Ver comprovante',
            'action_icon' => 'file-text',
        ];
        $staffMessage = $message;
        $staffMessage['role_priority'] = $roles;
        $this->notifications->dispatch($event, $tenant->id, $recipients, $staffMessage);

        if (in_array('associado', $configuredRoles, true) && $receipt->associate?->user) {
            $associateMessage = $message;
            $associateMessage['role_context'] = 'associado';
            $this->notifications->dispatch($event, $tenant->id, [$receipt->associate->user], $associateMessage);
        }
    }
}
