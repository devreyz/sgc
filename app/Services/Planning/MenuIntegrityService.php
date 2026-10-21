<?php

namespace App\Services\Planning;

use App\Models\PlanningReportSnapshot;
use App\Models\ProjectMenu;
use App\Models\SalesProject;

final class MenuIntegrityService
{
    public function issues(SalesProject $project): array
    {
        app(ProjectPlanningService::class)->assertContext($project);
        $menus = ProjectMenu::where('tenant_id', $project->tenant_id)->where('sales_project_id', $project->id)->where('status', '!=', 'finalized')->get();
        $snapshots = PlanningReportSnapshot::where('tenant_id', $project->tenant_id)->where('sales_project_id', $project->id)
            ->whereIn('id', $menus->pluck('calculation_snapshot_id')->filter())->get()->keyBy('id');
        $issues = [];
        foreach ($menus as $menu) {
            $add = function (string $message, string $severity = 'warning') use (&$issues, $menu) {
                $issues[] = ['domain' => 'menu', 'severity' => $severity, 'title' => $menu->name.' v'.$menu->version,
                    'message' => $message, 'action' => 'Revise o cardápio e o cálculo preservado.', 'actionKey' => 'open_menu', 'menuId' => $menu->id];
            };
            if ($menu->status === 'planned') { $add('Cardápio ainda não calculado.'); continue; }
            $snapshot = $snapshots->get($menu->calculation_snapshot_id);
            if (! $snapshot || (int) ($snapshot->result['menu']['id'] ?? 0) !== (int) $menu->id) {
                $add('Cálculo preservado ausente ou incompatível.', 'critical'); continue;
            }
            if ($menu->status === 'awaiting_validation') { $add('Cardápio aguardando validação do responsável.'); }
            foreach ($snapshot->result['issues'] as $issue) { $add($issue['message'], $issue['severity']); }
            foreach ($snapshot->result['evaluations'] as $evaluation) {
                if ($evaluation['status'] !== 'compliant') {
                    $add('Meta '.$evaluation['source']['name'].' em '.$evaluation['date'].': '.$evaluation['status'], $evaluation['status'] === 'critical' ? 'critical' : 'warning');
                }
            }
        }
        return $issues;
    }
}
