<?php

namespace App\Filament\Resources\FinancialReceiptResource\Pages;

use App\Filament\Resources\FinancialReceiptResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFinancialReceipts extends ListRecords
{
    protected static string $resource = FinancialReceiptResource::class;
    protected function getHeaderActions(): array { return [Actions\CreateAction::make()->label('Novo recebimento')]; }
}
