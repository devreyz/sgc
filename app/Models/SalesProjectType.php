<?php

namespace App\Models;

use App\Enums\ProjectType;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SalesProjectType extends Model
{
    use BelongsToTenant;

    private static array $optionCache = [];

    private static array $colorCache = [];

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'color',
        'description',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $type): void {
            $type->slug = Str::slug($type->slug ?: $type->name, '_');

            if ($type->slug === '' || array_key_exists($type->slug, self::builtInOptions())) {
                throw new \DomainException('Use um identificador exclusivo, diferente dos tipos padrao do sistema.');
            }
        });

        static::updating(function (self $type): void {
            if (! $type->isDirty('slug')) {
                return;
            }

            $inUse = SalesProject::query()
                ->where('tenant_id', $type->tenant_id)
                ->where('type', $type->getOriginal('slug'))
                ->exists();

            if ($inUse) {
                throw new \DomainException('O identificador nao pode ser alterado porque este tipo ja esta em uso.');
            }
        });

        static::deleting(function (self $type): void {
            $inUse = SalesProject::query()
                ->where('tenant_id', $type->tenant_id)
                ->where('type', $type->slug)
                ->exists();

            if ($inUse) {
                throw new \DomainException('Este tipo esta em uso e nao pode ser excluido. Desative-o para impedir novos projetos.');
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function builtInOptions(): array
    {
        return collect(ProjectType::cases())
            ->mapWithKeys(fn (ProjectType $type) => [$type->value => $type->getLabel()])
            ->all();
    }

    public static function options(?int $tenantId, bool $activeOnly = true): array
    {
        $cacheKey = ($tenantId ?: 0).':'.(int) $activeOnly;
        if (isset(self::$optionCache[$cacheKey])) {
            return self::$optionCache[$cacheKey];
        }

        $custom = $tenantId
            ? static::query()
                ->where('tenant_id', $tenantId)
                ->when($activeOnly, fn ($query) => $query->where('is_active', true))
                ->orderBy('name')
                ->pluck('name', 'slug')
                ->all()
            : [];

        return self::$optionCache[$cacheKey] = self::builtInOptions() + $custom;
    }

    public static function labelFor(?string $value, ?int $tenantId): string
    {
        if (! $value) {
            return 'Nao informado';
        }

        return self::options($tenantId, false)[$value] ?? Str::headline($value);
    }

    public static function colorFor(?string $value, ?int $tenantId): string
    {
        $builtIn = collect(ProjectType::cases())->first(fn (ProjectType $type) => $type->value === $value);
        if ($builtIn) {
            return (string) $builtIn->getColor();
        }

        if (! $tenantId) {
            return 'gray';
        }

        if (! isset(self::$colorCache[$tenantId])) {
            self::$colorCache[$tenantId] = static::query()
                ->where('tenant_id', $tenantId)
                ->pluck('color', 'slug')
                ->all();
        }

        return (string) (self::$colorCache[$tenantId][$value] ?? 'gray');
    }
}
