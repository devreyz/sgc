<?php

namespace App\Filament\Resources\AssociateReceiptResource\Pages;

use App\Filament\Resources\AssociateReceiptResource;
use App\Models\AssociateReceipt;
use App\Models\SalesProject;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateAssociateReceipt extends CreateRecord
{
    protected static string $resource = AssociateReceiptResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenantId = session('tenant_id');
        $data['tenant_id'] = $tenantId;

        if (! SalesProject::query()
            ->where('tenant_id', $tenantId)
            ->whereKey((int) ($data['sales_project_id'] ?? 0))
            ->exists()) {
            throw ValidationException::withMessages([
                'sales_project_id' => 'Selecione um projeto de venda válido desta organização.',
            ]);
        }

        // Auto-gerar número se não informado
        if (empty($data['receipt_number'])) {
            $year = (int) ($data['receipt_year'] ?? now()->year);
            $data['receipt_number'] = AssociateReceipt::nextNumber($tenantId, $year);
        }

        return $data;
    }
}
