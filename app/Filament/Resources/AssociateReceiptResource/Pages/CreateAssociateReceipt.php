<?php

namespace App\Filament\Resources\AssociateReceiptResource\Pages;

use App\Filament\Resources\AssociateReceiptResource;
use App\Models\AssociateReceipt;
use App\Models\SalesProject;
use App\Services\AssociateReceiptService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateAssociateReceipt extends CreateRecord
{
    protected static string $resource = AssociateReceiptResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenantId = session('tenant_id');
        $data['tenant_id'] = $tenantId;

        $project = SalesProject::query()
            ->where('tenant_id', $tenantId)
            ->find((int) ($data['sales_project_id'] ?? 0));

        if (! $project) {
            throw ValidationException::withMessages([
                'sales_project_id' => 'Selecione um projeto de venda válido desta organização.',
            ]);
        }

        $data = array_merge($data, AssociateReceipt::numberingFor($project));

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return DB::transaction(function () use ($data) {
                $record = parent::handleRecordCreation($data);
                $project = SalesProject::query()
                    ->where('tenant_id', $record->tenant_id)
                    ->findOrFail($record->sales_project_id);

                app(AssociateReceiptService::class)->replaceDistributions(
                    $record,
                    $data['delivery_ids'] ?? [],
                    $project
                );

                return $record->refresh();
            });
        } catch (\RuntimeException $exception) {
            throw ValidationException::withMessages([
                'delivery_ids' => $exception->getMessage(),
            ]);
        }
    }
}
