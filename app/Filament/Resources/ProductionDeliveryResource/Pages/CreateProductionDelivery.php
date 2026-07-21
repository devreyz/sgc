<?php

namespace App\Filament\Resources\ProductionDeliveryResource\Pages;

use App\Filament\Resources\ProductionDeliveryResource;
use App\Models\SalesProject;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateProductionDelivery extends CreateRecord
{
    protected static string $resource = ProductionDeliveryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenantId = (int) session('tenant_id');
        $projectId = (int) ($data['sales_project_id'] ?? 0);

        if (! SalesProject::query()->where('tenant_id', $tenantId)->whereKey($projectId)->exists()) {
            throw ValidationException::withMessages([
                'sales_project_id' => 'Selecione um projeto de venda válido desta organização.',
            ]);
        }

        $data['tenant_id'] = $tenantId;
        $data['sales_project_id'] = $projectId;
        unset($data['is_standalone']);

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Entrega registrada com sucesso!';
    }
}
