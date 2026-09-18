<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_version_fields', function (Blueprint $table): void {
            if (! Schema::hasColumn('service_version_fields', 'evidence_for_field')) {
                $table->string('evidence_for_field', 80)->nullable()->after('conditional_rule');
            }
        });
        Schema::table('expenses', function (Blueprint $table): void {
            if (! Schema::hasColumn('expenses', 'origin_module')) {
                $table->string('origin_module', 40)->nullable()->after('expenseable_id')->index();
            }
        });
        DB::table('expenses')->whereIn('expenseable_type', [\App\Models\ServiceOrder::class, \App\Models\ServiceExecution::class])->update(['origin_module' => 'services']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $operate = Permission::firstOrCreate(['name' => 'operate_all_service_orders_portal', 'guard_name' => 'web']);
        $expenses = Permission::firstOrCreate(['name' => 'manage_service_expenses', 'guard_name' => 'web']);
        $approve = Permission::firstOrCreate(['name' => 'approve_service_execution', 'guard_name' => 'web']);
        Role::query()->whereIn('name', ['super_admin', 'admin', 'financeiro', 'tesoureiro'])->get()
            ->each(fn (Role $role) => $role->givePermissionTo([$operate, $expenses, $approve]));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table('service_version_fields', function (Blueprint $table): void {
            if (Schema::hasColumn('service_version_fields', 'evidence_for_field')) {
                $table->dropColumn('evidence_for_field');
            }
        });
        Schema::table('expenses', function (Blueprint $table): void {
            if (Schema::hasColumn('expenses', 'origin_module')) {
                $table->dropColumn('origin_module');
            }
        });
        Permission::query()->where('name', 'operate_all_service_orders_portal')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
