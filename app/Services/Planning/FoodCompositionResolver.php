<?php

namespace App\Services\Planning;

use App\Models\FoodProfile;
use Illuminate\Support\Collection;

final class FoodCompositionResolver
{
    public function load(int $tenantId, array $productIds): Collection
    {
        return FoodProfile::where('tenant_id', $tenantId)->whereIn('product_id', $productIds)
            ->with(['nutrientValues' => fn ($q) => $q->where('tenant_id', $tenantId),
                'nutrientValues.nutrient' => fn ($q) => $q->where('tenant_id', $tenantId)])
            ->orderByDesc('version')->get()->groupBy('product_id');
    }

    public function resolve(Collection $profiles, int $productId, string $date): ?array
    {
        $profile = ($profiles->get($productId) ?? collect())->first(fn ($profile) =>
            $profile->effective_from->format('Y-m-d') <= $date && (! $profile->effective_to || $profile->effective_to->format('Y-m-d') >= $date));
        if (! $profile) { return null; }
        $input = $profile->attributesToArray();
        $input['values'] = $profile->nutrientValues->filter(fn ($value) => $value->nutrient !== null)->map(fn ($value) => [
            'nutrient_id' => $value->nutrient_id, 'code' => $value->nutrient->code, 'name' => $value->nutrient->name,
            'unit' => $value->nutrient->unit, 'amount' => $value->amount,
        ])->all();
        return $input;
    }
}
