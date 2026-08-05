<?php

namespace App\Filament\Resources\CustomerBillingReceiptResource\Pages;

use App\Enums\CustomerReceiptStatus;
use App\Filament\Resources\CustomerBillingReceiptResource;
use App\Models\CustomerBillingReceipt;
use App\Models\SalesProject;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateCustomerBillingReceipt extends CreateRecord
{
    protected static string $resource = CustomerBillingReceiptResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenantId = session('tenant_id');
        $project = SalesProject::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail((int) ($data['sales_project_id'] ?? 0));

        $data['tenant_id'] = $tenantId;
        $data['created_by'] = Auth::id();
        $data = array_merge($data, CustomerBillingReceipt::numberingFor($project, $data['issued_at'] ?? null));

        // status inicial = draft
        $data['status'] = CustomerReceiptStatus::DRAFT->value;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
