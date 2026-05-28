<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_deliveries', function (Blueprint $table) {
            // Só se aplica a distribuições (parent_delivery_id != NULL)
            $table->string('billing_status', 20)->default('unbilled')->after('status');
            $table->unsignedBigInteger('distribution_billing_id')->nullable()->after('billing_status');

            $table->index('billing_status');
            $table->index('distribution_billing_id');
        });
    }

    public function down(): void
    {
        Schema::table('production_deliveries', function (Blueprint $table) {
            $table->dropIndex(['billing_status']);
            $table->dropIndex(['distribution_billing_id']);
            $table->dropColumn(['billing_status', 'distribution_billing_id']);
        });
    }
};
