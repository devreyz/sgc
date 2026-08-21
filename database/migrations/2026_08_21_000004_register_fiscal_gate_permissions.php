<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $names = ['view_accounting_fiscal_queue', 'prepare_accounting_fiscal', 'view_accounting_fiscal_settings', 'manage_accounting_fiscal_settings'];
        foreach ($names as $name) {
            DB::table('permissions')->updateOrInsert(['name' => $name, 'guard_name' => 'web'], ['created_at' => now(), 'updated_at' => now()]);
        }
        $roles = DB::table('roles')->whereIn('name', ['super_admin', 'admin', 'financeiro', 'tesoureiro', 'contador'])->get();
        $permissions = DB::table('permissions')->whereIn('name', $names)->get()->keyBy('name');
        foreach ($roles as $role) {
            foreach ($names as $name) {
                if ($role->name === 'contador' && $name === 'manage_accounting_fiscal_settings') {
                    continue;
                }
                DB::table('role_has_permissions')->updateOrInsert(['role_id' => $role->id, 'permission_id' => $permissions[$name]->id]);
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->whereIn('name', ['view_accounting_fiscal_queue', 'prepare_accounting_fiscal', 'view_accounting_fiscal_settings', 'manage_accounting_fiscal_settings'])->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
