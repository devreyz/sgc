<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('production_deliveries', 'original_quantity')) {
            Schema::table('production_deliveries', function (Blueprint $table): void {
                $table->decimal('original_quantity', 12, 4)->nullable()->after('quantity');
            });
        }

        DB::table('production_deliveries')
            ->whereNull('original_quantity')
            ->update(['original_quantity' => DB::raw('quantity')]);

        if (! Schema::hasTable('production_delivery_quantity_adjustments')) {
            Schema::create('production_delivery_quantity_adjustments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id');
                $table->foreignId('production_delivery_id');
                $table->string('kind', 20);
                $table->decimal('quantity', 12, 4);
                $table->decimal('quantity_before', 12, 4);
                $table->decimal('quantity_after', 12, 4);
                $table->text('reason');
                $table->foreignId('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE production_delivery_quantity_adjustments ENGINE=InnoDB');
        }

        if (! Schema::hasIndex('production_delivery_quantity_adjustments', ['tenant_id', 'production_delivery_id'])) {
            Schema::table('production_delivery_quantity_adjustments', function (Blueprint $table): void {
                $table->index(['tenant_id', 'production_delivery_id'], 'delivery_qty_adjustments_tenant_delivery_idx');
            });
        }

        $foreignColumns = collect(Schema::getForeignKeys('production_delivery_quantity_adjustments'))
            ->flatMap(fn (array $foreign): array => $foreign['columns'] ?? [])
            ->map(fn (string $column): string => strtolower($column))
            ->unique()
            ->all();
        Schema::table('production_delivery_quantity_adjustments', function (Blueprint $table) use ($foreignColumns): void {
            if (! in_array('tenant_id', $foreignColumns, true)) {
                $table->foreign('tenant_id', 'delivery_qty_adj_tenant_fk')->references('id')->on('tenants')->restrictOnDelete();
            }
            if (! in_array('production_delivery_id', $foreignColumns, true)) {
                $table->foreign('production_delivery_id', 'delivery_qty_adj_delivery_fk')->references('id')->on('production_deliveries')->restrictOnDelete();
            }
            if (! in_array('created_by', $foreignColumns, true)) {
                $table->foreign('created_by', 'delivery_qty_adj_user_fk')->references('id')->on('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_delivery_quantity_adjustments');
        if (Schema::hasColumn('production_deliveries', 'original_quantity')) {
            Schema::table('production_deliveries', function (Blueprint $table): void {
                $table->dropColumn('original_quantity');
            });
        }
    }
};
