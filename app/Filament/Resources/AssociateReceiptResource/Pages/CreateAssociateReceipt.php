<?php

namespace App\Filament\Resources\AssociateReceiptResource\Pages;

use App\Filament\Resources\AssociateReceiptResource;
use App\Models\AssociateReceipt;
use Filament\Resources\Pages\CreateRecord;

class CreateAssociateReceipt extends CreateRecord
{
    protected static string $resource = AssociateReceiptResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenantId = session('tenant_id');
        $data['tenant_id'] = $tenantId;

        // Auto-gerar número se não informado
        if (empty($data['receipt_number'])) {
            $year = (int) ($data['receipt_year'] ?? now()->year);
            $data['receipt_number'] = AssociateReceipt::nextNumber($tenantId, $year);
        }

        return $data;
    }
}
