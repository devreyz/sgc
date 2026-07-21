<?php

namespace App\Filament\Widgets;

use App\Enums\ProjectStatus;
use App\Models\SalesProject;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Str;

class ProjectsProgressWidget extends ChartWidget
{
    protected static ?string $heading = 'Progresso dos Projetos PNAE/PAA';

    protected static ?int $sort = 3;

    protected static ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $tenantId = session('tenant_id');

        $projects = SalesProject::where('tenant_id', $tenantId)
            ->where('status', ProjectStatus::ACTIVE)
            ->select(['id', 'title'])
            ->withSum('demands as demand_total', 'total_value')
            ->withSum('deliveries as delivered_total', 'gross_value')
            ->take(5)
            ->get();

        $labels = [];
        $deliveredData = [];
        $pendingData = [];

        foreach ($projects as $project) {
            $labels[] = Str::limit($project->title, 20);

            $totalDemand = (float) ($project->demand_total ?? 0);
            $totalDelivered = (float) ($project->delivered_total ?? 0);

            $deliveredData[] = round($totalDelivered, 2);
            $pendingData[] = round(max(0, $totalDemand - $totalDelivered), 2);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Entregue',
                    'data' => $deliveredData,
                    'backgroundColor' => 'rgba(34, 197, 94, 0.7)',
                ],
                [
                    'label' => 'Pendente',
                    'data' => $pendingData,
                    'backgroundColor' => 'rgba(234, 179, 8, 0.7)',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                ],
            ],
            'scales' => [
                'x' => [
                    'stacked' => true,
                ],
                'y' => [
                    'stacked' => true,
                ],
            ],
        ];
    }
}
