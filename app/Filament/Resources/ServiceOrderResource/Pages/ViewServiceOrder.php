<?php

namespace App\Filament\Resources\ServiceOrderResource\Pages;

use App\Filament\Resources\ServiceOrderResource;
use App\Models\ServicePaymentEvent;
use App\Services\Services\ServicePaymentService;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Str;

class ViewServiceOrder extends ViewRecord
{
    protected static string $resource = ServiceOrderResource::class;

    protected static string $view = 'filament.pages.service-order-dossier';

    protected function getHeaderActions(): array
    {
        return [Action::make('reverse')->label('Estornar pagamento')->color('danger')
            ->visible(fn () => auth()->user()->checkPermissionTo('reverse_service_payment'))
            ->form([
                Hidden::make('operation_key')->default(fn () => (string) Str::uuid()),
                Select::make('payment_id')->label('Pagamento')->options(fn () => ServicePaymentEvent::query()->where('status', 'confirmed')->where('event_type', 'payment')->whereHas('allocations.obligation.execution', fn ($q) => $q->where('service_order_id', $this->record->id))->get()->mapWithKeys(fn ($event) => [$event->id => $event->payment_date->format('d/m/Y').' — R$ '.$event->amount]))->required(),
                Textarea::make('reason')->label('Motivo')->minLength(5)->required(),
            ])->action(function (array $data): void {
                abort_unless(auth()->user()->checkPermissionTo('reverse_service_payment'), 403);
                $event = ServicePaymentEvent::query()->where('tenant_id', session('tenant_id'))->whereKey($data['payment_id'])->whereHas('allocations.obligation.execution', fn ($q) => $q->where('service_order_id', $this->record->id))->firstOrFail();
                app(ServicePaymentService::class)->reverse($event, $data['reason'], $data['operation_key'], auth()->user());
                Notification::make()->title('Pagamento estornado')->success()->send();
            })];
    }
}
