<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Models\Customer;
use App\Models\CustomerBillingReceipt;
use App\Models\ProductionDelivery;
use App\Models\SalesProject;
use Illuminate\Support\Collection;

class CustomerBillingProjectContextService
{
    /** @return Collection<int, SalesProject> */
    public function projects(int $tenantId, array $projectIds): Collection
    {
        $ids = collect($projectIds)
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            throw new \RuntimeException('Selecione ao menos um projeto para a cobrança.');
        }

        $projects = SalesProject::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $ids)
            ->orderBy('start_date')
            ->orderBy('id')
            ->get();

        if ($projects->count() !== $ids->count()) {
            throw new \RuntimeException('Um ou mais projetos não pertencem à organização atual.');
        }

        if ($projects->pluck('type')->map(fn ($type): string => (string) $type)->unique()->count() !== 1) {
            throw new \RuntimeException('Todos os projetos da mesma cobrança devem possuir o mesmo tipo.');
        }

        return $projects;
    }

    /** @return Collection<int, SalesProject> */
    public function projectsForReceipt(CustomerBillingReceipt $receipt): Collection
    {
        return $this->projects((int) $receipt->tenant_id, $receipt->projectIds());
    }

    /**
     * Garante que cada projeto selecionado realmente contribui com ao menos uma
     * distribuição aprovada do mesmo destinatário.
     *
     * @param  Collection<int, SalesProject>  $projects
     * @param  Collection<int, ProductionDelivery>  $distributions
     */
    public function assertDistributionCoverage(
        Collection $projects,
        Collection $distributions,
        int $tenantId,
        ?int $customerId,
        ?int $organizationId,
    ): void {
        if (($customerId === null) === ($organizationId === null)) {
            throw new \RuntimeException('Escolha exatamente um cliente ou uma organização compradora.');
        }

        $projectIds = $projects->pluck('id')->map(fn ($id): int => (int) $id)->sort()->values();
        $distributionProjectIds = $distributions->pluck('sales_project_id')
            ->map(fn ($id): int => (int) $id)->unique()->sort()->values();

        if ($projectIds->all() !== $distributionProjectIds->all()) {
            throw new \RuntimeException('Cada projeto selecionado deve possuir ao menos uma distribuição incluída na cobrança.');
        }

        $customers = Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $distributions->pluck('customer_id')->filter()->unique())
            ->get(['id', 'organization_id'])
            ->keyBy('id');

        $invalid = $distributions->first(function (ProductionDelivery $distribution) use (
            $projectIds,
            $customers,
            $customerId,
            $organizationId,
            $tenantId,
        ): bool {
            $status = $distribution->status instanceof DeliveryStatus
                ? $distribution->status->value
                : (string) $distribution->status;
            $customer = $customers->get((int) $distribution->customer_id);

            return (int) $distribution->tenant_id !== $tenantId
                || ! $projectIds->contains((int) $distribution->sales_project_id)
                || $status !== DeliveryStatus::APPROVED->value
                || ! $customer
                || ($customerId !== null
                    ? (int) $customer->id !== $customerId
                    : (int) $customer->organization_id !== $organizationId);
        });

        if ($invalid) {
            throw new \RuntimeException("A distribuição #{$invalid->id} não pertence ao conjunto de projetos e destinatário desta cobrança.");
        }
    }

    public function summary(CustomerBillingReceipt $receipt): string
    {
        $projects = $receipt->includedProjects();

        if ($projects->isEmpty()) {
            return 'Projeto';
        }

        return $projects->count() === 1
            ? (string) $projects->first()->title
            : $projects->first()->title.' + '.($projects->count() - 1).' projeto(s)';
    }
}
