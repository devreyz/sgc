<?php

namespace App\Filament\Resources\AssociateResource\Pages;

use App\Filament\Resources\AssociateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAssociate extends EditRecord
{
    protected static string $resource = AssociateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('manageAccess')
                ->label('Segurança e acesso')
                ->icon('heroicon-o-key')
                ->url(fn (): string => route('security.associates.access.index', [
                    'tenant' => \App\Models\Tenant::query()->whereKey($this->record->tenant_id)->value('slug'),
                    'associate' => $this->record->id,
                ])),
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}
