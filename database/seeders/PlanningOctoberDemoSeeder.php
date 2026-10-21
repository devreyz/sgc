<?php

namespace Database\Seeders;

use App\Models\Associate;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductionDelivery;
use App\Models\SalesProject;
use App\Models\Tenant;
use App\Services\Planning\ProjectPlanningService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Explicit, local-only fixture. Never called from DatabaseSeeder. */
class PlanningOctoberDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) || ! $this->command) {
            throw new \LogicException('Fixture disponível somente em desenvolvimento local.');
        }
        $tenant = Tenant::findOrFail((int) $this->command->ask('ID da organização de desenvolvimento'));
        $originalTenant = session('tenant_id');
        session(['tenant_id' => $tenant->id]);
        try {
            $associate = Associate::where('tenant_id', $tenant->id)->findOrFail((int) $this->command->ask('ID de um produtor desta organização'));
            $customer = Customer::where('tenant_id', $tenant->id)->findOrFail((int) $this->command->ask('ID de uma unidade recebedora desta organização'));
            $product = Product::where('tenant_id', $tenant->id)->whereIn('unit', ['kg', 'KG'])->findOrFail((int) $this->command->ask('ID de um produto em kg (ex.: Banana)'));
            $project = DB::transaction(function () use ($tenant, $associate, $customer, $product) {
                return Model::unguarded(function () use ($tenant, $associate, $customer, $product) {
                    $project = SalesProject::create(['tenant_id' => $tenant->id, 'title' => 'PAA Teste Outubro 2026',
                        'type' => 'paa', 'customer_id' => $customer->id, 'start_date' => '2026-09-01', 'end_date' => '2026-10-31',
                        'reference_year' => 2026, 'status' => 'active', 'total_value' => null, 'admin_fee_percentage' => 0,
                        'allow_any_product' => true, 'restrict_participants' => false]);
                    $planning = app(ProjectPlanningService::class);
                    $planning->saveSettings($project, ['default_coverage_days' => 7]);
                    $group = $planning->createGroup($project, ['name' => 'Público de demonstração', 'customer_id' => $customer->id]);
                    $planning->addPopulation($project, $group->id, ['population' => 3000, 'effective_from' => '2026-01-01', 'source' => 'Fixture de desenvolvimento']);
                    foreach ([['2026-09-29', '350', 500], ['2026-10-06', '360', 520]] as [$date, $quantity, $people]) {
                        $base = ['tenant_id' => $tenant->id, 'sales_project_id' => $project->id, 'associate_id' => $associate->id,
                            'product_id' => $product->id, 'delivery_date' => $date, 'quantity' => $quantity, 'status' => 'approved'];
                        $parent = ProductionDelivery::create($base);
                        $child = ProductionDelivery::create($base + ['parent_delivery_id' => $parent->id, 'customer_id' => $customer->id, 'unit_price' => '1']);
                        $planning->saveConsumption($project, $child->id, ['count_method' => 'served_people', 'served_people' => $people, 'coverage_days' => 7, 'beneficiary_group_id' => $group->id]);
                    }
                    return $project;
                });
            });
            $this->command->info('Projeto de demonstração criado: #'.$project->id.'. Outubro: 610 kg / 6.140 pessoa-dias ≈ 99,3485 g/pessoa/dia.');
        } finally {
            session(['tenant_id' => $originalTenant]);
        }
    }
}
