<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('tenant_user')) {
            return;
        }

        Schema::table('tenant_user', function (Blueprint $table) {
            if (!Schema::hasColumn('tenant_user', 'tenant_name')) {
                $table->string('tenant_name')->nullable()->after('tenant_id');
            }
            if (!Schema::hasColumn('tenant_user', 'tenant_password')) {
                $table->string('tenant_password')->nullable()->after('tenant_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('tenant_user')) {
            return;
        }

        Schema::table('tenant_user', function (Blueprint $table) {
            if (Schema::hasColumn('tenant_user', 'tenant_password')) {
                $table->dropColumn('tenant_password');
            }
            if (Schema::hasColumn('tenant_user', 'tenant_name')) {
                $table->dropColumn('tenant_name');
            }
        });
    }
};
