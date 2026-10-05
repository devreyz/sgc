<?php

namespace App\Filament\Resources\CustomerBillingReceiptResource\Pages;

use App\Enums\CustomerReceiptStatus;
use App\Filament\Resources\CustomerBillingReceiptResource;
use App\Models\Customer;
use App\Models\CustomerBillingReceipt;
use App\Models\Organization;
use App\Services\CustomerBillingProjectContextService;
use App\Services\CustomerBillingReceiptService;
use App\Services\CustomerBillingSelectionService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CreateCustomerBillingReceipt extends CreateRecord
{
    protected static string $resource = CustomerBillingReceiptResource::class;

    /** @var list<int> */
    protected array $selectedProjectIds = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenantId = session('tenant_id');
        try {
            $projects = app(CustomerBillingProjectContextService::class)
                ->projects((int) $tenantId, (array) ($data['project_ids'] ?? []));
        } catch (\RuntimeException $exception) {
            throw ValidationException::withMessages(['project_ids' => $exception->getMessage()]);
        }
        $this->selectedProjectIds = $projects->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $project = $projects->first();
        unset($data['project_ids']);

        $customerId = filled($data['customer_id'] ?? null) ? (int) $data['customer_id'] : null;
        $organizationId = filled($data['organization_id'] ?? null) ? (int) $data['organization_id'] : null;
        if (($customerId === null) === ($organizationId === null)) {
            throw ValidationException::withMessages([
                'customer_id' => 'Escolha exatamente um destinatário: cliente ou organização.',
            ]);
        }
        $recipientExists = $customerId
            ? Customer::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereKey($customerId)->exists()
            : Organization::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereKey($organizationId)->exists();
        if (! $recipientExists) {
            throw ValidationException::withMessages([
                $customerId ? 'customer_id' : 'organization_id' => 'O destinatário não pertence à organização atual.',
            ]);
        }

        $selection = app(CustomerBillingSelectionService::class)->selectDistributionIds(
            (int) $tenantId,
            (array) ($data['delivery_ids'] ?? []),
            $this->selectedProjectIds,
            $customerId,
            $organizationId,
            isset($data['from_date']) ? (string) $data['from_date'] : null,
            isset($data['to_date']) ? (string) $data['to_date'] : null,
        );
        if ($selection['excluded_count'] > 0) {
            throw ValidationException::withMessages([
                'delivery_ids' => 'Uma ou mais distribuições já foram incluídas em outro faturamento ou não pertencem a esta seleção. Recarregue a lista e escolha somente os itens disponíveis.',
            ]);
        }
        $data['delivery_ids'] = $selection['selected_ids'];
        try {
            $snapshot = app(CustomerBillingReceiptService::class)->computeDraftSnapshotForIds(
                (int) $tenantId,
                $data['delivery_ids'],
                $projects,
            );
        } catch (\RuntimeException $exception) {
            throw ValidationException::withMessages(['delivery_ids' => $exception->getMessage()]);
        }
        $data['total_gross'] = $snapshot['total_gross'];
        $data['total_fees'] = $snapshot['total_fees'];
        $data['total_net'] = $snapshot['total_net'];
        $data['fee_snapshot'] = array_merge($snapshot['fee_snapshot'], ['draft_preview' => true]);

        $data['tenant_id'] = $tenantId;
        $data['sales_project_id'] = $project->id;
        $data['created_by'] = Auth::id();
        $data = array_merge($data, CustomerBillingReceipt::numberingFor($project, $data['issued_at'] ?? null));

        // status inicial = draft
        $data['status'] = CustomerReceiptStatus::DRAFT->value;

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->projects()->sync(collect($this->selectedProjectIds)
            ->mapWithKeys(fn (int $projectId): array => [
                $projectId => ['tenant_id' => (int) $this->record->tenant_id],
            ])->all());
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
