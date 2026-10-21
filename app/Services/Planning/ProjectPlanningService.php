<?php

namespace App\Services\Planning;

use App\Models\BeneficiaryGroup;
use App\Models\BeneficiaryPopulationPeriod;
use App\Models\DistributionConsumptionProfile;
use App\Models\PlanningRuleSet;
use App\Models\Product;
use App\Models\ProductPlanningProfile;
use App\Models\ProductionDelivery;
use App\Models\ProjectPlanningSetting;
use App\Models\SalesProject;
use App\Models\StockMovement;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ProjectPlanningService
{
    public function assertContext(SalesProject $project): void
    {
        abort_unless((int) session('tenant_id') > 0 && (int) session('tenant_id') === (int) $project->tenant_id, 403);
    }

    private function context(SalesProject $project): array
    {
        $this->assertContext($project);
        return ['tenant_id' => $project->tenant_id, 'sales_project_id' => $project->id];
    }

    private function dateRules(): array
    {
        return ['effective_from' => 'required|date_format:Y-m-d', 'effective_to' => 'nullable|date_format:Y-m-d|after_or_equal:effective_from'];
    }

    public function saveSettings(SalesProject $project, array $input): ProjectPlanningSetting
    {
        $context = $this->context($project);
        $data = Validator::make($input, ['default_coverage_days' => 'nullable|integer|min:1|max:3660'])->validate();
        return ProjectPlanningSetting::updateOrCreate($context, $data);
    }

    public function createGroup(SalesProject $project, array $input): BeneficiaryGroup
    {
        $context = $this->context($project);
        $data = Validator::make($input, [
            'name' => 'required|string|max:255', 'type' => 'nullable|string|max:100', 'notes' => 'nullable|string|max:5000',
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('tenant_id', $project->tenant_id)->whereNull('deleted_at')],
        ])->validate();
        if (! empty($data['customer_id'])) {
            app(\App\Services\ProjectDistributionCustomerService::class)->assertAllowed($project, collect([$data['customer_id']]));
        }
        return BeneficiaryGroup::create($context + $data);
    }

    public function addPopulation(SalesProject $project, int $groupId, array $input): BeneficiaryPopulationPeriod
    {
        $this->assertContext($project);
        $data = Validator::make($input, $this->dateRules() + [
            'population' => 'required|integer|min:0|max:100000000', 'source' => 'required|string|max:255', 'notes' => 'nullable|string|max:5000',
        ])->validate();
        return DB::transaction(function () use ($project, $groupId, $data) {
            $group = BeneficiaryGroup::where('tenant_id', $project->tenant_id)->where('sales_project_id', $project->id)->lockForUpdate()->findOrFail($groupId);
            // Consecutive open-ended periods are closed explicitly at the new start.
            $open = $group->populationPeriods()->where('tenant_id', $project->tenant_id)->whereNull('effective_to')->first();
            if ($open && $open->effective_from->format('Y-m-d') < $data['effective_from']) {
                $open->update(['effective_to' => CarbonImmutable::parse($data['effective_from'])->subDay()->toDateString()]);
            }
            $overlap = $group->populationPeriods()->where('tenant_id', $project->tenant_id)
                ->where('effective_from', '<=', $data['effective_to'] ?? '9999-12-31')
                ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $data['effective_from']))->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['effective_from' => 'Já existe população para parte deste período.']);
            }
            return $group->populationPeriods()->create(['tenant_id' => $project->tenant_id] + $data);
        });
    }

    public function addProductProfile(SalesProject $project, array $input): ProductPlanningProfile
    {
        $context = $this->context($project);
        $data = Validator::make($input, $this->dateRules() + [
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('tenant_id', $project->tenant_id)->whereNull('deleted_at')],
            'default_coverage_days' => 'nullable|integer|min:1|max:3660', 'shelf_life_days' => 'nullable|integer|min:1|max:3660',
            'mass_conversion_factor' => ['nullable', 'regex:/^\d{1,10}(\.\d{1,8})?$/', 'numeric', 'gt:0'],
            'storage_condition' => 'nullable|string|max:255', 'source' => 'required|string|max:255',
        ])->validate();
        return DB::transaction(function () use ($context, $data, $project) {
            Product::where('tenant_id', $project->tenant_id)->lockForUpdate()->findOrFail($data['product_id']);
            $query = ProductPlanningProfile::where($context)->where('product_id', $data['product_id']);
            $open = (clone $query)->whereNull('effective_to')->first();
            if ($open && $open->effective_from->format('Y-m-d') < $data['effective_from']) {
                $open->update(['effective_to' => CarbonImmutable::parse($data['effective_from'])->subDay()->toDateString()]);
            }
            if ((clone $query)->where('effective_from', '<=', $data['effective_to'] ?? '9999-12-31')
                ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $data['effective_from']))->exists()) {
                throw ValidationException::withMessages(['effective_from' => 'Já existe configuração do produto para este período.']);
            }
            return ProductPlanningProfile::create($context + $data);
        });
    }

    public function createRuleSet(SalesProject $project, array $input): PlanningRuleSet
    {
        $context = $this->context($project);
        $data = Validator::make($input, $this->dateRules() + [
            'name' => 'required|string|max:255', 'source_type' => ['required', Rule::in(['federal', 'state', 'municipal', 'notice', 'agreement', 'project', 'internal', 'other'])],
            'source_reference' => 'required|string|max:255', 'rules' => 'required|array|min:1|max:100',
            'rules.*' => 'array:name,metric,operator,value,value_max,evaluation_scope,severity,blocking_policy',
            'rules.*.name' => 'required|string|max:255',
            'rules.*.metric' => ['required', Rule::in(['daily_per_capita_g', 'coverage_days_vs_shelf_life'])],
            'rules.*.operator' => ['required', Rule::in(['<=', '>=', '<', '>', '=', 'between'])],
            'rules.*.value' => ['nullable', 'regex:/^\d{1,12}(\.\d{1,8})?$/', 'numeric', 'min:0'],
            'rules.*.value_max' => ['nullable', 'regex:/^\d{1,12}(\.\d{1,8})?$/', 'numeric', 'min:0'],
            'rules.*.evaluation_scope' => ['required', Rule::in(['consumption_cycle', 'report_period'])],
            'rules.*.severity' => ['required', Rule::in(['warning', 'critical', 'info'])],
            'rules.*.blocking_policy' => ['required', Rule::in(['warning_only', 'report_block'])],
        ])->validate();
        foreach ($data['rules'] as $rule) {
            if (($rule['metric'] === 'daily_per_capita_g' && ! isset($rule['value']))
                || ($rule['metric'] === 'coverage_days_vs_shelf_life' && ($rule['evaluation_scope'] !== 'consumption_cycle' || $rule['operator'] !== '<='))
                || ($rule['operator'] === 'between' && (! isset($rule['value_max'], $rule['value']) || bccomp((string) $rule['value_max'], (string) $rule['value'], 8) < 0))) {
                throw ValidationException::withMessages(['rules' => 'Limites ou escopo incompatíveis com a regra.']);
            }
        }
        return DB::transaction(function () use ($context, $data, $project) {
            SalesProject::where('tenant_id', $project->tenant_id)->lockForUpdate()->findOrFail($project->id);
            $rules = $data['rules'];
            unset($data['rules']);
            $version = (int) PlanningRuleSet::where($context)->max('version') + 1;
            // Append-only versions: no update/delete endpoints for issued rules.
            $set = PlanningRuleSet::create($context + $data + ['version' => $version]);
            foreach ($rules as $rule) {
                $set->rules()->create(['tenant_id' => $project->tenant_id] + $rule);
            }
            return $set;
        });
    }

    public function saveConsumption(SalesProject $project, int $distributionId, array $input): DistributionConsumptionProfile
    {
        $context = $this->context($project);
        $data = Validator::make($input, [
            'beneficiary_group_id' => ['nullable', 'integer', Rule::exists('beneficiary_groups', 'id')->where('tenant_id', $project->tenant_id)->where('sales_project_id', $project->id)],
            'count_method' => ['required', Rule::in(['served_people', 'daily_attendance', 'person_days_direct', 'not_applicable'])],
            'served_people' => 'nullable|required_if:count_method,served_people|integer|min:1|max:100000000',
            'service_events' => 'nullable|integer|min:0|max:100000000',
            'person_days_direct' => 'nullable|required_if:count_method,person_days_direct|numeric|gt:0|max:100000000000',
            'coverage_days' => 'nullable|integer|min:1|max:3660',
            'coverage_start_date' => 'nullable|date_format:Y-m-d',
            'coverage_end_date' => 'nullable|date_format:Y-m-d',
            'expires_at' => 'nullable|date_format:Y-m-d',
            'notes' => 'nullable|string|max:5000',
            'daily_audiences' => 'nullable|required_if:count_method,daily_attendance|array|max:3660',
            'daily_audiences.*' => 'array:date,served_people',
            'daily_audiences.*.date' => 'required|date_format:Y-m-d|distinct',
            'daily_audiences.*.served_people' => 'required|integer|min:0|max:100000000',
        ])->validate();
        return DB::transaction(function () use ($project, $distributionId, $context, $data) {
            $distribution = ProductionDelivery::where($context)->whereNotNull('parent_delivery_id')->lockForUpdate()->findOrFail($distributionId);
            $reason = app(DistributionPlanningContext::class)->reason($distribution);
            if ($reason) {
                throw ValidationException::withMessages(['distribution_id' => ConsumptionReportService::reasonLabel($reason)]);
            }
            if (! empty($data['beneficiary_group_id'])) {
                $group = BeneficiaryGroup::where($context)->findOrFail($data['beneficiary_group_id']);
                if ($group->customer_id && (int) $group->customer_id !== (int) $distribution->customer_id) {
                    throw ValidationException::withMessages(['beneficiary_group_id' => 'Grupo vinculado a outra unidade recebedora.']);
                }
            }
            $date = $distribution->delivery_date->format('Y-m-d');
            $productProfile = ProductPlanningProfile::where($context)->where('product_id', $distribution->product_id)
                ->where('effective_from', '<=', $date)->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date))->first();
            $product = Product::where('tenant_id', $project->tenant_id)->findOrFail($distribution->product_id);
            $settings = ProjectPlanningSetting::where($context)->first();
            $coverageSource = ! empty($data['coverage_end_date']) ? 'explicit_date_range'
                : (! empty($data['coverage_days']) ? 'explicit_days' : ($productProfile?->default_coverage_days ? 'product_default' : ($settings?->default_coverage_days ? 'project_default' : null)));
            $days = $data['coverage_days'] ?? $productProfile?->default_coverage_days ?? $settings?->default_coverage_days;
            $start = $data['coverage_start_date'] ?? $date;
            $end = $data['coverage_end_date'] ?? ($days ? CarbonImmutable::parse($start)->addDays($days - 1)->toDateString() : null);
            if ($end && ($end < $start || CarbonImmutable::parse($start)->diffInDays(CarbonImmutable::parse($end)) > 3659)) {
                throw ValidationException::withMessages(['coverage_end_date' => 'Janela de consumo inválida ou superior a 3660 dias.']);
            }
            if ($days && ! empty($data['coverage_end_date']) && (int) CarbonImmutable::parse($start)->diffInDays(CarbonImmutable::parse($end)) + 1 !== (int) $days && ! empty($data['coverage_days'])) {
                throw ValidationException::withMessages(['coverage_days' => 'Dias e intervalo de cobertura são incompatíveis.']);
            }
            $daily = $data['count_method'] === 'daily_attendance' ? ($data['daily_audiences'] ?? []) : [];
            foreach ($daily as $row) {
                if (! $end || $row['date'] < $start || $row['date'] > $end) {
                    throw ValidationException::withMessages(['daily_audiences' => 'Atendimento diário fora da janela de consumo.']);
                }
            }
            // Reuse real stock expiry when the reception has a stock movement.
            $reception = ProductionDelivery::where($context)->findOrFail($distribution->parent_delivery_id);
            $expiry = null;
            if ($reception->stock_movement_id) {
                $expiry = StockMovement::where('tenant_id', $project->tenant_id)->where('product_id', $distribution->product_id)
                    ->find($reception->stock_movement_id)?->expiry_date?->format('Y-m-d');
            }
            unset($data['coverage_days'], $data['daily_audiences']);
            $data['served_people'] = $data['count_method'] === 'served_people' ? ($data['served_people'] ?? null) : null;
            $data['person_days_direct'] = $data['count_method'] === 'person_days_direct' ? ($data['person_days_direct'] ?? null) : null;
            $data['coverage_start_date'] = $start;
            $data['coverage_end_date'] = $end;
            $data['coverage_source'] = $coverageSource;
            $shelfLife = $productProfile?->shelf_life_days ?? $product->shelf_life_days;
            $manualExpiry = $data['expires_at'] ?? null;
            $estimatedExpiry = $shelfLife ? CarbonImmutable::parse($date)->addDays($shelfLife - 1)->toDateString() : null;
            $data['expires_at'] = $expiry ?? $manualExpiry ?? $estimatedExpiry;
            $data['expiry_source'] = $expiry ? 'stock' : ($manualExpiry ? 'manual' : ($estimatedExpiry ? 'estimated_shelf_life' : null));
            $data['shelf_life_days_snapshot'] = $shelfLife;
            $data['mass_conversion_factor_snapshot'] = $productProfile?->mass_conversion_factor;
            $data['updated_by'] = auth()->id();
            if (\Illuminate\Support\Facades\Schema::hasColumn('distribution_consumption_profiles', 'customer_id_snapshot')) {
                $data['customer_id_snapshot'] = $distribution->customer_id;
                $data['product_id_snapshot'] = $distribution->product_id;
            }
            $profile = DistributionConsumptionProfile::firstOrNew($context + ['distribution_id' => $distribution->id]);
            if (! $profile->exists) {
                $profile->created_by = auth()->id();
            }
            $profile->fill($data)->save();
            $oldDaily = $profile->dailyAudiences()->where('tenant_id', $project->tenant_id)->get()->map(fn ($row) => ['date' => $row->date->format('Y-m-d'), 'served_people' => $row->served_people])->all();
            if ($oldDaily !== $daily) {
                activity()->useLog('planning')->performedOn($profile)
                    ->withProperties(['tenant_id' => $project->tenant_id, 'old_daily_audiences' => $oldDaily, 'daily_audiences' => $daily])
                    ->tap(fn ($activity) => $activity->tenant_id = $project->tenant_id)
                    ->log('Atendimento diário atualizado');
            }
            $profile->dailyAudiences()->where('tenant_id', $project->tenant_id)->delete();
            foreach ($daily as $row) {
                $profile->dailyAudiences()->create(['tenant_id' => $project->tenant_id] + $row);
            }
            return $profile;
        });
    }
}
