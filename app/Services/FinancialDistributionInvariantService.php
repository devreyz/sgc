<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\ProductionDelivery;
use App\Models\SalesProject;
use Illuminate\Support\Collection;

class FinancialDistributionInvariantService
{
    public function assertProjectContext(
        SalesProject $project,
        int $tenantId,
        int $projectId,
    ): void {
        if ((int) $project->tenant_id !== $tenantId || (int) $project->getKey() !== $projectId) {
            throw new \RuntimeException('O projeto informado nao pertence ao mesmo contexto do comprovante.');
        }
    }

    /** @param Collection<int, ProductionDelivery> $distributions */
    public function assertCommon(
        Collection $distributions,
        SalesProject $project,
        int $tenantId,
    ): void {
        $invalid = $distributions->first(function (ProductionDelivery $distribution) use ($project, $tenantId): bool {
            return (int) $distribution->tenant_id !== $tenantId
                || (int) $distribution->sales_project_id !== (int) $project->getKey()
                || is_null($distribution->parent_delivery_id)
                || is_null($distribution->customer_id)
                || is_null($distribution->product_id)
                || (float) $distribution->quantity <= 0
                || (float) $distribution->unit_price <= 0
                || $this->statusValue($distribution) !== DeliveryStatus::APPROVED->value;
        });

        if ($invalid) {
            throw new \RuntimeException(
                "A distribuicao #{$invalid->id} nao e um fato financeiro aprovado e valido para este projeto."
            );
        }

        $parentIds = $distributions->pluck('parent_delivery_id')->map(fn ($id) => (int) $id)->unique();
        $parents = ProductionDelivery::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('sales_project_id', $project->getKey())
            ->whereNull('parent_delivery_id')
            ->whereIn('id', $parentIds)
            ->lockForUpdate()
            ->get(['id', 'tenant_id', 'sales_project_id', 'associate_id', 'product_id', 'status'])
            ->keyBy('id');

        $invalidParent = $distributions->first(function (ProductionDelivery $distribution) use ($parents): bool {
            $parent = $parents->get((int) $distribution->parent_delivery_id);

            return ! $parent
                || (int) $parent->associate_id !== (int) $distribution->associate_id
                || (int) $parent->product_id !== (int) $distribution->product_id
                || $this->statusValue($parent) !== DeliveryStatus::APPROVED->value;
        });

        if ($invalidParent) {
            throw new \RuntimeException(
                "A distribuicao #{$invalidParent->id} nao possui uma entrega-pai valida no mesmo tenant e projeto."
            );
        }
    }

    /** @param Collection<int, ProductionDelivery> $distributions */
    public function assertAssociate(Collection $distributions, int $associateId): void
    {
        $invalid = $distributions->first(
            fn (ProductionDelivery $distribution): bool => (int) $distribution->associate_id !== $associateId
        );

        if ($invalid) {
            throw new \RuntimeException(
                "A distribuicao #{$invalid->id} pertence a outro membro da organizacao."
            );
        }
    }

    /** @param Collection<int, ProductionDelivery> $distributions */
    public function assertCustomerRecipient(
        Collection $distributions,
        int $tenantId,
        ?int $customerId,
        ?int $organizationId,
    ): void {
        if (($customerId === null) === ($organizationId === null)) {
            throw new \RuntimeException('A cobranca deve possuir exatamente um cliente ou uma organizacao compradora.');
        }

        $customers = Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $distributions->pluck('customer_id')->unique())
            ->get(['id', 'tenant_id', 'organization_id'])
            ->keyBy('id');

        if ($organizationId !== null && ! Organization::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereKey($organizationId)
            ->exists()) {
            throw new \RuntimeException('A organizacao compradora nao pertence ao tenant da cobranca.');
        }

        $invalid = $distributions->first(function (ProductionDelivery $distribution) use (
            $customers,
            $customerId,
            $organizationId,
        ): bool {
            $customer = $customers->get((int) $distribution->customer_id);
            if (! $customer) {
                return true;
            }

            return $customerId !== null
                ? (int) $customer->id !== $customerId
                : (int) $customer->organization_id !== $organizationId;
        });

        if ($invalid) {
            throw new \RuntimeException(
                "A distribuicao #{$invalid->id} nao pertence ao destinatario desta cobranca."
            );
        }
    }

    private function statusValue(ProductionDelivery $delivery): ?string
    {
        $status = $delivery->status;

        return $status instanceof DeliveryStatus ? $status->value : ($status ? (string) $status : null);
    }
}
