<?php

namespace App\Filament\Pages;

use App\Models\Asset;
use App\Models\Service;
use App\Models\ServiceProvider;
use App\Services\Services\ServiceReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

class ServiceOrdersPaymentReport extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static string $view = 'filament.pages.service-orders-payment-report';

    protected static ?string $navigationGroup = 'Serviços';

    protected static ?string $navigationLabel = 'Prestação de contas';

    protected static ?string $title = 'Prestação de contas de serviços';

    public ?array $data = [];

    public static function canAccess(array $parameters = []): bool
    {
        return (bool) session('tenant_id') && (auth()->user()?->checkPermissionTo('view_service_reports') ?? false);
    }

    public function mount(): void
    {
        $this->form->fill(['from' => now()->startOfMonth()->toDateString(), 'to' => now()->endOfMonth()->toDateString()]);
    }

    public function form(Form $form): Form
    {
        return $form->statePath('data')->schema([
            Forms\Components\DatePicker::make('from')->label('De')->required(),
            Forms\Components\DatePicker::make('to')->label('Até')->required()->afterOrEqual('from'),
            Forms\Components\Select::make('provider_id')->label('Prestador')->options(fn () => ServiceProvider::query()->pluck('name', 'id'))->searchable(),
            Forms\Components\Select::make('service_id')->label('Serviço')->options(fn () => Service::query()->pluck('name', 'id'))->searchable(),
            Forms\Components\Select::make('asset_id')->label('Equipamento')->options(fn () => Asset::query()->pluck('name', 'id'))->searchable(),
        ])->columns(['default' => 1, 'md' => 3]);
    }

    public function report(): array
    {
        abort_unless(static::canAccess(), 403);
        $data = $this->form->getState();

        return app(ServiceReportService::class)->summary((int) session('tenant_id'), Carbon::parse($data['from']), Carbon::parse($data['to']), $data['provider_id'] ?? null, $data['service_id'] ?? null, $data['asset_id'] ?? null);
    }

    public function generate(): void
    {
        $this->report();
    }

    public function download()
    {
        $summary = $this->report();
        $pdf = Pdf::loadView('pdf.service-accountability', compact('summary'))->setPaper('a4', 'landscape');

        return response()->streamDownload(fn () => print ($pdf->output()), 'prestacao-servicos.pdf', ['Content-Type' => 'application/pdf']);
    }
}
