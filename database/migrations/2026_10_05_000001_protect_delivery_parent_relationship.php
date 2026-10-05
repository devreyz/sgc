<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('production_deliveries', 'parent_delivery_id')) {
            return;
        }

        Schema::table('production_deliveries', function (Blueprint $table): void {
            $table->dropForeign(['parent_delivery_id']);
            $table->foreign('parent_delivery_id')
                ->references('id')
                ->on('production_deliveries')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('production_deliveries', 'parent_delivery_id')) {
            return;
        }

        Schema::table('production_deliveries', function (Blueprint $table): void {
            $table->dropForeign(['parent_delivery_id']);
            $table->foreign('parent_delivery_id')
                ->references('id')
                ->on('production_deliveries')
                ->nullOnDelete();
        });
    }
};
