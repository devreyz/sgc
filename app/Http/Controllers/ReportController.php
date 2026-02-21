<?php

namespace App\Http\Controllers;

use App\Models\Associate;
use App\Models\ProductionDelivery;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    /**
     * Relatório de entregas por associado.
     * Acessível via: /reports/associate-deliveries/{associate}
     */
    public function associateDeliveries(Request $request, Associate $associate)
    {
        $this->authorizeReport();

        $tenantId = session('tenant_id');
        $tenant   = Tenant::find($tenantId);

        // Validar que o associado pertence ao tenant atual
        if ($associate->tenant_id !== $tenantId) {
            abort(403, 'Acesso negado.');
        }

        // Filtros de período (opcionais via query string)
        $startDate = $request->query('start_date');
        $endDate   = $request->query('end_date');

        $query = ProductionDelivery::query()
            ->with(['product', 'salesProject'])
            ->where('associate_id', $associate->id)
            ->where('tenant_id', $tenantId)
            ->orderBy('delivery_date');

        if ($startDate) {
            $query->where('delivery_date', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('delivery_date', '<=', $endDate);
        }

        $deliveries = $query->get();

        // Montar período para exibição
        $period = match(true) {
            (bool) $startDate && (bool) $endDate => sprintf(
                '%s a %s',
                \Carbon\Carbon::parse($startDate)->format('d/m/Y'),
                \Carbon\Carbon::parse($endDate)->format('d/m/Y')
            ),
            (bool) $startDate => 'A partir de ' . \Carbon\Carbon::parse($startDate)->format('d/m/Y'),
            (bool) $endDate   => 'Até ' . \Carbon\Carbon::parse($endDate)->format('d/m/Y'),
            default           => 'Todos os registros',
        };

        // Registrar log de geração de relatório
        activity()
            ->causedBy(Auth::user())
            ->performedOn($associate)
            ->withProperties([
                'tenant_id'        => $tenantId,
                'report_type'      => 'associate_deliveries',
                'associate_id'     => $associate->id,
                'period'           => $period,
                'deliveries_count' => $deliveries->count(),
            ])
            ->log('report.generate_individual');

        return view('reports.associate-deliveries', [
            'tenant'         => $tenant,
            'associate'      => $associate,
            'deliveries'     => $deliveries,
            'period'         => $period,
            'reportType'     => 'Entregas por Associado',
            'generatedBy'    => Auth::user()?->name,
            'primaryColor'   => $tenant?->primary_color ?? '#1a4a7a',
            'secondaryColor' => $tenant?->secondary_color ?? '#2d6a4f',
            'accentColor'    => '#e8f4f8',
            'notes'          => $request->query('notes'),
        ]);
    }

    /**
     * Verifica se o usuário tem permissão para gerar relatórios.
     */
    private function authorizeReport(): void
    {
        $user = Auth::user();

        if (! $user) {
            abort(401);
        }

        // Super admin não gera relatórios operacionais de tenant
        if ($user->hasRole('super_admin') && ! session('tenant_id')) {
            abort(403, 'Super Admin não tem acesso a relatórios operacionais de tenant.');
        }

        // Usuário deve ter permissão ou ser admin do tenant
        if (! $user->can('report.generate_individual') && ! $user->can('report.generate')) {
            // Fallback: admins do painel têm acesso implícito
            if (! $user->hasAnyRole(['admin', 'super_admin', 'orgadmin'])) {
                abort(403, 'Sem permissão para gerar relatórios.');
            }
        }
    }
}
