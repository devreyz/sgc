<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Services\CustomerHierarchyService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->disabled(fn (): bool => app(CustomerHierarchyService::class)->deletionBlockReason($this->record) !== null)
                ->tooltip(fn (): ?string => app(CustomerHierarchyService::class)->deletionBlockReason($this->record)),
            Actions\RestoreAction::make(),
        ];
    }
}
