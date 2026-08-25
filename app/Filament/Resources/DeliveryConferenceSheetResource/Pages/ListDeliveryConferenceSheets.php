<?php

namespace App\Filament\Resources\DeliveryConferenceSheetResource\Pages;

use App\Filament\Resources\DeliveryConferenceSheetResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDeliveryConferenceSheets extends ListRecords
{
    protected static string $resource = DeliveryConferenceSheetResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\Action::make('portal')->label('Criar no portal de entregas')->icon('heroicon-o-plus')->url(route('delivery.conference-sheets.index', session('tenant_slug')))->openUrlInNewTab()];
    }
}
