<?php

namespace App\Filament\Resources\ServiceVersionResource\Pages;

use App\Filament\Resources\ServiceVersionResource;
use App\Services\Services\ServiceCatalogService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditServiceVersion extends EditRecord
{
    protected static string $resource = ServiceVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('publish')->label('Publicar versão')->color('success')->requiresConfirmation()->action(function (): void {
                $this->save();
                app(ServiceCatalogService::class)->publish($this->record, auth()->user());
                Notification::make()->success()->title('Versão publicada')->send();
                $this->redirect(ServiceVersionResource::getUrl('index'));
            }),
        ];
    }
}
