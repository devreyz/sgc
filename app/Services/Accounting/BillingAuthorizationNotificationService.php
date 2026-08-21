<?php

namespace App\Services\Accounting;

use App\Models\BillingAuthorization;
use App\Models\OrganizationAuthorizedEmail;
use App\Models\User;
use App\Services\TenantNotificationDispatcher;
use Illuminate\Support\Facades\DB;

class BillingAuthorizationNotificationService
{
    public function __construct(private readonly TenantNotificationDispatcher $dispatcher) {}

    public function sent(BillingAuthorization $authorization, bool $resent): void
    {
        $authorization->loadMissing('receipt');
        $emails = OrganizationAuthorizedEmail::withoutGlobalScope('tenant')
            ->where('tenant_id', $authorization->tenant_id)
            ->where('organization_id', $authorization->organization_id)
            ->where('active', true)
            ->pluck('email')->map(fn (string $email): string => mb_strtolower(trim($email)))->unique();
        $users = User::query()->where('status', true)
            ->whereIn(DB::raw('LOWER(email)'), $emails->all())->get(['id', 'status']);
        $event = $resent ? 'billing.authorization.resent' : 'billing.authorization.requested';

        $this->dispatcher->dispatch($event, (int) $authorization->tenant_id, $users, [
            'title' => $resent ? 'Nova versão para autorização' : 'Cobrança para autorização',
            'body' => $authorization->receipt?->formatted_number.' aguarda sua análise.',
            'url' => route('buyer.authorizations.show', [
                'tenant' => $authorization->receipt?->tenant?->slug ?? session('tenant_slug'),
                'billingAuthorization' => $authorization->id,
            ], false),
            'icon' => 'file-check-2',
        ]);
    }

    public function responded(BillingAuthorization $authorization, bool $authorized): void
    {
        $event = $authorized ? 'billing.authorization.authorized' : 'billing.authorization.correction_requested';
        $this->dispatcher->dispatchToConfiguredRoles($event, (int) $authorization->tenant_id, [
            'title' => $authorized ? 'Faturamento autorizado' : 'Correção solicitada',
            'body' => ($authorization->receipt?->formatted_number ?? 'Cobrança').' recebeu uma resposta da organização.',
            'url' => $this->accountingUrl($authorization),
            'icon' => $authorized ? 'badge-check' : 'message-square-warning',
        ]);
    }

    public function invalidated(BillingAuthorization $authorization): void
    {
        $this->dispatcher->dispatchToConfiguredRoles('billing.authorization.invalidated', (int) $authorization->tenant_id, [
            'title' => 'Autorização invalidada',
            'body' => ($authorization->receipt?->formatted_number ?? 'Cobrança').' foi alterada e precisa de nova versão.',
            'url' => $this->accountingUrl($authorization),
            'icon' => 'shield-alert',
        ]);
    }

    private function accountingUrl(BillingAuthorization $authorization): string
    {
        $authorization->loadMissing(['receipt.tenant']);
        $slug = $authorization->receipt?->tenant?->slug ?? session('tenant_slug');

        return $slug ? route('accounting.processes.show', [
            'tenant' => $slug,
            'receipt' => $authorization->customer_billing_receipt_id,
        ], false) : '/';
    }
}
