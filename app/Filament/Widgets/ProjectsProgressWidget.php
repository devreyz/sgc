<?php

namespace App\Filament\Widgets;

use App\Enums\ProjectStatus;
use App\Models\SalesProject;
use App\Models\ProjectDemand;
use Filament\Widgets\ChartWidget;

class ProjectsProgressWidget extends ChartWidget
{
    protected static ?string $heading = 'Progresso dos Projetos PNAE/PAA';

    protected static ?int $sort = 3;
    
    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $projects = SalesProject::where('status', ProjectStatus::ACTIVE)
            ->with(['demands', 'deliveries'])
            ->take(5)
            ->get();

        $labels = [];
        $deliveredData = [];
        $pendingData = [];

        foreach ($projects as $project) {
            $labels[] = \Illuminate\Support\Str::limit($project->title, 20);
            
            $totalDemand = $project->demands->sum('total_value');
            $totalDelivered = $project->deliveries->sum('gross_value');
            
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
