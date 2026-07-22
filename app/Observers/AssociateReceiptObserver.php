<?php

namespace App\Observers;

use App\Jobs\SyncAssociateReceiptToDrive;
use App\Models\AssociateReceipt;
use App\Models\Tenant;
use App\Services\TenantNotificationDispatcher;
use App\Enums\ReceiptStatus;

class AssociateReceiptObserver
{
    public function __construct(private readonly TenantNotificationDispatcher $notifications)
    {
    }

    public function created(AssociateReceipt $receipt): void
    {
        $this->notifyReceipt($receipt, 'receipt.generated');
    }

    public function saved(AssociateReceipt $receipt): void
    {
        if (empty($receipt->delivery_ids) || (float) ($receipt->total_net ?? 0) <= 0) {
            return;
        }

        SyncAssociateReceiptToDrive::dispatch($receipt->id)->afterCommit();

        if ($receipt->wasChanged('status') && $receipt->status === ReceiptStatus::OBSOLETE) {
            $this->notifyReceipt($receipt, 'receipt.obsolete');
        } elseif (! $receipt->wasRecentlyCreated && $receipt->wasChanged(['delivery_ids', 'total_net', 'total_gross'])) {
            $this->notifyReceipt($receipt, 'receipt.generated');
        }
    }

    private function notifyReceipt(AssociateReceipt $receipt, string $event): void
    {
        $receipt->loadMissing('associate.user');
        $tenant = Tenant::query()->find($receipt->tenant_id, ['id', 'slug']);
        if (! $tenant) return;

        $configuredRoles = $this->notifications->configuredRoles($event, $tenant->id);
        $roles = array_values(array_diff($configuredRoles, ['associado']));
        $recipients = $this->notifications->usersForRoles($tenant->id, $roles);

        $obsolete = $event === 'receipt.obsolete';
        $message = [
            'title' => $obsolete ? 'Comprovante precisa ser regenerado' : 'Comprovante gerado',
            'body' => 'Comprovante '.$receipt->formatted_number.' de '.$receipt->associate?->display_name.'.',
            'url' => route('delivery.projects.producers', ['tenant' => $tenant->slug, 'project' => $receipt->sales_project_id], false),
            'icon' => $obsolete ? 'file-warning' : 'file-check-2',
        ];
        $this->notifications->dispatch($event, $tenant->id, $recipients, $message);

        if (in_array('associado', $configuredRoles, true) && $receipt->associate?->user) {
            $message['url'] = route('associate.projects.show', [
                'tenant' => $tenant->slug,
                'project' => $receipt->sales_project_id,
            ], false);
            $this->notifications->dispatch($event, $tenant->id, [$receipt->associate->user], $message);
        }
    }
}
