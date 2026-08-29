<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Models\Associate;
use App\Models\AssociateLedger;
use App\Models\Expense;
use App\Models\Product;
use App\Models\ProductionDelivery;
use App\Models\Tenant;

class NotificationService
{
    public function __construct(private readonly TenantNotificationDispatcher $dispatcher) {}

    public function notifyDeliveryDecision(ProductionDelivery $delivery): void
    {
        $delivery->loadMissing([
            'associate:id,tenant_id,user_id',
            'product:id,name,unit',
            'salesProject:id,title',
        ]);
        $tenantId = (int) $delivery->tenant_id;
        if (! $tenantId || ! $delivery->associate || ! $delivery->product) {
            return;
        }

        if ($delivery->isDistribution() || ! in_array($delivery->status, [DeliveryStatus::APPROVED, DeliveryStatus::REJECTED], true)) {
            return;
        }

        $approved = $delivery->status === DeliveryStatus::APPROVED;
        $event = $approved ? 'delivery.approved' : 'delivery.rejected';
        $label = $approved ? 'Entrega aprovada' : 'Entrega rejeitada';

        $this->dispatcher->dispatchToConfiguredRoles($event, $tenantId, [
            'title' => $label,
            'body' => sprintf('%s: %.3f %s de %s.', $delivery->associate->display_name, (float) $delivery->quantity, $delivery->product->unit, $delivery->product->name),
            'url' => $this->deliveryUrl($delivery),
            'role_urls' => $this->deliveryRoleUrls($delivery),
            'icon' => $approved ? 'package-check' : 'package-x',
            'action_label' => 'Ver entrega',
            'action_icon' => 'truck',
        ]);
    }

    /**
     * Sends one operational summary only when a reception has no quantity left to distribute.
     */
    public function notifyDistributionCompleted(ProductionDelivery $reception, int $createdCount): void
    {
        $reception = ProductionDelivery::withoutGlobalScopes()
            ->with(['associate:id,tenant_id,user_id', 'product:id,name,unit'])
            ->find($reception->id);

        if (! $reception || ! $reception->isReception() || ! $reception->associate || ! $reception->product
            || $reception->remaining_quantity > 0.0005) {
            return;
        }

        $this->dispatcher->dispatchToConfiguredRoles('distribution.completed', (int) $reception->tenant_id, [
            'title' => 'Distribuição concluída',
            'body' => sprintf(
                '%s: toda a quantidade de %.3f %s de %s foi distribuída%s.',
                $reception->associate->display_name,
                (float) $reception->quantity,
                $reception->product->unit,
                $reception->product->name,
                $createdCount > 1 ? ' em '.$createdCount.' destinos' : ''
            ),
            'url' => $this->deliveryUrl($reception),
            'role_urls' => $this->deliveryRoleUrls($reception),
            'icon' => 'split',
            'action_label' => 'Ver distribuição',
            'action_icon' => 'split',
        ]);
    }

    public function notifyLowStock(Product $product): void
    {
        $tenantId = (int) $product->tenant_id;
        if (! $tenantId) {
            return;
        }

        $this->dispatcher->dispatchToConfiguredRoles('stock.low', $tenantId, [
            'title' => 'Estoque baixo',
            'body' => sprintf('%s possui %.2f %s em estoque.', $product->name, (float) $product->current_stock, $product->unit),
            'url' => '/admin/products/'.$product->getKey().'/edit',
            'icon' => 'triangle-alert',
        ]);
    }

    public function notifyDapCafExpiring(Associate $associate): void
    {
        $tenantId = (int) $associate->tenant_id;
        if (! $tenantId) {
            return;
        }

        $this->dispatcher->dispatchToConfiguredRoles('associate.document_expiring', $tenantId, [
            'title' => 'Documento a vencer',
            'body' => sprintf('A DAP/CAF de %s vence em %s.', $associate->display_name, $associate->dap_caf_expiry?->format('d/m/Y') ?? 'data nao informada'),
            'url' => '/admin/associates/'.$associate->getKey().'/edit',
            'icon' => 'file-warning',
        ]);
    }

    public function notifyOverdueExpense(Expense $expense): void
    {
        $tenantId = (int) $expense->tenant_id;
        if (! $tenantId) {
            return;
        }

        $this->dispatcher->dispatchToConfiguredRoles('expense.overdue', $tenantId, [
            'title' => 'Despesa vencida',
            'body' => sprintf('%s, R$ %s.', $expense->description, number_format((float) $expense->amount, 2, ',', '.')),
            'url' => '/admin/expenses/'.$expense->getKey().'/edit',
            'icon' => 'calendar-x',
        ]);
    }

    public function notifyAssociateLedgerCredit(AssociateLedger $entry): void
    {
        $this->notifyLedger($entry, 'ledger.credit', 'Credito registrado', 'arrow-up-circle');
    }

    public function notifyAssociateLedgerDebit(AssociateLedger $entry): void
    {
        $this->notifyLedger($entry, 'ledger.debit', 'Debito registrado', 'arrow-down-circle');
    }

    private function notifyLedger(AssociateLedger $entry, string $event, string $title, string $icon): void
    {
        $entry->loadMissing('associate.user');
        $associate = $entry->associate;
        $tenantId = (int) ($entry->tenant_id ?: $associate?->tenant_id);
        $user = $associate?->user;
        if (! $tenantId || ! $user) {
            return;
        }

        $tenant = Tenant::query()->find($tenantId, ['id', 'slug']);
        $this->dispatcher->dispatch($event, $tenantId, [$user], [
            'title' => $title,
            'body' => sprintf('R$ %s. %s', number_format((float) $entry->amount, 2, ',', '.'), $entry->description),
            'url' => $tenant ? route('associate.ledger', ['tenant' => $tenant->slug], false) : '/',
            'icon' => $icon,
        ]);
    }

    private function deliveryUrl(ProductionDelivery $delivery): string
    {
        $tenant = Tenant::query()->find($delivery->tenant_id, ['id', 'slug']);
        if (! $tenant || ! $delivery->sales_project_id) {
            return '/';
        }

        $url = route('delivery.projects.deliveries', [
            'tenant' => $tenant->slug,
            'project' => $delivery->sales_project_id,
        ], false);

        return $url.'?open_delivery='.(int) $delivery->getKey();
    }

    private function deliveryRoleUrls(ProductionDelivery $delivery): array
    {
        $tenant = Tenant::query()->find($delivery->tenant_id, ['id', 'slug']);
        if (! $tenant || ! $delivery->sales_project_id) {
            return [];
        }

        return [
            'associado' => route('associate.projects.show', ['tenant' => $tenant->slug, 'project' => $delivery->sales_project_id], false),
            'registrador_entregas' => $this->deliveryUrl($delivery),
            'visualizador_entregas' => route('delivery-viewer.projects.show', ['tenant' => $tenant->slug, 'project' => $delivery->sales_project_id], false).'#deliveries',
        ];
    }
}
