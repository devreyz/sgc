<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $role = Role::firstOrCreate(['name' => 'tesoureiro', 'guard_name' => 'web']);
        $permissions = Permission::query()->where(function ($query) {
            foreach (['financial', 'cash::movement', 'bank::account', 'expense', 'revenue', 'chart::account', 'payment', 'receipt'] as $term) {
                $query->orWhere('name', 'like', '%'.$term.'%');
            }
        })->pluck('name');

        if ($permissions->isNotEmpty()) {
            $role->givePermissionTo($permissions);
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::where('name', 'tesoureiro')->delete();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
