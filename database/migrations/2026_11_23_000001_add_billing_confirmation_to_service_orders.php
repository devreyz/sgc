<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_orders', function (Blueprint $table): void {
            $table->timestamp('billed_at')->nullable();
            $table->foreignId('billed_by')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::table('documents', function (Blueprint $table): void {
            $table->string('documentable_type', 191)->nullable()->change();
            $table->unsignedBigInteger('documentable_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Detached documents remain readable after rolling back the billing fields.
        Schema::table('service_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('billed_by');
            $table->dropColumn('billed_at');
        });
    }
};
