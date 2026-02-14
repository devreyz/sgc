<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewProduct extends ViewRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Voltar')
                ->url($this->getResource()::getUrl('index'))
                ->icon('heroicon-o-arrow-left')
                ->color('secondary'),
            Action::make("new")
                ->label('Novo')
                ->url($this->getResource()::getUrl('create'))
                ->icon('heroicon-o-plus')
                ->color('success'),
            Action::make("edit")
                ->label('Editar')
                ->url($this->getResource()::getUrl('edit', ['record' => $this->record]))
                ->icon('heroicon-o-pencil')
                ->color("warning"),
            Actions\DeleteAction::make(),
        ];
    }
}
