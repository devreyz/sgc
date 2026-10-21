<?php

namespace App\Services\Planning;

use App\Models\DistributionConsumptionProfile;
use App\Models\ProjectPlanningSetting;
use App\Models\SalesProject;
use Illuminate\Support\Facades\Schema;

final class PlanningIntegrityService
{
    public function append(SalesProject $project, array $integrity): array
    {
        app(ProjectPlanningService::class)->assertContext($project);
        if (! Schema::hasTable('distribution_consumption_profiles')) {
            return $integrity;
        }
        $context = ['tenant_id' => $project->tenant_id, 'sales_project_id' => $project->id];
        $menusEnabled = Schema::hasTable('project_menus') && \App\Models\ProjectMenu::where($context)->exists();
        if (! ProjectPlanningSetting::where($context)->exists() && ! DistributionConsumptionProfile::where($context)->exists() && ! $menusEnabled) {
            return $integrity;
        }
        $filters = [
            'date_from' => $project->start_date?->format('Y-m-d') ?? '1900-01-01',
            'date_to' => $project->end_date?->format('Y-m-d') ?? '9999-12-31',
            'competency' => 'consumption', 'analysis_mode' => 'compliance',
        ];
        $nutritionEnabled = Schema::hasTable('food_profiles') && (
            \App\Models\FoodProfile::where('tenant_id', $project->tenant_id)->whereIn('product_id',
                \App\Models\ProductionDelivery::where($context)->select('product_id'))->exists()
            || \App\Models\NutritionTargetSet::where($context)->exists()
        );
        $report = $nutritionEnabled ? app(NutritionReportService::class)->build($project, $filters)
            : app(ConsumptionReportService::class)->build($project, $filters);
        $issues = app(ConsumptionReportService::class)->integrityIssues($report);
        if ($nutritionEnabled) {
            $issues = array_merge($issues, app(NutritionReportService::class)->integrityIssues($report));
        }
        if ($menusEnabled) { $issues = array_merge($issues, app(MenuIntegrityService::class)->issues($project)); }
        foreach ($issues as $issue) {
            $integrity[$issue['severity']][] = $issue;
        }
        foreach (['critical', 'warning', 'info'] as $severity) {
            $integrity['counts'][$severity] = count($integrity[$severity]);
        }
        return $integrity;
    }
}
