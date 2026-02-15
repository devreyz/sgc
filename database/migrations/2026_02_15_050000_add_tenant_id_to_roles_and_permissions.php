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
        // Adiciona tenant_id às tabelas de Spatie Permission
        // Isso permite que cada organização tenha suas próprias roles e permissions
        
        if (Schema::hasTable('roles') && !Schema::hasColumn('roles', 'tenant_id')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('guard_name')->index();
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            });

            // Adiciona índice composto para garantir unicidade de nome por tenant
            Schema::table('roles', function (Blueprint $table) {
                $table->dropUnique(['name', 'guard_name']);
                $table->unique(['name', 'guard_name', 'tenant_id']);
            });
        }

        if (Schema::hasTable('permissions') && !Schema::hasColumn('permissions', 'tenant_id')) {
            Schema::table('permissions', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('guard_name')->index();
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            });

            // Adiciona índice composto para garantir unicidade de nome por tenant
            Schema::table('permissions', function (Blueprint $table) {
                $table->dropUnique(['name', 'guard_name']);
                $table->unique(['name', 'guard_name', 'tenant_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('roles') && Schema::hasColumn('roles', 'tenant_id')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->dropUnique(['name', 'guard_name', 'tenant_id']);
                $table->unique(['name', 'guard_name']);
                $table->dropForeign(['tenant_id']);
                $table->dropColumn('tenant_id');
            });
        }

        if (Schema::hasTable('permissions') && Schema::hasColumn('permissions', 'tenant_id')) {
            Schema::table('permissions', function (Blueprint $table) {
                $table->dropUnique(['name', 'guard_name', 'tenant_id']);
                $table->unique(['name', 'guard_name']);
                $table->dropForeign(['tenant_id']);
                $table->dropColumn('tenant_id');
            });
        }
    }
};
