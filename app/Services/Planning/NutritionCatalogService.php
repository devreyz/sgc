<?php

namespace App\Services\Planning;

use App\Models\BeneficiaryGroup;
use App\Models\FoodProfile;
use App\Models\Nutrient;
use App\Models\NutritionTargetSet;
use App\Models\Product;
use App\Models\SalesProject;
use App\Services\ProjectDistributionCustomerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class NutritionCatalogService
{
    public const DECIMAL = '/^\d{1,12}(\.\d{1,8})?$/';

    public function __construct(private ProjectPlanningService $planning) {}

    public function createNutrient(SalesProject $project, array $input): Nutrient
    {
        $this->planning->assertContext($project);
        $data = Validator::make($input, [
            'code' => ['required', 'regex:/^[a-z][a-z0-9_]{0,79}$/', Rule::unique('nutrients')->where('tenant_id', $project->tenant_id)],
            'name' => 'required|string|max:255', 'unit' => ['required', Rule::in(['g', 'mg', 'mcg', 'kcal', 'kJ'])],
        ])->validate();
        // Dictionary units are immutable: a unit change must use a new code.
        return Nutrient::create(['tenant_id' => $project->tenant_id] + $data);
    }

    public function createFoodProfile(SalesProject $project, array $input): FoodProfile
    {
        $this->planning->assertContext($project);
        $data = Validator::make($input, [
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('tenant_id', $project->tenant_id)->whereNull('deleted_at')],
            'name' => 'required|string|max:255',
            'reference_quantity_g' => ['required', 'regex:'.self::DECIMAL, 'numeric', 'gt:0', 'max:1000000000'],
            'edible_fraction' => ['required', 'regex:'.self::DECIMAL, 'numeric', 'gt:0', 'max:1'],
            'source' => 'required|string|max:255', 'source_version' => 'required|string|max:255', 'notes' => 'nullable|string|max:5000',
            'effective_from' => 'required|date_format:Y-m-d', 'effective_to' => 'nullable|date_format:Y-m-d|after_or_equal:effective_from',
            'values' => 'required|array|min:1|max:100', 'values.*' => 'array:nutrient_id,amount',
            'values.*.nutrient_id' => ['required', 'integer', 'distinct', Rule::exists('nutrients', 'id')->where('tenant_id', $project->tenant_id)],
            'values.*.amount' => ['required', 'regex:'.self::DECIMAL, 'numeric', 'min:0'],
        ])->validate();
        return DB::transaction(function () use ($project, $data) {
            Product::where('tenant_id', $project->tenant_id)->lockForUpdate()->findOrFail($data['product_id']);
            $version = (int) FoodProfile::where('tenant_id', $project->tenant_id)->where('product_id', $data['product_id'])->max('version') + 1;
            $values = $data['values'];
            unset($data['values']);
            $profile = FoodProfile::create(['tenant_id' => $project->tenant_id, 'version' => $version] + $data);
            foreach ($values as $value) {
                $profile->nutrientValues()->create(['tenant_id' => $project->tenant_id] + $value);
            }
            return $profile;
        });
    }

    public function createTargetSet(SalesProject $project, array $input): NutritionTargetSet
    {
        $this->planning->assertContext($project);
        $data = Validator::make($input, [
            'beneficiary_group_id' => ['required', 'integer', Rule::exists('beneficiary_groups', 'id')->where('tenant_id', $project->tenant_id)->where('sales_project_id', $project->id)],
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->where('tenant_id', $project->tenant_id)->whereNull('deleted_at')],
            'name' => 'required|string|max:255',
            'source_type' => ['required', Rule::in(['federal', 'state', 'municipal', 'notice', 'agreement', 'project', 'internal', 'other'])],
            'source_reference' => 'required|string|max:255', 'effective_from' => 'required|date_format:Y-m-d',
            'effective_to' => 'nullable|date_format:Y-m-d|after_or_equal:effective_from',
            'targets' => 'required|array|min:1|max:100', 'targets.*' => 'array:nutrient_id,min_value,max_value,severity',
            'targets.*.nutrient_id' => ['required', 'integer', 'distinct', Rule::exists('nutrients', 'id')->where('tenant_id', $project->tenant_id)],
            'targets.*.min_value' => ['nullable', 'regex:'.self::DECIMAL, 'numeric', 'min:0'],
            'targets.*.max_value' => ['nullable', 'regex:'.self::DECIMAL, 'numeric', 'min:0'],
            'targets.*.severity' => ['required', Rule::in(['info', 'warning', 'critical'])],
        ])->validate();
        app(ProjectDistributionCustomerService::class)->assertAllowed($project, [$data['customer_id']]);
        foreach ($data['targets'] as $target) {
            if ((! isset($target['min_value']) && ! isset($target['max_value']))
                || (isset($target['min_value'], $target['max_value']) && bccomp((string) $target['min_value'], (string) $target['max_value'], 8) > 0)) {
                throw ValidationException::withMessages(['targets' => 'Informe ao menos um limite e mantenha mínimo ≤ máximo.']);
            }
        }
        return DB::transaction(function () use ($project, $data) {
            $group = BeneficiaryGroup::where('tenant_id', $project->tenant_id)->where('sales_project_id', $project->id)->lockForUpdate()->findOrFail($data['beneficiary_group_id']);
            if ($group->customer_id && (int) $group->customer_id !== (int) $data['customer_id']) {
                throw ValidationException::withMessages(['customer_id' => 'O grupo pertence a outra unidade recebedora.']);
            }
            $context = ['tenant_id' => $project->tenant_id, 'sales_project_id' => $project->id,
                'beneficiary_group_id' => $group->id, 'customer_id' => $data['customer_id']];
            $version = (int) NutritionTargetSet::where($context)->max('version') + 1;
            $targets = $data['targets'];
            unset($data['targets']);
            $set = NutritionTargetSet::create($context + ['version' => $version] + $data);
            foreach ($targets as $target) {
                $set->targets()->create(['tenant_id' => $project->tenant_id] + $target);
            }
            return $set;
        });
    }
}
