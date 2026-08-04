<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_receipts', function (Blueprint $table): void {
            $table->decimal('manual_amount', 15, 2)->nullable()->after('total_amount');
        });
    }

    public function down(): void
    {
        Schema::table('financial_receipts', function (Blueprint $table): void {
            $table->dropColumn('manual_amount');
        });
    }
};
