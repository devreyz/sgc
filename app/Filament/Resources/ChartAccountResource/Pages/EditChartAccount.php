<?php

namespace App\Filament\Resources\ChartAccountResource\Pages;

use App\Filament\Resources\ChartAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditChartAccount extends EditRecord
{
    protected static string $resource = ChartAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}
