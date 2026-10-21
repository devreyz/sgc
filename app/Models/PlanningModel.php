<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

abstract class PlanningModel extends Model
{
    use BelongsToTenant, LogsActivity;

    // Only validated domain service payloads are passed to these models.
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        $assertMutable = function ($model): void {
            if ($model instanceof PlanningReportSnapshot || $model instanceof PlanningRule || $model instanceof PlanningRuleSet
                || $model instanceof Nutrient || $model instanceof FoodProfile || $model instanceof FoodNutrientValue
                || $model instanceof NutritionTargetSet || $model instanceof NutritionTarget
                || $model instanceof Recipe || $model instanceof RecipeItem) {
                throw new \LogicException('Regras e relatórios emitidos são imutáveis. Crie uma nova versão.');
            }
        };
        static::updating($assertMutable);
        static::deleting($assertMutable);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
