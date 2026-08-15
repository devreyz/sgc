<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_projects', function (Blueprint $table): void {
            $table->date('member_payment_forecast_date')->nullable()->after('end_date');
            $table->string('member_payment_forecast_note', 255)->nullable()->after('member_payment_forecast_date');
        });
    }

    public function down(): void
    {
        Schema::table('sales_projects', function (Blueprint $table): void {
            $table->dropColumn(['member_payment_forecast_date', 'member_payment_forecast_note']);
        });
    }
};
