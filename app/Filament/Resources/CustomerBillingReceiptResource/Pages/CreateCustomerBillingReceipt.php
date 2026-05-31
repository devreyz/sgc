<?php

namespace App\Filament\Resources\CustomerBillingReceiptResource\Pages;

use App\Filament\Resources\CustomerBillingReceiptResource;
use App\Models\CustomerBillingReceipt;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateCustomerBillingReceipt extends CreateRecord
{
    protected static string $resource = CustomerBillingReceiptResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenantId = session('tenant_id');
        $year     = (int) date('Y');

        $data['tenant_id']    = $tenantId;
        $data['created_by']   = Auth::id();
        $data['receipt_year'] = $year;
        $data['receipt_number'] = CustomerBillingReceipt::nextNumber($tenantId, $year);

        // status inicial = draft
        $data['status'] = \App\Enums\CustomerReceiptStatus::DRAFT->value;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
