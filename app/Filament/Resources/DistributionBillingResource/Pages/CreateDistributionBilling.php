<?php

namespace App\Filament\Resources\DistributionBillingResource\Pages;

use App\Filament\Resources\DistributionBillingResource;
use App\Models\Associate;
use App\Models\ProductionDelivery;
use App\Models\SalesProject;
use App\Services\DistributionBillingService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Log;

class CreateDistributionBilling extends Page
{
    protected static string $resource = DistributionBillingResource::class;

    protected static string $view = 'filament.resources.distribution-billing.create';

    protected static ?string $title = 'Novo Faturamento';

    // ---- form data ----
    public ?int $sales_project_id = null;
    public ?int $associate_id = null;
    public ?string $billing_date = null;
    public ?string $period_start = null;
    public ?string $period_end = null;
    public ?string $reference = null;
    public ?string $notes = null;

    // ---- selected distribution IDs ----
    public array $selectedIds = [];

    public function mount(): void
    {
        $this->billing_date = now()->toDateString();
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Forms\Components\Section::make('Filtros')->schema([
                    Forms\Components\Select::make('sales_project_id')
                        ->label('Projeto *')
                        ->options(fn () => SalesProject::where('tenant_id', session('tenant_id'))
                            ->orderBy('title')
                            ->pluck('title', 'id'))
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn () => $this->selectedIds = []),

                    Forms\Components\Select::make('associate_id')
                        ->label('Associado (opcional)')
                        ->options(fn () => Associate::where('tenant_id', session('tenant_id'))
                            ->with('user')->get()
                            ->mapWithKeys(fn ($a) => [$a->id => $a->user->name ?? "#{$a->id}"]))
                        ->nullable()
                        ->live()
                        ->afterStateUpdated(fn () => $this->selectedIds = []),

                    Forms\Components\DatePicker::make('period_start')
                        ->label('Período Início')
                        ->nullable()
                        ->live(),

                    Forms\Components\DatePicker::make('period_end')
                        ->label('Período Fim')
                        ->nullable()
                        ->live(),
                ])->columns(2),

                Forms\Components\Section::make('Dados do Lote')->schema([
                    Forms\Components\DatePicker::make('billing_date')
                        ->label('Data Faturamento')
                        ->required()
                        ->default(now()->toDateString()),

                    Forms\Components\TextInput::make('reference')
                        ->label('Referência'),

                    Forms\Components\Textarea::make('notes')
                        ->label('Observações')
                        ->columnSpanFull(),
                ])->columns(2),
            ]);
    }

    public function getUnbilledDistributions(): \Illuminate\Database\Eloquent\Collection
    {
        $data = $this->form->getState();

        if (empty($data['sales_project_id'])) {
            return collect();
        }

        $query = ProductionDelivery::where('tenant_id', session('tenant_id'))
            ->where('sales_project_id', $data['sales_project_id'])
            ->whereNotNull('parent_delivery_id')
            ->where('status', 'approved')
            ->where('billing_status', 'unbilled')
            ->with(['associate.user', 'product', 'customer']);

        if (!empty($data['associate_id'])) {
            $query->where('associate_id', $data['associate_id']);
        }

        if (!empty($data['period_start'])) {
            $query->where('delivery_date', '>=', $data['period_start']);
        }

        if (!empty($data['period_end'])) {
            $query->where('delivery_date', '<=', $data['period_end']);
        }

        return $query->orderBy('delivery_date')->get();
    }

    public function bill(): void
    {
        if (empty($this->selectedIds)) {
            Notification::make()
                ->title('Nenhuma distribuição selecionada')
                ->warning()
                ->send();
            return;
        }

        $data = $this->form->getState();

        try {
            $service = app(DistributionBillingService::class);
            $billing = $service->billDistributions($this->selectedIds, [
                'billing_date' => $data['billing_date'],
                'reference'    => $data['reference'] ?? null,
                'notes'        => $data['notes'] ?? null,
                'period_start' => $data['period_start'] ?? null,
                'period_end'   => $data['period_end'] ?? null,
            ]);

            Notification::make()
                ->title("Faturamento #" . $billing->id . " gerado com sucesso!")
                ->body(count($this->selectedIds) . " distribuições faturadas. Líquido: R$ " . number_format((float) $billing->total_net, 2, ',', '.'))
                ->success()
                ->send();

            $this->selectedIds = [];
            $this->redirect(DistributionBillingResource::getUrl('view', ['record' => $billing->id]));
        } catch (\Throwable $e) {
            Log::error('Erro ao faturar distribuições: ' . $e->getMessage());
            Notification::make()
                ->title('Erro ao faturar')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function toggleSelect(int $id): void
    {
        if (in_array($id, $this->selectedIds)) {
            $this->selectedIds = array_values(array_filter($this->selectedIds, fn ($v) => $v !== $id));
        } else {
            $this->selectedIds[] = $id;
        }
    }

    public function selectAll(): void
    {
        $this->selectedIds = $this->getUnbilledDistributions()->pluck('id')->toArray();
    }

    public function clearSelection(): void
    {
        $this->selectedIds = [];
    }
}
