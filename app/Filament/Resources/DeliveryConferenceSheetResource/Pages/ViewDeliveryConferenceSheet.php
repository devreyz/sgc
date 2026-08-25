<?php

namespace App\Filament\Resources\DeliveryConferenceSheetResource\Pages;

use App\Filament\Resources\DeliveryConferenceSheetResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewDeliveryConferenceSheet extends ViewRecord
{
    protected static string $resource = DeliveryConferenceSheetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('portal')->label('Abrir no portal')->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn () => route('delivery.conference-sheets.show', [session('tenant_slug'), $this->record]))->openUrlInNewTab(),
            Actions\Action::make('pdf')->label('PDF oficial')->icon('heroicon-o-document-arrow-down')
                ->visible(fn () => filled($this->record->snapshot))->url(fn () => route('delivery.conference-sheets.pdf', [session('tenant_slug'), $this->record]))->openUrlInNewTab(),
        ];
    }
}
