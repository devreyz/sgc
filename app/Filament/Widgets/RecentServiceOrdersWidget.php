<?php

namespace App\Filament\Widgets;

use App\Enums\ServiceOrderStatus;
use App\Models\ServiceOrder;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentServiceOrdersWidget extends BaseWidget
{
    protected static ?string $heading = 'Ordens de Serviço Agendadas';

    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ServiceOrder::query()
                    ->where('status', ServiceOrderStatus::SCHEDULED)
                    ->orderBy('scheduled_date')
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label('Número'),
                Tables\Columns\TextColumn::make('scheduled_date')
                    ->label('Agendamento')
                    ->date('d/m/Y'),
                Tables\Columns\TextColumn::make('associate.user.name')
                    ->label('Associado')
                    ->limit(25),
                Tables\Columns\TextColumn::make('service.name')
                    ->label('Serviço'),
                Tables\Columns\TextColumn::make('asset.name')
                    ->label('Equipamento'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (ServiceOrderStatus $state): string => $state->label())
                    ->color(fn (ServiceOrderStatus $state): string => $state->color()),
            ])
            ->paginated(false);
    }
}
