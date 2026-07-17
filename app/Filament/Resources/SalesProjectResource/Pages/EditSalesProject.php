<?php

namespace App\Filament\Resources\SalesProjectResource\Pages;

use App\Filament\Resources\SalesProjectResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditSalesProject extends EditRecord
{
    protected static string $resource = SalesProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
            Actions\RestoreAction::make(),
            Actions\Action::make('convert_to_financial_limits')
                ->label('Converter limites para financeiro')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription('Os limites por produto serao arquivados. O historico sera preservado e o projeto passara a usar somente limites financeiros.')
                ->visible(fn (): bool => $this->record->associateProductLimits()->where('status', 'active')->exists())
                ->action(function (): void {
                    $count = $this->record->associateProductLimits()->where('status', 'active')->update([
                        'status' => 'archived',
                        'archived_at' => now(),
                        'archived_by' => auth()->id(),
                        'archive_reason' => 'Conversao confirmada para projeto com multiplos clientes.',
                    ]);

                    activity('associate_project_limits')->performedOn($this->record)->withProperties([
                        'tenant_id' => $this->record->tenant_id,
                        'archived_limits' => $count,
                    ])->log('Limites por produto arquivados para conversao de modo');
                    Notification::make()->success()->title('Limites por produto arquivados')->send();
                }),
        ];
    }

    protected function beforeSave(): void
    {
        $state = $this->form->getRawState();
        $customerIds = collect($state['customers'] ?? [])->push($state['customer_id'] ?? null)->filter()->unique();
        $activeProductLimits = $this->record->associateProductLimits()->where('status', 'active')->exists();
        $currentCustomerIds = app(\App\Services\AssociateProjectLimitService::class)
            ->projectMode($this->record)['customers']->pluck('id')->map(fn ($id) => (int) $id)->sort()->values();
        $newCustomerIds = $customerIds->map(fn ($id) => (int) $id)->sort()->values();

        if ($customerIds->count() > 1 && $activeProductLimits) {
            throw ValidationException::withMessages([
                'customers' => 'Este projeto possui limites por produto. Confirme primeiro a conversao para controle somente financeiro.',
            ]);
        }

        if ($activeProductLimits && $currentCustomerIds->all() !== $newCustomerIds->all()) {
            throw ValidationException::withMessages([
                'customer_id' => 'A troca de cliente altera os precos de referencia. Arquive os limites por produto, salve o cliente e recrie os limites apos revisar os novos precos.',
            ]);
        }

        $generalLimit = isset($state['max_total_value_per_associate']) && $state['max_total_value_per_associate'] !== ''
            ? (float) $state['max_total_value_per_associate']
            : null;

        if ($generalLimit !== null) {
            $largestConsumed = (float) \App\Models\ProductionDelivery::query()
                ->where('tenant_id', $this->record->tenant_id)
                ->where('sales_project_id', $this->record->id)
                ->whereNotNull('parent_delivery_id')
                ->where('status', \App\Enums\DeliveryStatus::APPROVED->value)
                ->groupBy('associate_id')
                ->selectRaw('SUM(gross_value) as consumed')
                ->orderByDesc('consumed')
                ->value('consumed');

            if ($generalLimit + 0.005 < $largestConsumed) {
                throw ValidationException::withMessages([
                    'max_total_value_per_associate' => 'O limite geral nao pode ser inferior ao valor ja distribuido por um associado.',
                ]);
            }
        }
    }
}
