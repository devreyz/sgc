<?php

namespace App\Filament\Resources\AssociateReceiptResource\Pages;

use App\Filament\Resources\AssociateReceiptResource;
use App\Models\ProductionDelivery;
use App\Models\SalesProject;
use App\Services\AssociateReceiptService;
use Filament\Notifications\Notification;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EditAssociateReceipt extends EditRecord
{
    protected static string $resource = AssociateReceiptResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if (! AssociateReceiptResource::canEdit($this->record)) {
            Notification::make()
                ->danger()
                ->title('Comprovante bloqueado')
                ->body('Comprovantes faturados, pagos ou parcialmente pagos nao podem ser editados.')
                ->send();

            $this->redirect(AssociateReceiptResource::getUrl('index'));
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn () => AssociateReceiptResource::canDelete($this->record))
                ->using(function (): void {
                    if (! AssociateReceiptResource::canDelete($this->record)) {
                        Notification::make()
                            ->danger()
                            ->title('Comprovante bloqueado')
                            ->body('Comprovantes faturados, pagos ou parcialmente pagos nao podem ser excluidos.')
                            ->send();

                        return;
                    }

                    \App\Models\ProductionDelivery::where('tenant_id', $this->record->tenant_id)
                        ->where('associate_receipt_id', $this->record->id)
                        ->update(['associate_receipt_id' => null]);

                    $this->record->delete();
                    $this->redirect(AssociateReceiptResource::getUrl('index'));
                }),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return DB::transaction(function () use ($record, $data) {
                $selectedIds = collect($data['delivery_ids'] ?? [])
                    ->map(fn ($id) => (int) $id)
                    ->filter()
                    ->unique()
                    ->sort()
                    ->values();
                $linkedIds = ProductionDelivery::query()
                    ->where('tenant_id', $record->tenant_id)
                    ->where('associate_receipt_id', $record->id)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->sort()
                    ->values();

                $updated = parent::handleRecordUpdate($record, $data);

                if ($selectedIds->all() !== $linkedIds->all()) {
                    $project = SalesProject::query()
                        ->where('tenant_id', $updated->tenant_id)
                        ->findOrFail($updated->sales_project_id);

                    app(AssociateReceiptService::class)->replaceDistributions(
                        $updated,
                        $selectedIds->all(),
                        $project,
                        true,
                        'As distribuicoes vinculadas foram alteradas no painel administrativo. Regenere o comprovante.'
                    );
                }

                return $updated->refresh();
            });
        } catch (\RuntimeException $exception) {
            throw ValidationException::withMessages([
                'delivery_ids' => $exception->getMessage(),
            ]);
        }
    }
}
