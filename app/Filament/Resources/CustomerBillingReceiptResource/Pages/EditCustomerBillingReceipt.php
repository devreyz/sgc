<?php

namespace App\Filament\Resources\CustomerBillingReceiptResource\Pages;

use App\Filament\Resources\CustomerBillingReceiptResource;
use App\Filament\Resources\CustomerBillingReceiptResource\Pages\Concerns\HasCustomerBillingIntegrityAction;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\SalesProject;
use App\Services\CustomerBillingProjectContextService;
use App\Services\CustomerBillingReceiptService;
use App\Services\CustomerBillingSelectionService;
use App\Services\ProjectReceiptNumberingService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditCustomerBillingReceipt extends EditRecord
{
    use HasCustomerBillingIntegrityAction;

    protected static string $resource = CustomerBillingReceiptResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        // Redireciona se o comprovante não é mais editável (foi emitido ou pago)
        if ($this->record->isLocked()) {
            Notification::make()->warning()
                ->title('Comprovante bloqueado')
                ->body('Este comprovante já foi emitido ou pago e não pode ser editado.')
                ->send();

            $this->redirect($this->getResource()::getUrl('index'));
        }
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['project_ids'] = $this->record->projectIds();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Nunca permite alterar campos de controle
        unset($data['tenant_id'], $data['status'], $data['project_ids']);
        $customerId = filled($data['customer_id'] ?? null) ? (int) $data['customer_id'] : null;
        $organizationId = filled($data['organization_id'] ?? null) ? (int) $data['organization_id'] : null;
        if (($customerId === null) === ($organizationId === null)) {
            throw ValidationException::withMessages([
                'customer_id' => 'Escolha exatamente um destinatário: cliente ou organização.',
            ]);
        }
        $recipientExists = $customerId
            ? Customer::withoutGlobalScopes()->where('tenant_id', $this->record->tenant_id)->whereKey($customerId)->exists()
            : Organization::withoutGlobalScopes()->where('tenant_id', $this->record->tenant_id)->whereKey($organizationId)->exists();
        if (! $recipientExists) {
            throw ValidationException::withMessages([
                $customerId ? 'customer_id' : 'organization_id' => 'O destinatário não pertence à organização atual.',
            ]);
        }
        $selection = app(CustomerBillingSelectionService::class)->selectDistributionIds(
            (int) $this->record->tenant_id,
            (array) ($data['delivery_ids'] ?? []),
            $this->record->projectIds(),
            $customerId,
            $organizationId,
            isset($data['from_date']) ? (string) $data['from_date'] : null,
            isset($data['to_date']) ? (string) $data['to_date'] : null,
            (int) $this->record->id,
        );
        if ($selection['excluded_count'] > 0) {
            throw ValidationException::withMessages([
                'delivery_ids' => 'Uma ou mais distribuições já foram incluídas em outro faturamento ou não pertencem a esta seleção. Recarregue a lista e escolha somente os itens disponíveis.',
            ]);
        }
        $data['delivery_ids'] = $selection['selected_ids'];
        try {
            $projects = app(CustomerBillingProjectContextService::class)->projectsForReceipt($this->record);
            $snapshot = app(CustomerBillingReceiptService::class)->computeDraftSnapshotForIds(
                (int) $this->record->tenant_id,
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
        $tenantDuplicate = $this->record->newQuery()
            ->where('tenant_id', $this->record->tenant_id)
            ->where('tenant_receipt_year', $data['tenant_receipt_year'])
            ->where('tenant_receipt_number', $data['tenant_receipt_number'])
            ->whereKeyNot($this->record->getKey())
            ->exists();
        $projectDuplicate = $this->record->newQuery()
            ->where('tenant_id', $this->record->tenant_id)
            ->where('sales_project_id', $this->record->sales_project_id)
            ->where('project_receipt_year', $data['project_receipt_year'])
            ->where('project_receipt_number', $data['project_receipt_number'])
            ->whereKeyNot($this->record->getKey())
            ->exists();

        if ($tenantDuplicate || $projectDuplicate) {
            throw ValidationException::withMessages([
                $tenantDuplicate ? 'tenant_receipt_number' : 'project_receipt_number' => $tenantDuplicate
                        ? 'Este numero geral ja esta em uso neste ano.'
                        : 'Este numero ja esta em uso neste projeto e ano.',
            ]);
        }

        $project = SalesProject::query()
            ->where('tenant_id', $this->record->tenant_id)
            ->findOrFail($this->record->sales_project_id);
        $service = app(ProjectReceiptNumberingService::class);
        $usesProject = $service->usesProjectSequence($project);
        $data['receipt_number'] = (int) $data[$usesProject ? 'project_receipt_number' : 'tenant_receipt_number'];
        $data['receipt_year'] = (int) $data[$usesProject ? 'project_receipt_year' : 'tenant_receipt_year'];
        $data['receipt_label'] = $usesProject
            ? $service->format($project, $data['receipt_number'], $data['receipt_year'], 'COM-')
            : $service->format($project, $data['receipt_number'], $data['receipt_year'], 'COM-');

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->integrityAction(),
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->visible(fn () => $this->record->isEditable())
                ->using(function (): bool {
                    app(CustomerBillingReceiptService::class)->discardDraftReceipt($this->record);

                    return true;
                }),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
