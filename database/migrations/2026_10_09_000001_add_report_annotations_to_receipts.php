<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['associate_receipts', 'customer_billing_receipts'] as $table) {
            Schema::table($table, function (Blueprint $schema): void {
                $schema->json('report_annotations')->nullable();
                $schema->string('report_annotations_position', 10)->default('after');
            });
        }
    }

    public function down(): void
    {
        foreach (['associate_receipts', 'customer_billing_receipts'] as $table) {
            Schema::table($table, function (Blueprint $schema): void {
                $schema->dropColumn(['report_annotations', 'report_annotations_position']);
            });
        }
    }
};
