<?php

namespace App\Filament\Resources\CustomerBillingReceiptResource\Pages;

use App\Filament\Resources\CustomerBillingReceiptResource;
use App\Models\SalesProject;
use App\Services\CustomerBillingReceiptService;
use App\Services\ProjectReceiptNumberingService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditCustomerBillingReceipt extends EditRecord
{
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
