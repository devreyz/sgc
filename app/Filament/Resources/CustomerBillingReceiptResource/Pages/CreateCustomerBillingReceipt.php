<?php

namespace App\Filament\Resources\CustomerBillingReceiptResource\Pages;

use App\Enums\CustomerReceiptStatus;
use App\Filament\Resources\CustomerBillingReceiptResource;
use App\Models\CustomerBillingReceipt;
use App\Services\CustomerBillingProjectContextService;
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
