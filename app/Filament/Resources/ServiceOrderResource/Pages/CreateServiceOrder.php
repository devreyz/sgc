<?php

namespace App\Filament\Resources\ServiceOrderResource\Pages;

use App\Filament\Resources\ServiceOrderResource;
use App\Models\ServiceVersion;
use App\Services\Services\CreateServiceOrder as CreateServiceOrderService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateServiceOrder extends CreateRecord
{
    protected static string $resource = ServiceOrderResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $version = ServiceVersion::query()->whereKey($data['service_version_id'])->where('status', 'published')->firstOrFail();

        return app(CreateServiceOrderService::class)->handle((int) session('tenant_id'), $version, [
            'associate_id' => $data['associate_id'] ?? null,
            'beneficiary_name' => $data['beneficiary_name'] ?? null,
            'service_provider_id' => $data['service_provider_id'] ?? null,
            'asset_id' => $data['asset_id'] ?? null,
            'scheduled_at' => $data['scheduled_at'] ?? now(),
            'location' => $data['location'] ?? null,
            'order_data' => array_replace($data['order_data'] ?? [], array_filter([
                'description' => $data['work_description'] ?? null,
                'notes' => $data['notes'] ?? null,
            ], fn ($value) => filled($value))),
        ], auth()->user());
    }
}
